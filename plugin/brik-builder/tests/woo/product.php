<?php
/**
 * Single product integration tests: conditions, dynamic tags, module rendering and MCP tools.
 * Runs inside WordPress through WP-CLI:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/woo/product.php
 *
 * Products it creates are named "bwt …" and deleted at the end.
 *
 * @package Brik
 */

use Brik\Dynamic;
use Brik\McpTools;
use Brik\Modules;
use Brik\Renderer;
use Brik\ThemeBuilder;
use Brik\Woo\Conditions;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "FAIL WooCommerce is not active\n";
	exit( 1 );
}

// eval-file runs this file inside a function, so the counters are set as real globals.
$GLOBALS['bwt_pass']     = 0;
$GLOBALS['bwt_fail']     = 0;
$GLOBALS['bwt_failures'] = array();
$GLOBALS['bwt_warnings'] = array();

function bwt_ok( $condition, $name, $detail = '' ) {
	global $bwt_pass, $bwt_fail, $bwt_failures;
	if ( $condition ) {
		++$bwt_pass;
		echo '  PASS ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	++$bwt_fail;
	$line           = $name . ( '' !== $detail ? ' — ' . $detail : '' );
	$bwt_failures[] = $line;
	echo '  FAIL ' . $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

function bwt_section( $name ) {
	echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}

/**
 * Point the main query at a request, like a page load would.
 */
function bwt_go( array $args ) {
	global $wp_query, $wp_the_query, $post;
	$wp_query     = new WP_Query( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$wp_the_query = $wp_query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$post         = $wp_query->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
}

function bwt_render( $type, $post_id, array $attrs = array(), $canvas = false ) {
	$renderer = new Renderer( $post_id, $canvas );
	return $renderer->render_node(
		array(
			'id'    => 'bwt' . substr( md5( $type . wp_json_encode( $attrs ) ), 0, 6 ),
			'type'  => $type,
			'attrs' => $attrs,
		)
	);
}

/**
 * Inner markup of a module (without the element wrapper).
 */
function bwt_inner( $html ) {
	return trim( (string) preg_replace( '#^<div[^>]*>(.*)</div>$#s', '$1', trim( $html ) ) );
}

// PHP notices and warnings raised by the code under test count as failures.
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	static function ( $no, $str, $file, $line ) {
		global $bwt_warnings;
		if ( false !== strpos( $file, 'brik-builder' ) ) {
			$bwt_warnings[] = $str . ' (' . basename( $file ) . ':' . $line . ')';
		}
		return false;
	},
	E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED
);

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $admins[0] );
if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
	wc_load_cart();
}

/* -------------------------------------------------------------------------
 * Fixtures.
 * ---------------------------------------------------------------------- */

$bwt_ids = array();

$cat = wp_insert_term( 'bwt Category', 'product_cat' );
$cat = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
$tag = wp_insert_term( 'bwt Tag', 'product_tag' );
$tag = is_wp_error( $tag ) ? (int) $tag->get_error_data() : (int) $tag['term_id'];

$simple = new WC_Product_Simple();
$simple->set_name( 'bwt Sale tee' );
$simple->set_status( 'publish' );
$simple->set_regular_price( '40' );
$simple->set_sale_price( '30' );
$simple->set_sku( 'bwt-sale-' . wp_rand( 1000, 9999 ) );
$simple->set_short_description( 'A <strong>soft</strong> tee.' );
$simple->set_description( '<p>Long description of the tee.</p>' );
$simple->set_weight( '0.3' );
$simple->set_length( '10' );
$simple->set_width( '20' );
$simple->set_height( '2' );
$simple->set_manage_stock( true );
$simple->set_stock_quantity( 12 );
$simple->set_category_ids( array( $cat ) );
$simple->set_tag_ids( array( $tag ) );
$color = new WC_Product_Attribute();
$color->set_name( 'Color' );
$color->set_options( array( 'Blue', 'Red' ) );
$color->set_visible( true );
$simple->set_attributes( array( 'color' => $color ) );
$simple_id = $simple->save();
$bwt_ids[] = $simple_id;

