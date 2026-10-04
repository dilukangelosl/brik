<?php
/**
 * Editor tools: responsive helpers, fluid values, diagnosis, inspector and export.
 *
 * Most of the feature lives in the builder app (features/editor-tools). This class adds
 * the server side: export/convert endpoints and MCP tools.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Editor;

use Brik\Data;
use Brik\McpTools;
use Brik\Modules;
use Brik\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

final class Editor {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'brik/mcp_tools', array( __CLASS__, 'mcp_tools' ) );
		add_filter( 'brik/mcp_guide', array( __CLASS__, 'mcp_guide' ) );
	}

	/* ---------------------------------------------------------------------
	 * REST.
	 * ------------------------------------------------------------------- */

	public static function routes() {
		$can_edit = static function ( WP_REST_Request $r ) {
			return current_user_can( 'edit_post', (int) $r['id'] );
		};
		$id_arg   = array(
			'id' => array(
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
		);
		$mode_arg = array(
			'type'    => 'string',
			'enum'    => Export::MODES,
			'default' => 'blocks',
		);

		register_rest_route(
			Rest::NS,
			'/editor/export/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_preview' ),
					'permission_callback' => $can_edit,
					'args'                => $id_arg + array( 'mode' => $mode_arg ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_convert' ),
					'permission_callback' => $can_edit,
					'args'                => $id_arg + array( 'mode' => $mode_arg ),
				),
			)
		);

		register_rest_route(
			Rest::NS,
			'/editor/restore/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					return Export::restore( (int) $r['id'] );
				},
				'permission_callback' => $can_edit,
				'args'                => $id_arg,
			)
		);

		register_rest_route(
			Rest::NS,
			'/editor/status/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => static function ( WP_REST_Request $r ) {
					return Export::status( (int) $r['id'] );
				},
				'permission_callback' => $can_edit,
				'args'                => $id_arg,
			)
		);
	}

	public static function rest_preview( WP_REST_Request $r ) {
		$built = Export::build( (int) $r['id'], (string) $r['mode'] );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		return array(
			'mode'      => (string) $r['mode'],
			'content'   => $built['content'],
			'preview'   => $built['preview'],
			'stats'     => $built['stats'],
			'css_bytes' => strlen( $built['css'] ),
			'warnings'  => self::warnings(),
		);
	}

	public static function rest_convert( WP_REST_Request $r ) {
		return Export::convert( (int) $r['id'], (string) $r['mode'] );
	}

	private static function warnings() {
		$out = array();
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$out[] = __( 'Your account cannot save <style> tags, so WordPress will strip the exported CSS. Ask an administrator to run the conversion.', 'brik-builder' );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * MCP.
	 * ------------------------------------------------------------------- */

	public static function mcp_tools( array $tools ) {
		$post_id = array(
			'type'        => 'integer',
			'description' => 'ID of the page or post.',
			'minimum'     => 1,
		);

		$tools['convert_page'] = array(
			'title'       => 'Convert a page away from Brik',
			'description' => 'Removes the builder from one page without losing its content. mode "blocks" maps sections/rows/columns/headings/text/buttons/images/galleries/videos/dividers/spacers/accordions/code to core Gutenberg blocks (anything else becomes a Custom HTML block with its rendered markup) and adds the needed CSS in an HTML block at the top. mode "static" writes one Custom HTML block with the rendered page and only the CSS it uses. The Brik data is backed up first and Brik is switched off for the page; restore_brik_page undoes it. Use dry_run: true to preview stats and block markup without changing anything.',
			'destructive' => true,
			'props'       => array(
				'post_id' => $post_id,
				'mode'    => array(
					'type' => 'string',
					'enum' => Export::MODES,
				),
				'dry_run' => array(
					'type'        => 'boolean',
					'description' => 'Only return what the conversion would produce.',
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'mcp_convert' ),
		);

		$tools['restore_brik_page'] = array(
			'title'       => 'Restore a converted page',
			'description' => 'Re-enables Brik on a page converted with convert_page, restoring its Brik tree from the backup taken before the conversion.',
			'props'       => array( 'post_id' => $post_id ),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'mcp_restore' ),
		);

		$tools['make_fluid'] = array(
			'title'       => 'Make a value fluid',
			'description' => 'Sets a length attribute (font_size, title_font_size, gap, width, max_width, min_height, space…) to a fluid clamp() that scales linearly from min_px at a 390px viewport to max_px at 1440px, and removes its @tablet/@mobile overrides. Without min_px/max_px the current @mobile and desktop values are used.',
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => array( 'type' => 'string' ),
				'key'     => array(
					'type'        => 'string',
					'description' => 'Attribute name without a state suffix, e.g. "title_font_size".',
				),
				'min_px'  => array(
					'type'        => 'number',
					'description' => 'Value at a 390px wide viewport.',
				),
				'max_px'  => array(
					'type'        => 'number',
					'description' => 'Value at a 1440px wide viewport.',
				),
			),
			'required'    => array( 'post_id', 'node_id', 'key' ),
			'callback'    => array( __CLASS__, 'mcp_fluid' ),
		);

		return $tools;
	}

	private static function editable( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post ) {
			return new WP_Error( 'brik_mcp_not_found', __( 'Post not found.', 'brik-builder' ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new WP_Error( 'brik_mcp_forbidden', __( 'You are not allowed to edit this item.', 'brik-builder' ) );
		}
		return $post;
	}

	public static function mcp_convert( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$mode = isset( $a['mode'] ) ? $a['mode'] : 'blocks';
		foreach ( self::warnings() as $w ) {
			McpTools::warn( $w );
		}
		if ( ! empty( $a['dry_run'] ) ) {
			$built = Export::build( $post->ID, $mode );
			if ( is_wp_error( $built ) ) {
				return $built;
			}
			$content = $built['content'];
			return array(
				'mode'      => $mode,
				'stats'     => $built['stats'],
				'css_bytes' => strlen( $built['css'] ),
				'content'   => strlen( $content ) > 12000 ? substr( $content, 0, 12000 ) . "\n…" : $content,
			);
		}
		if ( ! Data::enabled( $post->ID ) ) {
			return new WP_Error( 'brik_mcp_not_brik', __( 'This page is not built with Brik.', 'brik-builder' ) );
		}
		return Export::convert( $post->ID, $mode );
	}

	public static function mcp_restore( array $a ) {
		$post = self::editable( $a['post_id'] );
		return is_wp_error( $post ) ? $post : Export::restore( $post->ID );
	}

	public static function mcp_fluid( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree = Data::get( $post->ID );
		$node = &Data::find( $tree, (string) $a['node_id'] );
		if ( ! $node ) {
			return new WP_Error( 'brik_mcp_args', __( 'Node not found.', 'brik-builder' ) );
		}
		$key   = preg_replace( '/@.*$/', '', sanitize_key( $a['key'] ) );
		$def   = Modules::get( $node['type'] );
		$field = $def && isset( $def['fields'][ $key ] ) ? $def['fields'][ $key ] : null;
		if ( ! $field || 'unit' !== $field['type'] ) {
			/* translators: %s: attribute name */
			return new WP_Error( 'brik_mcp_args', sprintf( __( '"%s" is not a length attribute of this element.', 'brik-builder' ), $key ) );
		}
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		$base  = isset( $attrs[ $key ] ) ? Fluid::to_px( $attrs[ $key ] ) : null;
		if ( null === $base && isset( $def['fields'][ $key ]['default'] ) ) {
			$base = Fluid::to_px( $def['fields'][ $key ]['default'] );
		}
		$small = isset( $attrs[ $key . '@mobile' ] ) ? Fluid::to_px( $attrs[ $key . '@mobile' ] ) : ( isset( $attrs[ $key . '@tablet' ] ) ? Fluid::to_px( $attrs[ $key . '@tablet' ] ) : null );
		$min   = isset( $a['min_px'] ) ? (float) $a['min_px'] : $small;
		$max   = isset( $a['max_px'] ) ? (float) $a['max_px'] : $base;
		if ( null === $min || null === $max ) {
			return new WP_Error( 'brik_mcp_args', __( 'Give min_px and max_px: the current values are not in px.', 'brik-builder' ) );
		}
		$value         = Fluid::clamp( $min, $max );
		$attrs[ $key ] = $value;
		unset( $attrs[ $key . '@tablet' ], $attrs[ $key . '@mobile' ] );
		$node['attrs'] = $attrs;
		unset( $node );
		Data::save( $post->ID, $tree );
		return array(
			'node_id' => (string) $a['node_id'],
			'key'     => $key,
			'value'   => $value,
		);
	}

	public static function mcp_guide( $guide ) {
		return $guide . "\n\n## Fluid values and leaving Brik\n\n"
			. "Lengths can be fluid instead of per-device: `clamp(30px, calc(18.8571px + 2.8571vw), 48px)` is 30px on a 390px phone and 48px at 1440px, "
			. "scaling smoothly in between. `make_fluid` writes these for you and removes the @tablet/@mobile overrides.\n\n"
			. "`convert_page` turns a Brik page into core blocks (mode \"blocks\") or one static HTML block (mode \"static\") and switches Brik off for it, "
			. "keeping a backup; `restore_brik_page` brings Brik back. Always run it with dry_run: true first and tell the user what will become Custom HTML.\n";
	}
}
