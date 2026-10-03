<?php
namespace Brik\Content;

use Brik\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the defined post types, taxonomies and field meta with WordPress.
 */
final class Register {

	private static $types_done = false;

	private static $meta_done = false;

	/** Meta keys we registered, per object type and subtype, for is_protected_meta. */
	private static $keys = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_types' ), 5 );
		// Late enough for post types other plugins add on the default priority.
		add_action( 'init', array( __CLASS__, 'register_meta' ), 20 );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 99 );
		add_filter( 'brik/post_types', array( __CLASS__, 'builder_types' ) );
		add_filter( 'is_protected_meta', array( __CLASS__, 'protect_meta' ), 10, 3 );
		// Definitions saved during a request (REST, MCP) are usable right away.
		add_action(
			'brik/content/saved',
			static function ( $def, $kind ) {
				Register::late( $kind, $def );
			},
			10,
			2
		);
	}

	public static function register_types() {
		foreach ( Registry::post_types( true ) as $def ) {
			self::register_post_type( $def );
		}
		foreach ( Registry::taxonomies( true ) as $def ) {
			self::register_taxonomy( $def );
		}
		self::$types_done = true;
	}

	public static function register_post_type( array $def ) {
		if ( post_type_exists( $def['key'] ) && ! self::ours( 'post_type', $def['key'] ) ) {
			return;
		}
		$result = register_post_type( $def['key'], self::post_type_args( $def ) );
		if ( ! is_wp_error( $result ) ) {
			// Built-in taxonomies attach from here; ours list their post types themselves.
			foreach ( $def['taxonomies'] as $tax ) {
				if ( taxonomy_exists( $tax ) ) {
					register_taxonomy_for_object_type( $tax, $def['key'] );
				}
			}
		}
	}

	public static function register_taxonomy( array $def ) {
		if ( taxonomy_exists( $def['key'] ) && ! self::ours( 'taxonomy', $def['key'] ) ) {
			return;
		}
		$types = $def['post_types'];
		// A post type may also claim the taxonomy from its own definition.
		foreach ( Registry::post_types( true ) as $type ) {
			if ( in_array( $def['key'], $type['taxonomies'], true ) ) {
				$types[] = $type['key'];
			}
		}
		register_taxonomy( $def['key'], array_values( array_unique( $types ) ), self::taxonomy_args( $def ) );
	}

	private static function ours( $what, $key ) {
		$object = 'post_type' === $what ? get_post_type_object( $key ) : get_taxonomy( $key );
		return $object && ! empty( $object->brik_content );
	}

	public static function post_type_args( array $def ) {
		$public  = (bool) $def['public'];
		$archive = false;
		if ( $def['has_archive'] ) {
			$archive = '' !== $def['archive_slug'] ? $def['archive_slug'] : ( '' !== $def['rewrite_slug'] ? $def['rewrite_slug'] : true );
		}
		$args = array(
			'labels'              => Registry::post_type_labels( $def['singular'], $def['plural'], $def['labels'] ),
			'description'         => $def['description'],
			'public'              => $public,
			'publicly_queryable'  => $public,
			'exclude_from_search' => (bool) $def['exclude_from_search'] || ! $public,
			'show_ui'             => true,
			'show_in_menu'        => (bool) $def['show_in_menu'],
			'show_in_nav_menus'   => $public,
			'show_in_admin_bar'   => (bool) $def['show_in_menu'],
			'show_in_rest'        => (bool) $def['show_in_rest'],
			'menu_position'       => (int) $def['menu_position'],
			'menu_icon'           => self::menu_icon( $def['icon'] ),
			'hierarchical'        => (bool) $def['hierarchical'],
			'supports'            => $def['supports'] ? $def['supports'] : false,
			'taxonomies'          => $def['taxonomies'],
			'has_archive'         => $public ? $archive : false,
			'rewrite'             => $public ? array(
				'slug'       => '' !== $def['rewrite_slug'] ? $def['rewrite_slug'] : $def['key'],
				'with_front' => false,
			) : false,
			'query_var'           => $public,
			'capability_type'     => $def['capability_type'] ? $def['capability_type'] : 'post',
			'map_meta_cap'        => true,
			'delete_with_user'    => false,
			'brik_content'        => true,
		);
		/**
		 * Arguments passed to register_post_type() for a Brik post type.
		 *
		 * @param array $args Arguments.
		 * @param array $def  Brik definition.
		 */
		return apply_filters( 'brik/content/post_type_args', $args, $def );
	}

	public static function taxonomy_args( array $def ) {
		$public = (bool) $def['public'];
		$args   = array(
			'labels'             => Registry::taxonomy_labels( $def['singular'], $def['plural'], $def['labels'], $def['hierarchical'] ),
			'description'        => $def['description'],
			'public'             => $public,
			'publicly_queryable' => $public,
			'hierarchical'       => (bool) $def['hierarchical'],
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_nav_menus'  => $public,
			'show_in_rest'       => (bool) $def['show_in_rest'],
			'show_tagcloud'      => ! $def['hierarchical'],
			'show_admin_column'  => (bool) $def['show_admin_column'],
			'rewrite'            => $public ? array(
				'slug'         => '' !== $def['rewrite_slug'] ? $def['rewrite_slug'] : $def['key'],
				'with_front'   => false,
				'hierarchical' => (bool) $def['hierarchical'],
			) : false,
			'query_var'          => $public,
			'brik_content'       => true,
		);
		/**
		 * Arguments passed to register_taxonomy() for a Brik taxonomy.
		 *
		 * @param array $args Arguments.
		 * @param array $def  Brik definition.
		 */
		return apply_filters( 'brik/content/taxonomy_args', $args, $def );
	}

	/**
	 * Dashicon classes pass through; "lucide:name" becomes an SVG data URI drawn in the
	 * admin menu's gray.
	 */
	public static function menu_icon( $icon ) {
		if ( 0 === strpos( (string) $icon, 'lucide:' ) ) {
			$svg = Icons::svg(
				substr( $icon, 7 ),
				array(
					'stroke'      => '#a7aaad',
					'width'       => '20',
					'height'      => '20',
					'aria-hidden' => null,
				)
			);
			if ( $svg ) {
				return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
			return 'dashicons-admin-post';
		}
		return $icon ? $icon : 'dashicons-admin-post';
	}

	/**
	 * Enable the builder for types defined with "brik": true.
	 */
	public static function builder_types( $types ) {
		foreach ( Registry::post_types( true ) as $def ) {
			if ( $def['brik'] ) {
				$types[] = $def['key'];
			}
		}
		return $types;
	}

	public static function maybe_flush() {
		if ( get_option( Registry::OPT_FLUSH ) ) {
			delete_option( Registry::OPT_FLUSH );
			flush_rewrite_rules( false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Meta.
	 * ------------------------------------------------------------------- */

	public static function register_meta() {
		foreach ( Registry::groups( true ) as $group ) {
			self::register_group_meta( $group );
		}
		self::$meta_done = true;
	}

	/**
	 * Run registration for a definition added after init already fired.
	 */
	public static function late( $kind, array $def ) {
		if ( 'post_types' === $kind && self::$types_done && ! empty( $def['active'] ) ) {
			self::register_post_type( $def );
		} elseif ( 'taxonomies' === $kind && self::$types_done && ! empty( $def['active'] ) ) {
			self::register_taxonomy( $def );
		} elseif ( 'groups' === $kind && self::$meta_done && ! empty( $def['active'] ) ) {
			self::register_group_meta( $def );
		}
	}

	public static function register_group_meta( array $group ) {
		$targets = Registry::targets( $group );
		foreach ( $group['fields'] as $field ) {
			if ( ! Fields::has_value( $field['type'] ) ) {
				continue;
			}
			foreach ( $targets['post_types'] as $type ) {
				// REST only exposes meta for types that support custom fields.
				if ( ! post_type_supports( $type, 'custom-fields' ) ) {
					add_post_type_support( $type, 'custom-fields' );
				}
				register_post_meta( $type, $field['name'], self::meta_args( $field, 'post' ) );
				self::$keys['post'][ $type ][ $field['name'] ] = true;
			}
			foreach ( $targets['taxonomies'] as $tax ) {
				register_term_meta( $tax, $field['name'], self::meta_args( $field, 'term' ) );
				self::$keys['term'][ $tax ][ $field['name'] ] = true;
			}
			if ( $targets['users'] ) {
				register_meta( 'user', $field['name'], self::meta_args( $field, 'user' ) );
				self::$keys['user'][''][ $field['name'] ] = true;
			}
		}
	}

	public static function meta_args( array $field, $object ) {
		$schema = Fields::rest_schema( $field );
		$type   = $schema['type'];
		if ( '' !== $field['default'] && null !== $field['default'] && ! is_array( $field['default'] ) ) {
			$schema['default'] = Values::to_storage( $field, $field['default'] );
		}
		return array(
			'type'              => $type,
			'description'       => $field['label'],
			'single'            => true,
			'show_in_rest'      => array( 'schema' => $schema ),
			'sanitize_callback' => static function ( $value ) use ( $field ) {
				return Values::to_storage( $field, Fields::sanitize( $field, $value ) );
			},
			'auth_callback'     => static function ( $allowed, $key, $id ) use ( $object ) {
				switch ( $object ) {
					case 'term':
						return current_user_can( 'edit_term', $id );
					case 'user':
						return current_user_can( 'edit_user', $id );
				}
				return current_user_can( 'edit_post', $id );
			},
		);
	}

	/**
	 * Hide field values from the classic Custom Fields box, which would show serialized
	 * arrays and could overwrite them.
	 */
	public static function protect_meta( $protected, $key, $type ) {
		if ( $protected || 'post' !== $type || empty( self::$keys['post'] ) ) {
			return $protected;
		}
		foreach ( self::$keys['post'] as $names ) {
			if ( isset( $names[ $key ] ) ) {
				return true;
			}
		}
		return $protected;
	}
}
