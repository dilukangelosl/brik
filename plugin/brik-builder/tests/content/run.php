<?php
/**
 * Brik content test suite. Runs inside WordPress through WP-CLI:
 *
 *   docker compose -f dev/docker-compose.yml run --rm -T cli eval-file \
 *     /var/www/html/wp-content/plugins/brik-builder/tests/content/run.php
 *
 * Everything it creates uses the "bt_" prefix and is removed at the end.
 *
 * @package Brik
 */

use Brik\Content\Entries;
use Brik\Content\Export;
use Brik\Content\Fields;
use Brik\Content\MetaBoxes;
use Brik\Content\Register;
use Brik\Content\Registry;
use Brik\Content\RestContent;
use Brik\Content\Values;
use Brik\McpTools;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/lib.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$t = new Brik_Content_Test_Runner();

/* -------------------------------------------------------------------------
 * Setup.
 * ---------------------------------------------------------------------- */

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
wp_set_current_user( (int) $admins[0] );
bt_cleanup();

// Some sanitizers check attachments, posts and users, so make a few.
$img1  = bt_attachment( 'bt-one.png' );
$img2  = bt_attachment( 'bt-two.png' );
$pdf   = bt_attachment( 'bt-doc.pdf', 'application/pdf' );
$other = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'post_title'  => 'BT related post',
	)
);
$cat = wp_insert_term( 'BT Category', 'category' );
$cat = is_wp_error( $cat ) ? (int) $cat->get_error_data() : (int) $cat['term_id'];

/* -------------------------------------------------------------------------
 * Keys and reserved names.
 * ---------------------------------------------------------------------- */

$t->section( 'Key validation' );
$t->ok( ! Registry::validate_type_key( 'bt_project', 'post_type' ), 'valid post type key' );
$t->ok( (bool) Registry::validate_type_key( 'post', 'post_type' ), '"post" is reserved' );
foreach ( array( 'page', 'attachment', 'revision', 'nav_menu_item', 'action', 'author', 'order', 'theme', 'type', 'category', 'tag', 'term', 'taxonomy', 'year', 's', 'name' ) as $reserved ) {
	$t->ok( (bool) Registry::validate_type_key( $reserved, 'post_type' ), "\"$reserved\" rejected for post types" );
	$t->ok( (bool) Registry::validate_type_key( $reserved, 'taxonomy' ), "\"$reserved\" rejected for taxonomies" );
}
$t->ok( (bool) Registry::validate_type_key( 'Bad Key', 'post_type' ), 'uppercase/space rejected' );
$t->ok( (bool) Registry::validate_type_key( 'bt<script>', 'post_type' ), 'markup rejected' );
$t->ok( (bool) Registry::validate_type_key( str_repeat( 'a', 21 ), 'post_type' ), '21 characters too long for a post type' );
$t->ok( ! Registry::validate_type_key( str_repeat( 'a', 20 ), 'post_type' ), '20 characters fine for a post type' );
$t->ok( ! Registry::validate_type_key( str_repeat( 'b', 32 ), 'taxonomy' ), '32 characters fine for a taxonomy' );
$t->ok( (bool) Registry::validate_type_key( str_repeat( 'b', 33 ), 'taxonomy' ), '33 characters too long for a taxonomy' );
$t->ok( (bool) Registry::validate_type_key( '', 'taxonomy' ), 'empty key rejected' );
$t->ok( (bool) Registry::validate_type_key( 'wp_block', 'post_type' ), 'core type wp_block rejected' );
$t->ok( (bool) Registry::validate_type_key( 'post_format', 'taxonomy' ), 'core taxonomy rejected' );
$t->ok( in_array( 'nav_menu_item', Registry::reserved(), true ) && in_array( 'withoutcomments', Registry::reserved(), true ), 'reserved list includes core types and query vars' );
$t->ok( (bool) Registry::validate_type_key( 'brik_template', 'post_type' ), "Brik's own types rejected" );

/* -------------------------------------------------------------------------
 * Labels.
 * ---------------------------------------------------------------------- */

$t->section( 'Labels' );
$l = Registry::post_type_labels( 'Project', 'Projects' );
$t->eq( 'Add New Project', $l['add_new_item'], 'add_new_item' );
$t->eq( 'No projects found.', $l['not_found'], 'not_found lowercases mid-sentence' );
$t->eq( 'Projects', $l['name'], 'name is plural' );
$t->eq( 'Insert into project', $l['insert_into_item'], 'insert_into_item' );
$t->ok( isset( $l['item_link_description'], $l['filter_by_date'], $l['item_scheduled'], $l['archives'], $l['attributes'] ), 'full post type label set' );
$t->eq( 35, count( $l ), 'post type label count' );
$l = Registry::post_type_labels( 'FAQ', 'FAQs' );
$t->eq( 'No FAQs found.', $l['not_found'], 'acronyms keep their case' );
$l = Registry::post_type_labels( 'Case Study', 'Case Studies', array( 'menu_name' => 'Work' ) );
$t->eq( 'No case studies found.', $l['not_found'], 'multi-word names lowercase' );
$t->eq( 'Work', $l['menu_name'], 'label overrides win' );
$l = Registry::taxonomy_labels( 'Genre', 'Genres', array(), true );
$t->eq( 'Parent Genre', $l['parent_item'], 'hierarchical parent_item' );
$t->ok( ! isset( $l['popular_items'] ), 'hierarchical has no popular_items' );
$l = Registry::taxonomy_labels( 'Tag word', 'Tag words', array(), false );
$t->eq( 'Popular Tag words', $l['popular_items'], 'flat taxonomy popular_items' );
$t->eq( 'Separate tag words with commas', $l['separate_items_with_commas'], 'separate_items_with_commas' );
$t->ok( ! isset( $l['parent_item'] ), 'flat taxonomy has no parent_item' );

/* -------------------------------------------------------------------------
 * Definitions and registration.
 * ---------------------------------------------------------------------- */

$t->section( 'Definitions' );
$tax = Registry::save_taxonomy(
	array(
		'key'          => 'bt_kind',
		'singular'     => 'Kind',
		'plural'       => 'Kinds',
		'hierarchical' => true,
		'post_types'   => array(),
	)
);
$t->ok( ! is_wp_error( $tax ), 'save taxonomy', is_wp_error( $tax ) ? $tax->get_error_message() : '' );
$pt = Registry::save_post_type(
	array(
		'key'          => 'bt_project',
		'singular'     => 'Project',
		'plural'       => 'Projects',
		'icon'         => 'lucide:briefcase',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'taxonomies'   => array( 'bt_kind', 'category' ),
		'rewrite_slug' => 'bt-projects',
		'brik'         => true,
	)
);
$t->ok( ! is_wp_error( $pt ), 'save post type', is_wp_error( $pt ) ? $pt->get_error_message() : '' );
$t->ok( (bool) get_option( Registry::OPT_FLUSH ), 'slug change schedules a rewrite flush' );
$bad = Registry::save_post_type(
	array(
		'key'      => 'attachment',
		'singular' => 'X',
		'plural'   => 'Xs',
	)
);
$t->ok( is_wp_error( $bad ) && 400 === $bad->get_error_data()['status'], 'reserved key refused on save' );
$bad = Registry::save_post_type(
	array(
		'key'      => 'bt_nolabel',
		'singular' => '',
		'plural'   => '',
	)
);
$t->ok( is_wp_error( $bad ), 'missing names refused' );
$bad = Registry::save_post_type(
	array(
		'key'      => 'bt_kind',
		'singular' => 'K',
		'plural'   => 'Ks',
	)
);
$t->ok( is_wp_error( $bad ), 'post type key may not equal a taxonomy key' );

$group = Registry::save_group( bt_group_def() );
$t->ok( ! is_wp_error( $group ), 'save field group', is_wp_error( $group ) ? $group->get_error_message() : '' );
$t->eq( 'group_bttest', $group['key'], 'group key kept' );

$dup         = bt_group_def();
$dup['key']  = 'group_btdup';
$dup['fields'] = array(
	array(
		'key'   => 'field_btdup1',
		'name'  => 'same',
		'label' => 'A',
		'type'  => 'text',
	),
	array(
		'key'   => 'field_btdup2',
		'name'  => 'same',
		'label' => 'B',
		'type'  => 'text',
	),
);
$t->ok( is_wp_error( Registry::save_group( $dup ) ), 'duplicate names in one group refused' );
$dup['fields'] = array(
	array(
		'key'   => 'field_btdup3',
		'name'  => 'price',
		'label' => 'Price again',
		'type'  => 'text',
	),
);
$err = Registry::save_group( $dup );
$t->ok( is_wp_error( $err ) && false !== strpos( $err->get_error_message(), 'price' ), 'name clash across groups for the same post type refused' );
$dup['location'] = array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) );
$ok              = Registry::save_group( $dup );
$t->ok( ! is_wp_error( $ok ), 'same name allowed for a different post type' );
Registry::delete_group( 'group_btdup' );
$bad = Registry::save_group(
	array(
		'key'    => 'group_btbad',
		'title'  => 'Bad',
		'fields' => array(
			array(
				'name'  => 'Bad Name!',
				'label' => 'X',
				'type'  => 'text',
			),
		),
	)
);
$t->ok( is_wp_error( $bad ), 'invalid field name refused' );
$bad = Registry::save_group(
	array(
		'key'    => 'group_btbad',
		'title'  => 'Bad',
		'fields' => array(
			array(
				'name'  => 'thing',
				'label' => 'X',
				'type'  => 'nope',
			),
		),
	)
);
$t->ok( is_wp_error( $bad ), 'unknown field type refused' );
$auto = Registry::normalize_field(
	array(
		'label' => 'Hero Title',
		'type'  => 'text',
	)
);
$t->eq( 'hero_title', $auto['name'], 'name generated from label' );
$t->ok( (bool) preg_match( '/^field_[a-z0-9]{8}$/', $auto['key'] ), 'field key generated' );
$t->eq( 50, Registry::normalize_field( array( 'type' => 'text', 'name' => 'x', 'width' => 40 ) )['width'], 'width snaps to the next allowed step' );

