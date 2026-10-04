<?php
/**
 * Data feature test suite (dynamic tags, sources, visual queries, display conditions).
 * Runs inside WordPress through WP-CLI:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/data/run.php
 *
 * ACF (free) should be active for the ACF sections: ./dev/wp.sh plugin install advanced-custom-fields --activate
 * Everything it creates uses the "btd" prefix and is removed at the end.
 *
 * @package Brik
 */

use Brik\Data\Conditions;
use Brik\Data\Plugins;
use Brik\Data\Query;
use Brik\Data\Sources;
use Brik\Data\Tags;
use Brik\Dynamic;

defined( 'ABSPATH' ) || exit;

final class Brik_Data_Test_Runner {

	private $pass = 0;

	private $fail = 0;

	private $section = '';

	private $failures = array();

	public function section( $name ) {
		$this->section = $name;
		echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function ok( $condition, $name, $detail = '' ) {
		if ( $condition ) {
			++$this->pass;
			echo '  PASS ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
		++$this->fail;
		$line             = '  FAIL ' . $name . ( '' !== $detail ? ' — ' . $detail : '' );
		$this->failures[] = '[' . $this->section . '] ' . trim( $line );
		echo $line . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	}

	public function eq( $expected, $actual, $name ) {
		$same = $expected === $actual;
		return $this->ok( $same, $name, $same ? '' : 'expected ' . self::show( $expected ) . ', got ' . self::show( $actual ) );
	}

	public static function show( $v ) {
		$out = is_object( $v ) ? get_class( $v ) : wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return strlen( (string) $out ) > 400 ? substr( $out, 0, 400 ) . '…' : (string) $out;
	}

	public function finish() {
		echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		printf( "PASS %d  FAIL %d\n", (int) $this->pass, (int) $this->fail );
		foreach ( $this->failures as $f ) {
			echo $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		exit( $this->fail ? 1 : 0 );
	}
}

/* -------------------------------------------------------------------------
 * Fixtures.
 * ---------------------------------------------------------------------- */

function btd_cleanup() {
	global $wpdb;
	$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('btd_home','btd_page') OR post_title LIKE 'BTD %'" ); // phpcs:ignore WordPress.DB
	foreach ( $ids as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( array( 'btd_area' ) as $tax ) {
		$terms = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ) ); // phpcs:ignore WordPress.DB
		foreach ( $terms as $term ) {
			if ( taxonomy_exists( $tax ) ) {
				wp_delete_term( (int) $term, $tax );
			}
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( array( 'btd_sub', 'btd_editor', 'btd_customer' ) as $login ) {
		$u = get_user_by( 'login', $login );
		if ( $u ) {
			wp_delete_user( $u->ID );
		}
	}
	if ( function_exists( 'wc_get_orders' ) ) {
		foreach ( wc_get_orders( array( 'limit' => -1, 'customer' => 'btd_customer@example.com' ) ) as $order ) {
			$order->delete( true );
		}
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient%brik\\_data\\_meta\\_%'" ); // phpcs:ignore WordPress.DB
}

/**
 * Ids of the posts a query returns, in order.
 */
function btd_ids( $query, array $opt = array() ) {
	$args = Query::args( $query, $opt );
	if ( is_wp_error( $args ) ) {
		return $args;
	}
	$args['fields'] = 'ids';
	return array_map( 'intval', ( new WP_Query( $args ) )->posts );
}

function btd_q( array $rules, $relation = 'AND', array $extra = array() ) {
	return array_merge(
		array(
			'post_type' => 'btd_home',
			'where'     => array(
				'relation' => $relation,
				'rules'    => $rules,
			),
			'order'     => array( array( 'by' => 'post:id', 'dir' => 'ASC' ) ),
			'limit'     => 100,
		),
		$extra
	);
}

function btd_rule( $field, $op, $value = null ) {
	return array(
		'field' => $field,
		'op'    => $op,
		'value' => $value,
	);
}

function btd_set( array $ids ) {
	$ids = array_map( 'intval', $ids );
	sort( $ids );
	return $ids;
}

$t = new Brik_Data_Test_Runner();

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$admin  = (int) $admins[0];
wp_set_current_user( $admin );
btd_cleanup();

register_post_type(
	'btd_home',
	array(
		'public'   => true,
		'label'    => 'BTD Homes',
		'supports' => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'page-attributes' ),
	)
);
register_taxonomy(
	'btd_area',
	'btd_home',
	array(
		'public' => true,
		'label'  => 'Areas',
	)
);

$lg = brik_register_field_group(
	array(
		'key'      => 'group_btdhome',
		'title'    => 'BTD home',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'btd_home' ) ) ),
		'fields'   => array(
			array( 'key' => 'field_btdprice', 'name' => 'price', 'label' => 'Price', 'type' => 'number', 'options' => array( 'prepend' => '$' ) ),
			array( 'key' => 'field_btdbeds', 'name' => 'beds', 'label' => 'Bedrooms', 'type' => 'number' ),
			array(
				'key'     => 'field_btdloc',
				'name'    => 'location',
				'label'   => 'Location',
				'type'    => 'select',
				'options' => array(
					'choices' => array(
						array( 'value' => 'colombo', 'label' => 'Colombo' ),
						array( 'value' => 'kandy', 'label' => 'Kandy' ),
					),
				),
			),
			array(
				'key'     => 'field_btdfeat',
				'name'    => 'features',
				'label'   => 'Features',
				'type'    => 'checkbox',
				'options' => array(
					'choices' => array(
						array( 'value' => 'pool', 'label' => 'Pool' ),
						array( 'value' => 'garden', 'label' => 'Garden' ),
					),
				),
			),
			array( 'key' => 'field_btdnote', 'name' => 'note', 'label' => 'Note', 'type' => 'text' ),
			array( 'key' => 'field_btdopen', 'name' => 'open_day', 'label' => 'Open day', 'type' => 'date' ),
		),
	)
);
$t->section( 'Setup' );
$t->ok( ! is_wp_error( $lg ), 'local Brik field group', is_wp_error( $lg ) ? $lg->get_error_message() : '' );

$has_acf = Plugins::has_acf() && function_exists( 'acf_add_local_field_group' );
$t->ok( $has_acf, 'ACF is active' );
if ( $has_acf ) {
	acf_add_local_field_group(
		array(
			'key'      => 'group_btdacf',
			'title'    => 'BTD ACF',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'btd_home' ) ) ),
			'fields'   => array(
				array( 'key' => 'field_btdacf_price', 'name' => 'acf_price', 'label' => 'ACF price', 'type' => 'number' ),
				array( 'key' => 'field_btdacf_city', 'name' => 'acf_city', 'label' => 'City', 'type' => 'select', 'choices' => array( 'col' => 'Colombo City', 'kan' => 'Kandy City' ) ),
				array( 'key' => 'field_btdacf_photo', 'name' => 'acf_photo', 'label' => 'Photo', 'type' => 'image', 'return_format' => 'array' ),
				array( 'key' => 'field_btdacf_feat', 'name' => 'acf_featured', 'label' => 'Featured', 'type' => 'true_false' ),
				array( 'key' => 'field_btdacf_date', 'name' => 'acf_date', 'label' => 'Listed', 'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'd/m/Y' ),
				array( 'key' => 'field_btdacf_rel', 'name' => 'acf_related', 'label' => 'Related', 'type' => 'relationship', 'post_type' => array( 'btd_home' ) ),
				array( 'key' => 'field_btdacf_html', 'name' => 'acf_html', 'label' => 'Html text', 'type' => 'text' ),
				array(
					'key'        => 'field_btdacf_info',
					'name'       => 'acf_info',
					'label'      => 'Info',
					'type'       => 'group',
					'sub_fields' => array(
						array( 'key' => 'field_btdacf_agent', 'name' => 'agent', 'label' => 'Agent', 'type' => 'text' ),
					),
				),
			),
		)
	);
}

// An image for featured image and ACF image tests.
$uploads = wp_upload_dir();
$path    = trailingslashit( $uploads['path'] ) . 'btd-photo.png';
file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==' ) ); // phpcs:ignore
$img = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'BTD photo',
		'post_status'    => 'inherit',
	),
	$path
);
update_post_meta( $img, '_wp_attachment_image_alt', 'BTD alt "text"' );

$north = wp_insert_term( 'North', 'btd_area', array( 'slug' => 'north' ) );
$south = wp_insert_term( 'South', 'btd_area', array( 'slug' => 'south' ) );
$north = (int) $north['term_id'];
$south = (int) $south['term_id'];

