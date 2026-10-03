<?php
namespace Brik\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Add to cart without a page reload, and "Buy now".
 *
 * The add to cart form is posted unchanged to ?wc-ajax=brik_add_to_cart. WooCommerce's own
 * form handler adds the item on wp_loaded (so validation filters and plugin fields behave
 * exactly as on a normal submit); this endpoint only reports the outcome and the refreshed
 * cart fragments.
 */
final class Cart {

	const ENDPOINT = 'brik_product_atc';

	/** Whether an item was added during this request. */
	private static $added = false;

	public static function init() {
		add_action( 'wc_ajax_' . self::ENDPOINT, array( __CLASS__, 'respond' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'mark_added' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'buy_now_request' ), 19 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect' ), 999, 2 );

		if ( self::is_endpoint() ) {
			// Stay on this request instead of following the shop's "redirect to cart" setting.
			add_filter( 'pre_option_woocommerce_cart_redirect_after_add', array( __CLASS__, 'no' ) );
		}
	}

	public static function no() {
		return 'no';
	}

	private static function is_endpoint() {
		return isset( $_GET['wc-ajax'] ) && self::ENDPOINT === sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function mark_added() {
		self::$added = true;
	}

	/**
	 * A "Buy now" button carries the product id in brik_buy_now. Simple products send their id
	 * on the add to cart button, which isn't the one pressed, so copy it over.
	 */
	public static function buy_now_request() {
		// phpcs:disable WordPress.Security.NonceVerification -- mirrors WooCommerce's add to cart form, which has no nonce.
		if ( empty( $_REQUEST['brik_buy_now'] ) || isset( $_REQUEST['add-to-cart'] ) ) {
			return;
		}
		$id = absint( wp_unslash( $_REQUEST['brik_buy_now'] ) );
		if ( $id ) {
			$_REQUEST['add-to-cart'] = $id;
			$_POST['add-to-cart']    = $id;
		}
		// phpcs:enable
	}

	public static function redirect( $url, $product = null ) {
		if ( self::is_endpoint() ) {
			return false;
		}
		if ( ! empty( $_REQUEST['brik_buy_now'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return wc_get_checkout_url();
		}
		return $url;
	}

	/**
	 * Mini cart and other fragments, as WooCommerce sends them after an AJAX add to cart.
	 */
	public static function fragments() {
		ob_start();
		woocommerce_mini_cart();
		$mini = ob_get_clean();
		return apply_filters(
			'woocommerce_add_to_cart_fragments',
			array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>',
			)
		);
	}

	public static function respond() {
		$notices = wc_get_notices();
		wc_clear_notices();

		$errors = array();
		foreach ( isset( $notices['error'] ) ? $notices['error'] : array() as $notice ) {
			$errors[] = wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- see buy_now_request().
		$id       = isset( $_REQUEST['add-to-cart'] ) ? absint( wp_unslash( $_REQUEST['add-to-cart'] ) ) : 0;
		$buy_now  = ! empty( $_REQUEST['brik_buy_now'] );
		$quantity = 0;
		if ( isset( $_REQUEST['quantity'] ) ) {
			$raw      = wp_unslash( $_REQUEST['quantity'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- summed as numbers below.
			$quantity = is_array( $raw ) ? array_sum( array_map( 'wc_stock_amount', $raw ) ) : wc_stock_amount( $raw );
		}
		// phpcs:enable

		$product = $id ? wc_get_product( $id ) : null;
		if ( ! self::$added || $errors ) {
			if ( ! $errors ) {
				$errors[] = __( 'This product could not be added to your cart.', 'brik-builder' );
			}
			wp_send_json(
				array(
					'success' => false,
					'errors'  => $errors,
				)
			);
		}

		$name    = $product ? $product->get_name() : '';
		$message = $quantity > 1
			/* translators: 1: quantity, 2: product name */
			? sprintf( __( '%1$d × “%2$s” added to your cart.', 'brik-builder' ), $quantity, $name )
			/* translators: %s: product name */
			: sprintf( __( '“%s” added to your cart.', 'brik-builder' ), $name );

		$image = '';
		if ( $product && $product->get_image_id() ) {
			$image = (string) wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_gallery_thumbnail' );
		}

		wp_send_json(
			array(
				'success'   => true,
				'message'   => $message,
				'image'     => $image,
				'count'     => WC()->cart->get_cart_contents_count(),
				'subtotal'  => html_entity_decode( wp_strip_all_tags( WC()->cart->get_cart_subtotal() ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'fragments' => self::fragments(),
				'cart_hash' => WC()->cart->get_cart_hash(),
				'redirect'  => $buy_now ? wc_get_checkout_url() : '',
			)
		);
	}
}
