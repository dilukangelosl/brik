<?php
/**
 * Animated beam: integration diagram with light beams flowing between icons and a hub.
 *
 * The icons are plain markup; the beams are drawn by fx/animated-beam.js, which measures
 * the icons and redraws the SVG paths whenever the layout changes.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_beam_node_fields = array(
	'icon'  => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
	'image' => Fields::field( 'image', __( 'Image', 'brik-builder' ) ),
	'label' => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
);

return array(
	'type'        => 'animated_beam',
	'title'       => __( 'Animated Beam', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'workflow',
	'description' => 'Integration diagram: left icons → center hub → right icons, joined by animated gradient beams. left_icons, right_icons: repeaters [{icon (Lucide or brand:slug), image, label}]. center_icon or center_image. flow: through (left into hub, hub out to right) | inward (all beams flow into the hub) | outward. color_1, color_2: beam gradient. duration: seconds per beam run. curvature: 0-100. icon_size px, center_size px. diagram_height: CSS length. brand_colors: bool. Paths are recomputed on resize; reduced motion shows static lines.',
	'fields'      => array(
		'left_icons'   => Fields::field(
			'repeater',
			__( 'Left icons', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'label',
				'fields'      => $brik_beam_node_fields,
				'default'     => brik_fx_brand_items( array( 'github', 'figma', 'stripe', 'discord', 'wordpress' ) ),
			)
		),
		'center_icon'  => Fields::field( 'icon', __( 'Center icon', 'brik-builder' ), 'content', array( 'default' => 'zap' ) ),
		'center_image' => Fields::field( 'image', __( 'Center image', 'brik-builder' ) ),
		'center_label' => Fields::field( 'text', __( 'Center label', 'brik-builder' ), 'content', array( 'default' => __( 'Your app', 'brik-builder' ) ) ),
		'right_icons'  => Fields::field(
			'repeater',
			__( 'Right icons', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'label',
				'fields'      => $brik_beam_node_fields,
				'default'     => array(
					array( 'icon' => 'user', 'label' => __( 'Customers', 'brik-builder' ) ),
					array( 'icon' => 'mail', 'label' => __( 'Email', 'brik-builder' ) ),
					array( 'icon' => 'database', 'label' => __( 'Database', 'brik-builder' ) ),
				),
			)
		),
		'flow'         => Fields::field( 'select', __( 'Flow', 'brik-builder' ), 'content', array( 'default' => 'through', 'options' => Fields::opts( array( 'through' => __( 'Left to right through the hub', 'brik-builder' ), 'inward' => __( 'Into the hub', 'brik-builder' ), 'outward' => __( 'Out of the hub', 'brik-builder' ) ) ) ) ),
		'duration'     => Fields::field( 'range', __( 'Beam duration (s)', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 10, 'step' => 0.5 ) ),
		'curvature'    => Fields::field( 'range', __( 'Curvature', 'brik-builder' ), 'content', array( 'default' => 50, 'min' => 0, 'max' => 100, 'step' => 5 ) ),
		'icon_size'    => Fields::field( 'range', __( 'Icon size (px)', 'brik-builder' ), 'content', array( 'default' => 52, 'min' => 32, 'max' => 96, 'step' => 2 ) ),
		'center_size'  => Fields::field( 'range', __( 'Center size (px)', 'brik-builder' ), 'content', array( 'default' => 88, 'min' => 48, 'max' => 160, 'step' => 4 ) ),
		'diagram_height' => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'content', array( 'default' => '400px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-beam', 'min-height' ) ) ),
		'brand_colors' => Fields::field( 'toggle', __( 'Brand colors', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'color_1'      => Fields::field( 'color', __( 'Beam color 1', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Beam colors', 'brik-builder' ), 'placeholder' => '#6366f1' ) ),
		'color_2'      => Fields::field( 'color', __( 'Beam color 2', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Beam colors', 'brik-builder' ), 'placeholder' => '#ec4899' ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$node = static function ( array $item, $brand ) {
			$icon  = brik_item( $item, 'icon' );
			$label = trim( wp_strip_all_tags( (string) brik_item( $item, 'label' ) ) );
			$media = brik_has_image( brik_item( $item, 'image' ) )
				? brik_image( $item['image'], 'thumbnail', array( 'class' => 'size-[58%] object-contain', 'alt' => '' ) )
				: ( $icon ? brik_icon( $icon, 'size-[46%]' ) : '' );
			if ( '' === $media ) {
				return '';
			}
			$color = $brand ? brik_fx_brand_hex( $icon ) : '';
			return '<li class="brik-beam-node"' . ( $color ? ' style="' . esc_attr( 'color:' . $color ) . '"' : '' ) . ( '' !== $label ? ' title="' . esc_attr( $label ) . '"' : '' ) . '>'
				. $media . ( '' !== $label ? '<span class="sr-only">' . esc_html( $label ) . '</span>' : '' ) . '</li>';
		};
		$column = static function ( $items, $side ) use ( $node, $a ) {
			$html = '';
			foreach ( brik_items( $items ) as $item ) {
				$html .= $node( $item, ! empty( $a['brand_colors'] ) );
			}
			return '' !== $html ? '<ul class="brik-beam-col brik-beam-col--' . $side . '">' . $html . '</ul>' : '<div class="brik-beam-col brik-beam-col--' . $side . '"></div>';
		};

		$left  = $column( $a['left_icons'], 'left' );
		$right = $column( $a['right_icons'], 'right' );
		$core  = brik_has_image( $a['center_image'] )
			? brik_image( $a['center_image'], 'medium', array( 'class' => 'size-[62%] object-contain', 'alt' => '' ) )
			: ( ! empty( $a['center_icon'] ) ? brik_icon( $a['center_icon'], 'size-[42%]' ) : '' );
		$clabel = trim( wp_strip_all_tags( (string) $a['center_label'] ) );

		$ctx->script( 'animated-beam' );

		$flow = in_array( $a['flow'], array( 'through', 'inward', 'outward' ), true ) ? $a['flow'] : 'through';
		$vars = brik_fx_vars(
			array(
				'--brik-beam-icon'   => (int) brik_fx_num( $a['icon_size'], 52, 32, 96 ) . 'px',
				'--brik-beam-center' => (int) brik_fx_num( $a['center_size'], 88, 48, 160 ) . 'px',
				'--brik-beam-dur'    => brik_fx_num( $a['duration'], 3, 1, 10 ) . 's',
				'--brik-beam-c1'     => $a['color_1'],
				'--brik-beam-c2'     => $a['color_2'],
			)
		);
		return '<div class="brik-beam" data-flow="' . esc_attr( $flow ) . '" data-curve="' . esc_attr( (string) brik_fx_num( $a['curvature'], 50, 0, 100 ) ) . '" data-brik-fx-pause style="' . esc_attr( $vars ) . '">'
			. '<svg class="brik-beam-svg" aria-hidden="true" focusable="false"></svg>'
			. $left
			. '<div class="brik-beam-hub"><div class="brik-beam-core"' . ( '' !== $clabel ? ' title="' . esc_attr( $clabel ) . '"' : '' ) . '>' . $core . '</div>' . ( '' !== $clabel ? '<span class="brik-beam-hub-label">' . esc_html( $clabel ) . '</span>' : '' ) . '</div>'
			. $right
			. '</div>';
	},
);