$editor = wp_insert_user( array( 'user_login' => 'btd_editor', 'user_pass' => wp_generate_password(), 'user_email' => 'btd_editor@example.com', 'role' => 'editor', 'display_name' => 'BTD Editor', 'description' => 'Bio <b>bold</b>' ) );
$sub    = wp_insert_user( array( 'user_login' => 'btd_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'btd_sub@example.com', 'role' => 'subscriber', 'first_name' => 'Sub' ) );

/*
 * Homes: [ title, price, beds, location, features, area terms, days ago, author, acf city, acf featured, acf date ]
 */
$rows  = array(
	array( 'BTD Alpha Villa', 100, 1, 'colombo', array( 'pool' ), array( $north ), 1, $admin, 'col', 1, '20260110' ),
	array( 'BTD Beta House', 200, 2, 'colombo', array( 'garden' ), array( $south ), 5, $admin, 'col', 0, '20260215' ),
	array( 'BTD Gamma Flat', 300, 3, 'kandy', array( 'pool', 'garden' ), array( $north, $south ), 10, $editor, 'kan', 1, '20260320' ),
	array( 'BTD Delta Lodge', 400, 4, 'kandy', array(), array( $south ), 40, $editor, 'kan', 0, '20260425' ),
	array( 'BTD Epsilon Cabin', 500, 3, 'colombo', array( 'pool' ), array(), 100, $admin, 'col', 1, '20260530' ),
	array( 'BTD Zeta Studio', null, 2, '', array(), array( $north ), 400, $editor, '', 0, '' ),
);
$homes = array();
foreach ( $rows as $i => $r ) {
	$id = wp_insert_post(
		array(
			'post_type'   => 'btd_home',
			'post_status' => 'publish',
			'post_title'  => $r[0],
			'post_author' => $r[7],
			'menu_order'  => $i,
			'post_date'   => wp_date( 'Y-m-d H:i:s', time() - $r[6] * DAY_IN_SECONDS ),
		)
	);
	$homes[] = $id;
	if ( null !== $r[1] ) {
		update_post_meta( $id, 'price', $r[1] );
	}
	update_post_meta( $id, 'beds', $r[2] );
	if ( '' !== $r[3] ) {
		update_post_meta( $id, 'location', $r[3] );
	}
	if ( $r[4] ) {
		update_post_meta( $id, 'features', $r[4] );
	}
	update_post_meta( $id, 'btd_plain', 'plain ' . $i );
	update_post_meta( $id, 'btd_num', $i * 10 );
	update_post_meta( $id, 'btd_day', wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) );
	update_post_meta( $id, 'btd_list', array( 'x', 'y' ) );
	update_post_meta( $id, '_btd_secret', 'secret ' . $i );
	if ( $r[5] ) {
		wp_set_object_terms( $id, $r[5], 'btd_area' );
	}
	if ( $has_acf && '' !== $r[8] ) {
		update_field( 'field_btdacf_price', $r[1] * 1000, $id );
		update_field( 'field_btdacf_city', $r[8], $id );
		update_field( 'field_btdacf_feat', $r[9], $id );
		update_field( 'field_btdacf_date', $r[10], $id );
	}
}
list( $h1, $h2, $h3, $h4, $h5, $h6 ) = $homes;
update_post_meta( $h2, 'note', 'Sea view <b>terrace</b>' );
update_post_meta( $h3, 'note', 'Hill view' );
update_post_meta( $h2, 'btd_html', 'Sea view <b>terrace</b>' );
update_post_meta( $h2, 'open_day', wp_date( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ) );
update_post_meta( $h3, 'open_day', wp_date( 'Y-m-d', time() - 3 * DAY_IN_SECONDS ) );
set_post_thumbnail( $h1, $img );
wp_update_post(
	array(
		'ID'           => $h1,
		'post_excerpt' => 'Excerpt one',
		'post_title'   => 'BTD Alpha Villa <script>alert(1)</script>',
	)
);
if ( $has_acf ) {
	update_field( 'field_btdacf_photo', $img, $h1 );
	update_field( 'field_btdacf_rel', array( $h2, $h3 ), $h1 );
	update_field( 'field_btdacf_html', '<img src=x onerror=alert(1)>Hi', $h1 );
	update_field( 'field_btdacf_info', array( 'field_btdacf_agent' => 'Agent Nimal' ), $h1 );
}
delete_transient( 'brik_data_meta_' . md5( 'btd_home|' . ( function_exists( 'brik_listing_cache_version' ) ? brik_listing_cache_version() : 1 ) ) );

$tag = static function ( $tag, $post_id ) {
	return Dynamic::replace( '{' . $tag . '}', $post_id );
};

/* -------------------------------------------------------------------------
 * Sources discovery.
 * ---------------------------------------------------------------------- */

$t->section( 'Sources discovery' );
$tree   = Sources::tree( $h1 );
$groups = wp_list_pluck( $tree['groups'], 'key' );
$t->eq( 'btd_home', $tree['context']['post_type'], 'context post type from the post' );
$t->eq( $h1, $tree['context']['post_id'], 'previews use the given post' );
foreach ( array( 'post', 'terms', 'fields', 'meta', 'user', 'site', 'options', 'request' ) as $g ) {
	$t->ok( in_array( $g, $groups, true ), "group $g present" );
}
$t->ok( ! $has_acf || in_array( 'acf', $groups, true ), 'ACF group present' );
$t->ok( ! class_exists( 'WooCommerce' ) || in_array( 'woo', $groups, true ), 'WooCommerce group present' );
$find = static function ( $tree, $tag ) {
	$hit  = null;
	$walk = static function ( $items ) use ( &$walk, &$hit, $tag ) {
		foreach ( $items as $item ) {
			if ( isset( $item['items'] ) ) {
				$walk( $item['items'] );
			} elseif ( isset( $item['tag'] ) && $item['tag'] === $tag ) {
				$hit = $item;
			}
		}
	};
	foreach ( $tree['groups'] as $g ) {
		$walk( $g['items'] );
	}
	return $hit;
};
$item = $find( $tree, 'post:title' );
$t->ok( $item && false !== strpos( $item['preview'], 'BTD Alpha Villa' ), 'post:title preview' );
$t->ok( $item && false === strpos( $item['preview'], '<script' ), 'preview has no markup' );
$t->ok( (bool) $find( $tree, 'term:btd_area' ), 'taxonomy listed' );
$item = $find( $tree, 'field:price' );
$t->ok( $item && 'number' === $item['kind'] && '$100' === $item['preview'], 'Brik field with formatted preview', $item ? $item['preview'] : '' );
$t->ok( (bool) $find( $tree, 'meta:btd_plain' ), 'discovered meta key' );
$t->ok( ! $find( $tree, 'meta:price' ), 'Brik field names are not repeated under meta' );
$t->ok( ! $find( $tree, 'meta:_btd_secret' ), 'protected meta hidden by default' );
$t->ok( (bool) $find( Sources::tree( $h1, '', true ), 'meta:_btd_secret' ), 'protected meta listed when asked (editors)' );
if ( $has_acf ) {
	$item = $find( $tree, 'acf:acf_price' );
	$t->ok( $item && 'number' === $item['kind'] && '100000' === $item['preview'], 'ACF field listed with preview' );
	$t->ok( (bool) $find( $tree, 'acf:acf_info.agent' ), 'ACF group sub field listed' );
	$t->eq( 'image', $find( $tree, 'acf:acf_photo' )['kind'], 'ACF image kind' );
}
$meta = Sources::meta_keys( 'btd_home' );
$t->eq( 'number', isset( $meta['btd_num'] ) ? $meta['btd_num']['type'] : '', 'meta type guess: number' );
$t->eq( 'text', isset( $meta['btd_plain'] ) ? $meta['btd_plain']['type'] : '', 'meta type guess: text' );
$t->eq( 'date', isset( $meta['btd_day'] ) ? $meta['btd_day']['type'] : '', 'meta type guess: date' );
$t->eq( 'list', isset( $meta['btd_list'] ) ? $meta['btd_list']['type'] : '', 'meta type guess: serialized list' );
$t->ok( isset( $meta['price'] ), 'Brik field meta (registered for REST) is discoverable' );

$fields = Sources::fields( 'btd_home' );
foreach ( array( 'post:title' => 'text', 'post:date' => 'date', 'post:author' => 'user', 'tax:btd_area' => 'term', 'field:price' => 'number', 'field:location' => 'choice', 'field:features' => 'choice', 'field:open_day' => 'date', 'meta:btd_plain' => 'text' ) as $k => $type ) {
	$t->eq( $type, isset( $fields[ $k ] ) ? $fields[ $k ]['type'] : null, "query field $k is $type" );
}
$t->ok( ! empty( $fields['field:features']['multiple'] ), 'checkbox field is multiple' );
$t->eq( array( 'colombo', 'kandy' ), wp_list_pluck( $fields['field:location']['options'], 'value' ), 'choice values' );
$t->eq( array( 'north', 'south' ), wp_list_pluck( $fields['tax:btd_area']['options'], 'value' ), 'term options' );
if ( $has_acf ) {
	$t->eq( 'number', $fields['acf:acf_price']['type'], 'ACF number field' );
	$t->eq( 'choice', $fields['acf:acf_city']['type'], 'ACF select field' );
	$t->eq( 'bool', $fields['acf:acf_featured']['type'], 'ACF true/false field' );
	$t->eq( 'Ymd', $fields['acf:acf_date']['date_format'], 'ACF date stored format' );
	$t->eq( 'post', $fields['acf:acf_related']['type'], 'ACF relationship field' );
}
$t->ok( ! isset( $fields['meta:_btd_secret'] ), 'protected meta not queryable' );

/* -------------------------------------------------------------------------
 * Tags.
 * ---------------------------------------------------------------------- */

$t->section( 'Tags: post, author, terms' );
$t->eq( 'BTD Alpha Villa &lt;script&gt;alert(1)&lt;/script&gt;', $tag( 'post:title', $h1 ), 'post:title is escaped' );
$t->eq( 'Excerpt one', $tag( 'post:excerpt', $h1 ), 'post:excerpt' );
$t->eq( wp_date( 'c', get_post_timestamp( $h1 ) ), $tag( 'post:date|iso', $h1 ), 'post:date|iso' );
$t->ok( false !== strpos( $tag( 'post:date|relative', $h1 ), 'ago' ), 'post:date|relative' );
$t->eq( esc_url( wp_get_attachment_image_url( $img, 'full' ) ), $tag( 'post:image', $h1 ), 'post:image (URL)' );
$t->eq( (string) $img, $tag( 'post:image|id', $h1 ), 'post:image|id' );
$t->eq( 'BTD alt &quot;text&quot;', $tag( 'post:image|alt', $h1 ), 'post:image|alt escaped' );
$t->eq( esc_url( get_permalink( $h1 ) ), $tag( 'post:url', $h1 ), 'post:url' );
$t->eq( (string) $h1, $tag( 'post:id', $h1 ), 'post:id' );
$t->eq( 'BTD Homes', $tag( 'post:type_label', $h1 ), 'post:type_label' );
$t->eq( get_the_author_meta( 'display_name', $admin ), html_entity_decode( $tag( 'author:name', $h1 ) ), 'author:name' );
$t->eq( 'Bio &lt;b&gt;bold&lt;/b&gt;', $tag( 'author:bio', $h3 ), 'author:bio escaped' );
$t->ok( 0 === strpos( $tag( 'author:avatar', $h3 ), 'http' ), 'author:avatar URL' );
$t->eq( '', $tag( 'author:email', $h3 ), 'author email is never exposed' );
$t->eq( 'North, South', $tag( 'term:btd_area', $h3 ), 'term:taxonomy lists names' );
$t->eq( 'North', $tag( 'term:btd_area|first', $h3 ), 'term|first' );
$t->eq( '2', $tag( 'term:btd_area|count', $h3 ), 'term|count' );
$t->eq( 'north, south', $tag( 'term:btd_area|slug', $h3 ), 'term|slug' );
$t->ok( 2 === substr_count( $tag( 'term:btd_area|links', $h3 ), '<a href="' ), 'term|links' );
$t->eq( esc_url( get_term_link( $north ) ), $tag( 'term:btd_area|url', $h3 ), 'term|url' );
$t->eq( '', $tag( 'term:btd_area', $h5 ), 'no terms: empty' );

$t->section( 'Tags: meta, options, params, site, now' );
$t->eq( 'plain 0', $tag( 'meta:btd_plain', $h1 ), 'meta:key' );
$t->eq( '300', $tag( 'meta:price|number', $h3 ), 'meta with a modifier' );
$t->eq( 'Sea view &lt;b&gt;terrace&lt;/b&gt;', $tag( 'meta:btd_html', $h2 ), 'meta escaped' );
$t->eq( 'SEA VIEW &lt;B&gt;TERRACE&lt;/B&gt;', $tag( 'meta:btd_html|upper', $h2 ), 'meta with a modifier is escaped too' );
$t->eq( 'secret 0', $tag( 'meta:_btd_secret', $h1 ), 'protected meta shown to an editor of the post' );
wp_set_current_user( 0 );
$t->eq( '', $tag( 'meta:_btd_secret', $h1 ), 'protected meta hidden from visitors' );
update_post_meta( $h1, '_price', '42' );
$t->eq( '42', $tag( 'meta:_price', $h1 ), 'allow-listed protected meta (_price) is public' );
$t->eq( esc_html( get_option( 'blogname' ) ), $tag( 'option:blogname', $h1 ), 'option:blogname' );
$t->ok( false === strpos( $tag( 'option:admin_email', $h1 ), '@' ), 'option:admin_email is not exposed' );
$_GET['utm_source'] = '<img src=x onerror=alert(1)>news';
$t->eq( 'news', $tag( 'param:utm_source', $h1 ), 'param is sanitized' );
$_GET['q'] = 'a "quote" & <b>';
$t->eq( 'a &quot;quote&quot; &amp;', $tag( 'param:q', $h1 ), 'param is escaped' );
$_GET['arr'] = array( 'x' );
$t->eq( '', $tag( 'param:arr', $h1 ), 'array params are ignored' );
unset( $_GET['utm_source'], $_GET['q'], $_GET['arr'] );
$t->eq( '', $tag( 'param:missing', $h1 ), 'missing param is empty' );
$t->eq( wp_date( 'Y' ), $tag( 'now:Y', $h1 ), 'now:Y' );
$t->eq( esc_html( wp_date( get_option( 'date_format' ) ) ), $tag( 'now:date', $h1 ), 'now:date' );
$t->eq( '', $tag( 'now:Y<b>', $h1 ) === '{now:Y<b>}' ? '' : 'x', 'unsafe format characters are not a tag' );
$t->eq( esc_html( get_bloginfo( 'name' ) ), $tag( 'site:name', $h1 ), 'site:name' );
$t->eq( esc_url( home_url( '/' ) ), $tag( 'site:url', $h1 ), 'site:url' );

$t->section( 'Tags: current user' );
$t->eq( '', $tag( 'user:email', $h1 ), 'guest: user:email empty' );
$t->eq( '', $tag( 'user:name', $h1 ), 'guest: user:name empty' );
wp_set_current_user( $sub );
$t->eq( 'btd_sub@example.com', $tag( 'user:email', $h1 ), 'user:email for the current user' );
$t->eq( 'Sub', $tag( 'user:first_name', $h1 ), 'user:first_name' );
$t->eq( 'Subscriber', $tag( 'user:role', $h1 ), 'user:role' );
$t->eq( 'Sub', $tag( 'user:meta.first_name', $h1 ), 'user:meta.allowed key' );
$t->eq( '', $tag( 'user:meta.wp_capabilities', $h1 ), 'user:meta of private keys is empty' );
$t->eq( '', $tag( 'user:meta.session_tokens', $h1 ), 'user:meta session tokens never shown' );
wp_set_current_user( $admin );

$t->section( 'Tags: Brik fields and plugins' );
$t->eq( '$100', $tag( 'field:price', $h1 ), 'Brik field formatted' );
$t->eq( 'Colombo', $tag( 'field:location', $h1 ), 'Brik choice label' );
if ( $has_acf ) {
	$t->eq( '100000', $tag( 'acf:acf_price', $h1 ), 'acf number' );
	$t->eq( '100,000', $tag( 'acf:acf_price|number', $h1 ), 'acf number|number' );
	$t->eq( 'Colombo City', $tag( 'acf:acf_city', $h1 ), 'acf select shows the label' );
	$t->eq( 'col', $tag( 'acf:acf_city|raw', $h1 ), 'acf select|raw is the value' );
	$t->eq( esc_url( wp_get_attachment_image_url( $img, 'full' ) ), $tag( 'acf:acf_photo', $h1 ), 'acf image URL' );
	$t->eq( 'BTD alt &quot;text&quot;', $tag( 'acf:acf_photo|alt', $h1 ), 'acf image alt' );
	$t->eq( 'Yes', $tag( 'acf:acf_featured', $h1 ), 'acf true/false' );
	$t->eq( '10/01/2026', $tag( 'acf:acf_date', $h1 ), 'acf date uses its display format' );
	$t->eq( '2026', $tag( 'acf:acf_date|year', $h1 ), 'acf date|year' );
	$t->eq( 'BTD Beta House, BTD Gamma Flat', $tag( 'acf:acf_related', $h1 ), 'acf relationship titles' );
	$t->eq( '2', $tag( 'acf:acf_related|count', $h1 ), 'acf relationship|count' );
	$t->eq( esc_url( get_permalink( $h2 ) ), $tag( 'acf:acf_related|url', $h1 ), 'acf relationship|url' );
	$t->eq( 'Agent Nimal', $tag( 'acf:acf_info.agent', $h1 ), 'acf group sub field' );
	$t->eq( '&lt;img src=x onerror=alert(1)&gt;Hi', $tag( 'acf:acf_html', $h1 ), 'acf text is escaped' );
	$t->eq( '', $tag( 'acf:nope', $h1 ), 'unknown acf field is empty' );
}
$t->eq( Plugins::has_metabox() ? $tag( 'metabox:x', $h1 ) : '', $tag( 'metabox:x', $h1 ), 'metabox tag safe when inactive' );
$t->eq( Plugins::has_pods() ? $tag( 'pods:x', $h1 ) : '', $tag( 'pods:x', $h1 ), 'pods tag safe when inactive' );
if ( class_exists( 'WooCommerce' ) ) {
	$t->ok( '' !== $tag( 'cart:url', $h1 ), 'WooCommerce tags still resolve' );
}
wp_update_post(
	array(
		'ID'          => $h6,
		'post_status' => 'draft',
	)
);
wp_set_current_user( 0 );
$t->eq( '', $tag( 'post:title', $h6 ), 'draft posts are not readable by visitors' );
wp_set_current_user( $admin );
wp_update_post(
	array(
		'ID'          => $h6,
		'post_status' => 'publish',
	)
);
$t->eq( 'BTD Beta House', Tags::raw( 'post:title', $h2 ), 'raw value for comparisons' );
$t->eq( array( 'North', 'South', 'north', 'south', (string) $north, (string) $south ), Tags::raw( 'term:btd_area', $h3 ), 'raw terms: names, slugs and ids' );
$t->eq( '200', Tags::raw( 'field:price', $h2 ), 'raw Brik field' );

/* -------------------------------------------------------------------------
 * Query: operators.
 * ---------------------------------------------------------------------- */

$t->section( 'Query: number operators' );
$num = array(
	array( 'eq', 300, array( $h3 ) ),
	array( 'neq', 300, array( $h1, $h2, $h4, $h5 ) ),
	array( 'lt', 300, array( $h1, $h2 ) ),
	array( 'lte', 300, array( $h1, $h2, $h3 ) ),
	array( 'gt', 300, array( $h4, $h5 ) ),
	array( 'gte', 300, array( $h3, $h4, $h5 ) ),
	array( 'between', array( 200, 400 ), array( $h2, $h3, $h4 ) ),
	array( 'in', array( 100, 500 ), array( $h1, $h5 ) ),
	array( 'not_in', array( 100, 500 ), array( $h2, $h3, $h4 ) ),
	array( 'empty', null, array( $h6 ) ),
	array( 'not_empty', null, array( $h1, $h2, $h3, $h4, $h5 ) ),
);
foreach ( $num as $c ) {
	$t->eq( btd_set( $c[2] ), btd_set( btd_ids( btd_q( array( btd_rule( 'field:price', $c[0], $c[1] ) ) ) ) ), 'price ' . $c[0] . ' ' . wp_json_encode( $c[1] ) );
}
$args = Query::args( btd_q( array( btd_rule( 'field:price', 'lt', 300 ) ) ) );
$t->eq(
	array(
		'key'     => 'price',
		'compare' => '<',
		'value'   => '300',
		'type'    => 'DECIMAL(20,6)',
	),
	$args['meta_query'][0],
	'number rule becomes a typed meta clause'
);

$t->section( 'Query: text operators' );
$text = array(
	array( 'post:title', 'eq', 'BTD Beta House', array( $h2 ) ),
	array( 'post:title', 'neq', 'BTD Beta House', array( $h1, $h3, $h4, $h5, $h6 ) ),
	array( 'post:title', 'contains', 'Lodge', array( $h4 ) ),
	array( 'post:title', 'not_contains', 'Villa', array( $h2, $h3, $h4, $h5, $h6 ) ),
	array( 'post:title', 'starts', 'BTD G', array( $h3 ) ),
	array( 'post:title', 'in', array( 'BTD Beta House', 'BTD Delta Lodge' ), array( $h2, $h4 ) ),
	array( 'post:title', 'not_in', array( 'BTD Beta House', 'BTD Delta Lodge' ), array( $h1, $h3, $h5, $h6 ) ),
	array( 'post:slug', 'eq', 'btd-gamma-flat', array( $h3 ) ),
	array( 'field:note', 'contains', 'view', array( $h2, $h3 ) ),
	array( 'field:note', 'not_contains', 'Sea', array( $h3 ) ),
	array( 'field:note', 'starts', 'Hill', array( $h3 ) ),
	array( 'field:note', 'eq', 'Hill view', array( $h3 ) ),
	array( 'field:note', 'empty', null, array( $h1, $h4, $h5, $h6 ) ),
	array( 'meta:btd_plain', 'in', array( 'plain 1', 'plain 4' ), array( $h2, $h5 ) ),
	array( 'post:title', 'contains', '100%', array() ),
);
foreach ( $text as $c ) {
	$t->eq( btd_set( $c[3] ), btd_set( btd_ids( btd_q( array( btd_rule( $c[0], $c[1], $c[2] ) ) ) ) ), $c[0] . ' ' . $c[1] . ' ' . wp_json_encode( $c[2] ) );
}
$t->eq( array( 'title' => 'BTD Beta House' ), array_intersect_key( Query::args( btd_q( array( btd_rule( 'post:title', 'eq', 'BTD Beta House' ) ) ) ), array( 'title' => 1 ) ), 'title "is" uses the native title argument' );
$args = Query::args( btd_q( array( btd_rule( 'post:title', 'contains', 'Lodge' ) ) ) );
$t->ok( isset( $args['brik_where'] ) && 'post_title' === $args['brik_where'][0]['column'] && 'LIKE' === $args['brik_where'][0]['compare'], 'title contains uses brik_where' );

$t->section( 'Query: choice, bool, post' );
$choice = array(
	array( 'field:location', 'eq', 'colombo', array( $h1, $h2, $h5 ) ),
	array( 'field:location', 'neq', 'colombo', array( $h3, $h4 ) ),
	array( 'field:location', 'in', array( 'kandy' ), array( $h3, $h4 ) ),
	array( 'field:location', 'not_in', array( 'kandy' ), array( $h1, $h2, $h5 ) ),
	array( 'field:location', 'empty', null, array( $h6 ) ),
	array( 'field:location', 'not_empty', null, array( $h1, $h2, $h3, $h4, $h5 ) ),
	array( 'field:features', 'eq', 'pool', array( $h1, $h3, $h5 ) ),
	array( 'field:features', 'in', array( 'garden', 'pool' ), array( $h1, $h2, $h3, $h5 ) ),
	array( 'field:features', 'not_in', array( 'pool' ), array( $h2, $h4, $h6 ) ),
	array( 'field:features', 'neq', 'garden', array( $h1, $h4, $h5, $h6 ) ),
	array( 'field:features', 'empty', null, array( $h4, $h6 ) ),
);
foreach ( $choice as $c ) {
	$t->eq( btd_set( $c[3] ), btd_set( btd_ids( btd_q( array( btd_rule( $c[0], $c[1], $c[2] ) ) ) ) ), $c[0] . ' ' . $c[1] . ' ' . wp_json_encode( $c[2] ) );
}
if ( $has_acf ) {
	$t->eq( btd_set( array( $h1, $h3, $h5 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'acf:acf_featured', 'is_true' ) ) ) ) ), 'acf bool is_true' );
	$t->eq( btd_set( array( $h2, $h4, $h6 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'acf:acf_featured', 'is_false' ) ) ) ) ), 'acf bool is_false (incl. missing)' );
	$t->eq( btd_set( array( $h3, $h4 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'acf:acf_city', 'eq', 'kan' ) ) ) ) ), 'acf select eq' );
	$t->eq( array( $h1 ), btd_ids( btd_q( array( btd_rule( 'acf:acf_related', 'contains', (string) $h2 ) ) ) ), 'acf relationship contains a post' );
	$t->eq( btd_set( array( $h2, $h3, $h4, $h5, $h6 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'acf:acf_related', 'not_contains', (string) $h2 ) ) ) ) ), 'acf relationship not_contains' );
	$t->eq( btd_set( array( $h4, $h5 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'acf:acf_price', 'gt', 300000 ) ) ) ) ), 'acf number gt' );
}

