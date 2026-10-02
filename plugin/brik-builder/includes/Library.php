<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Saved layouts, sections, modules and global elements, plus the bundled layout packs.
 */
final class Library {

	const POST_TYPE = 'brik_layout';
	const META_KIND = '_brik_kind';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
	}

	public static function kinds() {
		return array(
			'layout'  => __( 'Layout', 'brik' ),
			'section' => __( 'Section', 'brik' ),
			'row'     => __( 'Row', 'brik' ),
			'module'  => __( 'Module', 'brik' ),
		);
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Library', 'brik' ),
					'singular_name' => __( 'Library item', 'brik' ),
					'edit_item'     => __( 'Edit library item', 'brik' ),
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

	public static function item( $post ) {
		$post = get_post( $post );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$kind = get_post_meta( $post->ID, self::META_KIND, true );
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'kind'     => $kind ? $kind : 'layout',
			'global'   => (bool) get_post_meta( $post->ID, '_brik_global', true ),
			'modified' => get_post_modified_time( 'c', true, $post ),
		);
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
