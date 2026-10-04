<?php
namespace Brik\Design;

use Brik\Data;
use Brik\Library;
use Brik\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Components: library items of kind "component" with a master tree.
 *
 * An instance is a `global` node: { type: "global", attrs: { ref: ID, overrides: { nodeId: { key: value } } } }.
 * Each component has an override policy (post meta) of explicit per-field choices:
 * { nodeId: { fieldKey: true|false } }. Fields without a choice follow the default: content
 * fields of inner elements are overridable, design and advanced fields are locked.
 */
final class Components {

	const META_POLICY = '_brik_component_policy';

	public static function is_component( $id ) {
		return (bool) Library::item( $id ) && 'component' === get_post_meta( (int) $id, Library::META_KIND, true );
	}

	/**
	 * Save nodes as a new component. Returns the library item id or WP_Error.
	 */
	public static function create( $title, array $nodes ) {
		if ( ! $nodes ) {
			return new \WP_Error( 'brik_invalid', __( 'A component needs at least one element.', 'brik-builder' ) );
		}
		// Columns can't stand alone in a stored tree; give them a row.
		if ( 1 === count( $nodes ) && isset( $nodes[0]['type'] ) && 'column' === $nodes[0]['type'] ) {
			$nodes = array(
				array(
					'type'     => 'row',
					'children' => $nodes,
				),
			);
		}
		// A nested instance may sit at the root of a stored tree, but then it can't be unwrapped
		// into a column later; store it in the usual section > row > column shape.
		foreach ( $nodes as $i => $node ) {
			if ( isset( $node['type'] ) && 'global' === $node['type'] ) {
				$nodes[ $i ] = array(
					'type'     => 'section',
					'children' => array(
						array(
							'type'     => 'row',
							'children' => array(
								array(
									'type'     => 'column',
									'children' => array( $node ),
								),
							),
						),
					),
				);
			}
		}
		return Library::create( '' !== trim( (string) $title ) ? $title : __( 'Component', 'brik-builder' ), 'component', $nodes, true );
	}

	public static function policy( $id ) {
		$policy = get_post_meta( (int) $id, self::META_POLICY, true );
		return is_array( $policy ) ? $policy : array();
	}

	public static function save_policy( $id, $policy ) {
		$clean = array();
		foreach ( is_array( $policy ) ? $policy : array() as $node_id => $fields ) {
			$node_id = strtolower( (string) $node_id );
			if ( ! preg_match( '/^[a-z0-9]{4,24}$/', $node_id ) || ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $key => $allowed ) {
				$key = preg_replace( '/[^a-z0-9_\-]/i', '', (string) $key );
				if ( '' !== $key && null !== $allowed ) {
					$clean[ $node_id ][ $key ] = (bool) $allowed;
				}
			}
		}
		update_post_meta( (int) $id, self::META_POLICY, $clean );
		return $clean;
	}

	/** Field types that change structure rather than content; never overridable by default. */
	private static function structural_field( $field ) {
		return in_array( $field['type'], array( 'columns', 'library' ), true );
	}

	/**
	 * Whether an attribute of a master node can be overridden by instances.
	 */
	public static function allowed( array $policy, array $node, $key ) {
		$base = strtok( (string) $key, '@' );
		$id   = isset( $node['id'] ) ? $node['id'] : '';
		if ( isset( $policy[ $id ][ $base ] ) ) {
			return (bool) $policy[ $id ][ $base ];
		}
		$def = isset( $node['type'] ) ? Modules::get( $node['type'] ) : null;
		if ( ! $def || ! isset( $def['fields'][ $base ] ) || in_array( $node['type'], array( 'section', 'row', 'column', 'global' ), true ) ) {
			return false;
		}
		$field = $def['fields'][ $base ];
		return 'content' === ( isset( $field['tab'] ) ? $field['tab'] : 'content' ) && ! self::structural_field( $field );
	}

	/**
	 * Merge an instance's overrides into master nodes. Locked fields are ignored.
	 * Overridden nodes get an id derived from the instance, so their generated CSS (some content
	 * fields write styles) doesn't leak into other instances that share the master ids.
	 */
	public static function apply_overrides( array $nodes, $ref, array $overrides, $instance_id = '' ) {
		if ( ! $overrides ) {
			return $nodes;
		}
		$policy = self::policy( $ref );
		return self::merge( $nodes, $overrides, $policy, (string) $instance_id );
	}

