<?php
/**
 * Product short description.
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
	'type'        => 'product_short_description',
	'title'       => __( 'Short description', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'file-text',
	'description' => 'Short description (excerpt) of the current product, passed through WooCommerce\'s woocommerce_short_description filter. size: sm|default|lg. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'size' => Fields::field( 'select', __( 'Text size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
		),
		Product::field(),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-product-excerpt' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product short description', 'brik-builder' ) );
		}
		$text = Product::with(
			$product,
			static function ( $product ) {
				return apply_filters( 'woocommerce_short_description', $product->get_short_description() );
			}
		);
		if ( '' === trim( wp_strip_all_tags( (string) $text ) ) ) {
			return $ctx->placeholder( __( 'This product has no short description', 'brik-builder' ) );
		}
		$sizes = array(
			'sm'      => 'text-sm',
			'default' => 'text-base',
			'lg'      => 'text-lg',
		);
		$size  = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['default'];
		return '<div class="brik-product-excerpt woocommerce-product-details__short-description brik-prose leading-relaxed text-muted-foreground ' . esc_attr( $size ) . '">' . wp_kses_post( $text ) . '</div>';
	},
);
