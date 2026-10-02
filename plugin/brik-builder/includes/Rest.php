<?php
namespace Brik;

use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Rest {

	const NS = 'brik/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		$edit_post = static function ( WP_REST_Request $r ) {
			$id = (int) ( $r['id'] ? $r['id'] : $r['post_id'] );
			return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
		};
		$editor = static function () {
			return current_user_can( 'edit_posts' );
		};
		$admin = static function () {
			return current_user_can( 'edit_theme_options' );
		};

		register_rest_route(
			self::NS,
			'/posts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_post' ),
					'permission_callback' => $edit_post,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_post' ),
					'permission_callback' => $edit_post,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/render',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'render' ),
				'permission_callback' => $edit_post,
			)
		);

		register_rest_route(
			self::NS,
			'/schema',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'schema' ),
				'permission_callback' => $editor,
			)
		);

		register_rest_route(
			self::NS,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function () {
						return Settings::for_client();
					},
					'permission_callback' => $editor,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						Settings::update( (array) $r->get_json_params() );
						return Settings::for_client();
					},
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/library',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						return array(
							'items'   => Library::items( sanitize_key( (string) $r['kind'] ) ),
							'bundled' => Library::bundled(),
						);
					},
					'permission_callback' => $editor,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'library_create' ),
					'permission_callback' => static function () {
						return current_user_can( 'edit_pages' );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/library/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						$item = Library::item( (int) $r['id'] );
						if ( ! $item ) {
							return new WP_Error( 'brik_not_found', __( 'Library item not found.', 'brik' ), array( 'status' => 404 ) );
						}
						$item['tree'] = Library::nodes_for( (int) $r['id'], sanitize_key( (string) $r['context'] ) );
						return $item;
					},
					'permission_callback' => $editor,
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => static function ( WP_REST_Request $r ) {
						return array( 'deleted' => (bool) wp_trash_post( (int) $r['id'] ) );
					},
					'permission_callback' => static function ( WP_REST_Request $r ) {
						return Library::item( (int) $r['id'] ) && current_user_can( 'delete_post', (int) $r['id'] );
					},
				),
			)
		);

		register_rest_route(
			self::NS,
			'/templates',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'template_create' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NS,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search' ),
				'permission_callback' => $editor,
			)
		);
	}

	public static function post_payload( $post ) {
		$post    = get_post( $post );
		$payload = array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'type'    => $post->post_type,
			'status'  => $post->post_status,
			'link'    => get_permalink( $post ),
			'enabled' => Data::enabled( $post->ID ),
			'tree'    => Data::get( $post->ID ),
			'page'    => (object) Data::page_settings( $post->ID ),
		);
		if ( ThemeBuilder::is_template( $post->ID ) ) {
			$payload['area']       = ThemeBuilder::area( $post->ID );
			$payload['conditions'] = ThemeBuilder::conditions( $post->ID );
		}
		return $payload;
	}

	public static function get_post( WP_REST_Request $r ) {
		$post = get_post( (int) $r['id'] );
		if ( ! $post ) {
			return new WP_Error( 'brik_not_found', __( 'Post not found.', 'brik' ), array( 'status' => 404 ) );
		}
		return self::post_payload( $post );
	}

	public static function save_post( WP_REST_Request $r ) {
		$id   = (int) $r['id'];
		$post = get_post( $id );
		if ( ! $post ) {
			return new WP_Error( 'brik_not_found', __( 'Post not found.', 'brik' ), array( 'status' => 404 ) );
		}
		$body = (array) $r->get_json_params();

		$update = array( 'ID' => $id );
		if ( isset( $body['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $body['title'] );
		}
		if ( ! empty( $body['status'] ) && in_array( $body['status'], array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			if ( 'publish' !== $body['status'] || current_user_can( get_post_type_object( $post->post_type )->cap->publish_posts ) ) {
				$update['post_status'] = $body['status'];
			}
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( $update );
		}

		if ( isset( $body['tree'] ) ) {
			Data::save( $id, (array) $body['tree'], isset( $body['page'] ) ? (array) $body['page'] : null );
		}
		if ( ThemeBuilder::is_template( $id ) ) {
			if ( isset( $body['conditions'] ) ) {
				ThemeBuilder::save_conditions( $id, $body['conditions'] );
			}
			if ( ! empty( $body['area'] ) && isset( ThemeBuilder::areas()[ $body['area'] ] ) ) {
				update_post_meta( $id, ThemeBuilder::META_AREA, $body['area'] );
			}
		}
		return self::post_payload( $id );
	}

	/**
	 * Live preview rendering for the builder canvas.
	 */
	public static function render( WP_REST_Request $r ) {
		$body    = (array) $r->get_json_params();
		$post_id = isset( $body['post_id'] ) ? (int) $body['post_id'] : 0;
		$tree    = Data::normalize( isset( $body['tree'] ) ? (array) $body['tree'] : array() );
		$ids     = isset( $body['ids'] ) ? array_filter( (array) $body['ids'], 'is_string' ) : array();

		self::setup_post( $post_id );

		$renderer          = new Renderer( $post_id, empty( $body['static'] ) );
		$renderer->capture = array_fill_keys( $ids, true );
		$root              = $renderer->render_root( $tree );
		$html              = $renderer->captured;

		$page = isset( $body['page'] ) ? (array) $body['page'] : array();
		$css  = $renderer->style->css() . ( isset( $page['custom_css'] ) ? str_ireplace( '</style', '', (string) $page['custom_css'] ) : '' );

		return array(
			'root'  => $ids ? null : $root,
			'html'  => (object) $html,
			'css'   => $css,
			'fonts' => Fonts::url( $renderer->style->fonts() ),
			'tree'  => $tree,
		);
	}

	/**
	 * Make template tags behave as they would on the post itself.
	 */
	private static function setup_post( $post_id ) {
		if ( ! $post_id ) {
			return;
		}
		$post = get_post( $post_id );
		if ( $post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
		}
	}

	public static function schema() {
		return array(
			'modules'     => Modules::schema(),
			'categories'  => Modules::categories(),
			'common'      => Modules::common_schema(),
			'groups'      => Fields::group_labels(),
			'tags'        => Dynamic::tags(),
			'fonts'       => array_keys( Fonts::google() ),
			'breakpoints' => array(
				'tablet' => Style::TABLET,
				'mobile' => Style::MOBILE,
			),
			'conditions'  => ThemeBuilder::rule_types(),
			'post_types'  => self::post_types(),
			'taxonomies'  => self::taxonomies(),
			'menus'       => array_map(
				static function ( $m ) {
					return array(
						'id'    => $m->term_id,
						'title' => $m->name,
					);
				},
				wp_get_nav_menus()
			),
		);
	}

	private static function post_types() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			$out[ $type->name ] = $type->labels->singular_name;
		}
		return $out;
	}

	private static function taxonomies() {
		$out = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			$out[ $tax->name ] = $tax->labels->singular_name;
		}
		return $out;
	}

	public static function library_create( WP_REST_Request $r ) {
		$body = (array) $r->get_json_params();
		$id   = Library::create(
			isset( $body['title'] ) ? $body['title'] : __( 'Untitled', 'brik' ),
			isset( $body['kind'] ) ? $body['kind'] : 'layout',
			isset( $body['tree'] ) ? (array) $body['tree'] : array(),
			! empty( $body['global'] )
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return Library::item( $id );
	}

	public static function template_create( WP_REST_Request $r ) {
		$body = (array) $r->get_json_params();
		$area = isset( $body['area'] ) && isset( ThemeBuilder::areas()[ $body['area'] ] ) ? $body['area'] : 'header';
		$id   = wp_insert_post(
			array(
				'post_type'   => ThemeBuilder::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => ! empty( $body['title'] ) ? sanitize_text_field( $body['title'] ) : ThemeBuilder::areas()[ $area ],
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, ThemeBuilder::META_AREA, $area );
		ThemeBuilder::save_conditions( $id, isset( $body['conditions'] ) ? $body['conditions'] : array( array( 'type' => 'include', 'rule' => 'entire_site' ) ) );
		Data::save( $id, isset( $body['tree'] ) ? (array) $body['tree'] : array() );
		return array_merge( self::post_payload( $id ), array( 'builder' => Builder::url( $id ) ) );
	}

	public static function search( WP_REST_Request $r ) {
		$q    = sanitize_text_field( (string) $r['q'] );
		$what = sanitize_key( (string) $r['what'] );

		if ( 'term' === $what ) {
			$terms = get_terms(
				array(
					'taxonomy'   => sanitize_key( (string) $r['taxonomy'] ),
					'search'     => $q,
					'number'     => 20,
					'hide_empty' => false,
				)
			);
			return is_wp_error( $terms ) ? array() : array_map(
				static function ( $t ) {
					return array(
						'id'    => $t->term_id,
						'title' => $t->name,
					);
				},
				$terms
			);
		}

		$posts = get_posts(
			array(
				's'              => $q,
				'post_type'      => $r['post_type'] ? sanitize_key( (string) $r['post_type'] ) : 'any',
				'posts_per_page' => 20,
				'post_status'    => array( 'publish', 'draft', 'private' ),
			)
		);
		return array_map(
			static function ( $p ) {
				return array(
					'id'    => $p->ID,
					'title' => $p->post_title,
					'type'  => $p->post_type,
				);
			},
			$posts
		);
	}
}
