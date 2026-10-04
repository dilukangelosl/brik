<?php
/**
 * REST: data sources, query fields, query preview and condition types (brik/v1/data/…).
 *
 * @package Brik
 */

namespace Brik\Data;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Rest {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$ns     = \Brik\Rest::NS;
		$editor = static function ( WP_REST_Request $r ) {
			$id = (int) $r['post_id'];
			return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
		};

		register_rest_route(
			$ns,
			'/data/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$type = sanitize_key( (string) $r['post_type'] );
					if ( $type && ! isset( Data::post_types()[ $type ] ) ) {
						return new WP_Error( 'brik_data_type', __( 'Unknown post type.', 'brik-builder' ), array( 'status' => 400 ) );
					}
					return Sources::tree( (int) $r['post_id'], $type, ! empty( $r['private'] ) && current_user_can( 'edit_others_posts' ) );
				},
				'permission_callback' => $editor,
			)
		);

		register_rest_route(
			$ns,
			'/data/fields',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$type = sanitize_key( (string) $r['post_type'] );
					if ( ! isset( Data::post_types()[ $type ] ) ) {
						return new WP_Error( 'brik_data_type', __( 'Unknown post type.', 'brik-builder' ), array( 'status' => 400 ) );
					}
					return self::fields_payload( $type );
				},
				'permission_callback' => $editor,
			)
		);

		register_rest_route(
			$ns,
			'/data/query/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					$body   = (array) $r->get_json_params();
					$result = Query::preview( isset( $body['query'] ) ? $body['query'] : array(), isset( $body['post_id'] ) ? (int) $body['post_id'] : 0 );
					if ( is_wp_error( $result ) ) {
						$result->add_data( array( 'status' => 400 ) );
					}
					return $result;
				},
				'permission_callback' => static function ( WP_REST_Request $r ) {
					$body = (array) $r->get_json_params();
					$id   = isset( $body['post_id'] ) ? (int) $body['post_id'] : 0;
					return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			$ns,
			'/data/conditions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function () {
					return array( 'types' => Conditions::types() );
				},
				'permission_callback' => $editor,
			)
		);
	}

	/**
	 * Fields, operators and sortable fields for the query builder.
	 */
	public static function fields_payload( $type ) {
		$fields = array_values( Sources::fields( $type ) );
		$order  = array(
			array(
				'key'   => 'rand',
				'label' => __( 'Random', 'brik-builder' ),
				'group' => '',
				'type'  => 'rand',
			),
		);
		foreach ( $fields as $f ) {
			if ( isset( Query::ORDER_COLUMNS[ $f['key'] ] ) || ( ! empty( $f['meta_key'] ) && in_array( $f['type'], array( 'text', 'number', 'date', 'choice' ), true ) ) ) {
				$order[] = array(
					'key'   => $f['key'],
					'label' => $f['label'],
					'group' => $f['group'],
					'type'  => $f['type'],
				);
			}
		}
		return array(
			'post_type'  => $type,
			'post_types' => Data::post_types(),
			'fields'     => $fields,
			'order'      => $order,
			'ops'        => Query::ops(),
		);
	}
}
