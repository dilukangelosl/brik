<?php
/**
 * Listing, listing filter and form action tests. Runs inside WordPress through WP-CLI:
 *
 *   docker compose -f dev/docker-compose.yml run --rm -T cli eval-file \
 *     /var/www/html/wp-content/plugins/brik-builder/tests/content/listing-forms.php
 *
 * Fixtures use the "blf_" prefix (a post type, a taxonomy, posts and users registered for
 * this run only) and are removed at the end. Exits non-zero when an assertion fails.
 *
 * @package Brik
 */

use Brik\Forms;
use Brik\Renderer;

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/user.php';

$blf_pass     = 0;
$blf_failures = array();

$ok = static function ( $cond, $name, $detail = '' ) use ( &$blf_pass, &$blf_failures ) {
	if ( $cond ) {
		++$blf_pass;
		echo '  ok   ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return;
	}
	$blf_failures[] = $name . ( '' !== $detail ? ' — ' . $detail : '' );
	echo '  FAIL ' . $name . ( '' !== $detail ? ' — ' . $detail : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
};
$eq = static function ( $expected, $actual, $name ) use ( $ok ) {
	$ok( $expected === $actual, $name, 'expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) );
};
$section = static function ( $name ) {
	echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
};

/* -------------------------------------------------------------------------
 * Fixtures.
 * ---------------------------------------------------------------------- */

$cleanup = static function () {
	global $wpdb;
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'blf_item' OR post_title LIKE 'BLF %'" ) as $id ) { // phpcs:ignore WordPress.DB
		wp_delete_post( (int) $id, true );
	}
	foreach ( get_terms( array( 'taxonomy' => 'blf_kind', 'hide_empty' => false, 'fields' => 'ids' ) ) as $term ) {
		if ( is_int( $term ) ) {
			wp_delete_term( $term, 'blf_kind' );
		}
	}
	foreach ( array( 'blf_sub', 'blf_author' ) as $login ) {
		$user = get_user_by( 'login', $login );
		if ( $user ) {
			wp_delete_user( $user->ID );
		}
	}
};

register_taxonomy( 'blf_kind', array(), array( 'public' => true, 'hierarchical' => true ) );
register_post_type( 'blf_item', array( 'public' => true, 'supports' => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ), 'taxonomies' => array( 'blf_kind' ) ) );
register_taxonomy_for_object_type( 'blf_kind', 'blf_item' );
$cleanup();

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$admin = (int) $admin[0];
$sub   = wp_insert_user( array( 'user_login' => 'blf_sub', 'user_email' => 'blf_sub@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$auth  = wp_insert_user( array( 'user_login' => 'blf_author', 'user_email' => 'blf_author@example.com', 'user_pass' => wp_generate_password(), 'role' => 'author' ) );

$kinds = array();
foreach ( array( 'alpha', 'beta', 'gamma' ) as $slug ) {
	$term           = wp_insert_term( ucfirst( $slug ), 'blf_kind', array( 'slug' => $slug ) );
	$kinds[ $slug ] = (int) $term['term_id'];
}
$items = array();
foreach ( range( 1, 9 ) as $n ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'blf_item',
			'post_status' => 'publish',
			'post_title'  => 'BLF item ' . $n,
			'post_date'   => sprintf( '2024-01-%02d 10:00:00', $n ),
			'post_author' => $admin,
		)
	);
	update_post_meta( $id, 'blf_price', $n * 10 );
	update_post_meta( $id, 'blf_color', 0 === $n % 2 ? 'red' : 'blue' );
	wp_set_object_terms( $id, array( $kinds[ array( 'alpha', 'beta', 'gamma' )[ $n % 3 ] ] ), 'blf_kind' );
	$items[ $n ] = $id;
}
update_post_meta( $items[1], 'blf_related', array( $items[2], $items[3] ) );
brik_listing_cache_bump();

$titles = static function ( array $args ) {
	return wp_list_pluck( ( new WP_Query( $args ) )->posts, 'post_title' );
};

/* -------------------------------------------------------------------------
 * Query building from attributes.
 * ---------------------------------------------------------------------- */

$section( 'Query building' );

