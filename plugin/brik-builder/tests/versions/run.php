<?php
/**
 * Versions, staging and scheduled publishing tests. Runs inside WordPress through WP-CLI:
 *
 *   ./dev/wp.sh eval-file /var/www/html/wp-content/plugins/brik-builder/tests/versions/run.php
 *
 * Front-end checks fetch pages over HTTP from the CLI container (http://wordpress, with the
 * site's Host header). Everything created uses the "BV " title prefix and is removed at the end.
 *
 * @package Brik
 */

use Brik\Data;
use Brik\McpTools;
use Brik\Versions\Diff;
use Brik\Versions\Endpoints;
use Brik\Versions\Schedule;
use Brik\Versions\Staging;
use Brik\Versions\Versions;

defined( 'ABSPATH' ) || exit;

final class Brik_Versions_Test {

	public $pass = 0;

	public $fail = 0;

	public $failures = array();

	public function section( $name ) {
		echo "\n== " . $name . " ==\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public function ok( $cond, $name, $detail = '' ) {
		if ( $cond ) {
			++$this->pass;
			echo '  PASS ' . $name . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return true;
		}
		++$this->fail;
		$this->failures[] = $name . ( $detail ? ' — ' . $detail : '' );
		echo '  FAIL ' . $name . ( $detail ? ' — ' . $detail : '' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		return false;
	}

	public function eq( $expected, $actual, $name ) {
		return $this->ok( $expected === $actual, $name, $expected === $actual ? '' : 'expected ' . wp_json_encode( $expected ) . ', got ' . wp_json_encode( $actual ) );
	}

	public function has( $needle, $haystack, $name ) {
		return $this->ok( false !== strpos( (string) $haystack, $needle ), $name, 'missing "' . $needle . '" in "' . mb_substr( (string) $haystack, 0, 200 ) . '"' );
	}
}

/**
 * GET a front-end URL as a logged-out visitor, following redirects inside the container.
 */
function bv_fetch( $url ) {
	$home = home_url();
	for ( $i = 0; $i < 4; $i++ ) {
		$internal = str_replace( $home, 'http://wordpress', $url );
		$r        = wp_remote_get(
			$internal,
			array(
				'headers'     => array( 'Host' => wp_parse_url( $home, PHP_URL_HOST ) . ( wp_parse_url( $home, PHP_URL_PORT ) ? ':' . wp_parse_url( $home, PHP_URL_PORT ) : '' ) ),
				'redirection' => 0,
				'timeout'     => 20,
			)
		);
		if ( is_wp_error( $r ) ) {
			return array( 0, $r->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $r );
		if ( $code >= 300 && $code < 400 ) {
			$url = wp_remote_retrieve_header( $r, 'location' );
			continue;
		}
		return array( $code, wp_remote_retrieve_body( $r ) );
	}
	return array( 0, 'too many redirects' );
}

function bv_heading( $id, $text, $attrs = array() ) {
	return array(
		'id'    => $id,
		'type'  => 'heading',
		'attrs' => array_merge( array( 'text' => $text ), $attrs ),
	);
}

function bv_section( $id, array $modules, $attrs = array() ) {
	return array(
		'id'       => $id,
		'type'     => 'section',
		'attrs'    => $attrs,
		'children' => array(
			array(
				'id'       => $id . 'r',
				'type'     => 'row',
				'attrs'    => array( 'columns' => '1' ),
				'children' => array(
					array(
						'id'       => $id . 'c',
						'type'     => 'column',
						'attrs'    => array(),
						'children' => $modules,
					),
				),
			),
		),
	);
}

function bv_tree( $hero = 'Welcome aboard', $features_size = '', $with_footer = true, $with_pricing = false ) {
	$tree = array(
		bv_section( 'hero01', array( bv_heading( 'hh0001', $hero ), array( 'id' => 'hb0001', 'type' => 'button', 'attrs' => array( 'text' => 'Start' ) ) ) ),
		bv_section( 'feat01', array( bv_heading( 'fh0001', 'Features', $features_size ? array( 'title_font_size' => $features_size ) : array() ) ) ),
	);
	if ( $with_pricing ) {
		$tree[] = bv_section( 'pric01', array( bv_heading( 'ph0001', 'Pricing' ) ) );
	}
	if ( $with_footer ) {
		$tree[] = bv_section( 'foot01', array( bv_heading( 'th0001', 'Ready to start?' ) ), array( 'admin_label' => 'Footer CTA' ) );
	}
	return $tree;
}

function bv_versions( $post_id ) {
	return array_map( 'intval', Versions::ids( $post_id ) );
}

function bv_latest( $post_id ) {
	$ids = Versions::ids( $post_id );
	return $ids ? Versions::payload( $ids[0] ) : null;
}

$t = new Brik_Versions_Test();

$admins = get_users(
	array(
		'role'   => 'administrator',
		'number' => 1,
		'fields' => 'ID',
	)
);
$admin = (int) $admins[0];
wp_set_current_user( $admin );

// Leftovers from an interrupted run.
foreach ( get_posts( array( 'post_type' => array( 'page', 'post' ), 's' => 'BV ', 'post_status' => 'any', 'posts_per_page' => -1 ) ) as $old ) {
	if ( 0 === strpos( $old->post_title, 'BV ' ) ) {
		wp_delete_post( $old->ID, true );
	}
}
$old_limit = get_option( Versions::OPTION_LIMIT, null );
delete_option( Versions::OPTION_LIMIT );

$page = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'BV Versions page',
		'post_name'   => 'bv-versions-page',
	)
);

