<?php
/**
 * Post content for body templates.
 *
 * @package Brik
 */

use Brik\Data;
use Brik\Fields;
use Brik\Frontend;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'post_content',
	'title'       => __( 'Post content', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'file-text',
	'description' => 'Outputs the viewed post\'s content inside a body template (Brik-built posts render their own layout). prose: bool, applies article typography to classic/block content. Shows sample text while the template is edited.',
	'fields'      => array_merge(
		array(
			'prose' => Fields::field( 'toggle', __( 'Article typography', 'brik-builder' ), 'content', array( 'default' => true, 'description' => __( 'Style headings, lists, quotes and images in regular content. Brik-built posts keep their own design.', 'brik-builder' ) ) ),
		),
		Fields::typography( 'body', __( 'Body text', 'brik-builder' ), Fields::WRAP . ' .brik-post-content' ),
		Fields::typography( 'headings', __( 'Headings', 'brik-builder' ), Fields::WRAP . ' .brik-post-content :is(h1,h2,h3,h4,h5,h6)' ),
		Fields::typography( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-post-content a' )
	),
	'render'      => static function ( $a, $ctx ) {
		$class = brik_cls( 'brik-post-content', array( 'brik-prose' => ! empty( $a['prose'] ) ) );

		if ( brik_site_is_preview( $ctx ) ) {
			return '<div class="' . esc_attr( $class ) . '">' . brik_site_sample_content() . '</div>';
		}

		$post = brik_site_post( $ctx );
		// A post can't contain itself.
		if ( ! $post || $post->ID === (int) $ctx->post_id ) {
			return $ctx->placeholder( __( 'Post content is shown when this layout is used as a body template', 'brik-builder' ) );
		}

		$key = 'post_content:' . $post->ID;
		if ( ! $ctx->renderer->enter( $key ) ) {
			return '';
		}

		if ( post_password_required( $post ) ) {
			$html = '<div class="' . esc_attr( $class ) . '">' . get_the_password_form( $post ) . '</div>';
		} elseif ( Data::enabled( $post->ID ) ) {
			$result = Frontend::render( $post->ID );
			$html   = $result['html'];
		} else {
			$html = brik_site_with_post(
				$post,
				static function ( $p ) {
					return apply_filters( 'the_content', get_the_content( null, false, $p ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- core filter.
				}
			);
			$html = '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
		}

		$ctx->renderer->leave( $key );
		return $html;
	},
);
