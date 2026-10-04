<?php
namespace Brik\Design;

use Brik\Data;
use Brik\Fields;
use Brik\Modules;
use Brik\Settings;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

/**
 * Global CSS classes.
 *
 * Settings "classes" = { id: { name, label, type, attrs } }. attrs uses the same keys as an
 * element's Design tab (with @tablet/@mobile/@hover variants). "type" is the module the class
 * was made from, so its own design fields (button_bg, title_font_size…) compile too.
 * Elements reference classes by id in attrs.classes and get "cls-{name}" on their wrapper.
 */
final class Classes {

	const PREFIX = 'cls-';

	private static $css_cache = array();

	public static function all() {
		$classes = Settings::get( 'classes' );
		return is_array( $classes ) ? $classes : array();
	}

	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/** Find a class by id or by name (with or without the cls- prefix). */
	public static function find( $ref ) {
		$ref = (string) $ref;
		$all = self::all();
		if ( isset( $all[ $ref ] ) ) {
			return $ref;
		}
		$name = self::sanitize_name( preg_replace( '/^\.?(cls-)?/', '', $ref ) );
		foreach ( $all as $id => $class ) {
			if ( $class['name'] === $name ) {
				return $id;
			}
		}
		return null;
	}

	/** Class names are strictly [a-z0-9-_] and never start with a digit or dash. */
	public static function sanitize_name( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '/[^a-z0-9_-]+/', '-', $name );
		$name = preg_replace( '/-{2,}/', '-', $name );
		$name = trim( substr( $name, 0, 60 ), '-_' );
		if ( '' !== $name && ! preg_match( '/^[a-z]/', $name ) ) {
			$name = 'c-' . $name;
		}
		return $name;
	}

	/**
	 * Design fields a class can style: the module's design fields, or the shared ones.
	 */
	public static function fields( $type = '' ) {
		$def    = $type ? Modules::get( $type ) : null;
		$fields = $def ? $def['fields'] : Fields::common();
		$out    = array();
		foreach ( $fields as $key => $field ) {
			if ( isset( $field['tab'] ) && 'design' === $field['tab'] ) {
				$out[ $key ] = $field;
			}
		}
		$common            = Fields::common();
		$out['custom_css'] = $common['custom_css'];
		return $out;
	}

	public static function sanitize( $value ) {
		$out     = array();
		$names   = array();
		$trusted = current_user_can( 'unfiltered_html' );
		foreach ( is_array( $value ) ? $value : array() as $id => $class ) {
			if ( ! is_array( $class ) ) {
				continue;
			}
			$id   = sanitize_key( $id );
			$name = self::sanitize_name( isset( $class['name'] ) ? $class['name'] : ( isset( $class['label'] ) ? $class['label'] : '' ) );
			if ( '' === $id || '' === $name ) {
				continue;
			}
			// Names stay unique: a clash gets a numeric suffix.
			$base = $name;
			for ( $i = 2; isset( $names[ $name ] ); $i++ ) {
				$name = $base . '-' . $i;
			}
			$names[ $name ] = true;

			$type   = isset( $class['type'] ) ? sanitize_key( $class['type'] ) : '';
			$type   = $type && Modules::get( $type ) ? $type : '';
			$fields = self::fields( $type );
			$attrs  = array();
			foreach ( isset( $class['attrs'] ) && is_array( $class['attrs'] ) ? $class['attrs'] : array() as $key => $v ) {
				$base_key = strtok( (string) $key, '@' );
				if ( isset( $fields[ $base_key ] ) && null !== $v && '' !== $v ) {
					$attrs[ $key ] = $v;
				}
			}
			$attrs = Data::sanitize_attrs( $attrs, $fields, $trusted );
			foreach ( $attrs as $key => $v ) {
				if ( is_string( $v ) && 'custom_css' !== strtok( $key, '@' ) ) {
					$attrs[ $key ] = Style::clean( $v );
				} elseif ( is_string( $v ) ) {
					$attrs[ $key ] = str_ireplace( '</style', '', $v );
				}
			}

			$out[ $id ] = array(
				'name'  => $name,
				'label' => sanitize_text_field( ! empty( $class['label'] ) ? $class['label'] : $name ),
				'type'  => $type,
				'attrs' => $attrs,
			);
		}
		return $out;
	}

	public static function selector( $name ) {
		// :where() drops .brik from the specificity, so per-element styles always win.
		return ':where(.brik) .' . self::PREFIX . $name;
	}

	/**
	 * Compile every class into one stylesheet.
	 */
	public static function css() {
		$classes = self::all();
		if ( ! $classes ) {
			return '';
		}
		$key = md5( wp_json_encode( $classes ) );
		if ( isset( self::$css_cache[ $key ] ) ) {
			return self::$css_cache[ $key ][0];
		}
		$css   = '';
		$fonts = array();
		foreach ( $classes as $class ) {
			list( $one, $used ) = self::compile( $class );
			$css  .= $one;
			$fonts = array_merge( $fonts, $used );
		}
		self::$css_cache[ $key ] = array( $css, array_values( array_unique( $fonts ) ) );
		return $css;
	}

	/** Fonts referenced by class styles (loaded with the page). */
	public static function fonts() {
		self::css();
		$key = md5( wp_json_encode( self::all() ) );
		return isset( self::$css_cache[ $key ] ) ? self::$css_cache[ $key ][1] : array();
	}

	/**
	 * CSS for one class: [ css, fonts ].
	 */
	public static function compile( array $class ) {
		if ( empty( $class['name'] ) ) {
			return array( '', array() );
		}
		$attrs = isset( $class['attrs'] ) ? (array) $class['attrs'] : array();
		$sel   = self::selector( $class['name'] );
		$style = new Style();
		$hover = $style->add_fields( $sel, self::fields( isset( $class['type'] ) ? $class['type'] : '' ), $attrs );
		if ( $hover || ! empty( $attrs['transition'] ) ) {
			$style->push( 'desktop', $sel, 'transition:all ' . ( ! empty( $attrs['transition'] ) ? (int) $attrs['transition'] : 300 ) . 'ms ease' );
		}
		$css = $style->css();
		if ( ! empty( $attrs['custom_css'] ) ) {
			$css .= Style::custom_css( $attrs['custom_css'], $sel );
		}
		return array( $css, $style->fonts() );
	}

	/* ---------------------------------------------------------------------
	 * Changes.
	 * ------------------------------------------------------------------- */

	public static function save_all( array $classes ) {
		Settings::update( array( 'classes' => $classes ) );
		return self::all();
	}

	/**
	 * Create or update a class. Returns the id.
	 */
	public static function upsert( $id, array $data ) {
		$all = self::all();
		$id  = sanitize_key( $id ? $id : Data::id() );
		$old = isset( $all[ $id ] ) ? $all[ $id ] : array( 'attrs' => array() );
		$all[ $id ] = array_merge( $old, array_intersect_key( $data, array_flip( array( 'name', 'label', 'type', 'attrs' ) ) ) );
		if ( empty( $all[ $id ]['name'] ) ) {
			$all[ $id ]['name'] = ! empty( $data['label'] ) ? $data['label'] : 'class-' . $id;
		}
		$old_name = isset( $old['name'] ) ? $old['name'] : '';
		self::save_all( $all );
		$saved = self::get( $id );
		if ( $saved && $old_name && $old_name !== $saved['name'] ) {
			self::rename_references( $old_name, $saved['name'] );
		}
		return $saved ? $id : null;
	}

	public static function duplicate( $id ) {
		$class = self::get( $id );
		if ( ! $class ) {
			return null;
		}
		$class['name']  .= '-copy';
		$class['label'] .= ' ' . __( '(copy)', 'brik-builder' );
		return self::upsert( Data::id(), $class );
	}

	/**
	 * Delete a class and drop it from every element that uses it.
	 */
	public static function delete( $id ) {
		$all = self::all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		self::save_all( $all );
		Usage::map_nodes(
			static function ( array $node ) use ( $id ) {
				if ( ! empty( $node['attrs']['classes'] ) && is_array( $node['attrs']['classes'] ) && in_array( $id, $node['attrs']['classes'], true ) ) {
					$node['attrs']['classes'] = array_values( array_diff( $node['attrs']['classes'], array( $id ) ) );
					if ( ! $node['attrs']['classes'] ) {
						unset( $node['attrs']['classes'] );
					}
				}
				return $node;
			}
		);
		return true;
	}

	/**
	 * Elements reference classes by id; this only rewrites hand-typed "cls-old" in the free-text
	 * CSS classes field so those keep working after a rename.
	 */
	public static function rename_references( $old, $new ) {
		$from = self::PREFIX . $old;
		$to   = self::PREFIX . $new;
		return Usage::map_nodes(
			static function ( array $node ) use ( $from, $to ) {
				if ( ! empty( $node['attrs']['css_class'] ) && is_string( $node['attrs']['css_class'] ) ) {
					$list = preg_split( '/\s+/', trim( $node['attrs']['css_class'] ) );
					if ( in_array( $from, $list, true ) ) {
						$node['attrs']['css_class'] = implode( ' ', str_replace( $from, $to, $list ) );
					}
				}
				return $node;
			}
		);
	}

	/**
	 * Apply or remove a class on a node in a stored tree. Returns the updated node list or WP_Error.
	 */
	public static function toggle_on_node( array $nodes, $node_id, $class_id, $on = true ) {
		$node = &Data::find( $nodes, $node_id );
		if ( null === $node ) {
			return new \WP_Error( 'brik_not_found', __( 'No node with that id.', 'brik-builder' ) );
		}
		$list = isset( $node['attrs']['classes'] ) && is_array( $node['attrs']['classes'] ) ? $node['attrs']['classes'] : array();
		$list = array_values( array_diff( $list, array( $class_id ) ) );
		if ( $on ) {
			$list[] = $class_id;
		}
		if ( $list ) {
			$node['attrs']['classes'] = $list;
		} else {
			unset( $node['attrs']['classes'] );
		}
		unset( $node );
		return $nodes;
	}

	/** Public description for the builder and MCP clients. */
	public static function describe( $usage = null ) {
		$out = array();
		foreach ( self::all() as $id => $class ) {
			$out[] = array(
				'id'       => $id,
				'name'     => $class['name'],
				'class'    => self::PREFIX . $class['name'],
				'label'    => $class['label'],
				'type'     => $class['type'],
				'attrs'    => $class['attrs'] ? $class['attrs'] : new \stdClass(),
				'usage'    => is_array( $usage ) && isset( $usage[ $id ] ) ? $usage[ $id ] : 0,
			);
		}
		return $out;
	}
}
