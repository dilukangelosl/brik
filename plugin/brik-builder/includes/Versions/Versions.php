<?php
/**
 * Version history for Brik documents.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

use Brik\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Every save of a Brik tree (pages, posts, templates, library items) is recorded as a
 * `brik_version` post whose parent is the document and whose content is the JSON tree.
 */
final class Versions {

	const POST_TYPE = 'brik_version';

	const OPTION_LIMIT = 'brik_versions_limit';

	const DEFAULT_LIMIT = 50;

	/* Version meta. */
	const M_HASH    = '_brik_v_hash';
	const M_PAGE    = '_brik_v_page';
	const M_SOURCE  = '_brik_v_source';
	const M_SUMMARY = '_brik_v_summary';
	const M_NAME    = '_brik_v_name';
	const M_PINNED  = '_brik_v_pinned';
	const M_NUMBER  = '_brik_v_number';
	const M_TARGET  = '_brik_v_target';

	/* Document meta: running version number. */
	const M_SEQ = '_brik_v_seq';

	const SOURCES = array( 'builder', 'mcp', 'staging', 'deploy', 'restore', 'schedule' );

	/** Source forced by the code that is saving (deploy, restore, …). */
	private static $source = null;

	/** REST route of the request being served, used to tell builder saves from MCP saves. */
	private static $route = '';

