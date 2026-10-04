<?php
/**
 * Staging trees and the shareable staging preview.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

use Brik\Data;
use Brik\Library;
use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * A document can carry a staging tree next to its live tree. Staging lives in its own meta
 * and never touches _brik_data or post_content, so visitors keep seeing the live page until
 * the staging copy is deployed. A secret token lets anyone with the link see the staging copy.
 */
final class Staging {

	const META       = '_brik_staging';
	const META_PAGE  = '_brik_staging_page';
	const META_TOKEN = '_brik_stage_token';
	const META_TIME  = '_brik_staging_time';
	const META_USER  = '_brik_staging_user';

	const QUERY_VAR = 'brik_stage';

	/** Document shown in staging mode for this request (0 = none). */
	private static $previewing = 0;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_preview_request' ), 1 );
		add_action( 'wp', array( __CLASS__, 'preview_setup' ) );
	}

	/* ---------------------------------------------------------------------
	 * Storage.
	 * ------------------------------------------------------------------- */

	public static function exists( $post_id ) {
		return '' !== (string) get_post_meta( $post_id, self::META, true );
	}

	public static function tree( $post_id ) {
		$raw  = get_post_meta( $post_id, self::META, true );
		$tree = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return is_array( $tree ) ? $tree : array();
	}

	public static function page( $post_id ) {
		$page = get_post_meta( $post_id, self::META_PAGE, true );
		return is_array( $page ) ? $page : Data::page_settings( $post_id );
	}

	/**
	 * Save a staging tree. Live data (_brik_data, _brik_page, post_content) is untouched.
	 */
	public static function save( $post_id, array $tree, $page = null, $source = 'staging' ) {
		$tree = Data::sanitize( Data::normalize( $tree ) );
		update_post_meta( $post_id, self::META, wp_slash( wp_json_encode( $tree ) ) );

		$clean = is_array( $page ) ? self::clean_page( $page ) : self::page( $post_id );
		update_post_meta( $post_id, self::META_PAGE, $clean );
		update_post_meta( $post_id, self::META_TIME, time() );
		update_post_meta( $post_id, self::META_USER, get_current_user_id() );
		self::token( $post_id );

		Versions::record( $post_id, $tree, $clean, $source, 'staging' );
		do_action( 'brik/staging_saved', $post_id, $tree );
		return $tree;
	}

	/** Same rules Data::save applies to page settings. */
	private static function clean_page( array $page ) {
		return array(
			'custom_css' => isset( $page['custom_css'] ) ? str_ireplace( '</style', '', (string) $page['custom_css'] ) : '',
			'body_class' => isset( $page['body_class'] ) ? sanitize_text_field( $page['body_class'] ) : '',
			'dark'       => ! empty( $page['dark'] ),
			'hide_title' => ! empty( $page['hide_title'] ),
		);
	}

	public static function discard( $post_id ) {
		delete_post_meta( $post_id, self::META );
		delete_post_meta( $post_id, self::META_PAGE );
		delete_post_meta( $post_id, self::META_TIME );
		delete_post_meta( $post_id, self::META_USER );
	}

	/**
	 * Copy staging to live, record a "deploy" version and clear staging.
	 *
	 * @return array|\WP_Error The live tree.
	 */
	public static function deploy( $post_id, $source = 'deploy' ) {
		if ( ! self::exists( $post_id ) ) {
			return new \WP_Error( 'brik_no_staging', __( 'There are no staged changes to deploy.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		$tree = self::tree( $post_id );
		$page = self::page( $post_id );
		$live = Versions::with_source(
			$source,
			static function () use ( $post_id, $tree, $page ) {
				return Data::save( $post_id, $tree, $page );
			}
		);

		// A page that has never been published goes live with its first deploy.
		$post = get_post( $post_id );
		if ( $post && ! in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) && 'publish' !== $post->post_status && 'private' !== $post->post_status ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'publish',
				)
			);
		}

		self::discard( $post_id );
		do_action( 'brik/staging_deployed', $post_id, $live );
		return $live;
	}

	/* ---------------------------------------------------------------------
	 * Token and preview link.
	 * ------------------------------------------------------------------- */

	public static function token( $post_id, $regenerate = false ) {
		$token = (string) get_post_meta( $post_id, self::META_TOKEN, true );
		if ( $regenerate || strlen( $token ) < 20 ) {
			$token = wp_generate_password( 32, false, false );
			update_post_meta( $post_id, self::META_TOKEN, $token );
		}
		return $token;
	}

	public static function check_token( $post_id, $token ) {
		$stored = (string) get_post_meta( (int) $post_id, self::META_TOKEN, true );
		return $post_id && strlen( $stored ) >= 20 && is_string( $token ) && hash_equals( $stored, $token );
	}

	public static function preview_url( $post_id ) {
		$post  = get_post( $post_id );
		$token = self::token( $post_id );
		if ( in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			// Templates show up across the site, so preview them on the home page.
			return add_query_arg(
				array(
					self::QUERY_VAR => $token,
					'brik_post'     => $post->ID,
				),
				home_url( '/' )
			);
		}
		$url = 'publish' === $post->post_status ? get_permalink( $post ) : add_query_arg( 'p', $post->ID, home_url( '/' ) );
		if ( 'page' === $post->post_type && 'publish' !== $post->post_status ) {
			$url = add_query_arg( 'page_id', $post->ID, home_url( '/' ) );
		}
		return add_query_arg( self::QUERY_VAR, $token, $url );
	}

	/* ---------------------------------------------------------------------
	 * Front-end preview.
	 * ------------------------------------------------------------------- */

	private static function requested_token() {
		return isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Unpublished documents are not queryable for visitors; let a valid token through
	 * (the post is only treated as published in memory, for this request).
	 */
	public static function maybe_preview_request() {
		if ( '' === self::requested_token() || is_admin() ) {
			return;
		}
		add_filter( 'posts_results', array( __CLASS__, 'open_draft' ), 10, 2 );
		// Canonical redirects would drop the token or send drafts elsewhere.
		add_filter( 'redirect_canonical', '__return_false' );
	}

	public static function open_draft( $posts, $query ) {
		if ( ! $query->is_main_query() || 1 !== count( (array) $posts ) ) {
			return $posts;
		}
		$post = $posts[0];
		if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) && self::check_token( $post->ID, self::requested_token() ) ) {
			$post->post_status = 'publish';
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		}
		remove_filter( 'posts_results', array( __CLASS__, 'open_draft' ), 10 );
		return $posts;
	}

	public static function preview_setup() {
		$token = self::requested_token();
		if ( '' === $token || is_admin() ) {
			return;
		}
		$id = isset( $_GET['brik_post'] ) ? absint( $_GET['brik_post'] ) : get_queried_object_id(); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $id || ! self::check_token( $id, $token ) || ! self::exists( $id ) ) {
			return;
		}
		self::$previewing = $id;

		// Serve the staging tree wherever this document renders during the request.
		add_filter( 'get_post_metadata', array( __CLASS__, 'swap_meta' ), 10, 4 );
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		}
		header( 'X-Robots-Tag: noindex, nofollow' );
		add_filter( 'wp_robots', 'wp_robots_no_robots' );
		add_action( 'wp_footer', array( __CLASS__, 'badge' ), 100 );
	}

	public static function previewing() {
		return self::$previewing;
	}

	public static function swap_meta( $value, $object_id, $key, $single ) {
		if ( (int) $object_id !== self::$previewing ) {
			return $value;
		}
		if ( Data::META === $key ) {
			remove_filter( 'get_post_metadata', array( __CLASS__, 'swap_meta' ), 10 );
			$raw = get_post_meta( $object_id, self::META, true );
			add_filter( 'get_post_metadata', array( __CLASS__, 'swap_meta' ), 10, 4 );
			return $single ? array( $raw ) : array( $raw );
		}
		if ( Data::META_PAGE === $key ) {
			remove_filter( 'get_post_metadata', array( __CLASS__, 'swap_meta' ), 10 );
			$page = self::page( $object_id );
			add_filter( 'get_post_metadata', array( __CLASS__, 'swap_meta' ), 10, 4 );
			return array( $page );
		}
		if ( Data::META_ENABLED === $key ) {
			return array( 1 );
		}
		return $value;
	}

	public static function badge() {
		$post = get_post( self::$previewing );
		$time = (int) get_post_meta( self::$previewing, self::META_TIME, true );
		?>
		<div id="brik-stage-badge" role="status" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147483000;display:flex;align-items:center;gap:10px;padding:8px 14px 8px 10px;border-radius:999px;background:#18181b;color:#fafafa;font:500 13px/1.2 ui-sans-serif,system-ui,-apple-system,'Segoe UI',sans-serif;box-shadow:0 10px 30px -10px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.08)">
			<span style="display:inline-flex;align-items:center;gap:6px;padding:3px 8px;border-radius:999px;background:#f59e0b;color:#18181b;font-weight:600;font-size:11px;letter-spacing:.02em;text-transform:uppercase"><span style="width:6px;height:6px;border-radius:50%;background:#18181b"></span><?php esc_html_e( 'Staging preview', 'brik-builder' ); ?></span>
			<span><?php echo esc_html( $post ? $post->post_title : '' ); ?></span>
			<?php if ( $time ) : ?>
				<span style="color:#a1a1aa">
				<?php
				/* translators: %s: human time difference */
				echo esc_html( sprintf( __( 'updated %s ago', 'brik-builder' ), human_time_diff( $time ) ) );
				?>
				</span>
			<?php endif; ?>
			<span style="color:#a1a1aa">· <?php esc_html_e( 'Not live yet', 'brik-builder' ); ?></span>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Workflow state.
	 * ------------------------------------------------------------------- */

	/**
	 * Where a document is in the pipeline: draft → preview → staging → published.
	 * Unpublished work saved to staging is "preview" (only reachable through the preview
	 * link); a published page with staged edits is "staging".
	 */
	public static function stage( $post_id ) {
		$post      = get_post( $post_id );
		$published = $post && ( 'publish' === $post->post_status || 'private' === $post->post_status );
		$staging   = self::exists( $post_id );
		if ( $staging ) {
			return $published ? 'staging' : 'preview';
		}
		return $published ? 'published' : 'draft';
	}

	public static function state( $post_id, $with_tree = false ) {
		$staging = self::exists( $post_id );
		$time    = (int) get_post_meta( $post_id, self::META_TIME, true );
		$user    = get_userdata( (int) get_post_meta( $post_id, self::META_USER, true ) );
		$state   = array(
			'stage'       => self::stage( $post_id ),
			'staging'     => $staging,
			'staged_at'   => $staging && $time ? gmdate( 'c', $time ) : null,
			'staged_ago'  => $staging && $time ? human_time_diff( $time ) : null,
			'staged_by'   => $staging && $user ? $user->display_name : null,
			'preview_url' => self::preview_url( $post_id ),
			'schedules'   => Schedule::all( $post_id ),
			'now_local'   => wp_date( 'Y-m-d\TH:i' ),
			'timezone'    => wp_timezone_string(),
			'can_deploy'  => Endpoints::can_publish( $post_id ),
			'limit'       => Versions::limit(),
		);
		if ( $staging ) {
			$cmp              = Diff::compare( Data::get( $post_id ), self::tree( $post_id ), Data::page_settings( $post_id ), self::page( $post_id ) );
			$state['counts']  = $cmp['counts'];
			$state['pending'] = ! $cmp['identical'];
		}
		if ( $with_tree && $staging ) {
			$state['tree'] = self::tree( $post_id );
			$state['page'] = (object) self::page( $post_id );
		}
		return $state;
	}
}
