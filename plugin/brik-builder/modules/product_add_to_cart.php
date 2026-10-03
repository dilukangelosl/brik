<?php
/**
 * Add to cart: WooCommerce's own form (simple, variable, grouped, external) restyled, with
 * variation pills and color swatches, a quantity stepper, AJAX add to cart, "Buy now" and a
 * sticky bar on phones.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Woo\AddToCart;
use Brik\Woo\Product;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'product_add_to_cart',
	'title'       => __( 'Add to cart', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'shopping-bag',
	'description' => 'Add to cart form of the current product using WooCommerce\'s own templates (simple, variable, grouped, external; extensions hooked into the form keep working). selector_style: auto (color swatches for color attributes, pills otherwise)|pills|swatches|dropdown. quantity: bool. button_text: overrides the button label. button_size: default|lg|xl. full_width: bool. ajax: bool (add without reload, shows a toast and refreshes cart fragments). buy_now: bool adds a "Buy now" button that goes straight to checkout (buy_now_text). show_stock: bool. sticky_mobile: bool, sticky add to cart bar on phones once the form scrolls away. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'selector_style' => Fields::field( 'select', __( 'Variation selectors', 'brik-builder' ), 'content', array( 'default' => 'auto', 'options' => Fields::opts( array( 'auto' => __( 'Auto (swatches for colors, pills otherwise)', 'brik-builder' ), 'pills' => __( 'Pills', 'brik-builder' ), 'swatches' => __( 'Color swatches', 'brik-builder' ), 'dropdown' => __( 'Dropdowns', 'brik-builder' ) ) ) ) ),
			'quantity'       => Fields::field( 'toggle', __( 'Quantity', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'button_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Add to cart', 'brik-builder' ) ) ),
			'button_size'    => Fields::field( 'select', __( 'Button size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'full_width'     => Fields::field( 'toggle', __( 'Full width button', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'ajax'           => Fields::field( 'toggle', __( 'Add to cart without reloading', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'buy_now'        => Fields::field( 'toggle', __( '"Buy now" button', 'brik-builder' ), 'content' ),
			'buy_now_text'   => Fields::field( 'text', __( '"Buy now" text', 'brik-builder' ), 'content', array( 'default' => __( 'Buy now', 'brik-builder' ), 'show_if' => array( 'buy_now' => true ) ) ),
			'show_stock'     => Fields::field( 'toggle', __( 'Stock message', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'sticky_mobile'  => Fields::field( 'toggle', __( 'Sticky bar on phones', 'brik-builder' ), 'content', array( 'description' => __( 'Shows a compact add to cart bar at the bottom of the screen once the form scrolls out of view.', 'brik-builder' ) ) ),
		),
		Product::field(),
		array(
			// WooCommerce's stylesheet styles these buttons with high specificity, so the
			// design fields set variables the module stylesheet reads.
			'button_bg'        => Fields::field( 'color', __( 'Background', 'brik-builder' ), 'button', array( 'tab' => 'design', 'group_label' => __( 'Button', 'brik-builder' ), 'css' => array( Fields::WRAP, '--brik-atc-bg' ) ) ),
			'button_color'     => Fields::field( 'color', __( 'Text color', 'brik-builder' ), 'button', array( 'tab' => 'design', 'group_label' => __( 'Button', 'brik-builder' ), 'css' => array( Fields::WRAP, '--brik-atc-fg' ) ) ),
			'button_radius'    => Fields::field( 'unit', __( 'Corner radius', 'brik-builder' ), 'button', array( 'tab' => 'design', 'group_label' => __( 'Button', 'brik-builder' ), 'css' => array( Fields::WRAP, '--brik-atc-radius' ) ) ),
			'option_radius'    => Fields::field( 'unit', __( 'Corner radius', 'brik-builder' ), 'options', array( 'tab' => 'design', 'group_label' => __( 'Variation options', 'brik-builder' ), 'css' => array( Fields::WRAP, '--brik-var-radius' ) ) ),
			'option_active_bg' => Fields::field( 'color', __( 'Selected background', 'brik-builder' ), 'options', array( 'tab' => 'design', 'group_label' => __( 'Variation options', 'brik-builder' ), 'css' => array( Fields::WRAP, '--brik-var-active' ) ) ),
		)
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Add to cart', 'brik-builder' ) );
		}

		$a['show_stock'] = ! empty( $a['show_stock'] );
		$form            = AddToCart::render( $product, $a );
		if ( '' === trim( $form ) ) {
			return $ctx->placeholder( __( 'This product can’t be purchased right now', 'brik-builder' ) );
		}

		$type  = $product->get_type();
		$style = in_array( $a['selector_style'], array( 'auto', 'pills', 'swatches', 'dropdown' ), true ) ? $a['selector_style'] : 'auto';
		$size  = in_array( $a['button_size'], array( 'default', 'lg', 'xl' ), true ) ? $a['button_size'] : 'lg';
		$ajax  = ! empty( $a['ajax'] ) && ! $product->is_type( 'external' );

		$attrs = array(
			'class'             => brik_cls(
				'brik-atc',
				'brik-atc--' . sanitize_html_class( $type ),
				'brik-atc--' . $style,
				'brik-atc--btn-' . $size,
				array(
					'brik-atc--no-qty' => empty( $a['quantity'] ),
					'brik-atc--full'   => ! empty( $a['full_width'] ),
					'brik-atc--buy'    => ! empty( $a['buy_now'] ),
				)
			),
			'data-brik-atc'     => $product->get_id(),
			'data-brik-product' => $product->get_id(),
			'data-ajax'         => $ajax ? '1' : '0',
		);
		$html  = '<div' . brik_attrs( $attrs ) . '>' . $form . '</div>';

		$sticky = ! empty( $a['sticky_mobile'] ) && ! $ctx->canvas && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( array( 'external', 'grouped' ) );
		if ( $sticky ) {
			$thumb  = $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_gallery_thumbnail', false, array( 'class' => 'size-11 shrink-0 rounded-md border bg-muted object-cover', 'loading' => 'lazy' ) ) : '';
			$label  = $product->is_type( 'variable' ) ? __( 'Choose options', 'brik-builder' ) : $product->single_add_to_cart_text();
			$html  .= '<div class="brik-atc-sticky" data-brik-atc-sticky="' . (int) $product->get_id() . '" hidden>'
				. '<div class="brik-atc-sticky-inner">' . $thumb
				. '<div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">' . esc_html( $product->get_name() ) . '</p>'
				. '<div class="brik-price brik-atc-sticky-price text-sm" data-brik-price="' . (int) $product->get_id() . '"><p class="brik-price-amount price">' . wp_kses_post( $product->get_price_html() ) . '</p></div></div>'
				. '<button type="button" class="' . esc_attr( brik_button_class( 'default', 'default', 'brik-atc-sticky-button shrink-0' ) ) . '" data-label-ready="' . esc_attr( $product->single_add_to_cart_text() ) . '">' . brik_icon( 'shopping-bag', 'size-4' ) . '<span>' . esc_html( $label ) . '</span></button>'
				. '</div></div>';
		}
		return $html;
	},
);