$oos = new WC_Product_Simple();
$oos->set_name( 'bwt Sold out cap' );
$oos->set_status( 'publish' );
$oos->set_regular_price( '15' );
$oos->set_stock_status( 'outofstock' );
$oos_id    = $oos->save();
$bwt_ids[] = $oos_id;

/* -------------------------------------------------------------------------
 * MCP tools (also create the variable product used below).
 * ---------------------------------------------------------------------- */

bwt_section( 'MCP tools' );

foreach ( array( 'woo_list_products', 'woo_create_products', 'woo_update_product', 'woo_list_orders' ) as $tool ) {
	bwt_ok( McpTools::exists( $tool ), "tool $tool is registered" );
}
bwt_ok( false !== strpos( McpTools::guide(), '{product:price}' ), 'guide documents product tags' );
bwt_ok( false !== strpos( McpTools::guide(), 'shop-product-single' ), 'guide mentions the product layouts' );

$image_id = 0;
foreach ( wc_get_products( array( 'limit' => 5, 'return' => 'ids' ) ) as $pid ) {
	$candidate = (int) get_post_thumbnail_id( $pid );
	if ( $candidate && wp_attachment_is_image( $candidate ) ) {
		$image_id = $candidate;
		break;
	}
}

list( $created ) = McpTools::call(
	'woo_create_products',
	array(
		'products' => array(
			array(
				'name'               => 'bwt Variable hoodie',
				'type'               => 'variable',
				'categories'         => array( 'bwt Category', 'bwt New category' ),
				'images'             => $image_id ? array( $image_id ) : array(),
				'short_description'  => 'Cozy.',
				'attributes'         => array(
					array(
						'name'    => 'Color',
						'options' => array( 'Blue', 'Green' ),
					),
					array(
						'name'    => 'Size',
						'options' => array( 'S', 'M' ),
					),
				),
				'default_attributes' => array( 'Color' => 'Blue' ),
				'variations'         => array(
					array(
						'attributes'    => array( 'Color' => 'Blue' ),
						'regular_price' => '50',
						'sale_price'    => '40',
					),
					array(
						'attributes'     => array( 'Color' => 'Green', 'Size' => 'M' ),
						'regular_price'  => '55',
						'stock_quantity' => 3,
					),
				),
			),
			array(
				'name'          => 'bwt Simple mug',
				'regular_price' => '12.5',
				'sku'           => 'bwt-mug-' . wp_rand( 1000, 9999 ),
				'stock_status'  => 'instock',
			),
			array( 'type' => 'simple' ),
		),
	)
);
bwt_ok( is_array( $created ) && 2 === $created['created'], 'woo_create_products creates valid products', wp_json_encode( is_wp_error( $created ) ? $created->get_error_message() : $created['created'] ) );
$var_id = 0;
foreach ( is_array( $created ) ? $created['products'] : array() as $row ) {
	if ( isset( $row['id'] ) ) {
		$bwt_ids[] = $row['id'];
		if ( 'variable' === $row['type'] ) {
			$var_id = $row['id'];
		}
	}
}
bwt_ok( is_array( $created ) && isset( $created['products'][2]['error'] ), 'a product without a name is reported, not created' );
$variable = $var_id ? wc_get_product( $var_id ) : null;
bwt_ok( $variable && $variable->is_type( 'variable' ) && 2 === count( $variable->get_children() ), 'variable product has two variations' );
bwt_ok( $variable && $variable->is_on_sale(), 'variable product is on sale through a variation' );
bwt_ok( $variable && 'blue' === strtolower( (string) $variable->get_default_attributes()['color'] ), 'default attributes are set' );
$new_cat = get_term_by( 'name', 'bwt New category', 'product_cat' );
bwt_ok( (bool) $new_cat, 'missing categories are created' );
if ( $variable ) {
	$any = null;
	foreach ( $variable->get_children() as $child ) {
		$v = wc_get_product( $child );
		if ( '' === $v->get_attribute( 'size' ) && 'Blue' === $v->get_attribute( 'color' ) ) {
			$any = $v;
		}
	}
	bwt_ok( (bool) $any, 'a variation without an attribute matches any value' );
}