$t->section( 'Query: dates' );
$today = wp_date( 'Y-m-d' );
$dates = array(
	array( 'post:date', 'on', wp_date( 'Y-m-d', time() - 5 * DAY_IN_SECONDS ), array( $h2 ) ),
	array( 'post:date', 'before', wp_date( 'Y-m-d', time() - 40 * DAY_IN_SECONDS ), array( $h5, $h6 ) ),
	array( 'post:date', 'after', wp_date( 'Y-m-d', time() - 10 * DAY_IN_SECONDS ), array( $h1, $h2 ) ),
	array( 'post:date', 'between', array( wp_date( 'Y-m-d', time() - 10 * DAY_IN_SECONDS ), wp_date( 'Y-m-d', time() - 5 * DAY_IN_SECONDS ) ), array( $h2, $h3 ) ),
	array( 'post:date', 'last_days', 30, array( $h1, $h2, $h3 ) ),
	array( 'post:date', 'older_days', 30, array( $h4, $h5, $h6 ) ),
	array( 'post:date', 'next_days', 30, array() ),
	array( 'field:open_day', 'next_days', 7, array( $h2 ) ),
	array( 'field:open_day', 'last_days', 7, array( $h3 ) ),
	array( 'field:open_day', 'after', $today, array( $h2 ) ),
	array( 'field:open_day', 'before', $today, array( $h3 ) ),
	array( 'field:open_day', 'on', wp_date( 'Y-m-d', time() + 3 * DAY_IN_SECONDS ), array( $h2 ) ),
);
if ( $has_acf ) {
	$dates[] = array( 'acf:acf_date', 'before', '2026-03-01', array( $h1, $h2 ) );
	$dates[] = array( 'acf:acf_date', 'between', array( '2026-02-01', '2026-04-30' ), array( $h2, $h3, $h4 ) );
	$dates[] = array( 'acf:acf_date', 'after', '2026-04-25', array( $h5 ) );
}
foreach ( $dates as $c ) {
	$t->eq( btd_set( $c[3] ), btd_set( btd_ids( btd_q( array( btd_rule( $c[0], $c[1], $c[2] ) ) ) ) ), $c[0] . ' ' . $c[1] . ' ' . wp_json_encode( $c[2] ) );
}
$args = Query::args( btd_q( array( btd_rule( 'post:date', 'last_days', 30 ) ) ) );
$t->ok( isset( $args['date_query'][0]['after'] ) && 'post_date' === $args['date_query'][0]['column'], 'post date rule becomes date_query' );
$args = Query::args( btd_q( array( btd_rule( 'post:modified', 'after', '2020-01-01' ) ) ) );
$t->eq( 'post_modified', $args['date_query'][0]['column'], 'modified date uses post_modified' );

