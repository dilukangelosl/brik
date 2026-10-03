<?php
/**
 * Shop module tests: product queries, filter validation, the product card, cart fragments,
 * module rendering and the guards used when WooCommerce is missing. Runs through WP-CLI:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/woo/shop.php
 *
 * Uses the store's own products and leaves the cart as it found it. Exits non-zero on failure.
 *
 * @package Brik
 */

use Brik\Renderer;

defined( 'ABSPATH' ) || exit;

$bws_pass     = 0;
$bws_failures = array();

$ok      = static function ( $cond, $name, $detail = '' ) use ( &$bws_pass, &$bws_failures ) {
	if ( $cond ) {
		++$bws_pass;
		echo '  ok   ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	$bws_failures[] = $name . ( '' !== $detail ? ' — ' . $detail : '' );
	echo '  FAIL ' . $name . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
};
$eq      = static function ( $expected, $actual, $name ) use ( $ok ) {
	$ok( $expected === $actual, $name, 'expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) );
};
$section = static function ( $name ) {
	echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
};

// PHP warnings and notices raised by Brik code count as failures.
$bws_warnings = array();
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	static function ( $no, $msg, $file, $line ) use ( &$bws_warnings ) {
		if ( false !== strpos( (string) $file, 'brik-builder' ) ) {
			$bws_warnings[] = $msg . ' @ ' . basename( $file ) . ':' . $line;
		}
		return false;
	}
);

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "WooCommerce is not active.\n";
	exit( 1 );
}
wc_load_cart();
$saved_cart = WC()->cart->get_cart_for_session();
WC()->cart->empty_cart();

$ids  = static function ( array $args ) {
	$q = new WP_Query( array_merge( $args, array( 'fields' => 'ids' ) ) );
	return array_map( 'intval', $q->posts );
};
$all  = wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'ids', 'visibility' => 'catalog' ) );
$by   = static function ( $type ) {
	$p = wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'type' => $type, 'return' => 'ids' ) );
	return $p ? wc_get_product( $p[0] ) : null;
};
$simple   = $by( 'simple' );
$variable = $by( 'variable' );

/* -------------------------------------------------------------------------
 * Query building.
 * ----------------------------------------------------------------------- */
$section( 'Query sources' );

$args = brik_woo_products_query_args( array() );
$eq( 'product', $args['post_type'], 'defaults query products' );
$eq( 8, $args['posts_per_page'], 'default per page' );
$ok( isset( $args['tax_query'] ) && 'NOT IN' === $args['tax_query'][0]['operator'], 'catalog visibility is applied' );
$eq( count( $all ), count( $ids( array_merge( $args, array( 'posts_per_page' => -1, 'offset' => 0 ) ) ) ), 'all source returns the catalog' );

$args = brik_woo_products_query_args( array( 'per_page' => 500, 'offset' => -4 ), array( 'page' => 3 ) );
$eq( 100, $args['posts_per_page'], 'per page is capped' );
$eq( 200, $args['offset'], 'paging offset' );

$featured = $ids( array_merge( brik_woo_products_query_args( array( 'source' => 'featured', 'per_page' => 100 ) ) ) );
$expect   = array_map( 'intval', wc_get_featured_product_ids() );
$ok( $featured && ! array_diff( $featured, $expect ), 'featured source' );

$sale = $ids( brik_woo_products_query_args( array( 'source' => 'sale', 'per_page' => 100 ) ) );
$ok( $sale && ! array_diff( $sale, array_map( 'intval', wc_get_product_ids_on_sale() ) ), 'sale source' );

