<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Storage, normalization and sanitization of Brik trees.
 */
final class Data {

	const META         = '_brik_data';
	const META_ENABLED = '_brik_enabled';
	const META_PAGE    = '_brik_page';

	public static function id() {
		return substr( md5( uniqid( '', true ) . wp_rand() ), 0, 8 );
	}

	public static function enabled( $post_id ) {
		return $post_id && (bool) get_post_meta( $post_id, self::META_ENABLED, true );
	}

	public static function get( $post_id ) {
		$raw = get_post_meta( $post_id, self::META, true );
		if ( ! $raw ) {
			return array();
		}
		$data = is_array( $raw ) ? $raw : json_decode( $raw, true );
		return is_array( $data ) ? $data : array();
	}

	public static function page_settings( $post_id ) {
		$s = get_post_meta( $post_id, self::META_PAGE, true );
		return is_array( $s ) ? $s : array();
	}

	/**
	 * Save a tree for a post. Returns the normalized tree.
	 */
	public static function save( $post_id, $nodes, $page = null ) {
		$nodes = self::sanitize( self::normalize( $nodes ) );

		update_post_meta( $post_id, self::META, wp_slash( wp_json_encode( $nodes ) ) );
		update_post_meta( $post_id, self::META_ENABLED, 1 );

		if ( is_array( $page ) ) {
			$clean = array(
				'custom_css' => isset( $page['custom_css'] ) ? str_ireplace( '</style', '', (string) $page['custom_css'] ) : '',
				'body_class' => isset( $page['body_class'] ) ? sanitize_text_field( $page['body_class'] ) : '',
				'dark'       => ! empty( $page['dark'] ),
				'hide_title' => ! empty( $page['hide_title'] ),
			);
			update_post_meta( $post_id, self::META_PAGE, $clean );
		}

		self::snapshot( $post_id, $nodes );
		do_action( 'brik/saved', $post_id, $nodes );
		return $nodes;
	}