/* -------------------------------------------------------------------------
 * Versions on save.
 * ---------------------------------------------------------------------- */

$t->section( 'Versions are recorded on save' );
$t->eq( Versions::DEFAULT_LIMIT, Versions::limit(), 'default limit is 50' );
Data::save( $page, bv_tree() );
$t->eq( 1, count( bv_versions( $page ) ), 'first save records a version' );
$v1 = bv_latest( $page );
$t->eq( 'First version', $v1['summary'], 'first summary' );
$t->eq( 'builder', $v1['source'], 'source defaults to builder' );
$t->eq( 1, $v1['number'], 'numbered from 1' );
$t->eq( $admin, $v1['author']['id'], 'author recorded' );
$t->ok( '' !== $v1['author']['initials'], 'author initials present' );
$t->eq( Data::get( $page ), Versions::tree_of( $v1['id'] ), 'version stores the saved tree' );

Data::save( $page, bv_tree() );
$t->eq( 1, count( bv_versions( $page ) ), 'identical save is skipped' );

Data::save( $page, bv_tree( 'Welcome to Brik' ) );
$v2 = bv_latest( $page );
$t->eq( 2, count( bv_versions( $page ) ), 'changed save records a version' );
$t->has( 'Changed Welcome to Brik', $v2['summary'], 'summary: content change names the section by its heading' );

Data::save( $page, bv_tree( 'Welcome to Brik', '64px' ) );
$v3 = bv_latest( $page );
$t->eq( 'Changed typography in Features', $v3['summary'], 'summary: typography change' );

Data::save( $page, bv_tree( 'Welcome to Brik', '64px', true, true ) );
$v4 = bv_latest( $page );
$t->eq( 'Added Pricing section', $v4['summary'], 'summary: added section' );

Data::save( $page, bv_tree( 'Welcome to Brik', '64px', false, true ) );
$v5 = bv_latest( $page );
$t->eq( 'Removed Footer CTA', $v5['summary'], 'summary: removed section uses admin label' );

$pg = Data::page_settings( $page );
Data::save( $page, bv_tree( 'Welcome to Brik', '64px', false, true ), array( 'dark' => true ) );
$t->has( 'Changed page settings', bv_latest( $page )['summary'], 'summary: page settings' );
Data::save( $page, bv_tree( 'Welcome to Brik', '64px', false, true ), array() );

