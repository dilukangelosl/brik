<?php
/**
 * Embed: oEmbed providers (YouTube, Vimeo, Spotify, X, CodePen…) or a raw iframe URL.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'embed',
	'title'       => __( 'Embed', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'code-xml',
	'description' => 'Embeds third-party content. mode: oembed (url of any WordPress oEmbed provider: YouTube, Vimeo, Spotify, SoundCloud, X, TikTok…) | iframe (url loaded in an iframe). ratio: auto|16:9|4:3|1:1|21:9|9:16… (auto keeps the provider size). embed_height: iframe height when ratio is auto. title: accessible iframe title.',
	'fields'      => array(
		'mode'         => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'oembed', 'options' => Fields::opts( array( 'oembed' => __( 'Auto (oEmbed)', 'brik-builder' ), 'iframe' => __( 'Iframe URL', 'brik-builder' ) ) ) ) ),
		'url'          => Fields::field( 'text', __( 'URL', 'brik-builder' ), 'content', array( 'default' => 'https://vimeo.com/22439234', 'placeholder' => 'https://' ) ),
		'title'        => Fields::field( 'text', __( 'Accessible title', 'brik-builder' ), 'content', array( 'default' => __( 'Embedded content', 'brik-builder' ) ) ),
		'ratio'        => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => '16:9', 'options' => Fields::opts( brik_aspect_options() ) ) ),
		'embed_height' => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'content', array( 'default' => '450px', 'responsive' => true, 'show_if' => array( 'ratio' => 'auto', 'mode' => 'iframe' ), 'css' => array( Fields::WRAP . ' .brik-embed-iframe', 'height' ) ) ),
		'rounded'      => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'embed_style', array( 'tab' => 'design', 'group_label' => __( 'Frame', 'brik-builder' ), 'default' => 'lg', 'options' => Fields::opts( brik_radius_options() ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$url = trim( (string) $a['url'] );
		if ( '' === $url ) {
			return $ctx->placeholder( __( 'Paste a URL to embed', 'brik-builder' ) );
		}
		$ratio = brik_aspect_class( $a['ratio'] );
		$title = '' !== $a['title'] ? wp_strip_all_tags( $a['title'] ) : __( 'Embedded content', 'brik-builder' );
		$frame = brik_cls( 'brik-embed-frame relative w-full overflow-hidden', $ratio, brik_radius_class( $a['rounded'] ) );

		if ( 'iframe' === $a['mode'] ) {
			$src = brik_safe_iframe_src( $url );
			if ( ! $src ) {
				return $ctx->placeholder( __( 'Only http(s) URLs can be embedded', 'brik-builder' ) );
			}
			return '<div class="' . esc_attr( $frame ) . '"><iframe class="brik-embed-iframe block w-full border-0' . ( $ratio ? ' absolute inset-0 h-full' : '' ) . '" src="' . esc_url( $src ) . '" title="' . esc_attr( $title ) . '" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
		}

		$html = brik_oembed_html( $url );
		if ( '' === $html ) {
			if ( $ctx->canvas ) {
				return $ctx->placeholder( __( 'This URL could not be embedded', 'brik-builder' ) );
			}
			return '<p class="brik-embed-fallback"><a class="text-primary underline underline-offset-4" href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
		}

		// Ratio only makes sense for iframe players; rich embeds (tweets, posts) keep their own height.
		$is_iframe = (bool) preg_match( '/^\s*<iframe/i', $html );
		if ( $is_iframe && ! preg_match( '/\stitle=/i', $html ) ) {
			$html = preg_replace( '/<iframe/i', '<iframe title="' . esc_attr( $title ) . '"', $html, 1 );
		}
		if ( ! $is_iframe || ! $ratio ) {
			return '<div class="brik-embed-frame brik-embed--rich [&_iframe]:max-w-full">' . $html . '</div>';
		}
		return '<div class="' . esc_attr( $frame ) . ' [&_iframe]:absolute [&_iframe]:inset-0 [&_iframe]:h-full [&_iframe]:w-full">' . $html . '</div>';
	},
);