	/**
	 * Store a static HTML copy in post_content so content survives without the plugin
	 * and search/excerpts keep working.
	 */
	private static function snapshot( $post_id, array $nodes ) {
		$post = get_post( $post_id );
		if ( ! $post || in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return;
		}
		$renderer = new Renderer( $post_id );
		$html     = $renderer->render_root( $nodes );
		$html     = preg_replace( '/\s+data-brik-[a-z]+="[^"]*"/', '', $html );

		remove_action( 'post_updated', 'wp_save_post_revision' );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( "<!-- brik -->\n" . $html . "\n<!-- /brik -->" ),
			)
		);
		add_action( 'post_updated', 'wp_save_post_revision' );
	}

	/* ---------------------------------------------------------------------
	 * Normalization: fills ids, repairs structure.
	 * ------------------------------------------------------------------- */

	/**
	 * Repair a tree so it always follows section > row > column > module.
	 * Lone modules are wrapped automatically, which keeps hand-written and AI-written trees valid.
	 */
	public static function normalize( $nodes, $parent = null, array &$seen = array() ) {
		if ( ! is_array( $nodes ) ) {
			return array();
		}
		if ( isset( $nodes['type'] ) ) {
			$nodes = array( $nodes );
		}

		$out     = array();
		$pending = array();
		$flush   = static function () use ( &$pending, &$out, $parent ) {
			if ( ! $pending ) {
				return;
			}
			if ( null === $parent ) {
				$out[] = array( 'type' => 'section', 'children' => array( array( 'type' => 'row', 'children' => array( array( 'type' => 'column', 'children' => $pending ) ) ) ) );
			} elseif ( 'section' === $parent ) {
				$out[] = array( 'type' => 'row', 'children' => array( array( 'type' => 'column', 'children' => $pending ) ) );
			} elseif ( 'row' === $parent ) {
				$out[] = array( 'type' => 'column', 'children' => $pending );
			} elseif ( 'column' === $parent ) {
				// Columns placed straight in a column become a nested row; sections give up their rows.
				$columns = array();
				foreach ( $pending as $item ) {
					if ( 'column' === $item['type'] ) {
						$columns[] = $item;
						continue;
					}
					if ( $columns ) {
						$out[]   = array( 'type' => 'row', 'attrs' => array( 'columns' => (string) count( $columns ) ), 'children' => $columns );
						$columns = array();
					}
					if ( 'section' === $item['type'] && ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
						foreach ( $item['children'] as $child ) {
							$out[] = $child;
						}
					}
				}
				if ( $columns ) {
					$out[] = array( 'type' => 'row', 'attrs' => array( 'columns' => (string) count( $columns ) ), 'children' => $columns );
				}
			}
			$pending = array();
		};

		foreach ( array_values( $nodes ) as $node ) {
			if ( is_object( $node ) ) {
				$node = json_decode( wp_json_encode( $node ), true );
			}
			if ( ! is_array( $node ) || empty( $node['type'] ) || ! is_string( $node['type'] ) ) {
				continue;
			}
			$type = sanitize_key( $node['type'] );

			$fits = ( null === $parent && 'section' === $type )
				|| ( 'section' === $parent && 'row' === $type )
				|| ( 'row' === $parent && 'column' === $type )
				|| ( 'column' === $parent && ! in_array( $type, array( 'section', 'column' ), true ) )
				|| ( null !== $parent && ! in_array( $parent, array( 'section', 'row', 'column' ), true ) );

			// A global element can sit anywhere a module can.
			if ( 'global' === $type && in_array( $parent, array( null, 'section' ), true ) ) {
				$fits = true;
			}

			if ( ! $fits ) {
				$pending[] = $node;
				continue;
			}
			$flush();
			$out[] = $node;
		}
		$flush();

		foreach ( $out as &$node ) {
			$node['type'] = sanitize_key( $node['type'] );
			$id           = isset( $node['id'] ) ? strtolower( (string) $node['id'] ) : '';
			if ( ! preg_match( '/^[a-z0-9]{4,24}$/', $id ) || isset( $seen[ $id ] ) ) {
				$id = self::id();
			}
			$seen[ $id ] = true;
			$node['id']  = $id;

			$node['attrs'] = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
			if ( is_object( $node['attrs'] ) || ( $node['attrs'] && array_keys( $node['attrs'] ) === range( 0, count( $node['attrs'] ) - 1 ) ) ) {
				$node['attrs'] = array();
			}

			$def      = Modules::get( $node['type'] );
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
			if ( $def && $def['children'] ) {
				$node['children'] = self::normalize( $children, $node['type'], $seen );
			} else {
				unset( $node['children'] );
			}
			if ( 'row' === $node['type'] && empty( $node['children'] ) ) {
				$node['children'] = array( array( 'id' => self::id(), 'type' => 'column', 'attrs' => array(), 'children' => array() ) );
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Sanitization.
	 * ------------------------------------------------------------------- */

	public static function sanitize( array $nodes ) {
		$trusted = current_user_can( 'unfiltered_html' );
		foreach ( $nodes as &$node ) {
			$def           = Modules::get( $node['type'] );
			$fields        = $def ? $def['fields'] : array();
			$node['attrs'] = self::sanitize_attrs( $node['attrs'], $fields, $trusted );
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = self::sanitize( $node['children'] );
			}
		}
		return $nodes;
	}

	public static function sanitize_attrs( array $attrs, array $fields, $trusted ) {
		$out = array();
		foreach ( $attrs as $key => $value ) {
			$key  = preg_replace( '/[^a-z0-9_@\-]/i', '', (string) $key );
			$base = strtok( $key, '@' );
			if ( '' === $key || null === $value ) {
				continue;
			}
			$field     = isset( $fields[ $base ] ) ? $fields[ $base ] : array( 'type' => 'unknown' );
			$out[ $key ] = self::sanitize_value( $value, $field, $trusted );
		}
		return $out;
	}

	private static function sanitize_value( $value, array $field, $trusted ) {
		$type = $field['type'];

		if ( 'repeater' === $type ) {
			$items = array();
			foreach ( (array) $value as $item ) {
				if ( is_array( $item ) ) {
					$items[] = self::sanitize_attrs( $item, isset( $field['fields'] ) ? $field['fields'] : array(), $trusted );
				}
			}
			return $items;
		}

		if ( is_array( $value ) ) {
			array_walk_recursive(
				$value,
				static function ( &$v, $k ) use ( $trusted ) {
					if ( is_string( $v ) ) {
						if ( 'url' === $k ) {
							// Keep dynamic tags such as {post_url}; they become URLs when rendered.
							$v = preg_match( '/^\{[a-z_]+(:[A-Za-z0-9_.\-]+)?(\|[a-z_]+)?\}/', $v ) ? sanitize_text_field( $v ) : esc_url_raw( $v );
						} else {
							$v = $trusted ? $v : wp_kses_post( $v );
						}
					}
				}
			);
			return $value;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		$value = (string) $value;
		if ( $trusted ) {
			return $value;
		}

		if ( isset( $field['language'] ) && 'css' === $field['language'] ) {
			return str_ireplace( '</', '', $value );
		}

		switch ( $type ) {
			case 'richtext':
			case 'code':
				return wp_kses_post( $value );
			case 'text':
			case 'textarea':
				return brik_inline( $value );
			default:
				return wp_strip_all_tags( $value );
		}
	}

	/* ---------------------------------------------------------------------
	 * Tree operations (used by the MCP server).
	 * ------------------------------------------------------------------- */

	public static function &find( array &$nodes, $id ) {
		$null = null;
		foreach ( $nodes as &$node ) {
			if ( isset( $node['id'] ) && $node['id'] === $id ) {
				return $node;
			}
			if ( ! empty( $node['children'] ) ) {
				$found = &self::find( $node['children'], $id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}
		return $null;
	}

	public static function remove( array $nodes, $id, &$removed = null ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( $node['id'] === $id ) {
				$removed = $node;
				continue;
			}
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = self::remove( $node['children'], $id, $removed );
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * Insert nodes under a parent (null = root) at an index (-1 = end).
	 */
	public static function insert( array $nodes, $parent_id, array $new, $index = -1 ) {
		if ( null === $parent_id || '' === $parent_id ) {
			$index = $index < 0 ? count( $nodes ) : $index;
			array_splice( $nodes, $index, 0, $new );
			return $nodes;
		}
		$parent = &self::find( $nodes, $parent_id );
		if ( null === $parent ) {
			return null;
		}
		$children = isset( $parent['children'] ) ? $parent['children'] : array();
		$index    = $index < 0 ? count( $children ) : $index;
		array_splice( $children, $index, 0, $new );
		$parent['children'] = $children;
		return $nodes;
	}

	/**
	 * Every node in a tree, depth first, with its parent id.
	 */
	public static function flatten( array $nodes, $parent = null, array &$out = array() ) {
		foreach ( $nodes as $node ) {
			$out[] = array(
				'id'     => $node['id'],
				'type'   => $node['type'],
				'parent' => $parent,
			);
			if ( ! empty( $node['children'] ) ) {
				self::flatten( $node['children'], $node['id'], $out );
			}
		}
		return $out;
	}
}
