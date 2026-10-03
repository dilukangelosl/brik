<?php
/**
 * Spacer: empty vertical space.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'spacer',
	'title'       => __( 'Spacer', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'move-vertical',
	'description' => 'Empty vertical space. space: CSS length, responsive (space@tablet, space@mobile). Default 48px.',
	'fields'      => array(
		'space' => Fields::field( 'unit', __( 'Space', 'brik-builder' ), 'content', array( 'default' => '48px', 'responsive' => true, 'css' => array( Fields::WRAP, 'height' ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		return $ctx->canvas ? '<div class="brik-spacer-guide" aria-hidden="true"></div>' : '';
	},
);