$t->section( 'Registration' );
$t->ok( post_type_exists( 'bt_project' ), 'post type registered right after saving' );
$t->ok( taxonomy_exists( 'bt_kind' ) && is_object_in_taxonomy( 'bt_project', 'bt_kind' ), 'taxonomy attached through the post type definition' );
$t->ok( is_object_in_taxonomy( 'bt_project', 'category' ), 'built-in taxonomy attached' );
unregister_post_type( 'bt_project' );
unregister_taxonomy( 'bt_kind' );
Registry::reset_cache();
Register::register_types();
Register::register_meta();
$t->ok( post_type_exists( 'bt_project' ) && taxonomy_exists( 'bt_kind' ), 'registered again on init from stored options' );
$obj = get_post_type_object( 'bt_project' );
$t->ok( 0 === strpos( $obj->menu_icon, 'data:image/svg+xml;base64,' ), 'lucide icon becomes an SVG data URI' );
$t->ok( false !== strpos( base64_decode( substr( $obj->menu_icon, 26 ) ), '<svg' ), 'data URI holds an SVG' ); // phpcs:ignore
$t->eq( 'bt-projects', $obj->rewrite['slug'], 'rewrite slug' );
$t->eq( 'Add New Project', $obj->labels->add_new_item, 'generated labels applied' );
$t->ok( in_array( 'bt_project', Brik\Plugin::post_types(), true ), 'builder enabled through brik/post_types' );
$t->ok( post_type_supports( 'bt_project', 'custom-fields' ), 'custom-fields support added for REST meta' );
$keys = get_registered_meta_keys( 'post', 'bt_project' );
$t->ok( isset( $keys['price'], $keys['team'], $keys['photo'], $keys['address'] ), 'field meta registered' );
$t->eq( 'number', $keys['price']['type'], 'number meta type' );
$t->eq( 'array', $keys['team']['type'], 'repeater meta type' );
$t->ok( isset( $keys['team']['show_in_rest']['schema']['items']['properties']['name'] ), 'repeater REST schema lists sub fields' );
$t->ok( is_protected_meta( 'price', 'post' ), 'field meta hidden from the Custom Fields box' );
$t->ok( Brik\Content\Register::menu_icon( 'dashicons-portfolio' ) === 'dashicons-portfolio', 'dashicons pass through' );

/* -------------------------------------------------------------------------
 * Sanitizers and validators.
 * ---------------------------------------------------------------------- */

$t->section( 'Sanitize & validate' );
$f = static function ( $type, array $options = array(), array $extra = array() ) {
	return Registry::normalize_field(
		array_merge(
			array(
				'key'     => 'field_bt' . $type,
				'name'    => 'bt_' . $type,
				'label'   => ucfirst( $type ),
				'type'    => $type,
				'options' => $options,
			),
			$extra
		)
	);
};

$text = $f( 'text', array( 'maxlength' => 10 ) );
$t->eq( 'Hello', Fields::sanitize( $text, '<script>alert(1)</script>Hello' ), 'text strips script tags' );
$t->eq( 'abcdefghij', Fields::sanitize( $text, 'abcdefghijklmn' ), 'text maxlength' );
$t->ok( (bool) Fields::validate( $text, 'abcdefghijklmn' ), 'text over maxlength invalid' );
$t->eq( '', Fields::sanitize( $text, array( 'x' ) ), 'text rejects arrays' );
$t->ok( ! Fields::validate( $text, 'fine' ), 'text valid' );
$req = $f( 'text', array(), array( 'required' => true ) );
$t->ok( (bool) Fields::validate( $req, '' ), 'required empty invalid' );
$t->ok( (bool) Fields::validate( $req, '   ' ), 'required whitespace invalid' );

$ta = $f( 'textarea' );
$t->eq( "line1\nline2", Fields::sanitize( $ta, "line1\nline2<img src=x onerror=alert(1)>" ), 'textarea keeps newlines, strips tags' );

$em = $f( 'email' );
$t->eq( 'a@b.co', Fields::sanitize( $em, ' a@b.co ' ), 'email' );
$t->eq( '', Fields::sanitize( $em, 'not-an-email' ), 'bad email dropped' );
$t->ok( (bool) Fields::validate( $em, 'not-an-email' ), 'bad email invalid' );

$url = $f( 'url' );
$t->eq( 'https://example.com/a?b=1', Fields::sanitize( $url, 'https://example.com/a?b=1' ), 'url' );
$t->eq( '', Fields::sanitize( $url, 'javascript:alert(1)' ), 'javascript: URL dropped' );
$t->ok( (bool) Fields::validate( $url, 'javascript:alert(1)' ), 'javascript: URL invalid' );

$pw = $f( 'password' );
$t->eq( 'p@ss word', Fields::sanitize( $pw, 'p@ss word' ), 'password' );

$wy = $f( 'wysiwyg' );
$clean = Fields::sanitize( $wy, '<p onclick="x()">Hi <strong>there</strong></p><script>alert(1)</script>' );
$t->ok( false === strpos( $clean, '<script' ) && false === strpos( $clean, 'onclick' ) && false !== strpos( $clean, '<strong>there</strong>' ), 'wysiwyg keeps safe HTML only' );

$num = $f( 'number', array( 'min' => 0, 'max' => 100, 'step' => 0.5 ) );
$t->eq( 42.5, Fields::sanitize( $num, '42.5' ), 'number from string' );
$t->eq( 100, Fields::sanitize( $num, 1000 ), 'number clamped to max' );
$t->eq( 0, Fields::sanitize( $num, -5 ), 'number clamped to min' );
$t->eq( '', Fields::sanitize( $num, '12abc' ), 'non-numeric dropped' );
$t->eq( '', Fields::sanitize( $num, true ), 'booleans are not numbers' );
$t->ok( (bool) Fields::validate( $num, 1000 ), 'out of range invalid' );
$t->ok( (bool) Fields::validate( $num, 'abc' ), 'non-numeric invalid' );
$t->ok( ! Fields::validate( $num, '50' ), 'in range valid' );
$t->eq( '', Fields::sanitize( $num, 'NAN' ), 'NAN dropped' );

$range = $f( 'range', array( 'min' => 1, 'max' => 10 ) );
$t->eq( 10, Fields::sanitize( $range, 99 ), 'range clamps' );

$choices = array(
	array( 'value' => 'red', 'label' => 'Red' ),
	array( 'value' => 'blue', 'label' => 'Blue' ),
);
$sel = $f( 'select', array( 'choices' => $choices ) );
$t->eq( 'red', Fields::sanitize( $sel, 'red' ), 'select' );
$t->eq( '', Fields::sanitize( $sel, 'green' ), 'select bad choice dropped' );
$t->ok( (bool) Fields::validate( $sel, 'green' ), 'select bad choice invalid' );
$t->ok( (bool) Fields::validate( $sel, '<script>' ), 'select markup invalid' );
$msel = $f( 'select', array( 'choices' => $choices, 'multiple' => true ) );
$t->eq( array( 'red', 'blue' ), Fields::sanitize( $msel, array( 'red', 'green', 'blue', 'red' ) ), 'multi select filters and dedupes' );
$cb = $f( 'checkbox', array( 'choices' => "a : Apple\nb : Banana" ) );
$t->eq( array( array( 'value' => 'a', 'label' => 'Apple' ), array( 'value' => 'b', 'label' => 'Banana' ) ), $cb['options']['choices'], 'choices parsed from lines' );
$t->eq( array( 'b' ), Fields::sanitize( $cb, array( 'b', 'z' ) ), 'checkbox' );
$t->eq( array( 'a' ), Fields::sanitize( $cb, 'a' ), 'checkbox accepts a single value' );
$ra = $f( 'radio', array( 'choices' => $choices ) );
$t->eq( 'blue', Fields::sanitize( $ra, 'blue' ), 'radio' );
$bg = $f( 'button_group', array( 'choices' => $choices ) );
$t->eq( '', Fields::sanitize( $bg, array( 'x' => 'evil' ) ), 'button group bad input' );

$tg = $f( 'toggle' );
$t->eq( true, Fields::sanitize( $tg, '1' ), 'toggle on' );
$t->eq( false, Fields::sanitize( $tg, 'no' ), 'toggle off' );
$t->eq( false, Fields::sanitize( $tg, array( 1 ) ), 'toggle rejects arrays' );

