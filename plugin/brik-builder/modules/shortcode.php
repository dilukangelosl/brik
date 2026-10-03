<?php
/**
 * Shortcode: runs any WordPress shortcode.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'shortcode',
	'title'       => __( 'Shortcode', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'square-code',
	'description' => 'Outputs a WordPress shortcode. shortcode: e.g. [gallery ids="1,2"] or [contact-form-7 id="12"].',
	'fields'      => array(
		'shortcode' => Fields::field( 'textarea', __( 'Shortcode', 'brik-builder' ), 'content', array( 'placeholder' => '[gallery ids="1,2,3"]' ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$code = trim( (string) $a['shortcode'] );
		if ( '' === $code ) {
			return $ctx->placeholder( __( 'Enter a shortcode', 'brik-builder' ) );
		}
		$out = do_shortcode( shortcode_unautop( $code ) );
		if ( $ctx->canvas && '' === trim( $out ) ) {
			return $ctx->placeholder( __( 'This shortcode produced no output.', 'brik-builder' ) );
		}
		return $out;
	},
);
