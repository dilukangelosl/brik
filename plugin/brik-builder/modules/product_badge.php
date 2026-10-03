<?php
/**
 * Product badges: sale, new, out of stock, featured.
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
	'type'        => 'product_badge',
	'title'       => __( 'Product badge', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'award',
	'description' => 'Status badges for the current product, shown only when they apply. sale: bool (sale_format percent|text), new: bool (new_days: products younger than this), out_of_stock: bool, featured: bool. Custom labels: new_text, out_text, featured_text. Updates the sale badge when a variation is chosen. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'sale'          => Fields::field( 'toggle', __( 'Sale', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'sale_format'   => Fields::field( 'select', __( 'Sale badge text', 'brik-builder' ), 'content', array( 'default' => 'percent', 'options' => Fields::opts( array( 'percent' => __( 'Percentage (−20%)', 'brik-builder' ), 'text' => __( '"Sale"', 'brik-builder' ) ) ), 'show_if' => array( 'sale' => true ) ) ),
			'new'           => Fields::field( 'toggle', __( 'New', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'new_days'      => Fields::field( 'number', __( 'New for (days)', 'brik-builder' ), 'content', array( 'default' => 30, 'min' => 1, 'show_if' => array( 'new' => true ) ) ),
			'new_text'      => Fields::field( 'text', __( '"New" text', 'brik-builder' ), 'content', array( 'default' => __( 'New', 'brik-builder' ), 'show_if' => array( 'new' => true ) ) ),
			'out_of_stock'  => Fields::field( 'toggle', __( 'Out of stock', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'out_text'      => Fields::field( 'text', __( '"Out of stock" text', 'brik-builder' ), 'content', array( 'default' => __( 'Sold out', 'brik-builder' ), 'show_if' => array( 'out_of_stock' => true ) ) ),
			'featured'      => Fields::field( 'toggle', __( 'Featured', 'brik-builder' ), 'content' ),
			'featured_text' => Fields::field( 'text', __( '"Featured" text', 'brik-builder' ), 'content', array( 'default' => __( 'Featured', 'brik-builder' ), 'show_if' => array( 'featured' => true ) ) ),
		),
		Product::field()
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product badge', 'brik-builder' ) );
		}
		$text   = static function ( $value, $fallback ) {
			$value = trim( wp_strip_all_tags( (string) $value ) );
			return '' !== $value ? $value : $fallback;
		};
		$badges = '';
		if ( ! empty( $a['out_of_stock'] ) && ! $product->is_in_stock() ) {
			$badges .= Product::badge( $text( $a['out_text'], __( 'Sold out', 'brik-builder' ) ), 'secondary', 'brik-badge-out' );
		}
		if ( ! empty( $a['sale'] ) ) {
			$label   = $product->is_on_sale() ? Product::sale_label( $product, $a['sale_format'] ) : '';
			$badges .= '<span class="' . esc_attr( brik_badge_class( 'destructive', 'default', 'brik-product-badge brik-badge-sale' ) ) . '" data-brik-sale-badge="' . (int) $product->get_id() . '" data-format="' . esc_attr( 'text' === $a['sale_format'] ? 'text' : 'percent' ) . '"' . ( '' === $label ? ' hidden' : '' ) . '>' . esc_html( $label ) . '</span>';
		}
		if ( ! empty( $a['new'] ) && Product::is_new( $product, (int) $a['new_days'] ) ) {
			$badges .= Product::badge( $text( $a['new_text'], __( 'New', 'brik-builder' ) ), 'default', 'brik-badge-new' );
		}
		if ( ! empty( $a['featured'] ) && $product->is_featured() ) {
			$badges .= Product::badge( $text( $a['featured_text'], __( 'Featured', 'brik-builder' ) ), 'outline', 'brik-badge-featured' );
		}
		// The sale badge can be present but hidden until a discounted variation is chosen.
		if ( '' === trim( wp_strip_all_tags( $badges ) ) && false === strpos( $badges, 'data-brik-sale-badge' ) ) {
			return $ctx->placeholder( __( 'No badge applies to this product', 'brik-builder' ) );
		}
		return '<div class="brik-product-badges flex flex-wrap items-center gap-1.5">' . $badges . '</div>';
	},
);