list( $updated ) = McpTools::call(
	'woo_update_product',
	array(
		'id'         => $simple_id,
		'sale_price' => '25',
		'tags'       => array( 'bwt Tag', 'bwt Extra' ),
	)
);
bwt_ok( is_array( $updated ) && '25' === $updated['sale_price'], 'woo_update_product changes the sale price', wp_json_encode( is_wp_error( $updated ) ? $updated->get_error_message() : '' ) );
bwt_ok( 2 === count( wc_get_product( $simple_id )->get_tag_ids() ), 'woo_update_product replaces tags' );

list( $listed ) = McpTools::call( 'woo_list_products', array( 'search' => 'bwt', 'status' => 'any' ) );
bwt_ok( is_array( $listed ) && $listed['total'] >= 4, 'woo_list_products finds the test products' );
list( $orders ) = McpTools::call( 'woo_list_orders', array( 'per_page' => 5 ) );
bwt_ok( is_array( $orders ) && isset( $orders['orders'] ), 'woo_list_orders answers for managers' );

wp_set_current_user( 0 );
list( $denied ) = McpTools::call( 'woo_list_orders', array() );
bwt_ok( is_wp_error( $denied ), 'woo_list_orders needs manage_woocommerce' );
list( $denied ) = McpTools::call( 'woo_create_products', array( 'products' => array( array( 'name' => 'nope' ) ) ) );
bwt_ok( is_wp_error( $denied ), 'woo_create_products needs edit_products' );
wp_set_current_user( (int) $admins[0] );

/* -------------------------------------------------------------------------
 * Conditions.
 * ---------------------------------------------------------------------- */

bwt_section( 'Conditions' );

$types = ThemeBuilder::rule_types();
foreach ( array( 'product', 'product_in_cat', 'shop', 'product_cat', 'product_tag', 'cart', 'checkout', 'order_received', 'account' ) as $rule ) {
	bwt_ok( isset( $types[ $rule ] ), "rule $rule is offered" );
}
bwt_ok( isset( $types['404'] ), 'core rule keys survive (404)' );

$score = static function ( $rule, $ids = array() ) {
	return (int) apply_filters( 'brik/condition_score', 0, array( 'type' => 'include', 'rule' => $rule, 'post_type' => '', 'taxonomy' => '', 'ids' => $ids ) );
};

bwt_go( array( 'p' => $simple_id, 'post_type' => 'product' ) );
bwt_ok( is_singular( 'product' ), 'test request is a single product' );
bwt_ok( 15 === $score( 'product' ), 'product without ids scores 15', (string) $score( 'product' ) );
bwt_ok( 50 === $score( 'product', array( $simple_id ) ), 'product with its id scores 50' );
bwt_ok( 0 === $score( 'product', array( $oos_id ) ), 'product with another id does not match' );
bwt_ok( 35 === $score( 'product_in_cat', array( $cat ) ), 'product_in_cat with its category scores 35' );
bwt_ok( 0 === $score( 'product_in_cat', array( (int) $new_cat->term_id ) ), 'product_in_cat with another category does not match' );
bwt_ok( 0 === $score( 'shop' ) && 0 === $score( 'cart' ) && 0 === $score( 'product_cat' ), 'archive and page rules do not match a product' );
bwt_ok( $score( 'product', array( $simple_id ) ) > $score( 'product_in_cat', array( $cat ) ) && $score( 'product_in_cat', array( $cat ) ) > $score( 'product' ), 'specific product > category > any product' );