$best = brik_woo_products_query_args( array( 'source' => 'best_selling' ) );
$eq( 'popularity', $best['brik_wc']['order'], 'best selling sorts by sales' );
$top = brik_woo_products_query_args( array( 'source' => 'top_rated' ) );
$eq( 'rating', $top['brik_wc']['order'], 'top rated sorts by rating' );
$rated = $ids( array_merge( $top, array( 'posts_per_page' => 1 ) ) );
$best_rating = 0;
foreach ( $all as $pid ) {
	$best_rating = max( $best_rating, (float) wc_get_product( $pid )->get_average_rating() );
}
$ok( $rated && (float) wc_get_product( $rated[0] )->get_average_rating() === $best_rating, 'top rated puts the best rating first' );
$new = brik_woo_products_query_args( array( 'source' => 'newest' ) );
$eq( 'DESC', $new['orderby']['date'], 'newest orders by date' );

$pick = array_slice( $all, 0, 3 );
$got  = $ids( brik_woo_products_query_args( array( 'source' => 'ids', 'ids' => implode( ', ', array_reverse( $pick ) ) . ', nonsense' ) ) );
$eq( array_reverse( $pick ), $got, 'ids source keeps the given order' );
$none = brik_woo_products_query_args( array( 'source' => 'ids', 'ids' => '' ) );
$eq( array( 0 ), $none['post__in'], 'ids source without ids matches nothing' );

$cat = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 1, 'exclude' => array( (int) get_option( 'default_product_cat' ) ) ) )[0];
$got = $ids( brik_woo_products_query_args( array( 'source' => 'categories', 'categories' => $cat->slug, 'per_page' => 100 ) ) );
$ok( $got && count( $got ) === count( array_filter( $got, static function ( $id ) use ( $cat ) { return has_term( $cat->term_id, 'product_cat', $id ); } ) ), 'categories source by slug' );
$got2 = $ids( brik_woo_products_query_args( array( 'source' => 'categories', 'categories' => (string) $cat->term_id, 'per_page' => 100 ) ) );
$eq( $got, $got2, 'categories source by id' );
$not = $ids( brik_woo_products_query_args( array( 'source' => 'categories', 'categories' => $cat->slug, 'terms_operator' => 'NOT IN', 'per_page' => 100 ) ) );
$ok( ! array_intersect( $not, $got ), 'categories NOT IN excludes them' );

$tag_args = brik_woo_products_query_args( array( 'source' => 'tags', 'tags' => 'no-such-tag' ) );
$eq( array( 0 ), $tag_args['post__in'], 'unknown tags match nothing' );

$attrs = brik_woo_attribute_options();
if ( isset( $attrs['pa_color'] ) ) {
	$term = get_terms( array( 'taxonomy' => 'pa_color', 'hide_empty' => true, 'number' => 1 ) )[0];
	$got  = $ids( brik_woo_products_query_args( array( 'source' => 'attribute', 'attribute' => 'pa_color', 'attribute_terms' => $term->slug, 'per_page' => 100 ) ) );
	$ok( (bool) $got, 'attribute source finds products' );
}
$bad = brik_woo_products_query_args( array( 'source' => 'attribute', 'attribute' => 'category', 'attribute_terms' => 'x' ) );
$eq( array( 0 ), $bad['post__in'], 'attribute source refuses non-attribute taxonomies' );

$rel = brik_woo_products_query_args( array( 'source' => 'related' ), array( 'product' => $simple ) );
$ok( isset( $rel['post__in'] ) && ! in_array( $simple->get_id(), $rel['post__in'], true ), 'related excludes the product itself' );
$rel0 = brik_woo_products_query_args( array( 'source' => 'related' ) );
$eq( array( 0 ), $rel0['post__in'], 'related without a product matches nothing' );
$up = brik_woo_products_query_args( array( 'source' => 'upsells' ), array( 'product' => $simple ) );
$ok( isset( $up['post__in'] ), 'upsells source restricts to upsell ids' );
WC()->cart->add_to_cart( $simple->get_id() );
$xs = brik_woo_products_query_args( array( 'source' => 'cross_sells' ) );
$ok( isset( $xs['post__in'] ), 'cross-sells use the cart without a product' );
WC()->cart->empty_cart();

