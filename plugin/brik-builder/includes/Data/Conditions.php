<?php
/**
 * Display conditions for any element: attrs.visibility_rules.
 *
 *   { "relation": "AND", "rules": [
 *       { "type": "user_status", "value": "logged_in" },
 *       { "type": "cart_total", "op": "gt", "value": 100 },
 *       { "relation": "OR", "rules": [ { "type": "device", "op": "in", "value": ["mobile"] }, … ] }
 *   ] }
 *
 * Rules are evaluated on the server. Device and viewport rules can't be known there, so the
 * tree is reduced to an expression over those (a CSS media condition) and the element is
 * hidden with CSS when it doesn't match. The builder canvas never hides anything.
 *
 * @package Brik
 */

namespace Brik\Data;

defined( 'ABSPATH' ) || exit;

final class Conditions {

	const MAX_DEPTH = 4;

	/** Rule types whose result depends on the visitor (pages showing them shouldn't be cached). */
	const PER_VISITOR = array( 'user_status', 'user_role', 'user_id', 'url_param', 'referrer', 'cookie', 'cart_total', 'cart_count', 'cart_contains', 'purchased' );

	public static function init() {
		add_filter( 'brik/attrs', array( __CLASS__, 'attrs' ), 30, 3 );
		add_filter( 'brik/visible', array( __CLASS__, 'visible' ), 10, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Rule types (for the editor and MCP clients).
	 * ------------------------------------------------------------------- */

	public static function types() {
		$in    = array(
			'in'     => __( 'is any of', 'brik-builder' ),
			'not_in' => __( 'is none of', 'brik-builder' ),
		);
		$text  = array(
			'eq'           => __( 'is', 'brik-builder' ),
			'neq'          => __( 'is not', 'brik-builder' ),
			'contains'     => __( 'contains', 'brik-builder' ),
			'not_contains' => __( 'does not contain', 'brik-builder' ),
			'exists'       => __( 'is set', 'brik-builder' ),
			'not_exists'   => __( 'is not set', 'brik-builder' ),
		);
		$num   = array(
			'gt'  => '>',
			'gte' => '≥',
			'lt'  => '<',
			'lte' => '≤',
			'eq'  => '=',
			'neq' => '≠',
		);
		$roles = array();
		foreach ( wp_roles()->get_names() as $role => $name ) {
			$roles[] = array(
				'value' => $role,
				'label' => translate_user_role( $name ),
			);
		}
		$types = array();
		foreach ( Data::post_types() as $type => $label ) {
			$types[] = array(
				'value' => $type,
				'label' => $label,
			);
		}
		$templates = array(
			array(
				'value' => 'default',
				'label' => __( 'Default template', 'brik-builder' ),
			),
		);
		foreach ( wp_get_theme()->get_page_templates() as $file => $name ) {
			$templates[] = array(
				'value' => $file,
				'label' => $name,
			);
		}
		$days = array();
		foreach ( array( 1, 2, 3, 4, 5, 6, 7 ) as $d ) {
			$days[] = array(
				'value' => (string) $d,
				'label' => wp_date( 'l', strtotime( 'monday this week +' . ( $d - 1 ) . ' days' ) ),
			);
		}
		$taxes = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$taxes[] = array(
				'value' => $tax->name,
				'label' => $tax->labels->singular_name,
			);
		}

		$out = array(
			'user_status'   => array(
				'label'   => __( 'Visitor', 'brik-builder' ),
				'group'   => __( 'User', 'brik-builder' ),
				'value'   => 'select',
				'options' => array(
					array(
						'value' => 'logged_in',
						'label' => __( 'is logged in', 'brik-builder' ),
					),
					array(
						'value' => 'logged_out',
						'label' => __( 'is logged out', 'brik-builder' ),
					),
				),
			),
			'user_role'     => array(
				'label'   => __( 'User role', 'brik-builder' ),
				'group'   => __( 'User', 'brik-builder' ),
				'ops'     => $in,
				'value'   => 'multi',
				'options' => $roles,
			),
			'user_id'       => array(
				'label' => __( 'User ID', 'brik-builder' ),
				'group' => __( 'User', 'brik-builder' ),
				'ops'   => $in,
				'value' => 'list',
			),
			'post_type'     => array(
				'label'   => __( 'Post type', 'brik-builder' ),
				'group'   => __( 'Content', 'brik-builder' ),
				'ops'     => $in,
				'value'   => 'multi',
				'options' => $types,
			),
			'post_id'       => array(
				'label' => __( 'Post ID', 'brik-builder' ),
				'group' => __( 'Content', 'brik-builder' ),
				'ops'   => $in,
				'value' => 'list',
			),
			'data'          => array(
				'label' => __( 'Field value', 'brik-builder' ),
				'group' => __( 'Content', 'brik-builder' ),
				'ops'   => array(
					'eq'           => __( 'is', 'brik-builder' ),
					'neq'          => __( 'is not', 'brik-builder' ),
					'gt'           => '>',
					'gte'          => '≥',
					'lt'           => '<',
					'lte'          => '≤',
					'contains'     => __( 'contains', 'brik-builder' ),
					'not_contains' => __( 'does not contain', 'brik-builder' ),
					'starts'       => __( 'starts with', 'brik-builder' ),
					'in'           => __( 'is one of', 'brik-builder' ),
					'not_in'       => __( 'is none of', 'brik-builder' ),
					'before'       => __( 'is before', 'brik-builder' ),
					'after'        => __( 'is after', 'brik-builder' ),
					'empty'        => __( 'is empty', 'brik-builder' ),
					'not_empty'    => __( 'is not empty', 'brik-builder' ),
				),
				'value' => 'text',
				'field' => true,
			),
			'term'          => array(
				'label'    => __( 'Post terms', 'brik-builder' ),
				'group'    => __( 'Content', 'brik-builder' ),
				'ops'      => array(
					'in'     => __( 'include any of', 'brik-builder' ),
					'not_in' => __( 'include none of', 'brik-builder' ),
				),
				'value'    => 'list',
				'taxonomy' => $taxes,
			),
			'page_template' => array(
				'label'   => __( 'Page template', 'brik-builder' ),
				'group'   => __( 'Content', 'brik-builder' ),
				'ops'     => $in,
				'value'   => 'multi',
				'options' => $templates,
			),
			'url_param'     => array(
				'label' => __( 'URL parameter', 'brik-builder' ),
				'group' => __( 'Request', 'brik-builder' ),
				'ops'   => $text,
				'value' => 'text',
				'key'   => 'utm_source',
			),
			'referrer'      => array(
				'label' => __( 'Referrer', 'brik-builder' ),
				'group' => __( 'Request', 'brik-builder' ),
				'ops'   => array(
					'contains'     => __( 'contains', 'brik-builder' ),
					'not_contains' => __( 'does not contain', 'brik-builder' ),
					'exists'       => __( 'is set', 'brik-builder' ),
					'not_exists'   => __( 'is not set', 'brik-builder' ),
				),
				'value' => 'text',
			),
			'cookie'        => array(
				'label' => __( 'Cookie', 'brik-builder' ),
				'group' => __( 'Request', 'brik-builder' ),
				'ops'   => $text,
				'value' => 'text',
				'key'   => 'cookie_name',
			),
			'language'      => array(
				'label' => __( 'Language', 'brik-builder' ),
				'group' => __( 'Request', 'brik-builder' ),
				'ops'   => $in,
				'value' => 'list',
				'hint'  => __( 'Language codes or locales, e.g. en, fr_FR.', 'brik-builder' ),
			),
			'date_range'    => array(
				'label' => __( 'Date', 'brik-builder' ),
				'group' => __( 'Date & time', 'brik-builder' ),
				'value' => 'date_range',
			),
			'time_of_day'   => array(
				'label' => __( 'Time of day', 'brik-builder' ),
				'group' => __( 'Date & time', 'brik-builder' ),
				'value' => 'time_range',
			),
			'day_of_week'   => array(
				'label'   => __( 'Day of week', 'brik-builder' ),
				'group'   => __( 'Date & time', 'brik-builder' ),
				'ops'     => $in,
				'value'   => 'multi',
				'options' => $days,
			),
			'device'        => array(
				'label'   => __( 'Device', 'brik-builder' ),
				'group'   => __( 'Device', 'brik-builder' ),
				'ops'     => $in,
				'value'   => 'multi',
				'client'  => true,
				'options' => array(
					array(
						'value' => 'desktop',
						'label' => __( 'Desktop', 'brik-builder' ),
					),
					array(
						'value' => 'tablet',
						'label' => __( 'Tablet', 'brik-builder' ),
					),
					array(
						'value' => 'mobile',
						'label' => __( 'Mobile', 'brik-builder' ),
					),
				),
			),
			'viewport'      => array(
				'label'  => __( 'Viewport width', 'brik-builder' ),
				'group'  => __( 'Device', 'brik-builder' ),
				'value'  => 'px_range',
				'client' => true,
			),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$shop                  = __( 'Shop', 'brik-builder' );
			$out['cart_total']     = array(
				'label' => __( 'Cart total', 'brik-builder' ),
				'group' => $shop,
				'ops'   => $num,
				'value' => 'number',
			);
			$out['cart_count']     = array(
				'label' => __( 'Cart items', 'brik-builder' ),
				'group' => $shop,
				'ops'   => $num,
				'value' => 'number',
			);
			$out['cart_contains']  = array(
				'label' => __( 'Cart contains', 'brik-builder' ),
				'group' => $shop,
				'ops'   => array(
					'in'     => __( 'any of', 'brik-builder' ),
					'not_in' => __( 'none of', 'brik-builder' ),
				),
				'value' => 'list',
				'kind'  => array(
					array(
						'value' => 'product',
						'label' => __( 'Product IDs', 'brik-builder' ),
					),
					array(
						'value' => 'category',
						'label' => __( 'Categories', 'brik-builder' ),
					),
				),
			);
			$out['purchased']      = array(
				'label' => __( 'Customer has purchased', 'brik-builder' ),
				'group' => $shop,
				'ops'   => array(
					'in'     => __( 'any of', 'brik-builder' ),
					'not_in' => __( 'none of', 'brik-builder' ),
				),
				'value' => 'list',
				'hint'  => __( 'Product IDs. Empty: any product.', 'brik-builder' ),
			);
			$out['product_status'] = array(
				'label'   => __( 'Product', 'brik-builder' ),
				'group'   => $shop,
				'value'   => 'select',
				'options' => array(
					array(
						'value' => 'in_stock',
						'label' => __( 'is in stock', 'brik-builder' ),
					),
					array(
						'value' => 'out_of_stock',
						'label' => __( 'is out of stock', 'brik-builder' ),
					),
					array(
						'value' => 'on_backorder',
						'label' => __( 'is on backorder', 'brik-builder' ),
					),
					array(
						'value' => 'on_sale',
						'label' => __( 'is on sale', 'brik-builder' ),
					),
					array(
						'value' => 'not_on_sale',
						'label' => __( 'is not on sale', 'brik-builder' ),
					),
					array(
						'value' => 'featured',
						'label' => __( 'is featured', 'brik-builder' ),
					),
				),
			);
		}

		foreach ( $out as $type => &$def ) {
			$def['per_visitor'] = in_array( $type, self::PER_VISITOR, true );
		}
		unset( $def );
		return apply_filters( 'brik/data/condition_types', $out );
	}

	/* ---------------------------------------------------------------------
	 * Hooks.
	 * ------------------------------------------------------------------- */

	public static function has_rules( $rules ) {
		return is_array( $rules ) && ! empty( $rules['rules'] ) && is_array( $rules['rules'] );
	}

	/**
	 * Evaluate once per render and leave the result (and CSS for client-side rules) on the attrs.
	 */
	public static function attrs( $attrs, $node, $renderer ) {
		if ( empty( $attrs['visibility_rules'] ) || ! self::has_rules( $attrs['visibility_rules'] ) ) {
			return $attrs;
		}
		if ( is_object( $renderer ) && ! empty( $renderer->canvas ) ) {
			return $attrs;
		}
		$post_id = is_object( $renderer ) && isset( $renderer->post_id ) ? (int) $renderer->post_id : 0;
		$result  = self::evaluate( $attrs['visibility_rules'], $post_id );

		$attrs['__brik_visible'] = false !== $result;
		if ( is_string( $result ) ) {
			$css = isset( $attrs['custom_css'] ) ? trim( (string) $attrs['custom_css'] ) : '';
			if ( '' !== $css && false === strpos( $css, '{' ) ) {
				$css = 'selector{' . $css . '}';
			}
			$attrs['custom_css'] = $css . "\n@media not " . $result . '{selector{display:none!important}}';
			$attrs['css_class']  = trim( ( isset( $attrs['css_class'] ) ? $attrs['css_class'] : '' ) . ' brik-dc-client' );
		}
		return $attrs;
	}

	public static function visible( $visible, $attrs ) {
		if ( ! $visible || empty( $attrs['visibility_rules'] ) || ! self::has_rules( $attrs['visibility_rules'] ) ) {
			return $visible;
		}
		if ( isset( $attrs['__brik_visible'] ) ) {
			return (bool) $attrs['__brik_visible'];
		}
		return false !== self::evaluate( $attrs['visibility_rules'], 0 );
	}

	/* ---------------------------------------------------------------------
	 * Evaluation.
	 * ------------------------------------------------------------------- */

	/**
	 * true (show), false (hide) or a CSS media condition the viewport must match.
	 */
	public static function evaluate( $tree, $post_id = 0 ) {
		if ( is_string( $tree ) ) {
			$tree = json_decode( $tree, true );
		}
		if ( ! self::has_rules( $tree ) ) {
			return true;
		}
		return self::group( $tree, Data::context_post( $post_id ), 0 );
	}

	private static function group( array $g, $post_id, $depth ) {
		if ( $depth > self::MAX_DEPTH ) {
			return true;
		}
		$or    = isset( $g['relation'] ) && 'OR' === strtoupper( (string) $g['relation'] );
		$media = array();
		$any   = false;
		foreach ( (array) $g['rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			if ( isset( $rule['rules'] ) ) {
				if ( ! self::has_rules( $rule ) ) {
					continue;
				}
				$r = self::group( $rule, $post_id, $depth + 1 );
			} elseif ( empty( $rule['type'] ) ) {
				continue;
			} else {
				$r = self::rule( $rule, $post_id );
			}
			if ( null === $r ) {
				continue; // Unknown or incomplete rule: ignored.
			}
			$any = true;
			if ( true === $r && $or ) {
				return true;
			}
			if ( false === $r && ! $or ) {
				return false;
			}
			if ( is_string( $r ) ) {
				$media[] = $r;
			}
		}
		if ( ! $any ) {
			return true;
		}
		if ( ! $media ) {
			return ! $or; // AND: everything held; OR: nothing did.
		}
		return 1 === count( $media ) ? $media[0] : '(' . implode( $or ? ' or ' : ' and ', $media ) . ')';
	}

	/**
	 * One rule: true, false, a media condition, or null when it can't be evaluated.
	 */
	public static function rule( array $r, $post_id ) {
		$type  = (string) $r['type'];
		$op    = isset( $r['op'] ) ? (string) $r['op'] : '';
		$value = isset( $r['value'] ) ? $r['value'] : '';
		$list  = self::listify( $value );
		$not   = 'not_in' === $op;

		if ( in_array( $type, self::PER_VISITOR, true ) && ! defined( 'DONOTCACHEPAGE' ) && ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			// Honoured by most page caches.
			define( 'DONOTCACHEPAGE', true );
		}

		switch ( $type ) {
			case 'user_status':
				return 'logged_out' === $value ? ! is_user_logged_in() : is_user_logged_in();

			case 'user_role':
				$user = wp_get_current_user();
				$has  = $user->exists() && array_intersect( $list, (array) $user->roles );
				return $not ? ! $has : (bool) $has;

			case 'user_id':
				$has = in_array( (string) get_current_user_id(), $list, true ) && is_user_logged_in();
				return $not ? ! $has : $has;

			case 'post_type':
				$type_now = $post_id ? get_post_type( $post_id ) : ( is_post_type_archive() ? get_query_var( 'post_type' ) : '' );
				$has      = in_array( (string) $type_now, $list, true );
				return $not ? ! $has : $has;

			case 'post_id':
				$has = in_array( (string) $post_id, $list, true );
				return $not ? ! $has : $has;

			case 'term':
				$tax = isset( $r['taxonomy'] ) ? sanitize_key( (string) $r['taxonomy'] ) : '';
				if ( ! $tax || ! taxonomy_exists( $tax ) || ! $post_id ) {
					return $not;
				}
				$terms = array_map(
					static function ( $t ) {
						return ctype_digit( $t ) ? (int) $t : $t;
					},
					$list
				);
				$has   = $terms ? has_term( $terms, $tax, $post_id ) : has_term( '', $tax, $post_id );
				return $not ? ! $has : $has;

			case 'page_template':
				$template = $post_id ? (string) get_page_template_slug( $post_id ) : '';
				$has      = in_array( '' === $template ? 'default' : $template, $list, true );
				return $not ? ! $has : $has;

			case 'data':
				if ( empty( $r['field'] ) ) {
					return null;
				}
				return self::compare( Tags::raw( (string) $r['field'], $post_id ), $op ? $op : 'eq', $value );

			case 'url_param':
			case 'cookie':
				$key = isset( $r['key'] ) ? (string) $r['key'] : '';
				if ( '' === $key ) {
					return null;
				}
				$source = 'cookie' === $type ? $_COOKIE : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$set    = isset( $source[ $key ] ) && is_string( $source[ $key ] );
				$actual = $set ? sanitize_text_field( wp_unslash( $source[ $key ] ) ) : '';
				if ( 'exists' === $op || 'not_exists' === $op ) {
					return 'exists' === $op ? $set : ! $set;
				}
				return self::compare( $actual, $op ? $op : 'eq', $value );

			case 'referrer':
				$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
				if ( 'exists' === $op || 'not_exists' === $op ) {
					return 'exists' === $op ? '' !== $ref : '' === $ref;
				}
				return self::compare( $ref, $op ? $op : 'contains', $value );

			case 'date_range':
				$now  = time();
				$from = ! empty( $r['from'] ) ? Tags::timestamp( (string) $r['from'] ) : 0;
				$to   = ! empty( $r['to'] ) ? Tags::timestamp( (string) $r['to'] ) : 0;
				if ( $to && ! preg_match( '/\d{1,2}:\d{2}/', (string) $r['to'] ) ) {
					$to += DAY_IN_SECONDS - 1; // A date without a time includes the whole day.
				}
				return ( ! $from || $now >= $from ) && ( ! $to || $now <= $to );

			case 'time_of_day':
				$now  = wp_date( 'H:i' );
				$from = isset( $r['from'] ) && preg_match( '/^\d{1,2}:\d{2}$/', (string) $r['from'] ) ? str_pad( (string) $r['from'], 5, '0', STR_PAD_LEFT ) : '00:00';
				$to   = isset( $r['to'] ) && preg_match( '/^\d{1,2}:\d{2}$/', (string) $r['to'] ) ? str_pad( (string) $r['to'], 5, '0', STR_PAD_LEFT ) : '23:59';
				// "22:00 to 06:00" spans midnight.
				return $from <= $to ? ( $now >= $from && $now <= $to ) : ( $now >= $from || $now <= $to );

			case 'day_of_week':
				$has = in_array( wp_date( 'N' ), $list, true );
				return $not ? ! $has : $has;

			case 'language':
				$current = self::languages();
				$has     = (bool) array_intersect( array_map( 'strtolower', $list ), $current );
				return $not ? ! $has : $has;

			case 'device':
				$conds = array();
				foreach ( $list as $device ) {
					$c = self::device_media( $device );
					if ( $c ) {
						$conds[] = $c;
					}
				}
				if ( ! $conds ) {
					return $not;
				}
				$expr = 1 === count( $conds ) ? $conds[0] : '(' . implode( ' or ', $conds ) . ')';
				return $not ? '(not ' . $expr . ')' : $expr;

			case 'viewport':
				$min   = isset( $r['min'] ) ? absint( $r['min'] ) : 0;
				$max   = isset( $r['max'] ) ? absint( $r['max'] ) : 0;
				$conds = array();
				if ( $min ) {
					$conds[] = '(min-width:' . $min . 'px)';
				}
				if ( $max ) {
					$conds[] = '(max-width:' . $max . 'px)';
				}
				if ( ! $conds ) {
					return null;
				}
				return 1 === count( $conds ) ? $conds[0] : '(' . implode( ' and ', $conds ) . ')';

			case 'cart_total':
			case 'cart_count':
				$cart = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;
				if ( $cart && 'cart_total' === $type && ! $cart->is_empty() && ! did_action( 'woocommerce_after_calculate_totals' ) && 0.0 === (float) $cart->get_total( 'edit' ) ) {
					// Totals are only stored once something calculated them (e.g. the cart page).
					$cart->calculate_totals();
				}
				if ( 'cart_count' === $type ) {
					$actual = $cart ? (float) $cart->get_cart_contents_count() : 0.0;
				} else {
					$actual = $cart ? (float) $cart->get_total( 'edit' ) : 0.0;
				}
				return self::compare( $actual, $op ? $op : 'gt', $value );

			case 'cart_contains':
				$cart = function_exists( 'WC' ) && WC()->cart ? WC()->cart : null;
				$has  = false;
				if ( $cart ) {
					$by_cat = isset( $r['kind'] ) && 'category' === $r['kind'];
					foreach ( $cart->get_cart() as $item ) {
						$pid = (int) $item['product_id'];
						if ( $by_cat ) {
							$cats = array_map(
								static function ( $t ) {
									return ctype_digit( $t ) ? (int) $t : $t;
								},
								$list
							);
							$has  = $cats && has_term( $cats, 'product_cat', $pid );
						} else {
							$has = in_array( (string) $pid, $list, true ) || in_array( (string) $item['variation_id'], $list, true );
						}
						if ( $has ) {
							break;
						}
					}
				}
				return $not ? ! $has : $has;

			case 'purchased':
				$user = wp_get_current_user();
				$has  = false;
				if ( $user->exists() && function_exists( 'wc_customer_bought_product' ) ) {
					if ( $list ) {
						foreach ( $list as $pid ) {
							if ( wc_customer_bought_product( $user->user_email, $user->ID, (int) $pid ) ) {
								$has = true;
								break;
							}
						}
					} elseif ( function_exists( 'wc_get_customer_order_count' ) ) {
						$has = wc_get_customer_order_count( $user->ID ) > 0;
					}
				}
				return $not ? ! $has : $has;

			case 'product_status':
				$product = function_exists( 'wc_get_product' ) && $post_id ? wc_get_product( $post_id ) : null;
				if ( ! $product ) {
					return false;
				}
				switch ( $value ) {
					case 'in_stock':
						return $product->is_in_stock();
					case 'out_of_stock':
						return ! $product->is_in_stock();
					case 'on_backorder':
						return $product->is_on_backorder();
					case 'on_sale':
						return $product->is_on_sale();
					case 'not_on_sale':
						return ! $product->is_on_sale();
					case 'featured':
						return $product->is_featured();
				}
				return null;
		}

		$custom = apply_filters( 'brik/data/condition', null, $r, $post_id );
		return null === $custom ? null : $custom;
	}

	private static function device_media( $device ) {
		$tablet = \Brik\Style::TABLET;
		$mobile = \Brik\Style::MOBILE;
		switch ( $device ) {
			case 'desktop':
				return '(min-width:' . ( $tablet + 1 ) . 'px)';
			case 'tablet':
				return '((min-width:' . ( $mobile + 1 ) . 'px) and (max-width:' . $tablet . 'px))';
			case 'mobile':
				return '(max-width:' . $mobile . 'px)';
		}
		return '';
	}

	/**
	 * Current language as lower-case codes: slug and locale from WPML or Polylang, else the
	 * site/user locale and its language part.
	 */
	private static function languages() {
		$out = array();
		if ( has_filter( 'wpml_current_language' ) ) {
			$out[] = (string) apply_filters( 'wpml_current_language', null );
		}
		if ( function_exists( 'pll_current_language' ) ) {
			$out[] = (string) pll_current_language( 'slug' );
			$out[] = (string) pll_current_language( 'locale' );
		}
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$out[]  = $locale;
		$out[]  = substr( $locale, 0, 2 );
		return array_values( array_unique( array_filter( array_map( 'strtolower', $out ) ) ) );
	}

	private static function listify( $value ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = explode( ',', (string) $value );
		}
		$out = array();
		foreach ( $items as $v ) {
			if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
				$out[] = trim( (string) $v );
			}
		}
		return $out;
	}

	/**
	 * Compare an actual value (string, number or list) with an expected one.
	 */
	public static function compare( $actual, $op, $expected ) {
		$values = is_array( $actual ) ? array_map( 'strval', $actual ) : array( (string) $actual );
		$exp    = is_array( $expected ) ? $expected : (string) $expected;
		$text   = is_array( $actual ) ? implode( ', ', $values ) : (string) $actual;
		$lower  = static function ( $s ) {
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s ) : strtolower( (string) $s );
		};

		// Yes/no style values compare as 1/0, so "yes" matches a stored "1".
		$flag = static function ( $s ) use ( $lower ) {
			$s = $lower( $s );
			return in_array( $s, array( 'yes', 'true', 'on' ), true ) ? '1' : ( in_array( $s, array( 'no', 'false', 'off' ), true ) ? '0' : $s );
		};
		switch ( $op ) {
			case 'eq':
			case 'neq':
				$want = $flag( is_array( $exp ) ? reset( $exp ) : $exp );
				$has  = in_array( $want, array_map( $flag, $values ), true );
				return 'eq' === $op ? $has : ! $has;
			case 'gt':
			case 'gte':
			case 'lt':
			case 'lte':
				if ( ! is_numeric( $text ) || ! is_numeric( is_array( $exp ) ? reset( $exp ) : $exp ) ) {
					return false;
				}
				$a = (float) $text;
				$b = (float) ( is_array( $exp ) ? reset( $exp ) : $exp );
				return 'gt' === $op ? $a > $b : ( 'gte' === $op ? $a >= $b : ( 'lt' === $op ? $a < $b : $a <= $b ) );
			case 'contains':
				return '' !== (string) ( is_array( $exp ) ? reset( $exp ) : $exp ) && false !== strpos( $lower( $text ), $lower( is_array( $exp ) ? reset( $exp ) : $exp ) );
			case 'not_contains':
				return '' === (string) ( is_array( $exp ) ? reset( $exp ) : $exp ) || false === strpos( $lower( $text ), $lower( is_array( $exp ) ? reset( $exp ) : $exp ) );
			case 'starts':
				return 0 === strpos( $lower( $text ), $lower( is_array( $exp ) ? reset( $exp ) : $exp ) );
			case 'in':
			case 'not_in':
				$want = array_map( $lower, self::listify( $exp ) );
				$has  = (bool) array_intersect( $want, array_map( $lower, $values ) );
				return 'in' === $op ? $has : ! $has;
			case 'before':
			case 'after':
				$a = Tags::timestamp( $text );
				$b = Tags::timestamp( is_array( $exp ) ? reset( $exp ) : $exp );
				if ( ! $a || ! $b ) {
					return false;
				}
				return 'before' === $op ? $a < $b : $a > $b;
			case 'empty':
				return '' === trim( $text );
			case 'not_empty':
			case 'exists':
				return '' !== trim( $text );
			case 'not_exists':
				return '' === trim( $text );
		}
		return false;
	}
}
