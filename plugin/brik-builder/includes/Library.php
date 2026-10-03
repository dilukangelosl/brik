<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Saved layouts, sections, modules and global elements, plus the bundled layout packs.
 */
final class Library {

	const POST_TYPE = 'brik_layout';
	const META_KIND = '_brik_kind';

	/** Loop items: the post shown while designing, its post type and the preview width. */
	const META_PREVIEW      = '_brik_preview_post';
	const META_LOOP_TYPE    = '_brik_loop_post_type';
	const META_LOOP_WIDTH   = '_brik_loop_width';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'preview_render' ), 10, 3 );
	}

	public static function kinds() {
		return array(
			'layout'  => __( 'Layout', 'brik-builder' ),
			'section' => __( 'Section', 'brik-builder' ),
			'row'     => __( 'Row', 'brik-builder' ),
			'module'  => __( 'Module', 'brik-builder' ),
			'loop'    => __( 'Loop item', 'brik-builder' ),
		);
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Library', 'brik-builder' ),
					'singular_name' => __( 'Library item', 'brik-builder' ),
					'edit_item'     => __( 'Edit library item', 'brik-builder' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => true,
				'exclude_from_search' => true,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author', 'revisions' ),
				'rewrite'             => false,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Loop item preview.
	 * ------------------------------------------------------------------- */

	public static function is_loop( $id ) {
		return get_post_type( $id ) === self::POST_TYPE && 'loop' === get_post_meta( $id, self::META_KIND, true );
	}

	/**
	 * The post a loop item is previewed with in the builder: an explicit choice (the
	 * ?brik_preview_post= canvas parameter or the stored meta), else the latest post of the
	 * loop's post type.
	 */
	public static function preview_post( $id, $requested = 0 ) {
		$readable = static function ( $post ) {
			return $post instanceof \WP_Post && self::POST_TYPE !== $post->post_type
				&& ( 'publish' === $post->post_status || current_user_can( 'edit_post', $post->ID ) );
		};
		foreach ( array( (int) $requested, (int) get_post_meta( $id, self::META_PREVIEW, true ) ) as $candidate ) {
			$post = $candidate ? get_post( $candidate ) : null;
			if ( $readable( $post ) ) {
				return $post;
			}
		}
		$type  = (string) get_post_meta( $id, self::META_LOOP_TYPE, true );
		$type  = $type && post_type_exists( $type ) ? $type : 'post';
		$posts = get_posts(
			array(
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'suppress_filters' => false,
			)
		);
		return $posts ? $posts[0] : null;
	}

	public static function preview_width( $id ) {
		$width = (int) get_post_meta( $id, self::META_LOOP_WIDTH, true );
		return $width >= 200 && $width <= 1600 ? $width : 400;
	}

	public static function routes() {
		register_rest_route(
			Rest::NS,
			'/library/(?P<id>\d+)/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_preview' ),
				'permission_callback' => static function ( \WP_REST_Request $r ) {
					return self::item( (int) $r['id'] ) && current_user_can( 'edit_post', (int) $r['id'] );
				},
				'args'                => array(
					'post_id'   => array( 'sanitize_callback' => 'absint' ),
					'post_type' => array( 'sanitize_callback' => 'sanitize_key' ),
					'width'     => array( 'sanitize_callback' => 'absint' ),
				),
			)
		);
	}

	/**
	 * POST brik/v1/library/{id}/preview { post_id?, post_type?, width? }: choose the preview
	 * post of a loop item. post_id 0 clears the choice (the latest post of post_type is used).
	 */
	public static function save_preview( \WP_REST_Request $r ) {
		$id = (int) $r['id'];
		if ( null !== $r['post_type'] && '' !== $r['post_type'] ) {
			if ( ! post_type_exists( $r['post_type'] ) ) {
				return new \WP_Error( 'brik_invalid', __( 'Unknown post type.', 'brik-builder' ), array( 'status' => 400 ) );
			}
			update_post_meta( $id, self::META_LOOP_TYPE, $r['post_type'] );
		}
		if ( null !== $r['post_id'] ) {
			$post = (int) $r['post_id'] ? get_post( (int) $r['post_id'] ) : null;
			if ( (int) $r['post_id'] && ( ! $post || ! current_user_can( 'read_post', $post->ID ) ) ) {
				return new \WP_Error( 'brik_invalid', __( 'That post can\'t be used as a preview.', 'brik-builder' ), array( 'status' => 400 ) );
			}
			if ( $post ) {
				update_post_meta( $id, self::META_PREVIEW, $post->ID );
				if ( null === $r['post_type'] || '' === $r['post_type'] ) {
					update_post_meta( $id, self::META_LOOP_TYPE, $post->post_type );
				}
			} else {
				delete_post_meta( $id, self::META_PREVIEW );
			}
		}
		if ( null !== $r['width'] ) {
			update_post_meta( $id, self::META_LOOP_WIDTH, min( 1600, max( 200, (int) $r['width'] ) ) );
		}
		$preview = self::preview_post( $id );
		return array(
			'id'        => $id,
			'post_type' => (string) get_post_meta( $id, self::META_LOOP_TYPE, true ),
			'width'     => self::preview_width( $id ),
			'preview'   => $preview ? array(
				'id'    => $preview->ID,
				'title' => get_the_title( $preview ),
				'type'  => $preview->post_type,
			) : null,
		);
	}

	/**
	 * Live canvas updates of a loop item render against its preview post, so dynamic tags,
	 * {field:…} and post modules show real data while designing. Runs after the permission
	 * check, which was made against the library item itself.
	 */
	public static function preview_render( $result, $request, $route ) {
		if ( null !== $result || '/' . Rest::NS . '/render' !== $route ) {
			return $result;
		}
		$id = (int) $request->get_param( 'post_id' );
		if ( $id && self::is_loop( $id ) ) {
			$preview = self::preview_post( $id );
			if ( $preview ) {
				$request->set_param( 'post_id', $preview->ID );
			}
		}
		return $result;
	}

	public static function item( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$kind = get_post_meta( $post->ID, self::META_KIND, true );
		$item = array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'kind'     => $kind ? $kind : 'layout',
			'global'   => (bool) get_post_meta( $post->ID, '_brik_global', true ),
			'modified' => get_post_modified_time( 'c', true, $post ),
		);
		if ( 'loop' === $kind ) {
			$item['preview_post'] = (int) get_post_meta( $post->ID, self::META_PREVIEW, true );
			$item['post_type']    = (string) get_post_meta( $post->ID, self::META_LOOP_TYPE, true );
		}
		return $item;
	}

	public static function items( $kind = '' ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $kind ) {
			$args['meta_key']   = self::META_KIND; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $kind; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return array_values( array_filter( array_map( array( __CLASS__, 'item' ), get_posts( $args ) ) ) );
	}

	public static function create( $title, $kind, $nodes, $global = false ) {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $title ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, self::META_KIND, isset( self::kinds()[ $kind ] ) ? $kind : 'layout' );
		if ( $global ) {
			update_post_meta( $id, '_brik_global', 1 );
		}
		Data::save( $id, $nodes );
		return $id;
	}

	/**
	 * Nodes of a library item adapted to where they are inserted.
	 * Inside a column, a single wrapped module or row is unwrapped.
	 */
	public static function nodes_for( $id, $context = 'root' ) {
		$nodes = Data::get( $id );
		if ( 'column' !== $context || 1 !== count( $nodes ) ) {
			return $nodes;
		}
		$rows = isset( $nodes[0]['children'] ) ? $nodes[0]['children'] : array();
		if ( 1 === count( $rows ) && isset( $rows[0]['children'] ) && 1 === count( $rows[0]['children'] ) ) {
			return isset( $rows[0]['children'][0]['children'] ) ? $rows[0]['children'][0]['children'] : array();
		}
		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * Bundled layouts (layouts/*.json).
	 * ------------------------------------------------------------------- */

	public static function bundled() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		$cache = array();
		foreach ( glob( BRIK_DIR . 'layouts/*.json' ) as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) || empty( $data['tree'] ) ) {
				continue;
			}
			$cache[] = array(
				'slug'     => basename( $file, '.json' ),
				'title'    => isset( $data['title'] ) ? $data['title'] : basename( $file, '.json' ),
				'category' => isset( $data['category'] ) ? $data['category'] : 'general',
				'kind'     => isset( $data['kind'] ) ? $data['kind'] : 'section',
				'tree'     => $data['tree'],
			);
		}
		return apply_filters( 'brik/bundled_layouts', $cache );
	}

	public static function bundled_one( $slug ) {
		foreach ( self::bundled() as $layout ) {
			if ( $layout['slug'] === $slug ) {
				return $layout;
			}
		}
		return null;
	}
}