$tree_buttons = bv_tree( 'Welcome to Brik', '64px', false, true );
$tree_buttons[0]['children'][0]['children'][0]['children'][1]['attrs']['bg'] = '#ff0000';
Data::save( $page, $tree_buttons );
$t->eq( 'Changed color in Welcome to Brik', bv_latest( $page )['summary'], 'summary: color change' );
Data::save( $page, bv_tree( 'Welcome to Brik', '64px', false, true ) );

/* -------------------------------------------------------------------------
 * Compare.
 * ---------------------------------------------------------------------- */

$t->section( 'Compare' );
$cmp  = Endpoints::compare_refs( $page, $v1['id'], 'live' );
$rows = array();
foreach ( $cmp['sections'] as $row ) {
	$rows[ $row['id'] ] = $row;
}
$t->eq( array( 'hero01', 'feat01', 'pric01', 'foot01' ), array_keys( $rows ), 'rows in order, removed section at its old place' );
$t->eq( 'changed', $rows['hero01']['status'], 'hero changed' );
$t->eq( 'changed', $rows['feat01']['status'], 'features changed' );
$t->eq( 'added', $rows['pric01']['status'], 'pricing added' );
$t->eq( 'removed', $rows['foot01']['status'], 'footer CTA removed' );
$t->ok( in_array( 'Heading typography changed', $rows['feat01']['changes'], true ), 'changed-attribute summary: Heading typography changed', wp_json_encode( $rows['feat01']['changes'] ) );
$t->ok( in_array( 'Heading content changed', $rows['hero01']['changes'], true ), 'changed-attribute summary: Heading content changed', wp_json_encode( $rows['hero01']['changes'] ) );
$t->eq( array( 'added' => 1, 'removed' => 1, 'changed' => 2, 'unchanged' => 0 ), $cmp['counts'], 'counts' );
$same = Endpoints::compare_refs( $page, 'live', 'current' );
$t->ok( $same['identical'], 'live vs current is identical' );
$t->ok( is_wp_error( Endpoints::compare_refs( $page, 999999999, 'live' ) ), 'unknown version is an error' );
$t->eq( 'Button color changed', Diff::compare( bv_tree(), array_replace( bv_tree(), array( 0 => bv_section( 'hero01', array( bv_heading( 'hh0001', 'Welcome aboard' ), array( 'id' => 'hb0001', 'type' => 'button', 'attrs' => array( 'text' => 'Start', 'bg' => '#000' ) ) ) ) ) ) )['sections'][0]['changes'][0], 'Button color changed' );

/* -------------------------------------------------------------------------
 * Restore.
 * ---------------------------------------------------------------------- */

$t->section( 'Restore' );
$before = Data::get( $page );
$res    = Versions::restore( $page, $v1['id'], array( 'hero01', 'foot01' ) );
$live   = Data::get( $page );
$ids    = wp_list_pluck( $live, 'id' );
$t->eq( array( 'hero01', 'feat01', 'foot01', 'pric01' ), $ids, 'section restore: removed section re-inserted at its old index (2), others kept' );
$t->eq( 'Welcome aboard', $live[0]['children'][0]['children'][0]['children'][0]['attrs']['text'], 'section restore: changed section replaced' );
$t->eq( '64px', $live[1]['children'][0]['children'][0]['children'][0]['attrs']['title_font_size'], 'section restore: unselected section untouched' );
$t->eq( 'restore', bv_latest( $page )['source'], 'restore recorded with source restore' );

$res = Versions::restore( $page, $v1['id'], array( 'pric01' ) );
$t->eq( array( 'hero01', 'feat01', 'foot01' ), wp_list_pluck( Data::get( $page ), 'id' ), 'restoring a section the version did not have removes it' );