$t->section( 'Query: terms and authors' );
$terms = array(
	array( 'in', array( 'north' ), array( $h1, $h3, $h6 ) ),
	array( 'in', array( (string) $south ), array( $h2, $h3, $h4 ) ),
	array( 'all', array( 'north', 'south' ), array( $h3 ) ),
	array( 'not_in', array( 'north' ), array( $h2, $h4, $h5 ) ),
	array( 'exists', null, array( $h1, $h2, $h3, $h4, $h6 ) ),
	array( 'not_exists', null, array( $h5 ) ),
);
foreach ( $terms as $c ) {
	$t->eq( btd_set( $c[2] ), btd_set( btd_ids( btd_q( array( btd_rule( 'tax:btd_area', $c[0], $c[1] ) ) ) ) ), 'term ' . $c[0] . ' ' . wp_json_encode( $c[1] ) );
}
$args = Query::args( btd_q( array( btd_rule( 'tax:btd_area', 'all', array( 'north', 'south' ) ) ) ) );
$t->eq(
	array(
		'taxonomy' => 'btd_area',
		'field'    => 'slug',
		'terms'    => array( 'north', 'south' ),
		'operator' => 'AND',
	),
	$args['tax_query'][0],
	'term rule becomes a tax_query clause'
);
$t->eq( btd_set( array( $h3, $h4, $h6 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'post:author', 'eq', (string) $editor ) ) ) ) ), 'author eq' );
$t->eq( btd_set( array( $h1, $h2, $h5 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'post:author', 'neq', (string) $editor ) ) ) ) ), 'author neq' );
$t->eq( btd_set( array( $h1, $h2, $h5 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'post:author', 'in', array( 'current' ) ) ) ) ) ), 'author in [current]' );
$t->eq( array( $editor ), Query::args( btd_q( array( btd_rule( 'post:author', 'in', array( (string) $editor ) ) ) ) )['author__in'], 'author uses author__in' );
$t->eq( array( $h2, $h4 ), btd_ids( btd_q( array( btd_rule( 'post:id', 'in', array( $h2, $h4 ) ) ) ) ), 'post id in' );
$t->eq( btd_set( array( $h1, $h2 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'post:menu_order', 'lt', 2 ) ) ) ) ), 'menu order lt' );

