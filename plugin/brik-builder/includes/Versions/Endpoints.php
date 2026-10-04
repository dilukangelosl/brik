<?php
/**
 * REST endpoints for versions, staging and schedules.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

use Brik\Data;
use Brik\Plugin;
use Brik\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Endpoints {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'brik/saved', array( __CLASS__, 'after_builder_save' ), 20, 1 );
	}

	/**
	 * May the current user edit this document with Brik?
	 */
	public static function can_edit( $post_id ) {
		$post = get_post( (int) $post_id );
		return $post && 'trash' !== $post->post_status && Plugin::supports( $post ) && current_user_can( 'edit_post', $post->ID );
	}

	/**
	 * Deploying changes what visitors see, so it needs the publish capability of the post type
	 * (and edit_theme_options for theme builder templates).
	 */
	public static function can_publish( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || ! self::can_edit( $post_id ) ) {
			return false;
		}
		$type = get_post_type_object( $post->post_type );
		if ( \Brik\ThemeBuilder::is_template( $post->ID ) && ! current_user_can( 'edit_theme_options' ) ) {
			return false;
		}
		return $type && current_user_can( $type->cap->publish_posts );
	}

	public static function routes() {
		$ns   = Rest::NS;
		$edit = static function ( WP_REST_Request $r ) {
			return self::can_edit( (int) $r['post_id'] );
		};
		$publish = static function ( WP_REST_Request $r ) {
			return self::can_publish( (int) $r['post_id'] );
		};
		$id = '(?P<post_id>\d+)';

		register_rest_route(
			$ns,
			"/versions/$id",
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_versions' ),
				'permission_callback' => $edit,
			)
		);
		register_rest_route(
			$ns,
			"/versions/$id/(?P<version_id>\d+)",
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_version' ),
				'permission_callback' => $edit,
			)
		);
		register_rest_route(
			$ns,
			"/versions/$id/name",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'name_version' ),
				'permission_callback' => $edit,
			)
		);
		register_rest_route(
			$ns,
			"/versions/$id/restore",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'restore' ),
				'permission_callback' => $edit,
			)
		);
		register_rest_route(
			$ns,
			"/versions/$id/compare",
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( __CLASS__, 'compare' ),
				'permission_callback' => $edit,
			)
		);
		register_rest_route(
			$ns,
			'/versions/settings',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					update_option( Versions::OPTION_LIMIT, min( 500, max( 1, (int) $r['limit'] ) ), false );
					return array( 'limit' => Versions::limit() );
				},
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			$ns,
			"/staging/$id",
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						return Staging::state( (int) $r['post_id'], true );
					},
					'permission_callback' => $edit,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_staging' ),
					'permission_callback' => $edit,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						Staging::discard( (int) $r['post_id'] );
						return Staging::state( (int) $r['post_id'] );
					},
					'permission_callback' => $edit,
				),
			)
		);
		register_rest_route(
			$ns,
			"/staging/$id/deploy",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'deploy' ),
				'permission_callback' => $publish,
			)
		);
		register_rest_route(
			$ns,
			"/staging/$id/token",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					Staging::token( (int) $r['post_id'], true );
					return Staging::state( (int) $r['post_id'] );
				},
				'permission_callback' => $edit,
			)
		);

		register_rest_route(
			$ns,
			"/schedule/$id",
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'schedule' ),
				'permission_callback' => $publish,
			)
		);
		register_rest_route(
			$ns,
			"/schedule/$id/(?P<schedule_id>[a-f0-9]+)",
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$ok = Schedule::cancel( (int) $r['post_id'], (string) $r['schedule_id'] );
					if ( ! $ok ) {
						return new WP_Error( 'brik_not_found', __( 'Scheduled action not found.', 'brik-builder' ), array( 'status' => 404 ) );
					}
					return Staging::state( (int) $r['post_id'] );
				},
				'permission_callback' => $publish,
			)
		);
	}

	public static function list_versions( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		return array(
			'groups' => Versions::grouped( $post_id ),
			'state'  => Staging::state( $post_id ),
		);
	}

	public static function get_version( WP_REST_Request $r ) {
		$v = Versions::get( (int) $r['version_id'], (int) $r['post_id'] );
		if ( ! $v ) {
			return new WP_Error( 'brik_not_found', __( 'Version not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		return Versions::payload( $v, true );
	}

	public static function name_version( WP_REST_Request $r ) {
		$v = Versions::get( (int) $r['version_id'], (int) $r['post_id'] );
		if ( ! $v ) {
			return new WP_Error( 'brik_not_found', __( 'Version not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		Versions::set_name( $v->ID, (string) $r['name'], null === $r['pinned'] ? null : rest_sanitize_boolean( $r['pinned'] ) );
		return Versions::payload( $v );
	}

	private static function section_ids( $value ) {
		if ( null === $value || '' === $value ) {
			return null;
		}
		$ids = array_values( array_filter( array_map( 'sanitize_key', (array) $value ) ) );
		return $ids ? $ids : null;
	}

	public static function restore( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		$target  = 'staging' === $r['target'] ? 'staging' : 'live';
		if ( 'live' === $target && ! self::can_publish( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to change the live page. Restore to staging instead.', 'brik-builder' ), array( 'status' => 403 ) );
		}
		$base   = is_array( $r['tree'] ) ? Data::normalize( $r['tree'] ) : null;
		$result = Versions::restore( $post_id, (int) $r['version_id'], self::section_ids( $r['sections'] ), $target, $base );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'tree'   => $result[0],
			'page'   => (object) $result[1],
			'target' => $target,
			'post'   => Rest::post_payload( $post_id ),
			'state'  => Staging::state( $post_id ),
		);
	}

	/**
	 * Resolve a compare side: a version id, "current"/"live", or "staging".
	 *
	 * @return array|WP_Error [ tree, page, label ].
	 */
	public static function side( $post_id, $ref, $current = null ) {
		$ref = (string) $ref;
		if ( 'current' === $ref && is_array( $current ) ) {
			return array( $current['tree'], $current['page'], __( 'Current', 'brik-builder' ) );
		}
		if ( 'current' === $ref || 'live' === $ref ) {
			return array( Data::get( $post_id ), Data::page_settings( $post_id ), 'live' === $ref ? __( 'Live', 'brik-builder' ) : __( 'Current', 'brik-builder' ) );
		}
		if ( 'staging' === $ref ) {
			return Staging::exists( $post_id )
				? array( Staging::tree( $post_id ), Staging::page( $post_id ), __( 'Staging', 'brik-builder' ) )
				: array( Data::get( $post_id ), Data::page_settings( $post_id ), __( 'Staging', 'brik-builder' ) );
		}
		$v = Versions::get( (int) $ref, $post_id );
		if ( ! $v ) {
			/* translators: %s: version reference */
			return new WP_Error( 'brik_not_found', sprintf( __( 'Version "%s" not found.', 'brik-builder' ), $ref ), array( 'status' => 404 ) );
		}
		/* translators: %d: version number */
		return array( Versions::tree_of( $v ), Versions::page_of( $v ), sprintf( __( 'Version %d', 'brik-builder' ), (int) get_post_meta( $v->ID, Versions::M_NUMBER, true ) ) );
	}

	public static function compare_refs( $post_id, $a, $b, $current = null ) {
		$sa = self::side( $post_id, $a, $current );
		if ( is_wp_error( $sa ) ) {
			return $sa;
		}
		$sb = self::side( $post_id, $b, $current );
		if ( is_wp_error( $sb ) ) {
			return $sb;
		}
		return array_merge(
			array(
				'a' => array(
					'ref'   => (string) $a,
					'label' => $sa[2],
				),
				'b' => array(
					'ref'   => (string) $b,
					'label' => $sb[2],
				),
			),
			Diff::compare( $sa[0], $sb[0], $sa[1], $sb[1] )
		);
	}

	public static function compare( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		$current = null;
		// The builder sends its unsaved tree so "current" means what the editor shows.
		if ( is_array( $r['tree'] ) ) {
			$current = array(
				'tree' => Data::normalize( $r['tree'] ),
				'page' => is_array( $r['page'] ) ? $r['page'] : Data::page_settings( $post_id ),
			);
		}
		return self::compare_refs( $post_id, $r['a'] ? $r['a'] : 'live', $r['b'] ? $r['b'] : 'current', $current );
	}

	public static function save_staging( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		if ( ! is_array( $r['tree'] ) ) {
			return new WP_Error( 'brik_invalid', __( 'A tree is required.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		Staging::save( $post_id, $r['tree'], is_array( $r['page'] ) ? $r['page'] : null );
		return Staging::state( $post_id, true );
	}

	public static function deploy( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		$live    = Staging::deploy( $post_id );
		if ( is_wp_error( $live ) ) {
			return $live;
		}
		return array(
			'post'  => Rest::post_payload( $post_id ),
			'state' => Staging::state( $post_id ),
		);
	}

	public static function schedule( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		$entry   = Schedule::add( $post_id, (string) $r['action'], $r['time'], (int) $r['version_id'] );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		return array(
			'schedule' => $entry,
			'state'    => Staging::state( $post_id ),
		);
	}

	/**
	 * Publishing from the builder while it edits the staging copy publishes that copy:
	 * the builder flags the save and staging is cleared.
	 */
	public static function after_builder_save( $post_id ) {
		if ( Versions::request_flag( 'brik_clear_staging' ) && self::can_publish( $post_id ) && Staging::exists( $post_id ) ) {
			Staging::discard( $post_id );
		}
	}
}
