<?php
namespace Brik\Content;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Content definitions: post types, taxonomies and field groups.
 *
 * Definitions saved in the admin live in options; definitions added from code with
 * brik_register_post_type() / brik_register_field_group() are kept in memory, marked
 * "local" and can't be changed through the REST API.
 */
final class Registry {

	const OPT_POST_TYPES = 'brik_post_types';
	const OPT_TAXONOMIES = 'brik_taxonomies';
	const OPT_GROUPS     = 'brik_field_groups';
	const OPT_FLUSH      = 'brik_content_flush';
	const VERSION        = 1;

	const KINDS = array(
		'post_types' => self::OPT_POST_TYPES,
		'taxonomies' => self::OPT_TAXONOMIES,
		'groups'     => self::OPT_GROUPS,
	);

	const WIDTHS = array( 25, 33, 50, 66, 75, 100 );

	private static $local = array(
		'post_types' => array(),
		'taxonomies' => array(),
		'groups'     => array(),
	);

	private static $cache = array();

	/* ---------------------------------------------------------------------
	 * Reading.
	 * ------------------------------------------------------------------- */

	public static function post_types( $active_only = false ) {
		return self::all( 'post_types', $active_only );
	}

	public static function taxonomies( $active_only = false ) {
		return self::all( 'taxonomies', $active_only );
	}

	public static function groups( $active_only = false ) {
		return self::all( 'groups', $active_only );
	}

	public static function get_post_type( $key ) {
		return self::find( 'post_types', $key );
	}

	public static function get_taxonomy( $key ) {
		return self::find( 'taxonomies', $key );
	}

	public static function get_group( $key ) {
		return self::find( 'groups', $key );
	}

	/**
	 * All definitions of one kind (post_types|taxonomies|groups).
	 */
	public static function all_of( $kind ) {
		return isset( self::KINDS[ $kind ] ) ? self::all( $kind ) : array();
	}

	public static function find_any( $kind, $key ) {
		return isset( self::KINDS[ $kind ] ) ? self::find( $kind, $key ) : null;
	}

	private static function find( $kind, $key ) {
		foreach ( self::all( $kind ) as $def ) {
			if ( $def['key'] === $key ) {
				return $def;
			}
		}
		return null;
	}

	/**
	 * Stored plus local definitions. Local ones win on key clashes, since code is the
	 * source of truth for them.
	 */
	private static function all( $kind, $active_only = false ) {
		if ( ! isset( self::$cache[ $kind ] ) ) {
			$out = array();
			foreach ( self::stored( $kind ) as $def ) {
				$def['local']        = false;
				$out[ $def['key'] ] = $def;
			}
			foreach ( self::$local[ $kind ] as $def ) {
				$out[ $def['key'] ] = $def;
			}
			if ( 'groups' === $kind ) {
				uasort(
					$out,
					static function ( $a, $b ) {
						return $a['order'] === $b['order'] ? strcmp( $a['title'], $b['title'] ) : $a['order'] - $b['order'];
					}
				);
			}
			self::$cache[ $kind ] = array_values( $out );
		}
		if ( ! $active_only ) {
			return self::$cache[ $kind ];
		}
		return array_values(
			array_filter(
				self::$cache[ $kind ],
				static function ( $def ) {
					return ! empty( $def['active'] );
				}
			)
		);
	}

	private static function stored( $kind ) {
		$raw = get_option( self::KINDS[ $kind ], array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $def ) {
			if ( is_array( $def ) && ! empty( $def['key'] ) ) {
				// Stored definitions were validated on save; normalizing again fills defaults added later.
				$out[] = self::normalize( $kind, $def );
			}
		}
		return $out;
	}

	public static function reset_cache() {
		self::$cache = array();
		Fields::reset_cache();
	}

	/* ---------------------------------------------------------------------
	 * Local (code) definitions.
	 * ------------------------------------------------------------------- */

	/**
	 * Add a definition from code. Returns the normalized definition or a WP_Error.
	 */
	public static function add_local( $kind, array $def ) {
		if ( ! isset( self::KINDS[ $kind ] ) ) {
			return new WP_Error( 'brik_content_kind', __( 'Unknown definition kind.', 'brik-builder' ) );
		}
		if ( 'groups' === $kind && empty( $def['key'] ) ) {
			// Code-defined groups need a stable key so stored values keep matching.
			$def['key'] = 'group_' . substr( md5( isset( $def['title'] ) ? (string) $def['title'] : wp_json_encode( $def ) ), 0, 10 );
		}
		$def    = self::normalize( $kind, $def );
		$errors = self::validate( $kind, $def, $def['key'], true );
		if ( $errors ) {
			return self::error( $errors );
		}
		$def['local']                        = true;
		self::$local[ $kind ][ $def['key'] ] = $def;
		self::reset_cache();
		return $def;
	}

	public static function remove_local( $kind, $key ) {
		unset( self::$local[ $kind ][ $key ] );
		self::reset_cache();
	}

	public static function is_local( $kind, $key ) {
		return isset( self::$local[ $kind ][ $key ] );
	}

	/* ---------------------------------------------------------------------
	 * Writing.
	 * ------------------------------------------------------------------- */

	public static function save_post_type( array $def, $previous_key = '' ) {
		return self::save( 'post_types', $def, $previous_key );
	}

	public static function save_taxonomy( array $def, $previous_key = '' ) {
		return self::save( 'taxonomies', $def, $previous_key );
	}

	public static function save_group( array $def, $previous_key = '' ) {
		return self::save( 'groups', $def, $previous_key );
	}