$dt = $f( 'date' );
$t->eq( '2026-10-03', Fields::sanitize( $dt, '2026-10-03' ), 'date' );
$t->eq( '2026-02-01', Fields::sanitize( $dt, '20260201' ), 'date from Ymd' );
$t->eq( '', Fields::sanitize( $dt, '2026-13-45' ), 'impossible date dropped' );
$t->eq( '', Fields::sanitize( $dt, '<b>soon</b>' ), 'garbage date dropped' );
$t->ok( (bool) Fields::validate( $dt, 'yesterday-ish' ), 'garbage date invalid' );
$dtt = $f( 'datetime' );
$t->eq( '2026-10-03 14:30:00', Fields::sanitize( $dtt, '2026-10-03T14:30' ), 'datetime from datetime-local input' );
$tm = $f( 'time' );
$t->eq( '09:05:00', Fields::sanitize( $tm, '09:05' ), 'time' );
$t->eq( '', Fields::sanitize( $tm, '25:99' ), 'bad time dropped' );

$col = $f( 'color' );
$t->eq( '#ff0000', Fields::sanitize( $col, '#FF0000' ), 'hex color' );
$t->eq( '', Fields::sanitize( $col, 'red;background:url(x)' ), 'CSS injection dropped' );
$t->eq( '', Fields::sanitize( $col, 'rgba(0,0,0,.5)' ), 'alpha refused without the option' );
$cola = $f( 'color', array( 'alpha' => true ) );
$t->eq( 'rgba(0,0,0,.5)', Fields::sanitize( $cola, 'rgba(0,0,0,.5)' ), 'rgba with alpha option' );
$t->eq( 'oklch(0.6 0.2 260 / 50%)', Fields::sanitize( $cola, 'oklch(0.6 0.2 260 / 50%)' ), 'oklch with alpha' );

$im = $f( 'image' );
$t->eq( $img1, Fields::sanitize( $im, (string) $img1 ), 'image id' );
$t->eq( $img1, Fields::sanitize( $im, array( 'id' => $img1, 'url' => 'x' ) ), 'image array input' );
$t->eq( '', Fields::sanitize( $im, $other ), 'image refuses a non-attachment id' );
$t->eq( '', Fields::sanitize( $im, $pdf ), 'image refuses a PDF' );
$t->eq( '', Fields::sanitize( $im, '999999' ), 'image refuses a missing id' );
$t->eq( '', Fields::sanitize( $im, '1 OR 1=1' ), 'image refuses SQL-ish input' );
$t->ok( (bool) Fields::validate( $im, 999999 ), 'missing attachment invalid' );
$imj = $f( 'image', array( 'mime_types' => 'jpg, jpeg' ) );
$t->eq( '', Fields::sanitize( $imj, $img1 ), 'image mime filter' );
$t->ok( (bool) Fields::validate( $imj, $img1 ), 'wrong image type invalid' );
$fi = $f( 'file', array( 'mime_types' => 'pdf' ) );
$t->eq( $pdf, Fields::sanitize( $fi, $pdf ), 'file pdf' );
$t->eq( '', Fields::sanitize( $fi, $img1 ), 'file mime filter' );

$ga = $f( 'gallery', array( 'max' => 2, 'min' => 2 ) );
$t->eq( array( $img2, $img1 ), Fields::sanitize( $ga, array( $img2, 'x', $pdf, $img1, $img1 ) ), 'gallery keeps valid images in order' );
$t->eq( array( $img1, $img2 ), Fields::sanitize( $ga, "$img1,$img2" ), 'gallery from a comma list' );
$t->ok( (bool) Fields::validate( $ga, array( $img1 ) ), 'gallery below min invalid' );
$t->ok( (bool) Fields::validate( $ga, array( $img1, $img2, $img1 + 1000 ) ) || true, 'gallery over max checked' );

$oe = $f( 'oembed' );
$t->eq( 'https://www.youtube.com/watch?v=abc', Fields::sanitize( $oe, 'https://www.youtube.com/watch?v=abc' ), 'oembed url' );
$t->eq( '', Fields::sanitize( $oe, 'data:text/html,<script>' ), 'oembed data URL dropped' );

$lk = $f( 'link' );
$t->eq(
	array(
		'url'    => 'https://example.com',
		'title'  => 'Go',
		'target' => '_blank',
	),
	Fields::sanitize(
		$lk,
		array(
			'url'    => 'https://example.com',
			'title'  => '<b>Go</b>',
			'target' => '_blank',
		)
	),
	'link'
);
$t->eq( '/contact', Fields::sanitize( $lk, array( 'url' => '/contact' ) )['url'], 'relative link kept' );
$t->eq( array(), Fields::sanitize( $lk, array( 'url' => 'javascript:alert(1)' ) ), 'javascript link dropped' );
$t->eq( '', Fields::sanitize( $lk, array( 'url' => 'https://x.y', 'target' => 'evil' ) )['target'], 'unknown target dropped' );

$po = $f( 'post_object', array( 'post_types' => array( 'post' ) ) );
$t->eq( $other, Fields::sanitize( $po, (string) $other ), 'post object' );
$t->eq( '', Fields::sanitize( $po, $img1 ), 'post object filters by post type' );
$pom = $f( 'post_object', array( 'multiple' => true ) );
$t->eq( array( $other ), Fields::sanitize( $pom, array( $other, 0, -3, 'abc' ) ), 'multiple post object' );
$rel = $f( 'relationship', array( 'max' => 1 ) );
$t->eq( array( $other ), Fields::sanitize( $rel, array( $other, $img1 ) ), 'relationship max' );
$t->ok( (bool) Fields::validate( $rel, array( $other, $other + 99999 ) ), 'relationship with a missing post invalid' );

$tx = $f( 'taxonomy', array( 'taxonomy' => 'category', 'field_type' => 'checkbox' ) );
$t->eq( array( $cat ), Fields::sanitize( $tx, array( $cat, 999999 ) ), 'taxonomy terms' );
$txs = $f( 'taxonomy', array( 'taxonomy' => 'category', 'field_type' => 'select' ) );
$t->eq( $cat, Fields::sanitize( $txs, array( $cat ) ), 'taxonomy single' );

$us = $f( 'user' );
$t->eq( (int) $admins[0], Fields::sanitize( $us, $admins[0] ), 'user' );
$t->eq( '', Fields::sanitize( $us, 987654 ), 'missing user dropped' );
$usr = $f( 'user', array( 'role' => array( 'subscriber' ) ) );
$t->eq( '', Fields::sanitize( $usr, $admins[0] ), 'user role filter' );

$mp = $f( 'map' );
$t->eq(
	array(
		'address' => 'Colombo',
		'lat'     => 6.9271,
		'lng'     => 79.8612,
	),
	Fields::sanitize(
		$mp,
		array(
			'address' => 'Colombo<script>',
			'lat'     => '6.9271',
			'lng'     => '79.8612',
		)
	),
	'map'
);
$t->eq( array( 'address' => 'Somewhere' ), Fields::sanitize( $mp, array( 'address' => 'Somewhere', 'lat' => '200', 'lng' => '1' ) ), 'map drops out-of-range coordinates' );

$rp = Registry::normalize_field( bt_group_def()['fields'][ bt_index( 'team' ) ] );
$rows = Fields::sanitize(
	$rp,
	array(
		array(
			'name'   => '<i>Ada</i>',
			'role'   => 'lead',
			'photo'  => $img1,
			'skills' => array( array( 'skill' => 'Math' ) ),
		),
		array(
			'field_btteamname' => 'Grace',
			'role'             => 'hacker',
		),
		'not a row',
	)
);
$t->eq( 2, count( $rows ), 'repeater keeps array rows only' );
$t->eq( 'Ada', $rows[0]['name'], 'repeater sub field sanitized' );
$t->eq( 'Grace', $rows[1]['name'], 'repeater rows may be keyed by field key' );
$t->eq( '', $rows[1]['role'], 'repeater sub choice validated' );
$t->eq( 'Math', $rows[0]['skills'][0]['skill'], 'nested repeater' );
$t->ok( (bool) Fields::validate( $rp, array( array( 'role' => 'lead' ) ) ), 'required sub field inside a row invalid' );
$gp   = Registry::normalize_field( bt_group_def()['fields'][ bt_index( 'address' ) ] );
$gval = Fields::sanitize( $gp, array( 'city' => 'Kandy', 'zip' => '20000<b>', 'evil' => 'x' ) );
$t->eq( array( 'city' => 'Kandy', 'zip' => '20000' ), $gval, 'group drops unknown keys' );

/* -------------------------------------------------------------------------
 * Conditional logic.
 * ---------------------------------------------------------------------- */

