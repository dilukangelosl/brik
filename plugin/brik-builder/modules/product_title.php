<?php
/**
 * Product title.
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
	'type'        => 'product_title',
	'title'       => __( 'Product title', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'heading-1',
	'description' => 'Name of the current product (product templates, product loop items). level: h1-h6|p (default h1). size: display|h1|h2|h3|h4|h5. link: bool, links to the product. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'level' => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p' ) ) ) ),
			'size'  => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'display' => __( 'Display', 'brik-builder' ), 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5' ) ) ) ),
			'link'  => Fields::field( 'toggle', __( 'Link to the product', 'brik-builder' ), 'content' ),
			'align' => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP, 'prop' => 'text-align' ) ) ),
		),
		Product::field(),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-product-title' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product title', 'brik-builder' ) );
		}
		$sizes = array(
			'display' => 'text-4xl md:text-6xl font-bold tracking-tight text-balance',
			'h1'      => 'text-3xl md:text-5xl font-extrabold tracking-tight text-balance',
			'h2'      => 'text-2xl md:text-4xl font-semibold tracking-tight text-balance',
			'h3'      => 'text-2xl font-semibold tracking-tight',
			'h4'      => 'text-xl font-semibold tracking-tight',
			'h5'      => 'text-base font-medium',
		);
		$tag  = in_array( $a['level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? $a['level'] : 'h1';
		$size = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['h2'];
		$text = esc_html( $product->get_name() );
		if ( ! empty( $a['link'] ) ) {
			$text = '<a class="transition-colors hover:text-foreground/80" href="' . esc_url( $product->get_permalink() ) . '">' . $text . '</a>';
		}
		return sprintf( '<%1$s class="brik-product-title product_title entry-title font-heading %2$s">%3$s</%1$s>', $tag, esc_attr( $size ), $text );
	},
);
