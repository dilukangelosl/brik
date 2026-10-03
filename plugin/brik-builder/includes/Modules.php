<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Module registry. Built-in modules live in /modules, one definition per file.
 */
final class Modules {

	private static $modules;

	public static function categories() {
		return apply_filters(
			'brik/module_categories',
			array(
				'structure'   => __( 'Structure', 'brik-builder' ),
				'basic'       => __( 'Basic', 'brik-builder' ),
				'content'     => __( 'Content', 'brik-builder' ),
				'media'       => __( 'Media', 'brik-builder' ),
				'interactive' => __( 'Interactive', 'brik-builder' ),
				'effects'     => __( 'Effects', 'brik-builder' ),
				'shop'        => __( 'Shop', 'brik-builder' ),
				'forms'       => __( 'Forms', 'brik-builder' ),
				'site'        => __( 'Site', 'brik-builder' ),
				'post'        => __( 'Post', 'brik-builder' ),
			)
		);
	}

	public static function all() {
		if ( null === self::$modules ) {
			self::$modules = array();
			foreach ( glob( BRIK_DIR . 'modules/*.php' ) as $file ) {
				self::add( require $file );
			}
			do_action( 'brik/register_modules' );
		}
		return self::$modules;
	}

	public static function get( $type ) {
		$all = self::all();
		return isset( $all[ $type ] ) ? $all[ $type ] : null;
	}

	/**
	 * Register a module definition. See modules/button.php for the format.
	 */
	public static function add( $def ) {
		if ( ! is_array( $def ) || empty( $def['type'] ) ) {
			return;
		}
		$def = array_merge(
			array(
				'title'       => $def['type'],
				'category'    => 'basic',
				'icon'        => 'box',
				'description' => '',
				'fields'      => array(),
				'tag'         => 'div',
				'structural'  => false,
				'children'    => false,
				'render'      => null,
			),
			$def
		);

		foreach ( $def['fields'] as $key => &$field ) {
			$field = array_merge(
				array(
					'tab'   => 'content',
					'group' => 'content',
				),
				$field
			);
		}
		unset( $field );

		// Module fields win over shared ones with the same key, which is rarely intended.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$clash = array_intersect_key( $def['fields'], Fields::common() );
			if ( $clash ) {
				/* translators: 1: module type, 2: field keys */
				_doing_it_wrong( __METHOD__, esc_html( sprintf( 'Module "%1$s" redefines shared design fields: %2$s.', $def['type'], implode( ', ', array_keys( $clash ) ) ) ), '1.0.0' );
			}
		}
		$def['fields'] = $def['fields'] + Fields::common();
		if ( null !== self::$modules ) {
			self::$modules[ $def['type'] ] = $def;
		}
	}

	/**
	 * Default attribute values for a module type.
	 */
	public static function defaults( $type ) {
		$def = self::get( $type );
		$out = array();
		if ( $def ) {
			foreach ( $def['fields'] as $key => $field ) {
				if ( isset( $field['default'] ) && '' !== $field['default'] ) {
					$out[ $key ] = $field['default'];
				}
			}
		}
		return $out;
	}

	/**
	 * Public description of modules for the builder app and MCP clients.
	 *
	 * @param bool $compact Drop design/advanced fields (they are the same for every module).
	 */
	public static function schema( $compact = false ) {
		$out = array();
		foreach ( self::all() as $type => $def ) {
			$fields = array();
			foreach ( $def['fields'] as $key => $field ) {
				if ( $compact && isset( Fields::common()[ $key ] ) ) {
					continue;
				}
				if ( $compact ) {
					unset( $field['css'], $field['group_label'] );
				}
				$fields[ $key ] = $field;
			}
			$item = array(
				'type'        => $type,
				'title'       => $def['title'],
				'category'    => $def['category'],
				'description' => $def['description'],
				'structural'  => (bool) $def['structural'],
				'children'    => $def['children'],
				'fields'      => $fields,
			);
			if ( ! $compact ) {
				$item['icon'] = Icons::svg( $def['icon'], array( 'width' => 18, 'height' => 18 ) );
			}
			$out[] = $item;
		}
		return $out;
	}

	public static function common_schema() {
		$out = array();
		foreach ( Fields::common() as $key => $field ) {
			unset( $field['css'] );
			$out[ $key ] = $field;
		}
		return $out;
	}
}