$cur = brik_woo_products_query_args( array( 'source' => 'current' ), array( 'main' => array( 'taxonomy' => 'product_cat', 'term' => $cat->term_id, 's' => 'hood' ) ) );
$eq( 'hood', $cur['s'], 'current query keeps the search' );
$ok( (bool) wp_list_filter( $cur['tax_query'], array( 'taxonomy' => 'product_cat' ) ), 'current query keeps the term' );
$evil = brik_woo_products_query_args( array( 'source' => 'current' ), array( 'main' => array( 'taxonomy' => 'category', 'term' => 1 ) ) );
$ok( ! wp_list_filter( $evil['tax_query'], array( 'taxonomy' => 'category' ) ), 'current query ignores taxonomies products do not use' );
$fallback = brik_woo_products_query_args( array( 'source' => 'current' ) );
$ok( ! isset( $fallback['s'] ), 'current query outside archives falls back to all' );

$section( 'Ordering' );
$asc  = $ids( array_merge( brik_woo_products_query_args( array( 'orderby' => 'price', 'per_page' => 100 ) ) ) );
$prices = array_map( static function ( $id ) { return (float) wc_get_product( $id )->get_price(); }, $asc );
$sorted = $prices;
sort( $sorted );
$ok( count( $asc ) === count( $all ) && abs( reset( $prices ) - reset( $sorted ) ) < 0.01, 'price ascending starts with the cheapest' );
$desc = $ids( brik_woo_products_query_args( array( 'orderby' => 'price', 'order' => 'DESC', 'per_page' => 1 ) ) );
$max  = 0;
foreach ( $all as $pid ) {
	$p   = wc_get_product( $pid );
	$max = max( $max, (float) ( $p->is_type( 'variable' ) ? $p->get_variation_price( 'max' ) : $p->get_price() ) );
}
$top_price = wc_get_product( $desc[0] );
$eq( $max, (float) ( $top_price->is_type( 'variable' ) ? $top_price->get_variation_price( 'max' ) : $top_price->get_price() ), 'price descending starts with the most expensive' );
$t = brik_woo_products_query_args( array( 'orderby' => 'title' ) );
$eq( 'ASC', $t['order'], 'title orders A–Z' );
$r = brik_woo_products_query_args( array( 'orderby' => 'rand' ), array( 'page' => 2 ) );
$ok( 0 === strpos( $r['orderby'], 'RAND(' ), 'random order is stable on later pages' );
$x = brik_woo_products_query_args( array( 'orderby' => 'evil; DROP' ) );
$ok( isset( $x['orderby']['menu_order'] ), 'unknown orderby falls back to default' );

/* -------------------------------------------------------------------------
 * Filters.
 * ----------------------------------------------------------------------- */
$section( 'Filter definitions and parsing' );

$defs = brik_woo_filter_defs(
	array(
		array( 'type' => 'category' ),
		array( 'type' => 'price' ),
		array( 'type' => 'attribute', 'attribute' => 'pa_color' ),
		array( 'type' => 'attribute', 'attribute' => 'category' ),
		array( 'type' => 'attribute', 'attribute' => 'pa_nope' ),
		array( 'type' => 'rating' ),
		array( 'type' => 'stock' ),
		array( 'type' => 'sale' ),
		array( 'type' => 'search' ),
		array( 'type' => 'sort' ),
		array( 'type' => 'bogus' ),
		'not an array',
		array( 'type' => 'price' ),
	)
);
$keys = wp_list_pluck( $defs, 'key' );
$eq( isset( $attrs['pa_color'] ) ? array( 'cat', 'price', 'pa_color', 'rating', 'stock', 'sale', 'search', 'orderby' ) : array( 'cat', 'price', 'rating', 'stock', 'sale', 'search', 'orderby' ), $keys, 'definitions are validated and unique' );
if ( isset( $attrs['pa_color'] ) ) {
	$color = wp_list_filter( $defs, array( 'key' => 'pa_color' ) );
	$eq( 'swatches', reset( $color )['ui'], 'color attributes default to swatches' );
}