$t->section( 'Query: nested AND/OR' );
// (price < 300 AND beds >= 2) OR area = south  →  h2 (both) + h3, h4 (south).
$q = btd_q(
	array(
		array(
			'relation' => 'AND',
			'rules'    => array( btd_rule( 'field:price', 'lt', 300 ), btd_rule( 'field:beds', 'gte', 2 ) ),
		),
		btd_rule( 'tax:btd_area', 'in', array( 'south' ) ),
	),
	'OR'
);
$t->eq( btd_set( array( $h2, $h3, $h4 ) ), btd_set( btd_ids( $q ) ), 'meta group OR term (mixed kinds)' );
$args = Query::args( $q );
$t->ok( isset( $args['post__in'] ) && ! isset( $args['meta_query'] ) && ! isset( $args['tax_query'] ), 'mixed OR is resolved to post ids' );
// Meta-only OR nests relation OR in meta_query.
$q    = btd_q( array( btd_rule( 'field:price', 'lt', 200 ), btd_rule( 'field:location', 'eq', 'kandy' ) ), 'OR' );
$args = Query::args( $q );
$t->eq( 'OR', $args['meta_query'][0]['relation'], 'meta-only OR nests in meta_query' );
$t->eq( btd_set( array( $h1, $h3, $h4 ) ), btd_set( btd_ids( $q ) ), 'meta OR results' );
// Terms OR terms.
$q    = btd_q( array( btd_rule( 'tax:btd_area', 'all', array( 'north', 'south' ) ), btd_rule( 'tax:btd_area', 'not_exists' ) ), 'OR' );
$args = Query::args( $q );
$t->eq( 'OR', $args['tax_query'][0]['relation'], 'tax-only OR nests in tax_query' );
$t->eq( btd_set( array( $h3, $h5 ) ), btd_set( btd_ids( $q ) ), 'tax OR results' );
// Title OR date: both post columns → brik_where with OR.
$q    = btd_q( array( btd_rule( 'post:title', 'contains', 'Zeta' ), btd_rule( 'post:date', 'last_days', 2 ) ), 'OR' );
$args = Query::args( $q );
$t->eq( 'OR', isset( $args['brik_where'][0]['relation'] ) ? $args['brik_where'][0]['relation'] : '', 'post column OR becomes brik_where OR' );
$t->eq( btd_set( array( $h1, $h6 ) ), btd_set( btd_ids( $q ) ), 'post column OR results' );
// AND of everything with a nested OR group inside.
$q = btd_q(
	array(
		btd_rule( 'field:location', 'eq', 'colombo' ),
		array(
			'relation' => 'OR',
			'rules'    => array( btd_rule( 'field:beds', 'gte', 3 ), btd_rule( 'field:features', 'eq', 'garden' ) ),
		),
	)
);
$t->eq( btd_set( array( $h2, $h5 ) ), btd_set( btd_ids( $q ) ), 'AND with nested OR' );
// Three levels deep.
$q = btd_q(
	array(
		array(
			'relation' => 'OR',
			'rules'    => array(
				array(
					'relation' => 'AND',
					'rules'    => array( btd_rule( 'field:location', 'eq', 'kandy' ), btd_rule( 'field:beds', 'eq', 4 ) ),
				),
				btd_rule( 'post:title', 'starts', 'BTD Alpha' ),
			),
		),
	)
);
$t->eq( btd_set( array( $h1, $h4 ) ), btd_set( btd_ids( $q ) ), 'deep nesting with mixed kinds' );

$t->section( 'Query: order, limit, options' );
$q = btd_q( array(), 'AND', array( 'order' => array( array( 'by' => 'field:price', 'dir' => 'DESC' ) ) ) );
$t->eq( array( $h5, $h4, $h3, $h2, $h1, $h6 ), btd_ids( $q ), 'order by number field desc (missing last)' );
$q['order'] = array( array( 'by' => 'field:price', 'dir' => 'ASC' ) );
$ids        = btd_ids( $q );
$t->eq( array( $h1, $h2, $h3, $h4, $h5 ), array_values( array_diff( $ids, array( $h6 ) ) ), 'order by number field asc' );
$q = btd_q( array(), 'AND', array( 'order' => array( array( 'by' => 'post:title', 'dir' => 'ASC' ) ) ) );
$t->eq( array( $h1, $h2, $h4, $h5, $h3, $h6 ), btd_ids( $q ), 'order by title' );
$q = btd_q(
	array(),
	'AND',
	array(
		'order' => array(
			array( 'by' => 'field:beds', 'dir' => 'DESC' ),
			array( 'by' => 'post:title', 'dir' => 'DESC' ),
		),
	)
);
$t->eq( array( $h4, $h3, $h5, $h6, $h2, $h1 ), btd_ids( $q ), 'two sort keys' );
$args = Query::args( $q );
$t->ok( isset( $args['orderby']['brik_order_0'] ) && 'DESC' === $args['orderby']['brik_order_0'], 'meta order uses a named clause' );
$t->eq( 'rand', Query::args( btd_q( array(), 'AND', array( 'order' => array( array( 'by' => 'rand' ) ) ) ) )['orderby'], 'random order' );
$q = btd_q( array(), 'AND', array( 'order' => array( array( 'by' => 'post:date', 'dir' => 'DESC' ) ), 'limit' => 2, 'offset' => 1 ) );
$t->eq( array( $h2, $h3 ), btd_ids( $q ), 'limit and offset' );
$q = btd_q( array(), 'AND', array( 'exclude_current' => true ) );
$t->eq( btd_set( array( $h2, $h3, $h4, $h5, $h6 ) ), btd_set( btd_ids( $q, array( 'current' => get_post( $h1 ) ) ) ), 'exclude_current' );
$q = btd_q( array(), 'AND', array( 'search' => 'Lodge' ) );
$t->eq( array( $h4 ), btd_ids( $q ), 'search' );
$q = btd_q( array(), 'AND', array( 'author' => 'current' ) );
$t->eq( btd_set( array( $h1, $h2, $h5 ) ), btd_set( btd_ids( $q ) ), 'author: current user' );
wp_set_current_user( 0 );
$t->eq( array(), btd_ids( $q ), 'author: current matches nothing for guests' );
wp_set_current_user( $admin );
$_GET['loc'] = 'kandy';
$t->eq( btd_set( array( $h3, $h4 ) ), btd_set( btd_ids( btd_q( array( btd_rule( 'field:location', 'eq', '{param:loc}' ) ) ) ) ), 'dynamic tags in values' );
unset( $_GET['loc'] );
$t->eq( 100, Query::normalize( btd_q( array(), 'AND', array( 'limit' => 5000 ) ) )['limit'], 'limit is capped at 100' );

