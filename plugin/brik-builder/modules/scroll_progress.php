<?php
/**
 * Scroll progress: a thin gradient bar fixed to the viewport edge that fills as you scroll.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'scroll_progress',
	'title'       => __( 'Scroll Progress', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'gauge',
	'description' => 'Thin progress bar fixed to the top or bottom of the viewport. target: page (whole page) | content (reading progress through an element; selector, default the post content, e.g. ".brik-post-content" or "article"). placement: top|bottom. thickness: px. color_1, color_2, color_3: gradient. track: bool, faint background track. glow: bool. Place it anywhere on the page; it takes no space.',
	'fields'      => array(
		'target'   => Fields::field( 'select', __( 'Measure', 'brik-builder' ), 'content', array( 'default' => 'page', 'options' => Fields::opts( array( 'page' => __( 'Whole page', 'brik-builder' ), 'content' => __( 'Reading progress through an element', 'brik-builder' ) ) ) ) ),
		'selector' => Fields::field( 'text', __( 'Element selector', 'brik-builder' ), 'content', array( 'placeholder' => '.brik-post_content, article, main', 'show_if' => array( 'target' => 'content' ) ) ),
		'placement' => Fields::field( 'select', __( 'Position', 'brik-builder' ), 'content', array( 'default' => 'top', 'options' => Fields::opts( array( 'top' => __( 'Top of the screen', 'brik-builder' ), 'bottom' => __( 'Bottom of the screen', 'brik-builder' ) ) ) ) ),
		'thickness' => Fields::field( 'range', __( 'Height (px)', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 12, 'step' => 1 ) ),
		'track'    => Fields::field( 'toggle', __( 'Show track', 'brik-builder' ), 'content' ),
		'glow'     => Fields::field( 'toggle', __( 'Glow', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'color_1'  => Fields::field( 'color', __( 'Color 1', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Bar colors', 'brik-builder' ), 'placeholder' => '#6366f1' ) ),
		'color_2'  => Fields::field( 'color', __( 'Color 2', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Bar colors', 'brik-builder' ), 'placeholder' => '#a855f7' ) ),
		'color_3'  => Fields::field( 'color', __( 'Color 3', 'brik-builder' ), 'colors', array( 'tab' => 'design', 'group_label' => __( 'Bar colors', 'brik-builder' ), 'placeholder' => '#ec4899' ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'scroll-progress' );
		$selector = trim( wp_strip_all_tags( (string) $a['selector'] ) );
		$style    = brik_fx_vars(
			array(
				'--brik-sp-h'  => (int) brik_fx_num( $a['thickness'], 3, 1, 12 ) . 'px',
				'--brik-sp-c1' => $a['color_1'],
				'--brik-sp-c2' => $a['color_2'],
				'--brik-sp-c3' => $a['color_3'],
			)
		);
		$class    = brik_cls(
			'brik-sp',
			'bottom' === $a['placement'] ? 'brik-sp--bottom' : 'brik-sp--top',
			array(
				'brik-sp--track' => ! empty( $a['track'] ),
				'brik-sp--glow'  => ! empty( $a['glow'] ),
			)
		);
		$attrs    = array(
			'class'         => $class,
			'style'         => $style,
			'data-target'   => 'content' === $a['target'] ? 'content' : 'page',
			'data-selector' => 'content' === $a['target'] && '' !== $selector ? $selector : null,
			'aria-hidden'   => 'true',
		);
		$bar = '<div' . brik_attrs( $attrs ) . '><div class="brik-sp-bar"></div></div>';
		if ( $ctx->canvas ) {
			// The real bar is fixed to the viewport; give the builder something to select.
			$bar .= '<div class="brik-sp-preview flex items-center gap-3 rounded-md border border-dashed px-3 py-2 text-xs text-muted-foreground"><span class="brik-sp-sample" style="' . esc_attr( $style ) . '"></span>' . esc_html__( 'Scroll progress bar', 'brik-builder' ) . '</div>';
		}
		return $bar;
	},
);
