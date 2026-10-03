<?php
/**
 * Product price with the regular price struck through, an optional discount badge, and live
 * updates when a variation is chosen.
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
	'type'        => 'product_price',
	'title'       => __( 'Product price', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'badge-dollar-sign',
	'description' => 'Price of the current product. Sale prices show the regular price struck through. size: sm|default|lg|xl. badge: bool, adds a "−20%" discount badge. badge_format: percent|text. live: bool (default true), follows the chosen variation on variable products. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'size'         => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'badge'        => Fields::field( 'toggle', __( 'Discount badge', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'badge_format' => Fields::field( 'select', __( 'Badge text', 'brik-builder' ), 'content', array( 'default' => 'percent', 'options' => Fields::opts( array( 'percent' => __( 'Percentage (−20%)', 'brik-builder' ), 'text' => __( '"Sale"', 'brik-builder' ) ) ), 'show_if' => array( 'badge' => true ) ) ),
			'live'         => Fields::field( 'toggle', __( 'Update when a variation is chosen', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-price', 'map' => array( 'left' => 'justify-content:flex-start', 'center' => 'justify-content:center', 'right' => 'justify-content:flex-end' ) ) ) ),
		),
		Product::field(),
		Fields::typography( 'price', __( 'Price', 'brik-builder' ), Fields::WRAP . ' .brik-price-amount ins, ' . Fields::WRAP . ' .brik-price-amount > .amount' ),
		Fields::typography( 'regular', __( 'Regular price', 'brik-builder' ), Fields::WRAP . ' .brik-price-amount del' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product price', 'brik-builder' ) );
		}
		$html = $product->get_price_html();
		if ( '' === trim( $html ) ) {
			return $ctx->placeholder( __( 'This product has no price', 'brik-builder' ) );
		}

		$sizes = array(
			'sm'      => 'text-sm',
			'default' => 'text-base',
			'lg'      => 'text-2xl',
			'xl'      => 'text-3xl md:text-4xl',
		);
		$size  = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['lg'];

		$badge = '';
		if ( ! empty( $a['badge'] ) ) {
			$label = $product->is_on_sale() ? Product::sale_label( $product, $a['badge_format'] ) : '';
			$badge = '<span class="' . esc_attr( brik_badge_class( 'destructive', 'default', 'brik-price-badge self-center' ) ) . '"' . ( '' === $label ? ' hidden' : '' ) . ' data-format="' . esc_attr( 'text' === $a['badge_format'] ? 'text' : 'percent' ) . '">' . esc_html( $label ) . '</span>';
		}

		$attrs = array(
			'class' => brik_cls( 'brik-price flex flex-wrap items-center gap-x-3 gap-y-1', $size ),
		);
		if ( ! empty( $a['live'] ) ) {
			$attrs['data-brik-price'] = $product->get_id();
		}
		return '<div' . brik_attrs( $attrs ) . '><p class="brik-price-amount price">' . wp_kses_post( $html ) . '</p>' . $badge . '</div>';
	},
);