$base = array( 'post_type' => 'blf_item', 'posts_per_page' => 4, 'orderby' => 'date', 'order' => 'DESC' );
$args = brik_listing_query_args( $base );
$eq( 'blf_item', $args['post_type'], 'custom post type kept' );
$eq( 'publish', $args['post_status'], 'only published posts' );
$eq( 4, $args['posts_per_page'], 'per page' );
$eq( array( 'BLF item 9', 'BLF item 8', 'BLF item 7', 'BLF item 6' ), $titles( $args ), 'newest first' );

$args = brik_listing_query_args( array_merge( $base, array( 'offset' => 1 ) ), array( 'page' => 2 ) );
$eq( 5, $args['offset'], 'offset + page 2 = 1 + 4' );
$eq( 'post', brik_listing_query_args( array( 'post_type' => 'not_a_type' ) )['post_type'], 'unknown post type falls back to post' );
$eq( 100, brik_listing_query_args( array( 'posts_per_page' => 5000 ) )['posts_per_page'], 'per page capped at 100' );
$eq( 9, brik_listing_query_args( array() )['posts_per_page'], 'empty attrs give defaults (no warnings)' );

$args = brik_listing_query_args( array_merge( $base, array( 'taxonomy' => 'blf_kind', 'terms' => 'alpha, gamma' ) ) );
$eq( 'slug', $args['tax_query'][0]['field'], 'terms by slug' );
$eq( 6, count( $titles( array_merge( $args, array( 'posts_per_page' => 50 ) ) ) ), 'alpha + gamma → 6 items' );
$args = brik_listing_query_args( array_merge( $base, array( 'taxonomy' => 'blf_kind', 'terms' => (string) $kinds['beta'] ) ) );
$eq( 'term_id', $args['tax_query'][0]['field'], 'terms by id' );
$args = brik_listing_query_args( array_merge( $base, array( 'taxonomy' => 'blf_kind', 'terms' => 'alpha', 'terms_operator' => 'NOT IN', 'posts_per_page' => 50 ) ) );
$eq( 6, count( $titles( $args ) ), 'NOT IN excludes the term' );

$meta = static function ( array $rows, $relation = 'AND' ) use ( $base ) {
	return brik_listing_query_args( array_merge( $base, array( 'posts_per_page' => 50, 'meta_query' => $rows, 'meta_relation' => $relation ) ) );
};
$eq( array( 'BLF item 9', 'BLF item 8', 'BLF item 7' ), $titles( $meta( array( array( 'field' => 'blf_price', 'compare' => '>=', 'value' => '70', 'type' => 'NUMERIC' ) ) ) ), 'meta >= NUMERIC' );
$eq( 3, count( $titles( $meta( array( array( 'field' => 'blf_price', 'compare' => 'BETWEEN', 'value' => '20, 40', 'type' => 'NUMERIC' ) ) ) ) ), 'meta BETWEEN' );
$eq( 4, count( $titles( $meta( array( array( 'field' => 'blf_color', 'compare' => '=', 'value' => 'red' ) ) ) ) ), 'meta = CHAR' );
$eq( 2, count( $titles( $meta( array( array( 'field' => 'blf_price', 'compare' => 'IN', 'value' => '10,90', 'type' => 'NUMERIC' ) ) ) ) ), 'meta IN' );
$eq( 1, count( $titles( $meta( array( array( 'field' => 'blf_related', 'compare' => 'EXISTS' ) ) ) ) ), 'meta EXISTS' );
$eq( 5, count( $titles( $meta( array( array( 'field' => 'blf_color', 'compare' => '=', 'value' => 'red' ), array( 'field' => 'blf_price', 'compare' => '=', 'value' => '10', 'type' => 'NUMERIC' ) ), 'OR' ) ) ), 'meta relation OR' );
$ok( ! isset( $meta( array( array( 'field' => 'blf_price', 'compare' => '; DROP TABLE', 'value' => '1' ) ) )['meta_query'] ), 'unknown compare operator dropped' );
$ok( ! isset( $meta( array( array( 'field' => "blf_price' OR 1=1 --", 'compare' => '=', 'value' => '1' ) ) )['meta_query'] ), 'field name with SQL rejected' );
$ok( ! isset( $meta( array( array( 'field' => '_edit_lock', 'compare' => 'EXISTS' ) ) )['meta_query'] ), 'protected meta key rejected' );
$ok( ! isset( $meta( array( array( 'field' => 'blf_price', 'compare' => '>', 'value' => '1 OR 1=1', 'type' => 'NUMERIC' ) ) )['meta_query'] ), 'non-numeric NUMERIC value rejected' );
$ok( ! isset( $meta( array( array( 'field' => 'blf_price', 'compare' => 'BETWEEN', 'value' => '10', 'type' => 'NUMERIC' ) ) )['meta_query'] ), 'BETWEEN needs two values' );

