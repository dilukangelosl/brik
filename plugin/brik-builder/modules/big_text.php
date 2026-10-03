<?php
/**
 * Big text: a giant headline with an image or a video showing through the letters,
 * scaling gently as it scrolls past.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'big_text',
	'title'       => __( 'Big Text Mask', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'type',
	'description' => 'Giant headline with an image or video showing through the letters (text mask), scaling as it scrolls. text: headline, use <br> for line breaks. tag: h1|h2|p|div. media: image|video. image: fill for the letters. video: mp4 URL (YouTube/Vimeo cannot be masked). subtitle: small line under it. align: center|left. uppercase: bool. scroll_scale: bool, with scale_from / scale_to (0.5-1.5). outline: bool, thin outline that keeps the shape readable on busy backgrounds. Font size via title_font_size (default fluid, up to 15rem). Static with reduced motion.',
	'fields'      => array_merge(
		array(
			'text'         => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Explore', 'brik-builder' ), 'description' => __( 'Use <br> for a line break.', 'brik-builder' ) ) ),
			'tag'          => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'p' => 'p', 'div' => 'div' ) ) ) ),
			'media'        => Fields::field( 'select', __( 'Fill', 'brik-builder' ), 'content', array( 'default' => 'image', 'options' => Fields::opts( array( 'image' => __( 'Image', 'brik-builder' ), 'video' => __( 'Video', 'brik-builder' ) ) ) ) ),
			'image'        => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1462331940025-496dfbfc7564', 2000, 1100 ), 'alt' => '' ), 'show_if' => array( 'media' => 'image' ) ) ),
			'video'        => Fields::field( 'text', __( 'Video URL (.mp4)', 'brik-builder' ), 'content', array( 'default' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4', 'show_if' => array( 'media' => 'video' ) ) ),
			'subtitle'     => Fields::field( 'text', __( 'Subtitle', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'align'        => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'center' => __( 'Center', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'uppercase'    => Fields::field( 'toggle', __( 'Uppercase', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'scroll_scale' => Fields::field( 'toggle', __( 'Scale on scroll', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => true ) ),
			'scale_from'   => Fields::field( 'range', __( 'Scale from', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 0.85, 'min' => 0.5, 'max' => 1.5, 'step' => 0.01, 'show_if' => array( 'scroll_scale' => '!' ) ) ),
			'scale_to'     => Fields::field( 'range', __( 'Scale to', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 1.08, 'min' => 0.5, 'max' => 1.5, 'step' => 0.01, 'show_if' => array( 'scroll_scale' => '!' ) ) ),
			'outline'      => Fields::field( 'toggle', __( 'Outline letters', 'brik-builder' ), 'text_style', array( 'tab' => 'design', 'group_label' => __( 'Headline', 'brik-builder' ) ) ),
		),
		Fields::typography( 'title', __( 'Headline', 'brik-builder' ), Fields::WRAP . ' .brik-bt-text' ),
		Fields::typography( 'subtitle', __( 'Subtitle', 'brik-builder' ), Fields::WRAP . ' .brik-bt-sub' )
	),
	'render'      => static function ( $a, $ctx ) {
		if ( '' === trim( wp_strip_all_tags( (string) $a['text'] ) ) ) {
			return $ctx->placeholder( __( 'Add a headline', 'brik-builder' ) );
		}
		$ctx->script( 'big-text' );

		$video = 'video' === $a['media'] ? brik_video_info( $a['video'] ) : null;
		$video = $video && 'file' === $video['provider'] && '' !== $video['url'] ? $video['url'] : '';
		$image = brik_image_url( $a['image'], 'full' );

		$lines = '';
		foreach ( preg_split( '/<br\s*\/?>/i', (string) $a['text'] ) as $line ) {
			if ( '' !== trim( wp_strip_all_tags( $line ) ) ) {
				$lines .= '<span class="brik-bt-line">' . brik_inline( trim( $line ) ) . '</span>';
			}
		}

		$tag   = in_array( $a['tag'], array( 'h1', 'h2', 'p', 'div' ), true ) ? $a['tag'] : 'h2';
		$style = ! $video && $image ? ' style="' . esc_attr( 'background-image:url("' . esc_url_raw( $image ) . '")' ) . '"' : '';
		$text  = sprintf(
			'<%1$s class="%2$s"%3$s>%4$s</%1$s>',
			$tag,
			esc_attr(
				brik_cls(
					'brik-bt-text font-heading text-[min(15rem,17vw)] leading-[0.88] font-black tracking-[-0.04em]',
					array(
						'uppercase'         => ! empty( $a['uppercase'] ),
						'brik-bt-text--img' => ! $video && $image,
					)
				)
			),
			$style,
			$lines
		);

		$media = '';
		if ( $video ) {
			$clip  = $ctx->uid( 'clip' );
			$media = sprintf(
				'<svg class="brik-bt-clip" width="0" height="0" aria-hidden="true" focusable="false"><clipPath id="%1$s"></clipPath></svg><video class="brik-bt-video" src="%2$s" muted loop playsinline preload="auto" aria-hidden="true" style="clip-path:url(#%1$s)"></video>',
				esc_attr( $clip ),
				esc_url( $video )
			);
		}

		return sprintf(
			'<div class="%1$s"%2$s><div class="brik-bt-stage relative">%3$s%4$s</div>%5$s</div>',
			esc_attr(
				brik_cls(
					'brik-bt',
					$video ? 'brik-bt--video' : 'brik-bt--image',
					'left' === $a['align'] ? 'text-left' : 'text-center',
					array(
						'brik-bt--scale'   => ! empty( $a['scroll_scale'] ),
						'brik-bt--outline' => ! empty( $a['outline'] ),
					)
				)
			),
			brik_3d_vars(
				array(
					'--brik-bt-from' => (string) brik_3d_num( $a['scale_from'], 0.85, 0.5, 1.5 ),
					'--brik-bt-to'   => (string) brik_3d_num( $a['scale_to'], 1.08, 0.5, 1.5 ),
				)
			),
			$media,
			$text,
			'' !== (string) $a['subtitle'] ? '<p class="brik-bt-sub mt-6 text-base text-muted-foreground md:text-lg"' . $ctx->inline( 'subtitle' ) . '>' . brik_inline( $a['subtitle'] ) . '</p>' : ''
		);
	},
);