$t->section( 'Query: validation' );
$e = Query::args( btd_q( array( btd_rule( 'meta:nope', 'eq', 1 ) ) ) );
$t->ok( is_wp_error( $e ) && 'brik_query_field' === $e->get_error_code(), 'unknown field rejected' );
$e = Query::args( btd_q( array( btd_rule( 'meta:_btd_secret', 'eq', 1 ) ) ) );
$t->ok( is_wp_error( $e ), 'protected meta field rejected' );
$e = Query::args( btd_q( array( btd_rule( 'field:price', 'contains', 1 ) ) ) );
$t->ok( is_wp_error( $e ) && 'brik_query_op' === $e->get_error_code(), 'operator not valid for the type rejected' );
$e = Query::args( array( 'post_type' => 'nope_type' ) );
$t->ok( is_wp_error( $e ) && 'brik_query_type' === $e->get_error_code(), 'unknown post type rejected' );
$e = Query::args( array( 'post_type' => 'brik_template' ) );
$t->ok( is_wp_error( $e ), 'non-public post type rejected' );
$e = Query::args( btd_q( array(), 'AND', array( 'order' => array( array( 'by' => 'meta:nope' ) ) ) ) );
$t->ok( is_wp_error( $e ), 'unknown order field rejected' );
$deep = btd_rule( 'field:price', 'lt', 1 );
for ( $i = 0; $i < 7; $i++ ) {
	$deep = array(
		'relation' => 'AND',
		'rules'    => array( $deep ),
	);
}
$t->ok( is_wp_error( Query::args( btd_q( array( $deep ) ) ) ), 'too deep nesting rejected' );
$many = array();
for ( $i = 0; $i < 60; $i++ ) {
	$many[] = btd_rule( 'field:price', 'gt', $i );
}
$t->ok( is_wp_error( Query::args( btd_q( $many ) ) ), 'too many rules rejected' );
$t->ok( ! is_wp_error( Query::args( btd_q( array( btd_rule( '', 'eq', 1 ) ) ) ) ), 'unfinished rows are skipped' );
$t->eq( array(), btd_ids( btd_q( array( btd_rule( 'field:price', 'eq', "1' OR 1=1 -- " ) ) ) ), 'values are data, not SQL' );
$t->eq( array(), btd_ids( btd_q( array( btd_rule( 'post:title', 'eq', "x' OR '1'='1" ) ) ) ), 'title value is escaped' );
$injected = new WP_Query(
	array(
		'post_type'  => 'btd_home',
		'fields'     => 'ids',
		'brik_where' => array(
			array(
				'column'  => 'post_title) OR (1=1',
				'compare' => '=',
				'value'   => 'x',
			),
		),
	)
);
$t->eq( array(), $injected->posts, 'brik_where only accepts known columns' );
$code = Query::code( Query::args( btd_q( array( btd_rule( 'field:price', 'lt', 300 ) ) ) ) );
$t->ok( false !== strpos( $code, 'new WP_Query( $args )' ) && false !== strpos( $code, "'meta_query' => array(" ), 'PHP code export' );
// The exported code is valid PHP that builds the same arguments.
$built = null;
eval( str_replace( '$query = new WP_Query( $args );', '$built = $args;', $code ) ); // phpcs:ignore Squiz.PHP.Eval
$t->eq( Query::args( btd_q( array( btd_rule( 'field:price', 'lt', 300 ) ) ) ), $built, 'exported code evaluates to the same args' );

/* -------------------------------------------------------------------------
 * Listing module, REST and MCP.
 * ---------------------------------------------------------------------- */

$t->section( 'Listing module' );
$page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'BTD listing page',
	)
);
$listing = array(
	'type'  => 'listing',
	'id'    => 'btdlist1',
	'attrs' => array(
		'css_id' => 'btdlist',
		'query'  => btd_q( array( btd_rule( 'field:price', 'lt', 500 ), btd_rule( 'field:beds', 'gte', 2 ) ), 'AND', array( 'order' => array( array( 'by' => 'field:price', 'dir' => 'DESC' ) ) ) ),
	),
);
$r    = new Brik\Renderer( $page );
$html = $r->render_nodes( array( $listing ) );
preg_match_all( '/data-post-id="(\d+)"/', $html, $m );
$t->eq( array( $h4, $h3, $h2 ), array_map( 'intval', $m[1] ), 'listing renders the visual query in order' );
$legacy = array(
	'type'  => 'listing',
	'id'    => 'btdlist2',
	'attrs' => array(
		'post_type'      => 'btd_home',
		'posts_per_page' => 2,
		'orderby'        => 'title',
		'order'          => 'ASC',
	),
);
$html   = ( new Brik\Renderer( $page ) )->render_nodes( array( $legacy ) );
preg_match_all( '/data-post-id="(\d+)"/', $html, $m );
$t->eq( array( $h1, $h2 ), array_map( 'intval', $m[1] ), 'listings without a query keep working' );
$listing['attrs']['query']['limit'] = 1;
$listing['attrs']['pagination']     = 'numbered';
$html                               = ( new Brik\Renderer( $page ) )->render_nodes( array( $listing ) );
$t->ok( false !== strpos( $html, 'data-pages="3"' ), 'pagination counts query results' );
$bad                        = $listing;
$bad['attrs']['query']['where']['rules'] = array( btd_rule( 'meta:nope', 'eq', 1 ) );
$html                       = ( new Brik\Renderer( $page ) )->render_nodes( array( $bad ) );
$t->ok( false === strpos( $html, 'data-post-id' ), 'invalid query shows no posts' );

$t->section( 'REST and MCP' );
$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/data/sources' ) );
$t->eq( 200, $res->get_status(), 'GET data/sources' );
$req = new WP_REST_Request( 'GET', '/brik/v1/data/sources' );
$req->set_query_params( array( 'post_id' => $h1 ) );
$res = rest_do_request( $req );
$t->eq( 'btd_home', $res->get_data()['context']['post_type'], 'sources for a post' );
$req = new WP_REST_Request( 'GET', '/brik/v1/data/fields' );
$req->set_query_params( array( 'post_type' => 'btd_home' ) );
$res = rest_do_request( $req );
$t->ok( 200 === $res->get_status() && isset( $res->get_data()['ops']['number']['lt'] ), 'GET data/fields' );
$req = new WP_REST_Request( 'POST', '/brik/v1/data/query/preview' );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'query' => btd_q( array( btd_rule( 'field:location', 'eq', 'colombo' ) ) ) ) ) );
$res = rest_do_request( $req );
$t->ok( 200 === $res->get_status() && 3 === $res->get_data()['count'] && 3 === count( $res->get_data()['items'] ), 'POST data/query/preview' );
$req->set_body( wp_json_encode( array( 'query' => btd_q( array( btd_rule( 'meta:nope', 'eq', 1 ) ) ) ) ) );
$res = rest_do_request( $req );
$t->eq( 400, $res->get_status(), 'preview rejects unknown fields' );
$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/data/conditions' ) );
$t->ok( isset( $res->get_data()['types']['user_role'], $res->get_data()['types']['device']['client'] ), 'GET data/conditions' );
wp_set_current_user( $sub );
$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/data/sources' ) );
$t->eq( 403, $res->get_status(), 'subscribers cannot read sources' );
$res = rest_do_request( $req );
$t->eq( 403, $res->get_status(), 'subscribers cannot preview queries' );
wp_set_current_user( 0 );
$res = rest_do_request( new WP_REST_Request( 'GET', '/brik/v1/data/conditions' ) );
$t->eq( 401, $res->get_status(), 'guests cannot read condition types' );
wp_set_current_user( $admin );

