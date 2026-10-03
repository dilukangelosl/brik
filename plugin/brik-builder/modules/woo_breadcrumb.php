<?php
/**
 * WooCommerce breadcrumb (Home › Shop › Category › Product) as a shadcn breadcrumb.
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
	'type'        => 'woo_breadcrumb',
	'title'       => __( 'Shop breadcrumb', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'chevrons-right',
	'description' => 'WooCommerce breadcrumb trail (Home › Shop › Category › Product) with its structured data. separator: chevron|slash|dot. home_label: text (empty uses WooCommerce\'s). show_shop: bool, adds the shop page after Home. show_current: bool.',
	'fields'      => array_merge(
		array(
			'separator'    => Fields::field( 'select', __( 'Separator', 'brik-builder' ), 'content', array( 'default' => 'chevron', 'options' => Fields::opts( array( 'chevron' => __( 'Chevron', 'brik-builder' ), 'slash' => __( 'Slash', 'brik-builder' ), 'dot' => __( 'Dot', 'brik-builder' ) ) ) ) ),
			'home_label'   => Fields::field( 'text', __( 'Home label', 'brik-builder' ), 'content', array( 'default' => __( 'Home', 'brik-builder' ) ) ),
			'show_shop'    => Fields::field( 'toggle', __( 'Include the shop page', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_current' => Fields::field( 'toggle', __( 'Show current page', 'brik-builder' ), 'content', array( 'default' => true ) ),
		),
		Product::field(),
		Fields::typography( 'link', __( 'Links', 'brik-builder' ), Fields::WRAP . ' .brik-breadcrumb-link' ),
		Fields::typography( 'current', __( 'Current page', 'brik-builder' ), Fields::WRAP . ' .brik-breadcrumb-page' )
	),
	'render'      => static function ( $a, $ctx ) {
		$home  = trim( wp_strip_all_tags( (string) $a['home_label'] ) );
		$home  = '' !== $home ? $home : _x( 'Home', 'breadcrumb', 'brik-builder' );
		$shop  = wc_get_page_id( 'shop' ) > 0 ? array( get_the_title( wc_get_page_id( 'shop' ) ), get_permalink( wc_get_page_id( 'shop' ) ) ) : null;
		$trail = array();

		$fixed   = ! empty( $a['product'] ) ? brik_woo_product( $ctx, $a ) : null;
		$preview = $ctx->canvas || $fixed || ( ! brik_site_is_layout( $ctx->post_id ) && 'product' === get_post_type( $ctx->post_id ) );
		if ( $preview ) {
			// No main query to read the trail from: build it for the product shown.
			$product = $fixed ? $fixed : brik_woo_product( $ctx, $a );
			if ( $product ) {
				$terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'orderby' => 'parent', 'order' => 'DESC' ) );
				if ( $terms ) {
					foreach ( brik_site_term_trail( $terms[0] ) as $crumb ) {
						$trail[] = array( $crumb[0], is_wp_error( $crumb[1] ) ? '' : $crumb[1] );
					}
				}
				$trail[] = array( $product->get_name(), $product->get_permalink() );
			} else {
				$trail[] = array( __( 'Current page', 'brik-builder' ), '' );
			}
			array_unshift( $trail, array( $home, home_url( '/' ) ) );
		} else {
			$crumbs = new WC_Breadcrumb();
			$crumbs->add_crumb( $home, apply_filters( 'woocommerce_breadcrumb_home_url', home_url( '/' ) ) );
			$trail = $crumbs->generate();
			// Structured data and plugins hooked to the breadcrumb.
			do_action( 'woocommerce_breadcrumb', $crumbs, array( 'breadcrumb' => $trail ) );
		}

		// WooCommerce leaves the shop page out of product trails; add it back after Home.
		if ( ! empty( $a['show_shop'] ) && $shop && count( $trail ) > 1 && ! ( function_exists( 'is_shop' ) && is_shop() && ! $ctx->canvas ) ) {
			$urls = array_map(
				static function ( $c ) {
					return isset( $c[1] ) ? untrailingslashit( (string) $c[1] ) : '';
				},
				$trail
			);
			if ( ! in_array( untrailingslashit( (string) $shop[1] ), $urls, true ) ) {
				array_splice( $trail, 1, 0, array( $shop ) );
			}
		}

		$seps = array(
			'chevron' => brik_icon( 'chevron-right', 'size-3.5' ),
			'slash'   => brik_icon( 'slash', 'size-3.5 -rotate-12' ),
			'dot'     => '<span class="block size-1 rounded-full bg-current opacity-60"></span>',
		);
		$sep  = isset( $seps[ $a['separator'] ] ) ? $seps[ $a['separator'] ] : $seps['chevron'];
		$last = count( $trail ) - 1;
		$out  = array();
		foreach ( $trail as $i => $crumb ) {
			$label = esc_html( wp_strip_all_tags( (string) $crumb[0] ) );
			$url   = isset( $crumb[1] ) ? (string) $crumb[1] : '';
			if ( $i === $last && $last > 0 ) {
				if ( empty( $a['show_current'] ) ) {
					break;
				}
				$out[] = '<li class="inline-flex items-center gap-1.5"><span class="brik-breadcrumb-page font-normal text-foreground" aria-current="page">' . $label . '</span></li>';
			} elseif ( '' === $url ) {
				$out[] = '<li class="inline-flex items-center gap-1.5"><span>' . $label . '</span></li>';
			} else {
				$out[] = '<li class="inline-flex items-center gap-1.5"><a class="brik-breadcrumb-link transition-colors hover:text-foreground" href="' . esc_url( $url ) . '">' . $label . '</a></li>';
			}
		}
		$sep_html = '<li role="presentation" aria-hidden="true" class="inline-flex items-center [&>svg]:size-3.5">' . $sep . '</li>';
		return '<nav class="brik-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'brik-builder' ) . '"><ol class="brik-breadcrumb-list flex flex-wrap items-center gap-1.5 text-sm break-words text-muted-foreground sm:gap-2.5">' . implode( $sep_html, $out ) . '</ol></nav>';
	},
);
