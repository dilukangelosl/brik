<?php
/**
 * Additional information: weight, dimensions and visible attributes in a table.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Woo\Product;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'product_additional_info',
	'title'       => __( 'Additional information', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'table',
	'description' => 'Attributes table of the current product (weight, dimensions, visible attributes; passed through woocommerce_display_product_attributes). style: striped|lines|plain. title: optional heading. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'title' => Fields::field( 'text', __( 'Heading', 'brik-builder' ), 'content' ),
			'style' => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'striped', 'options' => Fields::opts( array( 'striped' => __( 'Striped', 'brik-builder' ), 'lines' => __( 'Lines', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
		),
		Product::field(),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-attr-label' ),
		Fields::typography( 'value', __( 'Values', 'brik-builder' ), Fields::WRAP . ' .brik-attr-value' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Additional information', 'brik-builder' ) );
		}
		$table = brik_woo_attributes_table( $product, $a['style'] );
		if ( '' === $table ) {
			return $ctx->placeholder( __( 'This product has no attributes to list', 'brik-builder' ) );
		}
		$title = '' !== trim( (string) $a['title'] ) ? '<h2 class="brik-attr-title mb-4 font-heading text-xl font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h2>' : '';
		return $title . $table;
	},
);