	private static function merge( array $nodes, array $overrides, array $policy, $instance_id ) {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) || empty( $node['id'] ) ) {
				continue;
			}
			if ( ! empty( $overrides[ $node['id'] ] ) && is_array( $overrides[ $node['id'] ] ) ) {
				$applied = false;
				foreach ( $overrides[ $node['id'] ] as $key => $value ) {
					if ( null === $value || ! self::allowed( $policy, $node, $key ) ) {
						continue;
					}
					$node['attrs'][ $key ] = $value;
					$applied               = true;
				}
				if ( $applied && '' !== $instance_id ) {
					$node['id'] = substr( md5( $instance_id . ':' . $node['id'] ), 0, 12 );
				}
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$node['children'] = self::merge( $node['children'], $overrides, $policy, $instance_id );
			}
			$nodes[ $i ] = $node;
		}
		return $nodes;
	}

	/**
	 * Master nodes with overrides applied, for where the instance sits ("root" or "column").
	 * $fresh gives every node a new id (a detached, independent copy).
	 */
	public static function resolve( $ref, array $overrides = array(), $context = 'root', $fresh = false ) {
		$nodes = Library::nodes_for( (int) $ref, 'column' === $context ? 'column' : 'root' );
		$nodes = self::merge( $nodes, $overrides, self::policy( $ref ), '' );
		return $fresh ? self::fresh_ids( $nodes ) : $nodes;
	}

	public static function fresh_ids( array $nodes ) {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$node['id'] = Data::id();
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$node['children'] = self::fresh_ids( $node['children'] );
			}
			$nodes[ $i ] = $node;
		}
		return $nodes;
	}

	/**
	 * Overridable (and locked) fields of a component's inner elements.
	 */
	public static function fields( $ref ) {
		$policy = self::policy( $ref );
		$out    = array();
		Usage::walk(
			Data::get( (int) $ref ),
			static function ( $node ) use ( $policy, &$out ) {
				$def = isset( $node['type'] ) ? Modules::get( $node['type'] ) : null;
				if ( ! $def || in_array( $node['type'], array( 'section', 'row', 'column' ), true ) ) {
					return;
				}
				$allowed = array();
				foreach ( $def['fields'] as $key => $field ) {
					if ( self::allowed( $policy, $node, $key ) ) {
						$allowed[ $key ] = array(
							'label' => $field['label'],
							'type'  => $field['type'],
							'value' => isset( $node['attrs'][ $key ] ) ? $node['attrs'][ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' ),
						);
					}
				}
				$out[] = array(
					'node_id'     => $node['id'],
					'type'        => $node['type'],
					'label'       => ! empty( $node['attrs']['admin_label'] ) ? $node['attrs']['admin_label'] : $def['title'],
					'overridable' => $allowed ? $allowed : new \stdClass(),
				);
			}
		);
		return $out;
	}

	public static function items( $usage = null ) {
		$usage = null === $usage ? Usage::scan()['components'] : $usage;
		$out   = array();
		foreach ( Library::items( 'component' ) as $item ) {
			$item['instances']   = isset( $usage[ $item['id'] ] ) ? $usage[ $item['id'] ]['count'] : 0;
			$item['used_on']     = isset( $usage[ $item['id'] ] ) ? $usage[ $item['id'] ]['posts'] : array();
			$item['builder_url'] = \Brik\Builder::url( $item['id'] );
			$item['embed']       = array(
				'type'  => 'global',
				'attrs' => array( 'ref' => $item['id'] ),
			);
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Clean an overrides map: { nodeId: { key: value } }.
	 */
	public static function clean_overrides( $overrides ) {
		$out = array();
		foreach ( is_array( $overrides ) ? $overrides : array() as $node_id => $attrs ) {
			$node_id = strtolower( (string) $node_id );
			if ( ! preg_match( '/^[a-z0-9]{4,24}$/', $node_id ) || ! is_array( $attrs ) ) {
				continue;
			}
			foreach ( $attrs as $key => $value ) {
				$key = preg_replace( '/[^a-z0-9_@\-]/i', '', (string) $key );
				if ( '' !== $key && null !== $value ) {
					$out[ $node_id ][ $key ] = $value;
				}
			}
		}
		return $out;
	}

	/**
	 * Standalone preview document of a component (used for thumbnails in the builder).
	 */
	public static function preview_html( $ref ) {
		$renderer = new \Brik\Renderer( 0, false );
		$html     = $renderer->render_root( Library::nodes_for( (int) $ref, 'root' ) );
		$css      = $renderer->style->css();
		$fonts    = \Brik\Fonts::url( array_merge( \Brik\Settings::fonts(), $renderer->style->fonts() ) );
		return '<!doctype html><html><head><meta charset="utf-8">'
			. '<link rel="stylesheet" href="' . esc_url( BRIK_URL . 'assets/build/frontend.css' ) . '">'
			. ( $fonts ? '<link rel="stylesheet" href="' . esc_url( $fonts ) . '">' : '' )
			. '<style>' . \Brik\Settings::css() . $css . 'body{margin:0;background:var(--background);color:var(--foreground);font-family:var(--brik-font-body,system-ui,sans-serif)}.brik-section{padding:24px!important}</style>'
			. '</head><body>' . $html . '</body></html>';
	}
}