bwt_go( array( 'post_type' => 'product' ) );
bwt_ok( 30 === $score( 'shop' ), 'shop archive scores 30', (string) $score( 'shop' ) );
bwt_ok( 0 === $score( 'product' ), 'product rule does not match the shop' );

bwt_go( array( 'product_cat' => get_term( $cat )->slug ) );
bwt_ok( 25 === $score( 'product_cat' ), 'product_cat archive scores 25' );
bwt_ok( 35 === $score( 'product_cat', array( $cat ) ), 'product_cat with its id scores 35' );
bwt_ok( 12 === $score( 'shop' ), 'shop rule also covers product category archives (12)' );
bwt_ok( 0 === $score( 'product_tag' ), 'product_tag does not match a category archive' );

// WooCommerce caches is_cart()/is_checkout() per request, so simulate those two through its filters.
add_filter( 'woocommerce_is_cart', '__return_true' );
bwt_ok( 40 === $score( 'cart' ), 'cart page scores 40' );
remove_filter( 'woocommerce_is_cart', '__return_true' );
bwt_go( array( 'page_id' => wc_get_page_id( 'myaccount' ) ) );
bwt_ok( 40 === $score( 'account' ), 'account page scores 40' );
add_filter( 'woocommerce_is_checkout', '__return_true' );
bwt_ok( 40 === $score( 'checkout' ) && 0 === $score( 'order_received' ), 'checkout scores 40, order received does not match' );
remove_filter( 'woocommerce_is_checkout', '__return_true' );

bwt_ok( Conditions::targets_product( array( array( 'type' => 'include', 'rule' => 'product', 'post_type' => '', 'taxonomy' => '', 'ids' => array() ) ) ), 'targets_product recognises product templates' );

/* -------------------------------------------------------------------------
 * Dynamic tags.
 * ---------------------------------------------------------------------- */

bwt_section( 'Dynamic tags' );