	/**
	 * Create or update a definition by key. $previous_key renames an existing one
	 * (posts and terms are not migrated).
	 */
	public static function save( $kind, array $def, $previous_key = '' ) {
		if ( 'groups' === $kind && empty( $def['key'] ) ) {
			$def['key'] = self::new_key( 'group' );
		}
		$def          = self::normalize( $kind, $def );
		$previous_key = $previous_key ? (string) $previous_key : $def['key'];

		if ( self::is_local( $kind, $def['key'] ) || self::is_local( $kind, $previous_key ) ) {
			return new WP_Error( 'brik_content_local', __( 'This definition is registered in code and can only be changed there.', 'brik-builder' ), array( 'status' => 400 ) );
		}

		$errors = self::validate( $kind, $def, $previous_key );
		if ( $errors ) {
			return self::error( $errors );
		}

		$stored = self::raw( $kind );
		$old    = null;
		$list   = array();
		foreach ( $stored as $item ) {
			if ( isset( $item['key'] ) && ( $item['key'] === $previous_key || $item['key'] === $def['key'] ) ) {
				$old = $item;
				continue;
			}
			$list[] = $item;
		}
		unset( $def['local'] );
		$list[] = $def;

		update_option( self::KINDS[ $kind ], array_values( $list ), true );

		if ( 'groups' !== $kind && self::rewrite_changed( $kind, $old, $def ) ) {
			self::schedule_flush();
		}
		self::reset_cache();

		/**
		 * Fires after a content definition was saved.
		 *
		 * @param array  $def  Normalized definition.
		 * @param string $kind post_types|taxonomies|groups.
		 */
		do_action( 'brik/content/saved', $def, $kind );

		$saved          = $def;
		$saved['local'] = false;
		return $saved;
	}

