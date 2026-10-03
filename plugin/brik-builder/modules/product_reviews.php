<?php
/**
 * Product reviews: rating summary, review list and review form.
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
	'type'        => 'product_reviews',
	'title'       => __( 'Product reviews', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'messages-square',
	'description' => 'Reviews of the current product: rating summary with a 5→1 breakdown, the review list (verified owner badges) and the review form (WordPress comment form with WooCommerce\'s rating field and settings). summary, list, form: bool. title: optional heading. limit: max reviews shown (0 = all). Has the #reviews anchor. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'title'   => Fields::field( 'text', __( 'Heading', 'brik-builder' ), 'content', array( 'default' => __( 'Customer reviews', 'brik-builder' ) ) ),
			'summary' => Fields::field( 'toggle', __( 'Rating summary', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'list'    => Fields::field( 'toggle', __( 'Review list', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'form'    => Fields::field( 'toggle', __( 'Review form', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'limit'   => Fields::field( 'number', __( 'Reviews shown', 'brik-builder' ), 'content', array( 'default' => 0, 'min' => 0, 'description' => __( '0 shows all reviews.', 'brik-builder' ) ) ),
		),
		Product::field()
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product reviews', 'brik-builder' ) );
		}
		if ( ! Product::reviews_open( $product ) ) {
			return $ctx->placeholder( __( 'Reviews are turned off for this product', 'brik-builder' ) );
		}
		$html = Product::reviews_html(
			$product,
			array(
				'title'   => wp_strip_all_tags( (string) $a['title'] ),
				'summary' => ! empty( $a['summary'] ),
				'list'    => ! empty( $a['list'] ),
				'form'    => ! empty( $a['form'] ),
				'limit'   => max( 0, (int) $a['limit'] ),
				'uid'     => $ctx->uid( 'review' ),
				'canvas'  => $ctx->canvas,
			)
		);
		return '<div id="reviews" class="brik-reviews woocommerce-Reviews flex flex-col gap-6">' . $html . '</div>';
	},
);