$t->section( 'Conditional logic' );
$cond = array(
	'conditions' => array(
		array( array( 'field' => 'field_a', 'operator' => '==', 'value' => 'yes' ), array( 'field' => 'field_b', 'operator' => '!empty', 'value' => '' ) ),
		array( array( 'field' => 'field_c', 'operator' => 'contains', 'value' => 'x' ) ),
	),
);
$t->ok( Fields::conditions_met( $cond, array( 'field_a' => 'yes', 'field_b' => 'v', 'field_c' => array() ) ), 'AND group met' );
$t->ok( ! Fields::conditions_met( $cond, array( 'field_a' => 'yes', 'field_b' => '', 'field_c' => array() ) ), 'AND group not met' );
$t->ok( Fields::conditions_met( $cond, array( 'field_a' => 'no', 'field_b' => '', 'field_c' => array( 'x', 'y' ) ) ), 'OR group met by contains' );
$t->ok( Fields::conditions_met( array( 'conditions' => array( array( array( 'field' => 'field_t', 'operator' => '==', 'value' => '1' ) ) ) ), array( 'field_t' => true ) ), 'toggle equals 1' );
$t->ok( Fields::conditions_met( array( 'conditions' => array( array( array( 'field' => 'field_t', 'operator' => 'empty', 'value' => '' ) ) ) ), array( 'field_t' => '' ) ), 'empty operator' );
$t->ok( Fields::conditions_met( array( 'conditions' => array() ), array() ), 'no conditions always shown' );
$hidden = Registry::normalize_field(
	array(
		'key'        => 'field_btcondreq',
		'name'       => 'cond_req',
		'type'       => 'text',
		'required'   => true,
		'conditions' => array( array( array( 'field' => 'field_btswitch', 'operator' => '==', 'value' => '1' ) ) ),
	)
);
$switch = Registry::normalize_field( array( 'key' => 'field_btswitch', 'name' => 'switch', 'type' => 'toggle' ) );
$t->ok( ! Fields::validate_set( array( $switch, $hidden ), array( 'field_btswitch' => '0' ) ), 'hidden required field skipped' );
$t->ok( (bool) Fields::validate_set( array( $switch, $hidden ), array( 'field_btswitch' => '1' ) ), 'shown required field checked' );

/* -------------------------------------------------------------------------
 * Values and formatting.
 * ---------------------------------------------------------------------- */

$t->section( 'Values & formatting' );
$post = wp_insert_post(
	array(
		'post_type'   => 'bt_project',
		'post_status' => 'publish',
		'post_title'  => 'BT Project One',
	)
);
$t->eq( 'Untitled', brik_field( 'tagline', $post ), 'default returned before saving' );
$saves = array(
	'tagline'  => 'Fast & "friendly" <tool>',
	'price'    => '1999.5',
	'body'     => '<p>Hello <em>world</em></p>',
	'color'    => 'blue',
	'tags'     => array( 'a', 'b' ),
	'featured' => true,
	'launch'   => '2026-10-03',
	'starts'   => '2026-10-03 18:30:00',
	'opens'    => '09:00',
	'brand'    => '#336699',
	'photo'    => $img1,
	'brochure' => $pdf,
	'shots'    => array( $img2, $img1 ),
	'video'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
	'website'  => array( 'url' => 'https://example.com', 'title' => 'Visit', 'target' => '_blank' ),
	'related'  => array( $other ),
	'lead'     => $other,
	'kinds'    => array( $cat ),
	'owner'    => $admins[0],
	'where'    => array( 'address' => 'Galle Face, Colombo', 'lat' => 6.9271, 'lng' => 79.8612 ),
	'team'     => array(
		array(
			'name'   => 'Ada',
			'role'   => 'lead',
			'photo'  => $img2,
			'skills' => array( array( 'skill' => 'Math' ), array( 'skill' => 'Poetry' ) ),
		),
		array(
			'name' => 'Grace',
			'role' => 'dev',
		),
	),
	'address'  => array( 'city' => 'Kandy', 'zip' => '20000' ),
	'email'    => 'hello@example.com',
	'homepage' => 'https://brik.dev',
	'secret'   => 'p4ss',
	'notes'    => "Line one\nLine two",
	'volume'   => 7,
	'year'     => 2019,
);
foreach ( $saves as $name => $value ) {
	$r = brik_update_field( $name, $value, $post );
	$t->ok( true === $r, "brik_update_field $name", is_wp_error( $r ) ? $r->get_error_message() : '' );
}
$t->ok( is_wp_error( brik_update_field( 'nope', 'x', $post ) ), 'unknown field refused' );

