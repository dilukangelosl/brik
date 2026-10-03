<?php
/**
 * Accordion (shadcn/ui accordion) built on <details>. One item works as a Divi-style toggle.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'accordion',
	'title'       => __( 'Accordion', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'rows-3',
	'description' => 'Collapsible items (FAQ). items: repeater of {title, content (HTML), icon (Lucide, optional)}. type: single (one open at a time) | multiple. first_open: bool. style: default (dividers) | bordered (one framed box) | separated (individual cards). indicator: chevron|plus|none. indicator_position: end|start. faq_schema: bool, outputs FAQPage JSON-LD.',
	'fields'      => array_merge(
		array(
			'items'              => Fields::field(
				'repeater',
				__( 'Items', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'fields'      => array(
						'title'   => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content' ),
						'content' => Fields::field( 'richtext', __( 'Content', 'brik-builder' ), 'content' ),
						'icon'    => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content' ),
					),
					'default'     => array(
						array(
							'title'   => __( 'Is it accessible?', 'brik-builder' ),
							'content' => '<p>' . __( 'Yes. It follows the WAI-ARIA disclosure pattern and works with the keyboard and screen readers out of the box, because it is built on native HTML elements.', 'brik-builder' ) . '</p>',
							'icon'    => '',
						),
						array(
							'title'   => __( 'Can I change how it looks?', 'brik-builder' ),
							'content' => '<p>' . __( 'Pick one of the built-in styles, then fine-tune the titles, panels and icons from the Design tab. Colors follow your global theme tokens, including dark mode.', 'brik-builder' ) . '</p>',
							'icon'    => '',
						),
						array(
							'title'   => __( 'Does it animate?', 'brik-builder' ),
							'content' => '<p>' . __( 'Panels open with a short height transition in browsers that support it, and simply appear everywhere else. Visitors who prefer reduced motion get no animation.', 'brik-builder' ) . '</p>',
							'icon'    => '',
						),
					),
				)
			),
			'type'               => Fields::field( 'select', __( 'Behaviour', 'brik-builder' ), 'content', array( 'default' => 'single', 'options' => Fields::opts( array( 'single' => __( 'One open at a time', 'brik-builder' ), 'multiple' => __( 'Several can be open', 'brik-builder' ) ) ) ) ),
			'first_open'         => Fields::field( 'toggle', __( 'First item open', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'style'              => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'default' => __( 'Dividers', 'brik-builder' ), 'bordered' => __( 'Bordered box', 'brik-builder' ), 'separated' => __( 'Separated cards', 'brik-builder' ) ) ) ) ),
			'indicator'          => Fields::field( 'select', __( 'Indicator', 'brik-builder' ), 'content', array( 'default' => 'chevron', 'options' => Fields::opts( array( 'chevron' => __( 'Chevron', 'brik-builder' ), 'plus' => __( 'Plus / minus', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
			'indicator_position' => Fields::field( 'select', __( 'Indicator position', 'brik-builder' ), 'content', array( 'default' => 'end', 'options' => Fields::opts( array( 'end' => __( 'After title', 'brik-builder' ), 'start' => __( 'Before title', 'brik-builder' ) ) ), 'show_if' => array( 'indicator' => array( 'chevron', 'plus' ) ) ) ),
			'faq_schema'         => Fields::field( 'toggle', __( 'FAQ structured data', 'brik-builder' ), 'content', array( 'description' => __( 'Adds FAQPage schema so search engines can show the questions as rich results.', 'brik-builder' ) ) ),
			'gap'                => Fields::field( 'unit', __( 'Space between cards', 'brik-builder' ), 'content', array( 'responsive' => true, 'show_if' => array( 'style' => 'separated' ), 'css' => array( Fields::WRAP . ' .brik-accordion', 'gap' ) ) ),
			'icon_color'         => Fields::field( 'color', __( 'Indicator color', 'brik-builder' ), 'indicator_style', array( 'tab' => 'design', 'group_label' => __( 'Indicator', 'brik-builder' ), 'hover' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-acc-indicator', 'prop' => 'color', 'hover_selector' => Fields::WRAP . ' .brik-acc-trigger:hover .brik-acc-indicator' ) ) ),
		),
		Fields::box( 'item', __( 'Item', 'brik-builder' ), Fields::WRAP . ' .brik-acc-item' ),
		Fields::box( 'open_item', __( 'Open item', 'brik-builder' ), Fields::WRAP . ' .brik-acc-item[open]', array( 'bg', 'color', 'border_color', 'shadow' ) ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-acc-trigger' ),
		Fields::typography( 'content', __( 'Content', 'brik-builder' ), Fields::WRAP . ' .brik-acc-body' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = is_array( $a['items'] ) ? array_values( array_filter( $a['items'], 'is_array' ) ) : array();
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add accordion items', 'brik-builder' ) );
		}
		return brik_disclosure_list(
			$items,
			array(
				'name'      => 'single' === $a['type'] ? $ctx->uid() : '',
				'first'     => ! empty( $a['first_open'] ),
				'style'     => $a['style'],
				'indicator' => $a['indicator'],
				'position'  => $a['indicator_position'],
			)
		) . ( ! empty( $a['faq_schema'] ) && ! $ctx->canvas ? brik_faq_schema( $items ) : '' );
	},
);
