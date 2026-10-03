<?php
/**
 * Sticky scroll: a column of steps beside a pinned visual that changes with the active step.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'sticky_scroll',
	'title'       => __( 'Sticky Scroll', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'scroll-text',
	'description' => 'Scrollytelling: steps (title + text) scroll past a sticky visual that crossfades to the active step, with a progress rail. items: repeater of {eyebrow, title, text, image, icon}; a step without an image shows a gradient card with its icon and title. visual_side: right|left. ratio: visual ratio (4:3|1:1|3:4|16:10). step_height: min height per step (CSS length, e.g. 70vh). numbers: bool, 01/02 counters. progress: bool, rail on the left. On phones each step shows its visual inline.',
	'fields'      => array_merge(
		array(
			'items'       => Fields::field(
				'repeater',
				__( 'Steps', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'default'     => array(
						array(
							'eyebrow' => __( 'Design', 'brik-builder' ),
							'title'   => __( 'Sketch ideas right in the browser', 'brik-builder' ),
							'text'    => __( 'Drag in sections, try a layout, change your mind. Every edit is live, so you see the real page while you work.', 'brik-builder' ),
							'image'   => array( 'url' => brik_sample_image( '1558655146-d09347e92766', 1200, 900 ), 'alt' => __( 'Interface designs on a large monitor', 'brik-builder' ) ),
							'icon'    => 'pen-tool',
						),
						array(
							'eyebrow' => __( 'Collaborate', 'brik-builder' ),
							'title'   => __( 'Work together without the hand-offs', 'brik-builder' ),
							'text'    => __( 'Share a link, leave comments on any element and keep one source of truth for copy, images and layout.', 'brik-builder' ),
							'image'   => array( 'url' => brik_sample_image( '1519389950473-47ba0277781c', 1200, 900 ), 'alt' => __( 'A team working on laptops around a table', 'brik-builder' ) ),
							'icon'    => 'users',
						),
						array(
							'eyebrow' => __( 'Build', 'brik-builder' ),
							'title'   => __( 'Clean markup you would write yourself', 'brik-builder' ),
							'text'    => __( 'Semantic HTML, utility classes and no inline clutter. Fast pages that stay easy to maintain.', 'brik-builder' ),
							'image'   => array( 'url' => brik_sample_image( '1555066931-4365d14bab8c', 1200, 900 ), 'alt' => __( 'Source code on a laptop screen', 'brik-builder' ) ),
							'icon'    => 'code',
						),
						array(
							'eyebrow' => __( 'Grow', 'brik-builder' ),
							'title'   => __( 'Measure what moves the needle', 'brik-builder' ),
							'text'    => __( 'Track sign-ups and conversions per section, then double down on the parts of the page that work.', 'brik-builder' ),
							'image'   => array( 'url' => brik_sample_image( '1460925895917-afdab827c52f', 1200, 900 ), 'alt' => __( 'Charts on an analytics dashboard', 'brik-builder' ) ),
							'icon'    => 'trending-up',
						),
					),
					'fields'      => array(
						'eyebrow' => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content' ),
						'title'   => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content' ),
						'text'    => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content' ),
						'image'   => Fields::field( 'image', __( 'Visual', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty for a gradient card.', 'brik-builder' ) ) ),
						'icon'    => Fields::field( 'icon', __( 'Icon (gradient card)', 'brik-builder' ), 'content' ),
					),
				)
			),
			'visual_side' => Fields::field( 'select', __( 'Visual side', 'brik-builder' ), 'content', array( 'default' => 'right', 'options' => Fields::opts( array( 'right' => __( 'Right', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'ratio'       => Fields::field( 'select', __( 'Visual ratio', 'brik-builder' ), 'content', array( 'default' => '4:3', 'options' => Fields::opts( array( '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4', '16:10' => '16:10' ) ) ) ),
			'step_height' => Fields::field( 'unit', __( 'Step height', 'brik-builder' ), 'content', array( 'default' => '70vh', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-ss', '--brik-ss-step' ) ) ),
			'numbers'     => Fields::field( 'toggle', __( 'Step numbers', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'progress'    => Fields::field( 'toggle', __( 'Progress rail', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'rounded'     => Fields::field( 'select', __( 'Visual corners', 'brik-builder' ), 'visual_style', array( 'tab' => 'design', 'group_label' => __( 'Visual', 'brik-builder' ), 'default' => '2xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'rail_color'  => Fields::field( 'color', __( 'Progress color', 'brik-builder' ), 'visual_style', array( 'tab' => 'design', 'group_label' => __( 'Visual', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-ss', '--brik-ss-accent' ) ) ),
		),
		Fields::typography( 'eyebrow', __( 'Eyebrow', 'brik-builder' ), Fields::WRAP . ' .brik-ss-eyebrow' ),
		Fields::typography( 'title', __( 'Step title', 'brik-builder' ), Fields::WRAP . ' .brik-ss-title' ),
		Fields::typography( 'text', __( 'Step text', 'brik-builder' ), Fields::WRAP . ' .brik-ss-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_3d_rows( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add steps', 'brik-builder' ) );
		}
		$ctx->script( 'sticky-scroll' );

		$ratio  = brik_3d_ratio_class( $a['ratio'] );
		$radius = brik_radius_class( $a['rounded'] );
		$grads  = array( 'brik-ss-grad-0', 'brik-ss-grad-1', 'brik-ss-grad-2', 'brik-ss-grad-3' );
		$steps  = '';
		$layers = '';

		foreach ( $items as $i => $item ) {
			$item  = array_merge( array( 'eyebrow' => '', 'title' => '', 'text' => '', 'image' => '', 'icon' => '' ), $item );
			$img   = brik_image( $item['image'], 'large', array( 'class' => 'absolute inset-0 h-full w-full object-cover', 'loading' => 0 === $i ? 'eager' : 'lazy' ) );
			$panel = $img ? $img : sprintf(
				'<div class="%1$s absolute inset-0 flex flex-col justify-end gap-4 p-8 text-white">%2$s<p class="text-2xl font-semibold tracking-tight text-balance">%3$s</p></div>',
				esc_attr( $grads[ $i % 4 ] ),
				$item['icon'] ? '<span class="inline-flex size-14 items-center justify-center rounded-2xl bg-white/15 backdrop-blur">' . brik_icon( $item['icon'], 'size-7' ) . '</span>' : '',
				brik_inline( $item['title'] )
			);
			$active = 0 === $i ? ' is-active' : '';

			$steps .= sprintf(
				'<li class="brik-ss-step%1$s" data-step="%2$d"><div class="brik-ss-step-body">%3$s%4$s%5$s%6$s<div class="%7$s md:hidden" aria-hidden="true">%8$s</div></div></li>',
				$active,
				$i,
				! empty( $a['numbers'] ) ? '<span class="brik-ss-num mb-4 inline-flex size-9 items-center justify-center rounded-full border text-xs font-semibold tabular-nums">' . esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ) . '</span>' : '',
				'' !== (string) $item['eyebrow'] ? '<p class="brik-ss-eyebrow mb-2 text-sm font-semibold tracking-wider text-muted-foreground uppercase">' . brik_inline( $item['eyebrow'] ) . '</p>' : '',
				'' !== (string) $item['title'] ? '<h3 class="brik-ss-title font-heading text-2xl font-semibold tracking-tight text-balance md:text-4xl">' . brik_inline( $item['title'] ) . '</h3>' : '',
				'' !== (string) $item['text'] ? '<p class="brik-ss-text mt-4 max-w-lg text-base text-muted-foreground md:text-lg">' . esc_html( $item['text'] ) . '</p>' : '',
				esc_attr( brik_cls( 'brik-ss-inline relative mt-6 overflow-hidden bg-muted', $ratio, $radius ) ),
				$panel
			);
			$layers .= '<div class="brik-ss-layer' . $active . '" data-step="' . (int) $i . '">' . $panel . '</div>';
		}

		return sprintf(
			'<div class="%1$s"><div class="brik-ss-track relative">%2$s<ol class="brik-ss-steps">%3$s</ol></div><div class="brik-ss-aside max-md:hidden"><div class="%4$s" aria-hidden="true">%5$s</div></div></div>',
			esc_attr( brik_cls( 'brik-ss grid gap-x-16 md:grid-cols-2', 'left' === $a['visual_side'] ? 'brik-ss--left' : '', array( 'brik-ss--rail' => ! empty( $a['progress'] ) ) ) ),
			! empty( $a['progress'] ) ? '<span class="brik-ss-rail" aria-hidden="true"><span class="brik-ss-rail-fill"></span></span>' : '',
			$steps,
			esc_attr( brik_cls( 'brik-ss-visual relative overflow-hidden bg-muted shadow-2xl', $ratio, $radius ) ),
			$layers
		);
	},
);
