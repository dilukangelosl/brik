<?php
/**
 * Container scroll: a framed screen tilted back in 3D that settles flat as it scrolls into view.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'container_scroll',
	'title'       => __( 'Container Scroll', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'panels-top-left',
	'description' => 'Scroll scene: a large device frame starts tilted back in 3D and flattens as it scrolls into view while the title above lifts. eyebrow, title (inline HTML), text, title_tag. image or video (mp4/YouTube/Vimeo URL, wins over image). frame: tablet|browser|laptop|plain. frame_theme: dark|light. url: browser address bar text. ratio: 16:10|16:9|4:3|3:2|21:9. tilt: start angle in degrees (0-45). scale_from: start scale (0.8-1.1, 1 on phones). card_width: CSS length. Flat and static with reduced motion and in the builder.',
	'fields'      => array_merge(
		array(
			'eyebrow'     => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content', array( 'default' => __( 'Introducing the new dashboard', 'brik-builder' ), 'inline' => true ) ),
			'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Everything you need,<br>in one beautiful view', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'   => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'p' ) ) ) ),
			'text'        => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Plan, track and ship from a single workspace that keeps your whole team in sync.', 'brik-builder' ) ) ),
			'image'       => Fields::field( 'image', __( 'Screen image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1551288049-bebda4e38f71', 1800, 1125 ), 'alt' => __( 'Analytics dashboard with charts', 'brik-builder' ) ) ) ),
			'video'       => Fields::field( 'text', __( 'Screen video URL', 'brik-builder' ), 'content', array( 'placeholder' => 'https://…/clip.mp4', 'description' => __( 'Optional. An .mp4 file, YouTube or Vimeo link plays muted on a loop instead of the image.', 'brik-builder' ) ) ),
			'frame'       => Fields::field( 'select', __( 'Frame', 'brik-builder' ), 'content', array( 'default' => 'tablet', 'options' => Fields::opts( array( 'tablet' => __( 'Tablet', 'brik-builder' ), 'browser' => __( 'Browser window', 'brik-builder' ), 'laptop' => __( 'Laptop', 'brik-builder' ), 'plain' => __( 'Plain card', 'brik-builder' ) ) ) ) ),
			'frame_theme' => Fields::field( 'select', __( 'Frame color', 'brik-builder' ), 'content', array( 'default' => 'dark', 'options' => Fields::opts( array( 'dark' => __( 'Dark', 'brik-builder' ), 'light' => __( 'Light', 'brik-builder' ) ) ) ) ),
			'url'         => Fields::field( 'text', __( 'Address bar text', 'brik-builder' ), 'content', array( 'default' => 'app.example.com', 'show_if' => array( 'frame' => 'browser' ) ) ),
			'ratio'       => Fields::field( 'select', __( 'Screen ratio', 'brik-builder' ), 'content', array( 'default' => '16:10', 'options' => Fields::opts( array_slice( brik_3d_ratio_options(), 0, 5, true ) ) ) ),
			'tilt'        => Fields::field( 'range', __( 'Start tilt', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 22, 'min' => 0, 'max' => 45, 'unit' => 'deg' ) ),
			'scale_from'  => Fields::field( 'range', __( 'Start scale', 'brik-builder' ), 'scroll', array( 'group_label' => __( 'Scroll effect', 'brik-builder' ), 'default' => 1.02, 'min' => 0.8, 'max' => 1.1, 'step' => 0.01 ) ),
			'card_width'  => Fields::field( 'unit', __( 'Card width', 'brik-builder' ), 'card_style', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'default' => '1100px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-cs-card', 'max-width' ) ) ),
		),
		Fields::typography( 'eyebrow', __( 'Eyebrow', 'brik-builder' ), Fields::WRAP . ' .brik-cs-eyebrow' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-cs-title' ),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-cs-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'container-scroll' );

		$media = brik_3d_media( $a['image'], $a['video'], array( 'class' => 'absolute inset-0 h-full w-full object-cover', 'size' => 'full' ) );
		if ( '' === $media ) {
			$media = $ctx->canvas ? '<div class="absolute inset-0 grid place-items-center bg-muted text-sm text-muted-foreground">' . esc_html__( 'Choose an image or video', 'brik-builder' ) . '</div>' : '';
		}

		$tag  = in_array( $a['title_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $a['title_tag'] : 'h2';
		$head = '';
		if ( '' !== (string) $a['eyebrow'] ) {
			$head .= '<p class="brik-cs-eyebrow mb-4 text-sm font-semibold tracking-wider text-muted-foreground uppercase"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== (string) $a['title'] ) {
			$head .= sprintf( '<%1$s class="brik-cs-title font-heading text-4xl leading-[1.05] font-bold tracking-tight text-balance md:text-6xl"%3$s>%2$s</%1$s>', $tag, brik_inline( $a['title'] ), $ctx->inline( 'title' ) );
		}
		if ( '' !== (string) $a['text'] ) {
			$head .= '<p class="brik-cs-text mx-auto mt-5 max-w-2xl text-base text-muted-foreground md:text-lg">' . esc_html( $a['text'] ) . '</p>';
		}

		$card = brik_3d_device(
			$a['frame'],
			$media,
			array(
				'theme' => $a['frame_theme'],
				'ratio' => $a['ratio'],
				'url'   => $a['url'],
				'class' => 'brik-cs-card mx-auto w-full max-w-[1100px]',
			)
		);

		return sprintf(
			'<div class="brik-cs"%1$s>%2$s<div class="brik-cs-stage">%3$s</div></div>',
			brik_3d_vars(
				array(
					'--brik-cs-tilt'  => (string) brik_3d_num( $a['tilt'], 22, 0, 45 ),
					'--brik-cs-scale' => (string) brik_3d_num( $a['scale_from'], 1.02, 0.8, 1.1 ),
				)
			),
			'' !== $head ? '<div class="brik-cs-head mx-auto max-w-4xl text-center">' . $head . '</div>' : '',
			$card
		);
	},
);
