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
		$html             = $renderer->render_nodes( Library::nodes_for( $ref, $where ) );
		$renderer->canvas = $canvas;
		$renderer->leave( 'global:' . $ref );

		if ( $canvas ) {
			return '<div class="brik-el brik-global" data-brik-id="' . esc_attr( $ctx->id ) . '" data-brik-type="global" data-brik-ref="' . $ref . '">' . $html . '</div>';
		}
		return $html;
	},
);
