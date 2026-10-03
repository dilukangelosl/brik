<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Lucide icons (stroke) and brand icons (fill). Brand icons are addressed as "brand:github".
 */
final class Icons {

	private static $icons;

	private static $brands;

	private static $aliases;

	/**
	 * Resolve renamed Lucide icons (trash-2 → trash) and common legacy names.
	 */
	public static function resolve( $name ) {
		if ( null === self::$aliases ) {
			self::$aliases = json_decode( (string) file_get_contents( BRIK_DIR . 'resources/icon-aliases.json' ), true );
		}
		if ( ! isset( self::icons()[ $name ] ) && isset( self::$aliases[ $name ] ) ) {
			return self::$aliases[ $name ];
		}
		return $name;
	}

	public static function icons() {
		if ( null === self::$icons ) {
			self::$icons = json_decode( (string) file_get_contents( BRIK_DIR . 'resources/icons.json' ), true );
		}
		return self::$icons;
	}

	public static function brands() {
		if ( null === self::$brands ) {
			self::$brands = json_decode( (string) file_get_contents( BRIK_DIR . 'resources/brands.json' ), true );
		}
		return self::$brands;
	}

	public static function exists( $name ) {
		if ( 0 === strpos( $name, 'brand:' ) ) {
			return isset( self::brands()[ substr( $name, 6 ) ] );
		}
		return isset( self::icons()[ self::resolve( $name ) ] );
	}

	public static function svg( $name, array $attrs = array() ) {
		$name = (string) $name;
		if ( '' === $name ) {
			return '';
		}
		$name = 0 === strpos( $name, 'brand:' ) ? $name : self::resolve( $name );

		if ( 0 === strpos( $name, 'brand:' ) ) {
			$brands = self::brands();
			$slug   = substr( $name, 6 );
			if ( ! isset( $brands[ $slug ] ) ) {
				return '';
			}
			$attrs = array_merge(
				array(
					'xmlns'       => 'http://www.w3.org/2000/svg',
					'viewBox'     => '0 0 24 24',
					'fill'        => 'currentColor',
					'aria-hidden' => 'true',
				),
				$attrs
			);
			return '<svg' . brik_attrs( $attrs ) . '><path d="' . esc_attr( $brands[ $slug ]['path'] ) . '"/></svg>';
		}

		$icons = self::icons();
		if ( ! isset( $icons[ $name ] ) ) {
			return '';
		}
		$attrs = array_merge(
			array(
				'xmlns'           => 'http://www.w3.org/2000/svg',
				'viewBox'         => '0 0 24 24',
				'fill'            => 'none',
				'stroke'          => 'currentColor',
				'stroke-width'    => '2',
				'stroke-linecap'  => 'round',
				'stroke-linejoin' => 'round',
				'aria-hidden'     => 'true',
			),
			$attrs
		);
		return '<svg' . brik_attrs( $attrs ) . '>' . $icons[ $name ] . '</svg>';
	}
}
