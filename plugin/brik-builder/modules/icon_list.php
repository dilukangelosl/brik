<?php
/**
 * Icon list: short lines of text with an icon each.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'icon_list',
	'title'       => __( 'Icon List', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'list-checks',
	'description' => 'List with icons. items repeater [{text, icon (optional, falls back to "icon"), link}]. icon: default icon (Lucide). icon_style: plain|soft (tinted circle). layout: vertical|inline. size: sm|default|lg. dividers: toggle lines between vertical items. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'items'      => Fields::field(
				'repeater',
				__( 'Items', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'text',
					'fields'      => array(
						'text' => Fields::field( 'text', __( 'Text', 'brik-builder' ) ),
						'icon' => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty to use the list icon.', 'brik-builder' ) ) ),
						'link' => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'text' => __( 'Unlimited projects and pages', 'brik-builder' ) ),
						array( 'text' => __( 'Visual editing with live preview', 'brik-builder' ) ),
						array( 'text' => __( 'Responsive controls for every setting', 'brik-builder' ) ),
						array( 'text' => __( 'Priority support from real people', 'brik-builder' ) ),
					),
				)
			),
			'icon'       => Fields::field( 'icon', __( 'List icon', 'brik-builder' ), 'content', array( 'default' => 'circle-check' ) ),
			'icon_style' => Fields::field( 'select', __( 'Icon style', 'brik-builder' ), 'content', array( 'default' => 'plain', 'options' => Fields::opts( array( 'plain' => __( 'Plain', 'brik-builder' ), 'soft' => __( 'Tinted circle', 'brik-builder' ) ) ) ) ),
			'layout'     => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'vertical', 'responsive' => true, 'options' => Fields::opts( array( 'vertical' => __( 'Vertical', 'brik-builder' ), 'inline' => __( 'Inline', 'brik-builder' ) ) ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-icon-list', 'map' => array( 'vertical' => 'display:grid;justify-content:stretch', 'inline' => 'display:flex;flex-wrap:wrap;column-gap:1.5rem' ) ) ) ),
			'size'       => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
			'dividers'   => Fields::field( 'toggle', __( 'Dividers', 'brik-builder' ), 'content', array( 'show_if' => array( 'layout' => 'vertical' ) ) ),
			'align'      => Fields::field(
				'align',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'responsive' => true,
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-icon-list',
						'map'      => array(
							'left'   => 'justify-content:flex-start;justify-items:start',
							'center' => 'justify-content:center;justify-items:center',
							'right'  => 'justify-content:flex-end;justify-items:end',
						),
					),
				)
			),
			'gap'        => Fields::field( 'unit', __( 'Space between items', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-icon-list', 'row-gap' ) ) ),
			'icon_color' => Fields::field( 'color', __( 'Icon color', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'hover' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-icon-list-icon', 'prop' => 'color', 'hover_selector' => Fields::WRAP . ' .brik-icon-list-item:hover .brik-icon-list-icon' ) ) ),
			'icon_bg'    => Fields::field( 'color', __( 'Icon background', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'show_if' => array( 'icon_style' => 'soft' ), 'css' => array( Fields::WRAP . ' .brik-icon-list-icon', 'background-color' ) ) ),
			'icon_px'    => Fields::field( 'unit', __( 'Icon size', 'brik-builder' ), 'item', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-icon-list-icon', '--brik-icon-size' ) ) ),
		),
		Fields::typography( 'item', __( 'Item text', 'brik-builder' ), Fields::WRAP . ' .brik-icon-list-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = brik_items( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add list items', 'brik-builder' ) );
		}
		$sizes = array(
			'sm'      => array( 'text-sm', '[--brik-icon-size:0.875rem]', 'size-6' ),
			'default' => array( 'text-base', '[--brik-icon-size:1.125rem]', 'size-7' ),
			'lg'      => array( 'text-lg', '[--brik-icon-size:1.375rem]', 'size-9' ),
		);
		$size  = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['default'];
		$soft  = 'soft' === $a['icon_style'];
		$lines = ! empty( $a['dividers'] ) && 'inline' !== $a['layout'];

		$html = '';
		foreach ( $items as $item ) {
			$icon_name = brik_item( $item, 'icon', $a['icon'] );
			$icon      = $icon_name ? brik_icon( $icon_name, 'size-[var(--brik-icon-size)]' ) : '';
			if ( '' !== $icon ) {
				$icon = '<span class="' . esc_attr( brik_cls( 'brik-icon-list-icon inline-flex shrink-0 items-center justify-center text-primary', $size[1], $soft ? 'rounded-full bg-primary/10 ' . $size[2] : '' ) ) . '">' . $icon . '</span>';
			}
			$text = '<span class="brik-icon-list-text">' . brik_inline( brik_item( $item, 'text' ) ) . '</span>';
			$body = $icon . $text;
			if ( brik_has_link( brik_item( $item, 'link' ) ) ) {
				$body = '<a' . brik_link_attrs( $item['link'], array( 'class' => 'inline-flex items-center gap-3 underline-offset-4 hover:underline' ) ) . '>' . $body . '</a>';
			}
			$html .= '<li class="' . esc_attr( brik_cls( 'brik-icon-list-item flex items-center gap-3', array( 'border-b pb-3 last:border-0 last:pb-0' => $lines ) ) ) . '">' . $body . '</li>';
		}
		return '<ul class="' . esc_attr( brik_cls( 'brik-icon-list grid gap-3', $size[0] ) ) . '">' . $html . '</ul>';
	},
);
