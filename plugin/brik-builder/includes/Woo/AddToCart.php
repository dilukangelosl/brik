<?php
namespace Brik\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Renders WooCommerce's own add to cart templates (so payment buttons, product add-ons and
 * other extensions hooked into the form keep working) and adds Brik's controls through the
 * hooks and filters those templates already offer.
 */
final class AddToCart {

	/** Attributes of the module currently rendering, or null outside of it. */
	private static $a = null;

	private static $product = null;

	/**
	 * Add to cart form markup for a product.
	 */
	public static function render( \WC_Product $product, array $a ) {
		self::$a       = $a;
		self::$product = $product;
		self::hook();
		try {
			$html = Product::capture(
				$product,
				static function () {
					woocommerce_template_single_add_to_cart();
				}
			);
		} finally {
			self::unhook();
			self::$a       = null;
			self::$product = null;
		}
		return $html;
	}

	private static function hook() {
		add_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'selectors' ), 20, 2 );
		add_filter( 'woocommerce_reset_variations_link', array( __CLASS__, 'reset_link' ), 20 );
		add_action( 'woocommerce_before_quantity_input_field', array( __CLASS__, 'qty_minus' ) );
		add_action( 'woocommerce_after_quantity_input_field', array( __CLASS__, 'qty_plus' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'buy_now' ) );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'button_text' ), 20, 2 );
		if ( empty( self::$a['show_stock'] ) ) {
			add_filter( 'woocommerce_get_stock_html', '__return_empty_string', 99 );
		}
	}

	private static function unhook() {
		remove_filter( 'woocommerce_dropdown_variation_attribute_options_html', array( __CLASS__, 'selectors' ), 20 );
		remove_filter( 'woocommerce_reset_variations_link', array( __CLASS__, 'reset_link' ), 20 );
		remove_action( 'woocommerce_before_quantity_input_field', array( __CLASS__, 'qty_minus' ) );
		remove_action( 'woocommerce_after_quantity_input_field', array( __CLASS__, 'qty_plus' ) );
		remove_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'buy_now' ) );
		remove_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'button_text' ), 20 );
		remove_filter( 'woocommerce_get_stock_html', '__return_empty_string', 99 );
	}

	public static function button_text( $text, $product = null ) {
		$custom = isset( self::$a['button_text'] ) ? trim( wp_strip_all_tags( (string) self::$a['button_text'] ) ) : '';
		if ( '' === $custom || ( $product instanceof \WC_Product && $product->is_type( 'external' ) ) ) {
			return $text;
		}
		return $custom;
	}

	public static function qty_minus() {
		echo '<button type="button" class="brik-qty-btn brik-qty-minus" data-brik-qty="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'brik-builder' ) . '">' . brik_icon( 'minus', 'size-4' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup.
	}

	public static function qty_plus() {
		echo '<button type="button" class="brik-qty-btn brik-qty-plus" data-brik-qty="1" aria-label="' . esc_attr__( 'Increase quantity', 'brik-builder' ) . '">' . brik_icon( 'plus', 'size-4' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup.
	}

	public static function buy_now() {
		$product = self::$product;
		if ( empty( self::$a['buy_now'] ) || ! $product || $product->is_type( 'external' ) ) {
			return;
		}
		// Grouped children render their own forms through the same hook; only the parent gets the button.
		if ( isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof \WC_Product && $GLOBALS['product']->get_id() !== $product->get_id() ) {
			return;
		}
		$text = trim( wp_strip_all_tags( (string) self::$a['buy_now_text'] ) );
		printf(
			'<button type="submit" name="brik_buy_now" value="%1$d" class="brik-buy-now button">%2$s<span>%3$s</span></button>',
			(int) $product->get_id(),
			brik_icon( 'zap', 'size-4' ), // phpcs:ignore WordPress.Security.EscapeOutput -- icon markup.
			esc_html( '' !== $text ? $text : __( 'Buy now', 'brik-builder' ) )
		);
	}

	public static function reset_link( $html ) {
		return '<a class="reset_variations brik-var-reset" href="#" aria-label="' . esc_attr__( 'Clear options', 'brik-builder' ) . '">' . brik_icon( 'rotate-ccw', 'size-3.5' ) . '<span>' . esc_html__( 'Clear', 'brik-builder' ) . '</span></a>';
	}

	/**
	 * Pills or color swatches next to WooCommerce's attribute select. The select stays in the
	 * form (hidden) and remains the source of truth for the variation script.
	 */
	public static function selectors( $html, $args ) {
		$style = isset( self::$a['selector_style'] ) ? self::$a['selector_style'] : 'auto';
		if ( null === self::$a || 'dropdown' === $style ) {
			return '<div class="brik-var-field brik-var-field--select">' . $html . brik_icon( 'chevron-down', 'brik-var-caret size-4' ) . '</div>';
		}

		$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
		$product   = isset( $args['product'] ) && $args['product'] instanceof \WC_Product ? $args['product'] : null;
		$options   = isset( $args['options'] ) ? (array) $args['options'] : array();
		if ( ! $options && $product && $attribute ) {
			$all     = $product->get_variation_attributes();
			$options = isset( $all[ $attribute ] ) ? $all[ $attribute ] : array();
		}

		$items = array();
		if ( $product && taxonomy_exists( $attribute ) ) {
			foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
				if ( in_array( $term->slug, $options, true ) ) {
					$items[] = array( $term->slug, apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product ) );
				}
			}
		} else {
			foreach ( $options as $option ) {
				$items[] = array( $option, apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product ) );
			}
		}
		if ( ! $items ) {
			return $html;
		}

		$swatches = 'swatches' === $style || ( 'auto' === $style && Product::is_color_attribute( $attribute ) );
		$buttons  = '';
		foreach ( $items as $item ) {
			list( $value, $label ) = $item;
			$label                 = wp_strip_all_tags( (string) $label );
			$color                 = $swatches ? Product::swatch_color( $attribute, $value, $label ) : '';
			if ( $color ) {
				$buttons .= sprintf(
					'<button type="button" class="brik-var-option brik-var-swatch" data-value="%1$s" aria-pressed="false" title="%2$s"><span class="brik-var-dot" style="--brik-swatch:%3$s"></span><span class="sr-only">%4$s</span></button>',
					esc_attr( $value ),
					esc_attr( $label ),
					esc_attr( $color ),
					esc_html( $label )
				);
			} else {
				$buttons .= sprintf(
					'<button type="button" class="brik-var-option brik-var-pill" data-value="%1$s" aria-pressed="false">%2$s</button>',
					esc_attr( $value ),
					esc_html( $label )
				);
			}
		}

		// Keep the select for the variation script, but out of the tab order and the a11y tree.
		$select = preg_replace( '/<select\b/', '<select tabindex="-1" aria-hidden="true"', $html, 1 );
		return '<div class="brik-var-field brik-var-field--buttons">' . $select . '<div class="brik-var-options" role="group" aria-label="' . esc_attr( wc_attribute_label( $attribute, $product ) ) . '">' . $buttons . '</div></div>';
	}
}
