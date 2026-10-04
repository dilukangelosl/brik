<?php
namespace Brik\Design;

use Brik\Data;
use Brik\Library;
use Brik\Rest;
use Brik\Settings;
use Brik\Style;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoints for the design system (brik/v1/design/…).
 */
final class RestDesign {

	public static function routes() {
		$editor = static function () {
			return current_user_can( 'edit_posts' );
		};
		$admin  = static function () {
			return current_user_can( 'edit_theme_options' );
		};
		$item   = static function ( WP_REST_Request $r ) {
			return Components::is_component( (int) $r['id'] ) && current_user_can( 'edit_post', (int) $r['id'] );
		};
		$read_item = static function ( WP_REST_Request $r ) {
			return current_user_can( 'edit_posts' ) && Components::is_component( (int) $r['id'] );
		};

		$route = static function ( $path, array $methods ) {
			register_rest_route( Rest::NS, '/design' . $path, $methods );
		};

		$route(
			'',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'overview' ),
				'permission_callback' => $editor,
			)
		);
		$route(
			'/css',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return array( 'css' => Settings::css() );
				},
				'permission_callback' => $editor,
			)
		);
		$route(
			'/usage',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return Usage::scan();
				},
				'permission_callback' => $editor,
			)
		);
		$route(
			'/variables',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$body = (array) $r->get_json_params();
					Settings::update( array( 'variables' => isset( $body['variables'] ) ? $body['variables'] : array() ) );
					return self::payload();
				},
				'permission_callback' => $admin,
			)
		);
		$route(
			'/classes/(?P<id>[a-z0-9_\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						$id = Classes::upsert( (string) $r['id'], (array) $r->get_json_params() );
						if ( ! $id ) {
							return new WP_Error( 'brik_invalid', __( 'Class names may only contain a-z, 0-9, - and _.', 'brik-builder' ), array( 'status' => 400 ) );
						}
						return self::payload( array( 'id' => $id ) );
					},
					'permission_callback' => $admin,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						Classes::delete( (string) $r['id'] );
						return self::payload();
					},
					'permission_callback' => $admin,
				),
			)
		);
		$route(
			'/classes/(?P<id>[a-z0-9_\-]+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					return self::payload( array( 'id' => Classes::duplicate( (string) $r['id'] ) ) );
				},
				'permission_callback' => $admin,
			)
		);
		$route(
			'/components',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return array( 'items' => Components::items() );
					},
					'permission_callback' => $editor,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_component' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_pages' );
					},
				),
			)
		);
		$route(
			'/components/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$id = (int) $r['id'];
					return array(
						'item'   => Library::item( $id ),
						'tree'   => Data::get( $id ),
						'policy' => (object) Components::policy( $id ),
						'url'    => \Brik\Builder::url( $id ),
					);
				},
				'permission_callback' => $read_item,
			)
		);
		$route(
			'/components/(?P<id>\d+)/policy',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$body = (array) $r->get_json_params();
					return array( 'policy' => (object) Components::save_policy( (int) $r['id'], isset( $body['policy'] ) ? $body['policy'] : array() ) );
				},
				'permission_callback' => $item,
			)
		);
		$route(
			'/components/(?P<id>\d+)/resolve',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$body = (array) $r->get_json_params();
					return array(
						'nodes' => Components::resolve(
							(int) $r['id'],
							Components::clean_overrides( isset( $body['overrides'] ) ? $body['overrides'] : array() ),
							isset( $body['context'] ) ? sanitize_key( $body['context'] ) : 'root',
							! empty( $body['fresh'] )
						),
					);
				},
				'permission_callback' => $read_item,
			)
		);
		$route(
			'/components/(?P<id>\d+)/preview',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					return array( 'html' => Components::preview_html( (int) $r['id'] ) );
				},
				'permission_callback' => $read_item,
			)
		);
	}

	public static function payload( array $extra = array() ) {
		return array_merge(
			array(
				'variables' => Variables::resolved(),
				'saved'     => (object) Variables::saved(),
				'classes'   => (object) Classes::all(),
				'css'       => Settings::css(),
			),
			$extra
		);
	}

	public static function overview() {
		return array_merge(
			self::payload(),
			array(
				'groups'      => Variables::groups(),
				'breakpoints' => array(
					'desktop' => Style::TABLET + 1,
					'tablet'  => Style::TABLET,
					'mobile'  => Style::MOBILE,
				),
			)
		);
	}

	public static function create_component( WP_REST_Request $r ) {
		$body  = (array) $r->get_json_params();
		$nodes = isset( $body['tree'] ) ? (array) $body['tree'] : array();
		$id    = Components::create( isset( $body['title'] ) ? $body['title'] : '', $nodes );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$item = Library::item( $id );
		return array(
			'item' => $item,
			'url'  => \Brik\Builder::url( $id ),
		);
	}
}
