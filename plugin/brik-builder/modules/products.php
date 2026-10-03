<?php
/**
 * Products: a WooCommerce product grid, carousel or list with its own query, the shop card,
 * pagination and product_filters support.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

$brik_q = array( 'group_label' => __( 'Query', 'brik-builder' ) );
$brik_p = array( 'group_label' => __( 'Pagination', 'brik-builder' ) );
$brik_c = array( 'group_label' => __( 'Product card', 'brik-builder' ) );

return array(
	'type'        => 'products',
	'title'       => __( 'Products', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'shopping-bag',
	'description' => 'WooCommerce products. source: all|featured|sale|best_selling|top_rated|newest|ids (ids: comma separated)|categories (categories: slugs or ids)|tags (tags)|attribute (attribute: pa_color + attribute_terms)|related|upsells|cross_sells (of the current product, or the cart)|current (the shop/category/search query of an archive template). terms_operator IN|AND|NOT IN. orderby: default|date|title|price|popularity|rating|menu_order|rand, order ASC|DESC, per_page (1-100), offset, hide_out_of_stock ""|yes|no. '
		. 'Layout: layout grid|carousel|list, columns (responsive, default 4/3/2), gap, arrows. Card: card_style card|minimal|overlay|horizontal, card_ratio 1:1|3:4|4:5|4:3, card_hover_image (second image on hover), card_category, card_rating, card_badges (sale/new/sold out), card_new_days, card_button, card_button_style full|icon, quick_view (dialog with gallery and the real add-to-cart form), title_tag. '
		. 'pagination: none|numbered|load_more|infinite (+ load_more_text). empty_message. Set css_id (e.g. "shop") so product_filters, shop_ordering and shop_result_count can target it; their values travel as ?bf_{css_id}_{filter}=… (WooCommerce\'s ?orderby, ?min_price, ?filter_color also work).',
	'fields'      => array_merge(
		array(
			'source'            => Fields::field( 'select', __( 'Products', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 'all', 'options' => Fields::opts( brik_woo_sources() ) ) ) ),
			'ids'               => Fields::field( 'text', __( 'Product IDs', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'placeholder' => '12, 34, 56', 'show_if' => array( 'source' => 'ids' ) ) ) ),
			'categories'        => Fields::field( 'text', __( 'Categories', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'placeholder' => 'hoodies, accessories', 'description' => __( 'Category slugs or IDs, separated by commas.', 'brik-builder' ), 'show_if' => array( 'source' => 'categories' ) ) ) ),
			'tags'              => Fields::field( 'text', __( 'Tags', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'placeholder' => 'summer', 'show_if' => array( 'source' => 'tags' ) ) ) ),
			'attribute'         => Fields::field( 'select', __( 'Attribute', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'options' => Fields::opts( array( '' => __( 'Choose…', 'brik-builder' ) ) + brik_woo_attribute_options() ), 'show_if' => array( 'source' => 'attribute' ) ) ) ),
			'attribute_terms'   => Fields::field( 'text', __( 'Attribute terms', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'placeholder' => 'blue, red', 'show_if' => array( 'source' => 'attribute' ) ) ) ),
			'terms_operator'    => Fields::field( 'select', __( 'Match', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 'IN', 'show_if' => array( 'source' => array( 'categories', 'tags', 'attribute' ) ), 'options' => Fields::opts( array( 'IN' => __( 'Any of them', 'brik-builder' ), 'AND' => __( 'All of them', 'brik-builder' ), 'NOT IN' => __( 'None of them', 'brik-builder' ) ) ) ) ) ),
			'orderby'           => Fields::field( 'select', __( 'Order by', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 'default', 'options' => Fields::opts( brik_woo_orderbys() ) ) ) ),
			'order'             => Fields::field( 'select', __( 'Order', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => '', 'options' => Fields::opts( array( '' => __( 'Automatic', 'brik-builder' ), 'ASC' => __( 'Ascending', 'brik-builder' ), 'DESC' => __( 'Descending', 'brik-builder' ) ) ) ) ) ),
			'per_page'          => Fields::field( 'number', __( 'Products per page', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 8, 'min' => 1, 'max' => 100 ) ) ),
			'offset'            => Fields::field( 'number', __( 'Skip products', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'min' => 0 ) ) ),
			'hide_out_of_stock' => Fields::field( 'select', __( 'Out of stock products', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => '', 'options' => Fields::opts( array( '' => __( 'Store setting', 'brik-builder' ), 'no' => __( 'Show', 'brik-builder' ), 'yes' => __( 'Hide', 'brik-builder' ) ) ) ) ) ),

			'layout'            => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'grid', 'options' => Fields::opts( array( 'grid' => __( 'Grid', 'brik-builder' ), 'carousel' => __( 'Carousel', 'brik-builder' ), 'list' => __( 'List', 'brik-builder' ) ) ) ) ),
			'columns'           => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 6, 'responsive' => true, 'show_if' => array( 'layout' => array( 'grid', 'carousel', 'list' ) ) ) ),
			'gap'               => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'placeholder' => '24px', 'css' => array( Fields::WRAP . ' .brik-listing-items', '--brik-listing-gap' ) ) ),
			'arrows'            => Fields::field( 'toggle', __( 'Arrows', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => 'carousel' ) ) ),
			'animate'           => Fields::field( 'toggle', __( 'Animate products in', 'brik-builder' ), 'content' ),
			'empty_message'     => Fields::field( 'text', __( 'Empty message', 'brik-builder' ), 'content', array( 'default' => __( 'No products match your selection.', 'brik-builder' ) ) ),

			'card_style'        => Fields::field( 'select', __( 'Card style', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 'card', 'options' => Fields::opts( brik_woo_card_styles() ) ) ) ),
			'card_ratio'        => Fields::field( 'select', __( 'Image ratio', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => '1:1', 'options' => Fields::opts( array( '1:1' => '1:1', '4:5' => '4:5', '3:4' => '3:4', '2:3' => '2:3', '4:3' => '4:3' ) ) ) ) ),
			'card_hover_image'  => Fields::field( 'toggle', __( 'Second image on hover', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_category'     => Fields::field( 'toggle', __( 'Category', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_rating'       => Fields::field( 'toggle', __( 'Rating', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_badges'       => Fields::field( 'toggle', __( 'Badges', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true, 'description' => __( 'Sale, new and sold out.', 'brik-builder' ) ) ) ),
			'card_new_days'     => Fields::field( 'number', __( '"New" for (days)', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 30, 'min' => 0, 'max' => 365, 'show_if' => array( 'card_badges' => true ) ) ) ),
			'card_button'       => Fields::field( 'toggle', __( 'Add to cart button', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_button_style' => Fields::field( 'select', __( 'Button', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 'full', 'options' => Fields::opts( array( 'full' => __( 'Full width', 'brik-builder' ), 'icon' => __( 'Icon next to the price', 'brik-builder' ) ) ), 'show_if' => array( 'card_style' => 'card', 'card_button' => true ) ) ) ),
			'quick_view'        => Fields::field( 'toggle', __( 'Quick view', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => false ) ) ),
			'card_excerpt'      => Fields::field( 'toggle', __( 'Short description', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'show_if' => array( 'card_style' => 'horizontal' ) ) ) ),
			'title_tag'         => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ) ) ) ) ),

			'pagination'        => Fields::field( 'select', __( 'Pagination', 'brik-builder' ), 'pagination', array_merge( $brik_p, array( 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'None', 'brik-builder' ), 'numbered' => __( 'Page numbers', 'brik-builder' ), 'load_more' => __( 'Load more button', 'brik-builder' ), 'infinite' => __( 'Infinite scroll', 'brik-builder' ) ) ) ) ) ),
			'load_more_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'pagination', array_merge( $brik_p, array( 'default' => __( 'Load more', 'brik-builder' ), 'show_if' => array( 'pagination' => array( 'load_more', 'infinite' ) ) ) ) ),
		),
		Fields::box( 'card', __( 'Cards', 'brik-builder' ), Fields::WRAP . ' .brik-pc' ),
		Fields::typography( 'title', __( 'Product names', 'brik-builder' ), Fields::WRAP . ' .brik-pc-title' ),
		Fields::typography( 'price', __( 'Prices', 'brik-builder' ), Fields::WRAP . ' .brik-pc-price' )
	),
	'css'         => static function ( $a, $wrap ) {
		$out   = '';
		$rules = array(
			'desktop' => '%s',
			'tablet'  => '@media (max-width:' . Brik\Style::TABLET . 'px){%s}',
			'mobile'  => '@media (max-width:' . Brik\Style::MOBILE . 'px){%s}',
		);
		$vars  = array(
			'desktop' => '--brik-listing-cols',
			'tablet'  => '--brik-listing-cols-t',
			'mobile'  => '--brik-listing-cols-m',
		);
		$fallback = array(
			'desktop' => 'list' === $a['layout'] ? 2 : 4,
			'tablet'  => 'list' === $a['layout'] ? 1 : 3,
			'mobile'  => 'list' === $a['layout'] || 'carousel' === $a['layout'] ? 1 : 2,
		);
		foreach ( $rules as $state => $rule ) {
			$v = (int) Brik\Style::raw_value( $a, 'columns', $state );
			if ( $v <= 0 ) {
				$v = 'desktop' === $state ? $fallback['desktop'] : min( $fallback[ $state ], max( 1, (int) Brik\Style::raw_value( $a, 'columns', 'desktop' ) ) );
			}
			$out .= sprintf( $rule, $wrap . '{' . $vars[ $state ] . ':' . min( 6, $v ) . '}' );
		}
		return $out;
	},
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$renderer = $ctx->renderer;
		$canvas   = $ctx->canvas;
		$key      = brik_listing_key( $a['css_id'], $ctx->id );

		$main = null;
		if ( 'current' === $a['source'] && ! $canvas && brik_woo_is_product_archive() ) {
			$main = brik_woo_main_context();
		}
		$product = in_array( $a['source'], array( 'related', 'upsells', 'cross_sells' ), true ) ? brik_woo_context_product( $ctx ) : null;
		if ( 'cross_sells' === $a['source'] && ! $product ) {
			brik_woo_ensure_cart();
		}

		$params  = $canvas ? array() : brik_woo_request_params();
		$defs    = brik_woo_filters_for( $renderer, $renderer->root, $key );
		list( $clauses, $state ) = brik_woo_filter_parse( $defs, $params, $key );
		unset( $state['orderby'] );
		$page_param = brik_listing_param( $key, 'page' );
		$page       = $canvas || ! isset( $params[ $page_param ] ) ? 1 : max( 1, absint( $params[ $page_param ] ) );

		$result = brik_woo_products_results(
			$a,
			array(
				'key'      => $key,
				'page'     => $page,
				'clauses'  => $clauses,
				'main'     => $main,
				'product'  => $product,
				'base_url' => $canvas ? '' : brik_listing_current_url(),
				'canvas'   => $canvas,
				'filtered' => (bool) $state,
			)
		);

		if ( brik_form_bool( $a['quick_view'] ) && ! $canvas ) {
			// The variation form inside the quick view needs WooCommerce's script.
			wp_enqueue_script( 'wc-add-to-cart-variation' );
		}

		if ( empty( $a['css_id'] ) ) {
			$ctx->attrs['id'] = $key;
		}
		$ctx->attrs['data-brik-products'] = $key;
		$ctx->attrs['data-node']          = $ctx->id;
		$ctx->attrs['data-post']          = (int) $ctx->post_id;
		$ctx->attrs['data-pages']         = (int) $result['pages'];
		$ctx->attrs['data-page']          = (int) $result['page'];
		$ctx->attrs['data-total']         = (int) $result['total'];
		$ctx->attrs['data-per-page']      = (int) $result['per_page'];
		$ctx->attrs['data-pagination']    = 'carousel' === $a['layout'] ? 'none' : sanitize_key( (string) $a['pagination'] );
		if ( $product && ! $canvas ) {
			$ctx->attrs['data-current'] = (int) $product->get_id();
		}
		if ( $main ) {
			$ctx->attrs['data-main'] = wp_json_encode( $main );
		}
		if ( 'carousel' === $a['layout'] && brik_form_bool( $a['arrows'] ) ) {
			$ctx->classes[] = 'brik-products--arrows';
		}

		return '<div class="brik-products-results relative grid gap-10" aria-live="polite">' . $result['html'] . '</div>';
	},
);
