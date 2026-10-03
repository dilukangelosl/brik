<?php
/**
 * Upsells.
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
	'type'        => 'product_upsells',
	'title'       => __( 'Upsells', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'sparkles',
	'description' => 'Upsell products set on the current product (Linked products → Upsells), shown as product cards. title: heading ("" hides it). limit: number of products. columns: 1-6 (responsive). orderby: rand|date|title|price|popularity|rating|menu_order. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'title'   => Fields::field( 'text', __( 'Heading', 'brik-builder' ), 'content', array( 'default' => __( 'Pairs well with', 'brik-builder' ), 'inline' => true ) ),
			'limit'   => Fields::field( 'number', __( 'Products', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 24 ) ),
			'columns' => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 6, 'responsive' => true ) ),
			'orderby' => Fields::field( 'select', __( 'Order', 'brik-builder' ), 'content', array( 'default' => 'rand', 'options' => Fields::opts( array( 'rand' => __( 'Random', 'brik-builder' ), 'date' => __( 'Newest', 'brik-builder' ), 'title' => __( 'Name', 'brik-builder' ), 'price' => __( 'Price', 'brik-builder' ), 'popularity' => __( 'Popularity', 'brik-builder' ), 'rating' => __( 'Rating', 'brik-builder' ), 'menu_order' => __( 'Menu order', 'brik-builder' ) ) ) ) ),
			'gap'     => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'default' => '24px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-product-grid', 'gap' ) ) ),
		),
		Product::field(),
		Fields::typography( 'heading', __( 'Heading', 'brik-builder' ), Fields::WRAP . ' .brik-linked-title' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Upsells', 'brik-builder' ) );
		}
		return brik_woo_linked_products( $product, 'upsells', $a, $ctx );
	},
);
