<?php
/**
 * Dock: row of icon links that magnify around the cursor, with tooltips.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'dock',
	'title'       => __( 'Dock', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'dock',
	'description' => 'Icon dock that magnifies the icons near the cursor, with tooltip labels. items: repeater [{icon (Lucide or brand:slug), label (tooltip and accessible name), link, separator: bool, divider before this item}]. size: base icon size in px (default 44). magnification: 1-2.5 (default 1.6). distance: px of influence (default 140). align: left|center|right. look: glass|solid. shape: circle|rounded. Touch devices and reduced motion get a static dock.',
	'fields'      => array_merge(
		array(
			'items'         => Fields::field(
				'repeater',
				__( 'Icons', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'fields'      => array(
						'icon'      => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'label'     => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'link'      => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
						'separator' => Fields::field( 'toggle', __( 'Divider before', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'icon' => 'house', 'label' => __( 'Home', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'search', 'label' => __( 'Search', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'mail', 'label' => __( 'Mail', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'calendar', 'label' => __( 'Calendar', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'music', 'label' => __( 'Music', 'brik-builder' ), 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'brand:github', 'label' => 'GitHub', 'link' => array( 'url' => '#' ), 'separator' => true ),
						array( 'icon' => 'brand:x', 'label' => 'X', 'link' => array( 'url' => '#' ) ),
						array( 'icon' => 'brand:figma', 'label' => 'Figma', 'link' => array( 'url' => '#' ) ),
					),
				)
			),
			'size'          => Fields::field( 'range', __( 'Icon size (px)', 'brik-builder' ), 'content', array( 'default' => 44, 'min' => 28, 'max' => 72, 'step' => 2 ) ),
			'magnification' => Fields::field( 'range', __( 'Magnification', 'brik-builder' ), 'content', array( 'default' => 1.6, 'min' => 1, 'max' => 2.5, 'step' => 0.1 ) ),
			'distance'      => Fields::field( 'range', __( 'Reach (px)', 'brik-builder' ), 'content', array( 'default' => 140, 'min' => 60, 'max' => 300, 'step' => 10 ) ),
			'align'         => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ) ) ),
			'look'          => Fields::field( 'select', __( 'Look', 'brik-builder' ), 'content', array( 'default' => 'glass', 'options' => Fields::opts( array( 'glass' => __( 'Frosted glass', 'brik-builder' ), 'solid' => __( 'Solid', 'brik-builder' ) ) ) ) ),
			'shape'         => Fields::field( 'select', __( 'Icon shape', 'brik-builder' ), 'content', array( 'default' => 'circle', 'options' => Fields::opts( array( 'circle' => __( 'Circle', 'brik-builder' ), 'rounded' => __( 'Rounded square', 'brik-builder' ) ) ) ) ),
			'label'         => Fields::field( 'text', __( 'Accessible name', 'brik-builder' ), 'content', array( 'default' => __( 'Quick links', 'brik-builder' ) ) ),
		),
		Fields::box( 'dock', __( 'Dock', 'brik-builder' ), Fields::WRAP . ' .brik-dock-bar' ),
		Fields::box( 'item', __( 'Icon', 'brik-builder' ), Fields::WRAP . ' .brik-dock-item', array( 'bg', 'color', 'border_width', 'border_color', 'shadow' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = '';
		foreach ( brik_items( $a['items'] ) as $item ) {
			$icon = brik_item( $item, 'icon' ) ? brik_icon( $item['icon'], 'brik-dock-svg' ) : '';
			if ( '' === $icon ) {
				continue;
			}
			$label = trim( wp_strip_all_tags( (string) brik_item( $item, 'label' ) ) );
			if ( ! empty( $item['separator'] ) && '' !== $items ) {
				$items .= '<li class="brik-dock-sep" role="separator" aria-hidden="true"></li>';
			}
			$extra = array(
				'class'      => 'brik-dock-item',
				'aria-label' => '' !== $label ? $label : null,
			);
			$tip   = '' !== $label ? '<span class="brik-dock-tip" aria-hidden="true">' . esc_html( $label ) . '</span>' : '';
			$items .= '<li class="brik-dock-li">' . ( brik_has_link( brik_item( $item, 'link' ) ) ? '<a' . brik_link_attrs( $item['link'], $extra ) . '>' . $icon . '</a>' : '<span' . brik_attrs( $extra ) . '>' . $icon . '</span>' ) . $tip . '</li>';
		}
		if ( '' === $items ) {
			return $ctx->placeholder( __( 'Add dock icons', 'brik-builder' ) );
		}
		$ctx->script( 'dock' );

		$size  = (int) brik_fx_num( $a['size'], 44, 28, 72 );
		$mag   = brik_fx_num( $a['magnification'], 1.6, 1, 2.5 );
		$align = array(
			'left'   => 'justify-start',
			'center' => 'justify-center',
			'right'  => 'justify-end',
		);
		$wrap  = brik_cls( 'brik-dock-wrap flex', isset( $align[ $a['align'] ] ) ? $align[ $a['align'] ] : 'justify-center' );
		$dock  = brik_cls(
			'brik-dock-bar brik-dock--' . ( 'solid' === $a['look'] ? 'solid' : 'glass' ),
			'brik-dock--' . ( 'rounded' === $a['shape'] ? 'rounded' : 'circle' )
		);
		return sprintf(
			'<nav class="%1$s" aria-label="%2$s" style="--brik-dock-size:%3$dpx;--brik-dock-mag:%4$s"><ul class="%5$s" data-mag="%4$s" data-distance="%6$d">%7$s</ul></nav>',
			esc_attr( $wrap ),
			esc_attr( '' !== trim( (string) $a['label'] ) ? wp_strip_all_tags( $a['label'] ) : __( 'Quick links', 'brik-builder' ) ),
			$size,
			esc_attr( (string) $mag ),
			esc_attr( $dock ),
			(int) brik_fx_num( $a['distance'], 140, 60, 300 ),
			$items
		);
	},
);
