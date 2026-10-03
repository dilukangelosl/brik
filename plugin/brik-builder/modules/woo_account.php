<?php
/**
 * My account: WooCommerce's account area with a vertical tab navigation, dashboard cards,
 * restyled orders, addresses and forms; login and registration when logged out.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'woo_account',
	'title'       => __( 'My account', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'circle-user-round',
	'description' => 'The WooCommerce My account area: vertical tab navigation (nav: vertical|horizontal), dashboard_cards (orders, addresses, account details), orders table, addresses, account forms, and the login/registration forms for visitors. Works with endpoints added by extensions.',
	'class'       => 'brik-woo',
	'fields'      => array(
		'nav'             => Fields::field( 'select', __( 'Navigation', 'brik-builder' ), 'content', array( 'default' => 'vertical', 'options' => Fields::opts( array( 'vertical' => __( 'Vertical tabs', 'brik-builder' ), 'horizontal' => __( 'Horizontal tabs', 'brik-builder' ) ) ) ) ),
		'dashboard_cards' => Fields::field( 'toggle', __( 'Dashboard cards', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'login_title'     => Fields::field( 'text', __( 'Login title', 'brik-builder' ), 'content', array( 'default' => __( 'Welcome back', 'brik-builder' ) ) ),
		'login_text'      => Fields::field( 'text', __( 'Login text', 'brik-builder' ), 'content', array( 'default' => __( 'Sign in to track orders and check out faster.', 'brik-builder' ) ) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		if ( brik_woo_is_static_render( $ctx ) ) {
			return brik_woo_shortcode( 'woocommerce_my_account', $ctx );
		}
		brik_woo_flag( 'account' );
		if ( ! brik_woo_ensure_cart() ) {
			return '';
		}
		$ctx->classes[] = 'horizontal' === $a['nav'] ? 'brik-woo--nav-h' : 'brik-woo--nav-v';

		$cards = brik_form_bool( $a['dashboard_cards'] );
		remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
		add_action( 'woocommerce_account_navigation', 'brik_woo_account_nav' );
		if ( $cards ) {
			add_action( 'woocommerce_account_dashboard', 'brik_woo_account_dashboard_cards' );
		}
		$html = brik_woo_shortcode( 'woocommerce_my_account', $ctx );
		remove_action( 'woocommerce_account_navigation', 'brik_woo_account_nav' );
		add_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation' );
		if ( $cards ) {
			remove_action( 'woocommerce_account_dashboard', 'brik_woo_account_dashboard_cards' );
		}

		$head = '';
		if ( ! is_user_logged_in() && ( '' !== trim( (string) $a['login_title'] ) || '' !== trim( (string) $a['login_text'] ) ) && ! isset( $_GET['reset-link-sent'] ) && ! isset( $_GET['show-reset-form'] ) && ! is_wc_endpoint_url( 'lost-password' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$head = '<div class="brik-acc-intro grid gap-2 text-center">'
				. ( '' !== trim( (string) $a['login_title'] ) ? '<h1 class="font-heading text-3xl font-semibold tracking-tight">' . brik_inline( $a['login_title'] ) . '</h1>' : '' )
				. ( '' !== trim( (string) $a['login_text'] ) ? '<p class="text-muted-foreground">' . brik_inline( $a['login_text'] ) . '</p>' : '' )
				. '</div>';
		}
		return '<div class="brik-acc grid gap-8">' . $head . $html . '</div>';
	},
);
