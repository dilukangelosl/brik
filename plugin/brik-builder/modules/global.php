<?php
/**
 * Global element: renders a library item so edits sync everywhere it is used.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Library;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'global',
	'title'       => __( 'Global element', 'brik-builder' ),
	'category'    => 'structure',
	'icon'        => 'globe',
	'raw'         => true,
	'description' => 'Embeds a library item by id ("ref"). Changing the library item updates every page that uses it.',
	'fields'      => array(
		'ref' => Fields::field( 'library', __( 'Library item', 'brik-builder' ), 'content' ),
	),
	'render'      => static function ( $a, $ctx ) {
		$ref = isset( $a['ref'] ) ? (int) $a['ref'] : 0;
		if ( ! $ref || ! Library::item( $ref ) ) {
			return $ctx->canvas ? '<div class="brik-el brik-global" data-brik-id="' . esc_attr( $ctx->id ) . '" data-brik-type="global">' . $ctx->placeholder( __( 'Choose a library item', 'brik-builder' ) ) . '</div>' : '';
		}
		$renderer = $ctx->renderer;
		if ( ! $renderer->enter( 'global:' . $ref ) ) {
			return '';
		}
		$parents = $renderer->stack;
		array_pop( $parents );
		$parent = $parents ? end( $parents ) : null;
		$where  = $parent && 'column' === $parent['type'] ? 'column' : 'root';

		// Render the item without builder markers so it can't be edited in place.
		$canvas           = $renderer->canvas;
		$renderer->canvas = false;
		$nodes            = Library::nodes_for( $ref, $where );
		// Components: merge the instance's allowed overrides into the master nodes.
		$component = 'component' === get_post_meta( $ref, Library::META_KIND, true ) && class_exists( 'Brik\\Design\\Components' );
		if ( $component ) {
			$nodes = Brik\Design\Components::apply_overrides( $nodes, $ref, isset( $a['overrides'] ) ? (array) $a['overrides'] : array(), $ctx->id );
		}
		$html             = $renderer->render_nodes( $nodes );
		$renderer->canvas = $canvas;
		$renderer->leave( 'global:' . $ref );

		if ( $canvas ) {
			$badge = $component ? ' data-brik-component="' . esc_attr( get_the_title( $ref ) ) . '"' : '';
			return '<div class="brik-el brik-global' . ( $component ? ' brik-is-component' : '' ) . '" data-brik-id="' . esc_attr( $ctx->id ) . '" data-brik-type="global" data-brik-ref="' . $ref . '"' . $badge . '>' . $html . '</div>';
		}
		return $html;
	},
);
