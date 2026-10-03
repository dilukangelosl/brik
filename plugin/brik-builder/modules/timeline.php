<?php
/**
 * Timeline: dated entries along a vertical line.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'timeline',
	'title'       => __( 'Timeline', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'git-commit-vertical',
	'description' => 'Vertical timeline. items repeater [{date, title, text, icon (optional Lucide name; empty shows a dot)}]. layout: left|alternate (alternate falls back to left on mobile). marker: soft|solid|outline. boxed: toggle puts each entry in a card.',
	'fields'      => array_merge(
		array(
			'items'  => Fields::field(
				'repeater',
				__( 'Entries', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'fields'      => array(
						'date'  => Fields::field( 'text', __( 'Date', 'brik-builder' ) ),
						'title' => Fields::field( 'text', __( 'Title', 'brik-builder' ) ),
						'text'  => Fields::field( 'textarea', __( 'Text', 'brik-builder' ) ),
						'icon'  => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'date' => 'March 2022', 'title' => __( 'The idea', 'brik-builder' ), 'text' => __( 'Two designers get tired of rebuilding the same landing pages and sketch a better builder on a napkin.', 'brik-builder' ), 'icon' => 'lightbulb' ),
						array( 'date' => 'January 2023', 'title' => __( 'First release', 'brik-builder' ), 'text' => __( 'Version 1.0 ships with 30 modules and a handful of brave early adopters.', 'brik-builder' ), 'icon' => 'rocket' ),
						array( 'date' => 'August 2024', 'title' => __( '10,000 sites', 'brik-builder' ), 'text' => __( 'The community crosses ten thousand live sites and the team grows to twelve people.', 'brik-builder' ), 'icon' => 'users' ),
						array( 'date' => 'Today', 'title' => __( 'Still building', 'brik-builder' ), 'text' => __( 'Shipping every week, guided by feedback from people who build for a living.', 'brik-builder' ), 'icon' => 'sparkles' ),
					),
				)
			),
			'layout' => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Line on the left', 'brik-builder' ), 'alternate' => __( 'Alternating', 'brik-builder' ) ) ) ) ),
			'marker' => Fields::field( 'select', __( 'Marker style', 'brik-builder' ), 'content', array( 'default' => 'outline', 'options' => Fields::opts( array( 'outline' => __( 'Outline', 'brik-builder' ), 'soft' => __( 'Soft (tinted)', 'brik-builder' ), 'solid' => __( 'Solid', 'brik-builder' ) ) ) ) ),
			'boxed'  => Fields::field( 'toggle', __( 'Card entries', 'brik-builder' ), 'content' ),
			'gap'    => Fields::field( 'unit', __( 'Space between entries', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line & markers', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-tl', '--brik-tl-gap' ) ) ),
			'line_color' => Fields::field( 'color', __( 'Line color', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line & markers', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-tl', '--brik-tl-line' ) ) ),
		),
		Fields::box( 'marker', __( 'Marker', 'brik-builder' ), Fields::WRAP . ' .brik-tl-marker', array( 'bg', 'color', 'border_width', 'border_color', 'radius', 'shadow' ) ),
		Fields::box( 'entry', __( 'Entry card', 'brik-builder' ), Fields::WRAP . ' .brik-tl-body' ),
		Fields::typography( 'date', __( 'Date', 'brik-builder' ), Fields::WRAP . ' .brik-tl-date' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-tl-title' ),
		Fields::typography( 'body', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-tl-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_items( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add timeline entries', 'brik-builder' ) );
		}
		$markers = array(
			'outline' => 'border bg-background text-foreground shadow-xs',
			'soft'    => 'bg-primary/10 text-primary ring-4 ring-background',
			'solid'   => 'bg-primary text-primary-foreground ring-4 ring-background',
		);
		$marker  = isset( $markers[ $a['marker'] ] ) ? $markers[ $a['marker'] ] : $markers['outline'];
		$alt     = 'alternate' === $a['layout'];

		$html = '';
		foreach ( $items as $item ) {
			$icon = brik_item( $item, 'icon' ) ? brik_icon( $item['icon'], 'size-4' ) : '';
			$dot  = '' !== $icon ? $icon : '<span class="size-2 rounded-full bg-current"></span>';
			$date = brik_item( $item, 'date' );

			$body = '';
			if ( '' !== $date ) {
				$body .= '<time class="brik-tl-date text-sm font-medium text-muted-foreground">' . brik_inline( $date ) . '</time>';
			}
			if ( '' !== brik_item( $item, 'title' ) ) {
				$body .= '<h3 class="brik-tl-title font-heading text-base font-semibold tracking-tight">' . brik_inline( $item['title'] ) . '</h3>';
			}
			if ( '' !== brik_item( $item, 'text' ) ) {
				$body .= '<p class="brik-tl-text text-sm leading-relaxed text-muted-foreground">' . nl2br( brik_inline( $item['text'] ) ) . '</p>';
			}

			$html .= '<li class="brik-tl-item">'
				. '<span class="' . esc_attr( brik_cls( 'brik-tl-marker relative z-[1] flex size-10 items-center justify-center rounded-full', $marker ) ) . '" aria-hidden="true">' . $dot . '</span>'
				. '<div class="' . esc_attr( brik_cls( 'brik-tl-body grid gap-1', ! empty( $a['boxed'] ) ? 'rounded-xl border bg-card p-5 text-card-foreground shadow-sm' : 'pt-1.5' ) ) . '">' . $body . '</div>'
				. ( $alt && '' !== $date ? '<div class="brik-tl-side text-sm font-medium text-muted-foreground" aria-hidden="true">' . brik_inline( $date ) . '</div>' : '' )
				. '</li>';
		}
		return '<ol class="' . esc_attr( brik_cls( 'brik-tl', array( 'brik-tl--alt' => $alt ) ) ) . '">' . $html . '</ol>';
	},
);
