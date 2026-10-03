<?php
/**
 * Scroll zoom image: a rounded card that grows to fill the screen while the section is pinned,
 * then reveals overlay text.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'scroll_zoom_image',
	'title'       => __( 'Scroll Zoom Image', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'maximize',
	'description' => 'Pinned scroll scene: an image (or video) starts as a small rounded card and scales up to fill the screen as you scroll, then overlay text fades in. intro: heading shown above the small card (fades out). image; video: mp4/YouTube/Vimeo URL (wins over image). eyebrow, title (inline HTML), text, button_text, link: overlay content. start_scale: 0.3-0.9. start_radius: corner radius of the small card (CSS length). scroll_length: extra scroll distance in vh (50-300). overlay: color over the media once full. content_position: center|bottom. Use in a full-width section with no padding. Reduced motion and the builder show the full image with its text.',
	'fields'      => array_merge(
		array(
			'intro'            => Fields::field( 'text', __( 'Intro heading', 'brik-builder' ), 'content', array( 'default' => __( 'A closer look', 'brik-builder' ), 'inline' => true ) ),
			'image'            => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1493246507139-91e8fad9978e', 2400, 1500 ), 'alt' => __( 'Turquoise lake below snowy peaks', 'brik-builder' ) ) ) ),
			'video'            => Fields::field( 'text', __( 'Video URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://…/clip.mp4' ) ),
			'eyebrow'          => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content', array( 'default' => __( 'Banff, Canada', 'brik-builder' ), 'inline' => true ) ),
			'title'            => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Every detail, up close', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'        => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'p' ) ) ) ),
			'text'             => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Scroll into the scene. Wide open spaces, shown the way they deserve to be seen.', 'brik-builder' ) ) ),
			'button_text'      => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content' ),
			'link'             => Fields::field( 'link', __( 'Button link', 'brik-builder' ), 'content', array( 'show_if' => array( 'button_text' => '!' ) ) ),
			'content_position' => Fields::field( 'select', __( 'Text position', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'center' => __( 'Center', 'brik-builder' ), 'bottom' => __( 'Bottom left', 'brik-builder' ) ) ) ) ),
			'start_scale'      => Fields::field( 'range', __( 'Start size', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 0.5, 'min' => 0.3, 'max' => 0.9, 'step' => 0.01 ) ),
			'start_radius'     => Fields::field( 'unit', __( 'Start corner radius', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => '28px' ) ),
			'scroll_length'    => Fields::field( 'range', __( 'Scroll length', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 150, 'min' => 50, 'max' => 300, 'step' => 10, 'unit' => 'vh' ) ),
			'overlay'          => Fields::field( 'color', __( 'Overlay', 'brik-builder' ), 'media_style', array( 'tab' => 'design', 'group_label' => __( 'Media', 'brik-builder' ), 'default' => 'rgb(0 0 0 / 0.4)', 'css' => array( Fields::WRAP . ' .brik-szi-shade', 'background-color' ) ) ),
		),
		Fields::typography( 'intro', __( 'Intro heading', 'brik-builder' ), Fields::WRAP . ' .brik-szi-intro' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-szi-title' ),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-szi-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$media = brik_3d_media( $a['image'], $a['video'], array( 'class' => 'absolute inset-0 h-full w-full object-cover', 'size' => 'full' ) );
		if ( '' === $media ) {
			return $ctx->placeholder( __( 'Choose an image or video', 'brik-builder' ) );
		}
		$ctx->script( 'scroll-zoom' );

		$tag     = in_array( $a['title_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $a['title_tag'] : 'h2';
		$content = '';
		if ( '' !== (string) $a['eyebrow'] ) {
			$content .= '<p class="brik-szi-eyebrow mb-3 text-sm font-semibold tracking-[0.2em] uppercase opacity-80"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== (string) $a['title'] ) {
			$content .= sprintf( '<%1$s class="brik-szi-title font-heading text-4xl leading-[1.05] font-bold tracking-tight text-balance md:text-7xl"%3$s>%2$s</%1$s>', $tag, brik_inline( $a['title'] ), $ctx->inline( 'title' ) );
		}
		if ( '' !== (string) $a['text'] ) {
			$content .= '<p class="brik-szi-text mt-5 max-w-xl text-base opacity-90 md:text-xl">' . esc_html( $a['text'] ) . '</p>';
		}
		if ( '' !== (string) $a['button_text'] && ! empty( $a['link']['url'] ) ) {
			$content .= '<div class="mt-8"><a' . brik_link_attrs( $a['link'], array( 'class' => 'inline-flex h-11 items-center justify-center gap-2 rounded-full bg-white px-7 text-sm font-medium whitespace-nowrap text-neutral-900 shadow-lg outline-none hover:bg-white/90 focus-visible:ring-[3px] focus-visible:ring-white/50' ) ) . '>' . brik_inline( $a['button_text'] ) . '</a></div>';
		}
		$bottom = 'bottom' === $a['content_position'];

		$radius = (string) $a['start_radius'];
		$radius = preg_match( '/^\d+(\.\d+)?$/', $radius ) ? $radius . 'px' : ( preg_match( '/^[\d.]+(px|rem|em|%)$/', $radius ) ? $radius : '28px' );

		return sprintf(
			'<div class="brik-szi"%1$s><div class="brik-szi-stage">%2$s<div class="brik-szi-card">%3$s<div class="brik-szi-shade" aria-hidden="true"></div>%4$s</div></div></div>',
			brik_3d_vars(
				array(
					'--brik-szi-scale'  => (string) brik_3d_num( $a['start_scale'], 0.5, 0.3, 0.9 ),
					'--brik-szi-radius' => $radius,
					'--brik-szi-length' => (int) brik_3d_num( $a['scroll_length'], 150, 50, 300 ) . 'vh',
				)
			),
			'' !== (string) $a['intro'] ? '<p class="brik-szi-intro font-heading text-3xl font-bold tracking-tight text-balance md:text-5xl"' . $ctx->inline( 'intro' ) . '>' . brik_inline( $a['intro'] ) . '</p>' : '',
			$media,
			'' !== $content ? '<div class="' . esc_attr( brik_cls( 'brik-szi-content absolute inset-0 flex flex-col p-8 text-white md:p-16', $bottom ? 'items-start justify-end text-left' : 'items-center justify-center text-center' ) ) . '">' . $content . '</div>' : ''
		);
	},
);
