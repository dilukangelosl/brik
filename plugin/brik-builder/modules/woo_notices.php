<?php
/**
 * WooCommerce notices ("added to cart", errors, info), styled as alerts.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'woo_notices',
	'title'       => __( 'Shop notices', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'bell',
	'description' => 'Prints WooCommerce notices (success, info, error) as shadcn alerts. Place it near the top of shop templates; without it, product and shop templates print notices above the template. Renders nothing when there are no notices.',
	'fields'      => array(
		'style' => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'tinted', 'options' => Fields::opts( array( 'tinted' => __( 'Tinted', 'brik-builder' ), 'outline' => __( 'Outline', 'brik-builder' ) ) ) ) ),
	),
	'render'      => static function ( $a, $ctx ) {
		$style = 'outline' === $a['style'] ? 'outline' : 'tinted';
		if ( $ctx->canvas ) {
			// Sample notices so the styles can be judged while editing.
			$html = '<div class="woocommerce-notices-wrapper">'
				. '<div class="woocommerce-message" role="alert"><a href="#" tabindex="-1" class="button wc-forward">' . esc_html__( 'View cart', 'brik-builder' ) . '</a> ' . esc_html__( '“Hoodie” has been added to your cart.', 'brik-builder' ) . '</div>'
				. '<div class="woocommerce-info" role="status">' . esc_html__( 'Free shipping on orders over $50.', 'brik-builder' ) . '</div>'
				. '<ul class="woocommerce-error" role="alert"><li>' . esc_html__( 'Please choose product options before adding to cart.', 'brik-builder' ) . '</li></ul>'
				. '</div>';
		} else {
			if ( ! function_exists( 'wc_print_notices' ) || ! WC()->session || ! wc_notice_count() ) {
				return '<div class="brik-woo-notices brik-woo-notices--' . esc_attr( $style ) . '"><div class="woocommerce-notices-wrapper"></div></div>';
			}
			$html = '<div class="woocommerce-notices-wrapper">' . wc_print_notices( true ) . '</div>';
		}
		return '<div class="brik-woo-notices brik-woo-notices--' . esc_attr( $style ) . '">' . $html . '</div>';
	},
);