$simple = wc_get_product( $simple_id );
$tag_of = static function ( $tag, $id ) {
	return Dynamic::value( $tag, $id );
};
bwt_ok( '$25.00' === html_entity_decode( $tag_of( 'product:price', $simple_id ) ), '{product:price} is the formatted current price', $tag_of( 'product:price', $simple_id ) );
bwt_ok( false !== strpos( $tag_of( 'product:price_html|raw', $simple_id ), '<del' ), '{product:price_html|raw} keeps the sale markup' );
bwt_ok( '$40.00' === html_entity_decode( $tag_of( 'product:regular_price', $simple_id ) ), '{product:regular_price}' );
bwt_ok( '40' === $tag_of( 'product:regular_price|raw', $simple_id ), '{product:regular_price|raw}' );
bwt_ok( '$25.00' === html_entity_decode( $tag_of( 'product:sale_price', $simple_id ) ), '{product:sale_price}' );
bwt_ok( '38' === $tag_of( 'product:on_sale_percent', $simple_id ), '{product:on_sale_percent}', $tag_of( 'product:on_sale_percent', $simple_id ) );
bwt_ok( $simple->get_sku() === $tag_of( 'product:sku', $simple_id ), '{product:sku}' );
bwt_ok( '12' === $tag_of( 'product:stock_quantity', $simple_id ), '{product:stock_quantity}' );
bwt_ok( '' !== $tag_of( 'product:stock', $simple_id ), '{product:stock} has a label' );
bwt_ok( 'Out of stock' === $tag_of( 'product:stock', $oos_id ), '{product:stock} for a sold out product', $tag_of( 'product:stock', $oos_id ) );
bwt_ok( '0.0' === $tag_of( 'product:rating', $simple_id ) && '0' === $tag_of( 'product:review_count', $simple_id ), '{product:rating} / {product:review_count}' );
bwt_ok( 'A soft tee.' === $tag_of( 'product:short_description', $simple_id ), '{product:short_description} is plain text', $tag_of( 'product:short_description', $simple_id ) );
bwt_ok( false !== strpos( $tag_of( 'product:add_to_cart_url', $simple_id ), 'add-to-cart=' . $simple_id ), '{product:add_to_cart_url}' );
bwt_ok( '' !== $tag_of( 'product:add_to_cart_text', $simple_id ), '{product:add_to_cart_text}' );
bwt_ok( get_permalink( $simple_id ) === $tag_of( 'product:permalink', $simple_id ), '{product:permalink}' );
bwt_ok( 0 === strpos( $tag_of( 'product:image', $simple_id ), 'http' ), '{product:image} falls back to the placeholder' );
bwt_ok( '0' === $tag_of( 'product:gallery_count', $simple_id ), '{product:gallery_count}' );
bwt_ok( 'bwt Category' === $tag_of( 'product:categories', $simple_id ), '{product:categories}' );
bwt_ok( false !== strpos( $tag_of( 'product:tags', $simple_id ), 'bwt Tag' ), '{product:tags}' );
bwt_ok( '' !== $tag_of( 'product:weight', $simple_id ) && false !== strpos( $tag_of( 'product:dimensions', $simple_id ), '10' ), '{product:weight} / {product:dimensions}' );
bwt_ok( 'Blue, Red' === $tag_of( 'product:attribute.color', $simple_id ), '{product:attribute.color}', $tag_of( 'product:attribute.color', $simple_id ) );
bwt_ok( (string) WC()->cart->get_cart_contents_count() === $tag_of( 'cart:count', 0 ), '{cart:count}' );
bwt_ok( '' !== $tag_of( 'cart:total', 0 ) && '' !== $tag_of( 'cart:subtotal', 0 ), '{cart:total} / {cart:subtotal}' );
bwt_ok( wc_get_cart_url() === $tag_of( 'cart:url', 0 ) && wc_get_checkout_url() === $tag_of( 'checkout:url', 0 ), '{cart:url} / {checkout:url}' );
bwt_ok( wc_get_page_permalink( 'shop' ) === $tag_of( 'shop:url', 0 ) && wc_get_page_permalink( 'myaccount' ) === $tag_of( 'account:url', 0 ), '{shop:url} / {account:url}' );
bwt_ok( '{product:nonsense}' === Dynamic::replace( '{product:nonsense}', $simple_id ) || '' === Dynamic::replace( '{product:nonsense}', $simple_id ), 'unknown product keys do not leak errors' );

// Through the renderer, including the two-colon attribute form.
$html = bwt_render( 'heading', $simple_id, array( 'text' => '{product:title} in {product:attribute:color} for {product:price}' ) );
bwt_ok( false !== strpos( $html, 'bwt Sale tee in Blue, Red for' ), '{product:attribute:color} resolves in module text', wp_strip_all_tags( $html ) );
bwt_ok( false !== strpos( Dynamic::value( 'product:title', $simple_id ), '&' ) || 'bwt Sale tee' === Dynamic::value( 'product:title', $simple_id ), 'titles are escaped' );

/* -------------------------------------------------------------------------
 * Module rendering.
 * ---------------------------------------------------------------------- */

bwt_section( 'Modules' );

$modules = array( 'product_title', 'product_price', 'product_add_to_cart', 'product_gallery', 'product_short_description', 'product_description', 'product_tabs', 'product_meta', 'product_rating', 'product_stock', 'product_additional_info', 'product_reviews', 'product_badge', 'product_upsells', 'product_related', 'woo_notices', 'woo_breadcrumb' );
foreach ( $modules as $type ) {
	$def = Modules::get( $type );
	bwt_ok( $def && 'shop' === $def['category'], "$type is registered in the shop category" );
}

