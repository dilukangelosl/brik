<?php
/**
 * Number counter. The final value is in the markup; the script counts up to it when in view.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'number_counter',
	'title'       => __( 'Number counter', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'hash',
	'description' => 'Animated number that counts up when scrolled into view. start, end (numbers), prefix, suffix (e.g. "+", "%", "k"), decimals (0-4), duration (ms), separator: ","|"."|" "|"" (thousands), title (label under the number), description, align: left|center|right, size: md|lg|xl.',
	'fields'      => array_merge(
		array(
			'end'         => Fields::field( 'number', __( 'Number', 'brik-builder' ), 'content', array( 'default' => 12500 ) ),
			'start'       => Fields::field( 'number', __( 'Start from', 'brik-builder' ), 'content', array( 'default' => 0 ) ),
			'prefix'      => Fields::field( 'text', __( 'Prefix', 'brik-builder' ), 'content', array( 'placeholder' => '$' ) ),
			'suffix'      => Fields::field( 'text', __( 'Suffix', 'brik-builder' ), 'content', array( 'default' => '+' ) ),
			'decimals'    => Fields::field( 'number', __( 'Decimals', 'brik-builder' ), 'content', array( 'default' => 0, 'min' => 0, 'max' => 4 ) ),
			'separator'   => Fields::field( 'select', __( 'Thousands separator', 'brik-builder' ), 'content', array( 'default' => ',', 'options' => Fields::opts( array( ',' => __( 'Comma (1,000)', 'brik-builder' ), '.' => __( 'Period (1.000)', 'brik-builder' ), ' ' => __( 'Space (1 000)', 'brik-builder' ), '' => __( 'None (1000)', 'brik-builder' ) ) ) ) ),
			'duration'    => Fields::field( 'range', __( 'Duration (ms)', 'brik-builder' ), 'content', array( 'default' => 2000, 'min' => 200, 'max' => 6000, 'step' => 100 ) ),
			'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Teams onboarded', 'brik-builder' ), 'inline' => true ) ),
			'description' => Fields::field( 'text', __( 'Description', 'brik-builder' ), 'content' ),
			'size'        => Fields::field( 'select', __( 'Number size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'md' => __( 'Medium', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'align'       => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::typography( 'number', __( 'Number', 'brik-builder' ), Fields::WRAP . ' .brik-counter-number' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-counter-title' ),
		Fields::typography( 'description', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-counter-description' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$sizes = array(
			'md' => 'text-3xl',
			'lg' => 'text-4xl md:text-5xl',
			'xl' => 'text-5xl md:text-7xl',
		);
		$decimals  = max( 0, min( 4, (int) $a['decimals'] ) );
		$separator = in_array( $a['separator'], array( ',', '.', ' ', '' ), true ) ? $a['separator'] : ',';
		$end       = is_numeric( $a['end'] ) ? (float) $a['end'] : 0;
		$start     = is_numeric( $a['start'] ) ? (float) $a['start'] : 0;

		$number = '<div class="' . esc_attr( 'brik-counter-number font-heading font-bold tracking-tight tabular-nums ' . ( isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['lg'] ) ) . '">'
			. ( '' !== $a['prefix'] ? '<span class="brik-counter-prefix">' . brik_inline( $a['prefix'] ) . '</span>' : '' )
			. '<span' . brik_attrs(
				array(
					'class'          => 'brik-counter-value',
					'data-start'     => $start,
					'data-end'       => $end,
					'data-decimals'  => $decimals,
					'data-separator' => $separator,
					'data-duration'  => max( 0, (int) $a['duration'] ),
				)
			) . '>' . esc_html( brik_format_number( $end, $decimals, $separator ) ) . '</span>'
			. ( '' !== $a['suffix'] ? '<span class="brik-counter-suffix">' . brik_inline( $a['suffix'] ) . '</span>' : '' )
			. '</div>';

		$title = '' !== trim( (string) $a['title'] ) ? '<div class="brik-counter-title mt-2 text-base font-medium"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</div>' : '';
		$desc  = '' !== trim( (string) $a['description'] ) ? '<p class="brik-counter-description mt-1 text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>' : '';

		return '<div class="brik-counter" data-brik-counter>' . $number . $title . $desc . '</div>';
	},
);