Versions::restore( $page, $v1['id'] );
$t->eq( Versions::tree_of( $v1['id'] ), Data::get( $page ), 'full restore' );
$t->ok( false !== strpos( get_post_field( 'post_content', $page ), 'Welcome aboard' ), 'full restore updates the live snapshot' );

/* -------------------------------------------------------------------------
 * Names, pins and pruning.
 * ---------------------------------------------------------------------- */

$t->section( 'Naming and pruning' );
Versions::set_name( $v2['id'], 'Client approved', null );
$t->eq( 'Client approved', Versions::payload( $v2['id'] )['name'], 'version named' );
Versions::set_name( $v3['id'], '', true );
$t->ok( Versions::payload( $v3['id'] )['pinned'], 'version pinned' );
update_option( Versions::OPTION_LIMIT, 4 );
for ( $i = 0; $i < 8; $i++ ) {
	Data::save( $page, bv_tree( 'Iteration ' . $i ) );
}
$all = bv_versions( $page );
$t->eq( 6, count( $all ), 'keeps limit (4) plus the named and pinned versions' );
$t->ok( in_array( (int) $v2['id'], $all, true ) && in_array( (int) $v3['id'], $all, true ), 'named and pinned versions survive pruning' );
$t->ok( ! in_array( (int) $v1['id'], $all, true ), 'old unpinned versions are pruned' );
$t->eq( 'Changed Iteration 7', bv_latest( $page )['summary'], 'summary after pruning still diffs against previous' );
delete_option( Versions::OPTION_LIMIT );
$grouped = Versions::grouped( $page );
$t->eq( 'Today', $grouped[0]['label'], 'grouped by day with Today label' );

/* -------------------------------------------------------------------------
 * Staging isolation and preview token.
 * ---------------------------------------------------------------------- */

$t->section( 'Staging' );
Data::save( $page, bv_tree( 'Live headline' ) );
$live_tree    = Data::get( $page );
$live_content = get_post_field( 'post_content', $page );
$live_count   = count( bv_versions( $page ) );
Staging::save( $page, bv_tree( 'Staged headline', '', true, true ) );
$t->ok( Staging::exists( $page ), 'staging stored' );
$t->eq( $live_tree, Data::get( $page ), 'live tree untouched by staging save' );
$t->eq( $live_content, get_post_field( 'post_content', $page ), 'post_content snapshot untouched by staging save' );
$t->eq( 'staging', bv_latest( $page )['source'], 'staging save recorded as a staging version' );
$t->eq( 'staging', bv_latest( $page )['target'], 'staging version targets staging' );
$t->eq( 'staging', Staging::stage( $page ), 'published page with staging is in stage "staging"' );
$state = Staging::state( $page );
$t->eq( 1, $state['counts']['added'], 'state counts staged additions' );
$t->ok( $state['pending'], 'state reports pending changes' );

$url = get_permalink( $page );
list( $code, $html ) = bv_fetch( $url );
$t->eq( 200, $code, 'live page loads' );
$t->has( 'Live headline', $html, 'logged-out visitor sees live' );
$t->ok( false === strpos( $html, 'Staged headline' ) && false === strpos( $html, 'brik-stage-badge' ), 'live page shows no staging content' );

$preview = Staging::preview_url( $page );
list( $code, $html ) = bv_fetch( $preview );
$t->eq( 200, $code, 'staging preview loads logged out' );
$t->has( 'Staged headline', $html, 'token shows staging tree' );
$t->has( 'Pricing', $html, 'token shows staged section' );
$t->has( 'brik-stage-badge', $html, 'staging badge bar printed' );
$t->has( 'noindex', $html, 'preview is noindex' );

list( $code, $html ) = bv_fetch( add_query_arg( 'brik_stage', 'wrong-token-wrong-token-xx', $url ) );
$t->ok( false === strpos( $html, 'Staged headline' ) && false !== strpos( $html, 'Live headline' ), 'wrong token denied (live shown)' );

