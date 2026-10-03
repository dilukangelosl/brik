<?php
/**
 * Product meta: SKU, categories and tags. Fires WooCommerce's product meta hooks so brands
 * and other plugins can add their lines.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Woo\Product;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'product_meta',
	'title'       => __( 'Product meta', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'tag',
	'description' => 'SKU, categories and tags of the current product. sku, categories, tags: bool. layout: stacked|inline. terms: badges|links. Plugins hooked to woocommerce_product_meta_start/end print here too. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'sku'        => Fields::field( 'toggle', __( 'SKU', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'categories' => Fields::field( 'toggle', __( 'Categories', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'tags'       => Fields::field( 'toggle', __( 'Tags', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'layout'     => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'stacked', 'options' => Fields::opts( array( 'stacked' => __( 'Stacked', 'brik-builder' ), 'inline' => __( 'Inline', 'brik-builder' ) ) ) ) ),
			'terms'      => Fields::field( 'select', __( 'Categories and tags as', 'brik-builder' ), 'content', array( 'default' => 'links', 'options' => Fields::opts( array( 'links' => __( 'Links', 'brik-builder' ), 'badges' => __( 'Badges', 'brik-builder' ) ) ) ) ),
		),
		Product::field(),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-meta-label' ),
		Fields::typography( 'value', __( 'Values', 'brik-builder' ), Fields::WRAP . ' .brik-meta-value' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product meta', 'brik-builder' ) );
		}
		$badges = 'badges' === $a['terms'];
		$row    = static function ( $label, $value, $class ) {
			return '<div class="brik-meta-row ' . esc_attr( $class ) . ' flex flex-wrap items-baseline gap-x-2 gap-y-1"><span class="brik-meta-label text-muted-foreground">' . esc_html( $label ) . '</span><span class="brik-meta-value text-foreground">' . $value . '</span></div>';
		};
		$terms  = static function ( $taxonomy ) use ( $product, $badges ) {
			$list = get_the_terms( $product->get_id(), $taxonomy );
			if ( ! $list || is_wp_error( $list ) ) {
				return '';
			}
			$out = array();
			foreach ( $list as $term ) {
				$url   = get_term_link( $term );
				$url   = is_wp_error( $url ) ? '#' : $url;
				$out[] = $badges
					? '<a class="' . esc_attr( brik_badge_class( 'outline' ) ) . '" href="' . esc_url( $url ) . '" rel="tag">' . esc_html( $term->name ) . '</a>'
					: '<a class="underline-offset-4 transition-colors hover:text-foreground hover:underline" href="' . esc_url( $url ) . '" rel="tag">' . esc_html( $term->name ) . '</a>';
			}
			return $badges ? '<span class="inline-flex flex-wrap gap-1.5">' . implode( '', $out ) . '</span>' : implode( ', ', $out );
		};

		$lines = '';
		if ( ! empty( $a['sku'] ) && wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) {
			$sku    = $product->get_sku() ? $product->get_sku() : __( 'N/A', 'brik-builder' );
			$lines .= $row( __( 'SKU', 'brik-builder' ), '<span class="sku tabular-nums" data-brik-sku="' . (int) $product->get_id() . '" data-default="' . esc_attr( $sku ) . '">' . esc_html( $sku ) . '</span>', 'sku_wrapper' );
		}
		if ( ! empty( $a['categories'] ) ) {
			$list = $terms( 'product_cat' );
			if ( $list ) {
				$count  = count( (array) $product->get_category_ids() );
				$lines .= $row( _n( 'Category', 'Categories', $count, 'brik-builder' ), $list, 'posted_in' );
			}
		}
		if ( ! empty( $a['tags'] ) ) {
			$list = $terms( 'product_tag' );
			if ( $list ) {
				$count  = count( (array) $product->get_tag_ids() );
				$lines .= $row( _n( 'Tag', 'Tags', $count, 'brik-builder' ), $list, 'tagged_as' );
			}
		}

		$html = Product::capture(
			$product,
			static function () use ( $lines ) {
				do_action( 'woocommerce_product_meta_start' );
				echo $lines; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped above.
				do_action( 'woocommerce_product_meta_end' );
			}
		);
		if ( '' === trim( $html ) ) {
			return $ctx->placeholder( __( 'This product has no SKU, categories or tags', 'brik-builder' ) );
		}
		$layout = 'inline' === $a['layout'] ? 'flex flex-wrap items-center gap-x-6 gap-y-2' : 'flex flex-col gap-2';
		return '<div class="brik-product-meta product_meta text-sm ' . esc_attr( $layout ) . '">' . $html . '</div>';
	},
);
