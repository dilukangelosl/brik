<?php
/**
 * Product stock status, following the chosen variation.
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
	'type'        => 'product_stock',
	'title'       => __( 'Product stock', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'package-check',
	'description' => 'Stock status of the current product ("In stock", "12 in stock", "Only 2 left", "Out of stock", "Available on backorder") with a colored dot. style: dot|badge. Updates when a variation is chosen. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'style' => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'dot', 'options' => Fields::opts( array( 'dot' => __( 'Text with dot', 'brik-builder' ), 'badge' => __( 'Badge', 'brik-builder' ) ) ) ) ),
		),
		Product::field(),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-stock-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product stock', 'brik-builder' ) );
		}
		$stock = Product::stock( $product );
		if ( '' === $stock['text'] ) {
			return $ctx->placeholder( __( 'Stock status', 'brik-builder' ) );
		}
		$badge = 'badge' === $a['style'];
		return sprintf(
			'<p class="%1$s" data-brik-stock="%2$d" data-status="%3$s"><span class="brik-stock-dot" aria-hidden="true"></span><span class="brik-stock-text">%4$s</span></p>',
			esc_attr( brik_cls( 'brik-stock inline-flex items-center gap-2 text-sm font-medium', array( 'brik-stock--badge rounded-full border px-2.5 py-0.5 text-xs' => $badge ) ) ),
			(int) $product->get_id(),
			esc_attr( $stock['status'] ),
			esc_html( $stock['text'] )
		);
	},
);