$k = 'shop';
list( $clauses, $state ) = brik_woo_filter_parse(
	$defs,
	array(
		'bf_shop_cat'       => $cat->slug . ',no-such-cat,<script>',
		'bf_shop_price_min' => '80',
		'bf_shop_price_max' => '10',
		'bf_shop_rating'    => '9,4,abc',
		'bf_shop_stock'     => '1',
		'bf_shop_sale'      => 'maybe',
		'bf_shop_search'    => str_repeat( 'a', 300 ),
		'bf_shop_orderby'   => 'price-desc',
		'bf_other_cat'      => 'ignored',
	),
	$k
);
$eq( array( $cat->slug ), $state['cat'], 'unknown category slugs are dropped' );
$eq( array( 'min' => 10.0, 'max' => 80.0 ), $state['price'], 'price range is swapped into order' );
$eq( '4', $state['rating'], 'rating keeps valid values only' );
$eq( '1', $state['stock'], 'stock toggle' );
$ok( ! isset( $state['sale'] ), 'invalid toggle value is ignored' );
$eq( 100, strlen( $state['search'] ), 'search is trimmed to 100 characters' );
$eq( 'price-desc', $state['orderby'], 'sort value accepted' );
$kinds = wp_list_pluck( $clauses, 'kind' );
$eq( array( 'tax', 'price', 'rating', 'stock', 'search', 'sort' ), $kinds, 'clauses built for valid values only' );

list( , $state ) = brik_woo_filter_parse( $defs, array( 'bf_shop_orderby' => 'post_title; DROP', 'bf_shop_price_min' => '-5', 'bf_shop_rating' => array( 'x' => array( 1 ) ) ), $k );
$ok( ! $state, 'malicious values are ignored', wp_json_encode( $state ) );

list( , $state ) = brik_woo_filter_parse( $defs, array( 'min_price' => '15', 'orderby' => 'popularity', 'filter_color' => 'blue', 'rating_filter' => '3' ), $k );
$ok( isset( $state['price'] ) && 15.0 === $state['price']['min'], 'WooCommerce min_price works as a fallback' );
$eq( 'popularity', $state['orderby'], 'WooCommerce orderby works as a fallback' );
$eq( '3', $state['rating'], 'WooCommerce rating_filter works as a fallback' );
if ( isset( $attrs['pa_color'] ) && get_term_by( 'slug', 'blue', 'pa_color' ) ) {
	$eq( array( 'blue' ), $state['pa_color'], 'WooCommerce filter_color works as a fallback' );
}

list( , $state ) = brik_woo_filter_parse( array(), array( 'bf_shop_orderby' => 'rating', 'bf_shop_price_min' => '5' ), $k );
$ok( isset( $state['orderby'] ) && ! isset( $state['price'] ), 'sorting works without a filter bar, other filters do not' );

$eq( 'min_price', brik_woo_filter_param( '', 'price', true, 'min' ), 'native parameter names' );
$eq( 'filter_color', brik_woo_filter_param( '', 'pa_color', true ), 'native attribute parameter' );
$eq( 'bf_shop_price_max', brik_woo_filter_param( 'shop', 'price', false, 'max' ), 'Brik parameter names' );

