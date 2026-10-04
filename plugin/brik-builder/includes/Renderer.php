<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a Brik tree into HTML and CSS.
 */
final class Renderer {

	/** @var Style */
	public $style;

	public $post_id;

	public $canvas;

	/** Stack of nodes being rendered, used by modules that need their parents. */
	public $stack = array();

	private $depth_guard = array();

	/** Node ids whose markup should be kept separately (builder partial updates). */
	public $capture = array();

	public $captured = array();

	public function __construct( $post_id = 0, $canvas = false ) {
		$this->post_id = (int) $post_id;
		$this->canvas  = (bool) $canvas;
		$this->style   = new Style( $this->canvas );
	}

	/**
	 * Render a list of top-level nodes and wrap them in the content root.
	 */
	/** Whether any effect module or background effect was rendered. */
	public $effects = false;

	/** Effect scripts requested while rendering (see Context::script()). */
	public $scripts = array();

	/** How many times each module type was rendered. */
	public $types = array();

	/** Top-level nodes of the tree being rendered, so sections can look at their neighbours. */
	public $root = array();

	public function render_root( array $nodes, $class = '' ) {
		$this->root = array_values( $nodes );
		$html       = $this->render_nodes( $nodes );
		if ( $this->canvas && ! $nodes ) {
			$html = '<div class="brik-canvas-empty" data-brik-empty="root"></div>';
		}
		return sprintf(
			'<div class="%s" data-brik-root="%d">%s</div>',
			esc_attr( brik_cls( 'brik brik-root', $class ) ),
			$this->post_id,
			$html
		);
	}

	public function render_nodes( array $nodes ) {
		$out = '';
		foreach ( $nodes as $node ) {
			$out .= $this->render_node( $node );
		}
		return $out;
	}

	public function render_node( $node ) {
		if ( ! is_array( $node ) || empty( $node['type'] ) ) {
			return '';
		}
		$def = Modules::get( $node['type'] );
		if ( ! $def ) {
			return $this->canvas ? $this->missing( $node ) : '';
		}
		$node = wp_parse_args(
			$node,
			array(
				'id'       => Data::id(),
				'attrs'    => array(),
				'children' => array(),
			)
		);

		$attrs = $this->resolve_attrs( $node, $def );

		if ( ! $this->canvas && ! $this->visible( $attrs ) ) {
			return '';
		}

		$this->types[ $node['type'] ] = ( isset( $this->types[ $node['type'] ] ) ? $this->types[ $node['type'] ] : 0 ) + 1;
		$this->style->add_node( $node, $def, $attrs );
		if ( 'effects' === $def['category'] || ! empty( $attrs['bg_effect'] ) ) {
			$this->effects = true;
			Frontend::effects_css();
		}

		$this->stack[] = $node;
		$ctx           = new Context( $this, $node, $def );
		$inner         = is_callable( $def['render'] ) ? (string) call_user_func( $def['render'], $attrs, $ctx ) : '';
		array_pop( $this->stack );

		$out = ! empty( $def['raw'] ) ? $inner : $this->wrap( $node, $def, $attrs, $inner, $ctx );
		if ( isset( $this->capture[ $node['id'] ] ) ) {
			$this->captured[ $node['id'] ] = $out;
		}
		return $out;
	}

	/**
	 * Merge defaults, preset and node attributes and resolve dynamic tags.
	 */
	public function resolve_attrs( array $node, array $def ) {
		$attrs = array_merge( array_fill_keys( array_keys( $def['fields'] ), '' ), Modules::defaults( $node['type'] ) );

		if ( ! empty( $node['attrs']['preset'] ) ) {
			$preset = Settings::preset( $node['type'], $node['attrs']['preset'] );
			if ( $preset ) {
				$attrs = array_merge( $attrs, $preset );
			}
		} elseif ( $default = Settings::default_preset( $node['type'] ) ) {
			$attrs = array_merge( $attrs, $default );
		}

		$attrs = array_merge( $attrs, (array) $node['attrs'] );

		foreach ( $def['fields'] as $key => $field ) {
			if ( isset( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) && in_array( $field['type'], array( 'text', 'textarea', 'richtext', 'link', 'image' ), true ) ) {
				$attrs[ $key ] = Dynamic::replace( $attrs[ $key ], $this->post_id );
			}
			// Link and image objects can carry tags in their url, e.g. {"url": "{post_url}"}.
			if ( isset( $attrs[ $key ]['url'] ) && is_string( $attrs[ $key ]['url'] ) && in_array( $field['type'], array( 'link', 'image', 'video' ), true ) ) {
				$attrs[ $key ]['url'] = Dynamic::replace( $attrs[ $key ]['url'], $this->post_id );
			}
			if ( 'repeater' === $field['type'] && isset( $attrs[ $key ] ) && is_array( $attrs[ $key ] ) ) {
				foreach ( $attrs[ $key ] as &$item ) {
					if ( is_array( $item ) ) {
						foreach ( $item as $k => $v ) {
							if ( is_string( $v ) ) {
								$item[ $k ] = Dynamic::replace( $v, $this->post_id );
							}
						}
					}
				}
				unset( $item );
			}
		}

		return apply_filters( 'brik/attrs', $attrs, $node, $this );
	}

