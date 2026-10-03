<?php
/**
 * Star rating.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'star_rating',
	'title'       => __( 'Star Rating', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'star',
	'description' => 'Star rating with partial stars. rating (number, e.g. 4.5), max (stars, default 5), star_size: CSS length, star_color: color, label: optional text (e.g. "from 2,400 reviews"), show_value: toggle shows "4.5" before the label. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'rating'     => Fields::field( 'number', __( 'Rating', 'brik-builder' ), 'content', array( 'default' => 4.8, 'min' => 0, 'max' => 10, 'step' => 0.1 ) ),
			'max'        => Fields::field( 'number', __( 'Number of stars', 'brik-builder' ), 'content', array( 'default' => 5, 'min' => 1, 'max' => 10 ) ),
			'show_value' => Fields::field( 'toggle', __( 'Show value', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'label'      => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'default' => __( 'from 2,400+ reviews', 'brik-builder' ), 'inline' => true ) ),
			'align'      => Fields::field(
				'align',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'responsive' => true,
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-rating',
						'map'      => array(
							'left'   => 'justify-content:flex-start',
							'center' => 'justify-content:center',
							'right'  => 'justify-content:flex-end',
						),
					),
				)
			),
			'star_size'  => Fields::field( 'unit', __( 'Star size', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'responsive' => true, 'placeholder' => '20px', 'css' => array( Fields::WRAP . ' .brik-star', array( 'width', 'height' ) ) ) ),
			'star_color' => Fields::field( 'color', __( 'Star color', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-star-full', 'color' ) ) ),
			'empty_color' => Fields::field( 'color', __( 'Empty star color', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-star-empty', 'color' ) ) ),
			'star_gap'   => Fields::field( 'unit', __( 'Space between stars', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-stars', 'gap' ) ) ),
		),
		Fields::typography( 'value', __( 'Value', 'brik-builder' ), Fields::WRAP . ' .brik-rating-value' ),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-rating-label' )
	),
	'render'      => static function ( $a, $ctx ) {
		$max    = max( 1, min( 10, (int) $a['max'] ) );
		$rating = max( 0, min( $max, (float) $a['rating'] ) );
		$html   = brik_stars( $rating, $max, 'size-5' );
		if ( ! empty( $a['show_value'] ) ) {
			$html .= '<span class="brik-rating-value text-sm font-semibold">' . esc_html( number_format_i18n( $rating, 1 ) ) . '</span>';
		}
		if ( '' !== trim( (string) $a['label'] ) ) {
			$html .= '<span class="brik-rating-label text-sm text-muted-foreground"' . $ctx->inline( 'label' ) . '>' . brik_inline( $a['label'] ) . '</span>';
		}
		return '<div class="brik-rating flex flex-wrap items-center gap-x-2 gap-y-1">' . $html . '</div>';
	},
);
