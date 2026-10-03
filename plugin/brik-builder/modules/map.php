<?php
/**
 * Google Map embed without an API key.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'map',
	'title'       => __( 'Map', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'map-pin',
	'description' => 'Google Maps embed (no API key). address: street address, place name or "lat,lng". zoom: 1-21. map_type: roadmap|satellite|hybrid|terrain. map_height: CSS length (responsive). gray_map: bool, grayscale map (in color on hover). rounded: none|sm|md|lg|xl|2xl.',
	'fields'      => array(
		'address'   => Fields::field( 'text', __( 'Address or coordinates', 'brik-builder' ), 'content', array( 'default' => 'Ferry Building, San Francisco, CA', 'placeholder' => __( 'e.g. 1600 Amphitheatre Pkwy or 37.42,-122.08', 'brik-builder' ) ) ),
		'zoom'      => Fields::field( 'range', __( 'Zoom', 'brik-builder' ), 'content', array( 'default' => 14, 'min' => 1, 'max' => 21, 'step' => 1 ) ),
		'map_type'  => Fields::field( 'select', __( 'Map type', 'brik-builder' ), 'content', array( 'default' => 'roadmap', 'options' => Fields::opts( array( 'roadmap' => __( 'Road map', 'brik-builder' ), 'satellite' => __( 'Satellite', 'brik-builder' ), 'hybrid' => __( 'Hybrid', 'brik-builder' ), 'terrain' => __( 'Terrain', 'brik-builder' ) ) ) ) ),
		'map_height' => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'content', array( 'default' => '420px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-map-frame', 'height' ) ) ),
		'gray_map'  => Fields::field( 'toggle', __( 'Grayscale', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'description' => __( 'Shows the map in color on hover.', 'brik-builder' ) ) ),
		'rounded'   => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
		'bordered'  => Fields::field( 'toggle', __( 'Border', 'brik-builder' ), 'map_style', array( 'tab' => 'design', 'group_label' => __( 'Map', 'brik-builder' ), 'default' => true ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		if ( '' === trim( (string) $a['address'] ) ) {
			return $ctx->placeholder( __( 'Enter an address', 'brik-builder' ) );
		}
		$class = brik_cls(
			'brik-map-frame relative w-full overflow-hidden bg-muted',
			brik_radius_class( $a['rounded'] ),
			array(
				'border'                                                             => ! empty( $a['bordered'] ),
				'brik-map--gray [&_iframe]:grayscale [&_iframe]:transition-[filter] [&_iframe]:duration-500 hover:[&_iframe]:grayscale-0' => ! empty( $a['gray_map'] ),
			)
		);
		return sprintf(
			'<div class="%1$s"><iframe class="absolute inset-0 h-full w-full" src="%2$s" title="%3$s" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>',
			esc_attr( $class ),
			esc_url( brik_map_embed_url( $a['address'], '' !== $a['zoom'] ? $a['zoom'] : 14, $a['map_type'] ) ),
			/* translators: %s: address shown on the map */
			esc_attr( sprintf( __( 'Map of %s', 'brik-builder' ), wp_strip_all_tags( $a['address'] ) ) )
		);
	},
);
