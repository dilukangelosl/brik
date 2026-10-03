<?php
namespace Brik\Woo;

use Brik\Dynamic;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic tags for products, the cart and shop pages:
 * {product:price}, {product:attribute:pa_color}, {cart:count}, {checkout:url} …
 */
final class Tags {

	public static function init() {
		add_filter( 'brik/dynamic_tags', array( __CLASS__, 'labels' ) );
		add_filter( 'brik/dynamic_value', array( __CLASS__, 'value' ), 10, 3 );
		add_filter( 'brik/attrs', array( __CLASS__, 'attribute_tags' ), 5, 3 );
	}

	public static function labels( $tags ) {
		return (array) $tags + array(
			'product:title'             => __( 'Product name', 'brik-builder' ),
			'product:price'             => __( 'Product price', 'brik-builder' ),
			'product:price_html|raw'    => __( 'Product price (HTML with sale price)', 'brik-builder' ),
			'product:regular_price'     => __( 'Regular price', 'brik-builder' ),
			'product:sale_price'        => __( 'Sale price', 'brik-builder' ),
			'product:on_sale_percent'   => __( 'Discount percent', 'brik-builder' ),
			'product:sku'               => __( 'SKU', 'brik-builder' ),
			'product:stock'             => __( 'Stock status', 'brik-builder' ),
			'product:stock_quantity'    => __( 'Stock quantity', 'brik-builder' ),
			'product:rating'            => __( 'Average rating', 'brik-builder' ),
			'product:review_count'      => __( 'Review count', 'brik-builder' ),
			'product:short_description' => __( 'Short description', 'brik-builder' ),
			'product:add_to_cart_url'   => __( 'Add to cart URL', 'brik-builder' ),
			'product:add_to_cart_text'  => __( 'Add to cart text', 'brik-builder' ),
			'product:permalink'         => __( 'Product URL', 'brik-builder' ),
			'product:image'             => __( 'Product image URL', 'brik-builder' ),
			'product:gallery_count'     => __( 'Gallery image count', 'brik-builder' ),
			'product:categories'        => __( 'Product categories', 'brik-builder' ),
			'product:tags'              => __( 'Product tags', 'brik-builder' ),
			'product:weight'            => __( 'Weight', 'brik-builder' ),
			'product:dimensions'        => __( 'Dimensions', 'brik-builder' ),
			'product:attribute:pa_KEY'  => __( 'Product attribute', 'brik-builder' ),
			'cart:count'                => __( 'Cart item count', 'brik-builder' ),
			'cart:total'                => __( 'Cart total', 'brik-builder' ),
			'cart:subtotal'             => __( 'Cart subtotal', 'brik-builder' ),
			'shop:url'                  => __( 'Shop URL', 'brik-builder' ),
			'cart:url'                  => __( 'Cart URL', 'brik-builder' ),
			'checkout:url'              => __( 'Checkout URL', 'brik-builder' ),
			'account:url'               => __( 'My account URL', 'brik-builder' ),
		);
	}

	/**
	 * The core tag pattern allows one colon, so {product:attribute:pa_color} would be left alone.
	 * Rewrite it to the dotted form before attributes are used.
	 */
	public static function attribute_tags( $attrs, $node, $renderer ) {
		$post_id = isset( $renderer->post_id ) ? (int) $renderer->post_id : 0;
		$replace = static function ( $text ) use ( $post_id ) {
			if ( ! is_string( $text ) || false === strpos( $text, '{product:attribute:' ) ) {
				return $text;
			}
			return preg_replace_callback(
				'/\{product:attribute:([A-Za-z0-9_\-]+)((?:\|[a-z_]+)?)\}/',
				static function ( $m ) use ( $post_id ) {
					$value = Dynamic::value( 'product:attribute.' . $m[1] . $m[2], $post_id );
					return null === $value ? $m[0] : $value;
				},
				$text
			);
		};
		foreach ( $attrs as $key => $value ) {
			if ( is_string( $value ) ) {
				$attrs[ $key ] = $replace( $value );
			} elseif ( is_array( $value ) && isset( $value['url'] ) && is_string( $value['url'] ) ) {
				$attrs[ $key ]['url'] = $replace( $value['url'] );
			}
		}
		return $attrs;
	}

