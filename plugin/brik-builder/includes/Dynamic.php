<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic content tags for text fields, e.g. "© {year} {site_name}" or "{meta:price}".
 */
final class Dynamic {

	public static function tags() {
		return apply_filters(
			'brik/dynamic_tags',
			array(
				'post_title'     => __( 'Post title', 'brik-builder' ),
				'post_excerpt'   => __( 'Post excerpt', 'brik-builder' ),
				'post_date'      => __( 'Post date', 'brik-builder' ),
				'post_modified'  => __( 'Last updated date', 'brik-builder' ),
				'post_url'       => __( 'Post URL', 'brik-builder' ),
				'post_id'        => __( 'Post ID', 'brik-builder' ),
				'featured_image' => __( 'Featured image URL', 'brik-builder' ),
				'author_name'    => __( 'Author name', 'brik-builder' ),
				'author_url'     => __( 'Author archive URL', 'brik-builder' ),
				'comment_count'  => __( 'Comment count', 'brik-builder' ),
				'archive_title'  => __( 'Archive title', 'brik-builder' ),
				'site_name'      => __( 'Site title', 'brik-builder' ),
				'site_tagline'   => __( 'Site tagline', 'brik-builder' ),
				'site_url'       => __( 'Home URL', 'brik-builder' ),
				'user_name'      => __( 'Current user name', 'brik-builder' ),
				'year'           => __( 'Current year', 'brik-builder' ),
				'date'           => __( 'Current date', 'brik-builder' ),
				'search_query'   => __( 'Search query', 'brik-builder' ),
				'meta:KEY'       => __( 'Custom field', 'brik-builder' ),
			)
		);
	}

	public static function replace( $text, $post_id = 0 ) {
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}
		return preg_replace_callback(
			'/\{([a-z_]+:[A-Za-z0-9_.\-]+(?:\|[a-z_]+)?|[a-z_]+)\}/',
			static function ( $m ) use ( $post_id ) {
				$value = Dynamic::value( $m[1], $post_id );
				return null === $value ? $m[0] : $value;
			},
			$text
		);
	}

	public static function value( $tag, $post_id = 0 ) {
		$post_id = $post_id ? $post_id : get_the_ID();
		if ( is_singular() && ThemeBuilder::is_template( $post_id ) ) {
			$post_id = get_queried_object_id();
		} elseif ( in_the_loop() ) {
			$post_id = get_the_ID();
		}

		if ( 0 === strpos( $tag, 'meta:' ) ) {
			$v = get_post_meta( $post_id, substr( $tag, 5 ), true );
			return is_scalar( $v ) ? esc_html( (string) $v ) : '';
		}

		switch ( $tag ) {
			case 'post_title':
				return esc_html( get_the_title( $post_id ) );
			case 'post_excerpt':
				return esc_html( get_the_excerpt( $post_id ) );
			case 'post_date':
				return esc_html( get_the_date( '', $post_id ) );
			case 'post_modified':
				return esc_html( get_the_modified_date( '', $post_id ) );
			case 'post_url':
				return esc_url( get_permalink( $post_id ) );
			case 'post_id':
				return (string) (int) $post_id;
			case 'featured_image':
				return esc_url( (string) get_the_post_thumbnail_url( $post_id, 'full' ) );
			case 'author_name':
				$post = get_post( $post_id );
				return $post ? esc_html( get_the_author_meta( 'display_name', $post->post_author ) ) : '';
			case 'author_url':
				$post = get_post( $post_id );
				return $post ? esc_url( get_author_posts_url( $post->post_author ) ) : '';
			case 'comment_count':
				return (string) get_comments_number( $post_id );
			case 'archive_title':
				return esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			case 'site_name':
				return esc_html( get_bloginfo( 'name' ) );
			case 'site_tagline':
				return esc_html( get_bloginfo( 'description' ) );
			case 'site_url':
				return esc_url( home_url( '/' ) );
			case 'user_name':
				$user = wp_get_current_user();
				return $user->exists() ? esc_html( $user->display_name ) : '';
			case 'year':
				return wp_date( 'Y' );
			case 'date':
				return esc_html( wp_date( get_option( 'date_format' ) ) );
			case 'search_query':
				return esc_html( get_search_query() );
		}

		return apply_filters( 'brik/dynamic_value', null, $tag, $post_id );
	}
}