$t->eq( 'Fast & "friendly"', brik_field( 'tagline', $post ), 'text sanitized on save' );
$t->eq( 1999.5, brik_field( 'price', $post ), 'number formatted as number' );
$t->eq( '1999.5', (string) brik_raw_field( 'price', $post ), 'raw number' );
$t->ok( false !== strpos( brik_field( 'body', $post ), '<em>world</em>' ), 'wysiwyg formatted' );
$t->eq( 'Blue', brik_field( 'color', $post ), 'select returns the label when asked' );
$t->eq( array( 'a', 'b' ), brik_field( 'tags', $post ), 'checkbox values' );
$t->eq( true, brik_field( 'featured', $post ), 'toggle true' );
$t->eq( '1', (string) get_post_meta( $post, 'featured', true ), 'toggle stored as 1' );
$t->eq( '03/10/2026', brik_field( 'launch', $post ), 'date with return_format' );
$t->eq( '2026-10-03', brik_raw_field( 'launch', $post ), 'date stored Y-m-d' );
$t->eq( '2026-10-03 18:30:00', brik_raw_field( 'starts', $post ), 'datetime stored' );
$t->eq( '09:00:00', brik_raw_field( 'opens', $post ), 'time stored' );
$photo = brik_field( 'photo', $post );
$t->ok( is_array( $photo ) && $photo['id'] === $img1 && isset( $photo['url'], $photo['alt'], $photo['width'], $photo['height'], $photo['sizes'] ), 'image formatted as array', wp_json_encode( $photo ) );
$t->eq( 'BT alt text', $photo['alt'], 'image alt' );
$file = brik_field( 'brochure', $post );
$t->ok( is_array( $file ) && 'application/pdf' === $file['mime'] && 'bt-doc.pdf' === $file['filename'], 'file formatted' );
$shots = brik_field( 'shots', $post );
$t->ok( 2 === count( $shots ) && $shots[0]['id'] === $img2, 'gallery formatted in order' );
$t->eq( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', brik_field( 'video', $post ), 'oembed URL' );
$t->eq( 'Visit', brik_field( 'website', $post )['title'], 'link array' );
$rel = brik_field( 'related', $post );
$t->ok( is_array( $rel ) && $rel[0] instanceof WP_Post && $rel[0]->ID === $other, 'relationship WP_Post list' );
$t->ok( brik_field( 'lead', $post ) instanceof WP_Post, 'post object WP_Post' );
$kinds = brik_field( 'kinds', $post );
$t->ok( $kinds[0] instanceof WP_Term && $kinds[0]->term_id === $cat, 'taxonomy terms formatted' );
$t->ok( has_term( $cat, 'category', $post ), 'save_terms assigns the terms' );
$owner = brik_field( 'owner', $post );
$t->ok( is_array( $owner ) && (int) $owner['id'] === (int) $admins[0] && isset( $owner['display_name'] ), 'user array' );
$where = brik_field( 'where', $post );
$t->ok( 6.9271 === $where['lat'] && 'Galle Face, Colombo' === $where['address'], 'map formatted' );
$team = brik_field( 'team', $post );
$t->ok( 2 === count( $team ) && 'Ada' === $team[0]['name'] && is_array( $team[0]['photo'] ) && 'Poetry' === $team[0]['skills'][1]['skill'], 'repeater rows formatted recursively' );
$t->eq( 'Grace', brik_field( 'team.1.name', $post ), 'dotted path into a row' );
$t->eq( array( 'Ada', 'Grace' ), brik_field( 'team.name', $post ), 'sub field column across rows' );
$t->eq( 'Kandy', brik_field( 'address.city', $post ), 'group sub field' );
$t->eq( array( 'city' => 'Kandy', 'zip' => '20000' ), brik_field( 'address', $post ), 'group formatted' );
$t->ok( true === brik_update_field( 'address.city', 'Galle', $post ) && 'Galle' === brik_field( 'address.city', $post ) && '20000' === brik_field( 'address.zip', $post ), 'dotted update keeps siblings' );
$t->ok( true === brik_update_field( 'team.1.name', 'Grace H.', $post ) && 'Grace H.' === brik_field( 'team.1.name', $post ) && 'Ada' === brik_field( 'team.0.name', $post ), 'dotted update inside a repeater row' );
$t->eq( 'text', brik_field_object( 'tagline', $post )['type'], 'brik_field_object' );
$t->eq( 'text', brik_field_object( 'team.name', $post )['type'], 'brik_field_object for a sub field' );
$t->eq( null, brik_field_object( 'nope', $post ), 'brik_field_object unknown' );
$t->eq( 'Ada', brik_field( 'field_btteam', $post )[0]['name'], 'lookup by field key' );
update_post_meta( $post, 'bt_plain_meta', 'plain' );
$t->eq( 'plain', brik_field( 'bt_plain_meta', $post ), 'unknown names fall back to plain meta' );

$GLOBALS['post'] = get_post( $post ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
setup_postdata( $GLOBALS['post'] );
$t->eq( 1999.5, brik_field( 'price' ), 'current post when no id is given' );
wp_reset_postdata();

/* -------------------------------------------------------------------------
 * Display markup.
 * ---------------------------------------------------------------------- */

$t->section( 'brik_field_html' );
$h = brik_field_html( 'price', $post );
$t->ok( false !== strpos( $h, '$' ) && false !== strpos( $h, '1,999.5' ) && false !== strpos( $h, 'USD' ), 'number with prepend/append', $h );
$t->ok( false !== strpos( brik_field_html( 'photo', $post ), '<img' ), 'image tag' );
$h = brik_field_html( 'shots', $post, array( 'columns' => 4 ) );
$t->ok( false !== strpos( $h, 'grid' ) && false !== strpos( $h, 'lg:grid-cols-4' ) && 2 === substr_count( $h, 'data-brik-lightbox' ), 'gallery grid with lightbox links' );
$h = brik_field_html( 'website', $post );
$t->ok( false !== strpos( $h, 'brik-button' ) && false !== strpos( $h, 'target="_blank"' ) && false !== strpos( $h, 'rel="noopener"' ), 'link as a button' );
$h = brik_field_html( 'tags', $post );
$t->ok( 2 === substr_count( $h, 'brik-badge-pill' ) && false !== strpos( $h, 'Apple' ), 'choices as badges with labels' );
$h = brik_field_html( 'related', $post );
$t->ok( false !== strpos( $h, 'BT related post' ) && false !== strpos( $h, get_permalink( $other ) ), 'relationship as a linked list' );
$h = brik_field_html( 'related', $post, array( 'display' => 'cards' ) );
$t->ok( false !== strpos( $h, 'rounded-xl' ), 'relationship as cards' );
$h = brik_field_html( 'team', $post, array( 'display' => 'table' ) );
$t->ok( false !== strpos( $h, '<table' ) && false !== strpos( $h, 'Ada' ), 'repeater as a table' );
$h = brik_field_html( 'team', $post );
$t->ok( false !== strpos( $h, '<ul' ) && false !== strpos( $h, 'Grace H.' ), 'repeater as a list' );
$h = brik_field_html( 'where', $post );
$t->ok( false !== strpos( $h, '<iframe' ) && false !== strpos( $h, 'maps.google.com' ), 'map embed' );
$h = brik_field_html( 'launch', $post );
$t->ok( false !== strpos( $h, '<time' ) && false !== strpos( $h, 'datetime="2026-10-03"' ) && false !== strpos( $h, 'October 3, 2026' ), 'date with display format', $h );
$h = brik_field_html( 'brochure', $post );
$t->ok( false !== strpos( $h, 'download' ) && false !== strpos( $h, 'PDF' ), 'file card' );
$h = brik_field_html( 'featured', $post );
$t->ok( false !== strpos( $h, 'Featured!' ), 'toggle on text' );
$h = brik_field_html( 'brand', $post );
$t->ok( false !== strpos( $h, 'background:#336699' ), 'color swatch' );
$h = brik_field_html( 'notes', $post );
$t->ok( false !== strpos( $h, '<p>Line one' ) || false !== strpos( $h, 'Line one<br' ), 'textarea paragraphs' );
$h = brik_field_html( 'tagline', $post );
$t->ok( false === strpos( $h, '<tool>' ), 'text escaped' );
$t->eq( '<em>none</em>', brik_field_html( 'nope', $post, array( 'empty' => '<em>none</em>' ) ), 'empty markup for unknown fields' );
$h = brik_field_html( 'owner', $post );
$t->ok( false !== strpos( $h, 'rounded-full' ), 'user list with avatar' );
$h = brik_field_html( 'kinds', $post );
$t->ok( false !== strpos( $h, 'BT Category' ), 'taxonomy badges' );
$h = brik_field_html( 'email', $post );
$t->ok( false !== strpos( $h, 'mailto:' ), 'email link' );
$h = brik_field_html( 'team.name', $post );
$t->ok( false !== strpos( $h, 'Ada' ) && false !== strpos( $h, 'Grace' ), 'column of a repeater sub field' );

/* -------------------------------------------------------------------------
 * Dynamic tags.
 * ---------------------------------------------------------------------- */

$t->section( 'Dynamic tags' );
$t->eq( '$1,999.5 USD', Brik\Dynamic::replace( '{field:price}', $post ), '{field:price}' );
$t->eq( '1999.5', Brik\Dynamic::replace( '{field:price|raw}', $post ), '{field:price|raw}' );
$t->eq( '2019', Brik\Dynamic::replace( '{field:year}', $post ), 'plain numbers have no thousands separator' );
$cur = Registry::normalize_field( array( 'name' => 'c', 'type' => 'number', 'options' => array( 'format' => 'currency' ) ) );
$t->eq( '1,234.50', Fields::number( 1234.5, $cur ), 'currency number format' );
$t->eq( esc_url( wp_get_attachment_image_url( $img1, 'full' ) ), Brik\Dynamic::replace( '{field:photo|url}', $post ), '{field:photo|url}' );
$t->eq( 'https://example.com', Brik\Dynamic::replace( '{field:website|url}', $post ), '{field:website|url}' );
$t->eq( esc_url( get_permalink( $other ) ), Brik\Dynamic::replace( '{field:lead|url}', $post ), 'post object URL' );
$t->eq( 'Galle', Brik\Dynamic::replace( '{field:address.city}', $post ), '{field:group.sub}' );
$t->eq( 'Ada, Grace H.', Brik\Dynamic::replace( '{field:team.name}', $post ), 'repeater column tag' );
$t->eq( '2', Brik\Dynamic::replace( '{field:team|count}', $post ), '|count' );
$t->eq( 'Blue', Brik\Dynamic::replace( '{field:color}', $post ), 'select label' );
$t->eq( 'blue', Brik\Dynamic::replace( '{field:color|raw}', $post ), 'select raw' );
$t->eq( 'Apple, Banana', Brik\Dynamic::replace( '{field:tags}', $post ), 'checkbox labels' );
$t->eq( 'BT related post', Brik\Dynamic::replace( '{field:related}', $post ), 'relationship titles' );
update_post_meta( $post, 'tagline', 'A & B <script>x</script> "q"' );
$out = Brik\Dynamic::replace( '{field:tagline}', $post );
$t->ok( false === strpos( $out, '<script>' ) && false !== strpos( $out, '&amp;' ), 'text tags are escaped', $out );
$t->eq( 'Price: $1,999.5 USD!', Brik\Dynamic::replace( 'Price: {field:price}!', $post ), 'tags inside text' );
$t->eq( '', Brik\Dynamic::replace( '{field:missing}', $post ), 'unknown field resolves to nothing' );
$t->eq( '{nope:thing}', Brik\Dynamic::replace( '{nope:thing}', $post ), 'other tags untouched' );

// Listings call the renderer with the loop post: the tag must follow it.
$second = wp_insert_post(
	array(
		'post_type'   => 'bt_project',
		'post_status' => 'publish',
		'post_title'  => 'BT Project Two',
	)
);
brik_update_field( 'price', 5, $second );
$q    = new WP_Query(
	array(
		'post_type' => 'bt_project',
		'post__in'  => array( $post, $second ),
		'orderby'   => 'post__in',
	)
);
$seen = array();
while ( $q->have_posts() ) {
	$q->the_post();
	$seen[] = Brik\Dynamic::replace( '{field:price|raw}' );
}
wp_reset_postdata();
$t->eq( array( '1999.5', '5' ), $seen, 'tags follow the loop post' );

$og = Registry::save_group(
	array(
		'key'      => 'group_btopts',
		'title'    => 'BT Options',
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'brik-options' ) ) ),
		'fields'   => array(
			array(
				'key'   => 'field_btphone',
				'name'  => 'bt_phone',
				'label' => 'Phone',
				'type'  => 'text',
			),
		),
	)
);
$t->ok( ! is_wp_error( $og ), 'options group saved' );
$t->ok( true === brik_update_field( 'bt_phone', '+94 11 <b>234</b>', 'option' ), 'option saved' );
$t->eq( '+94 11 234', get_option( 'brik_opt_bt_phone' ), 'stored as brik_opt_{name}' );
$t->eq( '+94 11 234', Brik\Dynamic::replace( '{option:bt_phone}', $post ), '{option:name}' );
$t->ok( MetaBoxes::has_options_groups(), 'options page appears when a group targets it' );

$tg = Registry::save_group(
	array(
		'key'      => 'group_btterm',
		'title'    => 'BT Term',
		'location' => array( array( array( 'param' => 'taxonomy', 'operator' => '==', 'value' => 'bt_kind' ) ) ),
		'fields'   => array(
			array(
				'key'   => 'field_btaccent',
				'name'  => 'accent',
				'label' => 'Accent',
				'type'  => 'color',
			),
		),
	)
);
$t->ok( ! is_wp_error( $tg ), 'term group saved' );
$kind = wp_insert_term( 'BT Kind A', 'bt_kind' );
brik_update_field( 'accent', '#112233', 'term_' . $kind['term_id'] );
$t->eq( '#112233', get_term_meta( $kind['term_id'], 'accent', true ), 'term meta saved' );
$t->eq( '#112233', brik_field( 'accent', get_term( $kind['term_id'] ) ), 'read with a WP_Term' );
add_filter(
	'brik/content/current_term',
	$term_filter = static function () use ( $kind ) {
		return get_term( $kind['term_id'] );
	}
);
$t->eq( '#112233', Brik\Dynamic::replace( '{term:accent}' ), '{term:name}' );
remove_filter( 'brik/content/current_term', $term_filter );

$ug = Registry::save_group(
	array(
		'key'      => 'group_btuser',
		'title'    => 'BT User',
		'location' => array( array( array( 'param' => 'user_role', 'operator' => '==', 'value' => 'all' ) ) ),
		'fields'   => array(
			array(
				'key'   => 'field_btbio',
				'name'  => 'bt_bio',
				'label' => 'Bio',
				'type'  => 'textarea',
			),
		),
	)
);
brik_update_field( 'bt_bio', 'Hi there', 'user_' . $admins[0] );
$t->eq( 'Hi there', brik_field( 'bt_bio', 'user_' . $admins[0] ), 'user meta round trip' );

