<?php
/**
 * Helpers for the single product modules. Safe to load without WooCommerce: every function
 * checks for it first.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether WooCommerce is available.
 */
function brik_woo_active() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

/**
 * The WC_Product a module describes: the loop item, the queried product, a fixed product id
 * from the module's "product" attribute, or a sample product while a template is edited.
 *
 * @param Brik\Context $ctx   Module context.
 * @param array        $attrs Module attributes (optional, for the "product" override).
 * @return WC_Product|null
 */
function brik_woo_product( Brik\Context $ctx, array $attrs = array() ) {
	if ( ! brik_woo_active() ) {
		return null;
	}
	return Brik\Woo\Product::current( $ctx, $attrs );
}

/**
 * A product's description with the usual content filters (blocks, shortcodes, embeds).
 *
 * @param WC_Product $product Product.
 * @return string HTML.
 */
function brik_woo_description_html( $product ) {
	static $rendering = array();
	if ( ! brik_woo_active() || ! $product instanceof WC_Product ) {
		return '';
	}
	$id = $product->get_id();
	// A Brik-built product could place this module in its own content; don't recurse.
	if ( isset( $rendering[ $id ] ) ) {
		return wpautop( wp_kses_post( $product->get_description() ) );
	}
	$rendering[ $id ] = true;
	$html             = Brik\Woo\Product::with(
		$product,
		static function ( $product ) {
			return (string) apply_filters( 'the_content', $product->get_description() );
		}
	);
	unset( $rendering[ $id ] );
	return $html;
}

/**
 * "Additional information" table: weight, dimensions and visible attributes.
 *
 * @param WC_Product $product Product.
 * @param string     $style   striped|lines|plain.
 * @return string HTML, empty when there is nothing to list.
 */
function brik_woo_attributes_table( $product, $style = 'striped' ) {
	if ( ! brik_woo_active() || ! $product instanceof WC_Product ) {
		return '';
	}
	$rows = Brik\Woo\Product::attribute_rows( $product );
	if ( ! $rows ) {
		return '';
	}
	$out = '';
	foreach ( $rows as $key => $row ) {
		$out .= '<tr class="brik-attr-row brik-attr-row--' . esc_attr( $key ) . '">'
			. '<th scope="row" class="brik-attr-label">' . wp_kses_post( $row['label'] ) . '</th>'
			. '<td class="brik-attr-value">' . wp_kses_post( preg_replace( '#^<p>(.*)</p>$#s', '$1', trim( (string) $row['value'] ) ) ) . '</td></tr>';
	}
	$style = in_array( $style, array( 'striped', 'lines', 'plain' ), true ) ? $style : 'striped';
	return '<div class="brik-attr-wrap overflow-hidden rounded-xl border"><table class="brik-attr-table brik-attr-table--' . esc_attr( $style ) . ' w-full text-sm">' . $out . '</table></div>';
}

/**
 * Grid of related products or upsells for the product_related / product_upsells modules.
 *
 * @param WC_Product   $product Product.
 * @param string       $kind    related|upsells.
 * @param array        $a       Module attributes (title, limit, columns, orderby).
 * @param Brik\Context $ctx     Module context.
 * @return string HTML.
 */
function brik_woo_linked_products( $product, $kind, array $a, Brik\Context $ctx ) {
	if ( ! brik_woo_active() || ! $product instanceof WC_Product ) {
		return '';
	}
	$limit = max( 1, min( 24, (int) $a['limit'] ? (int) $a['limit'] : 4 ) );
	$ids   = 'upsells' === $kind
		? $product->get_upsell_ids()
		: wc_get_related_products( $product->get_id(), max( $limit, 8 ), $product->get_upsell_ids() );
	$items = array_values( array_filter( array_map( 'wc_get_product', (array) $ids ), 'wc_products_array_filter_visible' ) );

	$orderby = in_array( $a['orderby'], array( 'rand', 'date', 'title', 'price', 'popularity', 'rating', 'menu_order' ), true ) ? $a['orderby'] : 'rand';
	if ( 'popularity' === $orderby || 'rating' === $orderby ) {
		usort(
			$items,
			static function ( $x, $y ) use ( $orderby ) {
				return 'popularity' === $orderby
					? (int) $y->get_total_sales() - (int) $x->get_total_sales()
					: ( (float) $y->get_average_rating() <=> (float) $x->get_average_rating() );
			}
		);
	} elseif ( $items ) {
		$items = wc_products_array_orderby( $items, $orderby, in_array( $orderby, array( 'price', 'title', 'menu_order' ), true ) ? 'asc' : 'desc' );
	}
	$items = array_slice( $items, 0, $limit );

	if ( ! $items ) {
		return $ctx->placeholder( 'upsells' === $kind ? __( 'This product has no upsells yet (Product data → Linked products)', 'brik-builder' ) : __( 'No related products found', 'brik-builder' ) );
	}

	$cols = max( 1, min( 6, (int) $a['columns'] ? (int) $a['columns'] : 4 ) );
	$ctx->css( Brik\Fields::WRAP . ' .brik-product-grid', '--brik-cols:' . $cols );
	$tab = Brik\Style::raw_value( $a, 'columns', 'tablet' );
	$tab = null !== $tab ? max( 1, (int) $tab ) : min( $cols, 3 );
	$ctx->css( Brik\Fields::WRAP . ' .brik-product-grid', '--brik-cols:' . $tab, 'tablet' );
	$mob = Brik\Style::raw_value( $a, 'columns', 'mobile' );
	$ctx->css( Brik\Fields::WRAP . ' .brik-product-grid', '--brik-cols:' . ( null !== $mob ? max( 1, (int) $mob ) : 2 ), 'mobile' );

	$cards = '';
	foreach ( $items as $item ) {
		$cards .= '<div class="brik-product-grid-item">' . Brik\Woo\Product::card( $item, array( 'context' => $kind ) ) . '</div>';
	}
	$title = '' !== trim( (string) $a['title'] ) ? '<h2 class="brik-linked-title mb-6 font-heading text-2xl font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h2>' : '';
	return '<section class="brik-linked brik-linked--' . esc_attr( $kind ) . ' ' . ( 'upsells' === $kind ? 'upsells' : 'related' ) . ' products">' . $title . '<div class="brik-product-grid">' . $cards . '</div></section>';
}

/**
 * Bundled shop layouts are hidden while WooCommerce is inactive.
 */
add_filter(
	'brik/bundled_layouts',
	static function ( $layouts ) {
		if ( brik_woo_active() ) {
			return $layouts;
		}
		return array_values(
			array_filter(
				$layouts,
				static function ( $layout ) {
					return 'shop' !== $layout['category'];
				}
			)
		);
	}
);
