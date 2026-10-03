<?php
/**
 * Circle counter: SVG ring filled to a percentage.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'circle_counter',
	'title'       => __( 'Circle counter', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'circle-gauge',
	'description' => 'Ring progress that fills to "percent" (0-100) when scrolled into view. title (label under the ring), description, show_percent (bool), size (px, default 160), thickness (px, default 10), color (ring, default primary), track_color (default muted), linecap: round|butt, duration (ms).',
	'fields'      => array_merge(
		array(
			'percent'      => Fields::field( 'range', __( 'Percent', 'brik-builder' ), 'content', array( 'default' => 87, 'min' => 0, 'max' => 100, 'unit' => '%' ) ),
			'title'        => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Customer satisfaction', 'brik-builder' ), 'inline' => true ) ),
			'description'  => Fields::field( 'text', __( 'Description', 'brik-builder' ), 'content' ),
			'show_percent' => Fields::field( 'toggle', __( 'Show percentage', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'duration'     => Fields::field( 'range', __( 'Duration (ms)', 'brik-builder' ), 'content', array( 'default' => 1600, 'min' => 200, 'max' => 6000, 'step' => 100 ) ),
			'size'         => Fields::field( 'range', __( 'Size', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'default' => 160, 'min' => 60, 'max' => 400, 'unit' => 'px' ) ),
			'thickness'    => Fields::field( 'range', __( 'Thickness', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'default' => 10, 'min' => 1, 'max' => 60, 'unit' => 'px' ) ),
			'color'        => Fields::field( 'color', __( 'Ring color', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'hover' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-ring-bar', 'prop' => 'stroke', 'hover_selector' => Fields::WRAP . ':hover .brik-ring-bar' ) ) ),
			'track_color'  => Fields::field( 'color', __( 'Track color', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-ring-track', 'stroke' ) ) ),
			'fill_color'   => Fields::field( 'color', __( 'Inner fill', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-ring-track', 'fill' ) ) ),
			'linecap'      => Fields::field( 'select', __( 'Line ends', 'brik-builder' ), 'ring', array( 'tab' => 'design', 'group_label' => __( 'Ring', 'brik-builder' ), 'default' => 'round', 'options' => Fields::opts( array( 'round' => __( 'Rounded', 'brik-builder' ), 'butt' => __( 'Flat', 'brik-builder' ) ) ) ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::typography( 'number', __( 'Percentage', 'brik-builder' ), Fields::WRAP . ' .brik-ring-number' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-counter-title' ),
		Fields::typography( 'description', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-counter-description' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$percent   = max( 0, min( 100, (float) $a['percent'] ) );
		$size      = max( 40, min( 600, (int) $a['size'] ? (int) $a['size'] : 160 ) );
		$thickness = max( 1, min( $size / 2, (float) $a['thickness'] ? (float) $a['thickness'] : 10 ) );
		$stroke    = round( $thickness * 100 / $size, 3 );
		$radius    = round( 50 - $stroke / 2, 3 );
		$cap       = 'butt' === $a['linecap'] ? 'butt' : 'round';
		$label     = ( floor( $percent ) == $percent ? (int) $percent : round( $percent, 1 ) ); // phpcs:ignore Universal.Operators.StrictComparisons

		$ctx->css( Fields::WRAP . ' .brik-ring', 'width:' . $size . 'px' );

		// pathLength=100 lets the dash offset be the remaining percentage at any size.
		$svg = '<svg class="brik-ring-svg size-full -rotate-90" viewBox="0 0 100 100" aria-hidden="true">'
			. '<circle class="brik-ring-track stroke-muted" cx="50" cy="50" r="' . $radius . '" fill="none" stroke-width="' . $stroke . '"/>'
			. '<circle class="brik-ring-bar stroke-primary" cx="50" cy="50" r="' . $radius . '" fill="none" stroke-width="' . $stroke . '" stroke-linecap="' . $cap . '" pathLength="100" stroke-dasharray="100" stroke-dashoffset="' . ( 100 - $percent ) . '"' . ( 0.0 === $percent ? ' opacity="0"' : '' ) . '/>'
			. '</svg>';

		$number = brik_form_bool( $a['show_percent'] )
			? '<span class="brik-ring-number absolute inset-0 grid place-items-center font-heading text-3xl font-bold tracking-tight tabular-nums"><span><span class="brik-counter-value" data-start="0" data-end="' . esc_attr( $percent ) . '" data-decimals="' . ( floor( $percent ) == $percent ? 0 : 1 ) . '" data-separator="">' . esc_html( $label ) . '</span><span class="brik-ring-unit text-[0.6em] font-semibold text-muted-foreground">%</span></span></span>' // phpcs:ignore Universal.Operators.StrictComparisons
			: '';

		$title = '' !== trim( (string) $a['title'] ) ? '<div class="brik-counter-title mt-4 text-base font-medium"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</div>' : '';
		$desc  = '' !== trim( (string) $a['description'] ) ? '<p class="brik-counter-description mt-1 text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>' : '';

		return '<div class="brik-circle" data-brik-circle data-percent="' . esc_attr( $percent ) . '" data-duration="' . max( 0, (int) $a['duration'] ) . '">'
			. '<div class="brik-ring relative inline-block aspect-square max-w-full align-top" role="img" aria-label="' . esc_attr( $label . '%' ) . '">' . $svg . $number . '</div>'
			. $title . $desc . '</div>';
	},
);