$t->ok( Brik\McpTools::exists( 'list_data_sources' ) && Brik\McpTools::exists( 'preview_query' ), 'MCP tools registered' );
list( $out ) = Brik\McpTools::call( 'list_data_sources', array( 'post_type' => 'btd_home' ) );
$t->ok( is_array( $out ) && in_array( 'field:price', wp_list_pluck( $out['query_fields'], 'field' ), true ), 'list_data_sources has query fields' );
$t->ok( is_array( $out ) && in_array( '{post:title}', wp_list_pluck( $out['tags'], 'tag' ), true ), 'list_data_sources has tags' );
list( $out ) = Brik\McpTools::call( 'preview_query', array( 'query' => btd_q( array( btd_rule( 'field:beds', 'gte', 3 ) ) ) ) );
$t->ok( is_array( $out ) && 3 === $out['count'], 'preview_query counts' );
list( $out ) = Brik\McpTools::call( 'preview_query', array( 'query' => wp_json_encode( btd_q( array( btd_rule( 'nope:x', 'eq', 1 ) ) ) ) ) );
$t->ok( is_wp_error( $out ) && false !== strpos( $out->get_error_message(), 'Unknown field' ), 'preview_query reports unknown fields' );
$t->ok( false !== strpos( Brik\McpTools::guide(), 'visibility_rules' ), 'guide documents visibility_rules' );

/* -------------------------------------------------------------------------
 * Conditions.
 * ---------------------------------------------------------------------- */

$t->section( 'Conditions: users and content' );
$c = static function ( array $rules, $post_id = 0, $relation = 'AND' ) {
	return Conditions::evaluate(
		array(
			'relation' => $relation,
			'rules'    => $rules,
		),
		$post_id
	);
};
wp_set_current_user( 0 );
$t->eq( false, $c( array( array( 'type' => 'user_status', 'value' => 'logged_in' ) ) ), 'guest: logged_in false' );
$t->eq( true, $c( array( array( 'type' => 'user_status', 'value' => 'logged_out' ) ) ), 'guest: logged_out true' );
$t->eq( false, $c( array( array( 'type' => 'user_role', 'op' => 'in', 'value' => array( 'subscriber' ) ) ) ), 'guest has no role' );
wp_set_current_user( $sub );
$t->eq( true, $c( array( array( 'type' => 'user_status', 'value' => 'logged_in' ) ) ), 'logged in' );
$t->eq( true, $c( array( array( 'type' => 'user_role', 'op' => 'in', 'value' => array( 'editor', 'subscriber' ) ) ) ), 'role in' );
$t->eq( false, $c( array( array( 'type' => 'user_role', 'op' => 'not_in', 'value' => array( 'subscriber' ) ) ) ), 'role not_in' );
$t->eq( true, $c( array( array( 'type' => 'user_id', 'op' => 'in', 'value' => (string) $sub ) ) ), 'user id in' );
$t->eq( false, $c( array( array( 'type' => 'user_id', 'op' => 'in', 'value' => array( $editor ) ) ) ), 'user id not matching' );
wp_set_current_user( $admin );
$t->eq( true, $c( array( array( 'type' => 'post_type', 'op' => 'in', 'value' => array( 'btd_home' ) ) ), $h1 ), 'post type in' );
$t->eq( false, $c( array( array( 'type' => 'post_type', 'op' => 'not_in', 'value' => array( 'btd_home' ) ) ), $h1 ), 'post type not_in' );
$t->eq( true, $c( array( array( 'type' => 'post_id', 'op' => 'in', 'value' => array( $h1, $h2 ) ) ), $h2 ), 'post id in' );
$t->eq( true, $c( array( array( 'type' => 'term', 'taxonomy' => 'btd_area', 'op' => 'in', 'value' => array( 'south' ) ) ), $h2 ), 'term in (slug)' );
$t->eq( true, $c( array( array( 'type' => 'term', 'taxonomy' => 'btd_area', 'op' => 'in', 'value' => array( (string) $north ) ) ), $h1 ), 'term in (id)' );
$t->eq( false, $c( array( array( 'type' => 'term', 'taxonomy' => 'btd_area', 'op' => 'not_in', 'value' => array( 'north' ) ) ), $h3 ), 'term not_in' );
$t->eq( true, $c( array( array( 'type' => 'page_template', 'op' => 'in', 'value' => array( 'default' ) ) ), $page ), 'page template default' );
$data = array(
	array( 'field:price', 'gt', '150', $h2, true ),
	array( 'field:price', 'lt', '150', $h2, false ),
	array( 'field:price', 'gte', '200', $h2, true ),
	array( 'field:price', 'lte', '199', $h2, false ),
	array( 'meta:btd_plain', 'eq', 'PLAIN 1', $h2, true ),
	array( 'meta:btd_plain', 'neq', 'plain 1', $h2, false ),
	array( 'post:title', 'contains', 'beta', $h2, true ),
	array( 'post:title', 'not_contains', 'beta', $h2, false ),
	array( 'post:title', 'starts', 'BTD B', $h2, true ),
	array( 'field:location', 'in', 'kandy, galle', $h3, true ),
	array( 'field:location', 'not_in', array( 'kandy' ), $h3, false ),
	array( 'field:note', 'empty', '', $h1, true ),
	array( 'field:note', 'not_empty', '', $h2, true ),
	array( 'term:btd_area', 'eq', 'south', $h3, true ),
	array( 'tax:btd_area', 'in', array( 'north' ), $h1, true ),
	array( 'post:date', 'after', '2000-01-01', $h1, true ),
	array( 'post:date', 'before', '2000-01-01', $h1, false ),
	array( 'field:open_day', 'after', $today, $h2, true ),
);
if ( $has_acf ) {
	$data[] = array( 'acf:acf_city', 'eq', 'Kandy City', $h3, true );
	$data[] = array( 'acf:acf_featured', 'eq', 'yes', $h1, true );
	$data[] = array( 'acf:acf_price', 'gt', '400000', $h5, true );
}
foreach ( $data as $d ) {
	$t->eq( $d[4], $c( array( array( 'type' => 'data', 'field' => $d[0], 'op' => $d[1], 'value' => $d[2] ) ), $d[3] ), 'data ' . $d[0] . ' ' . $d[1] . ' ' . wp_json_encode( $d[2] ) );
}

$t->section( 'Conditions: request, date and time' );
$_GET['ref'] = 'spring';
$t->eq( true, $c( array( array( 'type' => 'url_param', 'key' => 'ref', 'op' => 'eq', 'value' => 'spring' ) ) ), 'url param eq' );
$t->eq( true, $c( array( array( 'type' => 'url_param', 'key' => 'ref', 'op' => 'exists' ) ) ), 'url param exists' );
$t->eq( false, $c( array( array( 'type' => 'url_param', 'key' => 'nope', 'op' => 'exists' ) ) ), 'missing url param' );
$t->eq( true, $c( array( array( 'type' => 'url_param', 'key' => 'nope', 'op' => 'not_exists' ) ) ), 'url param not_exists' );
unset( $_GET['ref'] );
$_SERVER['HTTP_REFERER'] = 'https://www.google.com/search?q=brik';
$t->eq( true, $c( array( array( 'type' => 'referrer', 'op' => 'contains', 'value' => 'google.' ) ) ), 'referrer contains' );
$t->eq( false, $c( array( array( 'type' => 'referrer', 'op' => 'not_contains', 'value' => 'google' ) ) ), 'referrer not_contains' );
unset( $_SERVER['HTTP_REFERER'] );
$_COOKIE['btd_seen'] = 'yes';
$t->eq( true, $c( array( array( 'type' => 'cookie', 'key' => 'btd_seen', 'op' => 'eq', 'value' => 'yes' ) ) ), 'cookie eq' );
$t->eq( false, $c( array( array( 'type' => 'cookie', 'key' => 'btd_seen', 'op' => 'neq', 'value' => 'yes' ) ) ), 'cookie neq' );
unset( $_COOKIE['btd_seen'] );
$t->eq( true, $c( array( array( 'type' => 'date_range', 'from' => wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ), 'to' => $today ) ) ), 'date range includes today' );
$t->eq( false, $c( array( array( 'type' => 'date_range', 'from' => wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ) ) ) ), 'date range in the future' );
$t->eq( false, $c( array( array( 'type' => 'date_range', 'to' => wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) ) ), 'date range in the past' );
$t->eq( true, $c( array( array( 'type' => 'time_of_day', 'from' => '00:00', 'to' => '23:59' ) ) ), 'time of day: all day' );
$h = (int) wp_date( 'G' );
$t->eq( false, $c( array( array( 'type' => 'time_of_day', 'from' => sprintf( '%02d:00', ( $h + 2 ) % 24 ), 'to' => sprintf( '%02d:00', ( $h + 3 ) % 24 ) ) ) ), 'time of day: another hour' );
$t->eq( true, $c( array( array( 'type' => 'time_of_day', 'from' => sprintf( '%02d:00', ( $h + 1 ) % 24 ), 'to' => sprintf( '%02d:59', $h ) ) ) ), 'time of day: window across midnight' );
$t->eq( true, $c( array( array( 'type' => 'day_of_week', 'op' => 'in', 'value' => array( wp_date( 'N' ) ) ) ) ), 'day of week: today' );
$t->eq( false, $c( array( array( 'type' => 'day_of_week', 'op' => 'not_in', 'value' => array( wp_date( 'N' ) ) ) ) ), 'day of week: not today' );
$locale = strtolower( determine_locale() );
$t->eq( true, $c( array( array( 'type' => 'language', 'op' => 'in', 'value' => array( substr( $locale, 0, 2 ) ) ) ) ), 'language code' );
$t->eq( true, $c( array( array( 'type' => 'language', 'op' => 'in', 'value' => array( determine_locale() ) ) ) ), 'language locale' );
$t->eq( false, $c( array( array( 'type' => 'language', 'op' => 'in', 'value' => array( 'xx' ) ) ) ), 'other language' );

