<?php
/**
 * Globe: rotating dotted Earth drawn with WebGL (2D canvas fallback), with arcs between places.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'globe',
	'title'       => __( 'Globe', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'globe',
	'description' => 'Interactive 3D dotted globe (WebGL, 2D canvas fallback) with animated arcs and markers. routes: repeater of {from_label, from_lat, from_lng, to_label, to_lat, to_lng, color}. size: max width (CSS length). dot_color: land dots (default foreground token). ocean: bool, faint ocean grid. arc_color / arc_color_2: arc gradient. glow_color, glow: 0-1 atmosphere strength. speed: auto-rotation 0-3 (0 = still). tilt: degrees. center_lng: longitude facing the viewer at start. interactive: bool, drag to spin. markers: bool. dot_size: 0.5-2. label: accessible description. Static with reduced motion.',
	'fields'      => array(
		'routes'      => Fields::field(
			'repeater',
			__( 'Arcs', 'brik-builder' ),
			'content',
			array(
				'title_field' => 'to_label',
				'default'     => brik_3d_default_routes( array( array( 'nyc', 'lon' ), array( 'lon', 'dxb' ), array( 'dxb', 'sin' ), array( 'sin', 'syd' ), array( 'sfo', 'tyo' ), array( 'sao', 'los' ), array( 'nyc', 'sao' ), array( 'lon', 'nbo' ), array( 'bom', 'tyo' ), array( 'sfo', 'lon' ) ) ),
				'fields'      => brik_3d_route_fields(),
			)
		),
		'speed'       => Fields::field( 'range', __( 'Rotation speed', 'brik-builder' ), 'content', array( 'default' => 1, 'min' => 0, 'max' => 3, 'step' => 0.1 ) ),
		'interactive' => Fields::field( 'toggle', __( 'Drag to rotate', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'markers'     => Fields::field( 'toggle', __( 'Pulsing markers', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'tilt'        => Fields::field( 'range', __( 'Tilt', 'brik-builder' ), 'content', array( 'default' => 18, 'min' => -45, 'max' => 45, 'unit' => 'deg' ) ),
		'center_lng'  => Fields::field( 'number', __( 'Start longitude', 'brik-builder' ), 'content', array( 'default' => 10, 'min' => -180, 'max' => 180, 'description' => __( 'Longitude facing the viewer when the globe appears.', 'brik-builder' ) ) ),
		'label'       => Fields::field( 'text', __( 'Accessible description', 'brik-builder' ), 'content', array( 'default' => __( 'Globe with arcs connecting cities around the world', 'brik-builder' ) ) ),
		'size'        => Fields::field( 'unit', __( 'Size', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => '600px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-globe-stage', 'max-width' ) ) ),
		'dot_color'   => Fields::field( 'color', __( 'Dot color', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'description' => __( 'Defaults to the text color, so it adapts to light and dark sections.', 'brik-builder' ) ) ),
		'dot_size'    => Fields::field( 'range', __( 'Dot size', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => 1, 'min' => 0.5, 'max' => 2, 'step' => 0.05 ) ),
		'ocean'       => Fields::field( 'toggle', __( 'Ocean dots', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ) ) ),
		'arc_color'   => Fields::field( 'color', __( 'Arc color', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => '#22d3ee' ) ),
		'arc_color_2' => Fields::field( 'color', __( 'Arc end color', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => '#a78bfa' ) ),
		'glow_color'  => Fields::field( 'color', __( 'Glow color', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => '#38bdf8' ) ),
		'glow'        => Fields::field( 'range', __( 'Glow strength', 'brik-builder' ), 'globe_style', array( 'tab' => 'design', 'group_label' => __( 'Globe', 'brik-builder' ), 'default' => 0.6, 'min' => 0, 'max' => 1, 'step' => 0.05 ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'globe' );

		$config = array(
			'routes'      => brik_3d_routes( $a['routes'] ),
			'speed'       => brik_3d_num( $a['speed'], 1, 0, 3 ),
			'interactive' => ! empty( $a['interactive'] ),
			'markers'     => ! empty( $a['markers'] ),
			'tilt'        => brik_3d_num( $a['tilt'], 18, -45, 45 ),
			'lng'         => brik_3d_num( $a['center_lng'], 10, -180, 180 ),
			'dot'         => brik_3d_color( $a['dot_color'] ),
			'dotSize'     => brik_3d_num( $a['dot_size'], 1, 0.5, 2 ),
			'ocean'       => ! empty( $a['ocean'] ),
			'arc'         => brik_3d_color( $a['arc_color'] ) ? brik_3d_color( $a['arc_color'] ) : '#22d3ee',
			'arc2'        => brik_3d_color( $a['arc_color_2'] ) ? brik_3d_color( $a['arc_color_2'] ) : '#a78bfa',
		);

		$glow  = brik_3d_color( $a['glow_color'] );
		$label = '' !== (string) $a['label'] ? wp_strip_all_tags( $a['label'] ) : __( 'Globe', 'brik-builder' );

		return sprintf(
			'<div class="brik-globe-stage relative mx-auto aspect-square w-full max-w-[600px]%1$s" data-config="%2$s" data-mask="%3$s"%4$s><div class="brik-globe-glow" aria-hidden="true"></div><canvas class="brik-globe-canvas" role="img" aria-label="%5$s"></canvas></div>',
			$config['interactive'] ? ' is-interactive' : '',
			esc_attr( wp_json_encode( $config ) ),
			esc_attr( brik_3d_land_mask() ),
			brik_3d_vars(
				array(
					'--brik-globe-glow'     => $glow ? $glow : '#38bdf8',
					'--brik-globe-strength' => (string) brik_3d_num( $a['glow'], 0.6, 0, 1 ),
				)
			),
			esc_attr( $label )
		);
	},
);
