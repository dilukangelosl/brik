<?php
/**
 * Product description (the product's main content).
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
	'type'        => 'product_description',
	'title'       => __( 'Product description', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'align-justify',
	'description' => 'Full description of the current product (its content, with blocks and shortcodes rendered). product: optional fixed product id.',
	'fields'      => array_merge(
		Product::field(),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-product-description' ),
		Fields::typography( 'headings', __( 'Headings', 'brik-builder' ), Fields::WRAP . ' .brik-product-description :is(h2,h3,h4)' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product description', 'brik-builder' ) );
		}
		$html = brik_woo_description_html( $product );
		if ( '' === trim( wp_strip_all_tags( $html, true ) ) && false === strpos( $html, '<img' ) ) {
			return $ctx->placeholder( __( 'This product has no description', 'brik-builder' ) );
		}
		return '<div class="brik-product-description brik-prose">' . $html . '</div>';
	},
);
