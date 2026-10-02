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
	'title'       => __( 'Column', 'brik' ),
	'category'    => 'structure',
	'icon'        => 'rectangle-vertical',
	'structural'  => true,
	'children'    => true,
	'description' => 'Holds modules or nested rows. layout "stack" piles children vertically, "inline" lays them out in a wrapping row (e.g. buttons side by side).',
	'fields'      => array(
		'layout'  => Fields::field( 'select', __( 'Layout', 'brik' ), 'content', array( 'responsive' => true, 'default' => '', 'options' => Fields::opts( array( '' => __( 'Stack', 'brik' ), 'inline' => __( 'Inline (wrap)', 'brik' ) ) ), 'css' => array( 'selector' => Fields::WRAP, 'map' => array( 'inline' => 'flex-direction:row;flex-wrap:wrap;align-items:center', 'stack' => 'flex-direction:column;flex-wrap:nowrap;align-items:stretch' ) ) ) ),
		'justify' => Fields::field( 'select', __( 'Distribute', 'brik' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Start', 'brik' ), 'center' => __( 'Center', 'brik' ), 'flex-end' => __( 'End', 'brik' ), 'space-between' => __( 'Space between', 'brik' ) ) ), 'css' => array( Fields::WRAP, 'justify-content' ) ) ),
		'items'   => Fields::field( 'select', __( 'Align items', 'brik' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Default', 'brik' ), 'flex-start' => __( 'Start', 'brik' ), 'center' => __( 'Center', 'brik' ), 'flex-end' => __( 'End', 'brik' ), 'stretch' => __( 'Stretch', 'brik' ) ) ), 'css' => array( Fields::WRAP, 'align-items' ) ) ),
		'gap'     => Fields::field( 'unit', __( 'Space between elements', 'brik' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'gap' ) ) ),
		'sticky'  => Fields::field( 'toggle', __( 'Sticky column', 'brik' ), 'content', array( 'description' => __( 'Stays in view while the row scrolls.', 'brik' ) ) ),
	),
	'class'       => static function ( $a ) {
		return ! empty( $a['sticky'] ) ? 'brik-column--sticky' : '';
	},
	'render'      => static function ( $a, $ctx ) {
		return $ctx->children();
	},
);
