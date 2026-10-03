<?php
namespace Brik\Content;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes for the content admin app (brik/v1/content…) and the field pickers.
 */
final class RestContent {

	const NS = 'brik/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	public static function routes() {
		$admin  = array( __CLASS__, 'can_manage' );
		$editor = static function () {
			return current_user_can( 'edit_posts' );
		};
		$key    = '(?P<key>[a-z0-9_-]{1,40})';

		register_rest_route(
			self::NS,
			'/content',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'index' ),
				'permission_callback' => $admin,
			)
		);

		foreach ( array(
			'post-types' => 'post_types',
			'taxonomies' => 'taxonomies',
			'groups'     => 'groups',
		) as $route => $kind ) {
			register_rest_route(
				self::NS,
				'/content/' . $route,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function () use ( $kind ) {
							return self::with_stats( $kind, Registry::all_of( $kind ) );
						},
						'permission_callback' => $admin,
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => static function ( WP_REST_Request $r ) use ( $kind ) {
							return self::save( $kind, $r );
						},
						'permission_callback' => $admin,
					),
				)
			);
			register_rest_route(
				self::NS,
				'/content/' . $route . '/' . $key,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function ( WP_REST_Request $r ) use ( $kind ) {
							$def = self::find( $kind, (string) $r['key'] );
							return $def ? $def : new WP_Error( 'brik_content_not_found', __( 'Not found.', 'brik-builder' ), array( 'status' => 404 ) );
						},
						'permission_callback' => $admin,
					),
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => static function ( WP_REST_Request $r ) use ( $kind ) {
							return self::delete( $kind, $r );
						},
						'permission_callback' => $admin,
						'args'                => array(
							'delete_posts' => array( 'type' => 'boolean' ),
							'delete_terms' => array( 'type' => 'boolean' ),
						),
					),
				)
			);
		}

		register_rest_route(
			self::NS,
			'/content/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'import' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/content/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'export' ),
				'permission_callback' => $admin,
				'args'                => array(
					'format' => array(
						'type'    => 'string',
						'enum'    => array( 'json', 'php' ),
						'default' => 'json',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/content/preview-labels',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'preview_labels' ),
				'permission_callback' => $admin,
				'args'                => array(
					'singular'     => array( 'type' => 'string' ),
					'plural'       => array( 'type' => 'string' ),
					'kind'         => array(
						'type'    => 'string',
						'enum'    => array( 'post_type', 'taxonomy' ),
						'default' => 'post_type',
					),
					'hierarchical' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/content/search-posts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_posts' ),
				'permission_callback' => $editor,
			)
		);
		register_rest_route(
			self::NS,
			'/content/search-users',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_users' ),
				'permission_callback' => $editor,
			)
		);
		register_rest_route(
			self::NS,
			'/content/search-terms',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_terms' ),
				'permission_callback' => $editor,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Definitions.
	 * ------------------------------------------------------------------- */

	private static function find( $kind, $key ) {
		foreach ( self::with_stats( $kind, Registry::all_of( $kind ) ) as $def ) {
			if ( $def['key'] === $key ) {
				return $def;
			}
		}
		return null;
	}

	/**
	 * Add read-only counts the admin lists show.
	 */
	public static function with_stats( $kind, array $defs ) {
		foreach ( $defs as $i => $def ) {
			if ( 'post_types' === $kind ) {
				$counts                   = post_type_exists( $def['key'] ) ? (array) wp_count_posts( $def['key'] ) : array();
				$defs[ $i ]['count']      = (int) array_sum( array_intersect_key( $counts, array_flip( array( 'publish', 'draft', 'pending', 'private', 'future' ) ) ) );
				$defs[ $i ]['registered'] = post_type_exists( $def['key'] );
				$defs[ $i ]['edit_url']   = admin_url( 'edit.php?post_type=' . $def['key'] );
			} elseif ( 'taxonomies' === $kind ) {
				$count                    = taxonomy_exists( $def['key'] ) ? wp_count_terms( array( 'taxonomy' => $def['key'], 'hide_empty' => false ) ) : 0;
				$defs[ $i ]['count']      = is_wp_error( $count ) ? 0 : (int) $count;
				$defs[ $i ]['registered'] = taxonomy_exists( $def['key'] );
				$defs[ $i ]['edit_url']   = admin_url( 'edit-tags.php?taxonomy=' . $def['key'] );
			}
		}
		return $defs;
	}

	public static function index() {
		return array(
			'post_types'      => self::with_stats( 'post_types', Registry::post_types() ),
			'taxonomies'      => self::with_stats( 'taxonomies', Registry::taxonomies() ),
			'groups'          => Registry::groups(),
			'field_types'     => Fields::for_client(),
			'categories'      => Fields::categories(),
			'reserved'        => Registry::reserved(),
			'supports'        => Registry::supports_options(),
			'location_params' => self::location_params(),
			'condition_operators' => array(
				array( 'value' => '==', 'label' => __( 'is equal to', 'brik-builder' ) ),
				array( 'value' => '!=', 'label' => __( 'is not equal to', 'brik-builder' ) ),
				array( 'value' => 'empty', 'label' => __( 'is empty', 'brik-builder' ) ),
				array( 'value' => '!empty', 'label' => __( 'is not empty', 'brik-builder' ) ),
				array( 'value' => 'contains', 'label' => __( 'contains', 'brik-builder' ) ),
			),
			'widths'          => Registry::WIDTHS,
			'options_page'    => array(
				'slug' => MetaBoxes::OPTIONS,
				'url'  => admin_url( 'admin.php?page=' . MetaBoxes::OPTIONS ),
			),
		);
	}

	private static function choices_of( array $objects ) {
		$out = array();
		foreach ( $objects as $object ) {
			$out[] = array(
				'value' => $object->name,
				'label' => $object->labels->singular_name,
			);
		}
		return $out;
	}

	/**
	 * Location rule params with their value choices, for the group editor.
	 */
	public static function location_params() {
		$types = get_post_types( array( 'show_ui' => true ), 'objects' );
		unset( $types['attachment'], $types['wp_block'], $types['wp_navigation'], $types['wp_template'], $types['wp_template_part'] );
		$taxes = get_taxonomies( array( 'show_ui' => true ), 'objects' );

		$templates = array(
			array(
				'value' => 'default',
				'label' => __( 'Default template', 'brik-builder' ),
			),
		);
		foreach ( wp_get_theme()->get_page_templates( null, 'page' ) as $file => $name ) {
			$templates[] = array(
				'value' => $file,
				'label' => $name,
			);
		}
		foreach ( array( 'brik-canvas.php' => __( 'Brik canvas', 'brik-builder' ), 'brik-full-width.php' => __( 'Brik full width', 'brik-builder' ) ) as $file => $name ) {
			if ( ! in_array( $file, wp_list_pluck( $templates, 'value' ), true ) ) {
				$templates[] = array(
					'value' => $file,
					'label' => $name,
				);
			}
		}

		$roles = array(
			array(
				'value' => 'all',
				'label' => __( 'All roles', 'brik-builder' ),
			),
		);
		foreach ( wp_roles()->get_names() as $role => $name ) {
			$roles[] = array(
				'value' => $role,
				'label' => translate_user_role( $name ),
			);
		}

		$statuses = array();
		foreach ( get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' ) as $status ) {
			$statuses[] = array(
				'value' => $status->name,
				'label' => $status->label,
			);
		}

		$all = array(
			'value' => 'all',
			'label' => __( 'All', 'brik-builder' ),
		);
		return array(
			array(
				'value'   => 'post_type',
				'label'   => __( 'Post type', 'brik-builder' ),
				'choices' => array_merge( array( $all ), self::choices_of( $types ) ),
			),
			array(
				'value'   => 'post_template',
				'label'   => __( 'Page template', 'brik-builder' ),
				'choices' => $templates,
			),
			array(
				'value'   => 'page_type',
				'label'   => __( 'Page type', 'brik-builder' ),
				'choices' => array(
					array( 'value' => 'front_page', 'label' => __( 'Front page', 'brik-builder' ) ),
					array( 'value' => 'posts_page', 'label' => __( 'Posts page', 'brik-builder' ) ),
					array( 'value' => 'top_level', 'label' => __( 'Top level (no parent)', 'brik-builder' ) ),
					array( 'value' => 'parent', 'label' => __( 'Parent (has children)', 'brik-builder' ) ),
					array( 'value' => 'child', 'label' => __( 'Child (has parent)', 'brik-builder' ) ),
				),
			),
			array(
				'value'   => 'post_status',
				'label'   => __( 'Post status', 'brik-builder' ),
				'choices' => $statuses,
			),
			array(
				'value'   => 'taxonomy',
				'label'   => __( 'Taxonomy term screen', 'brik-builder' ),
				'choices' => array_merge( array( $all ), self::choices_of( $taxes ) ),
			),
			array(
				'value'   => 'user_role',
				'label'   => __( 'User profile', 'brik-builder' ),
				'choices' => $roles,
			),
			array(
				'value'   => 'options_page',
				'label'   => __( 'Options page', 'brik-builder' ),
				'choices' => array(
					array(
						'value' => MetaBoxes::OPTIONS,
						'label' => __( 'Site options', 'brik-builder' ),
					),
				),
			),
		);
	}

	private static function body( WP_REST_Request $r ) {
		$body = $r->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $r->get_body_params();
		}
		return is_array( $body ) ? $body : array();
	}

	private static function save( $kind, WP_REST_Request $r ) {
		$body     = self::body( $r );
		$previous = isset( $body['previous_key'] ) ? sanitize_key( $body['previous_key'] ) : '';
		unset( $body['previous_key'] );
		$def = Registry::save( $kind, $body, $previous );
		if ( is_wp_error( $def ) ) {
			return $def;
		}
		$stats = self::with_stats( $kind, array( $def ) );
		return $stats[0];
	}

	private static function delete( $kind, WP_REST_Request $r ) {
		$key = (string) $r['key'];
		switch ( $kind ) {
			case 'post_types':
				return Registry::delete_post_type( $key, (bool) $r['delete_posts'] );
			case 'taxonomies':
				return Registry::delete_taxonomy( $key, (bool) $r['delete_terms'] );
		}
		return Registry::delete_group( $key );
	}

	/**
	 * Import definitions. Taxonomies go first so post types can refer to them. Existing keys
	 * are updated unless "skip_existing" is set.
	 */
	public static function import( WP_REST_Request $r ) {
		return self::import_data( self::body( $r ) );
	}

	public static function import_data( array $body ) {
		$skip     = ! empty( $body['skip_existing'] );
		$imported = array(
			'post_types' => 0,
			'taxonomies' => 0,
			'groups'     => 0,
		);
		$errors   = array();
		foreach ( array( 'taxonomies', 'post_types', 'groups' ) as $kind ) {
			if ( empty( $body[ $kind ] ) || ! is_array( $body[ $kind ] ) ) {
				continue;
			}
			foreach ( $body[ $kind ] as $def ) {
				if ( ! is_array( $def ) ) {
					continue;
				}
				unset( $def['local'], $def['count'], $def['registered'], $def['edit_url'] );
				$key = isset( $def['key'] ) ? (string) $def['key'] : '';
				if ( $skip && $key && Registry::find_any( $kind, $key ) ) {
					continue;
				}
				$saved = Registry::save( $kind, $def );
				if ( is_wp_error( $saved ) ) {
					$label    = $key ? $key : ( isset( $def['title'] ) ? (string) $def['title'] : $kind );
					$errors[] = array(
						'kind'    => $kind,
						'key'     => $key,
						'message' => $label . ': ' . $saved->get_error_message(),
					);
					continue;
				}
				++$imported[ $kind ];
			}
		}
		return array_merge(
			self::index(),
			array(
				'imported' => $imported,
				'errors'   => $errors,
			)
		);
	}

	private static function list_param( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		return array_filter( array_map( 'sanitize_key', is_array( $value ) ? $value : explode( ',', (string) $value ) ) );
	}

	public static function export( WP_REST_Request $r ) {
		$only = array();
		foreach ( array( 'post_types', 'taxonomies', 'groups' ) as $kind ) {
			$list = self::list_param( $r[ $kind ] );
			if ( null !== $list ) {
				$only[ $kind ] = $list;
			}
		}
		if ( 'php' === $r['format'] ) {
			return array(
				'format'   => 'php',
				'filename' => 'brik-content.php',
				'code'     => Export::php( $only ),
			);
		}
		return Export::json( $only );
	}

	public static function preview_labels( WP_REST_Request $r ) {
		$singular = sanitize_text_field( (string) $r['singular'] );
		$plural   = sanitize_text_field( (string) $r['plural'] );
		if ( '' === $plural && '' !== $singular ) {
			$plural = $singular . 's';
		}
		$labels = 'taxonomy' === $r['kind']
			? Registry::taxonomy_labels( $singular, $plural, array(), (bool) $r['hierarchical'] )
			: Registry::post_type_labels( $singular, $plural );
		return array( 'labels' => $labels );
	}

	/* ---------------------------------------------------------------------
	 * Pickers.
	 * ------------------------------------------------------------------- */

	private static function ids( $value ) {
		return array_values( array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) );
	}

	/**
	 * GET /content/search-posts?post_type=a,b&s=&include=&exclude=&terms=tax:slug&page=
	 */
	public static function search_posts( WP_REST_Request $r ) {
		$allowed = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( ! in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ), true ) && current_user_can( $type->cap->edit_posts ) ) {
				$allowed[] = $type->name;
			}
		}
		$types = self::list_param( $r['post_type'] );
		$types = $types ? array_values( array_intersect( $types, $allowed ) ) : $allowed;
		if ( ! $types ) {
			return array(
				'items' => array(),
				'total' => 0,
				'pages' => 0,
			);
		}
		$per  = $r['per_page'] ? max( 1, min( 50, (int) $r['per_page'] ) ) : 20;
		$args = array(
			'post_type'           => $types,
			'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'      => $per,
			'paged'               => $r['page'] ? max( 1, (int) $r['page'] ) : 1,
			'perm'                => 'readable',
			'ignore_sticky_posts' => true,
			'orderby'             => 'title',
			'order'               => 'ASC',
		);
		$s = sanitize_text_field( (string) $r['s'] );
		if ( '' !== $s ) {
			$args['s']       = $s;
			$args['orderby'] = 'relevance';
		}
		$include = self::ids( $r['include'] );
		if ( $include ) {
			$args['post__in']       = $include;
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = count( $include );
		}
		$exclude = self::ids( $r['exclude'] );
		if ( $exclude ) {
			$args['post__not_in'] = $exclude;
		}
		$terms = array_filter( array_map( 'trim', explode( ',', (string) $r['terms'] ) ) );
		if ( $terms ) {
			$tq = array( 'relation' => 'OR' );
			foreach ( $terms as $pair ) {
				if ( preg_match( '/^([a-z0-9_-]+):([a-z0-9_-]+)$/', $pair, $m ) && taxonomy_exists( $m[1] ) ) {
					$tq[] = array(
						'taxonomy' => $m[1],
						'field'    => 'slug',
						'terms'    => $m[2],
					);
				}
			}
			if ( count( $tq ) > 1 ) {
				$args['tax_query'] = $tq; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
		}
		$q   = new \WP_Query( $args );
		$ids = array();
		foreach ( $q->posts as $post ) {
			if ( current_user_can( 'read_post', $post->ID ) ) {
				$ids[] = $post->ID;
			}
		}
		$items = MetaBoxes::describe( 'post', $ids );
		foreach ( $items as $i => $item ) {
			$items[ $i ]['type']   = get_post_type( $item['id'] );
			$items[ $i ]['status'] = get_post_status( $item['id'] );
			$items[ $i ]['url']    = get_permalink( $item['id'] );
		}
		return array(
			'items' => $items,
			'total' => (int) $q->found_posts,
			'pages' => (int) $q->max_num_pages,
		);
	}

	public static function search_users( WP_REST_Request $r ) {
		$args = array(
			'number'  => 20,
			'orderby' => 'display_name',
			'fields'  => 'ID',
		);
		$s = sanitize_text_field( (string) $r['s'] );
		if ( '' !== $s ) {
			$args['search']         = '*' . $s . '*';
			$args['search_columns'] = array( 'display_name', 'user_nicename', 'user_login' );
		}
		$roles = self::list_param( $r['role'] );
		if ( $roles ) {
			$args['role__in'] = $roles;
		}
		$include = self::ids( $r['include'] );
		if ( $include ) {
			$args['include'] = $include;
			$args['number']  = count( $include );
		}
		$items = MetaBoxes::describe( 'user', array_map( 'intval', get_users( $args ) ) );
		if ( ! current_user_can( 'list_users' ) ) {
			// Editors may pick authors by name, but don't need to see roles.
			foreach ( $items as $i => $item ) {
				$items[ $i ]['meta'] = '';
			}
		}
		return array( 'items' => $items );
	}

	public static function search_terms( WP_REST_Request $r ) {
		$tax = sanitize_key( (string) $r['taxonomy'] );
		if ( ! taxonomy_exists( $tax ) ) {
			return new WP_Error( 'brik_content_taxonomy', __( 'Unknown taxonomy.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( ! current_user_can( get_taxonomy( $tax )->cap->assign_terms ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You cannot assign these terms.', 'brik-builder' ), array( 'status' => 403 ) );
		}
		$args = array(
			'taxonomy'   => $tax,
			'hide_empty' => false,
			'number'     => 30,
			'fields'     => 'ids',
			'orderby'    => 'name',
		);
		$s = sanitize_text_field( (string) $r['s'] );
		if ( '' !== $s ) {
			$args['search'] = $s;
		}
		$include = self::ids( $r['include'] );
		if ( $include ) {
			$args['include'] = $include;
			$args['number']  = count( $include );
		}
		$ids = get_terms( $args );
		return array( 'items' => MetaBoxes::describe( 'term', is_wp_error( $ids ) ? array() : array_map( 'intval', $ids ) ) );
	}
}