$old_token = Staging::token( $page );
Staging::token( $page, true );
list( $code, $html ) = bv_fetch( add_query_arg( 'brik_stage', $old_token, $url ) );
$t->ok( false === strpos( $html, 'Staged headline' ), 'regenerated token invalidates the old link' );

// Draft page: only reachable with the token.
$draft = wp_insert_post(
	array(
		'post_type'   => 'page',
		'post_status' => 'draft',
		'post_title'  => 'BV Draft page',
	)
);
Staging::save( $draft, bv_tree( 'Draft staged' ) );
$t->eq( 'preview', Staging::stage( $draft ), 'unpublished page with staging is in stage "preview"' );
list( $code, $html ) = bv_fetch( Staging::preview_url( $draft ) );
$t->eq( 200, $code, 'draft staging preview loads logged out' );
$t->has( 'Draft staged', $html, 'draft preview shows staging' );
list( $code, $html ) = bv_fetch( add_query_arg( 'brik_stage', 'nope-nope-nope-nope-nope', home_url( '/?page_id=' . $draft ) ) );
$t->ok( false === strpos( $html, 'Draft staged' ), 'draft with a wrong token stays hidden', 'code ' . $code );
$t->eq( 'draft', get_post_status( $draft ), 'draft status unchanged after preview' );

/* -------------------------------------------------------------------------
 * Deploy.
 * ---------------------------------------------------------------------- */

$t->section( 'Deploy' );
$staged = Staging::tree( $page );
$r      = Staging::deploy( $page );
$t->ok( ! is_wp_error( $r ), 'deploy succeeds' );
$t->eq( $staged, Data::get( $page ), 'live tree equals staging after deploy' );
$t->ok( ! Staging::exists( $page ), 'staging cleared after deploy' );
$t->eq( 'deploy', bv_latest( $page )['source'], 'deploy version recorded' );
$t->has( 'Staged headline', get_post_field( 'post_content', $page ), 'snapshot updated on deploy' );
list( $code, $html ) = bv_fetch( $url );
$t->has( 'Staged headline', $html, 'front end shows deployed content' );
$t->ok( is_wp_error( Staging::deploy( $page ) ), 'deploy without staging is an error' );

Staging::deploy( $draft );
$t->eq( 'publish', get_post_status( $draft ), 'deploying a draft publishes it' );

// Publishing from the builder while staging is loaded clears staging.
Staging::save( $page, bv_tree( 'Builder staged' ) );
$req = new WP_REST_Request( 'POST', '/brik/v1/posts/' . $page );
$req->set_header( 'Content-Type', 'application/json' );
$req->set_body( wp_json_encode( array( 'tree' => bv_tree( 'Builder staged' ), 'brik_clear_staging' => true ) ) );
$res = rest_do_request( $req );
$t->eq( 200, $res->get_status(), 'builder save over REST' );
$t->ok( ! Staging::exists( $page ), 'builder publish with flag clears staging' );
$t->eq( 'builder', bv_latest( $page )['source'], 'builder REST save has source builder' );

/* -------------------------------------------------------------------------
 * REST endpoints.
 * ---------------------------------------------------------------------- */