	public static function delete_post_type( $key, $delete_posts = false ) {
		$key = (string) $key;
		if ( self::is_local( 'post_types', $key ) ) {
			return new WP_Error( 'brik_content_local', __( 'This post type is registered in code.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( ! self::get_post_type( $key ) ) {
			return new WP_Error( 'brik_content_not_found', __( 'No such post type.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		// Delete posts while the type is still registered, so capability checks map normally.
		$posts = $delete_posts ? self::delete_posts_of( $key ) : 0;
		self::delete( 'post_types', $key );
		$object = get_post_type_object( $key );
		if ( $object && ! empty( $object->brik_content ) ) {
			unregister_post_type( $key );
		}
		$groups = self::detach( 'post_type', $key );
		self::unlink( 'taxonomies', 'post_types', $key );
		self::schedule_flush();
		return array(
			'deleted'        => true,
			'posts_deleted'  => $posts,
			'groups_updated' => $groups,
		);
	}

	public static function delete_taxonomy( $key, $delete_terms = false ) {
		$key = (string) $key;
		if ( self::is_local( 'taxonomies', $key ) ) {
			return new WP_Error( 'brik_content_local', __( 'This taxonomy is registered in code.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( ! self::get_taxonomy( $key ) ) {
			return new WP_Error( 'brik_content_not_found', __( 'No such taxonomy.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		$terms = 0;
		if ( $delete_terms && taxonomy_exists( $key ) ) {
			$ids = get_terms(
				array(
					'taxonomy'   => $key,
					'hide_empty' => false,
					'fields'     => 'ids',
				)
			);
			foreach ( is_array( $ids ) ? $ids : array() as $id ) {
				if ( true === wp_delete_term( $id, $key ) ) {
					++$terms;
				}
			}
		}
		self::delete( 'taxonomies', $key );
		$object = get_taxonomy( $key );
		if ( $object && ! empty( $object->brik_content ) ) {
			unregister_taxonomy( $key );
		}
		$groups = self::detach( 'taxonomy', $key );
		self::unlink( 'post_types', 'taxonomies', $key );
		self::schedule_flush();
		return array(
			'deleted'        => true,
			'terms_deleted'  => $terms,
			'groups_updated' => $groups,
		);
	}

	public static function delete_group( $key ) {
		$key = (string) $key;
		if ( self::is_local( 'groups', $key ) ) {
			return new WP_Error( 'brik_content_local', __( 'This field group is registered in code.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( ! self::delete( 'groups', $key ) ) {
			return new WP_Error( 'brik_content_not_found', __( 'No such field group.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		return array( 'deleted' => true );
	}

	private static function delete( $kind, $key ) {
		$found = false;
		$list  = array();
		foreach ( self::raw( $kind ) as $item ) {
			if ( isset( $item['key'] ) && $item['key'] === $key ) {
				$found = true;
				continue;
			}
			$list[] = $item;
		}
		if ( $found ) {
			update_option( self::KINDS[ $kind ], $list, true );
			self::reset_cache();
			do_action( 'brik/content/deleted', $key, $kind );
		}
		return $found;
	}

	/**
	 * Drop location rule groups that require a deleted post type or taxonomy. A group with no
	 * rules left is deactivated, not deleted, so its fields can be pointed somewhere else.
	 *
	 * @return string[] Keys of the groups that changed.
	 */
	private static function detach( $param, $key ) {
		$changed = array();
		$list    = self::raw( 'groups' );
		foreach ( $list as $i => $group ) {
			if ( empty( $group['location'] ) || ! is_array( $group['location'] ) ) {
				continue;
			}
			$kept = array();
			foreach ( $group['location'] as $and ) {
				$hit = false;
				foreach ( (array) $and as $rule ) {
					if ( isset( $rule['param'], $rule['value'] ) && $param === $rule['param'] && $key === $rule['value'] && ( ! isset( $rule['operator'] ) || '!=' !== $rule['operator'] ) ) {
						$hit = true;
					}
				}
				if ( ! $hit ) {
					$kept[] = $and;
				}
			}
			if ( count( $kept ) !== count( $group['location'] ) ) {
				$list[ $i ]['location'] = $kept;
				if ( ! $kept ) {
					$list[ $i ]['active'] = false;
				}
				$changed[] = (string) $group['key'];
			}
		}
		if ( $changed ) {
			update_option( self::OPT_GROUPS, $list, true );
			self::reset_cache();
		}
		return $changed;
	}

	/**
	 * Remove a deleted key from the cross references of the other kind.
	 */
	private static function unlink( $kind, $prop, $key ) {
		$list    = self::raw( $kind );
		$changed = false;
		foreach ( $list as $i => $def ) {
			if ( ! empty( $def[ $prop ] ) && in_array( $key, (array) $def[ $prop ], true ) ) {
				$list[ $i ][ $prop ] = array_values( array_diff( (array) $def[ $prop ], array( $key ) ) );
				$changed             = true;
			}
		}
		if ( $changed ) {
			update_option( self::KINDS[ $kind ], $list, true );
			self::reset_cache();
		}
	}

	private static function delete_posts_of( $type ) {
		$count = 0;
		do {
			$ids = get_posts(
				array(
					'post_type'        => $type,
					'post_status'      => 'any',
					'posts_per_page'   => 100,
					'fields'           => 'ids',
					'suppress_filters' => true,
				)
			);
			foreach ( $ids as $id ) {
				if ( current_user_can( 'delete_post', $id ) || ! post_type_exists( $type ) ) {
					if ( wp_delete_post( $id, true ) ) {
						++$count;
					}
				}
			}
		} while ( count( $ids ) === 100 && $count > 0 );
		return $count;
	}

	private static function raw( $kind ) {
		$raw = get_option( self::KINDS[ $kind ], array() );
		return is_array( $raw ) ? array_values( array_filter( $raw, 'is_array' ) ) : array();
	}

	private static function rewrite_changed( $kind, $old, array $new ) {
		if ( ! $old ) {
			return true;
		}
		foreach ( array( 'key', 'active', 'public', 'rewrite_slug', 'has_archive', 'archive_slug', 'hierarchical' ) as $prop ) {
			$a = isset( $old[ $prop ] ) ? $old[ $prop ] : null;
			$b = isset( $new[ $prop ] ) ? $new[ $prop ] : null;
			if ( $a !== $b ) {
				return true;
			}
		}
		return false;
	}

	public static function schedule_flush() {
		update_option( self::OPT_FLUSH, 1, true );
	}

	public static function new_key( $prefix ) {
		return $prefix . '_' . strtolower( wp_generate_password( 8, false, false ) );
	}

	private static function error( array $errors ) {
		return new WP_Error(
			'brik_content_invalid',
			implode( ' ', $errors ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Reserved names.
	 * ------------------------------------------------------------------- */

	/**
	 * Names WordPress uses as query vars or for its own types. Taken from the "Reserved
	 * Terms" list in the WordPress docs plus the core post types and taxonomies.
	 */
	public static function reserved() {
		$names = array(
			// Core post types and taxonomies.
			'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
			'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles',
			'wp_navigation', 'wp_font_family', 'wp_font_face', 'category', 'post_tag', 'nav_menu',
			'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category',
			// Reserved terms.
			'action', 'attachment_id', 'author', 'author_name', 'calendar', 'cat', 'category__and',
			'category__in', 'category__not_in', 'category_name', 'comments_per_page', 'comments_popup',
			'custom', 'customize_messenger_channel', 'customized', 'cpage', 'day', 'debug', 'embed',
			'error', 'exact', 'feed', 'fields', 'hour', 'm', 'minute', 'monthnum', 'more', 'name',
			'nonce', 'nopaging', 'offset', 'order', 'orderby', 'p', 'page_id', 'paged', 'pagename',
			'pb', 'perm', 'post__in', 'post__not_in', 'post_mime_type', 'post_status', 'post_type',
			'posts', 'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's', 'search',
			'second', 'sentence', 'showposts', 'static', 'status', 'subpost', 'subpost_id', 'tag',
			'tag__and', 'tag__in', 'tag__not_in', 'tag_id', 'tag_slug__and', 'tag_slug__in', 'taxonomy',
			'tb', 'term', 'terms', 'theme', 'title', 'type', 'types', 'w', 'withcomments',
			'withoutcomments', 'year',
			// Brik's own types.
			'brik_template', 'brik_library', 'brik_submission',
		);
		return array_values( array_unique( apply_filters( 'brik/content/reserved', $names ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Validation.
	 * ------------------------------------------------------------------- */

	/**
	 * Errors for a normalized definition, as a list of messages.
	 */
	public static function validate( $kind, array $def, $previous_key = '', $local = false ) {
		$errors = array();
		switch ( $kind ) {
			case 'post_types':
				$errors = self::validate_type_key( $def['key'], 'post_type', $previous_key );
				if ( '' === trim( $def['singular'] ) || '' === trim( $def['plural'] ) ) {
					$errors[] = __( 'Enter a singular and a plural name.', 'brik-builder' );
				}
				// Code definitions may load before the taxonomies they name exist.
				foreach ( $local ? array() : $def['taxonomies'] as $tax ) {
					if ( ! taxonomy_exists( $tax ) && ! self::get_taxonomy( $tax ) ) {
						/* translators: %s: taxonomy key */
						$errors[] = sprintf( __( 'Unknown taxonomy "%s".', 'brik-builder' ), $tax );
					}
				}
				break;
			case 'taxonomies':
				$errors = self::validate_type_key( $def['key'], 'taxonomy', $previous_key );
				if ( '' === trim( $def['singular'] ) || '' === trim( $def['plural'] ) ) {
					$errors[] = __( 'Enter a singular and a plural name.', 'brik-builder' );
				}
				break;
			case 'groups':
				if ( ! preg_match( '/^group_[a-z0-9_]{3,40}$/', $def['key'] ) ) {
					$errors[] = __( 'Field group keys look like "group_ab12cd".', 'brik-builder' );
				}
				if ( '' === trim( $def['title'] ) ) {
					$errors[] = __( 'Give the field group a title.', 'brik-builder' );
				}
				$errors = array_merge( $errors, self::validate_fields( $def['fields'] ), self::validate_field_names( $def, $previous_key ) );
				break;
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Key checks shared by post types and taxonomies.
	 */
	public static function validate_type_key( $key, $what, $previous_key = '' ) {
		$errors = array();
		$max    = 'post_type' === $what ? 20 : 32;
		$label  = 'post_type' === $what ? __( 'Post type key', 'brik-builder' ) : __( 'Taxonomy key', 'brik-builder' );

		if ( '' === $key ) {
			/* translators: %s: "Post type key" or "Taxonomy key" */
			return array( sprintf( __( '%s is required.', 'brik-builder' ), $label ) );
		}
		if ( ! preg_match( '/^[a-z0-9_-]+$/', $key ) ) {
			/* translators: %s: "Post type key" or "Taxonomy key" */
			$errors[] = sprintf( __( '%s may only contain lowercase letters, numbers, dashes and underscores.', 'brik-builder' ), $label );
		}
		if ( strlen( $key ) > $max ) {
			/* translators: 1: "Post type key" or "Taxonomy key", 2: maximum length */
			$errors[] = sprintf( __( '%1$s must be %2$d characters or fewer.', 'brik-builder' ), $label, $max );
		}
		if ( in_array( $key, self::reserved(), true ) ) {
			/* translators: %s: the key */
			$errors[] = sprintf( __( '"%s" is reserved by WordPress. Choose another key.', 'brik-builder' ), $key );
			return $errors;
		}

		$own_types = wp_list_pluck( self::post_types(), 'key' );
		$own_taxes = wp_list_pluck( self::taxonomies(), 'key' );
		// Types we registered earlier in this request (and since deleted) still count as ours.
		$ours = static function ( $object ) {
			return $object && ! empty( $object->brik_content );
		};
		if ( $ours( get_post_type_object( $key ) ) ) {
			$own_types[] = $key;
		}
		if ( $ours( get_taxonomy( $key ) ) ) {
			$own_taxes[] = $key;
		}
		$renamed   = $previous_key && $previous_key !== $key;

		if ( 'post_type' === $what ) {
			$taken_by_us = in_array( $key, wp_list_pluck( self::post_types(), 'key' ), true ) && ( $renamed || '' === $previous_key );
			$taken_by_wp = post_type_exists( $key ) && ! in_array( $key, $own_types, true );
			$clash       = in_array( $key, $own_taxes, true ) || ( taxonomy_exists( $key ) && ! in_array( $key, $own_taxes, true ) );
		} else {
			$taken_by_us = in_array( $key, wp_list_pluck( self::taxonomies(), 'key' ), true ) && ( $renamed || '' === $previous_key );
			$taken_by_wp = taxonomy_exists( $key ) && ! in_array( $key, $own_taxes, true );
			$clash       = in_array( $key, $own_types, true ) || ( post_type_exists( $key ) && ! in_array( $key, $own_types, true ) );
		}
		if ( $taken_by_us || $taken_by_wp ) {
			/* translators: %s: the key */
			$errors[] = sprintf( __( '"%s" is already registered.', 'brik-builder' ), $key );
		} elseif ( $clash ) {
			/* translators: %s: the key */
			$errors[] = sprintf( __( '"%s" is already used by a post type or taxonomy; both share the same URL query variable.', 'brik-builder' ), $key );
		}
		return $errors;
	}

	private static function validate_fields( array $fields, $parent = '' ) {
		$errors = array();
		$names  = array();
		$keys   = array();
		foreach ( $fields as $field ) {
			$label = '' !== $field['label'] ? $field['label'] : $field['name'];
			if ( ! Fields::type( $field['type'] ) ) {
				/* translators: %s: field type */
				$errors[] = sprintf( __( 'Unknown field type "%s".', 'brik-builder' ), $field['type'] );
				continue;
			}
			if ( ! preg_match( '/^field_[a-z0-9_]{3,40}$/', $field['key'] ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( 'Field "%s" has an invalid key.', 'brik-builder' ), $label );
			}
			if ( isset( $keys[ $field['key'] ] ) ) {
				/* translators: %s: field key */
				$errors[] = sprintf( __( 'Field key "%s" is used twice.', 'brik-builder' ), $field['key'] );
			}
			$keys[ $field['key'] ] = true;

			if ( ! Fields::has_value( $field['type'] ) ) {
				continue;
			}
			if ( ! self::valid_field_name( $field['name'] ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( 'Field "%s" needs a name of lowercase letters, numbers and underscores, starting with a letter.', 'brik-builder' ), $label );
			} elseif ( isset( $names[ $field['name'] ] ) ) {
				/* translators: %s: field name */
				$errors[] = sprintf( __( 'The field name "%s" is used more than once in this group.', 'brik-builder' ), ( $parent ? $parent . '.' : '' ) . $field['name'] );
			}
			$names[ $field['name'] ] = true;

			if ( in_array( $field['type'], array( 'repeater', 'group' ), true ) ) {
				$errors = array_merge( $errors, self::validate_fields( $field['options']['sub_fields'], $field['name'] ) );
			}
		}
		return $errors;
	}

	public static function valid_field_name( $name ) {
		return is_string( $name ) && (bool) preg_match( '/^[a-z][a-z0-9_]{0,63}$/', $name );
	}

	/**
	 * Field names are meta keys, so they must not repeat across groups that share a target.
	 */
	private static function validate_field_names( array $group, $previous_key ) {
		$errors  = array();
		$targets = self::targets( $group );
		foreach ( self::groups() as $other ) {
			if ( $other['key'] === $group['key'] || $other['key'] === $previous_key ) {
				continue;
			}
			$o = self::targets( $other );
			$shared = array_intersect( $targets['post_types'], $o['post_types'] ) || array_intersect( $targets['taxonomies'], $o['taxonomies'] )
				|| ( $targets['users'] && $o['users'] ) || ( $targets['options'] && $o['options'] );
			if ( ! $shared ) {
				continue;
			}
			$theirs = array();
			foreach ( $other['fields'] as $f ) {
				if ( Fields::has_value( $f['type'] ) ) {
					$theirs[ $f['name'] ] = true;
				}
			}
			foreach ( $group['fields'] as $f ) {
				if ( Fields::has_value( $f['type'] ) && isset( $theirs[ $f['name'] ] ) ) {
					/* translators: 1: field name, 2: other group title */
					$errors[] = sprintf( __( 'The field name "%1$s" is already used by the group "%2$s" for the same content.', 'brik-builder' ), $f['name'], $other['title'] );
				}
			}
		}
		return $errors;
	}

	/* ---------------------------------------------------------------------
	 * Normalizing.
	 * ------------------------------------------------------------------- */

	public static function normalize( $kind, array $def ) {
		switch ( $kind ) {
			case 'post_types':
				return self::normalize_post_type( $def );
			case 'taxonomies':
				return self::normalize_taxonomy( $def );
			default:
				return self::normalize_group( $def );
		}
	}

	private static function str( array $a, $key, $default = '' ) {
		return isset( $a[ $key ] ) && is_scalar( $a[ $key ] ) ? sanitize_text_field( (string) $a[ $key ] ) : $default;
	}

	private static function bool( array $a, $key, $default ) {
		if ( ! array_key_exists( $key, $a ) ) {
			return $default;
		}
		return filter_var( $a[ $key ], FILTER_VALIDATE_BOOLEAN );
	}

	private static function labels_in( array $a ) {
		$out = array();
		if ( ! empty( $a['labels'] ) && is_array( $a['labels'] ) ) {
			foreach ( $a['labels'] as $k => $v ) {
				if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
					$out[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
				}
			}
		}
		return $out;
	}

	private static function key_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $value ) ) ) );
	}

	public static function supports_options() {
		return array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields', 'author', 'page-attributes', 'comments', 'trackbacks', 'post-formats' );
	}

	public static function normalize_post_type( array $d ) {
		$key      = isset( $d['key'] ) ? strtolower( trim( (string) $d['key'] ) ) : '';
		$singular = self::str( $d, 'singular' );
		$plural   = self::str( $d, 'plural' );
		$icon     = self::str( $d, 'icon', 'dashicons-admin-post' );
		if ( ! preg_match( '/^(dashicons-[a-z0-9-]+|lucide:[a-z0-9-]+)$/', $icon ) ) {
			$icon = 'dashicons-admin-post';
		}
		$supports = isset( $d['supports'] ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $d['supports'] ), self::supports_options() ) ) : array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' );
		$cap      = self::str( $d, 'capability_type', 'post' );
		$position = isset( $d['menu_position'] ) && '' !== $d['menu_position'] && null !== $d['menu_position'] ? max( 0, min( 1000, (int) $d['menu_position'] ) ) : 25;

		return array(
			'key'                 => $key,
			'version'             => self::VERSION,
			'active'              => self::bool( $d, 'active', true ),
			'singular'            => $singular,
			'plural'              => $plural,
			'labels'              => self::labels_in( $d ),
			'description'         => isset( $d['description'] ) && is_scalar( $d['description'] ) ? sanitize_textarea_field( (string) $d['description'] ) : '',
			'icon'                => $icon,
			'public'              => self::bool( $d, 'public', true ),
			'has_archive'         => self::bool( $d, 'has_archive', true ),
			'archive_slug'        => sanitize_title( self::str( $d, 'archive_slug' ) ),
			'rewrite_slug'        => sanitize_title( self::str( $d, 'rewrite_slug' ) ),
			'hierarchical'        => self::bool( $d, 'hierarchical', false ),
			'show_in_rest'        => self::bool( $d, 'show_in_rest', true ),
			'show_in_menu'        => self::bool( $d, 'show_in_menu', true ),
			'menu_position'       => $position,
			'supports'            => $supports,
			'taxonomies'          => isset( $d['taxonomies'] ) ? self::key_list( $d['taxonomies'] ) : array(),
			'exclude_from_search' => self::bool( $d, 'exclude_from_search', false ),
			'capability_type'     => in_array( $cap, array( 'post', 'page' ), true ) ? $cap : sanitize_key( $cap ),
			'brik'                => self::bool( $d, 'brik', true ),
			'local'               => ! empty( $d['local'] ),
		);
	}

	public static function normalize_taxonomy( array $d ) {
		return array(
			'key'               => isset( $d['key'] ) ? strtolower( trim( (string) $d['key'] ) ) : '',
			'version'           => self::VERSION,
			'active'            => self::bool( $d, 'active', true ),
			'singular'          => self::str( $d, 'singular' ),
			'plural'            => self::str( $d, 'plural' ),
			'labels'            => self::labels_in( $d ),
			'description'       => isset( $d['description'] ) && is_scalar( $d['description'] ) ? sanitize_textarea_field( (string) $d['description'] ) : '',
			'hierarchical'      => self::bool( $d, 'hierarchical', true ),
			'public'            => self::bool( $d, 'public', true ),
			'show_in_rest'      => self::bool( $d, 'show_in_rest', true ),
			'show_admin_column' => self::bool( $d, 'show_admin_column', true ),
			'rewrite_slug'      => sanitize_title( self::str( $d, 'rewrite_slug' ) ),
			'post_types'        => isset( $d['post_types'] ) ? self::key_list( $d['post_types'] ) : array(),
			'local'             => ! empty( $d['local'] ),
		);
	}

	public static function location_params() {
		return array( 'post_type', 'taxonomy', 'post_template', 'page_type', 'post_status', 'user_role', 'options_page' );
	}

	public static function normalize_group( array $d ) {
		$location = array();
		foreach ( isset( $d['location'] ) && is_array( $d['location'] ) ? $d['location'] : array() as $and ) {
			$rules = array();
			foreach ( is_array( $and ) ? $and : array() as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['param'] ) || ! in_array( $rule['param'], self::location_params(), true ) ) {
					continue;
				}
				$rules[] = array(
					'param'    => $rule['param'],
					'operator' => isset( $rule['operator'] ) && '!=' === $rule['operator'] ? '!=' : '==',
					'value'    => isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
				);
			}
			if ( $rules ) {
				$location[] = $rules;
			}
		}
		$position = isset( $d['position'] ) && in_array( $d['position'], array( 'normal', 'side', 'after_title' ), true ) ? $d['position'] : 'normal';
		$style    = isset( $d['style'] ) && 'seamless' === $d['style'] ? 'seamless' : 'card';
		$key      = isset( $d['key'] ) ? strtolower( trim( (string) $d['key'] ) ) : '';

		return array(
			'key'         => $key,
			'version'     => self::VERSION,
			'active'      => self::bool( $d, 'active', true ),
			'title'       => self::str( $d, 'title' ),
			'description' => isset( $d['description'] ) && is_scalar( $d['description'] ) ? sanitize_textarea_field( (string) $d['description'] ) : '',
			'location'    => $location,
			'position'    => $position,
			'style'       => $style,
			'order'       => isset( $d['order'] ) ? (int) $d['order'] : 0,
			'fields'      => self::normalize_fields( isset( $d['fields'] ) && is_array( $d['fields'] ) ? $d['fields'] : array() ),
			'local'       => ! empty( $d['local'] ),
		);
	}

	public static function normalize_fields( array $fields ) {
		$out = array();
		foreach ( $fields as $field ) {
			if ( is_array( $field ) ) {
				$out[] = self::normalize_field( $field );
			}
		}
		return $out;
	}

	public static function normalize_field( array $f ) {
		$type  = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
		$label = self::str( $f, 'label' );
		$name  = isset( $f['name'] ) && is_scalar( $f['name'] ) ? strtolower( trim( (string) $f['name'] ) ) : '';
		if ( '' === $name && '' !== $label && Fields::has_value( $type ) ) {
			$name = trim( preg_replace( '/[^a-z0-9]+/', '_', strtolower( remove_accents( $label ) ) ), '_' );
			$name = preg_match( '/^[a-z]/', $name ) ? $name : 'field_' . $name;
		}
		$key   = isset( $f['key'] ) && is_scalar( $f['key'] ) && '' !== $f['key'] ? strtolower( (string) $f['key'] ) : self::new_key( 'field' );
		$width = isset( $f['width'] ) ? (int) $f['width'] : 100;
		if ( ! in_array( $width, self::WIDTHS, true ) ) {
			$width = 100;
			foreach ( self::WIDTHS as $w ) {
				if ( isset( $f['width'] ) && (int) $f['width'] <= $w ) {
					$width = $w;
					break;
				}
			}
		}

		$conditions = array();
		foreach ( isset( $f['conditions'] ) && is_array( $f['conditions'] ) ? $f['conditions'] : array() as $and ) {
			$rules = array();
			foreach ( is_array( $and ) ? $and : array() as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['field'] ) ) {
					continue;
				}
				$op      = isset( $rule['operator'] ) ? (string) $rule['operator'] : '==';
				$rules[] = array(
					'field'    => sanitize_key( $rule['field'] ),
					'operator' => in_array( $op, array( '==', '!=', 'empty', '!empty', 'contains' ), true ) ? $op : '==',
					'value'    => isset( $rule['value'] ) && is_scalar( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
				);
			}
			if ( $rules ) {
				$conditions[] = $rules;
			}
		}

		$field = array(
			'key'          => sanitize_key( $key ),
			'name'         => $name,
			'label'        => $label,
			'type'         => $type,
			'instructions' => isset( $f['instructions'] ) && is_scalar( $f['instructions'] ) ? wp_kses( (string) $f['instructions'], array( 'a' => array( 'href' => true, 'target' => true ), 'strong' => array(), 'em' => array(), 'code' => array(), 'br' => array() ) ) : '',
			'required'     => self::bool( $f, 'required', false ),
			'default'      => null,
			'placeholder'  => self::str( $f, 'placeholder' ),
			'width'        => $width,
			'conditions'   => $conditions,
			'options'      => Fields::normalize_options( $type, isset( $f['options'] ) && is_array( $f['options'] ) ? $f['options'] : array() ),
		);
		$field['default'] = array_key_exists( 'default', $f ) && '' !== $f['default'] && null !== $f['default'] && Fields::has_value( $type )
			? Fields::sanitize( $field, $f['default'] )
			: '';
		return $field;
	}

	/* ---------------------------------------------------------------------
	 * Labels.
	 * ------------------------------------------------------------------- */

	/**
	 * Lowercase a name for use mid-sentence, leaving acronyms and brand-style words ("FAQ",
	 * "iPhone") alone.
	 */
	private static function lower( $text ) {
		$words = preg_split( '/(\s+)/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $words as $i => $word ) {
			if ( preg_match( '/^\p{Lu}[\p{Ll}\d\'’-]*$/u', $word ) ) {
				$words[ $i ] = function_exists( 'mb_strtolower' ) ? mb_strtolower( $word ) : strtolower( $word );
			}
		}
		return implode( '', $words );
	}

	/**
	 * Every label register_post_type() understands, generated from the two names.
	 */
	public static function post_type_labels( $singular, $plural, array $overrides = array() ) {
		$s  = (string) $singular;
		$p  = (string) $plural;
		$ls = self::lower( $s );
		$lp = self::lower( $p );

		$labels = array(
			'name'                     => $p,
			'singular_name'            => $s,
			'menu_name'                => $p,
			'name_admin_bar'           => $s,
			'add_new'                  => __( 'Add New', 'brik-builder' ),
			/* translators: %s: singular name, e.g. Project */
			'add_new_item'             => sprintf( __( 'Add New %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'edit_item'                => sprintf( __( 'Edit %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'new_item'                 => sprintf( __( 'New %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'view_item'                => sprintf( __( 'View %s', 'brik-builder' ), $s ),
			/* translators: %s: plural name, e.g. Projects */
			'view_items'               => sprintf( __( 'View %s', 'brik-builder' ), $p ),
			/* translators: %s: plural name */
			'search_items'             => sprintf( __( 'Search %s', 'brik-builder' ), $p ),
			/* translators: %s: plural name, lowercase */
			'not_found'                => sprintf( __( 'No %s found.', 'brik-builder' ), $lp ),
			/* translators: %s: plural name, lowercase */
			'not_found_in_trash'       => sprintf( __( 'No %s found in Trash.', 'brik-builder' ), $lp ),
			/* translators: %s: singular name */
			'parent_item_colon'        => sprintf( __( 'Parent %s:', 'brik-builder' ), $s ),
			/* translators: %s: plural name */
			'all_items'                => sprintf( __( 'All %s', 'brik-builder' ), $p ),
			/* translators: %s: singular name */
			'archives'                 => sprintf( __( '%s Archives', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'attributes'               => sprintf( __( '%s Attributes', 'brik-builder' ), $s ),
			/* translators: %s: singular name, lowercase */
			'insert_into_item'         => sprintf( __( 'Insert into %s', 'brik-builder' ), $ls ),
			/* translators: %s: singular name, lowercase */
			'uploaded_to_this_item'    => sprintf( __( 'Uploaded to this %s', 'brik-builder' ), $ls ),
			'featured_image'           => __( 'Featured image', 'brik-builder' ),
			'set_featured_image'       => __( 'Set featured image', 'brik-builder' ),
			'remove_featured_image'    => __( 'Remove featured image', 'brik-builder' ),
			'use_featured_image'       => __( 'Use as featured image', 'brik-builder' ),
			/* translators: %s: plural name, lowercase */
			'filter_items_list'        => sprintf( __( 'Filter %s list', 'brik-builder' ), $lp ),
			'filter_by_date'           => __( 'Filter by date', 'brik-builder' ),
			/* translators: %s: plural name */
			'items_list_navigation'    => sprintf( __( '%s list navigation', 'brik-builder' ), $p ),
			/* translators: %s: plural name */
			'items_list'               => sprintf( __( '%s list', 'brik-builder' ), $p ),
			/* translators: %s: singular name */
			'item_published'           => sprintf( __( '%s published.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_published_privately' => sprintf( __( '%s published privately.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_reverted_to_draft'   => sprintf( __( '%s reverted to draft.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_trashed'             => sprintf( __( '%s trashed.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_scheduled'           => sprintf( __( '%s scheduled.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_updated'             => sprintf( __( '%s updated.', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'item_link'                => sprintf( __( '%s Link', 'brik-builder' ), $s ),
			/* translators: %s: singular name, lowercase */
			'item_link_description'    => sprintf( __( 'A link to a %s.', 'brik-builder' ), $ls ),
		);
		return array_merge( $labels, array_intersect_key( $overrides, $labels ) );
	}

	/**
	 * Every label register_taxonomy() understands.
	 */
	public static function taxonomy_labels( $singular, $plural, array $overrides = array(), $hierarchical = true ) {
		$s  = (string) $singular;
		$p  = (string) $plural;
		$ls = self::lower( $s );
		$lp = self::lower( $p );

		$labels = array(
			'name'                       => $p,
			'singular_name'              => $s,
			'menu_name'                  => $p,
			/* translators: %s: plural name, e.g. Genres */
			'search_items'               => sprintf( __( 'Search %s', 'brik-builder' ), $p ),
			/* translators: %s: plural name */
			'popular_items'              => $hierarchical ? null : sprintf( __( 'Popular %s', 'brik-builder' ), $p ),
			/* translators: %s: plural name */
			'all_items'                  => sprintf( __( 'All %s', 'brik-builder' ), $p ),
			/* translators: %s: singular name, e.g. Genre */
			'parent_item'                => $hierarchical ? sprintf( __( 'Parent %s', 'brik-builder' ), $s ) : null,
			/* translators: %s: singular name */
			'parent_item_colon'          => $hierarchical ? sprintf( __( 'Parent %s:', 'brik-builder' ), $s ) : null,
			'name_field_description'     => __( 'The name is how it appears on your site.', 'brik-builder' ),
			'slug_field_description'     => __( 'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'brik-builder' ),
			/* translators: %s: singular name, lowercase */
			'parent_field_description'   => $hierarchical ? sprintf( __( 'Assign a parent %s to create a hierarchy.', 'brik-builder' ), $ls ) : null,
			'desc_field_description'     => __( 'The description is not prominent by default; however, some themes may show it.', 'brik-builder' ),
			/* translators: %s: singular name */
			'edit_item'                  => sprintf( __( 'Edit %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'view_item'                  => sprintf( __( 'View %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'update_item'                => sprintf( __( 'Update %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'add_new_item'               => sprintf( __( 'Add New %s', 'brik-builder' ), $s ),
			/* translators: %s: singular name */
			'new_item_name'              => sprintf( __( 'New %s Name', 'brik-builder' ), $s ),
			/* translators: %s: plural name, lowercase */
			'separate_items_with_commas' => $hierarchical ? null : sprintf( __( 'Separate %s with commas', 'brik-builder' ), $lp ),
			/* translators: %s: plural name, lowercase */
			'add_or_remove_items'        => $hierarchical ? null : sprintf( __( 'Add or remove %s', 'brik-builder' ), $lp ),
			/* translators: %s: plural name, lowercase */
			'choose_from_most_used'      => $hierarchical ? null : sprintf( __( 'Choose from the most used %s', 'brik-builder' ), $lp ),
			/* translators: %s: plural name, lowercase */
			'not_found'                  => sprintf( __( 'No %s found.', 'brik-builder' ), $lp ),
			/* translators: %s: plural name, lowercase */
			'no_terms'                   => sprintf( __( 'No %s', 'brik-builder' ), $lp ),
			/* translators: %s: singular name, lowercase */
			'filter_by_item'             => $hierarchical ? sprintf( __( 'Filter by %s', 'brik-builder' ), $ls ) : null,
			/* translators: %s: plural name */
			'items_list_navigation'      => sprintf( __( '%s list navigation', 'brik-builder' ), $p ),
			/* translators: %s: plural name */
			'items_list'                 => sprintf( __( '%s list', 'brik-builder' ), $p ),
			'most_used'                  => _x( 'Most Used', 'taxonomy', 'brik-builder' ),
			/* translators: %s: plural name */
			'back_to_items'              => sprintf( __( '&larr; Go to %s', 'brik-builder' ), $p ),
			/* translators: %s: singular name */
			'item_link'                  => sprintf( __( '%s Link', 'brik-builder' ), $s ),
			/* translators: %s: singular name, lowercase */
			'item_link_description'      => sprintf( __( 'A link to a %s.', 'brik-builder' ), $ls ),
		);
		$labels = array_filter(
			$labels,
			static function ( $v ) {
				return null !== $v;
			}
		);
		return array_merge( $labels, array_intersect_key( $overrides, $labels ) );
	}

	/* ---------------------------------------------------------------------
	 * Locations.
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a group's location rules match a screen context. The context holds whatever
	 * applies to the object being edited: post_type, post_template, page_type, post_status,
	 * taxonomy, user_role (list) or options_page.
	 */
	public static function matches( array $group, array $ctx ) {
		foreach ( $group['location'] as $and ) {
			$ok = true;
			foreach ( $and as $rule ) {
				if ( ! self::rule_matches( $rule, $ctx ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok && $and ) {
				return true;
			}
		}
		return false;
	}

	private static function rule_matches( array $rule, array $ctx ) {
		$param = $rule['param'];
		// A rule about another kind of object (a taxonomy rule on a post screen) never matches.
		if ( ! array_key_exists( $param, $ctx ) ) {
			return false;
		}
		$actual = $ctx[ $param ];
		$value  = $rule['value'];
		if ( 'user_role' === $param ) {
			$hit = 'all' === $value || in_array( $value, (array) $actual, true );
		} elseif ( 'all' === $value ) {
			$hit = true;
		} elseif ( 'post_template' === $param && in_array( $value, array( '', 'default' ), true ) ) {
			$hit = in_array( (string) $actual, array( '', 'default' ), true );
		} else {
			$hit = (string) $actual === $value || ( is_array( $actual ) && in_array( $value, $actual, true ) );
		}
		return '!=' === $rule['operator'] ? ! $hit : $hit;
	}

	/**
	 * Objects a group can apply to, used to register meta and to check name clashes.
	 *
	 * @return array post_types, taxonomies (lists), users, options (bools).
	 */
	public static function targets( array $group ) {
		$out       = array(
			'post_types' => array(),
			'taxonomies' => array(),
			'users'      => false,
			'options'    => false,
		);
		$all_types = null;
		foreach ( $group['location'] as $and ) {
			$params = wp_list_pluck( $and, 'param' );
			if ( in_array( 'options_page', $params, true ) ) {
				$out['options'] = true;
				continue;
			}
			if ( in_array( 'user_role', $params, true ) ) {
				$out['users'] = true;
				continue;
			}
			if ( in_array( 'taxonomy', $params, true ) ) {
				foreach ( $and as $rule ) {
					if ( 'taxonomy' !== $rule['param'] ) {
						continue;
					}
					if ( '==' === $rule['operator'] && 'all' !== $rule['value'] ) {
						$out['taxonomies'][] = $rule['value'];
					} else {
						$taxes               = array_merge( get_taxonomies( array( 'show_ui' => true ) ), wp_list_pluck( self::taxonomies( true ), 'key' ) );
						$out['taxonomies']   = array_merge( $out['taxonomies'], array_diff( $taxes, '!=' === $rule['operator'] ? array( $rule['value'] ) : array() ) );
					}
				}
				continue;
			}
			// Post screens. page_type rules only make sense for pages.
			$types = null;
			foreach ( $and as $rule ) {
				if ( 'post_type' === $rule['param'] ) {
					if ( '==' === $rule['operator'] && 'all' !== $rule['value'] ) {
						$types = array( $rule['value'] );
					} else {
						if ( null === $all_types ) {
							$all_types = self::all_post_type_keys();
						}
						$types = '!=' === $rule['operator'] ? array_diff( $all_types, array( $rule['value'] ) ) : $all_types;
					}
				} elseif ( 'page_type' === $rule['param'] && null === $types ) {
					$types = array( 'page' );
				}
			}
			if ( null === $types ) {
				if ( null === $all_types ) {
					$all_types = self::all_post_type_keys();
				}
				$types = $all_types;
			}
			$out['post_types'] = array_merge( $out['post_types'], $types );
		}
		$out['post_types'] = array_values( array_unique( $out['post_types'] ) );
		$out['taxonomies'] = array_values( array_unique( $out['taxonomies'] ) );
		return $out;
	}

	private static function all_post_type_keys() {
		$types = get_post_types( array( 'show_ui' => true ) );
		$types = array_diff( $types, array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'brik_template', 'brik_library', 'brik_submission' ) );
		return array_values( array_unique( array_merge( $types, wp_list_pluck( self::post_types( true ), 'key' ) ) ) );
	}

	/**
	 * Active groups that may apply to a kind of object.
	 *
	 * @param string $object post|term|user|option.
	 * @param string $sub    Post type or taxonomy.
	 */
	public static function groups_for( $object, $sub = '' ) {
		$out = array();
		foreach ( self::groups( true ) as $group ) {
			$t = self::targets( $group );
			if ( ( 'post' === $object && in_array( $sub, $t['post_types'], true ) )
				|| ( 'term' === $object && in_array( $sub, $t['taxonomies'], true ) )
				|| ( 'user' === $object && $t['users'] )
				|| ( 'option' === $object && $t['options'] ) ) {
				$out[] = $group;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Field lookups.
	 * ------------------------------------------------------------------- */

	/**
	 * Top-level fields with a value for a post type, keyed by name.
	 */
	public static function fields_for_post_type( $type ) {
		return self::fields_for( 'post', $type );
	}

	public static function fields_for( $object, $sub = '' ) {
		$out = array();
		foreach ( self::groups_for( $object, $sub ) as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( Fields::has_value( $field['type'] ) && ! isset( $out[ $field['name'] ] ) ) {
					$field['group'] = $group['key'];
					$out[ $field['name'] ] = $field;
				}
			}
		}
		return $out;
	}

	/**
	 * Find a field by key ("field_…"), name, or dotted path ("address.city"). With a post
	 * type only groups for that type are searched; without one, the first match wins.
	 */
	public static function find_field( $name_or_key, $post_type = null ) {
		$name_or_key = (string) $name_or_key;
		if ( 0 === strpos( $name_or_key, 'field_' ) ) {
			$hit = self::find_by_key( $name_or_key );
			if ( $hit ) {
				return $hit;
			}
		}
		$groups = null === $post_type ? self::groups( true ) : self::groups_for( 'post', $post_type );
		return self::find_in_groups( $name_or_key, $groups );
	}

	public static function find_in_groups( $path, array $groups ) {
		$parts = explode( '.', (string) $path );
		$top   = array_shift( $parts );
		foreach ( $groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( $field['name'] === $top && Fields::has_value( $field['type'] ) ) {
					$field['group'] = $group['key'];
					return self::descend( $field, $parts );
				}
			}
		}
		return null;
	}

	/**
	 * Walk into repeater/group sub fields along a path. Numeric parts (row indexes) are skipped.
	 */
	public static function descend( array $field, array $parts ) {
		foreach ( $parts as $part ) {
			if ( is_numeric( $part ) ) {
				continue;
			}
			if ( empty( $field['options']['sub_fields'] ) ) {
				return null;
			}
			$next = null;
			foreach ( $field['options']['sub_fields'] as $sub ) {
				if ( $sub['name'] === $part ) {
					$next = $sub;
					break;
				}
			}
			if ( ! $next ) {
				return null;
			}
			$field = $next;
		}
		return $field;
	}

	public static function find_by_key( $key, $fields = null ) {
		if ( null === $fields ) {
			foreach ( self::groups() as $group ) {
				$hit = self::find_by_key( $key, $group['fields'] );
				if ( $hit ) {
					$hit['group'] = $group['key'];
					return $hit;
				}
			}
			return null;
		}
		foreach ( $fields as $field ) {
			if ( $field['key'] === $key ) {
				return $field;
			}
			if ( ! empty( $field['options']['sub_fields'] ) ) {
				$hit = self::find_by_key( $key, $field['options']['sub_fields'] );
				if ( $hit ) {
					return $hit;
				}
			}
		}
		return null;
	}

	/**
	 * Everything at once, as served by GET /content.
	 */
	public static function export_data( array $only = array() ) {
		$out = array( 'version' => self::VERSION );
		foreach ( array_keys( self::KINDS ) as $kind ) {
			$items = self::all( $kind );
			if ( isset( $only[ $kind ] ) ) {
				$keys  = (array) $only[ $kind ];
				$items = array_values(
					array_filter(
						$items,
						static function ( $d ) use ( $keys ) {
							return in_array( $d['key'], $keys, true );
						}
					)
				);
			}
			$out[ $kind ] = $items;
		}
		return $out;
	}
}