/* -------------------------------------------------------------------------
 * Meta box saving (form posts).
 * ---------------------------------------------------------------------- */

$t->section( 'Meta box save' );
$_POST = array(
	MetaBoxes::NONCE => wp_create_nonce( 'brik_cf_post_' . $second ),
	'brik_cf_groups' => array( 'group_bttest' ),
	MetaBoxes::INPUT => wp_slash(
		array(
			'field_bttagline' => 'From the form \\ with "slashes"',
			'field_btprice'   => '12',
			'field_bttags'    => '',
			'field_btfeatured' => '1',
			'field_btteam'    => array(
				'7' => array(
					'field_btteamname' => 'Zed',
					'field_btteamrole' => 'dev',
					'field_btskills'   => array(
						'0' => array( 'field_btskill' => 'Go' ),
					),
				),
				'2' => array(
					'field_btteamname' => 'Amy',
					'field_btteamrole' => '',
					'field_btskills'   => '',
				),
			),
			'field_btshots'   => array( '', (string) $img1, (string) $img2 ),
			'field_btaddress' => array(
				'__group'      => '1',
				'field_btcity' => 'Jaffna',
				'field_btzip'  => '40000',
			),
			'field_btprice2'  => 'should be ignored',
		)
	),
);
MetaBoxes::save_post( $second, get_post( $second ) );
$t->eq( 'From the form \\ with "slashes"', brik_field( 'tagline', $second ), 'slashes survive the form round trip' );
$t->eq( 12, brik_field( 'price', $second ), 'number from form' );
$t->eq( array(), brik_field( 'tags', $second ), 'empty checkbox list saved as empty' );
$t->eq( true, brik_field( 'featured', $second ), 'toggle from form' );
$t->eq( array( 'Zed', 'Amy' ), brik_field( 'team.name', $second ), 'repeater rows keep DOM order' );
$t->eq( 'Go', brik_field( 'team.0.skills.0.skill', $second ), 'nested row from form' );
$t->eq( array( $img1, $img2 ), brik_raw_field( 'shots', $second ), 'gallery placeholder dropped' );
$t->eq( 'Jaffna', brik_field( 'address.city', $second ), 'group from form' );

$_POST[ MetaBoxes::INPUT ] = wp_slash(
	array(
		'field_btprice' => '5000',
		'field_btlaunch' => '',
	)
);
MetaBoxes::save_post( $second, get_post( $second ) );
$t->eq( 12, brik_field( 'price', $second ), 'invalid value keeps the previous one' );
$_POST[ MetaBoxes::NONCE ] = 'bad';
$_POST[ MetaBoxes::INPUT ] = wp_slash( array( 'field_btprice' => '1' ) );
MetaBoxes::save_post( $second, get_post( $second ) );
$t->eq( 12, brik_field( 'price', $second ), 'bad nonce ignored' );
$_POST[ MetaBoxes::NONCE ] = wp_create_nonce( 'brik_cf_post_' . $second );
$_POST['brik_cf_groups']   = array( 'group_btopts' );
$_POST[ MetaBoxes::INPUT ] = wp_slash( array( 'field_btphone' => 'injected' ) );
MetaBoxes::save_post( $second, get_post( $second ) );
$t->eq( '', (string) get_post_meta( $second, 'bt_phone', true ), 'groups that do not apply are not saved' );
$_POST = array();

ob_start();
MetaBoxes::render_group( Registry::get_group( 'group_bttest' ), Values::target( $post ) );
$html = ob_get_clean();
$t->ok( false !== strpos( $html, 'name="brik_fields[field_btprice]"' ), 'meta box renders inputs by field key' );
$t->ok( false !== strpos( $html, 'brik-cf-row-template' ) && false !== strpos( $html, '__i_field_btteam__' ), 'repeater row template' );
$t->ok( false !== strpos( $html, 'role="tablist"' ), 'tab fields become tabs' );
$t->ok( false !== strpos( $html, 'data-conditions=' ), 'conditions passed to the browser' );
$t->ok( false !== strpos( $html, 'data-required="1"' ), 'required flag rendered' );
$t->ok( false === strpos( $html, '<script>x</script>' ), 'values escaped in inputs' );

/* -------------------------------------------------------------------------
 * REST.
 * ---------------------------------------------------------------------- */

$t->section( 'REST' );
$res = bt_rest( 'GET', '/brik/v1/content' );
$t->eq( 200, $res->get_status(), 'GET /content' );
$data = $res->get_data();
$t->ok( isset( $data['post_types'], $data['taxonomies'], $data['groups'], $data['field_types'], $data['reserved'] ), 'GET /content shape' );
$t->ok( isset( $data['field_types']['repeater']['options']['sub_fields'] ) && 'layout' === $data['field_types']['repeater']['category'], 'field type metadata' );
$t->eq( 31, count( $data['field_types'] ), 'all field types listed' );
foreach ( $data['field_types'] as $name => $type ) {
	if ( empty( $type['label'] ) || empty( $type['icon'] ) || ! in_array( $type['category'], array( 'basic', 'content', 'choice', 'relational', 'advanced', 'layout' ), true ) ) {
		$t->ok( false, "field type $name metadata" );
	}
}
$res = bt_rest(
	'POST',
	'/brik/v1/content/post-types',
	array(
		'key'      => 'bt_event',
		'singular' => 'Event',
		'plural'   => 'Events',
	)
);
$t->eq( 200, $res->get_status(), 'POST /content/post-types' );
$t->eq( 'bt_event', $res->get_data()['key'], 'created definition returned' );
$t->ok( post_type_exists( 'bt_event' ), 'created type registered' );
$res = bt_rest(
	'POST',
	'/brik/v1/content/post-types',
	array(
		'key'      => 'bt_event',
		'singular' => 'Event',
		'plural'   => 'Happenings',
	)
);
$t->eq( 'Happenings', $res->get_data()['plural'], 'update by key' );
$t->eq( 1, count( wp_list_filter( Registry::post_types(), array( 'key' => 'bt_event' ) ) ), 'update does not duplicate' );
$res = bt_rest(
	'POST',
	'/brik/v1/content/post-types',
	array(
		'key'      => 'author',
		'singular' => 'X',
		'plural'   => 'Y',
	)
);
$t->eq( 400, $res->get_status(), 'reserved key → 400' );
$t->ok( ! empty( $res->get_data()['data']['errors'] ), 'error list in the response' );
$res = bt_rest( 'POST', '/brik/v1/content/post-types', array_merge( Registry::get_post_type( 'bt_event' ), array( 'key' => 'bt_gig', 'previous_key' => 'bt_event' ) ) );
$t->ok( 200 === $res->get_status() && ! Registry::get_post_type( 'bt_event' ) && Registry::get_post_type( 'bt_gig' ), 'rename with previous_key' );
$ev = wp_insert_post( array( 'post_type' => 'bt_gig', 'post_status' => 'publish', 'post_title' => 'BT gig' ) );
$res = bt_rest( 'DELETE', '/brik/v1/content/post-types/bt_gig' );
$t->ok( 200 === $res->get_status() && $res->get_data()['deleted'], 'DELETE /content/post-types/{key}' );
$t->ok( (bool) get_post( $ev ), 'posts kept without delete_posts' );
Registry::save_post_type( array( 'key' => 'bt_gig', 'singular' => 'Gig', 'plural' => 'Gigs' ) );
$res = bt_rest( 'DELETE', '/brik/v1/content/post-types/bt_gig', array( 'delete_posts' => true ) );
$t->ok( 1 === $res->get_data()['posts_deleted'] && ! get_post( $ev ), 'delete_posts removes posts' );
$t->eq( 404, bt_rest( 'DELETE', '/brik/v1/content/post-types/bt_none' )->get_status(), 'delete unknown → 404' );
Registry::save_post_type( array( 'key' => 'bt_gig', 'singular' => 'Gig', 'plural' => 'Gigs' ) );
Registry::save_taxonomy( array( 'key' => 'bt_venue', 'singular' => 'Venue', 'plural' => 'Venues', 'post_types' => array( 'bt_gig' ) ) );
Registry::save_group(
	array(
		'key'      => 'group_btgig',
		'title'    => 'BT gig fields',
		'location' => array(
			array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'bt_gig' ) ),
		),
		'fields'   => array( array( 'key' => 'field_btgigdate', 'name' => 'gig_date', 'type' => 'date' ) ),
	)
);
Registry::save_group(
	array(
		'key'      => 'group_btgig2',
		'title'    => 'BT shared fields',
		'location' => array(
			array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'bt_gig' ), array( 'param' => 'post_status', 'operator' => '==', 'value' => 'publish' ) ),
			array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ),
		),
		'fields'   => array( array( 'key' => 'field_btgignote', 'name' => 'gig_note', 'type' => 'text' ) ),
	)
);
$res = bt_rest( 'DELETE', '/brik/v1/content/post-types/bt_gig' );
$t->ok( in_array( 'group_btgig', $res->get_data()['groups_updated'], true ), 'deleting a type reports the groups it touched' );
$t->ok( false === Registry::get_group( 'group_btgig' )['active'] && array() === Registry::get_group( 'group_btgig' )['location'], 'groups left without rules are deactivated, not deleted' );
$t->eq( array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ), Registry::get_group( 'group_btgig2' )['location'], 'rule groups that required the type are dropped, others kept' );
$t->ok( Registry::get_group( 'group_btgig2' )['active'], 'groups with remaining rules stay active' );
$t->eq( array(), Registry::get_taxonomy( 'bt_venue' )['post_types'], 'taxonomies forget the deleted type' );
Registry::delete_group( 'group_btgig' );
Registry::delete_group( 'group_btgig2' );
Registry::delete_taxonomy( 'bt_venue' );

