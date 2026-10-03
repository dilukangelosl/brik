<?php
/**
 * Icon: a Lucide or brand icon, optionally inside a shape.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'icon',
	'title'       => __( 'Icon', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'shapes',
	'description' => 'Single icon. icon: Lucide name or "brand:github". icon_size: CSS length (default 32px). shape: none|circle|rounded|square. tone: soft|solid|outline|muted (shape fill). link: optional. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'icon'      => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'sparkles' ) ),
			'link'      => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
			'label'     => Fields::field( 'text', __( 'Accessible label', 'brik-builder' ), 'content', array( 'description' => __( 'Read by screen readers. Leave empty for decorative icons.', 'brik-builder' ) ) ),
			'shape'     => Fields::field( 'select', __( 'Background shape', 'brik-builder' ), 'content', array( 'default' => 'rounded', 'options' => Fields::opts( brik_icon_shape_options() ) ) ),
			'tone'      => Fields::field( 'select', __( 'Shape style', 'brik-builder' ), 'content', array( 'default' => 'soft', 'options' => Fields::opts( brik_icon_tone_options() ), 'show_if' => array( 'shape' => array( 'circle', 'rounded', 'square' ) ) ) ),
			'align'     => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
			'icon_size' => Fields::field( 'unit', __( 'Icon size', 'brik-builder' ), 'icon_style', array( 'tab' => 'design', 'default' => '32px', 'responsive' => true, 'group_label' => __( 'Icon', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-icon-shape', '--brik-icon-size' ) ) ),
			'rotate_icon' => Fields::field( 'range', __( 'Rotate icon', 'brik-builder' ), 'icon_style', array( 'tab' => 'design', 'hover' => true, 'min' => -180, 'max' => 180, 'unit' => 'deg', 'group_label' => __( 'Icon', 'brik-builder' ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-icon-svg', 'prop' => 'rotate', 'hover_selector' => Fields::WRAP . ':hover .brik-icon-svg' ) ) ),
		),
		Fields::box( 'shape', __( 'Icon shape', 'brik-builder' ), Fields::WRAP . ' .brik-icon-shape' )
	),
	'render'      => static function ( $a, $ctx ) {
		// The shape grows with the icon: padding scales with the icon size unless overridden.
		$html = brik_icon_shape( $a['icon'], $a['shape'], $a['tone'], 'auto' );
		if ( '' === $html ) {
			return $ctx->placeholder( __( 'Choose an icon', 'brik-builder' ) );
		}
		$label = trim( wp_strip_all_tags( (string) $a['label'] ) );
		if ( brik_has_link( $a['link'] ) ) {
			$extra = array( 'class' => 'brik-icon-link inline-flex rounded-lg outline-none transition-opacity hover:opacity-85 focus-visible:ring-[3px] focus-visible:ring-ring/50' );
			if ( '' !== $label ) {
				$extra['aria-label'] = $label;
			}
			return '<a' . brik_link_attrs( $a['link'], $extra ) . '>' . $html . '</a>';
		}
		if ( '' !== $label ) {
			return '<span class="inline-flex" role="img" aria-label="' . esc_attr( $label ) . '">' . $html . '</span>';
		}
		return '<span class="inline-flex">' . $html . '</span>';
	},
);
