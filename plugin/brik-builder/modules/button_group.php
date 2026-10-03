<?php
/**
 * Button group: several shadcn buttons in a row.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'button_group',
	'title'       => __( 'Button Group', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'rectangle-ellipsis',
	'description' => 'Row of buttons. buttons repeater [{text, link, variant: default|secondary|outline|ghost|link|destructive, icon, icon_position: start|end}]. size: sm|default|lg|xl. align: left|center|right (responsive). gap: CSS length. attached: toggle joins buttons into one segmented group. full: toggle stretches buttons to fill the row. stack_mobile: toggle stacks full-width on phones.',
	'fields'      => array_merge(
		array(
			'buttons'      => Fields::field(
				'repeater',
				__( 'Buttons', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'text',
					'fields'      => array(
						'text'          => Fields::field( 'text', __( 'Text', 'brik-builder' ) ),
						'link'          => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
						'variant'       => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
						'icon'          => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'icon_position' => Fields::field( 'select', __( 'Icon position', 'brik-builder' ), 'content', array( 'default' => 'end', 'options' => Fields::opts( array( 'start' => __( 'Before text', 'brik-builder' ), 'end' => __( 'After text', 'brik-builder' ) ) ) ) ),
					),
					'default'     => array(
						array( 'text' => __( 'Get started', 'brik-builder' ), 'link' => array( 'url' => '#' ), 'variant' => 'default', 'icon' => 'arrow-right', 'icon_position' => 'end' ),
						array( 'text' => __( 'View demo', 'brik-builder' ), 'link' => array( 'url' => '#' ), 'variant' => 'outline', 'icon' => 'play', 'icon_position' => 'start' ),
					),
				)
			),
			'size'         => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'align'        => Fields::field(
				'align',
				__( 'Alignment', 'brik-builder' ),
				'content',
				array(
					'responsive' => true,
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-btn-group-wrap',
						'map'      => array(
							'left'   => 'justify-content:flex-start',
							'center' => 'justify-content:center',
							'right'  => 'justify-content:flex-end',
						),
					),
				)
			),
			'gap'          => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'placeholder' => '12px', 'css' => array( Fields::WRAP . ' .brik-btn-group', 'gap' ) ) ),
			'attached'     => Fields::field( 'toggle', __( 'Join buttons', 'brik-builder' ), 'content', array( 'description' => __( 'Segmented group with shared edges.', 'brik-builder' ) ) ),
			'full'         => Fields::field( 'toggle', __( 'Full width', 'brik-builder' ), 'content' ),
			'stack_mobile' => Fields::field( 'toggle', __( 'Stack on mobile', 'brik-builder' ), 'content', array( 'default' => true ) ),
		),
		Fields::box( 'button', __( 'Buttons', 'brik-builder' ), Fields::WRAP . ' .brik-button' ),
		Fields::typography( 'button', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-button' )
	),
	'render'      => static function ( $a, $ctx ) {
		$buttons = brik_items( $a['buttons'] );
		if ( ! $buttons ) {
			return $ctx->placeholder( __( 'Add buttons', 'brik-builder' ) );
		}
		$full = ! empty( $a['full'] );
		$html = '';
		foreach ( $buttons as $btn ) {
			$icon = brik_item( $btn, 'icon' ) ? brik_icon( $btn['icon'] ) : '';
			$text = '' !== brik_item( $btn, 'text' ) ? '<span>' . brik_inline( $btn['text'] ) . '</span>' : '';
			$body = 'start' === brik_item( $btn, 'icon_position', 'end' ) ? $icon . $text : $text . $icon;
			$html .= '<a' . brik_link_attrs( brik_item( $btn, 'link', '#' ), array( 'class' => brik_button_class( brik_item( $btn, 'variant', 'default' ), $a['size'], $full ? 'flex-1' : '' ) ) ) . '>' . $body . '</a>';
		}
		$group = brik_cls(
			'brik-btn-group flex',
			array(
				'flex-wrap gap-3'          => empty( $a['attached'] ),
				'brik-btn-group--attached' => ! empty( $a['attached'] ),
				'brik-btn-group--stack'    => ! empty( $a['stack_mobile'] ),
				'w-full'                   => $full,
			)
		);
		return '<div class="brik-btn-group-wrap flex"><div class="' . esc_attr( $group ) . '" role="group">' . $html . '</div></div>';
	},
);