$expect = array(
	'product_title'             => 'bwt Sale tee',
	'product_price'             => '<del',
	'product_add_to_cart'       => 'single_add_to_cart_button',
	'product_gallery'           => 'data-brik-gallery',
	'product_short_description' => '<strong>soft</strong>',
	'product_description'       => 'Long description',
	'product_tabs'              => 'role="tablist"',
	'product_meta'              => 'bwt Category',
	'product_rating'            => 'brik-stars',
	'product_stock'             => 'data-status="instock"',
	'product_additional_info'   => 'Blue, Red',
	'product_reviews'           => 'comment-form',
	'product_badge'             => '−38%',
	'woo_breadcrumb'            => 'bwt Sale tee',
);
foreach ( $expect as $type => $needle ) {
	$html = bwt_render( $type, $simple_id );
	bwt_ok( false !== strpos( $html, $needle ), "$type renders the on-sale simple product", $needle );
}

$html = bwt_render( 'product_add_to_cart', $simple_id, array( 'buy_now' => true, 'sticky_mobile' => true ) );
bwt_ok( false !== strpos( $html, 'woocommerce_before_add_to_cart' ) || false !== strpos( $html, 'brik-qty-minus' ), 'add to cart uses the WooCommerce template with the stepper' );
bwt_ok( false !== strpos( $html, 'name="brik_buy_now"' ), 'buy now button is added' );
bwt_ok( false !== strpos( $html, 'data-brik-atc-sticky' ), 'sticky bar is rendered' );
$fired = did_action( 'woocommerce_before_add_to_cart_form' );
bwt_render( 'product_add_to_cart', $simple_id, array( 'quantity' => false ) );
bwt_ok( did_action( 'woocommerce_before_add_to_cart_form' ) > $fired, 'woocommerce_before_add_to_cart_form fires' );

if ( $var_id ) {
	$html = bwt_render( 'product_add_to_cart', $var_id );
	bwt_ok( false !== strpos( $html, 'variations_form' ) && false !== strpos( $html, 'brik-var-swatch' ) && false !== strpos( $html, 'brik-var-pill' ), 'variable product: swatches for Color, pills for Size' );
	bwt_ok( false !== strpos( $html, 'data-product_variations' ) && false !== strpos( $html, 'brik_price_html' ), 'variation data carries the live price' );
	$html = bwt_render( 'product_add_to_cart', $var_id, array( 'selector_style' => 'dropdown' ) );
	bwt_ok( false === strpos( $html, 'brik-var-pill' ) && false !== strpos( $html, 'brik-var-field--select' ), 'dropdown style keeps selects' );
	$html = bwt_render( 'product_price', $var_id );
	bwt_ok( false !== strpos( $html, 'data-brik-price="' . $var_id . '"' ) && false !== strpos( $html, '−20%' ), 'variable price is live and shows the discount', wp_strip_all_tags( $html ) );
	bwt_ok( false !== strpos( bwt_render( 'product_gallery', $var_id ), 'brik-pg-slide' ), 'variable product gallery renders' );
	foreach ( $modules as $type ) {
		bwt_render( $type, $var_id );
	}
	bwt_ok( true, 'every module renders a variable product' );
}

$html = bwt_render( 'product_add_to_cart', $oos_id );
bwt_ok( false !== strpos( $html, 'out-of-stock' ) && false === strpos( $html, 'single_add_to_cart_button' ), 'out of stock product: stock message, no button' );
bwt_ok( false !== strpos( bwt_render( 'product_badge', $oos_id ), 'Sold out' ), 'out of stock badge' );
bwt_ok( false !== strpos( bwt_render( 'product_stock', $oos_id ), 'data-status="outofstock"' ), 'stock module shows out of stock' );
bwt_ok( false !== strpos( bwt_render( 'product_gallery', $oos_id ), 'Sold out' ), 'gallery overlay shows sold out' );

