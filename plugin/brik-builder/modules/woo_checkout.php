<?php
/**
 * Checkout: WooCommerce's classic checkout restyled into a two-column layout with a sticky
 * order summary. WooCommerce's checkout script still drives it (order review updates,
 * validation, placing the order), so payment gateways and extensions keep working.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'woo_checkout',
	'title'       => __( 'Checkout', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'credit-card',
	'description' => 'The WooCommerce checkout (classic, driven by WooCommerce\'s checkout script so gateways and extensions work): two columns with a sticky order summary, numbered step headings, radio cards for shipping and payment methods. On the order-received endpoint it shows the thank-you screen (unless the page has a woo_order_received module). steps (bool), sticky_summary (bool), title.',
	'class'       => 'brik-woo',
	'fields'      => array(
		'title'          => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Checkout', 'brik-builder' ) ) ),
		'steps'          => Fields::field( 'toggle', __( 'Numbered steps', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'sticky_summary' => Fields::field( 'toggle', __( 'Sticky order summary', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'secure_note'    => Fields::field( 'text', __( 'Note under the order button', 'brik-builder' ), 'content', array( 'default' => __( 'Secure checkout. Your details are encrypted.', 'brik-builder' ) ) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		if ( brik_woo_is_static_render( $ctx ) ) {
			return brik_woo_shortcode( 'woocommerce_checkout', $ctx );
		}
		brik_woo_flag( 'checkout' );
		if ( ! brik_woo_ensure_cart() ) {
			return '';
		}

		if ( ! $ctx->canvas && is_wc_endpoint_url( 'order-received' ) ) {
			if ( brik_listing_nodes( $ctx->renderer->root, 'woo_order_received' ) ) {
				return '';
			}
			$html  = brik_woo_shortcode( 'woocommerce_checkout', $ctx );
			$order = brik_woo_received_order();
			if ( $order && false !== strpos( $html, 'woocommerce-order-overview' ) ) {
				$html = brik_woo_received_header( $order ) . '<div class="brik-or-details">' . $html . '</div>';
			}
			$ctx->classes[] = 'brik-woo--received';
			return '<div class="brik-or mx-auto grid max-w-4xl gap-10">' . $html . '</div>';
		}

		$ctx->classes[] = brik_form_bool( $a['steps'] ) ? 'brik-woo--steps' : '';
		$ctx->classes[] = brik_form_bool( $a['sticky_summary'] ) ? 'brik-woo--sticky' : '';

		$note = static function () use ( $a ) {
			if ( '' !== trim( (string) $a['secure_note'] ) ) {
				echo '<p class="brik-co-secure">' . brik_icon( 'lock', 'size-3.5' ) . '<span>' . esc_html( $a['secure_note'] ) . '</span></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
		};
		$marker  = static function () {
			echo '<input type="hidden" name="brik_checkout" value="1">';
		};
		$summary = static function () {
			echo '<h3 class="brik-co-summary-title">' . esc_html__( 'Order summary', 'brik-builder' ) . '</h3>';
		};
		$payment = static function () use ( $a ) {
			if ( brik_form_bool( $a['steps'] ) && WC()->cart->needs_payment() ) {
				echo '<h3 class="brik-co-step">' . esc_html__( 'Payment', 'brik-builder' ) . '</h3>';
			}
		};
		$hooks = array(
			array( 'woocommerce_review_order_after_submit', $note, 10 ),
			array( 'woocommerce_checkout_before_customer_details', $marker, 10 ),
			array( 'woocommerce_checkout_order_review', $summary, 5 ),
			array( 'woocommerce_checkout_order_review', $payment, 15 ),
		);
		foreach ( $hooks as $hook ) {
			add_action( $hook[0], $hook[1], $hook[2] );
		}
		$html = brik_woo_shortcode( 'woocommerce_checkout', $ctx );
		foreach ( $hooks as $hook ) {
			remove_action( $hook[0], $hook[1], $hook[2] );
		}

		$title = '' !== trim( (string) $a['title'] ) && ! WC()->cart->is_empty() ? '<h1 class="brik-co-title font-heading text-3xl font-semibold tracking-tight">' . brik_inline( $a['title'] ) . '</h1>' : '';
		return '<div class="brik-co grid gap-8">' . $title . $html . '</div>';
	},
);