$args = brik_listing_query_args( array_merge( $base, array( 'orderby' => 'meta_value_num', 'orderby_field' => 'blf_price', 'order' => 'ASC' ) ) );
$eq( 'blf_price', $args['meta_key'], 'order by field sets meta_key' );
$eq( array( 'BLF item 1', 'BLF item 2', 'BLF item 3', 'BLF item 4' ), $titles( $args ), 'order by number field ASC' );
$args = brik_listing_query_args( array_merge( $base, array( 'orderby' => 'meta_value_num', 'orderby_field' => 'bad key!' ) ) );
$eq( 'date', $args['orderby'], 'order by invalid field falls back to date' );
$eq( 'date', brik_listing_query_args( array( 'orderby' => 'post_title DESC; --' ) )['orderby'], 'unknown orderby rejected' );

$args = brik_listing_query_args( array_merge( $base, array( 'search' => 'item 7' ) ) );
$eq( 'item 7', $args['s'], 'search' );

wp_set_current_user( 0 );
$eq( array( 0 ), brik_listing_query_args( array( 'author' => 'current' ) )['author__in'], 'author current for a visitor matches nobody' );
wp_set_current_user( $admin );
$eq( array( $admin ), brik_listing_query_args( array( 'author' => 'current' ) )['author__in'], 'author current' );
$eq( array( 3, 4 ), brik_listing_query_args( array( 'author' => 'ids', 'author_ids' => '3, x, 4' ) )['author__in'], 'author ids' );

$current = get_post( $items[1] );
$args    = brik_listing_query_args( array_merge( $base, array( 'exclude_current' => true ) ), array( 'current' => $current ) );
$eq( array( $items[1] ), $args['post__not_in'], 'exclude current' );
$args = brik_listing_query_args( array_merge( $base, array( 'related' => true, 'related_by' => 'terms', 'related_taxonomy' => 'blf_kind', 'posts_per_page' => 50 ) ), array( 'current' => $current ) );
$eq( array( 'BLF item 7', 'BLF item 4' ), $titles( $args ), 'related by shared terms (excludes itself)' );
$args = brik_listing_query_args( array_merge( $base, array( 'related' => true, 'related_by' => 'field', 'related_field' => 'blf_related' ) ), array( 'current' => $current ) );
$eq( array( $items[2], $items[3] ), $args['post__in'], 'related by relationship field' );
$args = brik_listing_query_args( array_merge( $base, array( 'related' => true, 'related_by' => 'field', 'related_field' => 'blf_related' ) ), array( 'current' => get_post( $items[5] ) ) );
$eq( array( 0 ), $args['post__in'], 'related field without ids matches nothing' );

$main = brik_listing_main_args( array( 'taxonomy' => 'blf_kind', 'term' => $kinds['alpha'], 'post_type' => 'blf_item' ) );
$eq( $kinds['alpha'], $main['tax_query'][0]['terms'][0], 'main query context: term archive' );
$main = brik_listing_main_args( array( 'taxonomy' => 'blf_kind', 'term' => 999999 ) );
$ok( ! isset( $main['tax_query'] ), 'main query context ignores unknown terms' );

/* -------------------------------------------------------------------------
 * Filter definitions and parameter validation.
 * ---------------------------------------------------------------------- */

$section( 'Filter parameters' );

$defs = brik_listing_filter_defs(
	array(
		array( 'type' => 'search' ),
		array( 'type' => 'taxonomy', 'taxonomy' => 'blf_kind', 'ui' => 'checkboxes' ),
		array( 'type' => 'taxonomy', 'taxonomy' => 'blf_kind', 'ui' => 'pills' ),
		array( 'type' => 'field', 'field' => 'blf_price', 'ui' => 'range' ),
		array( 'type' => 'field', 'field' => 'blf_color', 'ui' => 'select' ),
		array( 'type' => 'field', 'field' => '_secret', 'ui' => 'select' ),
		array( 'type' => 'field', 'field' => 'x" onmouseover=', 'ui' => 'select' ),
		array( 'type' => 'sort', 'field' => 'blf_price' ),
		array( 'type' => 'reset' ),
		'garbage',
	)
);
$eq( array( 'search', 'blf_kind', 'blf_kind_2', 'blf_price', 'blf_color', 'sort', 'reset' ), wp_list_pluck( $defs, 'key' ), 'defs normalized with unique keys; bad field names dropped' );