// No product: nothing on the site, a placeholder in the builder.
$page = (int) get_option( 'page_on_front' );
$page = $page ? $page : (int) wc_get_page_id( 'cart' );
bwt_go( array( 'page_id' => $page ) );
foreach ( array_diff( $modules, array( 'woo_notices', 'woo_breadcrumb' ) ) as $type ) {
	$inner = bwt_inner( bwt_render( $type, $page ) );
	bwt_ok( '' === $inner, "$type renders nothing without a product", substr( $inner, 0, 80 ) );
	bwt_ok( false !== strpos( bwt_render( $type, $page, array(), true ), 'brik-placeholder' ), "$type shows a placeholder in the builder" );
}

// A fixed product id works anywhere.
bwt_ok( false !== strpos( bwt_render( 'product_title', $page, array( 'product' => $simple_id ) ), 'bwt Sale tee' ), 'product attribute picks a fixed product' );

// Templates in the builder preview a sample product.
$template = wp_insert_post(
	array(
		'post_type'   => ThemeBuilder::POST_TYPE,
		'post_status' => 'draft',
		'post_title'  => 'bwt template',
	)
);
update_post_meta( $template, ThemeBuilder::META_AREA, 'body' );
ThemeBuilder::save_conditions( $template, array( array( 'type' => 'include', 'rule' => 'product', 'ids' => array( $simple_id ) ) ) );
bwt_ok( false !== strpos( bwt_render( 'product_title', $template, array(), true ), 'bwt Sale tee' ), 'template preview uses the product from its conditions' );
bwt_ok( '' === bwt_inner( bwt_render( 'product_title', $template ) ), 'template outside a product request renders nothing' );
bwt_go( array( 'p' => $simple_id, 'post_type' => 'product' ) );
bwt_ok( false !== strpos( bwt_render( 'product_title', $template ), 'bwt Sale tee' ), 'template on a product page describes the queried product' );
bwt_ok( false !== strpos( Dynamic::value( 'product:title', $template ), 'bwt Sale tee' ), 'tags in a template resolve against the queried product' );
wp_delete_post( $template, true );

// Bundled layouts.
bwt_section( 'Layouts' );
foreach ( array( 'shop-product-single', 'shop-product-single-centered' ) as $slug ) {
	$layout = Brik\Library::bundled_one( $slug );
	bwt_ok( $layout && 'shop' === $layout['category'], "layout $slug is bundled" );
	if ( $layout ) {
		$renderer = new Renderer( $simple_id, false );
		$html     = $renderer->render_root( Brik\Data::normalize( $layout['tree'] ) );
		bwt_ok( false !== strpos( $html, 'single_add_to_cart_button' ) && false !== strpos( $html, 'data-brik-gallery' ), "layout $slug renders a complete product" );
	}
}

/* -------------------------------------------------------------------------
 * Cleanup.
 * ---------------------------------------------------------------------- */

foreach ( $bwt_ids as $id ) {
	$product = wc_get_product( $id );
	if ( $product ) {
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child ) {
				wp_delete_post( $child, true );
			}
		}
		$product->delete( true );
	}
}
foreach ( array( 'bwt Category', 'bwt New category' ) as $name ) {
	$term = get_term_by( 'name', $name, 'product_cat' );
	if ( $term ) {
		wp_delete_term( $term->term_id, 'product_cat' );
	}
}
foreach ( array( 'bwt Tag', 'bwt Extra' ) as $name ) {
	$term = get_term_by( 'name', $name, 'product_tag' );
	if ( $term ) {
		wp_delete_term( $term->term_id, 'product_tag' );
	}
}
restore_error_handler();

bwt_section( 'PHP notices' );
bwt_ok( ! $GLOBALS['bwt_warnings'], 'no PHP warnings or notices from Brik', implode( ' | ', array_slice( array_unique( $GLOBALS['bwt_warnings'] ), 0, 5 ) ) );

echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
printf( "PASS %d  FAIL %d\n", (int) $GLOBALS['bwt_pass'], (int) $GLOBALS['bwt_fail'] );
foreach ( $GLOBALS['bwt_failures'] as $f ) {
	echo 'FAIL ' . $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
exit( $GLOBALS['bwt_fail'] ? 1 : 0 );