	public static function value( $value, $tag, $post_id ) {
		if ( null !== $value || ! is_string( $tag ) ) {
			return $value;
		}
		$parts    = explode( '|', $tag, 2 );
		$name     = $parts[0];
		$modifier = isset( $parts[1] ) ? $parts[1] : '';

		switch ( $name ) {
			case 'shop:url':
				return esc_url( wc_get_page_permalink( 'shop' ) );
			case 'cart:url':
				return esc_url( wc_get_cart_url() );
			case 'checkout:url':
				return esc_url( wc_get_checkout_url() );
			case 'account:url':
				return esc_url( wc_get_page_permalink( 'myaccount' ) );
		}

		if ( 0 === strpos( $name, 'cart:' ) ) {
			return self::cart( substr( $name, 5 ), $modifier );
		}
		if ( 0 === strpos( $name, 'product:' ) ) {
			$product = Product::for_post( $post_id );
			return $product ? self::product( $product, substr( $name, 8 ), $modifier ) : '';
		}
		return $value;
	}

	private static function plain( $html ) {
		return esc_html( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	private static function cart( $key, $modifier ) {
		$cart = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;
		switch ( $key ) {
			case 'count':
				return (string) ( $cart ? (int) $cart->get_cart_contents_count() : 0 );
			case 'total':
				if ( 'raw' === $modifier ) {
					return $cart ? (string) wc_format_decimal( $cart->get_total( 'edit' ), wc_get_price_decimals() ) : '0';
				}
				return $cart ? self::plain( $cart->get_total() ) : self::plain( wc_price( 0 ) );
			case 'subtotal':
				if ( 'raw' === $modifier ) {
					return $cart ? (string) wc_format_decimal( $cart->get_subtotal(), wc_get_price_decimals() ) : '0';
				}
				return $cart ? self::plain( $cart->get_cart_subtotal() ) : self::plain( wc_price( 0 ) );
		}
		return null;
	}

	private static function product( \WC_Product $product, $key, $modifier ) {
		$raw = 'raw' === $modifier;

		if ( 0 === strpos( $key, 'attribute.' ) ) {
			// Custom attributes come back joined with WooCommerce's " | " delimiter.
			return esc_html( str_replace( ' ' . WC_DELIMITER . ' ', ', ', $product->get_attribute( substr( $key, 10 ) ) ) );
		}

		switch ( $key ) {
			case 'id':
				return (string) $product->get_id();
			case 'title':
			case 'name':
				return esc_html( $product->get_name() );
			case 'price':
				if ( $raw ) {
					return esc_html( (string) $product->get_price() );
				}
				return esc_html( Product::plain_current_price( $product ) );
			case 'price_html':
				return wp_kses_post( $product->get_price_html() );
			case 'regular_price':
				$amount = $product->is_type( 'variable' ) ? $product->get_variation_regular_price( 'min', true ) : $product->get_regular_price();
				return $raw ? esc_html( (string) $amount ) : esc_html( Product::plain_price( $amount ) );
			case 'sale_price':
				if ( ! $product->is_on_sale() ) {
					return '';
				}
				$amount = $product->is_type( 'variable' ) ? $product->get_variation_sale_price( 'min', true ) : $product->get_sale_price();
				return $raw ? esc_html( (string) $amount ) : esc_html( Product::plain_price( $amount ) );
			case 'on_sale_percent':
				$percent = Product::sale_percent( $product );
				return $percent ? (string) $percent : '';
			case 'sku':
				return esc_html( $product->get_sku() );
			case 'stock':
				return esc_html( Product::stock( $product )['text'] );
			case 'stock_quantity':
				$qty = $product->get_stock_quantity();
				return null === $qty ? '' : (string) (int) $qty;
			case 'rating':
				return $raw ? (string) (float) $product->get_average_rating() : esc_html( number_format_i18n( (float) $product->get_average_rating(), 1 ) );
			case 'review_count':
				return (string) (int) $product->get_review_count();
			case 'short_description':
				$text = apply_filters( 'woocommerce_short_description', $product->get_short_description() );
				return $raw ? wp_kses_post( $text ) : esc_html( brik_site_plain_text( $text ) );
			case 'add_to_cart_url':
				return esc_url( $product->add_to_cart_url() );
			case 'add_to_cart_text':
				return esc_html( $product->add_to_cart_text() );
			case 'permalink':
			case 'url':
				return esc_url( $product->get_permalink() );
			case 'image':
				$id = $product->get_image_id();
				return esc_url( $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : wc_placeholder_img_src( 'full' ) );
			case 'gallery_count':
				return (string) count( $product->get_gallery_image_ids() );
			case 'categories':
			case 'tags':
				$terms = get_the_terms( $product->get_id(), 'categories' === $key ? 'product_cat' : 'product_tag' );
				if ( ! $terms || is_wp_error( $terms ) ) {
					return '';
				}
				return esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
			case 'weight':
				return $product->has_weight() ? self::plain( wc_format_weight( $product->get_weight() ) ) : '';
			case 'dimensions':
				return $product->has_dimensions() ? self::plain( wc_format_dimensions( $product->get_dimensions( false ) ) ) : '';
		}
		return '';
	}
}