$p = static function ( array $params ) use ( $defs ) {
	return brik_listing_parse( $defs, $params, 'grid', 'blf_item' );
};

list( $clauses, $state ) = $p( array( 'bf_grid_search' => "item'; DROP TABLE wp_posts; --" ) );
$eq( 'search', $clauses[0]['kind'], 'search clause' );
$ok( false === strpos( $clauses[0]['value'], '<' ), 'search value sanitized' );
$ok( is_array( ( new WP_Query( brik_listing_query_args( $base, array( 'clauses' => $clauses ) ) ) )->posts ), 'hostile search runs as a normal prepared query' );

list( $clauses, $state ) = $p( array( 'bf_grid_blf_kind' => 'alpha,gamma,nope,../../etc' ) );
$eq( array( $kinds['alpha'], $kinds['gamma'] ), $clauses[0]['terms'], 'taxonomy slugs → term ids, unknown dropped' );
$eq( array( 'alpha', 'gamma' ), $state['blf_kind'], 'state for the controls' );
list( $clauses ) = $p( array( 'bf_grid_blf_kind_2' => array( 'beta', 'alpha' ) ) );
$eq( array( $kinds['beta'] ), $clauses[0]['terms'], 'single-choice control keeps one value' );
list( $clauses ) = brik_listing_parse( $defs, array( 'bf_grid_blf_kind' => 'alpha' ), 'grid', 'post' );
$eq( array(), $clauses, 'taxonomy not attached to the post type is ignored' );

list( $clauses ) = $p( array( 'bf_grid_blf_price_min' => '30', 'bf_grid_blf_price_max' => '1 OR 1=1' ) );
$eq( array( 'kind' => 'range', 'key' => 'blf_price', 'min' => 30.0, 'max' => null, 'type' => 'NUMERIC' ), $clauses[0], 'range: numeric min kept, junk max dropped' );
$found = $titles( brik_listing_query_args( array_merge( $base, array( 'posts_per_page' => 50 ) ), array( 'clauses' => $clauses ) ) );
$eq( 7, count( $found ), 'range ≥ 30 → 7 items' );

list( $clauses ) = $p( array( 'bf_grid_blf_color' => 'red' ) );
$eq( 4, count( $titles( brik_listing_query_args( array_merge( $base, array( 'posts_per_page' => 50 ) ), array( 'clauses' => $clauses ) ) ) ), 'field select filter' );

list( $clauses ) = $p( array( 'bf_grid_sort' => 'field_desc' ) );
$args = brik_listing_query_args( $base, array( 'clauses' => $clauses ) );
$eq( array( 'meta_value_num', 'DESC', 'blf_price' ), array( $args['orderby'], $args['order'], $args['meta_key'] ), 'sort by field from the filter' );
list( $clauses ) = $p( array( 'bf_grid_sort' => 'ID; DELETE' ) );
$eq( array(), $clauses, 'unknown sort value rejected' );

list( $clauses ) = $p( array( 'bf_grid__secret' => 'x', 'bf_grid_secret' => 'x', 'bf_other_search' => 'x', 'bf_grid_post_status' => 'draft' ) );
$eq( array(), $clauses, 'parameters without a definition are ignored' );

$ok( 'grid' === brik_listing_key( '#Grid', 'abc' ) && 'abcd1234' === brik_listing_key( '', 'abcd1234' ) && 'evilscript' === brik_listing_key( 'evil"><script', 'x' ), 'listing keys are sanitized' );

$values = brik_listing_meta_values( 'blf_item', 'blf_color' );
$eq( array( 'blue' => 5, 'red' => 4 ), $values, 'distinct values with counts' );
$eq( array( 10.0, 90.0 ), brik_listing_meta_range( 'blf_item', 'blf_price' ), 'numeric range' );
$eq( array(), brik_listing_meta_values( 'blf_item', "x' OR '1" ), 'distinct values reject bad keys' );

/* -------------------------------------------------------------------------
 * Rendering and the REST endpoint.
 * ---------------------------------------------------------------------- */

$section( 'Rendering and REST' );