$section( 'Filtered queries' );
list( $clauses ) = brik_woo_filter_parse( $defs, array( 'bf_shop_price_min' => '40', 'bf_shop_price_max' => '50' ), $k );
$got = $ids( brik_woo_products_query_args( array( 'per_page' => 100 ), array( 'clauses' => $clauses ) ) );
$in  = true;
foreach ( $got as $pid ) {
	$p      = wc_get_product( $pid );
	$prices = array( (float) $p->get_price() );
	if ( $p->is_type( 'variable' ) ) {
		$prices = array( (float) $p->get_variation_price( 'min' ), (float) $p->get_variation_price( 'max' ) );
	} elseif ( $p->is_type( 'grouped' ) ) {
		$prices = array_map( static function ( $c ) { return (float) wc_get_product( $c )->get_price(); }, $p->get_children() );
	}
	$lo = min( $prices );
	$hi = max( $prices );
	$in = $in && $hi >= 40 && $lo <= 50;
}
$ok( $got && $in, 'price range filter', wp_json_encode( $got ) );
list( $clauses ) = brik_woo_filter_parse( $defs, array( 'bf_shop_rating' => '4' ), $k );
$got = $ids( brik_woo_products_query_args( array( 'per_page' => 100 ), array( 'clauses' => $clauses ) ) );
$ok( $got && ! array_filter( $got, static function ( $id ) { return (float) wc_get_product( $id )->get_average_rating() < 4; } ), 'rating filter' );
list( $clauses ) = brik_woo_filter_parse( $defs, array( 'bf_shop_sale' => '1' ), $k );
$got = $ids( brik_woo_products_query_args( array( 'per_page' => 100 ), array( 'clauses' => $clauses ) ) );
$ok( $got && ! array_diff( $got, array_map( 'intval', wc_get_product_ids_on_sale() ) ), 'sale filter' );

list( $lo, $hi ) = brik_woo_price_bounds();
$ok( null !== $lo && $hi > $lo, 'price bounds come from the catalog' );

/* -------------------------------------------------------------------------
 * Card, fragments, rendering.
 * ----------------------------------------------------------------------- */
$section( 'Product card' );
$html = brik_woo_product_card( $simple );
$ok( false !== strpos( $html, 'data-brik-add-to-cart="' . $simple->get_id() . '"' ), 'simple products add over AJAX' );
$ok( false !== strpos( $html, esc_url( $simple->get_permalink() ) ), 'card links to the product' );
$html = brik_woo_product_card( $variable, array( 'style' => 'overlay', 'quick_view' => true ) );
$ok( false === strpos( $html, 'data-brik-add-to-cart' ) && false !== strpos( $html, 'data-brik-quick-view' ), 'variable products link out; quick view button' );
foreach ( array_keys( brik_woo_card_styles() ) as $style ) {
	$ok( false !== strpos( brik_woo_product_card( $simple, array( 'style' => $style ) ), 'brik-pc--' . $style ), 'card style ' . $style );
}
$ok( false !== strpos( brik_woo_product_card( $simple, array( 'style' => 'nope' ) ), 'brik-pc--card' ), 'unknown style falls back to card' );
$listing = apply_filters( 'brik/listing_card', null, get_post( $simple->get_id() ), array( 'layout' => 'grid' ) );
$ok( is_string( $listing ) && false !== strpos( $listing, 'brik-pc' ), 'listing module uses the shop card for products' );
$eq( null, apply_filters( 'brik/listing_card', null, get_post( get_option( 'page_on_front' ) ? get_option( 'page_on_front' ) : 1 ), array() ), 'other post types keep the listing card' );

$qv = brik_woo_quick_view_html( $variable );
$ok( false !== strpos( $qv, 'variations_form' ), 'quick view contains the real add-to-cart form' );

$section( 'Fragments' );
WC()->cart->add_to_cart( $simple->get_id(), 2 );
$frag = brik_woo_fragments();
$ok( isset( $frag['fragments']['span.brik-mc-count'], $frag['fragments']['div.brik-mc-content'], $frag['fragments']['span.brik-mc-total'] ), 'Brik fragments are registered' );
$ok( false !== strpos( $frag['fragments']['span.brik-mc-count'], 'data-count="2"' ), 'count fragment' );
$ok( false !== strpos( $frag['fragments']['div.brik-mc-content'], 'data-brik-qty' ) && false !== strpos( $frag['fragments']['div.brik-mc-content'], 'data-nonce=' ), 'drawer fragment has steppers and a nonce' );
$ok( '' !== $frag['cart_hash'], 'cart hash returned' );
$bar = brik_woo_shipping_bar( 40, 100 );
$ok( false !== strpos( $bar, 'width:40%' ), 'shipping bar progress' );
$eq( '', brik_woo_shipping_bar( 40, 0 ), 'no bar without a threshold' );
WC()->cart->empty_cart();
$ok( false !== strpos( brik_woo_mc_content_html(), 'brik-mc-empty' ), 'empty drawer state' );