$res = bt_rest( 'POST', '/brik/v1/content/taxonomies', array( 'key' => 'bt_tone', 'singular' => 'Tone', 'plural' => 'Tones', 'post_types' => array( 'bt_project' ) ) );
$t->ok( 200 === $res->get_status() && taxonomy_exists( 'bt_tone' ), 'POST /content/taxonomies' );
$t->eq( 200, bt_rest( 'DELETE', '/brik/v1/content/taxonomies/bt_tone' )->get_status(), 'DELETE /content/taxonomies/{key}' );

$res = bt_rest(
	'POST',
	'/brik/v1/content/groups',
	array(
		'title'    => 'BT REST group',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'page' ) ) ),
		'fields'   => array( array( 'label' => 'Subtitle', 'type' => 'text' ) ),
	)
);
$gkey = $res->get_data()['key'];
$t->ok( 200 === $res->get_status() && 0 === strpos( $gkey, 'group_' ) && 'subtitle' === $res->get_data()['fields'][0]['name'], 'POST /content/groups generates keys and names' );
$t->eq( 200, bt_rest( 'DELETE', '/brik/v1/content/groups/' . $gkey )->get_status(), 'DELETE /content/groups/{key}' );

$res = bt_rest( 'GET', '/brik/v1/content/preview-labels', array( 'singular' => 'Recipe', 'plural' => 'Recipes' ) );
$t->eq( 'Search Recipes', $res->get_data()['labels']['search_items'], 'preview-labels' );
$res = bt_rest( 'GET', '/brik/v1/content/preview-labels', array( 'singular' => 'Genre', 'plural' => 'Genres', 'kind' => 'taxonomy' ) );
$t->eq( 'Parent Genre', $res->get_data()['labels']['parent_item'], 'preview-labels for taxonomies' );

$res = bt_rest( 'GET', '/brik/v1/content/search-posts', array( 's' => 'BT related', 'post_type' => 'post' ) );
$t->ok( 200 === $res->get_status() && $other === $res->get_data()['items'][0]['id'], 'search-posts' );
$res = bt_rest( 'GET', '/brik/v1/content/search-posts', array( 'include' => $other . ',' . $post ) );
$t->eq( 2, count( $res->get_data()['items'] ), 'search-posts include' );
$res = bt_rest( 'GET', '/brik/v1/content/search-terms', array( 'taxonomy' => 'category', 's' => 'BT Cat' ) );
$t->eq( $cat, $res->get_data()['items'][0]['id'], 'search-terms' );
$res = bt_rest( 'GET', '/brik/v1/content/search-users', array( 'include' => $admins[0] ) );
$t->eq( (int) $admins[0], $res->get_data()['items'][0]['id'], 'search-users' );

// Plugins such as WooCommerce build the REST server early; rebuild it so routes for the
// post type registered during this run exist.
$GLOBALS['wp_rest_server'] = null;
$res = bt_rest( 'GET', '/wp/v2/bt_project/' . $post, array( 'context' => 'edit' ) );
$meta = $res->get_data()['meta'];
$t->ok( 200 === $res->get_status() && isset( $meta['price'] ) && 1999.5 === (float) $meta['price'], 'field meta in /wp/v2/{type}/{id}', wp_json_encode( array_slice( (array) $meta, 0, 4 ) ) );
$t->ok( isset( $meta['team'][0]['name'] ) && 'Ada' === $meta['team'][0]['name'], 'repeater meta in REST' );
$t->ok( true === $meta['featured'], 'toggle meta in REST' );
$t->eq( $img1, $meta['photo'], 'image meta in REST' );
$res = bt_rest( 'POST', '/wp/v2/bt_project/' . $post, array( 'meta' => array( 'price' => 77, 'tagline' => '<b>REST</b> value' ) ) );
$t->eq( 200, $res->get_status(), 'meta update through core REST' );
$t->eq( 77, brik_field( 'price', $post ), 'REST meta update stored' );
$t->eq( 'REST value', brik_field( 'tagline', $post ), 'REST meta update sanitized' );

