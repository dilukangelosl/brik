<?php
namespace Brik\Content;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creating and querying posts of content types with their field values. Used by the MCP
 * tools and available to form actions and listings.
 */
final class Entries {

	/**
	 * Create one post with fields and terms, as the current user.
	 *
	 * @param string $post_type Post type.
	 * @param array  $e         title, content, excerpt, status, slug, date, parent, menu_order,
	 *                          author, fields (name => value), terms (taxonomy => names|ids),
	 *                          featured_image (URL or attachment id).
	 * @return array|WP_Error id, url, edit_url, status, warnings.
	 */
	public static function create( $post_type, array $e ) {
		$post_type = sanitize_key( $post_type );
		$object    = get_post_type_object( $post_type );
		if ( ! $object ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_entries_type', sprintf( __( 'Unknown post type "%s".', 'brik-builder' ), $post_type ) );
		}
		if ( ! current_user_can( $object->cap->create_posts ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to create this content.', 'brik-builder' ) );
		}
		$warnings = array();
		$status   = isset( $e['status'] ) ? sanitize_key( $e['status'] ) : 'draft';
		if ( ! in_array( $status, array( 'draft', 'pending', 'publish', 'private', 'future' ), true ) ) {
			$status = 'draft';
		}
		if ( in_array( $status, array( 'publish', 'private', 'future' ), true ) && ! current_user_can( $object->cap->publish_posts ) ) {
			$warnings[] = __( 'You cannot publish this content, so it was submitted for review.', 'brik-builder' );
			$status     = 'pending';
		}

		$data = array(
			'post_type'    => $post_type,
			'post_status'  => $status,
			'post_title'   => isset( $e['title'] ) ? sanitize_text_field( (string) $e['title'] ) : '',
			'post_content' => isset( $e['content'] ) ? wp_kses_post( (string) $e['content'] ) : '',
			'post_excerpt' => isset( $e['excerpt'] ) ? sanitize_textarea_field( (string) $e['excerpt'] ) : '',
		);
		if ( ! empty( $e['slug'] ) ) {
			$data['post_name'] = sanitize_title( (string) $e['slug'] );
		}
		if ( ! empty( $e['date'] ) && false !== strtotime( (string) $e['date'] ) ) {
			$data['post_date'] = gmdate( 'Y-m-d H:i:s', strtotime( (string) $e['date'] ) );
		}
		if ( ! empty( $e['parent'] ) && get_post( (int) $e['parent'] ) ) {
			$data['post_parent'] = (int) $e['parent'];
		}
		if ( isset( $e['menu_order'] ) ) {
			$data['menu_order'] = (int) $e['menu_order'];
		}
		if ( ! empty( $e['author'] ) ) {
			if ( current_user_can( $object->cap->edit_others_posts ) && get_userdata( (int) $e['author'] ) ) {
				$data['post_author'] = (int) $e['author'];
			} else {
				$warnings[] = __( 'The author was not changed: you can only create content as yourself.', 'brik-builder' );
			}
		}

		$id = wp_insert_post( wp_slash( $data ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$warnings = array_merge( $warnings, self::apply( $id, $e ) );
		$post     = get_post( $id );
		return array(
			'id'       => $id,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'url'      => get_permalink( $id ),
			'edit_url' => get_edit_post_link( $id, 'raw' ),
			'warnings' => $warnings,
		);
	}

	/**
	 * Save fields, terms and the featured image on an existing post.
	 *
	 * @return string[] Warnings.
	 */
	public static function apply( $id, array $e ) {
		$warnings = array();
		$post     = get_post( $id );
		if ( ! $post ) {
			return array( __( 'Post not found.', 'brik-builder' ) );
		}

		if ( ! empty( $e['fields'] ) && is_array( $e['fields'] ) ) {
			$known = Registry::fields_for_post_type( $post->post_type );
			foreach ( $e['fields'] as $name => $value ) {
				$name = (string) $name;
				if ( ! isset( $known[ $name ] ) ) {
					/* translators: 1: field name, 2: post type */
					$warnings[] = sprintf( __( 'No field "%1$s" on %2$s; skipped.', 'brik-builder' ), $name, $post->post_type );
					continue;
				}
				$field = $known[ $name ];
				$value = self::prepare_media( $field, $value, $id, $warnings );
				$errs  = Fields::validate( $field, $value );
				if ( $errs ) {
					$warnings = array_merge( $warnings, $errs );
					continue;
				}
				Values::save( $field, Fields::sanitize( $field, $value ), Values::target( $id ) );
			}
		}

		if ( ! empty( $e['terms'] ) && is_array( $e['terms'] ) ) {
			foreach ( $e['terms'] as $tax => $terms ) {
				$tax = sanitize_key( (string) $tax );
				if ( ! taxonomy_exists( $tax ) || ! is_object_in_taxonomy( $post->post_type, $tax ) ) {
					/* translators: %s: taxonomy */
					$warnings[] = sprintf( __( 'Taxonomy "%s" is not available for this post type.', 'brik-builder' ), $tax );
					continue;
				}
				$taxonomy = get_taxonomy( $tax );
				if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) {
					/* translators: %s: taxonomy */
					$warnings[] = sprintf( __( 'You cannot assign %s terms.', 'brik-builder' ), $tax );
					continue;
				}
				$list = array();
				foreach ( (array) $terms as $term ) {
					if ( is_numeric( $term ) ) {
						$list[] = (int) $term;
					} elseif ( is_string( $term ) && '' !== trim( $term ) ) {
						$existing = get_term_by( 'name', trim( $term ), $tax );
						if ( ! $existing ) {
							$existing = get_term_by( 'slug', sanitize_title( $term ), $tax );
						}
						if ( $existing ) {
							$list[] = (int) $existing->term_id;
						} elseif ( current_user_can( $taxonomy->cap->edit_terms ) ) {
							$made = wp_insert_term( sanitize_text_field( trim( $term ) ), $tax );
							if ( ! is_wp_error( $made ) ) {
								$list[] = (int) $made['term_id'];
							}
						} else {
							/* translators: %s: term name */
							$warnings[] = sprintf( __( 'Term "%s" does not exist.', 'brik-builder' ), $term );
						}
					}
				}
				wp_set_object_terms( $id, $list, $tax, false );
			}
		}

		if ( ! empty( $e['featured_image'] ) ) {
			$thumb = self::attachment( $e['featured_image'], $id, $warnings );
			if ( $thumb && wp_attachment_is_image( $thumb ) ) {
				set_post_thumbnail( $id, $thumb );
			}
		}
		return $warnings;
	}

	/**
	 * Image, file and gallery values given as URLs are downloaded into the media library.
	 */
	private static function prepare_media( array $field, $value, $post_id, array &$warnings ) {
		if ( in_array( $field['type'], array( 'image', 'file' ), true ) ) {
			if ( is_array( $value ) && isset( $value['url'] ) && empty( $value['id'] ) ) {
				$value = $value['url'];
			}
			return is_string( $value ) && ! is_numeric( $value ) ? self::attachment( $value, $post_id, $warnings ) : $value;
		}
		if ( 'gallery' === $field['type'] && is_array( $value ) ) {
			$out = array();
			foreach ( $value as $item ) {
				$out[] = is_string( $item ) && ! is_numeric( $item ) ? self::attachment( $item, $post_id, $warnings ) : $item;
			}
			return array_filter( $out );
		}
		if ( 'repeater' === $field['type'] && is_array( $value ) ) {
			foreach ( $value as $i => $row ) {
				if ( is_array( $row ) ) {
					foreach ( $field['options']['sub_fields'] as $sub ) {
						if ( isset( $row[ $sub['name'] ] ) ) {
							$value[ $i ][ $sub['name'] ] = self::prepare_media( $sub, $row[ $sub['name'] ], $post_id, $warnings );
						}
					}
				}
			}
		}
		if ( 'group' === $field['type'] && is_array( $value ) ) {
			foreach ( $field['options']['sub_fields'] as $sub ) {
				if ( isset( $value[ $sub['name'] ] ) ) {
					$value[ $sub['name'] ] = self::prepare_media( $sub, $value[ $sub['name'] ], $post_id, $warnings );
				}
			}
		}
		return $value;
	}

	/**
	 * Attachment id from an id or a public URL (sideloaded).
	 */
	public static function attachment( $value, $post_id, array &$warnings ) {
		if ( is_numeric( $value ) ) {
			return 'attachment' === get_post_type( (int) $value ) ? (int) $value : 0;
		}
		$url = esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			$warnings[] = __( 'Media must be an attachment id or a public http(s) URL.', 'brik-builder' );
			return 0;
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			$warnings[] = __( 'You are not allowed to upload files.', 'brik-builder' );
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			$warnings[] = $tmp->get_error_message();
			return 0;
		}
		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( ! pathinfo( $name, PATHINFO_EXTENSION ) || ! wp_check_filetype( $name )['type'] ) {
			$mime = wp_get_image_mime( $tmp );
			$ext  = $mime ? array_search( $mime, wp_get_mime_types(), true ) : false;
			$name = ( pathinfo( $name, PATHINFO_FILENAME ) ? pathinfo( $name, PATHINFO_FILENAME ) : 'brik-media' ) . ( $ext ? '.' . explode( '|', $ext )[0] : '' );
		}
		$check = wp_check_filetype_and_ext( $tmp, $name );
		if ( empty( $check['type'] ) ) {
			wp_delete_file( $tmp );
			$warnings[] = __( 'That file type is not allowed.', 'brik-builder' );
			return 0;
		}
		$id = media_handle_sideload(
			array(
				'name'     => $check['proper_filename'] ? $check['proper_filename'] : $name,
				'tmp_name' => $tmp,
			),
			(int) $post_id
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			$warnings[] = $id->get_error_message();
			return 0;
		}
		return (int) $id;
	}

	/**
	 * meta_query from listing-style conditions: [{field, compare, value, type}]. compare is one
	 * of = != > >= < <= LIKE NOT LIKE IN NOT IN BETWEEN EXISTS NOT EXISTS; type NUMERIC, DATE,
	 * DATETIME, TIME, CHAR (inferred from the field when omitted).
	 */
	public static function meta_query( array $conditions, $post_type = '' ) {
		$known = $post_type ? Registry::fields_for_post_type( $post_type ) : array();
		$out   = array( 'relation' => 'AND' );
		$ops   = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS' );
		foreach ( $conditions as $c ) {
			if ( ! is_array( $c ) || empty( $c['field'] ) ) {
				continue;
			}
			$name    = sanitize_key( $c['field'] );
			$compare = isset( $c['compare'] ) ? strtoupper( trim( (string) $c['compare'] ) ) : '=';
			$compare = '==' === $compare ? '=' : $compare;
			if ( ! in_array( $compare, $ops, true ) ) {
				$compare = '=';
			}
			$type = isset( $c['type'] ) ? strtoupper( sanitize_key( $c['type'] ) ) : '';
			if ( ! $type && isset( $known[ $name ] ) ) {
				$ft   = $known[ $name ]['type'];
				$type = in_array( $ft, array( 'number', 'range' ), true ) ? 'NUMERIC' : ( 'date' === $ft ? 'DATE' : ( 'datetime' === $ft ? 'DATETIME' : ( 'time' === $ft ? 'TIME' : 'CHAR' ) ) );
			}
			$clause = array(
				'key'     => $name,
				'compare' => $compare,
				'type'    => in_array( $type, array( 'NUMERIC', 'DECIMAL', 'DATE', 'DATETIME', 'TIME', 'CHAR', 'SIGNED', 'UNSIGNED' ), true ) ? ( 'NUMERIC' === $type ? 'DECIMAL(20,6)' : $type ) : 'CHAR',
			);
			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$value = isset( $c['value'] ) ? $c['value'] : '';
				if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
					$value = is_array( $value ) ? $value : array_map( 'trim', explode( ',', (string) $value ) );
					$value = array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
				} else {
					$value = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
				}
				// Lists (checkboxes, relationships) are stored serialized, so equality means "contains".
				if ( isset( $known[ $name ] ) && Fields::is_multiple( $known[ $name ] ) && in_array( $compare, array( '=', '!=' ), true ) ) {
					$clause['compare'] = '=' === $compare ? 'LIKE' : 'NOT LIKE';
					$value             = '"' . $value . '"';
					$clause['type']    = 'CHAR';
				}
				$clause['value'] = $value;
			}
			$out[] = $clause;
		}
		return count( $out ) > 1 ? $out : array();
	}

	/**
	 * Find posts with their field values.
	 *
	 * @param array $a post_type, search, meta ([{field, compare, value, type}]), terms
	 *                 (taxonomy => slugs|ids), status, per_page, page, orderby, order, format.
	 */
	public static function query( array $a ) {
		$type = isset( $a['post_type'] ) ? sanitize_key( $a['post_type'] ) : 'post';
		if ( ! post_type_exists( $type ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_entries_type', sprintf( __( 'Unknown post type "%s".', 'brik-builder' ), $type ) );
		}
		$per   = isset( $a['per_page'] ) ? max( 1, min( 100, (int) $a['per_page'] ) ) : 20;
		$args  = array(
			'post_type'      => $type,
			'posts_per_page' => $per,
			'paged'          => isset( $a['page'] ) ? max( 1, (int) $a['page'] ) : 1,
			'post_status'    => isset( $a['status'] ) && 'any' !== $a['status'] ? sanitize_key( $a['status'] ) : array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'perm'           => 'readable',
			'no_found_rows'  => false,
		);
		if ( ! empty( $a['search'] ) ) {
			$args['s'] = sanitize_text_field( $a['search'] );
		}
		if ( ! empty( $a['meta'] ) && is_array( $a['meta'] ) ) {
			$mq = self::meta_query( $a['meta'], $type );
			if ( $mq ) {
				$args['meta_query'] = $mq; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
		}
		if ( ! empty( $a['terms'] ) && is_array( $a['terms'] ) ) {
			$tq = array( 'relation' => 'AND' );
			foreach ( $a['terms'] as $tax => $terms ) {
				if ( taxonomy_exists( $tax ) ) {
					$terms = (array) $terms;
					$tq[]  = array(
						'taxonomy' => $tax,
						'field'    => is_numeric( reset( $terms ) ) ? 'term_id' : 'slug',
						'terms'    => array_map( 'sanitize_title', $terms ),
					);
				}
			}
			if ( count( $tq ) > 1 ) {
				$args['tax_query'] = $tq; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
		}
		$orderby = isset( $a['orderby'] ) ? (string) $a['orderby'] : 'date';
		if ( in_array( $orderby, array( 'date', 'title', 'menu_order', 'modified', 'rand', 'ID' ), true ) ) {
			$args['orderby'] = $orderby;
		} elseif ( Registry::find_field( $orderby, $type ) ) {
			$field             = Registry::find_field( $orderby, $type );
			$args['meta_key']  = $field['name']; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']   = in_array( $field['type'], array( 'number', 'range' ), true ) ? 'meta_value_num' : 'meta_value';
		}
		$args['order'] = isset( $a['order'] ) && 'ASC' === strtoupper( (string) $a['order'] ) ? 'ASC' : 'DESC';

		$q     = new \WP_Query( $args );
		$items = array();
		foreach ( $q->posts as $post ) {
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$items[] = self::entry( $post );
			}
		}
		return array(
			'items' => $items,
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
		);
	}

	/**
	 * JSON-friendly summary of a post with its stored field values and terms.
	 */
	public static function entry( \WP_Post $post ) {
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$list = get_the_terms( $post, $tax );
			if ( $list && ! is_wp_error( $list ) ) {
				$terms[ $tax ] = wp_list_pluck( $list, 'name' );
			}
		}
		return array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'status'         => $post->post_status,
			'slug'           => $post->post_name,
			'date'           => $post->post_date,
			'url'            => get_permalink( $post ),
			'excerpt'        => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'featured_image' => (string) get_the_post_thumbnail_url( $post, 'full' ),
			'fields'         => Values::all( $post->ID ),
			'terms'          => $terms,
		);
	}
}
