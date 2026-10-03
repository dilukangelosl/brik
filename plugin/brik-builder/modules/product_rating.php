<?php
/**
 * Product rating: stars, average and a link to the reviews.
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
	'type'        => 'product_rating',
	'title'       => __( 'Product rating', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'star',
	'description' => 'Average rating of the current product as stars. show_average: bool ("4.5"). show_count: bool ("12 reviews", links to #reviews). empty: hide|show (products without reviews). star_size: CSS length. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'show_average' => Fields::field( 'toggle', __( 'Show average', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_count'   => Fields::field( 'toggle', __( 'Show review count', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'empty'        => Fields::field( 'select', __( 'Without reviews', 'brik-builder' ), 'content', array( 'default' => 'show', 'options' => Fields::opts( array( 'show' => __( 'Show "No reviews yet"', 'brik-builder' ), 'hide' => __( 'Hide', 'brik-builder' ) ) ) ) ),
			'star_size'    => Fields::field( 'unit', __( 'Star size', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'placeholder' => '16px', 'css' => array( Fields::WRAP . ' .brik-star', array( 'width', 'height' ) ) ) ),
			'star_color'   => Fields::field( 'color', __( 'Star color', 'brik-builder' ), 'stars', array( 'tab' => 'design', 'group_label' => __( 'Stars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-star-full', 'color' ) ) ),
		),
		Product::field(),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-rating-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product rating', 'brik-builder' ) );
		}
		if ( ! wc_review_ratings_enabled() ) {
			return $ctx->placeholder( __( 'Star ratings are turned off in WooCommerce settings', 'brik-builder' ) );
		}
		$count   = (int) $product->get_review_count();
		$average = (float) $product->get_average_rating();
		$link    = Product::reviews_open( $product ) && ! $ctx->canvas ? '#reviews' : '';

		if ( ! $count ) {
			if ( 'hide' === $a['empty'] ) {
				return $ctx->placeholder( __( 'No reviews yet (hidden on the site)', 'brik-builder' ) );
			}
			$text = esc_html__( 'No reviews yet', 'brik-builder' );
			if ( $link ) {
				$text = '<a class="brik-rating-link underline-offset-4 hover:text-foreground hover:underline" href="' . esc_url( $link ) . '">' . esc_html__( 'Be the first to review', 'brik-builder' ) . '</a>';
			}
			return '<div class="brik-rating flex flex-wrap items-center gap-2 text-sm">' . brik_stars( 0, 5, 'size-4' ) . '<span class="brik-rating-text text-muted-foreground">' . $text . '</span></div>';
		}

		$parts = '';
		if ( ! empty( $a['show_average'] ) ) {
			$parts .= '<span class="brik-rating-average font-medium tabular-nums">' . esc_html( number_format_i18n( $average, 1 ) ) . '</span>';
		}
		if ( ! empty( $a['show_count'] ) ) {
			/* translators: %s: number of reviews */
			$label  = sprintf( _n( '%s review', '%s reviews', $count, 'brik-builder' ), number_format_i18n( $count ) );
			$parts .= $link
				? '<a class="brik-rating-link text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline" href="' . esc_url( $link ) . '">' . esc_html( $label ) . '</a>'
				: '<span class="text-muted-foreground">' . esc_html( $label ) . '</span>';
		}
		return '<div class="brik-rating flex flex-wrap items-center gap-2 text-sm">' . brik_stars( $average, 5, 'size-4' ) . ( $parts ? '<span class="brik-rating-text inline-flex items-center gap-2">' . $parts . '</span>' : '' ) . '</div>';
	},
);
