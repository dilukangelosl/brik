<?php
/**
 * Divider (shadcn separator) with an optional label.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'divider',
	'title'       => __( 'Divider', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'separator-horizontal',
	'description' => 'Separator line. orientation: horizontal|vertical. line_style: solid|dashed|dotted|double. weight: CSS length (1px). line_color: color (default border token). line_width: length/% for horizontal; line_length for vertical. align: left|center|right. label: optional text in the line, label_position: center|left|right, label_icon: optional icon.',
	'fields'      => array_merge(
		array(
			'orientation'    => Fields::field( 'select', __( 'Orientation', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Horizontal', 'brik-builder' ), 'vertical' => __( 'Vertical', 'brik-builder' ) ) ) ) ),
			'label'          => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'placeholder' => __( 'e.g. or continue with', 'brik-builder' ), 'inline' => true ) ),
			'label_icon'     => Fields::field( 'icon', __( 'Label icon', 'brik-builder' ), 'content' ),
			'label_position' => Fields::field( 'select', __( 'Label position', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'center' => __( 'Center', 'brik-builder' ), 'left' => __( 'Start', 'brik-builder' ), 'right' => __( 'End', 'brik-builder' ) ) ), 'show_if' => array( 'orientation' => 'horizontal' ) ) ),
			'line_style'     => Fields::field( 'select', __( 'Line style', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line', 'brik-builder' ), 'default' => 'solid', 'options' => Fields::opts( array( 'solid' => __( 'Solid', 'brik-builder' ), 'dashed' => __( 'Dashed', 'brik-builder' ), 'dotted' => __( 'Dotted', 'brik-builder' ), 'double' => __( 'Double', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP, '--brik-sep-style' ) ) ),
			'weight'         => Fields::field( 'unit', __( 'Weight', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line', 'brik-builder' ), 'placeholder' => '1px', 'css' => array( Fields::WRAP, '--brik-sep-weight' ) ) ),
			'line_color'     => Fields::field( 'color', __( 'Color', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line', 'brik-builder' ), 'hover' => true, 'css' => array( 'selector' => Fields::WRAP, 'prop' => '--brik-sep-color', 'hover_selector' => Fields::WRAP . ':hover' ) ) ),
			'line_width'     => Fields::field( 'unit', __( 'Length', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line', 'brik-builder' ), 'responsive' => true, 'placeholder' => '100%', 'show_if' => array( 'orientation' => 'horizontal' ), 'css' => array( Fields::WRAP . ' .brik-sep', 'width' ) ) ),
			'line_length'    => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'line', array( 'tab' => 'design', 'group_label' => __( 'Line', 'brik-builder' ), 'responsive' => true, 'placeholder' => '48px', 'show_if' => array( 'orientation' => 'vertical' ), 'css' => array( Fields::WRAP . ' .brik-sep', 'height' ) ) ),
			'align'          => Fields::field(
				'select',
				__( 'Alignment', 'brik-builder' ),
				'line',
				array(
					'tab'         => 'design',
					'group_label' => __( 'Line', 'brik-builder' ),
					'responsive'  => true,
					'default'     => 'center',
					'options'     => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ),
					'css'         => array(
						'selector' => Fields::WRAP . ' .brik-sep',
						'map'      => array(
							'left'   => 'margin-left:0;margin-right:auto',
							'center' => 'margin-left:auto;margin-right:auto',
							'right'  => 'margin-left:auto;margin-right:0',
						),
					),
				)
			),
			'label_gap'      => Fields::field( 'unit', __( 'Space around label', 'brik-builder' ), 'label_typography', array( 'tab' => 'design', 'group_label' => __( 'Label', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-sep', 'gap' ) ) ),
		),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-sep-label' )
	),
	'render'      => static function ( $a, $ctx ) {
		$line = '<span class="brik-sep-line" aria-hidden="true"></span>';

		if ( 'vertical' === $a['orientation'] ) {
			return '<div class="brik-sep brik-sep--v" role="separator" aria-orientation="vertical">' . $line . '</div>';
		}

		$label = brik_inline( $a['label'] );
		$icon  = ! empty( $a['label_icon'] ) ? brik_icon( $a['label_icon'], 'size-4' ) : '';
		if ( '' === trim( $label ) && '' === $icon ) {
			return '<div class="brik-sep brik-sep--h" role="separator" aria-orientation="horizontal">' . $line . '</div>';
		}

		$pos   = in_array( $a['label_position'], array( 'left', 'right' ), true ) ? $a['label_position'] : 'center';
		$label = '<span class="brik-sep-label inline-flex shrink-0 items-center gap-2 text-sm text-muted-foreground">' . $icon . ( '' !== trim( $label ) ? '<span' . $ctx->inline( 'label' ) . '>' . $label . '</span>' : '' ) . '</span>';
		$html  = ( 'left' !== $pos ? $line : '' ) . $label . ( 'right' !== $pos ? $line : '' );
		return '<div class="brik-sep brik-sep--h brik-sep--label flex items-center gap-4">' . $html . '</div>';
	},
);