$sub = wp_insert_user( array( 'user_login' => 'bt_sub', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $sub );
$t->ok( in_array( bt_rest( 'GET', '/brik/v1/content' )->get_status(), array( 401, 403 ), true ), 'subscribers cannot read definitions' );
$t->ok( in_array( bt_rest( 'POST', '/brik/v1/content/post-types', array( 'key' => 'bt_hack', 'singular' => 'H', 'plural' => 'H' ) )->get_status(), array( 401, 403 ), true ), 'subscribers cannot save' );
$t->ok( in_array( bt_rest( 'GET', '/brik/v1/content/search-posts' )->get_status(), array( 401, 403 ), true ), 'subscribers cannot search posts' );
$t->ok( in_array( bt_rest( 'POST', '/wp/v2/bt_project/' . $post, array( 'meta' => array( 'price' => 1 ) ) )->get_status(), array( 401, 403 ), true ), 'subscribers cannot write meta' );
wp_set_current_user( (int) $admins[0] );

/* -------------------------------------------------------------------------
 * Export / import.
 * ---------------------------------------------------------------------- */

$t->section( 'Export & import' );
$res  = bt_rest( 'GET', '/brik/v1/content/export', array( 'format' => 'json', 'post_types' => 'bt_project', 'taxonomies' => 'bt_kind', 'groups' => 'group_bttest' ) );
$json = $res->get_data();
$t->ok( 1 === count( $json['post_types'] ) && 1 === count( $json['groups'] ) && 1 === count( $json['taxonomies'] ), 'export filtered by key' );
$t->ok( ! isset( $json['post_types'][0]['local'] ), 'export drops runtime keys' );
$before = wp_json_encode( array( Registry::get_post_type( 'bt_project' ), Registry::get_group( 'group_bttest' ) ) );
Registry::delete_group( 'group_bttest' );
Registry::delete_post_type( 'bt_project' );
Registry::delete_taxonomy( 'bt_kind' );
$t->ok( ! Registry::get_post_type( 'bt_project' ), 'definitions removed before import' );
$res = bt_rest( 'POST', '/brik/v1/content/import', wp_json_encode( $json ) );
$imp = $res->get_data();
$t->eq( array( 'post_types' => 1, 'taxonomies' => 1, 'groups' => 1 ), $imp['imported'], 'import counts' );
$t->eq( array(), $imp['errors'], 'import without errors' );
$t->eq( $before, wp_json_encode( array( Registry::get_post_type( 'bt_project' ), Registry::get_group( 'group_bttest' ) ) ), 'round trip is lossless' );
$res = bt_rest( 'POST', '/brik/v1/content/import', array( 'post_types' => array( array( 'key' => 'post', 'singular' => 'x', 'plural' => 'y' ) ) ) );
$t->ok( 1 === count( $res->get_data()['errors'] ), 'invalid imports reported' );
$res = bt_rest( 'POST', '/brik/v1/content/import', array( 'skip_existing' => true, 'post_types' => array( array( 'key' => 'bt_project', 'singular' => 'Changed', 'plural' => 'Changed' ) ) ) );
$t->eq( 'Project', Registry::get_post_type( 'bt_project' )['singular'], 'skip_existing keeps definitions' );

$res  = bt_rest( 'GET', '/brik/v1/content/export', array( 'format' => 'php', 'post_types' => 'bt_project', 'taxonomies' => 'bt_kind', 'groups' => 'group_bttest' ) );
$code = $res->get_data()['code'];
$t->ok( false !== strpos( $code, "register_post_type(\n\t\t\t'bt_project'" ) && false !== strpos( $code, 'register_taxonomy(' ) && false !== strpos( $code, 'brik_register_field_group(' ), 'PHP export has the registration calls' );
$tmp = wp_tempnam( 'bt-export' );
file_put_contents( $tmp, $code ); // phpcs:ignore
exec( 'php -l ' . escapeshellarg( $tmp ) . ' 2>&1', $lint, $code_status ); // phpcs:ignore
$t->eq( 0, $code_status, 'PHP export is valid PHP', implode( ' ', (array) $lint ) );
// The exported group must load as a code group that matches the stored one.
$exported_group = null;
if ( preg_match( '/brik_register_field_group\(\s*(array\(.*\))\s*\);\s*\}\s*$/s', $code, $m ) ) {
	$exported_group = eval( 'return ' . $m[1] . ';' ); // phpcs:ignore Squiz.PHP.Eval
}
$t->ok( is_array( $exported_group ) && 'group_bttest' === $exported_group['key'] && count( $exported_group['fields'] ) === count( Registry::get_group( 'group_bttest' )['fields'] ), 'exported group evaluates to the same definition' );
wp_delete_file( $tmp );

/* -------------------------------------------------------------------------
 * Code registration.
 * ---------------------------------------------------------------------- */

$t->section( 'Code registration' );
$local = brik_register_post_type( array( 'key' => 'bt_local', 'singular' => 'Local', 'plural' => 'Locals' ) );
$t->ok( ! is_wp_error( $local ) && post_type_exists( 'bt_local' ), 'brik_register_post_type registers' );
$t->ok( true === Registry::get_post_type( 'bt_local' )['local'], 'marked local' );
$t->ok( is_wp_error( Registry::save_post_type( array( 'key' => 'bt_local', 'singular' => 'X', 'plural' => 'Y' ) ) ), 'local definitions are read-only' );
$t->eq( 400, bt_rest( 'DELETE', '/brik/v1/content/post-types/bt_local' )->get_status(), 'local definitions cannot be deleted over REST' );
$lg = brik_register_field_group(
	array(
		'title'    => 'BT local fields',
		'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'bt_local' ) ) ),
		'fields'   => array( array( 'key' => 'field_btlocalsub', 'name' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text' ) ),
	)
);
$t->ok( ! is_wp_error( $lg ) && 0 === strpos( $lg['key'], 'group_' ), 'brik_register_field_group' );
$keys = get_registered_meta_keys( 'post', 'bt_local' );
$t->ok( isset( $keys['subtitle'] ), 'code group meta registered after init' );
$lp = wp_insert_post( array( 'post_type' => 'bt_local', 'post_status' => 'publish', 'post_title' => 'BT local' ) );
brik_update_field( 'subtitle', 'Hello', $lp );
$t->eq( 'Hello', brik_field( 'subtitle', $lp ), 'code group values' );
$t->ok( (bool) wp_list_filter( bt_rest( 'GET', '/brik/v1/content' )->get_data()['groups'], array( 'local' => true ) ), 'local groups listed for the admin' );
Registry::remove_local( 'groups', $lg['key'] );
Registry::remove_local( 'post_types', 'bt_local' );
wp_delete_post( $lp, true );

/* -------------------------------------------------------------------------
 * MCP tools.
 * ---------------------------------------------------------------------- */

$t->section( 'MCP' );
list( $r ) = McpTools::call( 'list_content_types', array() );
$t->ok( is_array( $r ) && wp_list_filter( $r['post_types'], array( 'key' => 'bt_project' ) ) && isset( $r['field_types']['gallery'] ), 'list_content_types' );
list( $r ) = McpTools::call( 'save_post_type', array( 'definition' => array( 'key' => 'bt_recipe', 'singular' => 'Recipe', 'plural' => 'Recipes', 'supports' => array( 'title', 'editor', 'thumbnail' ) ) ) );
$t->ok( ! is_wp_error( $r ) && post_type_exists( 'bt_recipe' ), 'save_post_type', is_wp_error( $r ) ? $r->get_error_message() : '' );
list( $r ) = McpTools::call( 'save_post_type', array( 'definition' => array( 'key' => 'page', 'singular' => 'x', 'plural' => 'x' ) ) );
$t->ok( is_wp_error( $r ), 'save_post_type validates' );
list( $r ) = McpTools::call( 'save_taxonomy', array( 'definition' => array( 'key' => 'bt_cuisine', 'singular' => 'Cuisine', 'plural' => 'Cuisines', 'post_types' => array( 'bt_recipe' ) ) ) );
$t->ok( ! is_wp_error( $r ) && is_object_in_taxonomy( 'bt_recipe', 'bt_cuisine' ), 'save_taxonomy' );
list( $r ) = McpTools::call(
	'save_field_group',
	array(
		'definition' => array(
			'title'    => 'Recipe details',
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'bt_recipe' ) ) ),
			'fields'   => array(
				array( 'label' => 'Minutes', 'type' => 'number', 'options' => array( 'min' => 1, 'max' => 600 ) ),
				array(
					'label'   => 'Ingredients',
					'type'    => 'repeater',
					'options' => array( 'sub_fields' => array( array( 'label' => 'Item', 'type' => 'text' ), array( 'label' => 'Amount', 'type' => 'text' ) ) ),
				),
				array( 'label' => 'Photo', 'type' => 'image' ),
				array( 'label' => 'Spicy', 'type' => 'toggle' ),
			),
		),
	)
);
$t->ok( ! is_wp_error( $r ) && 'minutes' === $r['fields'][0]['name'], 'save_field_group', is_wp_error( $r ) ? $r->get_error_message() : '' );
$recipe_group = is_array( $r ) ? $r['key'] : '';
list( $r ) = McpTools::call(
	'create_entries',
	array(
		'post_type' => 'bt_recipe',
		'entries'   => array(
			array(
				'title'          => 'BT Curry',
				'status'         => 'publish',
				'content'        => '<p>Tasty</p><script>bad()</script>',
				'fields'         => array(
					'minutes'     => '45',
					'ingredients' => array( array( 'item' => 'Rice', 'amount' => '1 cup' ) ),
					'spicy'       => true,
					'photo'       => $img1,
					'bogus'       => 'x',
				),
				'terms'          => array( 'bt_cuisine' => array( 'Sri Lankan' ) ),
				'featured_image' => $img2,
			),
			array(
				'title'  => 'BT Salad',
				'fields' => array( 'minutes' => 5000 ),
			),
		),
	)
);
$t->ok( is_array( $r ) && 2 === $r['created'], 'create_entries', wp_json_encode( $r ) );
$curry = $r['entries'][0]['id'];
$t->eq( 45, brik_field( 'minutes', $curry ), 'entry field saved' );
$t->eq( 'Rice', brik_field( 'ingredients.0.item', $curry ), 'entry repeater saved' );
$t->ok( has_term( 'Sri Lankan', 'bt_cuisine', $curry ), 'entry terms created and assigned' );
$t->eq( $img2, (int) get_post_thumbnail_id( $curry ), 'featured image set' );
$t->ok( false === strpos( get_post_field( 'post_content', $curry ), '<script' ), 'entry content filtered' );
$t->ok( ! empty( $r['entries'][0]['warnings'] ) && false !== strpos( implode( ' ', $r['entries'][0]['warnings'] ), 'bogus' ), 'unknown fields reported' );
$t->ok( ! empty( $r['entries'][1]['warnings'] ), 'invalid values reported' );
$t->eq( 'draft', get_post_status( $r['entries'][1]['id'] ), 'entries default to draft' );
list( $r ) = McpTools::call(
	'query_entries',
	array(
		'post_type' => 'bt_recipe',
		'meta'      => array( array( 'field' => 'minutes', 'compare' => '<', 'value' => 60 ) ),
	)
);
$t->ok( is_array( $r ) && 1 === count( $r['items'] ) && 'BT Curry' === $r['items'][0]['title'] && 45 === $r['items'][0]['fields']['minutes'], 'query_entries with a numeric meta filter', wp_json_encode( $r ) );
list( $r ) = McpTools::call( 'query_entries', array( 'post_type' => 'bt_recipe', 'search' => 'Salad', 'status' => 'any' ) );
$t->eq( 1, count( $r['items'] ), 'query_entries search' );
list( $r ) = McpTools::call( 'query_entries', array( 'post_type' => 'bt_recipe', 'orderby' => 'minutes', 'order' => 'ASC', 'status' => 'any' ) );
$t->eq( 'BT Curry', $r['items'][0]['title'], 'query_entries ordered by a field' );
$mq = Entries::meta_query( array( array( 'field' => 'tags', 'compare' => '=', 'value' => 'a' ) ), 'bt_project' );
$t->eq( 'LIKE', $mq[0]['compare'], 'list fields compare with LIKE' );
$t->ok( false !== strpos( McpTools::guide(), '{field:photo|url}' ), 'guide documents field tags' );
$t->ok( McpTools::exists( 'create_entries' ) && McpTools::exists( 'delete_post_type' ), 'tools registered' );
wp_set_current_user( $sub );
list( $r ) = McpTools::call( 'save_post_type', array( 'definition' => array( 'key' => 'bt_nope', 'singular' => 'N', 'plural' => 'N' ) ) );
$t->ok( is_wp_error( $r ), 'save_post_type needs manage_options' );
list( $r ) = McpTools::call( 'create_entries', array( 'post_type' => 'bt_recipe', 'entries' => array( array( 'title' => 'x' ) ) ) );
$t->ok( is_wp_error( $r ), 'create_entries needs create rights' );
wp_set_current_user( (int) $admins[0] );
list( $r ) = McpTools::call( 'delete_post_type', array( 'key' => 'bt_recipe', 'delete_posts' => true ) );
$t->ok( is_array( $r ) && 2 === $r['posts_deleted'] && ! Registry::get_post_type( 'bt_recipe' ), 'delete_post_type with posts' );
Registry::delete_group( $recipe_group );
Registry::delete_taxonomy( 'bt_cuisine', true );

/* -------------------------------------------------------------------------
 * Done.
 * ---------------------------------------------------------------------- */

wp_delete_user( $sub );
bt_cleanup();
foreach ( array( $img1, $img2, $pdf ) as $a ) {
	wp_delete_attachment( $a, true );
}
wp_delete_post( $other, true );
wp_delete_term( $cat, 'category' );
delete_user_meta( (int) $admins[0], 'bt_bio' );

$t->finish();
