<?php
/**
 * Order received: the thank-you screen for the order on the order-received endpoint.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'woo_order_received',
	'title'       => __( 'Order received', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'circle-check-big',
	'description' => 'Thank-you summary on the checkout\'s order-received endpoint: success header with order number, date, total and payment method, then WooCommerce\'s order details (payment instructions, items, addresses). Shows nothing elsewhere; in the builder it previews your latest order. show_details (bool).',
	'class'       => 'brik-woo',
	'fields'      => array(
		'show_details' => Fields::field( 'toggle', __( 'Order details', 'brik-builder' ), 'content', array( 'default' => true ) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$order = null;
		if ( ! $ctx->canvas && is_wc_endpoint_url( 'order-received' ) ) {
			$order = brik_woo_received_order();
		} elseif ( $ctx->canvas && current_user_can( 'edit_shop_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'limit'  => 1,
					'type'   => 'shop_order',
					'status' => array_merge( wc_get_is_paid_statuses(), array( 'on-hold' ) ),
				)
			);
			$order  = $orders ? $orders[0] : null;
		}
		if ( ! $order ) {
			return $ctx->placeholder( __( 'The thank-you screen appears here after an order is placed.', 'brik-builder' ) );
		}
		brik_woo_flag( 'screen' );
		$ctx->classes[] = 'brik-woo--received';
		return '<div class="brik-or mx-auto grid max-w-4xl gap-10">' . brik_woo_received_html( $order, brik_form_bool( $a['show_details'] ) ) . '</div>';
	},
);