	private function visible( array $attrs ) {
		if ( ! empty( $attrs['display'] ) ) {
			if ( 'logged_in' === $attrs['display'] && ! is_user_logged_in() ) {
				return false;
			}
			if ( 'logged_out' === $attrs['display'] && is_user_logged_in() ) {
				return false;
			}
		}
		$now = current_time( 'timestamp' );
		if ( ! empty( $attrs['show_from'] ) && strtotime( $attrs['show_from'] ) > $now ) {
			return false;
		}
		if ( ! empty( $attrs['show_until'] ) && strtotime( $attrs['show_until'] ) < $now ) {
			return false;
		}
		return (bool) apply_filters( 'brik/visible', true, $attrs );
	}

	private function wrap( array $node, array $def, array $attrs, $inner, Context $ctx ) {
		$tag = is_callable( $def['tag'] ) ? call_user_func( $def['tag'], $attrs ) : $def['tag'];
		$tag = tag_escape( $tag ? $tag : 'div' );

		$classes = array( 'brik-el', 'brik-' . $node['type'], 'brik-n-' . $node['id'] );
		if ( ! empty( $def['class'] ) ) {
			$classes[] = is_callable( $def['class'] ) ? call_user_func( $def['class'], $attrs ) : $def['class'];
		}
		if ( ! empty( $attrs['css_class'] ) ) {
			$classes[] = $attrs['css_class'];
		}
		// Global CSS classes are referenced by id, so renaming a class never breaks its usages.
		if ( ! empty( $attrs['classes'] ) && is_array( $attrs['classes'] ) ) {
			$defined = (array) Settings::get( 'classes' );
			foreach ( $attrs['classes'] as $class_id ) {
				if ( is_string( $class_id ) && ! empty( $defined[ $class_id ]['name'] ) ) {
					$classes[] = 'cls-' . $defined[ $class_id ]['name'];
				}
			}
		}
		$classes = array_merge( $classes, $ctx->classes );

		$html_attrs = $ctx->attrs;
		$html_attrs['class'] = brik_cls( $classes );
		if ( ! empty( $attrs['css_id'] ) ) {
			$html_attrs['id'] = sanitize_html_class( $attrs['css_id'] );
		}
		if ( ! empty( $attrs['animation'] ) ) {
			$html_attrs['data-brik-anim'] = $attrs['animation'];
		}
		if ( $this->canvas ) {
			$html_attrs['data-brik-id']   = $node['id'];
			$html_attrs['data-brik-type'] = $node['type'];
		}

		$link = '';
		if ( ! empty( $attrs['el_link']['url'] ) && ! $this->canvas ) {
			$link = sprintf( '<a class="brik-el-link" href="%s"%s aria-hidden="true" tabindex="-1"></a>', esc_url( $attrs['el_link']['url'] ), ! empty( $attrs['el_link']['new_tab'] ) ? ' target="_blank" rel="noopener"' : '' );
			$html_attrs['class'] .= ' brik-has-link';
		}

		return '<' . $tag . brik_attrs( $html_attrs ) . '>' . $inner . $link . '</' . $tag . '>';
	}

	private function missing( array $node ) {
		return sprintf(
			'<div class="brik-el brik-missing" data-brik-id="%s" data-brik-type="%s">%s</div>',
			esc_attr( isset( $node['id'] ) ? $node['id'] : '' ),
			esc_attr( $node['type'] ),
			/* translators: %s: module type */
			esc_html( sprintf( __( 'Unknown module "%s"', 'brik-builder' ), $node['type'] ) )
		);
	}

	/**
	 * Previous (-1) or next (1) top-level section of a node, or null.
	 */
	public function neighbour( $id, $offset ) {
		foreach ( $this->root as $i => $node ) {
			if ( isset( $node['id'] ) && $node['id'] === $id ) {
				$j = $i + $offset;
				return isset( $this->root[ $j ] ) && is_array( $this->root[ $j ] ) ? $this->root[ $j ] : null;
			}
		}
		return null;
	}

	/**
	 * Guard against global modules or templates including themselves.
	 */
	public function enter( $key ) {
		if ( isset( $this->depth_guard[ $key ] ) ) {
			return false;
		}
		$this->depth_guard[ $key ] = true;
		return true;
	}

	public function leave( $key ) {
		unset( $this->depth_guard[ $key ] );
	}
}
