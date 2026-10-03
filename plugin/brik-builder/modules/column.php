<?php
/**
 * Column: holds modules (and nested rows).
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'column',
	'title'       => __( 'Column', 'brik-builder' ),
	'category'    => 'structure',
	'icon'        => 'rectangle-vertical',
	'structural'  => true,
	'children'    => true,
	'description' => 'Holds modules or nested rows. layout "stack" piles children vertically, "inline" lays them out in a wrapping row (e.g. buttons side by side).',
	'fields'      => array(
		'layout'  => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'responsive' => true, 'default' => '', 'options' => Fields::opts( array( '' => __( 'Stack', 'brik-builder' ), 'inline' => __( 'Inline (wrap)', 'brik-builder' ) ) ), 'css' => array( 'selector' => Fields::WRAP, 'map' => array( 'inline' => 'flex-direction:row;flex-wrap:wrap;align-items:center', 'stack' => 'flex-direction:column;flex-wrap:nowrap;align-items:stretch' ) ) ) ),
		'justify' => Fields::field( 'select', __( 'Distribute', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Start', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'flex-end' => __( 'End', 'brik-builder' ), 'space-between' => __( 'Space between', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP, 'justify-content' ) ) ),
		'items'   => Fields::field( 'select', __( 'Align items', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Default', 'brik-builder' ), 'flex-start' => __( 'Start', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'flex-end' => __( 'End', 'brik-builder' ), 'stretch' => __( 'Stretch', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP, 'align-items' ) ) ),
		'gap'     => Fields::field( 'unit', __( 'Space between elements', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'gap' ) ) ),
		'sticky'  => Fields::field( 'toggle', __( 'Sticky column', 'brik-builder' ), 'content', array( 'description' => __( 'Stays in view while the row scrolls.', 'brik-builder' ) ) ),
	),
	'class'       => static function ( $a ) {
		return ! empty( $a['sticky'] ) ? 'brik-column--sticky' : '';
	},
	'render'      => static function ( $a, $ctx ) {
		return $ctx->children();
	},
);