$t->section( 'REST' );
$rest = static function ( $method, $route, $body = null, $query = array() ) {
	$req = new WP_REST_Request( $method, $route );
	if ( $query ) {
		$req->set_query_params( $query );
	}
	if ( null !== $body ) {
		$req->set_header( 'Content-Type', 'application/json' );
		$req->set_body( wp_json_encode( $body ) );
	}
	$res = rest_do_request( $req );
	return array( $res->get_status(), $res->get_data() );
};
list( $code, $data ) = $rest( 'GET', '/brik/v1/versions/' . $page );
$t->eq( 200, $code, 'GET versions' );
$t->ok( isset( $data['groups'][0]['items'][0]['summary'], $data['state']['stage'] ), 'versions payload has groups and state' );
$vid = $data['groups'][0]['items'][0]['id'];
list( $code, $data ) = $rest( 'GET', '/brik/v1/versions/' . $page . '/' . $vid );
$t->ok( 200 === $code && is_array( $data['tree'] ), 'GET version with tree' );
list( $code, $data ) = $rest( 'POST', '/brik/v1/versions/' . $page . '/name', array( 'version_id' => $vid, 'name' => 'Launch', 'pinned' => true ) );
$t->ok( 200 === $code && 'Launch' === $data['name'] && $data['pinned'], 'POST name/pin' );
list( $code, $data ) = $rest( 'POST', '/brik/v1/staging/' . $page, array( 'tree' => bv_tree( 'REST staged' ) ) );
$t->ok( 200 === $code && $data['staging'] && is_array( $data['tree'] ), 'POST staging' );
list( $code, $data ) = $rest( 'GET', '/brik/v1/versions/' . $page . '/compare', null, array( 'a' => 'live', 'b' => 'staging' ) );
$t->ok( 200 === $code && 'changed' === $data['sections'][0]['status'], 'GET compare live vs staging' );
list( $code, $data ) = $rest( 'POST', '/brik/v1/versions/' . $page . '/compare', array( 'a' => $vid, 'b' => 'current', 'tree' => bv_tree( 'Unsaved edit' ) ) );
$t->ok( 200 === $code && 'changed' === $data['sections'][0]['status'], 'POST compare against the unsaved editor tree' );
list( $code, $data ) = $rest( 'POST', '/brik/v1/versions/' . $page . '/restore', array( 'version_id' => $vid, 'target' => 'staging' ) );
$t->ok( 200 === $code && 'staging' === $data['target'], 'POST restore to staging' );
$t->eq( Versions::tree_of( $vid ), Staging::tree( $page ), 'restore to staging writes staging only' );
list( $code, $data ) = $rest( 'POST', '/brik/v1/staging/' . $page . '/deploy', array() );
$t->ok( 200 === $code && ! $data['state']['staging'], 'POST deploy' );

// Permissions.
$author = wp_insert_user(
	array(
		'user_login' => 'bv_contrib_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'contributor',
	)
);
wp_set_current_user( $author );
list( $code ) = $rest( 'GET', '/brik/v1/versions/' . $page );
$t->ok( in_array( $code, array( 401, 403 ), true ), 'contributor cannot read versions of another user\'s page' );
list( $code ) = $rest( 'POST', '/brik/v1/staging/' . $page . '/deploy', array() );
$t->ok( in_array( $code, array( 401, 403 ), true ), 'contributor cannot deploy' );
wp_set_current_user( 0 );
list( $code ) = $rest( 'GET', '/brik/v1/staging/' . $page );
$t->ok( in_array( $code, array( 401, 403 ), true ), 'logged-out REST denied' );
wp_set_current_user( $admin );

/* -------------------------------------------------------------------------
 * Scheduled deploy and rollback.
 * ---------------------------------------------------------------------- */

$t->section( 'Schedules' );
Staging::save( $page, bv_tree( 'Scheduled headline' ) );
$entry = Schedule::add( $page, 'deploy', wp_date( 'Y-m-d H:i', time() + 3600 ) );
$t->ok( ! is_wp_error( $entry ), 'schedule deploy (site timezone string)' );
$next = wp_next_scheduled( Schedule::HOOK, array( $page, $entry['id'] ) );
$t->ok( $next && abs( $next - ( time() + 3600 ) ) < 120, 'cron event scheduled at the right time' );
$t->eq( 1, count( Schedule::all( $page ) ), 'schedule listed' );
$t->ok( is_wp_error( Schedule::add( $page, 'deploy', '2001-01-01 10:00' ) ), 'past time rejected' );
$t->ok( is_wp_error( Schedule::add( $page, 'rollback', '+1 day', 999999999 ) ), 'rollback to unknown version rejected' );

