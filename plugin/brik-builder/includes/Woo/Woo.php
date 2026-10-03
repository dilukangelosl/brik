<?php
namespace Brik\Woo;

use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce integration. Only loaded when WooCommerce is active.
 */
final class Woo {

	public static function init() {
		Conditions::init();
		Tags::init();
		Cart::init();

		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts' ), 6 );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'variation_data' ), 10, 3 );

		do_action( 'brik/woo/loaded' );
	}

	/**
	 * Whether the request is a WooCommerce page: product, shop, product taxonomy, cart, checkout, account.
	 */
	public static function is_shop_request() {
		return is_woocommerce() || is_cart() || is_checkout() || is_account_page();
	}

	/**
	 * When a Brik body template takes over a shop page, wrap it in a template that still runs
	 * the hooks WooCommerce and its extensions expect around the product (notices, structured
	 * data). Runs after the theme builder (priority 99) and WooCommerce's own loader.
	 */
	public static function template_include( $template ) {
		$brik = array(
			BRIK_DIR . 'templates/body.php'      => 'body.php',
			BRIK_DIR . 'templates/full-page.php' => 'full-page.php',
		);
		if ( ! isset( $brik[ $template ] ) || ! ThemeBuilder::resolve( 'body' ) || ! self::is_shop_request() ) {
			return $template;
		}
		return __DIR__ . '/templates/' . $brik[ $template ];
	}

	public static function body_class( $classes ) {
		if ( ! is_admin() && self::is_shop_request() && ThemeBuilder::resolve( 'body' ) ) {
			$classes[] = 'brik-woo-template';
		}
		return $classes;
	}

	/**
	 * Printed before the body location of a shop page rendered by a Brik template.
	 */
	public static function before_body() {
		if ( is_product() ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
				// The "product" class is left out on purpose: WooCommerce's stylesheet lays out
				// div.product (floats, widths) for its own template, which would fight the layout.
				$classes = array_diff( wc_get_product_class( 'brik-woo-product', $product ), array( 'product' ) );
				printf( '<div id="product-%d" class="%s">', (int) $product->get_id(), esc_attr( implode( ' ', $classes ) ) );
				echo '<div class="brik brik-woo-page-notices">';
				do_action( 'woocommerce_before_single_product' );
				echo '</div>';
				if ( isset( WC()->structured_data ) && ! post_password_required() ) {
					WC()->structured_data->generate_product_data( $product );
				}
				return;
			}
		}
		if ( is_woocommerce() ) {
			echo '<div class="brik brik-woo-page-notices">';
			woocommerce_output_all_notices();
			echo '</div>';
		}
	}

	public static function after_body() {
		if ( is_product() && isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product ) {
			do_action( 'woocommerce_after_single_product' );
			echo '</div>';
		}
	}

	public static function scripts() {
		if ( ! wp_script_is( 'brik', 'registered' ) ) {
			return;
		}
		$data = array(
			'ajax'     => \WC_AJAX::get_endpoint( Cart::ENDPOINT ),
			'cart'     => wc_get_cart_url(),
			'checkout' => wc_get_checkout_url(),
			'i18n'     => array(
				'added'    => __( 'Added to cart', 'brik-builder' ),
				'viewCart' => __( 'View cart', 'brik-builder' ),
				'checkout' => __( 'Checkout', 'brik-builder' ),
				'error'    => __( 'Could not add to cart', 'brik-builder' ),
				'failed'   => __( 'Something went wrong. Please try again.', 'brik-builder' ),
				'choose'   => __( 'Please choose product options before adding to cart.', 'brik-builder' ),
				'unavail'  => __( 'Sorry, this combination is unavailable. Please choose another.', 'brik-builder' ),
				'close'    => __( 'Dismiss', 'brik-builder' ),
				'adding'   => __( 'Adding…', 'brik-builder' ),
				'sale'     => __( 'Sale', 'brik-builder' ),
			),
		);
		wp_add_inline_script( 'brik', 'window.brikWooProduct=' . wp_json_encode( $data ) . ';', 'before' );
	}

	/**
	 * Extra variation data for the price, badge and stock modules, which update live when a
	 * variation is chosen.
	 */
	public static function variation_data( $data, $product, $variation ) {
		if ( ! $variation instanceof \WC_Product ) {
			return $data;
		}
		$stock                   = Product::stock( $variation );
		$data['brik_price_html'] = $variation->get_price_html();
		$data['brik_percent']    = Product::sale_percent( $variation );
		$data['brik_sale_label'] = $variation->is_on_sale() ? Product::sale_label( $variation ) : '';
		$data['brik_stock']      = $stock;
		return $data;
	}
}
