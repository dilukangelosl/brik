<?php
/**
 * Post excerpt.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'post_excerpt',
	'title'       => __( 'Post excerpt', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'text-align-start',
	'description' => 'Excerpt of the current post: the manual excerpt, or the start of the content. length: words (0 = full manual excerpt). style: lead|body|muted. read_more: text for a link to the post ("" hides it).',
	'fields'      => array_merge(
		array(
			'length'    => Fields::field( 'number', __( 'Length (words)', 'brik-builder' ), 'content', array( 'default' => 40, 'min' => 0, 'max' => 200 ) ),
			'style'     => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'lead', 'options' => Fields::opts( array( 'lead' => __( 'Lead', 'brik-builder' ), 'body' => __( 'Body', 'brik-builder' ), 'muted' => __( 'Muted', 'brik-builder' ) ) ) ) ),
			'read_more' => Fields::field( 'text', __( 'Read more text', 'brik-builder' ), 'content' ),
			'align'     => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::typography( 'excerpt', __( 'Excerpt', 'brik-builder' ), Fields::WRAP . ' .brik-post-excerpt' )
	),
	'render'      => static function ( $a, $ctx ) {
		$post = brik_site_post( $ctx );
		if ( ! $post ) {
			return $ctx->placeholder( __( 'The post excerpt appears here', 'brik-builder' ) );
		}
		$text = brik_site_excerpt( $post, (int) $a['length'] );
		if ( '' === $text ) {
			return $ctx->placeholder( __( 'This post has no excerpt', 'brik-builder' ) );
		}
		$styles = array(
			'lead'  => 'text-xl text-muted-foreground leading-relaxed',
			'body'  => 'text-base leading-7',
			'muted' => 'text-sm text-muted-foreground',
		);
		$class  = isset( $styles[ $a['style'] ] ) ? $styles[ $a['style'] ] : $styles['lead'];
		$more   = '';
		if ( '' !== trim( (string) $a['read_more'] ) ) {
			$more = ' <a class="brik-post-more inline-flex items-center gap-1 font-medium text-primary underline-offset-4 hover:underline" href="' . esc_url( get_permalink( $post ) ) . '"><span' . $ctx->inline( 'read_more' ) . '>' . brik_inline( $a['read_more'] ) . '</span></a>';
		}
		return '<p class="brik-post-excerpt ' . esc_attr( $class ) . '">' . esc_html( $text ) . $more . '</p>';
	},
);
