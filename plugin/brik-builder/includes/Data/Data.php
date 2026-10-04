<?php
/**
 * Data feature: database-aware dynamic tags, the visual query builder and display conditions.
 *
 * Note: the core class \Brik\Data stores builder trees. This namespace (Brik\Data\…) is a
 * separate feature; inside it always refer to the core class as \Brik\Data.
 *
 * @package Brik
 */

namespace Brik\Data;

defined( 'ABSPATH' ) || exit;

final class Data {

	public static function init() {
		Tags::init();
		Query::init();
		Conditions::init();
		Rest::init();
		Mcp::init();
	}

	/**
	 * The post a tag or rule should read from. Templates and library items stand in for other
	 * content, so they use the viewed post on the front end and a sample post in previews.
	 */
	public static function context_post( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}
		if ( $post_id && function_exists( 'brik_site_is_layout' ) && brik_site_is_layout( $post_id ) ) {
			if ( in_the_loop() && get_the_ID() !== $post_id ) {
				return (int) get_the_ID();
			}
			if ( is_singular() && ! \Brik\ThemeBuilder::is_template( get_queried_object_id() ) ) {
				return (int) get_queried_object_id();
			}
			$sample = function_exists( 'brik_site_sample_post' ) ? brik_site_sample_post() : null;
			return $sample instanceof \WP_Post ? (int) $sample->ID : 0;
		}
		return $post_id;
	}

	/**
	 * Whether the current visitor may see a post's data.
	 */
	public static function can_read( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		if ( 'publish' === $post->post_status || 'inherit' === $post->post_status ) {
			return is_post_type_viewable( $post->post_type ) || current_user_can( 'read_post', $post->ID );
		}
		return current_user_can( 'read_post', $post->ID );
	}

	/**
	 * Labels of the post types a query may target (viewable ones).
	 */
	public static function post_types() {
		$out = array();
		foreach ( get_post_types( array(), 'objects' ) as $type ) {
			if ( is_post_type_viewable( $type ) && 'attachment' !== $type->name ) {
				$out[ $type->name ] = $type->labels->name;
			}
		}
		return $out;
	}
}
