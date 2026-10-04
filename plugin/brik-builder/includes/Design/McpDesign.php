<?php
namespace Brik\Design;

use Brik\Builder;
use Brik\Data;
use Brik\Library;
use Brik\McpTools;
use Brik\Plugin;
use Brik\Settings;
use Brik\ThemeBuilder;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * MCP tools for classes, variables and components.
 */
final class McpDesign {

	public static function tools( array $d ) {
		$post_id = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		$node_id = array(
			'type'        => 'string',
			'description' => 'Node id from get_page.',
		);

		$d['list_classes'] = array(
			'title'       => 'List CSS classes',
			'description' => 'Global CSS classes of the design system with their design attrs and how many elements use them. Apply with apply_class or by adding the class id to a node\'s attrs.classes list.',
			'read_only'   => true,
			'props'       => array(),
			'callback'    => array( __CLASS__, 'list_classes' ),
		);
		$d['save_class'] = array(
			'title'       => 'Save a CSS class',
			'description' => 'Creates or updates a global CSS class. attrs uses the same keys as an element\'s Design tab (padding, bg_color, radius, font_size, shadow… with @tablet/@mobile/@hover variants); give "type" (e.g. button) to also use that module\'s own design fields such as button_bg. The class prints as .cls-{name}; element settings still win over it. Values can use variables: "var(--space-md)".',
			'props'       => array(
				'id'    => array(
					'type'        => 'string',
					'description' => 'Existing class id or name to update; omit to create.',
				),
				'name'  => array(
					'type'        => 'string',
					'description' => 'CSS-safe name, e.g. "button-primary" (a-z, 0-9, -, _).',
				),
				'label' => array( 'type' => 'string' ),
				'type'  => array(
					'type'        => 'string',
					'description' => 'Module type whose design fields the class may use (optional).',
				),
				'attrs' => array( 'type' => 'object' ),
				'merge' => array(
					'type'        => 'boolean',
					'description' => 'Merge attrs into the existing ones instead of replacing them (default false).',
				),
			),
			'callback'    => array( __CLASS__, 'save_class' ),
		);
		$d['delete_class'] = array(
			'title'       => 'Delete a CSS class',
			'description' => 'Deletes a global CSS class and removes it from every element that uses it.',
			'destructive' => true,
			'props'       => array(
				'class' => array(
					'type'        => 'string',
					'description' => 'Class id or name.',
				),
			),
			'required'    => array( 'class' ),
			'callback'    => array( __CLASS__, 'delete_class' ),
		);
		$d['apply_class'] = array(
			'title'       => 'Apply a CSS class to a node',
			'description' => 'Adds (or with remove: true, removes) a global CSS class on an element.',
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => $node_id,
				'class'   => array(
					'type'        => 'string',
					'description' => 'Class id or name.',
				),
				'remove'  => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'post_id', 'node_id', 'class' ),
			'callback'    => array( __CLASS__, 'apply_class' ),
		);
		$d['list_variables'] = array(
			'title'       => 'List design variables',
			'description' => 'Spacing, radius, type and shadow scales plus custom variables, as CSS custom properties. Use them in any style value: "var(--space-lg)", "var(--radius-md)", "var(--text-2xl)", "var(--shadow-md)". Theme colors are var(--primary) etc.',
			'read_only'   => true,
			'props'       => array(),
			'callback'    => array( __CLASS__, 'list_variables' ),
		);
		$d['save_variables'] = array(
			'title'       => 'Save design variables',
			'description' => 'Changes scale steps and custom variables. space/radius/text/shadow: { step: value } (e.g. { "md": "1.75rem" }; empty string resets). fluid: true makes large type sizes scale with the viewport. headings: { h1: { size: "var(--text-5xl)", weight: "700" } }. custom: [ { name, value, group } ] adds or updates by name; remove: [names] deletes custom variables.',
			'props'       => array(
				'space'    => array( 'type' => 'object' ),
				'radius'   => array( 'type' => 'object' ),
				'text'     => array( 'type' => 'object' ),
				'shadow'   => array( 'type' => 'object' ),
				'fluid'    => array( 'type' => 'boolean' ),
				'headings' => array( 'type' => 'object' ),
				'custom'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'name'  => array( 'type' => 'string' ),
							'value' => array( 'type' => 'string' ),
							'group' => array(
								'type' => 'string',
								'enum' => array_keys( Variables::groups() ),
							),
						),
					),
				),
				'remove'   => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'callback'    => array( __CLASS__, 'save_variables' ),
		);
		$d['create_component'] = array(
			'title'       => 'Create a component',
			'description' => 'Saves a reusable component (a master tree). Either pass "tree", or post_id + node_id to turn an existing element into a component (the element is replaced by an instance). Place instances with { "type": "global", "attrs": { "ref": ID } }. Content fields of inner elements are overridable per instance (set_component_overrides); design is locked to the master.',
			'props'       => array(
				'title'   => array( 'type' => 'string' ),
				'tree'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
				'post_id' => $post_id,
				'node_id' => $node_id,
			),
			'required'    => array( 'title' ),
			'callback'    => array( __CLASS__, 'create_component' ),
		);
		$d['list_components'] = array(
			'title'       => 'List components',
			'description' => 'Components with their instance counts and the overridable fields of each inner element (node ids to use in set_component_overrides).',
			'read_only'   => true,
			'props'       => array(),
			'callback'    => array( __CLASS__, 'list_components' ),
		);
		$d['set_component_overrides'] = array(
			'title'       => 'Set component overrides',
			'description' => 'Sets per-instance values on a component instance: overrides = { "<inner node id>": { "text": "Buy now" } }. Locked fields are ignored with a warning. A null value resets that field to the master. replace: true drops all existing overrides first.',
			'props'       => array(
				'post_id'   => $post_id,
				'node_id'   => array(
					'type'        => 'string',
					'description' => 'Id of the instance (global) node.',
				),
				'overrides' => array( 'type' => 'object' ),
				'replace'   => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'post_id', 'node_id', 'overrides' ),
			'callback'    => array( __CLASS__, 'set_component_overrides' ),
		);
		$d['detach_component'] = array(
			'title'       => 'Detach a component instance',
			'description' => 'Replaces an instance with an independent copy of the master (overrides applied, new node ids). Later master changes no longer affect it.',
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => $node_id,
			),
			'required'    => array( 'post_id', 'node_id' ),
			'callback'    => array( __CLASS__, 'detach_component' ),
		);
		return $d;
	}

	public static function guide( $text ) {
		return $text . '

## Design system: variables, classes, components

* Variables (list_variables / save_variables): `var(--space-3xs…3xl)`, `var(--radius-sm|md|lg|xl|full)`, `var(--text-xs…7xl)` (optionally fluid), `var(--shadow-xs…2xl)` and custom ones. Prefer them over raw values in padding, margin, gap, radius, font sizes and shadows so the site stays consistent.
* CSS classes (list_classes / save_class / apply_class): shared design attrs printed as `.cls-{name}`. Elements list class ids in `attrs.classes`. Element design attrs override the class, so only set on an element what differs.
* Components (create_component / list_components / set_component_overrides / detach_component): a master tree reused as `{ "type": "global", "attrs": { "ref": ID, "overrides": { "<inner node id>": { "text": "…" } } } }`. Content fields are overridable, design is locked; editing the master updates every instance.
';
	}

	/* ---------------------------------------------------------------------
	 * Helpers.
	 * ------------------------------------------------------------------- */

	private static function can_theme() {
		return current_user_can( 'edit_theme_options' ) ? true : new WP_Error( 'brik_forbidden', __( 'Changing the design system requires the edit_theme_options capability.', 'brik-builder' ) );
	}

	private static function editable( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No post with id %d.', 'brik-builder' ), (int) $id ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) || ( ThemeBuilder::is_template( $post->ID ) && ! current_user_can( 'edit_theme_options' ) ) ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_forbidden', sprintf( __( 'You are not allowed to edit post %d.', 'brik-builder' ), $post->ID ) );
		}
		if ( ! Plugin::supports( $post ) ) {
			return new WP_Error( 'brik_unsupported', __( 'Brik is not enabled for this post type.', 'brik-builder' ) );
		}
		return $post;
	}

	/** [ node, parent type ] of a node in a tree. */
	private static function locate( array $nodes, $id, $parent = null ) {
		foreach ( $nodes as $node ) {
			if ( isset( $node['id'] ) && $node['id'] === $id ) {
				return array( $node, $parent );
			}
			if ( ! empty( $node['children'] ) ) {
				$found = self::locate( $node['children'], $id, $node['type'] );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	/** Replace one node with a list of nodes. */
	private static function splice( array $nodes, $id, array $with ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['id'] ) && $node['id'] === $id ) {
				$out = array_merge( $out, $with );
				continue;
			}
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = self::splice( $node['children'], $id, $with );
			}
			$out[] = $node;
		}
		return $out;
	}

	private static function instance( $post, $node_id ) {
		$tree  = Data::get( $post->ID );
		$found = self::locate( $tree, (string) $node_id );
		if ( ! $found ) {
			return new WP_Error( 'brik_not_found', __( 'No node with that id.', 'brik-builder' ) );
		}
		list( $node, $parent ) = $found;
		if ( 'global' !== $node['type'] || empty( $node['attrs']['ref'] ) || ! Components::is_component( (int) $node['attrs']['ref'] ) ) {
			return new WP_Error( 'brik_invalid', __( 'That node is not a component instance.', 'brik-builder' ) );
		}
		return array( $tree, $node, $parent );
	}

	/* ---------------------------------------------------------------------
	 * Tools.
	 * ------------------------------------------------------------------- */

	public static function list_classes() {
		return array( 'classes' => Classes::describe( Usage::scan()['classes'] ) );
	}

	public static function save_class( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id    = ! empty( $a['id'] ) ? Classes::find( $a['id'] ) : null;
		$class = $id ? Classes::get( $id ) : array( 'attrs' => array() );
		if ( ! empty( $a['id'] ) && ! $id ) {
			McpTools::warn( __( 'No class with that id or name; a new class was created.', 'brik-builder' ) );
		}
		if ( ! $id && empty( $a['name'] ) && empty( $a['label'] ) ) {
			return new WP_Error( 'brik_mcp_args', __( 'Give a name for the new class.', 'brik-builder' ) );
		}
		$data = array();
		foreach ( array( 'name', 'label', 'type' ) as $key ) {
			if ( isset( $a[ $key ] ) ) {
				$data[ $key ] = $a[ $key ];
			}
		}
		if ( isset( $a['attrs'] ) ) {
			$data['attrs'] = ! empty( $a['merge'] ) ? array_merge( (array) $class['attrs'], (array) $a['attrs'] ) : (array) $a['attrs'];
			$fields        = Classes::fields( isset( $data['type'] ) ? $data['type'] : ( isset( $class['type'] ) ? $class['type'] : '' ) );
			foreach ( array_keys( (array) $a['attrs'] ) as $key ) {
				if ( ! isset( $fields[ strtok( $key, '@' ) ] ) ) {
					/* translators: %s: attribute key */
					McpTools::warn( sprintf( __( '"%s" is not a design field and was dropped. Use get_module for design keys, or set "type".', 'brik-builder' ), $key ) );
				}
			}
		}
		$saved_id = Classes::upsert( $id ? $id : Data::id(), $data );
		if ( ! $saved_id ) {
			return new WP_Error( 'brik_invalid', __( 'Class names may only contain a-z, 0-9, - and _.', 'brik-builder' ) );
		}
		$saved = Classes::get( $saved_id );
		return array(
			'id'    => $saved_id,
			'class' => Classes::PREFIX . $saved['name'],
			'saved' => $saved,
			'usage' => array( 'attrs' => array( 'classes' => array( $saved_id ) ) ),
		);
	}

	public static function delete_class( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$id = Classes::find( $a['class'] );
		if ( ! $id ) {
			return new WP_Error( 'brik_not_found', __( 'No such class.', 'brik-builder' ) );
		}
		$usage = Usage::scan()['classes'];
		Classes::delete( $id );
		return array(
			'deleted'          => true,
			'id'               => $id,
			'removed_from'     => isset( $usage[ $id ] ) ? $usage[ $id ] : 0,
		);
	}

	public static function apply_class( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id = Classes::find( $a['class'] );
		if ( ! $id ) {
			return new WP_Error( 'brik_not_found', __( 'No such class. See list_classes.', 'brik-builder' ) );
		}
		$tree = Classes::toggle_on_node( Data::get( $post->ID ), (string) $a['node_id'], $id, empty( $a['remove'] ) );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$tree  = Data::save( $post->ID, $tree );
		$found = self::locate( $tree, (string) $a['node_id'] );
		return array(
			'post_id' => $post->ID,
			'node_id' => $a['node_id'],
			'classes' => $found && isset( $found[0]['attrs']['classes'] ) ? $found[0]['attrs']['classes'] : array(),
		);
	}

	public static function list_variables() {
		return array(
			'variables' => Variables::resolved(),
			'fluid'     => ! empty( Variables::saved()['fluid'] ),
			'headings'  => isset( Variables::saved()['headings'] ) ? Variables::saved()['headings'] : new \stdClass(),
			'colors'    => array_map(
				static function ( $name ) {
					return 'var(--' . $name . ')';
				},
				Settings::token_names()
			),
			'groups'    => Variables::groups(),
		);
	}

	public static function save_variables( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$saved = Variables::saved();
		foreach ( array( 'space', 'radius', 'text', 'shadow' ) as $group ) {
			if ( isset( $a[ $group ] ) ) {
				$steps = Variables::scales()[ $group ]['steps'];
				foreach ( (array) $a[ $group ] as $step => $value ) {
					if ( ! isset( $steps[ $step ] ) ) {
						/* translators: 1: step, 2: scale */
						McpTools::warn( sprintf( __( 'Unknown step "%1$s" in %2$s; use custom variables for new names.', 'brik-builder' ), $step, $group ) );
						continue;
					}
					$saved[ $group ][ $step ] = (string) $value;
				}
			}
		}
		if ( isset( $a['fluid'] ) ) {
			$saved['fluid'] = (bool) $a['fluid'];
		}
		if ( isset( $a['headings'] ) ) {
			$saved['headings'] = array_merge( isset( $saved['headings'] ) ? (array) $saved['headings'] : array(), (array) $a['headings'] );
		}
		$custom = array();
		foreach ( isset( $saved['custom'] ) ? (array) $saved['custom'] : array() as $var ) {
			$custom[ $var['name'] ] = $var;
		}
		foreach ( isset( $a['custom'] ) ? (array) $a['custom'] : array() as $var ) {
			$name = Variables::sanitize_name( isset( $var['name'] ) ? $var['name'] : '' );
			if ( '' !== $name ) {
				$custom[ $name ] = array_merge( isset( $custom[ $name ] ) ? $custom[ $name ] : array( 'group' => 'other' ), (array) $var, array( 'name' => $name ) );
			}
		}
		foreach ( isset( $a['remove'] ) ? (array) $a['remove'] : array() as $name ) {
			unset( $custom[ Variables::sanitize_name( $name ) ] );
		}
		$saved['custom'] = array_values( $custom );
		Settings::update( array( 'variables' => $saved ) );
		return self::list_variables();
	}

	public static function create_component( array $a ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Creating components requires the edit_pages capability.', 'brik-builder' ) );
		}
		$post = null;
		if ( ! empty( $a['post_id'] ) || ! empty( $a['node_id'] ) ) {
			if ( empty( $a['post_id'] ) || empty( $a['node_id'] ) ) {
				return new WP_Error( 'brik_mcp_args', __( 'Pass both post_id and node_id, or a tree.', 'brik-builder' ) );
			}
			$post = self::editable( $a['post_id'] );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
			$found = self::locate( Data::get( $post->ID ), (string) $a['node_id'] );
			if ( ! $found ) {
				return new WP_Error( 'brik_not_found', __( 'No node with that id.', 'brik-builder' ) );
			}
			$nodes = array( $found[0] );
		} else {
			$nodes = isset( $a['tree'] ) ? (array) $a['tree'] : array();
		}
		$id = Components::create( $a['title'], $nodes );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$out = array(
			'id'          => $id,
			'title'       => get_the_title( $id ),
			'builder_url' => Builder::url( $id ),
			'embed'       => array(
				'type'  => 'global',
				'attrs' => array( 'ref' => $id ),
			),
			'fields'      => Components::fields( $id ),
		);
		if ( $post ) {
			$instance = array(
				'id'    => Data::id(),
				'type'  => 'global',
				'attrs' => array( 'ref' => $id ),
			);
			Data::save( $post->ID, self::splice( Data::get( $post->ID ), (string) $a['node_id'], array( $instance ) ) );
			$out['instance_id'] = $instance['id'];
		}
		return $out;
	}

	public static function list_components() {
		$items = Components::items();
		foreach ( $items as &$item ) {
			$item['fields'] = Components::fields( $item['id'] );
		}
		return array( 'components' => $items );
	}

	public static function set_component_overrides( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$found = self::instance( $post, $a['node_id'] );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		list( $tree, $node ) = $found;
		$ref     = (int) $node['attrs']['ref'];
		$policy  = Components::policy( $ref );
		$masters = array();
		Usage::walk(
			Data::get( $ref ),
			static function ( $n ) use ( &$masters ) {
				$masters[ $n['id'] ] = $n;
			}
		);
		$current = ! empty( $a['replace'] ) || empty( $node['attrs']['overrides'] ) ? array() : (array) $node['attrs']['overrides'];
		foreach ( (array) $a['overrides'] as $inner => $attrs ) {
			if ( ! isset( $masters[ $inner ] ) ) {
				/* translators: %s: node id */
				McpTools::warn( sprintf( __( 'No element "%s" inside this component. See list_components.', 'brik-builder' ), $inner ) );
				continue;
			}
			foreach ( (array) $attrs as $key => $value ) {
				if ( null === $value ) {
					unset( $current[ $inner ][ $key ] );
					continue;
				}
				if ( ! Components::allowed( $policy, $masters[ $inner ], $key ) ) {
					/* translators: 1: field key, 2: node id */
					McpTools::warn( sprintf( __( '"%1$s" on %2$s is locked to the master and was ignored.', 'brik-builder' ), $key, $inner ) );
					continue;
				}
				$current[ $inner ][ $key ] = $value;
			}
			if ( empty( $current[ $inner ] ) ) {
				unset( $current[ $inner ] );
			}
		}
		$node['attrs']['overrides'] = Components::clean_overrides( $current );
		if ( ! $node['attrs']['overrides'] ) {
			unset( $node['attrs']['overrides'] );
		}
		Data::save( $post->ID, self::splice( $tree, $node['id'], array( $node ) ) );
		return array(
			'post_id'   => $post->ID,
			'node_id'   => $node['id'],
			'overrides' => isset( $node['attrs']['overrides'] ) ? $node['attrs']['overrides'] : new \stdClass(),
		);
	}

	public static function detach_component( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$found = self::instance( $post, $a['node_id'] );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		list( $tree, $node, $parent ) = $found;
		$nodes = Components::resolve(
			(int) $node['attrs']['ref'],
			isset( $node['attrs']['overrides'] ) ? (array) $node['attrs']['overrides'] : array(),
			'column' === $parent ? 'column' : 'root',
			true
		);
		Data::save( $post->ID, self::splice( $tree, $node['id'], $nodes ) );
		return array(
			'post_id' => $post->ID,
			'nodes'   => array_map(
				static function ( $n ) {
					return array(
						'id'   => $n['id'],
						'type' => $n['type'],
					);
				},
				$nodes
			),
		);
	}
}
