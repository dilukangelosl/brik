<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Passed to a module's render callback.
 */
final class Context {

	/** @var Renderer */
	public $renderer;

	public $node;

	public $def;

	public $id;

	public $post_id;

	public $canvas;

	/** Extra classes for the element wrapper. */
	public $classes = array();

	/** Extra HTML attributes for the element wrapper. */
	public $attrs = array();

	public function __construct( Renderer $renderer, array $node, array $def ) {
		$this->renderer = $renderer;
		$this->node     = $node;
		$this->def      = $def;
		$this->id       = $node['id'];
		$this->post_id  = $renderer->post_id;
		$this->canvas   = $renderer->canvas;
	}

	public function children() {
		$children = isset( $this->node['children'] ) ? (array) $this->node['children'] : array();
		$html     = $this->renderer->render_nodes( $children );
		if ( '' === $html && $this->canvas ) {
			$html = '<div class="brik-canvas-empty" data-brik-empty="' . esc_attr( $this->id ) . '"></div>';
		}
		return $html;
	}

	public function has_children() {
		return ! empty( $this->node['children'] );
	}

	public function selector() {
		return Style::selector( $this->node );
	}

	/**
	 * Add a CSS rule computed by the module. {{wrap}} is replaced with the element selector.
	 */
	public function css( $selector, $declaration, $state = 'desktop' ) {
		$this->renderer->style->push( $state, str_replace( Fields::WRAP, $this->selector(), $selector ), $declaration );
	}

	/**
	 * Placeholder shown only inside the builder when a module has nothing to display.
	 */
	public function placeholder( $text ) {
		return $this->canvas ? '<div class="brik-placeholder">' . esc_html( $text ) . '</div>' : '';
	}

	/**
	 * Attribute marking an element as editable in place in the builder, e.g. inline( 'text' ).
	 */
	public function inline( $field ) {
		return $this->canvas ? ' data-brik-inline="' . esc_attr( $field ) . '"' : '';
	}

	/**
	 * Load an effect script (assets/build/fx/{name}.js) only on pages that use it.
	 */
	public function script( $name ) {
		$name = sanitize_key( $name );
		$this->renderer->scripts[ $name ] = true;
		Frontend::fx( $name );
	}

	/**
	 * Unique DOM id for elements inside this module (tabs, accordions, dialogs).
	 */
	public function uid( $suffix = '' ) {
		return 'brik-' . $this->id . ( $suffix ? '-' . $suffix : '' );
	}
}
