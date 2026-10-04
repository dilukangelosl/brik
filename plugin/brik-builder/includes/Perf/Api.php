<?php
namespace Brik\Perf;

use Brik\McpTools;
use Brik\Plugin;
use Brik\Rest;
use Brik\ThemeBuilder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST routes (brik/v1/perf/…) and MCP tools for the analyzer and the cleaner.
 */
final class Api {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'brik/mcp_tools', array( __CLASS__, 'mcp_tools' ) );
		add_filter( 'brik/mcp_guide', array( __CLASS__, 'mcp_guide' ) );
	}

	public static function routes() {
		$can = static function ( WP_REST_Request $r ) {
			return current_user_can( 'edit_post', (int) $r['id'] );
		};

		register_rest_route(
			Rest::NS,
			'/perf/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'report' ),
					'permission_callback' => $can,
				),
				// POST analyzes an unsaved tree (the builder's current state).
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'report' ),
					'permission_callback' => $can,
				),
			)
		);

		register_rest_route(
			Rest::NS,
			'/perf/(?P<id>\d+)/clean',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'clean' ),
				'permission_callback' => $can,
				'args'                => array(
					'dry_run' => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);
	}

	private static function post( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			return new WP_Error( 'brik_not_found', __( 'Post not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		if ( ! Plugin::supports( $post ) ) {
			return new WP_Error( 'brik_unsupported', __( 'Brik is not enabled for this post type.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		if ( ThemeBuilder::is_template( $post->ID ) && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Editing theme builder templates requires the edit_theme_options capability.', 'brik-builder' ), array( 'status' => 403 ) );
		}
		return $post;
	}

	public static function report( WP_REST_Request $r ) {
		$post = self::post( $r['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$body = (array) $r->get_json_params();
		$tree = isset( $body['tree'] ) && is_array( $body['tree'] ) ? $body['tree'] : null;
		return Analyzer::report( $post->ID, $tree );
	}

	public static function clean( WP_REST_Request $r ) {
		$post = self::post( $r['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$body  = (array) $r->get_json_params();
		$tree  = isset( $body['tree'] ) && is_array( $body['tree'] ) ? $body['tree'] : null;
		$kinds = isset( $body['fixes'] ) ? array_map( 'sanitize_key', (array) $body['fixes'] ) : array();
		$dry   = isset( $body['dry_run'] ) ? rest_sanitize_boolean( $body['dry_run'] ) : (bool) $r['dry_run'];
		return Cleaner::run( $post->ID, $tree, ! $dry, $kinds );
	}

	/* ---------------------------------------------------------------------
	 * MCP.
	 * ------------------------------------------------------------------- */

	public static function mcp_tools( $tools ) {
		$post_id = array(
			'type'        => 'integer',
			'description' => 'Page, post, template or library item id.',
		);
		return $tools + array(
			'performance_report' => array(
				'title'       => 'Performance report',
				'description' => 'Renders a page server-side (with its header/footer/body templates) and reports: elements by type, which load JavaScript (KB each), CSS bytes (full library vs the optimized per-page file), HTML element count, image weights (flags files over 300 KB or more than twice their displayed width), fonts and how they load, third-party embeds, a 0-100 score with per-area scores, and suggestions with estimated savings. Findings carry node_id when an element is responsible.',
				'read_only'   => true,
				'props'       => array( 'post_id' => $post_id ),
				'required'    => array( 'post_id' ),
				'callback'    => array( __CLASS__, 'mcp_report' ),
			),
			'clean_page'         => array(
				'title'       => 'Clean page',
				'description' => 'Finds and (unless dry_run) removes dead weight without changing how the page looks: nested one-column rows without settings, empty sections/rows, empty headings/text, exact duplicate neighbours, settings equal to defaults, elements hidden on every device, empty custom CSS, and images using a much larger registered size than displayed. Returns the fixes, potential savings (css/js bytes, DOM nodes, image bytes) and unused style presets (report only). dry_run defaults to true; with dry_run false the cleaned tree is saved.',
				'destructive' => true,
				'props'       => array(
					'post_id' => $post_id,
					'dry_run' => array(
						'type'        => 'boolean',
						'description' => 'Only report (default true).',
					),
					'fixes'   => array(
						'type'        => 'array',
						'description' => 'Limit to these fix kinds (default all).',
						'items'       => array(
							'type' => 'string',
							'enum' => Cleaner::KINDS,
						),
					),
				),
				'required'    => array( 'post_id' ),
				'callback'    => array( __CLASS__, 'mcp_clean' ),
			),
		);
	}

	private static function mcp_post( $id ) {
		$post = self::post( $id );
		if ( ! is_wp_error( $post ) && ! current_user_can( 'edit_post', $post->ID ) ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_forbidden', sprintf( __( 'You are not allowed to edit post %d.', 'brik-builder' ), $post->ID ) );
		}
		return $post;
	}

	public static function mcp_report( array $a ) {
		$post = self::mcp_post( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$report = Analyzer::report( $post->ID );
		if ( is_wp_error( $report ) ) {
			return $report;
		}
		// Keep the response compact for clients.
		$report['images']['list'] = array_map(
			static function ( $img ) {
				return array_intersect_key( $img, array_flip( array( 'node_id', 'name', 'bytes', 'width', 'display', 'size', 'heavy', 'oversized', 'suggest', 'estimate', 'local' ) ) );
			},
			$report['images']['list']
		);
		if ( ! $report['optimized'] ) {
			McpTools::warn( __( 'Optimized assets are switched off (Brik → Settings → Performance); visitors get the full stylesheet and script bundle.', 'brik-builder' ) );
		}
		return $report;
	}

	public static function mcp_clean( array $a ) {
		$post = self::mcp_post( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$dry    = ! isset( $a['dry_run'] ) || $a['dry_run'];
		$result = Cleaner::run( $post->ID, null, ! $dry, isset( $a['fixes'] ) ? (array) $a['fixes'] : array() );
		unset( $result['tree'], $result['page'] );
		if ( $dry && $result['changed'] ) {
			McpTools::warn( __( 'Dry run: nothing was saved. Call clean_page again with dry_run false to apply these fixes.', 'brik-builder' ) );
		}
		return $result;
	}

	public static function mcp_guide( $guide ) {
		return $guide . "\n\n## Performance\n"
			. "- performance_report(post_id) scores a page 0-100 and lists what to fix with estimated savings. Run it after building a page.\n"
			. "- clean_page(post_id) is a dry run by default; review the fixes, then call it with dry_run false. It never changes how the page looks.\n"
			. "- Keep pages light: prefer native modules over effects, reuse the same few fonts, upload images close to their displayed size, and avoid nesting rows without a reason.\n";
	}
}
