<?php
namespace Brik\Woo;

defined( 'ABSPATH' ) || exit;

/**
 * Theme builder display conditions for shop pages.
 *
 * Scores follow the core rules: the more specific a rule, the higher its score, so a
 * category template beats a general shop template and a template for one product beats both.
 */
final class Conditions {

	public static function init() {
		add_filter( 'brik/condition_types', array( __CLASS__, 'types' ) );
		add_filter( 'brik/condition_score', array( __CLASS__, 'score' ), 10, 2 );
	}

	public static function types( $types ) {
		// "+" rather than array_merge(): the core "404" key is numeric and would be renumbered.
		return (array) $types + array(
			'product'        => __( 'Shop: single product', 'brik-builder' ),
			'product_in_cat' => __( 'Shop: products in categories', 'brik-builder' ),
			'shop'           => __( 'Shop: shop page and product archives', 'brik-builder' ),
			'product_cat'    => __( 'Shop: product category archive', 'brik-builder' ),
			'product_tag'    => __( 'Shop: product tag archive', 'brik-builder' ),
			'cart'           => __( 'Shop: cart', 'brik-builder' ),
			'checkout'       => __( 'Shop: checkout', 'brik-builder' ),
			'order_received' => __( 'Shop: order received (thank you)', 'brik-builder' ),
			'account'        => __( 'Shop: my account', 'brik-builder' ),
		);
	}

	/**
	 * Labels for the rules this class adds, keyed by rule.
	 */
	public static function keys() {
		return array_keys( self::types( array() ) );
	}

	public static function score( $score, $rule ) {
		if ( $score || ! is_array( $rule ) || empty( $rule['rule'] ) ) {
			return $score;
		}
		$ids = isset( $rule['ids'] ) ? array_map( 'intval', (array) $rule['ids'] ) : array();

		switch ( $rule['rule'] ) {
			case 'product':
				if ( ! is_singular( 'product' ) ) {
					return 0;
				}
				if ( $ids ) {
					return in_array( (int) get_queried_object_id(), $ids, true ) ? 50 : 0;
				}
				return 15;

			case 'product_in_cat':
				if ( ! is_singular( 'product' ) ) {
					return 0;
				}
				if ( $ids ) {
					// Children of the chosen categories count too.
					$terms = $ids;
					foreach ( $ids as $id ) {
						$terms = array_merge( $terms, get_term_children( $id, 'product_cat' ) );
					}
					return has_term( array_map( 'intval', $terms ), 'product_cat', get_queried_object_id() ) ? 35 : 0;
				}
				return has_term( '', 'product_cat', get_queried_object_id() ) ? 20 : 0;

			case 'shop':
				if ( function_exists( 'is_shop' ) && is_shop() ) {
					return 30;
				}
				return function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ? 12 : 0;

			case 'product_cat':
				if ( ! is_tax( 'product_cat' ) ) {
					return 0;
				}
				if ( $ids ) {
					$current = (int) get_queried_object_id();
					foreach ( $ids as $id ) {
						if ( $current === $id || term_is_ancestor_of( $id, $current, 'product_cat' ) ) {
							return 35;
						}
					}
					return 0;
				}
				return 25;

			case 'product_tag':
				if ( ! is_tax( 'product_tag' ) ) {
					return 0;
				}
				if ( $ids ) {
					return in_array( (int) get_queried_object_id(), $ids, true ) ? 35 : 0;
				}
				return 25;

			case 'cart':
				return function_exists( 'is_cart' ) && is_cart() ? 40 : 0;

			case 'checkout':
				return function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ? 40 : 0;

			case 'order_received':
				return function_exists( 'is_order_received_page' ) && is_order_received_page() ? 45 : 0;

			case 'account':
				return function_exists( 'is_account_page' ) && is_account_page() ? 40 : 0;
		}
		return $score;
	}

	/**
	 * Whether a template's conditions target single products (used for builder previews).
	 */
	public static function targets_product( array $rules ) {
		foreach ( $rules as $rule ) {
			if ( 'include' !== $rule['type'] ) {
				continue;
			}
			if ( in_array( $rule['rule'], array( 'product', 'product_in_cat' ), true ) ) {
				return true;
			}
			if ( in_array( $rule['rule'], array( 'singular', 'in_term' ), true ) && ( 'product' === $rule['post_type'] || in_array( $rule['taxonomy'], array( 'product_cat', 'product_tag' ), true ) ) ) {
				return true;
			}
		}
		return false;
	}
}
