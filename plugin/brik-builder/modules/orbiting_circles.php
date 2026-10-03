<?php
/**
 * Orbiting circles: a center logo with rings of icons orbiting around it. Pure CSS.
 *
 * Radii and icon sizes are stored in px for the full-size stage and turned into fractions
 * of the stage width, so the whole diagram scales down in narrow columns.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'orbiting_circles',
	'title'       => __( 'Orbiting Circles', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'orbit',
	'description' => 'Center icon/logo with rings of orbiting icons. center_icon (Lucide or brand:slug) or center_image, center_size px. rings: repeater [{radius px, duration seconds per turn, reverse: bool, icon_size px}] (ring 1 is the first row). items: repeater [{icon, image, label, ring: 1-4}] spread evenly on their ring. brand_colors: bool, brand icons in their own colors. paths: bool, draw the ring circles; dashed: bool. label: accessible description. Scales to the column width; reduced motion stops the orbit.',
	'fields'      => array(
		'center_icon'  => Fields::field( 'icon', __( 'Center icon', 'brik-builder' ), 'content', array( 'default' => 'sparkles' ) ),
		'center_image' => Fields::field( 'image', __( 'Center image', 'brik-builder' ), 'content', array( 'description' => __( 'Replaces the icon.', 'brik-builder' ) ) ),
		'center_size'  => Fields::field( 'range', __( 'Center size (px)', 'brik-builder' ), 'content', array( 'default' => 80, 'min' => 32, 'max' => 160, 'step' => 4 ) ),
		'rings'        => Fields::field(
			'repeater',
			__( 'Rings', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'radius',
				'fields'      => array(
					'radius'    => Fields::field( 'number', __( 'Radius (px)', 'brik-builder' ), 'content', array( 'default' => 100 ) ),
					'duration'  => Fields::field( 'number', __( 'Seconds per turn', 'brik-builder' ), 'content', array( 'default' => 24 ) ),
					'reverse'   => Fields::field( 'toggle', __( 'Reverse', 'brik-builder' ) ),
					'icon_size' => Fields::field( 'number', __( 'Icon size (px)', 'brik-builder' ), 'content', array( 'default' => 44 ) ),
				),
				'default'     => array(
					array( 'radius' => 96, 'duration' => 22, 'reverse' => false, 'icon_size' => 40 ),
					array( 'radius' => 176, 'duration' => 36, 'reverse' => true, 'icon_size' => 48 ),
				),
			)
		),
		'items'        => Fields::field(
			'repeater',
			__( 'Icons', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'label',
				'fields'      => array(
					'icon'  => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
					'image' => Fields::field( 'image', __( 'Image', 'brik-builder' ) ),
					'label' => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
					'ring'  => Fields::field( 'select', __( 'Ring', 'brik-builder' ), 'content', array( 'default' => '1', 'options' => Fields::opts( array( '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) ) ) ),
				),
				'default'     => array_merge(
					brik_fx_brand_items( array( 'github', 'figma', 'stripe' ), array( 'ring' => '1' ) ),
					brik_fx_brand_items( array( 'wordpress', 'discord', 'spotify', 'apple', 'youtube' ), array( 'ring' => '2' ) )
				),
			)
		),
		'brand_colors' => Fields::field( 'toggle', __( 'Brand colors', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'paths'        => Fields::field( 'toggle', __( 'Show ring paths', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'dashed'       => Fields::field( 'toggle', __( 'Dashed paths', 'brik-builder' ), 'content', array( 'show_if' => array( 'paths' => true ) ) ),
		'label'        => Fields::field( 'text', __( 'Accessible description', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Integrations', 'brik-builder' ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$rings = array();
		foreach ( brik_items( $a['rings'] ) as $ring ) {
			$rings[] = array(
				'r'    => brik_fx_num( brik_item( $ring, 'radius', 100 ), 100, 20, 600 ),
				'd'    => brik_fx_num( brik_item( $ring, 'duration', 24 ), 24, 2, 300 ),
				'rev'  => ! empty( $ring['reverse'] ),
				'size' => brik_fx_num( brik_item( $ring, 'icon_size', 44 ), 44, 16, 120 ),
			);
		}
		if ( ! $rings ) {
			$rings[] = array( 'r' => 100, 'd' => 24, 'rev' => false, 'size' => 44 );
		}

		// Group items per ring, dropping rings that don't exist.
		$per_ring = array();
		$labels   = array();
		foreach ( brik_items( $a['items'] ) as $item ) {
			$index = (int) brik_item( $item, 'ring', 1 ) - 1;
			if ( isset( $rings[ $index ] ) ) {
				$per_ring[ $index ][] = $item;
			}
			if ( '' !== trim( (string) brik_item( $item, 'label' ) ) ) {
				$labels[] = wp_strip_all_tags( $item['label'] );
			}
		}

		$center = brik_fx_num( $a['center_size'], 80, 32, 160 );
		$extent = $center / 2;
		foreach ( $rings as $ring ) {
			$extent = max( $extent, $ring['r'] + $ring['size'] / 2 );
		}
		$stage = (int) ceil( ( $extent + 6 ) * 2 );
		$half  = $stage / 2;
		$frac  = static function ( $px ) use ( $stage ) {
			return round( $px / $stage, 4 );
		};

		$svg = '';
		if ( ! empty( $a['paths'] ) ) {
			foreach ( $rings as $ring ) {
				$svg .= '<circle cx="' . $half . '" cy="' . $half . '" r="' . $ring['r'] . '"' . ( ! empty( $a['dashed'] ) ? ' stroke-dasharray="4 6"' : '' ) . '/>';
			}
			$svg = '<svg class="brik-orbit-paths" viewBox="0 0 ' . $stage . ' ' . $stage . '" aria-hidden="true">' . $svg . '</svg>';
		}

		$orbiters = '';
		foreach ( $per_ring as $index => $items ) {
			$ring  = $rings[ $index ];
			$count = count( $items );
			foreach ( $items as $n => $item ) {
				$icon  = brik_item( $item, 'icon' );
				$media = brik_has_image( brik_item( $item, 'image' ) )
					? brik_image( $item['image'], 'thumbnail', array( 'class' => 'size-[62%] object-contain', 'alt' => '' ) )
					: ( $icon ? brik_icon( $icon, 'size-[50%]' ) : '' );
				if ( '' === $media ) {
					continue;
				}
				$color = ! empty( $a['brand_colors'] ) ? brik_fx_brand_hex( $icon ) : '';
				// Offset each ring a little so icons on neighbouring rings don't line up.
				$angle = round( 360 / $count * $n + $index * 24, 2 );
				$style = brik_fx_vars(
					array(
						'--a'   => (string) $angle,
						'--rf'  => (string) ( 2 * $frac( $ring['r'] ) ),
						'--szf' => (string) $frac( $ring['size'] ),
						'--d'   => $ring['d'] . 's',
						'--dir' => $ring['rev'] ? 'reverse' : 'normal',
						'color' => $color,
					)
				);
				$orbiters .= '<span class="brik-orbit-item" style="' . esc_attr( $style ) . '">' . $media . '</span>';
			}
		}

		$core = brik_has_image( $a['center_image'] )
			? brik_image( $a['center_image'], 'medium', array( 'class' => 'size-[64%] object-contain', 'alt' => '' ) )
			: ( ! empty( $a['center_icon'] ) ? brik_icon( $a['center_icon'], 'size-[46%]' ) : '' );

		$label = '' !== trim( (string) $a['label'] ) ? wp_strip_all_tags( $a['label'] ) : __( 'Integrations', 'brik-builder' );
		if ( $labels ) {
			$label .= ': ' . implode( ', ', $labels );
		}

		return '<div class="brik-orbit" data-brik-fx-pause>'
			. '<div class="brik-orbit-stage" role="img" aria-label="' . esc_attr( $label ) . '" style="' . esc_attr( '--stage:' . $stage . 'px' ) . '">'
			. $svg
			. '<span class="brik-orbit-core" aria-hidden="true" style="' . esc_attr( '--szf:' . $frac( $center ) ) . '">' . $core . '</span>'
			. '<span class="brik-orbit-items" aria-hidden="true">' . $orbiters . '</span>'
			. '</div></div>';
	},
);
