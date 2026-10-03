<?php
/**
 * WooCommerce support: theme features, content wrappers, styles and the header cart link.
 * Loaded only while WooCommerce is active.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare WooCommerce support and the product gallery features.
 */
function brik_theme_woocommerce_setup() {
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 600,
			'single_image_width'    => 900,
			'product_grid'          => array(
				'default_rows'    => 3,
				'min_rows'        => 1,
				'default_columns' => 4,
				'min_columns'     => 1,
				'max_columns'     => 6,
			),
		)
	);
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'brik_theme_woocommerce_setup' );

/*
 * WooCommerce's own templates print a content wrapper and a sidebar meant for generic
 * themes; use the theme's container instead.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Opening wrapper for WooCommerce templates.
 */
function brik_theme_woocommerce_wrapper_start() {
	echo '<main id="primary" class="site-main wrap section brik-theme-woo">';
}
add_action( 'woocommerce_before_main_content', 'brik_theme_woocommerce_wrapper_start', 10 );

/**
 * Closing wrapper for WooCommerce templates.
 */
function brik_theme_woocommerce_wrapper_end() {
	echo '</main>';
}
add_action( 'woocommerce_after_main_content', 'brik_theme_woocommerce_wrapper_end', 10 );

/**
 * Styles for WooCommerce's default templates and the cart fragments script for the header count.
 */
function brik_theme_woocommerce_scripts() {
	wp_enqueue_style(
		'brik-theme-woocommerce',
		get_template_directory_uri() . '/assets/css/woocommerce.css',
		array( 'brik-theme' ),
		brik_theme_asset_version( 'assets/css/woocommerce.css' )
	);
	if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}
add_action( 'wp_enqueue_scripts', 'brik_theme_woocommerce_scripts', 20 );

/**
 * Related products: one row matching the grid.
 */
function brik_theme_woocommerce_related_args( $args ) {
	$args['posts_per_page'] = 4;
	$args['columns']        = 4;
	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'brik_theme_woocommerce_related_args' );

/**
 * Header cart link markup (also sent as a cart fragment).
 */
function brik_theme_cart_link_html() {
	$count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
	/* translators: %d: number of items in the cart */
	$label = sprintf( _n( 'Cart, %d item', 'Cart, %d items', $count, 'brik' ), $count );
	$icon  = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/></svg>';
	return sprintf(
		'<a class="icon-button site-cart" href="%1$s" aria-label="%2$s">%3$s<span class="site-cart-count" data-count="%4$d"%5$s>%4$d</span></a>',
		esc_url( wc_get_cart_url() ),
		esc_attr( $label ),
		$icon,
		$count,
		$count ? '' : ' hidden'
	);
}

/**
 * Print the header cart link.
 */
function brik_theme_cart_link() {
	echo brik_theme_cart_link_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in brik_theme_cart_link_html().
}

/**
 * Keep the header count current after AJAX add to cart and on cached pages.
 */
function brik_theme_cart_fragment( $fragments ) {
	$fragments['a.site-cart'] = brik_theme_cart_link_html();
	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'brik_theme_cart_fragment' );
