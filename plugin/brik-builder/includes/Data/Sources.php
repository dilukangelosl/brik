<?php
/**
 * Data source registry: what can be shown (tags for the data picker) and what can be queried
 * (fields for the visual query builder) for a post type.
 *
 * @package Brik
 */

namespace Brik\Data;

defined( 'ABSPATH' ) || exit;

final class Sources {

	const META_SCAN_POSTS = 200;

	const META_SCAN_ROWS = 3000;

	const META_MAX_KEYS = 80;

	/** Modifiers offered per value kind (the first is the default output). */
	public static function modifiers() {
		return array(
			'text'   => array(),
			'html'   => array( 'text' ),
			'image'  => array( 'url', 'id', 'alt' ),
			'url'    => array( 'url', 'label' ),
			'date'   => array( 'date', 'time', 'datetime', 'relative', 'iso', 'year' ),
			'number' => array( 'number', 'int', 'raw' ),
			'terms'  => array( 'list', 'first', 'links', 'count', 'url', 'slug' ),
			'posts'  => array( 'list', 'first', 'links', 'count', 'url' ),
			'list'   => array( 'list', 'first', 'count' ),
			'bool'   => array( 'raw' ),
		);
	}

	/**
	 * A post of the type to preview values with: the given one, else the latest published.
	 */
	public static function sample( $post_type, $post_id = 0 ) {
		$post = $post_id ? get_post( $post_id ) : null;
		if ( $post && ( ! $post_type || $post->post_type === $post_type ) && ! ( function_exists( 'brik_site_is_layout' ) && brik_site_is_layout( $post->ID ) ) ) {
			return $post;
		}
		$posts = get_posts(
			array(
				'post_type'      => $post_type ? $post_type : 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return $posts ? $posts[0] : null;
	}

	/* ---------------------------------------------------------------------
	 * Picker tree.
	 * ------------------------------------------------------------------- */

	/**
	 * Grouped tags with live previews.
	 *
	 * @param int    $post_id   Post being edited (templates and library items preview with a sample).
	 * @param string $post_type Preview a type other than the edited post's.
	 * @param bool   $private   Include protected meta keys (editors only).
	 */
	public static function tree( $post_id = 0, $post_type = '', $private = false ) {
		$edited = $post_id ? get_post( $post_id ) : null;
		if ( ! $post_type ) {
			$post_type = $edited && ! ( function_exists( 'brik_site_is_layout' ) && brik_site_is_layout( $edited->ID ) ) ? $edited->post_type : 'post';
		}
		$sample = self::sample( $post_type, $edited ? $edited->ID : 0 );
		$sid    = $sample ? (int) $sample->ID : 0;

		$groups = array();

		$groups[] = self::group(
			'post',
			__( 'Post', 'brik-builder' ),
			'file-text',
			array(
				self::item( __( 'Title', 'brik-builder' ), 'post:title' ),
				self::item( __( 'Excerpt', 'brik-builder' ), 'post:excerpt' ),
				self::item( __( 'Content', 'brik-builder' ), 'post:content', 'html' ),
				self::item( __( 'Date', 'brik-builder' ), 'post:date', 'date' ),
				self::item( __( 'Last updated', 'brik-builder' ), 'post:modified', 'date' ),
				self::item( __( 'Permalink', 'brik-builder' ), 'post:url', 'url' ),
				self::item( __( 'Featured image', 'brik-builder' ), 'post:image', 'image' ),
				self::item( __( 'ID', 'brik-builder' ), 'post:id', 'number' ),
				self::item( __( 'Slug', 'brik-builder' ), 'post:slug' ),
				self::item( __( 'Status', 'brik-builder' ), 'post:status' ),
				self::item( __( 'Comment count', 'brik-builder' ), 'post:comment_count', 'number' ),
				self::item( __( 'Reading time (min)', 'brik-builder' ), 'post:reading_time', 'number' ),
				self::item( __( 'Post type', 'brik-builder' ), 'post:type_label' ),
				self::sub(
					__( 'Parent', 'brik-builder' ),
					array(
						self::item( __( 'Parent title', 'brik-builder' ), 'post:parent_title' ),
						self::item( __( 'Parent URL', 'brik-builder' ), 'post:parent_url', 'url' ),
						self::item( __( 'Parent ID', 'brik-builder' ), 'post:parent_id', 'number' ),
					)
				),
				self::sub(
					__( 'Author', 'brik-builder' ),
					array(
						self::item( __( 'Name', 'brik-builder' ), 'author:name' ),
						self::item( __( 'First name', 'brik-builder' ), 'author:first_name' ),
						self::item( __( 'Last name', 'brik-builder' ), 'author:last_name' ),
						self::item( __( 'Bio', 'brik-builder' ), 'author:bio' ),
						self::item( __( 'Avatar', 'brik-builder' ), 'author:avatar', 'image' ),
						self::item( __( 'Archive URL', 'brik-builder' ), 'author:url', 'url' ),
						self::item( __( 'Website', 'brik-builder' ), 'author:website', 'url' ),
						self::item( __( 'Post count', 'brik-builder' ), 'author:posts', 'number' ),
					)
				),
			)
		);

		$terms = array();
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
			if ( is_taxonomy_viewable( $tax ) && 'post_format' !== $tax->name ) {
				$terms[] = self::item( $tax->labels->name, 'term:' . $tax->name, 'terms' );
			}
		}
		if ( $terms ) {
			$groups[] = self::group( 'terms', __( 'Taxonomies', 'brik-builder' ), 'tags', $terms );
		}

		$own = array();
		if ( class_exists( '\\Brik\\Content\\Registry' ) ) {
			foreach ( \Brik\Content\Registry::fields_for_post_type( $post_type ) as $name => $f ) {
				$kind = self::brik_kind( $f );
				if ( 'repeater' === $f['type'] || 'group' === $f['type'] ) {
					$subs = array();
					foreach ( isset( $f['options']['sub_fields'] ) ? (array) $f['options']['sub_fields'] : array() as $s ) {
						if ( ! empty( $s['name'] ) ) {
							$subs[] = self::item( $s['label'], 'field:' . $name . '.' . $s['name'], self::brik_kind( $s ) );
						}
					}
					if ( 'repeater' === $f['type'] ) {
						$subs[] = self::item( __( 'Rows', 'brik-builder' ), 'field:' . $name . '|count', 'number' );
					}
					$own[] = self::sub( $f['label'], $subs );
					continue;
				}
				$own[] = self::item( $f['label'], 'field:' . $name, $kind, array( 'field_type' => $f['type'] ) );
			}
		}
		if ( $own ) {
			$groups[] = self::group( 'fields', __( 'Custom fields', 'brik-builder' ), 'text-cursor-input', $own );
		}

		$acf = array();
		foreach ( Plugins::acf_fields( $post_type ) as $name => $f ) {
			if ( in_array( $f['type'], array( 'repeater', 'group', 'flexible_content' ), true ) ) {
				$subs = array();
				foreach ( isset( $f['sub_fields'] ) ? (array) $f['sub_fields'] : array() as $s ) {
					$subs[] = self::item( $s['label'], 'acf:' . $name . '.' . $s['name'], self::acf_kind( $s ) );
				}
				if ( 'group' !== $f['type'] ) {
					$subs[] = self::item( __( 'Rows', 'brik-builder' ), 'acf:' . $name, 'number' );
				}
				$acf[] = self::sub( $f['label'], $subs );
				continue;
			}
			$acf[] = self::item( $f['label'] ? $f['label'] : $name, 'acf:' . $name, self::acf_kind( $f ), array( 'field_type' => $f['type'] ) );
		}
		if ( $acf ) {
			$groups[] = self::group( 'acf', 'ACF', 'blocks', $acf );
		}

		$mb = array();
		foreach ( Plugins::metabox_fields( $post_type ) as $name => $f ) {
			$mb[] = self::item( $f['label'], 'metabox:' . $name, self::plugin_kind( $f['type'] ) );
		}
		if ( $mb ) {
			$groups[] = self::group( 'metabox', 'Meta Box', 'package', $mb );
		}

		$pods = array();
		foreach ( Plugins::pods_fields( $post_type ) as $name => $f ) {
			$pods[] = self::item( $f['label'], 'pods:' . $name, self::plugin_kind( $f['type'] ) );
		}
		if ( $pods ) {
			$groups[] = self::group( 'pods', 'Pods', 'boxes', $pods );
		}

		$skip = array_merge(
			class_exists( '\\Brik\\Content\\Registry' ) ? array_keys( \Brik\Content\Registry::fields_for_post_type( $post_type ) ) : array(),
			array_keys( Plugins::acf_fields( $post_type ) ),
			array_keys( Plugins::metabox_fields( $post_type ) ),
			array_keys( Plugins::pods_fields( $post_type ) )
		);
		$meta = array();
		foreach ( self::meta_keys( $post_type, $private ) as $key => $info ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			$item = self::item( $key, 'meta:' . $key, 'number' === $info['type'] ? 'number' : ( 'date' === $info['type'] ? 'date' : 'text' ) );
			if ( $info['private'] ) {
				$item['private'] = ! Tags::meta_allowed( $key, 0, $post_type );
			}
			$meta[] = $item;
		}
		$meta[] = self::custom( __( 'Other meta key…', 'brik-builder' ), 'meta:', 'my_field' );
		$groups[] = self::group( 'meta', __( 'Post meta', 'brik-builder' ), 'database', $meta );

		if ( class_exists( 'WooCommerce' ) ) {
			$woo = array();
			if ( 'product' === $post_type ) {
				foreach ( \Brik\Woo\Tags::labels( array() ) as $tag => $label ) {
					if ( 0 === strpos( $tag, 'product:' ) && false === strpos( $tag, 'pa_KEY' ) ) {
						$woo[] = self::item( $label, $tag, in_array( $tag, array( 'product:image' ), true ) ? 'image' : ( false !== strpos( $tag, 'url' ) || 'product:permalink' === $tag ? 'url' : 'text' ) );
					}
				}
			}
			foreach ( array( 'cart:count', 'cart:total', 'cart:subtotal', 'shop:url', 'cart:url', 'checkout:url', 'account:url' ) as $tag ) {
				$labels = \Brik\Woo\Tags::labels( array() );
				$woo[]  = self::item( $labels[ $tag ], $tag, false !== strpos( $tag, 'url' ) ? 'url' : 'text' );
			}
			$groups[] = self::group( 'woo', 'WooCommerce', 'shopping-bag', $woo );
		}

		$user = array(
			self::item( __( 'Display name', 'brik-builder' ), 'user:name' ),
			self::item( __( 'First name', 'brik-builder' ), 'user:first_name' ),
			self::item( __( 'Last name', 'brik-builder' ), 'user:last_name' ),
			self::item( __( 'Email', 'brik-builder' ), 'user:email' ),
			self::item( __( 'Username', 'brik-builder' ), 'user:login' ),
			self::item( __( 'Role', 'brik-builder' ), 'user:role', 'list' ),
			self::item( __( 'Avatar', 'brik-builder' ), 'user:avatar', 'image' ),
			self::item( __( 'ID', 'brik-builder' ), 'user:id', 'number' ),
			self::item( __( 'Registered', 'brik-builder' ), 'user:registered', 'date' ),
		);
		foreach ( Tags::user_meta_keys() as $key ) {
			if ( ! in_array( $key, array( 'first_name', 'last_name' ), true ) ) {
				$user[] = self::item( $key, 'user:meta.' . $key );
			}
		}
		$groups[] = self::group( 'user', __( 'Current user', 'brik-builder' ), 'user', $user );

		$site = array(
			self::item( __( 'Site title', 'brik-builder' ), 'site:name' ),
			self::item( __( 'Tagline', 'brik-builder' ), 'site:tagline' ),
			self::item( __( 'Home URL', 'brik-builder' ), 'site:url', 'url' ),
			self::item( __( 'Logo', 'brik-builder' ), 'site:logo', 'image' ),
			self::item( __( 'Site icon', 'brik-builder' ), 'site:icon', 'url' ),
			self::item( __( 'Language', 'brik-builder' ), 'site:language' ),
		);
		$groups[] = self::group( 'site', __( 'Site', 'brik-builder' ), 'globe', $site );

		$options = array();
		if ( class_exists( '\\Brik\\Content\\Registry' ) ) {
			foreach ( \Brik\Content\Registry::fields_for( 'option' ) as $name => $f ) {
				$options[] = self::item( $f['label'], 'option:' . $name, self::brik_kind( $f ) );
			}
		}
		foreach ( Tags::public_options() as $name => $label ) {
			$options[] = self::item( $label, 'option:' . $name, in_array( $name, array( 'home', 'siteurl' ), true ) ? 'url' : 'text' );
		}
		$groups[] = self::group( 'options', __( 'Options', 'brik-builder' ), 'sliders-horizontal', $options );

		$groups[] = self::group(
			'request',
			__( 'URL & date', 'brik-builder' ),
			'calendar-clock',
			array(
				self::custom( __( 'URL parameter…', 'brik-builder' ), 'param:', 'utm_source' ),
				self::item( __( 'Search query', 'brik-builder' ), 'search_query' ),
				self::item( __( 'Archive title', 'brik-builder' ), 'archive_title' ),
				self::item( __( 'Today', 'brik-builder' ), 'now:date' ),
				self::item( __( 'Current time', 'brik-builder' ), 'now:time' ),
				self::item( __( 'Current year', 'brik-builder' ), 'now:year' ),
				self::item( __( 'Weekday', 'brik-builder' ), 'now:weekday' ),
				self::custom( __( 'Custom date format…', 'brik-builder' ), 'now:', 'Y-m-d' ),
			)
		);

		$groups = self::previews( $groups, $sid );

		return array(
			'context'    => array(
				'post_id'   => $sid,
				'post_type' => $post_type,
				'title'     => $sample ? get_the_title( $sample ) : '',
			),
			'post_types' => Data::post_types(),
			'modifiers'  => self::modifiers(),
			'groups'     => $groups,
		);
	}

	private static function group( $key, $label, $icon, array $items ) {
		return array(
			'key'   => $key,
			'label' => $label,
			'icon'  => $icon,
			'items' => array_values( $items ),
		);
	}

	private static function item( $label, $tag, $kind = 'text', array $extra = array() ) {
		return array_merge(
			array(
				'label' => (string) $label,
				'tag'   => $tag,
				'kind'  => $kind,
			),
			$extra
		);
	}

	private static function sub( $label, array $items ) {
		return array(
			'label' => (string) $label,
			'items' => array_values( $items ),
		);
	}

	/** An entry that needs a name typed in (URL parameter, meta key, date format). */
	private static function custom( $label, $prefix, $placeholder ) {
		return array(
			'label'       => $label,
			'tag'         => $prefix,
			'kind'        => 'text',
			'input'       => true,
			'placeholder' => $placeholder,
		);
	}

	private static function previews( array $items, $post_id ) {
		foreach ( $items as &$item ) {
			if ( isset( $item['items'] ) ) {
				$item['items'] = self::previews( $item['items'], $post_id );
			}
			if ( isset( $item['tag'] ) && empty( $item['input'] ) ) {
				$value           = \Brik\Dynamic::value( $item['tag'], $post_id );
				$text            = null === $value ? '' : trim( wp_strip_all_tags( html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' ) ) );
				$item['preview'] = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
			}
		}
		return $items;
	}

	private static function brik_kind( array $f ) {
		switch ( $f['type'] ) {
			case 'image':
				return 'image';
			case 'file':
			case 'url':
			case 'oembed':
			case 'link':
				return 'url';
			case 'number':
			case 'range':
				return 'number';
			case 'wysiwyg':
				return 'html';
			case 'relationship':
			case 'post_object':
				return 'posts';
			case 'taxonomy':
				return 'terms';
			case 'checkbox':
			case 'gallery':
				return 'list';
			case 'toggle':
				return 'bool';
		}
		return 'text';
	}

	private static function acf_kind( array $f ) {
		switch ( $f['type'] ) {
			case 'image':
				return 'image';
			case 'file':
			case 'url':
			case 'link':
			case 'oembed':
			case 'page_link':
				return 'url';
			case 'number':
			case 'range':
				return 'number';
			case 'date_picker':
			case 'date_time_picker':
				return 'date';
			case 'wysiwyg':
				return 'html';
			case 'post_object':
			case 'relationship':
				return 'posts';
			case 'taxonomy':
				return 'terms';
			case 'checkbox':
			case 'gallery':
			case 'user':
				return 'list';
			case 'true_false':
				return 'bool';
		}
		return 'text';
	}

	private static function plugin_kind( $type ) {
		if ( in_array( $type, array( 'image', 'image_advanced', 'image_upload', 'single_image', 'file' ), true ) ) {
			return 'image';
		}
		if ( in_array( $type, array( 'number', 'range', 'slider', 'currency' ), true ) ) {
			return 'number';
		}
		if ( in_array( $type, array( 'date', 'datetime' ), true ) ) {
			return 'date';
		}
		if ( in_array( $type, array( 'url', 'website', 'oembed' ), true ) ) {
			return 'url';
		}
		return 'text';
	}

	/* ---------------------------------------------------------------------
	 * Meta key discovery.
	 * ------------------------------------------------------------------- */

	/**
	 * Distinct meta keys used by recent posts of a type, with a guessed value type. One
	 * prepared, capped query over the newest posts, cached until content changes.
	 *
	 * @return array key => [ type => text|number|date|list, private => bool ]
	 */
	public static function meta_keys( $post_type, $private = false ) {
		global $wpdb;
		$version = function_exists( 'brik_listing_cache_version' ) ? brik_listing_cache_version() : 1;
		$cache   = 'brik_data_meta_' . md5( $post_type . '|' . $version );
		$found   = get_transient( $cache );

		if ( ! is_array( $found ) ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
					INNER JOIN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'auto-draft', 'trash' ) ORDER BY ID DESC LIMIT %d ) p ON p.ID = pm.post_id
					LIMIT %d",
					$post_type,
					self::META_SCAN_POSTS,
					self::META_SCAN_ROWS
				),
				ARRAY_N
			);
			$found = array();
			foreach ( (array) $rows as $row ) {
				list( $key, $value ) = $row;
				if ( ! isset( $found[ $key ] ) ) {
					$found[ $key ] = array(
						'number' => true,
						'date'   => true,
						'list'   => false,
						'seen'   => 0,
					);
				}
				$v = (string) $value;
				if ( '' === $v ) {
					continue;
				}
				++$found[ $key ]['seen'];
				if ( is_serialized( $v ) ) {
					$found[ $key ]['list']   = true;
					$found[ $key ]['number'] = false;
					$found[ $key ]['date']   = false;
					continue;
				}
				if ( ! is_numeric( $v ) ) {
					$found[ $key ]['number'] = false;
				}
				if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v ) ) {
					$found[ $key ]['date'] = false;
				}
			}
			ksort( $found );
			set_transient( $cache, $found, HOUR_IN_SECONDS );
		}

		$out = array();
		foreach ( $found as $key => $info ) {
			$protected = is_protected_meta( $key, 'post' );
			if ( $protected && ! $private && ! Tags::meta_allowed( $key, 0, $post_type ) ) {
				continue;
			}
			// Internal bookkeeping keys aren't content.
			if ( preg_match( '/^_(edit_|wp_|oembed_|brik_|menu_item|encloseme|pingme|thumbnail_id)/', $key ) && ! $private ) {
				continue;
			}
			$type = 'text';
			if ( $info['seen'] && $info['list'] ) {
				$type = 'list';
			} elseif ( $info['seen'] && $info['number'] ) {
				$type = 'number';
			} elseif ( $info['seen'] && $info['date'] ) {
				$type = 'date';
			}
			$out[ $key ] = array(
				'type'    => $type,
				'private' => $protected,
			);
			if ( count( $out ) >= self::META_MAX_KEYS ) {
				break;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Query builder fields.
	 * ------------------------------------------------------------------- */

	/**
	 * Fields a query can filter and sort on, keyed by field key.
	 *
	 * Field: key, label, group, type (text|number|date|choice|term|user|bool|post), options,
	 * multiple, meta_key (meta based fields), date_format (stored date format).
	 */
	public static function fields( $post_type ) {
		$out  = array();
		$post = __( 'Post', 'brik-builder' );
		$add  = static function ( $key, $label, $group, $type, array $extra = array() ) use ( &$out ) {
			$out[ $key ] = array_merge(
				array(
					'key'   => $key,
					'label' => (string) $label,
					'group' => (string) $group,
					'type'  => $type,
				),
				$extra
			);
		};

		$add( 'post:title', __( 'Title', 'brik-builder' ), $post, 'text' );
		$add( 'post:date', __( 'Published date', 'brik-builder' ), $post, 'date' );
		$add( 'post:modified', __( 'Last updated', 'brik-builder' ), $post, 'date' );
		$add( 'post:author', __( 'Author', 'brik-builder' ), $post, 'user', array( 'options' => self::authors( $post_type ) ) );
		$add( 'post:id', __( 'ID', 'brik-builder' ), $post, 'number' );
		$add( 'post:slug', __( 'Slug', 'brik-builder' ), $post, 'text' );
		if ( is_post_type_hierarchical( $post_type ) ) {
			$add( 'post:parent', __( 'Parent ID', 'brik-builder' ), $post, 'number' );
		}
		$add( 'post:menu_order', __( 'Menu order', 'brik-builder' ), $post, 'number' );
		$add( 'post:comment_count', __( 'Comment count', 'brik-builder' ), $post, 'number' );

		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
			if ( ! is_taxonomy_viewable( $tax ) || 'post_format' === $tax->name ) {
				continue;
			}
			$terms   = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
					'number'     => 200,
				)
			);
			$options = array();
			foreach ( is_wp_error( $terms ) ? array() : $terms as $t ) {
				$options[] = array(
					'value' => $t->slug,
					'label' => $t->name,
				);
			}
			$add( 'tax:' . $tax->name, $tax->labels->name, __( 'Taxonomies', 'brik-builder' ), 'term', array( 'options' => $options ) );
		}

		$own = class_exists( '\\Brik\\Content\\Registry' ) ? \Brik\Content\Registry::fields_for_post_type( $post_type ) : array();
		foreach ( $own as $name => $f ) {
			$type = self::field_type( $f['type'] );
			if ( ! $type ) {
				continue;
			}
			$extra = array(
				'meta_key' => $name,
				'multiple' => in_array( $f['type'], array( 'checkbox', 'relationship', 'gallery' ), true ) || ( ! empty( $f['options']['multiple'] ) ),
			);
			if ( 'choice' === $type ) {
				$extra['options'] = array();
				foreach ( isset( $f['options']['choices'] ) ? (array) $f['options']['choices'] : array() as $c ) {
					if ( is_array( $c ) && isset( $c['value'] ) ) {
						$extra['options'][] = array(
							'value' => (string) $c['value'],
							'label' => isset( $c['label'] ) && '' !== $c['label'] ? (string) $c['label'] : (string) $c['value'],
						);
					}
				}
			}
			$add( 'field:' . $name, $f['label'], __( 'Custom fields', 'brik-builder' ), $type, $extra );
		}

		foreach ( Plugins::acf_fields( $post_type ) as $name => $f ) {
			$type = self::acf_type( $f['type'] );
			if ( ! $type ) {
				continue;
			}
			$extra = array(
				'meta_key' => $name,
				'multiple' => in_array( $f['type'], array( 'checkbox', 'relationship', 'gallery' ), true ) || ! empty( $f['multiple'] ),
			);
			if ( 'choice' === $type ) {
				$extra['options'] = array();
				foreach ( isset( $f['choices'] ) ? (array) $f['choices'] : array() as $v => $l ) {
					$extra['options'][] = array(
						'value' => (string) $v,
						'label' => (string) $l,
					);
				}
			}
			if ( 'date' === $type ) {
				$extra['date_format'] = 'date_picker' === $f['type'] ? 'Ymd' : 'Y-m-d H:i:s';
			}
			$add( 'acf:' . $name, $f['label'] ? $f['label'] : $name, 'ACF', $type, $extra );
		}

		foreach ( Plugins::metabox_fields( $post_type ) as $name => $f ) {
			$type  = self::plugin_type( $f['type'], ! empty( $f['choices'] ) );
			$extra = array(
				'meta_key' => $name,
				'multiple' => ! empty( $f['multiple'] ),
			);
			if ( 'choice' === $type ) {
				$extra['options'] = array();
				foreach ( $f['choices'] as $v => $l ) {
					$extra['options'][] = array(
						'value' => (string) $v,
						'label' => (string) $l,
					);
				}
			}
			$add( 'metabox:' . $name, $f['label'], 'Meta Box', $type, $extra );
		}

		foreach ( Plugins::pods_fields( $post_type ) as $name => $f ) {
			$add( 'pods:' . $name, $f['label'], 'Pods', self::plugin_type( $f['type'], false ), array( 'meta_key' => $name ) );
		}

		if ( 'product' === $post_type && class_exists( 'WooCommerce' ) ) {
			$woo = 'WooCommerce';
			$add( 'product:price', __( 'Price', 'brik-builder' ), $woo, 'number', array( 'meta_key' => '_price' ) );
			$add( 'product:regular_price', __( 'Regular price', 'brik-builder' ), $woo, 'number', array( 'meta_key' => '_regular_price' ) );
			$add( 'product:sku', __( 'SKU', 'brik-builder' ), $woo, 'text', array( 'meta_key' => '_sku' ) );
			$add(
				'product:stock_status',
				__( 'Stock status', 'brik-builder' ),
				$woo,
				'choice',
				array(
					'meta_key' => '_stock_status',
					'options'  => array(
						array(
							'value' => 'instock',
							'label' => __( 'In stock', 'brik-builder' ),
						),
						array(
							'value' => 'outofstock',
							'label' => __( 'Out of stock', 'brik-builder' ),
						),
						array(
							'value' => 'onbackorder',
							'label' => __( 'On backorder', 'brik-builder' ),
						),
					),
				)
			);
			$add( 'product:rating', __( 'Average rating', 'brik-builder' ), $woo, 'number', array( 'meta_key' => '_wc_average_rating' ) );
			$add( 'product:sales', __( 'Total sales', 'brik-builder' ), $woo, 'number', array( 'meta_key' => 'total_sales' ) );
		}

		$taken = array();
		foreach ( $out as $f ) {
			if ( ! empty( $f['meta_key'] ) ) {
				$taken[] = $f['meta_key'];
			}
		}
		foreach ( self::meta_keys( $post_type ) as $key => $info ) {
			if ( in_array( $key, $taken, true ) || 'list' === $info['type'] ) {
				continue;
			}
			$add( 'meta:' . $key, $key, __( 'Post meta', 'brik-builder' ), $info['type'], array( 'meta_key' => $key ) );
		}

		return apply_filters( 'brik/data/query_fields', $out, $post_type );
	}

	private static function authors( $post_type ) {
		$users = get_users(
			array(
				'has_published_posts' => array( $post_type ),
				'number'              => 50,
				'fields'              => array( 'ID', 'display_name' ),
			)
		);
		$out   = array(
			array(
				'value' => 'current',
				'label' => __( 'Logged-in user', 'brik-builder' ),
			),
		);
		foreach ( $users as $u ) {
			$out[] = array(
				'value' => (string) $u->ID,
				'label' => $u->display_name,
			);
		}
		return $out;
	}

	private static function field_type( $type ) {
		$map = array(
			'text'         => 'text',
			'textarea'     => 'text',
			'email'        => 'text',
			'url'          => 'text',
			'number'       => 'number',
			'range'        => 'number',
			'select'       => 'choice',
			'radio'        => 'choice',
			'checkbox'     => 'choice',
			'button_group' => 'choice',
			'toggle'       => 'bool',
			'date'         => 'date',
			'datetime'     => 'date',
			'time'         => 'text',
			'color'        => 'text',
			'post_object'  => 'post',
			'relationship' => 'post',
			'user'         => 'number',
			'image'        => 'number',
			'file'         => 'number',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	private static function acf_type( $type ) {
		$map = array(
			'text'             => 'text',
			'textarea'         => 'text',
			'email'            => 'text',
			'url'              => 'text',
			'password'         => '',
			'number'           => 'number',
			'range'            => 'number',
			'select'           => 'choice',
			'radio'            => 'choice',
			'checkbox'         => 'choice',
			'button_group'     => 'choice',
			'true_false'       => 'bool',
			'date_picker'      => 'date',
			'date_time_picker' => 'date',
			'time_picker'      => 'text',
			'color_picker'     => 'text',
			'post_object'      => 'post',
			'relationship'     => 'post',
			'user'             => 'number',
			'image'            => 'number',
			'file'             => 'number',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : '';
	}

	private static function plugin_type( $type, $has_choices ) {
		if ( $has_choices ) {
			return 'choice';
		}
		if ( in_array( $type, array( 'number', 'range', 'slider', 'currency' ), true ) ) {
			return 'number';
		}
		if ( in_array( $type, array( 'date', 'datetime' ), true ) ) {
			return 'date';
		}
		if ( in_array( $type, array( 'checkbox', 'switch', 'boolean' ), true ) ) {
			return 'bool';
		}
		return 'text';
	}
}