	/** Parameters of the REST request being served. */
	private static $params = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'brik/saved', array( __CLASS__, 'on_saved' ), 10, 2 );
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'capture_request' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'release_request' ), 10, 1 );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_for' ) );

		Staging::init();
		Schedule::init();
		Endpoints::init();
		Mcp::init();
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Versions', 'brik-builder' ),
					'singular_name' => __( 'Version', 'brik-builder' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'query_var'           => false,
				'rewrite'             => false,
				'can_export'          => true,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'supports'            => array( 'author' ),
			)
		);
	}

	public static function capture_request( $response, $handler, $request ) {
		self::$route  = (string) $request->get_route();
		self::$params = (array) $request->get_json_params();
		return $response;
	}

	public static function release_request( $response ) {
		self::$route  = '';
		self::$params = array();
		return $response;
	}

	/** True when the current REST request carries the given body flag. */
	public static function request_flag( $key ) {
		return ! empty( self::$params[ $key ] );
	}

	public static function limit() {
		$limit = (int) get_option( self::OPTION_LIMIT, self::DEFAULT_LIMIT );
		$limit = $limit > 0 ? $limit : self::DEFAULT_LIMIT;
		return (int) apply_filters( 'brik/versions_limit', min( 500, max( 1, $limit ) ) );
	}

	/**
	 * Run a callback with a forced version source, e.g. Versions::as( 'deploy', fn ).
	 */
	public static function with_source( $source, callable $fn ) {
		$previous     = self::$source;
		self::$source = $source;
		try {
			return $fn();
		} finally {
			self::$source = $previous;
		}
	}

	private static function current_source() {
		if ( self::$source ) {
			return self::$source;
		}
		if ( false !== strpos( self::$route, '/mcp' ) ) {
			return 'mcp';
		}
		return 'builder';
	}

	public static function on_saved( $post_id, $nodes ) {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE === $post->post_type || 'revision' === $post->post_type ) {
			return;
		}
		self::record( $post_id, (array) $nodes, Data::page_settings( $post_id ), self::current_source(), 'live' );
	}

	public static function hash( array $tree, $page ) {
		return md5( wp_json_encode( $tree ) . '|' . wp_json_encode( (array) $page ) );
	}

	/**
	 * Store a version. Identical consecutive saves are skipped. Returns the version id or 0.
	 */
	public static function record( $post_id, array $tree, $page, $source, $target = 'live', $label = '' ) {
		$hash   = self::hash( $tree, $page );
		$latest = self::latest( $post_id, $target );
		if ( $latest && get_post_meta( $latest->ID, self::M_HASH, true ) === $hash ) {
			return 0;
		}

		// A first staging save is described relative to what is live.
		$base     = $latest ? $latest : ( 'staging' === $target ? self::latest( $post_id, 'live' ) : null );
		$previous = $base ? self::tree_of( $base ) : null;
		$prev_pg  = $base ? self::page_of( $base ) : null;
		$summary  = Diff::summary( $previous, $tree, $prev_pg, $page );
		if ( 'staging' === $target ) {
			$summary = $summary ? $summary : __( 'Saved to staging', 'brik-builder' );
		}

		$number = (int) get_post_meta( $post_id, self::M_SEQ, true ) + 1;
		update_post_meta( $post_id, self::M_SEQ, $number );

		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => (int) $post_id,
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf( 'v%d', $number ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}

		// Written directly: content filters (kses) would corrupt the JSON for some roles.
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_content' => wp_json_encode( $tree ) ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );

		update_post_meta( $id, self::M_HASH, $hash );
		update_post_meta( $id, self::M_PAGE, (array) $page );
		update_post_meta( $id, self::M_SOURCE, in_array( $source, self::SOURCES, true ) ? $source : 'builder' );
		update_post_meta( $id, self::M_SUMMARY, $summary );
		update_post_meta( $id, self::M_NUMBER, $number );
		update_post_meta( $id, self::M_TARGET, 'staging' === $target ? 'staging' : 'live' );
		if ( '' !== $label ) {
			update_post_meta( $id, self::M_NAME, sanitize_text_field( $label ) );
		}

		self::prune( $post_id );
		do_action( 'brik/version_recorded', $id, $post_id, $source );
		return $id;
	}

	/**
	 * Keep the newest N versions plus every named or pinned one.
	 */
	public static function prune( $post_id ) {
		$ids   = self::ids( $post_id );
		$keep  = self::limit();
		$n     = 0;
		$saved = Schedule::referenced_versions( $post_id );
		foreach ( $ids as $id ) {
			if ( self::is_pinned( $id ) || in_array( (int) $id, $saved, true ) ) {
				continue;
			}
			++$n;
			if ( $n > $keep ) {
				wp_delete_post( $id, true );
			}
		}
	}

	public static function is_pinned( $id ) {
		return (bool) get_post_meta( $id, self::M_PINNED, true ) || '' !== (string) get_post_meta( $id, self::M_NAME, true );
	}

	/** Version ids of a document, newest first. */
	public static function ids( $post_id ) {
		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_parent'      => (int) $post_id,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'orderby'          => array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				),
				'fields'           => 'ids',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			)
		);
	}

	public static function latest( $post_id, $target = null ) {
		$args = array(
			'post_type'        => self::POST_TYPE,
			'post_parent'      => (int) $post_id,
			'post_status'      => 'any',
			'posts_per_page'   => 1,
			'orderby'          => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'suppress_filters' => true,
			'no_found_rows'    => true,
		);
		if ( $target ) {
			$args['meta_key']   = self::M_TARGET; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $target; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$posts = get_posts( $args );
		return $posts ? $posts[0] : null;
	}

	/**
	 * A version of a document, or null when it doesn't belong to it.
	 */
	public static function get( $version_id, $post_id = 0 ) {
		$v = get_post( (int) $version_id );
		if ( ! $v || self::POST_TYPE !== $v->post_type || ( $post_id && (int) $v->post_parent !== (int) $post_id ) ) {
			return null;
		}
		return $v;
	}

	public static function tree_of( $version ) {
		$version = get_post( $version );
		$tree    = $version ? json_decode( $version->post_content, true ) : null;
		return is_array( $tree ) ? $tree : array();
	}

	public static function page_of( $version ) {
		$page = get_post_meta( is_object( $version ) ? $version->ID : (int) $version, self::M_PAGE, true );
		return is_array( $page ) ? $page : array();
	}

	public static function delete_for( $post_id ) {
		if ( self::POST_TYPE === get_post_type( $post_id ) ) {
			return;
		}
		foreach ( self::ids( $post_id ) as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * Public shape of a version for REST and MCP.
	 */
	public static function payload( $v, $with_tree = false ) {
		$v      = get_post( $v );
		$user   = get_userdata( (int) $v->post_author );
		$name   = $user ? $user->display_name : __( 'System', 'brik-builder' );
		$time   = get_post_time( 'U', true, $v );
		$words  = preg_split( '/\s+/', trim( $name ) );
		$out    = array(
			'id'       => $v->ID,
			'number'   => (int) get_post_meta( $v->ID, self::M_NUMBER, true ),
			'date'     => gmdate( 'c', $time ),
			'time'     => wp_date( get_option( 'time_format' ), $time ),
			'day'      => wp_date( 'Y-m-d', $time ),
			'when'     => wp_date( get_option( 'date_format' ), $time ),
			'name'     => (string) get_post_meta( $v->ID, self::M_NAME, true ),
			'pinned'   => (bool) get_post_meta( $v->ID, self::M_PINNED, true ),
			'summary'  => (string) get_post_meta( $v->ID, self::M_SUMMARY, true ),
			'source'   => (string) get_post_meta( $v->ID, self::M_SOURCE, true ),
			'target'   => get_post_meta( $v->ID, self::M_TARGET, true ) ? (string) get_post_meta( $v->ID, self::M_TARGET, true ) : 'live',
			'author'   => array(
				'id'       => $user ? $user->ID : 0,
				'name'     => $name,
				'initials' => strtoupper( mb_substr( $words[0], 0, 1 ) . ( count( $words ) > 1 ? mb_substr( end( $words ), 0, 1 ) : '' ) ),
				// A transparent default lets the initials show through when there is no avatar.
				'avatar'   => $user ? get_avatar_url( $user->ID, array( 'size' => 48, 'default' => 'blank' ) ) : '',
			),
		);
		if ( $with_tree ) {
			$out['tree'] = self::tree_of( $v );
			$out['page'] = (object) self::page_of( $v );
		}
		return $out;
	}

	/**
	 * Versions of a document grouped by day (site timezone), newest first.
	 */
	public static function grouped( $post_id ) {
		$today     = wp_date( 'Y-m-d' );
		$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
		$groups    = array();
		foreach ( self::ids( $post_id ) as $id ) {
			$item = self::payload( $id );
			$day  = $item['day'];
			if ( ! isset( $groups[ $day ] ) ) {
				if ( $day === $today ) {
					$label = __( 'Today', 'brik-builder' );
				} elseif ( $day === $yesterday ) {
					$label = __( 'Yesterday', 'brik-builder' );
				} else {
					$label = wp_date( get_option( 'date_format' ), strtotime( $day . ' 12:00:00' ) );
				}
				$groups[ $day ] = array(
					'day'   => $day,
					'label' => $label,
					'items' => array(),
				);
			}
			$groups[ $day ]['items'][] = $item;
		}
		return array_values( $groups );
	}

	public static function set_name( $version_id, $name, $pinned = null ) {
		$name = sanitize_text_field( (string) $name );
		if ( '' === $name ) {
			delete_post_meta( $version_id, self::M_NAME );
		} else {
			update_post_meta( $version_id, self::M_NAME, $name );
		}
		if ( null !== $pinned ) {
			if ( $pinned ) {
				update_post_meta( $version_id, self::M_PINNED, 1 );
			} else {
				delete_post_meta( $version_id, self::M_PINNED );
			}
		}
	}

	/**
	 * Restore a version (or only some of its top-level sections) onto a document.
	 *
	 * @param int        $post_id  Document.
	 * @param int        $version  Version id.
	 * @param array|null $sections Section ids to restore; null restores everything.
	 * @param string     $target   live|staging.
	 * @param array|null $base     Tree to apply section restores to (defaults to the target's tree).
	 * @return array|\WP_Error [ tree, page ].
	 */
	public static function restore( $post_id, $version, $sections = null, $target = 'live', $base = null, $source = 'restore' ) {
		$v = self::get( $version, $post_id );
		if ( ! $v ) {
			return new \WP_Error( 'brik_not_found', __( 'Version not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		$old  = self::tree_of( $v );
		$page = self::page_of( $v );

		if ( $sections ) {
			$current = null !== $base ? $base : ( 'staging' === $target && Staging::exists( $post_id ) ? Staging::tree( $post_id ) : Data::get( $post_id ) );
			$tree    = Diff::restore_sections( $current, $old, array_map( 'strval', (array) $sections ) );
			$page    = 'staging' === $target && Staging::exists( $post_id ) ? Staging::page( $post_id ) : Data::page_settings( $post_id );
		} else {
			$tree = $old;
		}

		if ( 'staging' === $target ) {
			$tree = Staging::save( $post_id, $tree, $page, $source );
		} else {
			$tree = self::with_source(
				$source,
				static function () use ( $post_id, $tree, $page ) {
					return Data::save( $post_id, $tree, $page );
				}
			);
		}
		return array( $tree, $page );
	}
}