$section( 'Module rendering (empty attributes)' );
$types = array( 'products', 'product_filters', 'shop_result_count', 'shop_ordering', 'shop_archive_header', 'product_categories', 'mini_cart', 'woo_cart', 'woo_checkout', 'woo_account', 'woo_order_received' );
$renderer = new Renderer( 0 );
foreach ( $types as $type ) {
	$ok( (bool) Brik\Modules::get( $type ), 'module registered: ' . $type );
	$before = count( $bws_warnings );
	$out    = $renderer->render_node( array( 'id' => 'tst' . substr( md5( $type ), 0, 5 ), 'type' => $type, 'attrs' => array() ) );
	$ok( count( $bws_warnings ) === $before, 'no warnings: ' . $type, implode( '; ', array_slice( $bws_warnings, $before ) ) );
	$ok( is_string( $out ), 'renders: ' . $type );
}
$out = $renderer->render_node( array( 'id' => 'tstcart1', 'type' => 'woo_cart', 'attrs' => array() ) );
$ok( false !== strpos( $out, '[woocommerce_cart]' ), 'cart keeps the shortcode in the saved copy' );
$out = $renderer->render_node( array( 'id' => 'tstprod1', 'type' => 'products', 'attrs' => array( 'per_page' => 3 ) ) );
$eq( 3, substr_count( $out, 'class="brik-listing-item brik-products-item"' ), 'products renders per_page items' );
$canvas = new Renderer( 0, true );
$out    = $canvas->render_node( array( 'id' => 'tstfilt1', 'type' => 'product_filters', 'attrs' => array( 'filters' => array() ) ) );
$ok( false !== strpos( $out, 'brik-placeholder' ), 'filters without definitions show a placeholder in the builder' );

$section( 'Without WooCommerce' );
add_filter( 'brik/woo_shop_enabled', '__return_false' );
$ok( ! brik_woo_shop_enabled(), 'the guard can be switched off' );
foreach ( $types as $type ) {
	$def = require BRIK_DIR . 'modules/' . $type . '.php';
	$ok( null === $def, 'module skips registration: ' . $type );
}
$eq( false, brik_woo_ensure_cart(), 'cart helper refuses' );
$eq( array(), brik_woo_attribute_options(), 'attribute options empty' );
$frag = apply_filters( 'woocommerce_add_to_cart_fragments', array( 'a' => 1 ) );
$ok( ! isset( $frag['span.brik-mc-count'] ) && ! isset( $frag['div.brik-mc-content'] ), 'no Brik fragments are added' );
remove_filter( 'brik/woo_shop_enabled', '__return_false' );

// Restore the cart.
WC()->cart->empty_cart();
foreach ( $saved_cart as $item ) {
	WC()->cart->add_to_cart( $item['product_id'], $item['quantity'], $item['variation_id'], isset( $item['variation'] ) ? $item['variation'] : array() );
}
restore_error_handler();

$section( 'Warnings' );
$ok( ! $bws_warnings, 'no PHP warnings from Brik', implode( "\n", array_unique( $bws_warnings ) ) );

echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
printf( "PASS %d  FAIL %d\n", (int) $bws_pass, count( $bws_failures ) );
foreach ( $bws_failures as $f ) {
	echo $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
exit( $bws_failures ? 1 : 0 );