wp_set_current_user( 0 ); // Cron runs without a user.
do_action( Schedule::HOOK, $page, $entry['id'] );
wp_set_current_user( $admin );
$t->has( 'Scheduled headline', wp_json_encode( Data::get( $page ) ), 'cron deploy published staging' );
$t->ok( ! Staging::exists( $page ), 'cron deploy cleared staging' );
$t->eq( 'schedule', bv_latest( $page )['source'], 'cron deploy recorded with source schedule' );
$t->eq( 0, count( Schedule::all( $page ) ), 'ran schedule removed' );
$t->ok( ! wp_next_scheduled( Schedule::HOOK, array( $page, $entry['id'] ) ), 'ran schedule leaves no cron event behind' );

$target_version = (int) $vid;
$entry          = Schedule::add( $page, 'rollback', gmdate( 'c', time() + 7200 ), $target_version );
$t->ok( ! is_wp_error( $entry ) && $entry['version'] === $target_version, 'schedule rollback' );
$entry2 = Schedule::add( $page, 'rollback', gmdate( 'c', time() + 9000 ), $target_version );
$t->ok( Schedule::cancel( $page, $entry2['id'] ), 'cancel schedule' );
$t->ok( ! wp_next_scheduled( Schedule::HOOK, array( $page, $entry2['id'] ) ), 'cancelled cron event removed' );
wp_set_current_user( 0 );
do_action( Schedule::HOOK, $page, $entry['id'] );
wp_set_current_user( $admin );
$t->eq( Versions::tree_of( $target_version ), Data::get( $page ), 'cron rollback restored the version' );
$t->eq( 'schedule', bv_latest( $page )['source'], 'rollback recorded with source schedule' );

/* -------------------------------------------------------------------------
 * MCP tools.
 * ---------------------------------------------------------------------- */

$t->section( 'MCP tools' );
foreach ( array( 'list_versions', 'get_version', 'restore_version', 'compare_versions', 'save_staging', 'deploy_staging', 'schedule_deploy', 'schedule_rollback', 'cancel_schedule', 'staging_preview_link' ) as $tool ) {
	$t->ok( McpTools::exists( $tool ), "tool $tool registered" );
}
$t->has( 'Versions, staging and publishing', McpTools::guide(), 'guide mentions staging workflow' );