$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'BLF listing page' ) );
Brik\Data::save(
	$page,
	array(
		array( 'type' => 'section', 'children' => array( array( 'type' => 'row', 'children' => array( array( 'type' => 'column', 'children' => array(
			array( 'id' => 'blffilt1', 'type' => 'listing_filter', 'attrs' => array( 'target' => 'blf', 'filters' => array( array( 'type' => 'taxonomy', 'taxonomy' => 'blf_kind', 'ui' => 'pills' ), array( 'type' => 'field', 'field' => 'blf_price', 'ui' => 'range' ) ) ) ),
			array( 'id' => 'blflist1', 'type' => 'listing', 'attrs' => array( 'css_id' => 'blf', 'post_type' => 'blf_item', 'posts_per_page' => 4, 'pagination' => 'numbered' ) ),
		) ) ) ) ) ),
	)
);
$r    = new Renderer( $page );
$html = $r->render_root( Brik\Data::get( $page ) );
$ok( 4 === substr_count( $html, 'class="brik-listing-item"' ), 'server render shows one page of items' );
$ok( false !== strpos( $html, 'data-brik-listing="blf"' ) && false !== strpos( $html, 'id="blf"' ), 'listing addressable by css id' );
$ok( false !== strpos( $html, 'name="bf_blf_blf_kind"' ) && false !== strpos( $html, 'name="bf_blf_blf_price_min"' ), 'filter inputs use bf_{listing}_{filter} names' );
$ok( false !== strpos( $html, 'bf_blf_page=2' ), 'numbered pagination links' );

