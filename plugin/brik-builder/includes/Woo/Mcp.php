<?php
namespace Brik\Woo;

use Brik\McpTools;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * MCP tools for WooCommerce: list, create and update products, read orders.
 * Registered by McpTools while WooCommerce is active.
 */
final class Mcp {

	const MAX_CREATE = 25;

	/**
	 * Tool definitions in the McpTools format.
	 */
	public static function definitions() {
		$attribute = array(
			'type'       => 'object',
			'properties' => array(
				'name'      => array(
					'type'        => 'string',
					'description' => 'Attribute name, e.g. "Color". With global: true a shop-wide attribute (pa_color) is used or created.',
				),
				'options'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'variation' => array(
					'type'        => 'boolean',
					'description' => 'Used for variations (default true for variable products).',
				),
				'visible'   => array(
					'type'        => 'boolean',
					'description' => 'Shown in "Additional information" (default true).',
				),
				'global'    => array(
					'type'        => 'boolean',
					'description' => 'Use a global attribute taxonomy (enables filtering by it). Default false.',
				),
			),
			'required'   => array( 'name', 'options' ),
		);
		$product   = array(
			'type'       => 'object',
			'properties' => array(
				'name'              => array( 'type' => 'string' ),
				'type'              => array(
					'type' => 'string',
					'enum' => array( 'simple', 'variable' ),
				),
				'status'            => array(
					'type' => 'string',
					'enum' => array( 'publish', 'draft', 'pending', 'private' ),
				),
				'slug'              => array( 'type' => 'string' ),
				'regular_price'     => array(
					'type'        => array( 'string', 'number' ),
					'description' => 'Simple products; variations take their own.',
				),
				'sale_price'        => array( 'type' => array( 'string', 'number' ) ),
				'sku'               => array( 'type' => 'string' ),
				'stock_quantity'    => array(
					'type'        => array( 'integer', 'null' ),
					'description' => 'Turns on stock management. null turns it off.',
				),
				'stock_status'      => array(
					'type' => 'string',
					'enum' => array( 'instock', 'outofstock', 'onbackorder' ),
				),
				'short_description' => array( 'type' => 'string' ),
				'description'       => array(
					'type'        => 'string',
					'description' => 'HTML.',
				),
				'categories'        => array(
					'type'        => 'array',
					'description' => 'Category names (created when missing) or ids.',
					'items'       => array( 'type' => array( 'string', 'integer' ) ),
				),
				'tags'              => array(
					'type'  => 'array',
					'items' => array( 'type' => array( 'string', 'integer' ) ),
				),
				'images'            => array(
					'type'        => 'array',
					'description' => 'Public image URLs (downloaded into the media library) or attachment ids. The first is the product image, the rest the gallery.',
					'items'       => array( 'type' => array( 'string', 'integer' ) ),
				),
				'featured'          => array( 'type' => 'boolean' ),
				'weight'            => array( 'type' => array( 'string', 'number' ) ),
				'dimensions'        => array(
					'type'       => 'object',
					'properties' => array(
						'length' => array( 'type' => array( 'string', 'number' ) ),
						'width'  => array( 'type' => array( 'string', 'number' ) ),
						'height' => array( 'type' => array( 'string', 'number' ) ),
					),
				),
				'attributes'        => array(
					'type'  => 'array',
					'items' => $attribute,
				),
				'default_attributes' => array(
					'type'        => 'object',
					'description' => 'Preselected variation, e.g. { "Color": "Blue" }.',
				),
				'variations'        => array(
					'type'        => 'array',
					'description' => 'Variable products: one entry per combination. attributes maps attribute name to option ("" or omitted = any).',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'attributes'     => array( 'type' => 'object' ),
							'regular_price'  => array( 'type' => array( 'string', 'number' ) ),
							'sale_price'     => array( 'type' => array( 'string', 'number' ) ),
							'sku'            => array( 'type' => 'string' ),
							'stock_quantity' => array( 'type' => array( 'integer', 'null' ) ),
							'stock_status'   => array( 'type' => 'string' ),
							'image'          => array( 'type' => array( 'string', 'integer' ) ),
							'description'    => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'name' ),
		);

		return array(
			'woo_list_products'   => array(
				'title'       => 'List WooCommerce products',
				'description' => 'Products with id, type, status, prices, SKU, stock, categories, image and URLs. Filter by search, category (slug), type or status. Requires edit_products.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_products' ),
				'props'       => array(
					'search'   => array( 'type' => 'string' ),
					'category' => array(
						'type'        => 'string',
						'description' => 'Category slug.',
					),
					'type'     => array(
						'type' => 'string',
						'enum' => array( 'simple', 'variable', 'grouped', 'external' ),
					),
					'status'   => array(
						'type' => 'string',
						'enum' => array( 'any', 'publish', 'draft', 'pending', 'private' ),
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
			'woo_create_products' => array(
				'title'       => 'Create WooCommerce products',
				'description' => 'Bulk-creates up to 25 simple or variable products: prices, SKU, stock, descriptions, categories and tags (created when missing), images by URL (sideloaded), attributes and variations. Example variable product: { "name": "Linen shirt", "type": "variable", "categories": ["Shirts"], "images": ["https://…jpg"], "attributes": [{ "name": "Color", "options": ["Sand", "Navy"] }, { "name": "Size", "options": ["S", "M", "L"] }], "variations": [{ "attributes": { "Color": "Sand" }, "regular_price": "59" }, { "attributes": { "Color": "Navy" }, "regular_price": "59", "sale_price": "49" }] } — a variation that leaves an attribute out matches any value. Requires edit_products (and upload_files for image URLs).',
				'open_world'  => true,
				'callback'    => array( __CLASS__, 'create_products' ),
				'props'       => array(
					'products' => array(
						'type'  => 'array',
						'items' => $product,
					),
				),
				'required'    => array( 'products' ),
			),
			'woo_update_product'  => array(
				'title'       => 'Update a WooCommerce product',
				'description' => 'Changes fields of one product (same fields as woo_create_products; only the given ones change). categories/tags/images replace the current ones. variations: entries with "id" update that variation, others are added; remove_variations deletes variation ids. Requires edit_products.',
				'open_world'  => true,
				'callback'    => array( __CLASS__, 'update_product' ),
				'props'       => array_merge(
					array(
						'id'                => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'remove_variations' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
					$product['properties']
				),
				'required'    => array( 'id' ),
			),
			'woo_list_orders'     => array(
				'title'       => 'List WooCommerce orders',
				'description' => 'Recent orders (read only): number, status, date, totals, customer and line items. Filter by status or customer email. Requires manage_woocommerce.',
				'read_only'   => true,
				'callback'    => array( __CLASS__, 'list_orders' ),
				'props'       => array(
					'status'   => array(
						'type'        => 'string',
						'description' => 'pending, processing, on-hold, completed, cancelled, refunded, failed, or any.',
					),
					'customer' => array(
						'type'        => 'string',
						'description' => 'Billing email.',
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Tools.
	 * ------------------------------------------------------------------- */

	public static function list_products( array $a ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to manage products.', 'brik-builder' ) );
		}
		$per_page = isset( $a['per_page'] ) ? (int) $a['per_page'] : 20;
		$args     = array(
			'limit'    => $per_page,
			'page'     => isset( $a['page'] ) ? (int) $a['page'] : 1,
			'status'   => isset( $a['status'] ) && 'any' !== $a['status'] ? $a['status'] : array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
		);
		if ( ! empty( $a['search'] ) ) {
			$args['s'] = sanitize_text_field( $a['search'] );
		}
		if ( ! empty( $a['category'] ) ) {
			$args['category'] = array( sanitize_title( $a['category'] ) );
		}
		if ( ! empty( $a['type'] ) ) {
			$args['type'] = $a['type'];
		}
		$result = wc_get_products( $args );
		return array(
			'total'    => (int) $result->total,
			'pages'    => (int) $result->max_num_pages,
			'products' => array_map( array( __CLASS__, 'payload' ), $result->products ),
		);
	}

	public static function create_products( array $a ) {
		if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'publish_products' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to create products.', 'brik-builder' ) );
		}
		$items = array_values( array_filter( (array) $a['products'], 'is_array' ) );
		$out   = array();
		foreach ( array_slice( $items, 0, self::MAX_CREATE ) as $i => $data ) {
			$warnings = array();
			$type     = isset( $data['type'] ) && 'variable' === $data['type'] ? 'variable' : 'simple';
			$product  = 'variable' === $type ? new \WC_Product_Variable() : new \WC_Product_Simple();
			$product->set_status( 'publish' );
			$saved = self::apply( $product, $data, $warnings );
			if ( is_wp_error( $saved ) ) {
				$out[] = array(
					'index' => $i,
					'error' => $saved->get_error_message(),
				);
				continue;
			}
			$entry = array_merge( array( 'index' => $i ), self::payload( wc_get_product( $saved ) ) );
			if ( $warnings ) {
				$entry['warnings'] = $warnings;
			}
			$out[] = $entry;
		}
		$result = array(
			'created'  => count(
				array_filter(
					$out,
					static function ( $o ) {
						return isset( $o['id'] );
					}
				)
			),
			'products' => $out,
		);
		if ( count( $items ) > self::MAX_CREATE ) {
			/* translators: %d: maximum number of products */
			$result['note'] = sprintf( __( 'Only the first %d products were created.', 'brik-builder' ), self::MAX_CREATE );
		}
		return $result;
	}

	public static function update_product( array $a ) {
		$product = wc_get_product( (int) $a['id'] );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			/* translators: %d: product id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No product with id %d.', 'brik-builder' ), (int) $a['id'] ) );
		}
		if ( ! current_user_can( 'edit_product', $product->get_id() ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to edit this product.', 'brik-builder' ) );
		}
		if ( isset( $a['status'] ) && 'publish' === $a['status'] && ! current_user_can( 'publish_products' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to publish products.', 'brik-builder' ) );
		}
		if ( isset( $a['type'] ) && $a['type'] !== $product->get_type() ) {
			$class   = \WC_Product_Factory::get_product_classname( $product->get_id(), $a['type'] );
			$product = new $class( $product->get_id() );
		}
		if ( ! empty( $a['remove_variations'] ) ) {
			foreach ( array_map( 'intval', (array) $a['remove_variations'] ) as $vid ) {
				$variation = wc_get_product( $vid );
				if ( $variation && $variation->get_parent_id() === $product->get_id() ) {
					$variation->delete( true );
				}
			}
		}
		$warnings = array();
		unset( $a['id'], $a['remove_variations'] );
		$saved = self::apply( $product, $a, $warnings, true );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$out = self::payload( wc_get_product( $saved ) );
		if ( $warnings ) {
			$out['warnings'] = $warnings;
		}
		return $out;
	}

	public static function list_orders( array $a ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Reading orders requires the manage_woocommerce capability.', 'brik-builder' ) );
		}
		$args = array(
			'limit'    => isset( $a['per_page'] ) ? (int) $a['per_page'] : 20,
			'page'     => isset( $a['page'] ) ? (int) $a['page'] : 1,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => true,
			'type'     => 'shop_order',
		);
		if ( ! empty( $a['status'] ) && 'any' !== $a['status'] ) {
			$args['status'] = array( 'wc-' . sanitize_key( preg_replace( '/^wc-/', '', $a['status'] ) ) );
		}
		if ( ! empty( $a['customer'] ) ) {
			$args['billing_email'] = sanitize_email( $a['customer'] );
		}
		$result = wc_get_orders( $args );
		$orders = array();
		foreach ( $result->orders as $order ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'name'       => $item->get_name(),
					'product_id' => $item->get_product_id(),
					'quantity'   => $item->get_quantity(),
					'total'      => wc_format_decimal( $item->get_total(), wc_get_price_decimals() ),
				);
			}
			$date     = $order->get_date_created();
			$orders[] = array(
				'id'             => $order->get_id(),
				'number'         => $order->get_order_number(),
				'status'         => $order->get_status(),
				'date'           => $date ? $date->date( 'c' ) : null,
				'currency'       => $order->get_currency(),
				'total'          => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ),
				'payment_method' => $order->get_payment_method_title(),
				'customer'       => array(
					'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'email' => $order->get_billing_email(),
				),
				'items'          => $items,
				'edit_url'       => $order->get_edit_order_url(),
			);
		}
		return array(
			'total'  => (int) $result->total,
			'pages'  => (int) $result->max_num_pages,
			'orders' => $orders,
		);
	}

	/* ---------------------------------------------------------------------
	 * Helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * Compact description of a product for tool results.
	 */
	public static function payload( $product ) {
		if ( ! $product instanceof \WC_Product ) {
			return array();
		}
		$image = $product->get_image_id();
		$cats  = get_the_terms( $product->get_id(), 'product_cat' );
		$out   = array(
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'sku'            => $product->get_sku(),
			'price'          => Product::plain_current_price( $product ),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'on_sale'        => $product->is_on_sale(),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity' => $product->get_stock_quantity(),
			'categories'     => is_array( $cats ) ? wp_list_pluck( $cats, 'name' ) : array(),
			'image'          => $image ? wp_get_attachment_image_url( $image, 'full' ) : null,
			'gallery_count'  => count( $product->get_gallery_image_ids() ),
			'url'            => $product->get_permalink(),
			'edit_url'       => get_edit_post_link( $product->get_id(), 'raw' ),
		);
		if ( $product->is_type( 'variable' ) ) {
			$out['variations'] = array();
			foreach ( $product->get_children() as $id ) {
				$variation = wc_get_product( $id );
				if ( $variation ) {
					$out['variations'][] = array(
						'id'            => $variation->get_id(),
						'attributes'    => $variation->get_attributes(),
						'regular_price' => $variation->get_regular_price(),
						'sale_price'    => $variation->get_sale_price(),
						'sku'           => $variation->get_sku(),
						'stock_status'  => $variation->get_stock_status(),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Set product fields from tool arguments and save. Returns the product id or an error.
	 */
	private static function apply( \WC_Product $product, array $d, array &$warnings, $update = false ) {
		if ( isset( $d['name'] ) ) {
			$name = sanitize_text_field( $d['name'] );
			if ( '' === $name ) {
				return new WP_Error( 'brik_mcp_args', __( 'Products need a name.', 'brik-builder' ) );
			}
			$product->set_name( $name );
		} elseif ( ! $update ) {
			return new WP_Error( 'brik_mcp_args', __( 'Products need a name.', 'brik-builder' ) );
		}
		if ( isset( $d['status'] ) ) {
			$product->set_status( sanitize_key( $d['status'] ) );
		}
		if ( isset( $d['slug'] ) ) {
			$product->set_slug( sanitize_title( $d['slug'] ) );
		}
		if ( isset( $d['description'] ) ) {
			$product->set_description( wp_kses_post( $d['description'] ) );
		}
		if ( isset( $d['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( $d['short_description'] ) );
		}
		if ( isset( $d['featured'] ) ) {
			$product->set_featured( (bool) $d['featured'] );
		}
		if ( ! $product->is_type( 'variable' ) ) {
			if ( isset( $d['regular_price'] ) ) {
				$product->set_regular_price( wc_format_decimal( $d['regular_price'] ) );
			}
			if ( isset( $d['sale_price'] ) ) {
				$product->set_sale_price( '' === $d['sale_price'] ? '' : wc_format_decimal( $d['sale_price'] ) );
			}
		} elseif ( isset( $d['regular_price'] ) ) {
			$warnings[] = __( 'Variable products take prices per variation; regular_price on the product was ignored.', 'brik-builder' );
		}
		if ( isset( $d['sku'] ) ) {
			try {
				$product->set_sku( wc_clean( $d['sku'] ) );
			} catch ( \WC_Data_Exception $e ) {
				$warnings[] = $e->getMessage();
			}
		}
		self::apply_stock( $product, $d );
		if ( isset( $d['weight'] ) ) {
			$product->set_weight( wc_format_decimal( $d['weight'] ) );
		}
		if ( isset( $d['dimensions'] ) && is_array( $d['dimensions'] ) ) {
			foreach ( array( 'length', 'width', 'height' ) as $dim ) {
				if ( isset( $d['dimensions'][ $dim ] ) ) {
					$product->{'set_' . $dim}( wc_format_decimal( $d['dimensions'][ $dim ] ) );
				}
			}
		}
		if ( isset( $d['categories'] ) ) {
			$product->set_category_ids( self::terms( (array) $d['categories'], 'product_cat', $warnings ) );
		}
		if ( isset( $d['tags'] ) ) {
			$product->set_tag_ids( self::terms( (array) $d['tags'], 'product_tag', $warnings ) );
		}
		if ( isset( $d['images'] ) ) {
			$ids = array();
			foreach ( (array) $d['images'] as $image ) {
				$id = self::image( $image, $warnings );
				if ( $id ) {
					$ids[] = $id;
				}
			}
			$product->set_image_id( $ids ? array_shift( $ids ) : '' );
			$product->set_gallery_image_ids( $ids );
		}

		$map = array();
		if ( isset( $d['attributes'] ) ) {
			list( $attributes, $map ) = self::attributes( (array) $d['attributes'], $product->is_type( 'variable' ), $warnings );
			$product->set_attributes( $attributes );
		} else {
			$map = self::attribute_map( $product );
		}
		if ( isset( $d['default_attributes'] ) && is_array( $d['default_attributes'] ) ) {
			$product->set_default_attributes( self::variation_attributes( $d['default_attributes'], $map, $warnings ) );
		}

		$id = $product->save();
		if ( ! $id ) {
			return new WP_Error( 'brik_save_failed', __( 'The product could not be saved.', 'brik-builder' ) );
		}

		if ( $product->is_type( 'variable' ) && ! empty( $d['variations'] ) ) {
			foreach ( (array) $d['variations'] as $v ) {
				if ( ! is_array( $v ) ) {
					continue;
				}
				$variation = ! empty( $v['id'] ) ? wc_get_product( (int) $v['id'] ) : null;
				if ( ! $variation || $variation->get_parent_id() !== $id ) {
					$variation = new \WC_Product_Variation();
					$variation->set_parent_id( $id );
				}
				if ( isset( $v['attributes'] ) && is_array( $v['attributes'] ) ) {
					$variation->set_attributes( self::variation_attributes( $v['attributes'], $map, $warnings ) );
				}
				if ( isset( $v['regular_price'] ) ) {
					$variation->set_regular_price( wc_format_decimal( $v['regular_price'] ) );
				}
				if ( isset( $v['sale_price'] ) ) {
					$variation->set_sale_price( '' === $v['sale_price'] ? '' : wc_format_decimal( $v['sale_price'] ) );
				}
				if ( isset( $v['description'] ) ) {
					$variation->set_description( wp_kses_post( $v['description'] ) );
				}
				if ( isset( $v['sku'] ) ) {
					try {
						$variation->set_sku( wc_clean( $v['sku'] ) );
					} catch ( \WC_Data_Exception $e ) {
						$warnings[] = $e->getMessage();
					}
				}
				self::apply_stock( $variation, $v );
				if ( isset( $v['image'] ) ) {
					$image = self::image( $v['image'], $warnings );
					$variation->set_image_id( $image ? $image : '' );
				}
				$variation->save();
			}
			\WC_Product_Variable::sync( $id );
			wc_delete_product_transients( $id );
		}
		return $id;
	}

	private static function apply_stock( \WC_Product $product, array $d ) {
		if ( array_key_exists( 'stock_quantity', $d ) ) {
			if ( null === $d['stock_quantity'] ) {
				$product->set_manage_stock( false );
			} else {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $d['stock_quantity'] );
			}
		}
		if ( ! empty( $d['stock_status'] ) && in_array( $d['stock_status'], array( 'instock', 'outofstock', 'onbackorder' ), true ) ) {
			$product->set_stock_status( $d['stock_status'] );
		}
	}

	/**
	 * Term ids from names or ids, creating missing terms when allowed.
	 */
	private static function terms( array $values, $taxonomy, array &$warnings ) {
		$ids = array();
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) ) {
				$term = get_term( (int) $value, $taxonomy );
			} else {
				$name = sanitize_text_field( (string) $value );
				$term = get_term_by( 'name', $name, $taxonomy );
				if ( ! $term && '' !== $name ) {
					if ( ! current_user_can( get_taxonomy( $taxonomy )->cap->manage_terms ) ) {
						/* translators: %s: term name */
						$warnings[] = sprintf( __( 'Skipped "%s": you are not allowed to create terms.', 'brik-builder' ), $name );
						continue;
					}
					$made = wp_insert_term( $name, $taxonomy );
					$term = is_wp_error( $made ) ? null : get_term( $made['term_id'], $taxonomy );
				}
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Attachment id for an image URL (sideloaded) or id.
	 */
	private static function image( $value, array &$warnings ) {
		if ( is_numeric( $value ) ) {
			return wp_attachment_is_image( (int) $value ) ? (int) $value : 0;
		}
		$url = trim( (string) $value );
		if ( '' === $url ) {
			return 0;
		}
		// The same URL used twice in one call (e.g. product and variation) is only downloaded once.
		static $cache = array();
		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}
		$result = McpTools::upload_media( array( 'url' => $url ) );
		if ( is_wp_error( $result ) ) {
			/* translators: 1: image URL, 2: error message */
			$warnings[] = sprintf( __( 'Image %1$s was not imported: %2$s', 'brik-builder' ), $url, $result->get_error_message() );
			return 0;
		}
		$cache[ $url ] = (int) $result['id'];
		return $cache[ $url ];
	}

	/**
	 * WC_Product_Attribute objects from tool input, plus a name → [ key, values ] map for variations.
	 */
	private static function attributes( array $input, $variable, array &$warnings ) {
		$attributes = array();
		$map        = array();
		$position   = 0;
		foreach ( $input as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				continue;
			}
			$label   = sanitize_text_field( $row['name'] );
			$options = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( isset( $row['options'] ) ? $row['options'] : array() ) ), 'strlen' ) );
			$attr    = new \WC_Product_Attribute();
			$attr->set_position( $position++ );
			$attr->set_visible( ! isset( $row['visible'] ) || (bool) $row['visible'] );
			$attr->set_variation( $variable && ( ! isset( $row['variation'] ) || (bool) $row['variation'] ) );

			if ( ! empty( $row['global'] ) || 0 === strpos( $label, 'pa_' ) ) {
				$taxonomy = self::global_attribute( $label, $warnings );
				if ( $taxonomy ) {
					$term_ids = array();
					$slugs    = array();
					foreach ( $options as $option ) {
						$term = get_term_by( 'name', $option, $taxonomy );
						if ( ! $term ) {
							$made = wp_insert_term( $option, $taxonomy );
							$term = is_wp_error( $made ) ? null : get_term( $made['term_id'], $taxonomy );
						}
						if ( $term && ! is_wp_error( $term ) ) {
							$term_ids[]                       = (int) $term->term_id;
							$slugs[ strtolower( $option ) ] = $term->slug;
						}
					}
					$attr->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
					$attr->set_name( $taxonomy );
					$attr->set_options( $term_ids );
					$attributes[ $taxonomy ] = $attr;
					$map[ strtolower( $label ) ] = array( $taxonomy, $slugs );
					$map[ $taxonomy ]            = $map[ strtolower( $label ) ];
					continue;
				}
			}

			$attr->set_id( 0 );
			$attr->set_name( $label );
			$attr->set_options( $options );
			$key                         = sanitize_title( $label );
			$attributes[ $key ]          = $attr;
			$values                      = array();
			foreach ( $options as $option ) {
				$values[ strtolower( $option ) ] = $option;
			}
			$map[ strtolower( $label ) ] = array( $key, $values );
		}
		return array( $attributes, $map );
	}

	/**
	 * Name → [ key, values ] map from a product's saved attributes (for updates).
	 */
	private static function attribute_map( \WC_Product $product ) {
		$map = array();
		foreach ( $product->get_attributes() as $key => $attr ) {
			if ( ! $attr instanceof \WC_Product_Attribute ) {
				continue;
			}
			$values = array();
			if ( $attr->is_taxonomy() ) {
				foreach ( $attr->get_terms() as $term ) {
					$values[ strtolower( $term->name ) ] = $term->slug;
					$values[ $term->slug ]               = $term->slug;
				}
			} else {
				foreach ( $attr->get_options() as $option ) {
					$values[ strtolower( $option ) ] = $option;
				}
			}
			$entry                                                  = array( $key, $values );
			$map[ strtolower( wc_attribute_label( $attr->get_name() ) ) ] = $entry;
			$map[ strtolower( $attr->get_name() ) ]                 = $entry;
		}
		return $map;
	}

	/**
	 * Variation attribute array ({ key: value }) from { "Color": "Blue" }.
	 */
	private static function variation_attributes( array $input, array $map, array &$warnings ) {
		$out = array();
		foreach ( $input as $name => $value ) {
			$lookup = strtolower( (string) $name );
			if ( ! isset( $map[ $lookup ] ) ) {
				/* translators: %s: attribute name */
				$warnings[] = sprintf( __( 'Unknown attribute "%s" in a variation.', 'brik-builder' ), $name );
				continue;
			}
			list( $key, $values ) = $map[ $lookup ];
			$value                = (string) $value;
			if ( '' === $value ) {
				$out[ $key ] = '';
				continue;
			}
			if ( isset( $values[ strtolower( $value ) ] ) ) {
				$out[ $key ] = $values[ strtolower( $value ) ];
			} else {
				/* translators: 1: option, 2: attribute name */
				$warnings[] = sprintf( __( '"%1$s" is not an option of "%2$s".', 'brik-builder' ), $value, $name );
			}
		}
		return $out;
	}

	/**
	 * Taxonomy name of a global attribute, created (and registered for this request) when missing.
	 */
	private static function global_attribute( $label, array &$warnings ) {
		$slug     = wc_sanitize_taxonomy_name( preg_replace( '/^pa_/', '', $label ) );
		$taxonomy = wc_attribute_taxonomy_name( $slug );
		if ( wc_attribute_taxonomy_id_by_name( $taxonomy ) ) {
			return $taxonomy;
		}
		if ( ! current_user_can( 'manage_product_terms' ) ) {
			/* translators: %s: attribute name */
			$warnings[] = sprintf( __( 'Attribute "%s" was added to the product only: creating shop-wide attributes requires manage_product_terms.', 'brik-builder' ), $label );
			return '';
		}
		$id = wc_create_attribute(
			array(
				'name' => 0 === strpos( $label, 'pa_' ) ? ucfirst( $slug ) : $label,
				'slug' => $slug,
			)
		);
		if ( is_wp_error( $id ) ) {
			$warnings[] = $id->get_error_message();
			return '';
		}
		// Taxonomies register on init; make the new one usable right away.
		register_taxonomy( $taxonomy, array( 'product' ), array( 'hierarchical' => false, 'show_ui' => false, 'query_var' => true, 'rewrite' => false ) );
		return $taxonomy;
	}

	/**
	 * Guide section on shop templates.
	 */
	public static function guide() {
		return <<<'MD'

## WooCommerce

WooCommerce is active. Shop modules (category "shop") render the current product and keep WooCommerce's
hooks working: product_title, product_price (live variation price, discount badge), product_add_to_cart
(variation pills/swatches, quantity stepper, AJAX add to cart with toast, buy_now, sticky_mobile bar),
product_gallery (thumbs bottom|left, zoom, lightbox, follows the chosen variation), product_short_description,
product_description, product_tabs (tabs|accordion: description, additional information, reviews),
product_meta, product_rating, product_stock, product_badge, product_additional_info, product_reviews,
product_related, product_upsells, woo_notices, woo_breadcrumb. Every product_* module takes an optional
`product` (id) to show a fixed product on any page; otherwise it uses the viewed product or the loop item
(listings of products). In the builder, templates preview with a sample product.

Single product page: `create_template` with `area: "body"`, `conditions: [{"type":"include","rule":"product"}]`
and `layout: "shop-product-single"` (gallery left, details right, tabs, related) or
`"shop-product-single-centered"` (wide gallery, sticky details card). Shop conditions: `product` (ids optional),
`product_in_cat` (category ids), `shop` (shop page and product archives), `product_cat`, `product_tag`
(term ids optional), `cart`, `checkout`, `order_received`, `account`. Specific rules win
(`product` with ids > `product_in_cat` > `product`).

Product tags (current product): {product:title}, {product:price}, {product:price_html|raw}, {product:regular_price},
{product:sale_price}, {product:on_sale_percent}, {product:sku}, {product:stock}, {product:stock_quantity},
{product:rating}, {product:review_count}, {product:short_description}, {product:add_to_cart_url},
{product:add_to_cart_text}, {product:permalink}, {product:image}, {product:gallery_count}, {product:categories},
{product:tags}, {product:weight}, {product:dimensions}, {product:attribute:pa_color}. Cart and pages:
{cart:count}, {cart:total}, {cart:subtotal}, {shop:url}, {cart:url}, {checkout:url}, {account:url}.

Catalog: `woo_list_products`, `woo_create_products` (simple and variable products with images, categories,
attributes and variations), `woo_update_product`, `woo_list_orders` (read only).
MD;
	}
}
