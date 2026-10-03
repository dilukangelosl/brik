<?php
/**
 * Cart: WooCommerce's cart (so coupons, shipping calculator and extensions keep working)
 * restyled, with an empty state that suggests products.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'woo_cart',
	'title'       => __( 'Cart', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'shopping-cart',
	'description' => 'The WooCommerce cart page (classic cart, so coupons, the shipping calculator and extensions keep working): line items with quantity steppers, coupon field, totals card and checkout button. cross_sells: product cards below the cart. Empty cart: empty_title, empty_text, "Return to shop" and suggestions (featured|best_selling|newest|sale|none) with suggestions_count. sticky_totals keeps the totals in view.',
	'class'       => 'brik-woo',
	'fields'      => array(
		'title'             => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Shopping cart', 'brik-builder' ) ) ),
		'sticky_totals'     => Fields::field( 'toggle', __( 'Sticky totals', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'cross_sells'       => Fields::field( 'toggle', __( 'Cross-sells below the cart', 'brik-builder' ), 'content', array( 'default' => true ) ),
		'empty_title'       => Fields::field( 'text', __( 'Empty cart title', 'brik-builder' ), 'empty', array( 'default' => __( 'Your cart is empty', 'brik-builder' ), 'group_label' => __( 'Empty cart', 'brik-builder' ) ) ),
		'empty_text'        => Fields::field( 'textarea', __( 'Empty cart text', 'brik-builder' ), 'empty', array( 'default' => __( 'Nothing here yet. Have a look around the shop and find something you love.', 'brik-builder' ), 'group_label' => __( 'Empty cart', 'brik-builder' ) ) ),
		'suggestions'       => Fields::field( 'select', __( 'Suggestions', 'brik-builder' ), 'empty', array( 'default' => 'best_selling', 'group_label' => __( 'Empty cart', 'brik-builder' ), 'options' => Fields::opts( array( 'best_selling' => __( 'Best selling', 'brik-builder' ), 'featured' => __( 'Featured', 'brik-builder' ), 'newest' => __( 'Newest', 'brik-builder' ), 'sale' => __( 'On sale', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
		'suggestions_count' => Fields::field( 'number', __( 'Number of suggestions', 'brik-builder' ), 'empty', array( 'default' => 4, 'min' => 1, 'max' => 12, 'group_label' => __( 'Empty cart', 'brik-builder' ), 'show_if' => array( 'suggestions' => array( 'best_selling', 'featured', 'newest', 'sale' ) ) ) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		if ( brik_woo_is_static_render( $ctx ) ) {
			return brik_woo_shortcode( 'woocommerce_cart', $ctx );
		}
		brik_woo_flag( 'cart' );
		if ( ! brik_woo_ensure_cart() ) {
			return '';
		}
		if ( brik_form_bool( $a['sticky_totals'] ) ) {
			$ctx->classes[] = 'brik-woo--sticky';
		}

		$grid = static function ( $source, $count, $heading ) {
			list( $query ) = brik_woo_products_query(
				array(
					'source'   => $source,
					'per_page' => $count,
				),
				array()
			);
			$cards = '';
			foreach ( $query->posts as $i => $post ) {
				$product = wc_get_product( $post );
				if ( $product ) {
					$cards .= '<div class="brik-listing-item">' . brik_woo_product_card( $product, array( 'index' => $i + 4 ) ) . '</div>';
				}
			}
			return '' !== $cards ? '<section class="brik-cart-more grid gap-6"><h2 class="font-heading text-xl font-semibold tracking-tight">' . esc_html( $heading ) . '</h2><div class="brik-listing-items brik-listing-items--grid is-equal" style="--brik-listing-cols:4;--brik-listing-cols-t:3;--brik-listing-cols-m:2">' . $cards . '</div></section>' : '';
		};

		$empty = static function () use ( $a, $grid ) {
			$shop    = wc_get_page_permalink( 'shop' );
			$suggest = 'none' !== $a['suggestions'] ? $grid( (string) $a['suggestions'], min( 12, max( 1, (int) $a['suggestions_count'] ) ), __( 'Popular right now', 'brik-builder' ) ) : '';
			// .wc-empty-cart-message lets WooCommerce's cart script swap this in after the last item is removed.
			echo '<div class="brik-cart-empty wc-empty-cart-message grid gap-16">' // phpcs:ignore WordPress.Security.EscapeOutput
				. '<div class="cart-empty flex flex-col items-center gap-4 rounded-2xl border border-dashed px-6 py-16 text-center">'
				. '<span class="grid size-16 place-items-center rounded-full bg-muted text-muted-foreground">' . brik_icon( 'shopping-cart', 'size-7' ) . '</span>'
				. '<h2 class="font-heading text-2xl font-semibold tracking-tight">' . esc_html( $a['empty_title'] ) . '</h2>'
				. '<p class="max-w-sm text-muted-foreground text-pretty">' . esc_html( $a['empty_text'] ) . '</p>'
				. '<a class="' . esc_attr( brik_button_class( 'default', 'lg', 'mt-2' ) ) . '" href="' . esc_url( $shop ? $shop : home_url( '/' ) ) . '">' . brik_icon( 'arrow-left' ) . esc_html__( 'Return to shop', 'brik-builder' ) . '</a>'
				. '</div>' . $suggest . '</div>';
		};

		remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
		remove_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		add_action( 'woocommerce_cart_is_empty', $empty, 10 );
		$html = brik_woo_shortcode( 'woocommerce_cart', $ctx );
		remove_action( 'woocommerce_cart_is_empty', $empty, 10 );
		add_action( 'woocommerce_cart_is_empty', 'wc_empty_cart_message', 10 );
		add_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

		$title = '' !== trim( (string) $a['title'] ) && ! WC()->cart->is_empty()
			/* translators: %d: number of items */
			? '<div class="brik-cart-title flex items-baseline gap-3"><h1 class="font-heading text-3xl font-semibold tracking-tight">' . brik_inline( $a['title'] ) . '</h1><span class="text-sm text-muted-foreground">' . esc_html( sprintf( _n( '%d item', '%d items', WC()->cart->get_cart_contents_count(), 'brik-builder' ), WC()->cart->get_cart_contents_count() ) ) . '</span></div>'
			: '';

		$more = '';
		if ( brik_form_bool( $a['cross_sells'] ) && ! WC()->cart->is_empty() && WC()->cart->get_cross_sells() ) {
			$more = $grid( 'cross_sells', 4, __( 'You may also like', 'brik-builder' ) );
		}
		return '<div class="brik-cart grid gap-8">' . $title . $html . $more . '</div>';
	},
);
