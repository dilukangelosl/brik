<?php
/**
 * Fluid values: linear interpolation between two viewport widths, written as clamp().
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Editor;

defined( 'ABSPATH' ) || exit;

final class Fluid {

	/** Viewport widths the two reference values belong to. */
	const MIN_VW = 390;
	const MAX_VW = 1440;

	/**
	 * clamp() that equals $min_value at $min_vw and $max_value at $max_vw, linear in between.
	 * Values are px numbers. Equal values give a plain px length.
	 */
	public static function clamp( $min_value, $max_value, $min_vw = self::MIN_VW, $max_vw = self::MAX_VW ) {
		$a = (float) $min_value;
		$b = (float) $max_value;
		if ( abs( $a - $b ) < 0.0001 || $max_vw <= $min_vw ) {
			return self::num( $a ) . 'px';
		}
		$slope     = ( $b - $a ) / ( $max_vw - $min_vw );
		$intercept = $a - $slope * $min_vw;
		$vw        = $slope * 100;
		$lo        = min( $a, $b );
		$hi        = max( $a, $b );
		$sign      = $vw < 0 ? ' - ' : ' + ';
		return sprintf( 'clamp(%spx, calc(%spx%s%svw), %spx)', self::num( $lo ), self::num( $intercept ), $sign, self::num( abs( $vw ) ), self::num( $hi ) );
	}

	/**
	 * Parse a clamp() written by clamp(). Returns [ value at MIN_VW, value at MAX_VW ] or null.
	 */
	public static function parse( $value, $min_vw = self::MIN_VW, $max_vw = self::MAX_VW ) {
		if ( ! is_string( $value ) || ! preg_match( '/^clamp\(\s*(-?[\d.]+)px\s*,\s*calc\(\s*(-?[\d.]+)px\s*([+-])\s*(-?[\d.]+)vw\s*\)\s*,\s*(-?[\d.]+)px\s*\)$/', trim( $value ), $m ) ) {
			return null;
		}
		$lo    = (float) $m[1];
		$hi    = (float) $m[5];
		$inter = (float) $m[2];
		$vw    = (float) $m[4] * ( '-' === $m[3] ? -1 : 1 );
		$at    = static function ( $w ) use ( $lo, $hi, $inter, $vw ) {
			return max( $lo, min( $hi, $inter + $vw * $w / 100 ) );
		};
		return array( round( $at( $min_vw ), 2 ), round( $at( $max_vw ), 2 ) );
	}

	/**
	 * A CSS length as px, or null when it can't be converted (%, auto, var()…).
	 */
	public static function to_px( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(-?[\d.]+)(px)?$/', $value, $m ) ) {
			return (float) $m[1];
		}
		if ( preg_match( '/^(-?[\d.]+)r?em$/', $value, $m ) ) {
			return (float) $m[1] * 16;
		}
		$parsed = self::parse( $value );
		return $parsed ? $parsed[1] : null;
	}

	private static function num( $n ) {
		$s = rtrim( rtrim( number_format( (float) $n, 4, '.', '' ), '0' ), '.' );
		return '-0' === $s ? '0' : $s;
	}
}