list( $r ) = McpTools::call( 'save_staging', array( 'post_id' => $page, 'tree' => array( array( 'type' => 'section', 'children' => array( array( 'type' => 'heading', 'attrs' => array( 'text' => 'MCP staged' ) ) ) ) ) ) );
$t->ok( is_array( $r ) && 'staging' === $r['saved'] && ! empty( $r['preview_url'] ), 'save_staging' );
$t->ok( false === strpos( wp_json_encode( Data::get( $page ) ), 'MCP staged' ), 'save_staging leaves live alone' );
list( $r ) = McpTools::call( 'compare_versions', array( 'post_id' => $page ) );
$t->ok( is_array( $r ) && $r['counts']['added'] >= 1, 'compare_versions live vs staging' );
list( $r ) = McpTools::call( 'staging_preview_link', array( 'post_id' => $page ) );
$t->ok( is_array( $r ) && false !== strpos( $r['preview_url'], 'brik_stage=' ), 'staging_preview_link' );
list( $r ) = McpTools::call( 'list_versions', array( 'post_id' => $page, 'limit' => 3 ) );
$count = 0;
foreach ( $r['groups'] as $g ) {
	$count += count( $g['items'] );
}
$t->eq( 3, $count, 'list_versions honours limit' );
$first_id = $r['groups'][0]['items'][0]['id'];
list( $r ) = McpTools::call( 'get_version', array( 'post_id' => $page, 'version_id' => $first_id ) );
$t->ok( is_array( $r ) && isset( $r['tree'] ), 'get_version' );
list( $r ) = McpTools::call( 'schedule_deploy', array( 'post_id' => $page, 'at' => wp_date( 'Y-m-d H:i', time() + 600 ) ) );
$t->ok( is_array( $r ) && 'deploy' === $r['scheduled']['action'], 'schedule_deploy' );
list( $r2 ) = McpTools::call( 'cancel_schedule', array( 'post_id' => $page, 'schedule_id' => $r['scheduled']['id'] ) );
$t->ok( is_array( $r2 ) && $r2['cancelled'], 'cancel_schedule' );
list( $r ) = McpTools::call( 'schedule_rollback', array( 'post_id' => $page, 'version_id' => $target_version, 'at' => wp_date( 'Y-m-d H:i', time() + 600 ) ) );
$t->ok( is_array( $r ) && 'rollback' === $r['scheduled']['action'], 'schedule_rollback' );
McpTools::call( 'cancel_schedule', array( 'post_id' => $page, 'schedule_id' => $r['scheduled']['id'] ) );
list( $r ) = McpTools::call( 'deploy_staging', array( 'post_id' => $page ) );
$t->ok( is_array( $r ) && $r['deployed'], 'deploy_staging' );
$t->has( 'MCP staged', wp_json_encode( Data::get( $page ) ), 'deploy_staging made it live' );
list( $r ) = McpTools::call( 'restore_version', array( 'post_id' => $page, 'version_id' => $target_version, 'sections' => array( 'hero01' ) ) );
$t->ok( is_array( $r ) && $r['restored'], 'restore_version with sections' );
$t->ok( in_array( 'hero01', wp_list_pluck( Data::get( $page ), 'id' ), true ), 'restored section present' );

// A save through the MCP endpoint is recorded with source "mcp".
$mcp_enabled = \Brik\Settings::get( 'mcp_enabled' );
if ( $mcp_enabled ) {
	$req = new WP_REST_Request( 'POST', '/brik/v1/mcp' );
	$req->set_header( 'Content-Type', 'application/json' );
	$req->set_body(
		wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'id'      => 1,
				'method'  => 'tools/call',
				'params'  => array(
					'name'      => 'update_page',
					'arguments' => array(
						'id'   => $page,
						'tree' => bv_tree( 'Through MCP' ),
					),
				),
			)
		)
	);
	rest_do_request( $req );
	$t->eq( 'mcp', bv_latest( $page )['source'], 'MCP save recorded with source mcp' );
} else {
	$t->ok( true, 'MCP endpoint disabled; source check skipped' );
}

list( $r ) = McpTools::call( 'deploy_staging', array( 'post_id' => $page ) );
$t->ok( is_wp_error( $r ), 'deploy_staging without staging is an error' );

/* -------------------------------------------------------------------------
 * Cleanup.
 * ---------------------------------------------------------------------- */

$t->section( 'Cleanup' );
$pending     = Schedule::add( $page, 'rollback', '+1 day', $target_version );
$version_ids = array_merge( bv_versions( $page ), bv_versions( $draft ) );
wp_delete_post( $page, true );
wp_delete_post( $draft, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $author );
$left = array_filter( array_map( 'get_post', $version_ids ) );
$t->eq( 0, count( $left ), 'deleting a document deletes its versions' );
$t->ok( ! wp_next_scheduled( Schedule::HOOK, array( $page, $pending['id'] ) ), 'deleting a document clears its scheduled actions' );
if ( null !== $old_limit ) {
	update_option( Versions::OPTION_LIMIT, $old_limit );
}

echo "\n" . str_repeat( '-', 60 ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
printf( "PASS %d  FAIL %d\n", (int) $t->pass, (int) $t->fail );
foreach ( $t->failures as $f ) {
	echo '  - ' . $f . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
exit( $t->fail ? 1 : 0 );
