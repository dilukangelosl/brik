<?php
/**
 * Icon cloud: icons and logos arranged on a rotating 3D sphere.
 *
 * Without JavaScript the icons sit in a simple wrapped grid inside the same square box.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'icon_cloud',
	'title'       => __( 'Icon Cloud', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'globe',
	'description' => 'Interactive 3D sphere of icons or brand logos that rotates on its own; the cursor steers it and it can be dragged. items: repeater [{icon (Lucide or brand:slug), image, label}] (defaults to ~30 brand logos). size: sphere diameter px (scales down to the column). icon_size px. speed: 0.25-3. brand_colors: bool (off = monochrome text color). interactive: bool. Reduced motion shows a still sphere.',
	'fields'      => array(
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
				),
				'default'     => brik_fx_brand_items( brik_fx_default_brands() ),
			)
		),
		'size'         => Fields::field( 'range', __( 'Size (px)', 'brik-builder' ), 'content', array( 'default' => 380, 'min' => 200, 'max' => 720, 'step' => 10 ) ),
		'icon_size'    => Fields::field( 'range', __( 'Icon size (px)', 'brik-builder' ), 'content', array( 'default' => 34, 'min' => 16, 'max' => 72, 'step' => 2 ) ),
		'speed'        => Fields::field( 'range', __( 'Speed', 'brik-builder' ), 'content', array( 'default' => 1, 'min' => 0.25, 'max' => 3, 'step' => 0.25 ) ),
		'brand_colors' => Fields::field( 'toggle', __( 'Brand colors', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'interactive'  => Fields::field( 'toggle', __( 'Steer with cursor and drag', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'label'        => Fields::field( 'text', __( 'Accessible description', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Technologies we work with', 'brik-builder' ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$cells  = '';
		$labels = array();
		foreach ( brik_items( $a['items'] ) as $item ) {
			$icon  = brik_item( $item, 'icon' );
			$media = brik_has_image( brik_item( $item, 'image' ) )
				? brik_image( $item['image'], 'thumbnail', array( 'class' => 'size-full object-contain', 'alt' => '' ) )
				: ( $icon ? brik_icon( $icon, 'size-full' ) : '' );
			if ( '' === $media ) {
				continue;
			}
			$label = trim( wp_strip_all_tags( (string) brik_item( $item, 'label' ) ) );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
			$color  = ! empty( $a['brand_colors'] ) ? brik_fx_brand_hex( $icon ) : '';
			$cells .= '<li class="brik-cloud-item"' . ( $color ? ' style="' . esc_attr( 'color:' . $color ) . '"' : '' ) . ( '' !== $label ? ' title="' . esc_attr( $label ) . '"' : '' ) . '>' . $media . '</li>';
		}
		if ( '' === $cells ) {
			return $ctx->placeholder( __( 'Add icons', 'brik-builder' ) );
		}
		$ctx->script( 'icon-cloud' );

		$label = '' !== trim( (string) $a['label'] ) ? wp_strip_all_tags( $a['label'] ) : __( 'Technologies we work with', 'brik-builder' );
		if ( $labels ) {
			$label .= ': ' . implode( ', ', $labels );
		}
		$style = '--brik-cloud-size:' . (int) brik_fx_num( $a['size'], 380, 200, 720 ) . 'px;--brik-cloud-icon:' . (int) brik_fx_num( $a['icon_size'], 34, 16, 72 ) . 'px';
		return '<div class="brik-cloud" role="img" aria-label="' . esc_attr( $label ) . '" style="' . esc_attr( $style ) . '" data-speed="' . esc_attr( (string) brik_fx_num( $a['speed'], 1, 0.25, 3 ) ) . '" data-interactive="' . ( ! empty( $a['interactive'] ) ? '1' : '0' ) . '">'
			. '<ul class="brik-cloud-list" aria-hidden="true">' . $cells . '</ul></div>';
	},
);