$t->section( 'Conditions: devices (CSS) and logic' );
$t->eq( '(max-width:767px)', $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ) ) ), 'device mobile → media condition' );
$t->eq( '((min-width:768px) and (max-width:980px))', $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'tablet' ) ) ) ), 'device tablet' );
$t->eq( '(not (min-width:981px))', $c( array( array( 'type' => 'device', 'op' => 'not_in', 'value' => array( 'desktop' ) ) ) ), 'device not desktop' );
$t->eq( '((min-width:600px) and (max-width:900px))', $c( array( array( 'type' => 'viewport', 'min' => 600, 'max' => 900 ) ) ), 'viewport range' );
$t->eq( false, $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ), array( 'type' => 'user_status', 'value' => 'logged_out' ) ) ), 'AND with a false server rule hides' );
$t->eq( '(max-width:767px)', $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ), array( 'type' => 'user_status', 'value' => 'logged_in' ) ) ), 'AND with a true server rule keeps the CSS part' );
$t->eq( true, $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ), array( 'type' => 'user_status', 'value' => 'logged_in' ) ), 0, 'OR' ), 'OR with a true server rule shows' );
$t->eq( '((max-width:767px) or (min-width:981px))', $c( array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ), array( 'type' => 'device', 'op' => 'in', 'value' => array( 'desktop' ) ) ), 0, 'OR' ), 'OR of client rules' );
$nested = $c(
	array(
		array( 'type' => 'user_status', 'value' => 'logged_in' ),
		array(
			'relation' => 'OR',
			'rules'    => array(
				array( 'type' => 'user_role', 'op' => 'in', 'value' => array( 'editor' ) ),
				array( 'type' => 'post_id', 'op' => 'in', 'value' => array( $h1 ) ),
			),
		),
	),
	$h1
);
$t->eq( true, $nested, 'nested groups' );
$t->eq( true, $c( array() ), 'no rules: visible' );
$t->eq( true, $c( array( array( 'type' => 'made_up' ) ) ), 'unknown rule types are ignored' );

$section = array(
	'type'     => 'section',
	'id'       => 'btdsec1',
	'attrs'    => array(
		'visibility_rules' => array(
			'relation' => 'AND',
			'rules'    => array( array( 'type' => 'user_status', 'value' => 'logged_out' ) ),
		),
	),
	'children' => array(),
);
$t->eq( '', ( new Brik\Renderer( $page ) )->render_nodes( array( $section ) ), 'renderer hides an element whose rules fail' );
$t->ok( false !== strpos( ( new Brik\Renderer( $page, true ) )->render_nodes( array( $section ) ), 'brik-n-btdsec1' ), 'the builder canvas always shows it' );
$section['attrs']['visibility_rules']['rules'] = array( array( 'type' => 'device', 'op' => 'in', 'value' => array( 'mobile' ) ) );
$section['attrs']['custom_css']                = 'color:red';
$r2   = new Brik\Renderer( $page );
$html = $r2->render_nodes( array( $section ) );
$css  = $r2->style->css();
$t->ok( false !== strpos( $html, 'brik-n-btdsec1' ) && false !== strpos( $html, 'brik-dc-client' ), 'client-side rules render the element with a marker class' );
$t->ok( (bool) preg_match( '/@media not \(max-width:767px\)\{[^{}]*\.brik-n-btdsec1\{display:none!important\}\}/', $css ), 'client-side rules add a media rule', $css );
$t->ok( false !== strpos( $css, '.brik-n-btdsec1{color:red}' ), 'existing custom CSS declarations are kept' );

if ( class_exists( 'WooCommerce' ) ) {
	$t->section( 'Conditions: WooCommerce' );
	$product = new WC_Product_Simple();
	$product->set_name( 'BTD Product' );
	$product->set_regular_price( '60' );
	$product->set_status( 'publish' );
	$product->set_stock_status( 'instock' );
	$pid = $product->save();
	$cat = wp_insert_term( 'BTD Cat', 'product_cat' );
	$cat = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];
	wp_set_object_terms( $pid, array( $cat ), 'product_cat' );
	$customer = wp_insert_user( array( 'user_login' => 'btd_customer', 'user_pass' => wp_generate_password(), 'user_email' => 'btd_customer@example.com', 'role' => 'customer' ) );
	wp_set_current_user( $customer );
	if ( function_exists( 'wc_load_cart' ) ) {
		wc_load_cart();
	}
	WC()->cart->empty_cart();
	$t->eq( false, $c( array( array( 'type' => 'cart_total', 'op' => 'gt', 'value' => '100' ) ) ), 'empty cart: total > 100 false' );
	WC()->cart->add_to_cart( $pid, 2 );
	WC()->cart->calculate_totals();
	$t->eq( true, $c( array( array( 'type' => 'cart_total', 'op' => 'gt', 'value' => '100' ) ) ), 'cart total > 100' );
	$t->eq( false, $c( array( array( 'type' => 'cart_total', 'op' => 'lt', 'value' => '100' ) ) ), 'cart total < 100 false' );
	$t->eq( true, $c( array( array( 'type' => 'cart_count', 'op' => 'eq', 'value' => '2' ) ) ), 'cart count = 2' );
	$t->eq( true, $c( array( array( 'type' => 'cart_contains', 'kind' => 'product', 'op' => 'in', 'value' => array( $pid ) ) ) ), 'cart contains product' );
	$t->eq( true, $c( array( array( 'type' => 'cart_contains', 'kind' => 'category', 'op' => 'in', 'value' => array( $cat ) ) ) ), 'cart contains category' );
	$t->eq( false, $c( array( array( 'type' => 'cart_contains', 'kind' => 'product', 'op' => 'not_in', 'value' => array( $pid ) ) ) ), 'cart contains: none of' );
	$rules = array(
		array( 'type' => 'user_status', 'value' => 'logged_in' ),
		array( 'type' => 'user_role', 'op' => 'in', 'value' => array( 'customer' ) ),
		array( 'type' => 'cart_total', 'op' => 'gt', 'value' => '100' ),
	);
	$t->eq( true, $c( $rules ), 'logged-in customer with cart > 100' );
	WC()->cart->empty_cart();
	$t->eq( false, $c( $rules ), 'same customer with an empty cart' );
	$t->eq( false, $c( array( array( 'type' => 'purchased', 'op' => 'in', 'value' => array( $pid ) ) ) ), 'not purchased yet' );
	$order = wc_create_order( array( 'customer_id' => $customer ) );
	$order->add_product( wc_get_product( $pid ), 1 );
	$order->set_billing_email( 'btd_customer@example.com' );
	$order->calculate_totals();
	$order->update_status( 'completed' );
	wp_cache_flush();
	$t->eq( true, $c( array( array( 'type' => 'purchased', 'op' => 'in', 'value' => array( $pid ) ) ) ), 'has purchased the product' );
	$t->eq( false, $c( array( array( 'type' => 'purchased', 'op' => 'not_in', 'value' => array( $pid ) ) ) ), 'purchased: none of' );
	$t->eq( true, $c( array( array( 'type' => 'product_status', 'value' => 'in_stock' ) ), $pid ), 'product in stock' );
	$t->eq( false, $c( array( array( 'type' => 'product_status', 'value' => 'on_sale' ) ), $pid ), 'product not on sale' );
	$product = wc_get_product( $pid );
	$product->set_sale_price( '50' );
	$product->set_stock_status( 'outofstock' );
	$product->save();
	$t->eq( true, $c( array( array( 'type' => 'product_status', 'value' => 'on_sale' ) ), $pid ), 'product on sale' );
	$t->eq( true, $c( array( array( 'type' => 'product_status', 'value' => 'out_of_stock' ) ), $pid ), 'product out of stock' );
	wp_set_current_user( $admin );
	$order->delete( true );
	wp_delete_post( $pid, true );
	wp_delete_term( $cat, 'product_cat' );
}

/* -------------------------------------------------------------------------
 * Done.
 * ---------------------------------------------------------------------- */

wp_set_current_user( $admin );
wp_delete_attachment( $img, true );
btd_cleanup();
$t->finish();