$rest = static function ( array $body ) {
	$req = new WP_REST_Request( 'POST', '/brik/v1/listing' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_do_request( $req );
};
wp_set_current_user( 0 );
$res = $rest( array( 'post_id' => $page, 'node_id' => 'blflist1', 'filters' => array( 'bf_blf_blf_kind' => 'beta' ), 'page' => 1 ) );
$eq( 200, $res->get_status(), 'REST listing responds to visitors' );
$eq( array( 3, 1, 1 ), array( $res->get_data()['total'], $res->get_data()['pages'], $res->get_data()['page'] ), 'REST filters by term' );
$res = $rest( array( 'post_id' => $page, 'node_id' => 'blflist1', 'filters' => array( 'bf_blf_blf_price_min' => '50', 'bf_blf_unknown' => '1', 'bf_blf_search' => 'zzz' ), 'page' => 2 ) );
$eq( array( 5, 2, 2 ), array( $res->get_data()['total'], $res->get_data()['pages'], $res->get_data()['page'] ), 'REST range + page 2; undeclared filters ignored' );
$res = $rest( array( 'post_id' => $page, 'node_id' => 'blffilt1' ) );
$eq( 404, $res->get_status(), 'REST only answers for listing nodes' );
wp_update_post( array( 'ID' => $page, 'post_status' => 'draft' ) );
$res = $rest( array( 'post_id' => $page, 'node_id' => 'blflist1' ) );
$eq( 404, $res->get_status(), 'REST refuses listings on unpublished pages' );
wp_set_current_user( $admin );

/* -------------------------------------------------------------------------
 * Form actions: mapping and permissions.
 * ---------------------------------------------------------------------- */

$section( 'Form actions' );

$eq( array( 'email', 'save_entry' ), brik_form_actions( array( 'send_email' => true, 'save_entries' => true, 'webhook_url' => '', 'redirect' => '' ) ), 'legacy toggles still decide without an actions list' );
$eq( array( 'save_entry', 'webhook', 'redirect' ), brik_form_actions( array( 'send_email' => false, 'save_entries' => '1', 'webhook_url' => 'https://x.test', 'redirect' => '/thanks' ) ), 'legacy webhook + redirect' );
$eq( array( 'save_entry', 'create_post' ), brik_form_actions( array( 'actions' => 'create_post, save_entry, hack' ) ), 'actions as comma string; unknown dropped' );
$eq( array( 'update_post' ), brik_form_actions( array( 'actions' => array( 'update_post' ), 'send_email' => true ) ), 'explicit actions win over toggles' );

$map = brik_form_mapping(
	array(
		'mapping' => array(
			array( 'field' => 'Title', 'target' => 'title' ),
			array( 'field' => 'price', 'target' => 'meta:blf_price' ),
			array( 'field' => 'kind', 'target' => 'tax:blf_kind' ),
			array( 'field' => 'x', 'target' => 'meta:_thumbnail_id' ),
			array( 'field' => 'y', 'target' => 'meta:a b' ),
			array( 'field' => 'z', 'target' => 'post_status' ),
		),
	)
);
$eq( array( array( 'title', 'title', '' ), array( 'price', 'meta', 'blf_price' ), array( 'kind', 'tax', 'blf_kind' ) ), $map, 'mapping validated; protected and unknown targets dropped' );

$form = array(
	'form_name'   => 'BLF form',
	'post_type'   => 'blf_item',
	'post_status' => 'pending',
	'post_author' => 'current',
	'mapping'     => array(
		array( 'field' => 'title', 'target' => 'title' ),
		array( 'field' => 'body', 'target' => 'content' ),
		array( 'field' => 'summary', 'target' => 'excerpt' ),
		array( 'field' => 'price', 'target' => 'meta:blf_price' ),
		array( 'field' => 'color', 'target' => 'meta:blf_color' ),
		array( 'field' => 'kind', 'target' => 'tax:blf_kind' ),
		array( 'field' => 'tag', 'target' => 'tax:post_tag' ),
	),
);
$schema = brik_form_schema(
	array(
		array( 'label' => 'Title', 'name' => 'title', 'type' => 'text', 'required' => true ),
		array( 'label' => 'Body', 'name' => 'body', 'type' => 'textarea' ),
		array( 'label' => 'Summary', 'name' => 'summary', 'type' => 'textarea' ),
		array( 'label' => 'Price', 'name' => 'price', 'type' => 'number' ),
		array( 'label' => 'Color', 'name' => 'color', 'type' => 'text' ),
		array( 'label' => 'Kind', 'name' => 'kind', 'type' => 'select', 'options_source' => 'taxonomy:blf_kind' ),
		array( 'label' => 'Tag', 'name' => 'tag', 'type' => 'text' ),
		array( 'label' => 'Photo', 'name' => 'photo', 'type' => 'image' ),
	)
);
$eq( array( (string) $kinds['alpha'], (string) $kinds['beta'], (string) $kinds['gamma'] ), $schema[5]['options'], 'options from a taxonomy are term ids' );

list( $values, $errors ) = Forms::validate( $schema, array( 'title' => 'BLF created', 'kind' => '999999' ) );
$eq( 'Please choose one of the options.', isset( $errors['kind'] ) ? $errors['kind'] : '', 'sourced select rejects ids that are not options' );

$fake = array( 'photo' => array( 'name' => 'x.png', 'type' => 'image/png', 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK, 'size' => 10 ) );
list( $values, $errors ) = Forms::validate( $schema, array( 'title' => 'BLF created' ), $fake );
$ok( isset( $errors['photo'] ), 'a file that was not uploaded through PHP is rejected' );

list( $values, $errors ) = Forms::validate(
	$schema,
	array(
		'title'   => 'BLF created <script>x</script>',
		'body'    => "Line one\n\nLine two",
		'summary' => 'Short',
		'price'   => '42',
		'color'   => '<b>green</b>',
		'kind'    => (string) $kinds['beta'],
		'tag'     => 'whatever',
	)
);
$eq( array(), $errors, 'valid submission' );
$eq( 'Beta', $values['kind']['value'], 'sourced option shows its label' );
$eq( (string) $kinds['beta'], $values['kind']['raw'], 'and keeps the id for mapping' );

$created = Forms::save_post( $form, $values, $schema, array() );
$ok( is_int( $created ) && $created > 0, 'create_post returns the new id' );
$post = get_post( $created );
$eq( array( 'blf_item', 'pending', $admin ), array( $post->post_type, $post->post_status, (int) $post->post_author ), 'type, status and author' );
$eq( 'BLF created', $post->post_title, 'title mapped and sanitized' );
$ok( false !== strpos( $post->post_content, '<p>Line one</p>' ), 'content mapped' );
$eq( 'Short', $post->post_excerpt, 'excerpt mapped' );
$eq( '42', get_post_meta( $created, 'blf_price', true ), 'meta mapped' );
$eq( 'green', get_post_meta( $created, 'blf_color', true ), 'plain meta saved as sanitized text' );
$eq( array( 'beta' ), wp_get_object_terms( $created, 'blf_kind', array( 'fields' => 'slugs' ) ), 'terms assigned by id' );
$eq( array(), wp_get_object_terms( $created, 'post_tag', array( 'fields' => 'slugs' ) ), 'taxonomy not on the post type is ignored' );
$ok( ! Forms::save_terms( $created, 'blf_item', 'blf_kind', array( 'Brand new term' ) ) && ! term_exists( 'Brand new term', 'blf_kind' ), 'forms never create terms' );

if ( function_exists( 'brik_update_field' ) && function_exists( 'brik_field_object' ) ) {
	$ok( null === brik_field_object( 'blf_price', $created ), 'unregistered field goes to plain meta (content API present)' );
}

$values['title']['value'] = 'BLF updated';
$values['price']['value'] = '43';
$updated                  = Forms::save_post( $form, $values, $schema, array(), $created );
$eq( $created, $updated, 'update_post keeps the id' );
$eq( array( 'BLF updated', '43', 'pending' ), array( get_post( $created )->post_title, get_post_meta( $created, 'blf_price', true ), get_post_status( $created ) ), 'update maps values and keeps the status' );

$prefill = brik_form_prefill( $form, $created );
$eq( 'BLF updated', $prefill['title'], 'prefill title' );
$eq( '43', $prefill['price'], 'prefill meta' );
$filled = brik_form_apply_prefill( $schema, $prefill );
$eq( array( (string) $kinds['beta'] ), $filled[5]['prefill'], 'prefill terms as ids for sourced options' );

// Permissions.
$guest = array_merge( $form, array( 'allow_guests' => false ) );
wp_set_current_user( 0 );
$eq( 'brik_form_login', is_wp_error( brik_form_can_create( $guest ) ) ? brik_form_can_create( $guest )->get_error_code() : 'allowed', 'guests blocked unless allow_guests' );
$eq( true, brik_form_can_create( array_merge( $form, array( 'allow_guests' => true ) ) ), 'guests allowed with allow_guests' );
$ok( is_wp_error( brik_form_can_update( $form, $created ) ), 'guests can never update' );
wp_set_current_user( $sub );
$eq( 'brik_form_forbidden', is_wp_error( brik_form_can_create( $guest ) ) ? brik_form_can_create( $guest )->get_error_code() : 'allowed', 'subscriber without create capability blocked' );
$ok( is_wp_error( brik_form_can_update( $form, $created ) ), "subscriber can't edit someone else's post" );
wp_set_current_user( $auth );
$eq( true, brik_form_can_create( $guest ), 'author may create' );
$ok( is_wp_error( brik_form_can_update( $form, $created ) ), "author can't edit an admin's post" );
wp_set_current_user( $admin );
$eq( true, brik_form_can_update( $form, $created ), 'admin may update' );
$ok( is_wp_error( brik_form_can_update( array_merge( $form, array( 'post_type' => 'page' ) ), $created ) ), 'update refused when the post type differs' );
$ok( is_wp_error( brik_form_can_create( array_merge( $form, array( 'post_type' => 'brik_submission', 'allow_guests' => true ) ) ) ), 'internal post types refused' );
$ok( is_wp_error( brik_form_can_create( array_merge( $form, array( 'post_type' => 'nope', 'allow_guests' => true ) ) ) ), 'unknown post type refused' );
$eq( 'pending', brik_form_post_settings( array( 'post_status' => 'trash' ) )['post_status'], 'unknown status falls back to pending' );

$eq( 'subscriber', brik_form_safe_role( 'administrator' ), 'administrator role never granted' );
$eq( 'subscriber', brik_form_safe_role( 'editor' ), 'editor role never granted' );
$eq( 'subscriber', brik_form_safe_role( 'made_up' ), 'unknown role → subscriber' );
$eq( 'author', brik_form_safe_role( 'author' ), 'author role allowed' );
add_role( 'blf_power', 'BLF power', array( 'read' => true, 'manage_options' => true ) );
$eq( 'subscriber', brik_form_safe_role( 'blf_power' ), 'custom role with admin capabilities refused' );
remove_role( 'blf_power' );

/* -------------------------------------------------------------------------
 * Done.
 * ---------------------------------------------------------------------- */

wp_set_current_user( 0 );
$cleanup();
unregister_post_type( 'blf_item' );
unregister_taxonomy( 'blf_kind' );

echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
printf( "PASS %d  FAIL %d\n", (int) $blf_pass, count( $blf_failures ) );
foreach ( $blf_failures as $failure ) {
	echo '  ' . $failure . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
exit( $blf_failures ? 1 : 0 );
