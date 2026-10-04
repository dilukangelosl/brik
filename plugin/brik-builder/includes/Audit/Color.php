<?php
namespace Brik\Audit;

use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Colour parsing and WCAG contrast maths for the server-side audit.
 * Understands hex, rgb(a), hsl(a), oklch, a few keywords and var(--token) for theme tokens.
 */
final class Color {

	private static $tokens = array();

	/**
	 * Parse a CSS colour into [ r, g, b, a ] (0-255, alpha 0-1), or null when unknown.
	 *
	 * @param string $value CSS colour.
	 * @param string $mode  light|dark, used to resolve theme tokens.
	 */
	public static function parse( $value, $mode = 'light' ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		if ( preg_match( '/^var\(\s*--([a-z0-9-]+)\s*(?:,\s*(.+))?\)$/', $value, $m ) ) {
			$tokens = self::tokens( $mode );
			if ( isset( $tokens[ $m[1] ] ) ) {
				return self::parse( $tokens[ $m[1] ], $mode );
			}
			return isset( $m[2] ) ? self::parse( $m[2], $mode ) : null;
		}

		$keywords = array(
			'white'       => array( 255, 255, 255, 1 ),
			'black'       => array( 0, 0, 0, 1 ),
			'transparent' => array( 0, 0, 0, 0 ),
			'red'         => array( 255, 0, 0, 1 ),
			'gray'        => array( 128, 128, 128, 1 ),
			'grey'        => array( 128, 128, 128, 1 ),
			'silver'      => array( 192, 192, 192, 1 ),
			'yellow'      => array( 255, 255, 0, 1 ),
		);
		if ( isset( $keywords[ $value ] ) ) {
			return $keywords[ $value ];
		}

		if ( preg_match( '/^#([0-9a-f]{3,8})$/', $value, $m ) ) {
			$h = $m[1];
			if ( 3 === strlen( $h ) || 4 === strlen( $h ) ) {
				$h = preg_replace( '/(.)/', '$1$1', $h );
			}
			if ( 6 !== strlen( $h ) && 8 !== strlen( $h ) ) {
				return null;
			}
			return array(
				hexdec( substr( $h, 0, 2 ) ),
				hexdec( substr( $h, 2, 2 ) ),
				hexdec( substr( $h, 4, 2 ) ),
				8 === strlen( $h ) ? round( hexdec( substr( $h, 6, 2 ) ) / 255, 3 ) : 1,
			);
		}

		if ( ! preg_match( '/^(rgba?|hsla?|oklch)\((.+)\)$/', $value, $m ) ) {
			return null;
		}
		$parts = preg_split( '/[\s,\/]+/', trim( $m[2] ) );
		if ( count( $parts ) < 3 ) {
			return null;
		}
		$alpha = isset( $parts[3] ) ? self::number( $parts[3], 1 ) : 1;

		switch ( $m[1] ) {
			case 'rgb':
			case 'rgba':
				return array( self::number( $parts[0], 255 ), self::number( $parts[1], 255 ), self::number( $parts[2], 255 ), $alpha );
			case 'hsl':
			case 'hsla':
				return array_merge( self::hsl( (float) $parts[0], self::number( $parts[1], 1 ), self::number( $parts[2], 1 ) ), array( $alpha ) );
			default:
				return array_merge( self::oklch( self::number( $parts[0], 1 ), (float) $parts[1], (float) $parts[2] ), array( $alpha ) );
		}
	}

	/**
	 * A number that may be a percentage, scaled so 100% equals $scale.
	 */
	private static function number( $raw, $scale ) {
		if ( '%' === substr( $raw, -1 ) ) {
			return (float) $raw / 100 * $scale;
		}
		return (float) $raw;
	}

	private static function hsl( $h, $s, $l ) {
		$h = fmod( fmod( $h, 360 ) + 360, 360 ) / 360;
		$f = static function ( $n ) use ( $h, $s, $l ) {
			$k = fmod( $n + $h * 12, 12 );
			$a = $s * min( $l, 1 - $l );
			return $l - $a * max( -1, min( $k - 3, 9 - $k, 1 ) );
		};
		return array( round( $f( 0 ) * 255 ), round( $f( 8 ) * 255 ), round( $f( 4 ) * 255 ) );
	}

	/**
	 * OKLCH to sRGB (Björn Ottosson's OKLab matrices).
	 */
	private static function oklch( $l, $c, $h ) {
		$rad = deg2rad( $h );
		$a   = $c * cos( $rad );
		$b   = $c * sin( $rad );

		$l_ = $l + 0.3963377774 * $a + 0.2158037573 * $b;
		$m_ = $l - 0.1055613458 * $a - 0.0638541728 * $b;
		$s_ = $l - 0.0894841775 * $a - 1.2914855480 * $b;
		$l3 = $l_ * $l_ * $l_;
		$m3 = $m_ * $m_ * $m_;
		$s3 = $s_ * $s_ * $s_;

		$lin = array(
			4.0767416621 * $l3 - 3.3077115913 * $m3 + 0.2309699292 * $s3,
			-1.2684380046 * $l3 + 2.6097574011 * $m3 - 0.3413193965 * $s3,
			-0.0041960863 * $l3 - 0.7034186147 * $m3 + 1.7076147010 * $s3,
		);
		$out = array();
		foreach ( $lin as $v ) {
			$v     = $v <= 0.0031308 ? 12.92 * $v : 1.055 * pow( max( 0, $v ), 1 / 2.4 ) - 0.055;
			$out[] = (int) round( max( 0, min( 1, $v ) ) * 255 );
		}
		return $out;
	}

	private static function tokens( $mode ) {
		if ( ! isset( self::$tokens[ $mode ] ) ) {
			self::$tokens[ $mode ] = class_exists( '\Brik\Settings' ) ? Settings::tokens( 'dark' === $mode ? 'dark' : 'light' ) : array();
		}
		return self::$tokens[ $mode ];
	}

	/**
	 * Composite a (possibly translucent) colour over an opaque background.
	 */
	public static function over( array $top, array $bottom ) {
		$a = isset( $top[3] ) ? $top[3] : 1;
		return array(
			$top[0] * $a + $bottom[0] * ( 1 - $a ),
			$top[1] * $a + $bottom[1] * ( 1 - $a ),
			$top[2] * $a + $bottom[2] * ( 1 - $a ),
			1,
		);
	}

	public static function luminance( array $c ) {
		$ch = array();
		foreach ( array( 0, 1, 2 ) as $i ) {
			$v    = $c[ $i ] / 255;
			$ch[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
	}

	public static function ratio( array $fg, array $bg ) {
		$fg = self::over( $fg, $bg );
		$l1 = self::luminance( $fg );
		$l2 = self::luminance( $bg );
		return round( ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 ), 2 );
	}

	public static function hex( array $c ) {
		return sprintf( '#%02x%02x%02x', (int) round( $c[0] ), (int) round( $c[1] ), (int) round( $c[2] ) );
	}
}
