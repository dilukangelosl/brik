<?php
namespace Brik\Design;

use Brik\Fields;
use Brik\Settings;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

/**
 * Design variables: spacing, radius, type and shadow scales plus custom variables.
 *
 * Stored in Settings "variables" as overrides of the default scales:
 * { space: { md: "1.5rem" }, radius: {…}, text: {…}, shadow: {…}, fluid: bool,
 *   headings: { h1: { size, weight, line_height, letter_spacing } }, custom: [ { name, value, group } ] }
 */
final class Variables {

	/** Viewport range used for fluid type, in rem (360px – 1280px). */
	const FLUID_MIN_VW = 22.5;
	const FLUID_MAX_VW = 80;

	public static function scales() {
		$shadows = Fields::shadow_map();
		unset( $shadows['none'] );
		return array(
			'space'  => array(
				'label' => __( 'Spacing', 'brik-builder' ),
				'steps' => array(
					'3xs' => '0.25rem',
					'2xs' => '0.5rem',
					'xs'  => '0.75rem',
					'sm'  => '1rem',
					'md'  => '1.5rem',
					'lg'  => '2rem',
					'xl'  => '3rem',
					'2xl' => '4rem',
					'3xl' => '6rem',
				),
			),
			'radius' => array(
				'label' => __( 'Radius', 'brik-builder' ),
				// Follows the shadcn/ui radius scale, so it moves with the global --radius.
				'steps' => array(
					'sm'   => 'calc(var(--radius) - 4px)',
					'md'   => 'calc(var(--radius) - 2px)',
					'lg'   => 'var(--radius)',
					'xl'   => 'calc(var(--radius) + 4px)',
					'full' => '9999px',
				),
			),
			'text'   => array(
				'label' => __( 'Type scale', 'brik-builder' ),
				// Same values as the Tailwind scale the modules use, so defaults change nothing.
				'steps' => array(
					'xs'   => '0.75rem',
					'sm'   => '0.875rem',
					'base' => '1rem',
					'lg'   => '1.125rem',
					'xl'   => '1.25rem',
					'2xl'  => '1.5rem',
					'3xl'  => '1.875rem',
					'4xl'  => '2.25rem',
					'5xl'  => '3rem',
					'6xl'  => '3.75rem',
					'7xl'  => '4.5rem',
				),
			),
			'shadow' => array(
				'label' => __( 'Shadows', 'brik-builder' ),
				'steps' => $shadows,
			),
		);
	}

	public static function groups() {
		return array(
			'color'  => __( 'Color', 'brik-builder' ),
			'space'  => __( 'Spacing', 'brik-builder' ),
			'radius' => __( 'Radius', 'brik-builder' ),
			'text'   => __( 'Font size', 'brik-builder' ),
			'shadow' => __( 'Shadow', 'brik-builder' ),
			'size'   => __( 'Size', 'brik-builder' ),
			'other'  => __( 'Other', 'brik-builder' ),
		);
	}

	public static function saved() {
		$saved = Settings::get( 'variables' );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Every variable with its effective value: [ group => [ [ name, step, value, default, css ] ] ].
	 */
	public static function resolved() {
		$saved = self::saved();
		$fluid = ! empty( $saved['fluid'] );
		$out   = array();
		foreach ( self::scales() as $group => $scale ) {
			foreach ( $scale['steps'] as $step => $default ) {
				$value = isset( $saved[ $group ][ $step ] ) && '' !== $saved[ $group ][ $step ] ? $saved[ $group ][ $step ] : $default;
				$css   = 'text' === $group && $fluid ? self::fluid( $value ) : $value;

				$out[ $group ][] = array(
					'name'    => $group . '-' . $step,
					'step'    => (string) $step,
					'value'   => $value,
					'default' => $default,
					'css'     => $css,
				);
			}
		}
		$out['custom'] = isset( $saved['custom'] ) ? array_values( (array) $saved['custom'] ) : array();
		return $out;
	}

	/**
	 * clamp() that scales a font size down to about two thirds on small screens.
	 * Sizes up to 1.25rem stay fixed: body copy shouldn't shrink.
	 */
	public static function fluid( $value ) {
		if ( ! preg_match( '/^(\d*\.?\d+)(rem|px)$/', trim( $value ), $m ) ) {
			return $value;
		}
		$max = 'px' === $m[2] ? (float) $m[1] / 16 : (float) $m[1];
		if ( $max <= 1.25 ) {
			return $value;
		}
		$min       = max( 1.125, round( $max * 0.66, 3 ) );
		$slope     = ( $max - $min ) / ( self::FLUID_MAX_VW - self::FLUID_MIN_VW );
		$intercept = $min - $slope * self::FLUID_MIN_VW;
		return sprintf( 'clamp(%srem,%srem + %svw,%srem)', self::num( $min ), self::num( $intercept ), self::num( $slope * 100 ), self::num( $max ) );
	}

	private static function num( $n ) {
		return rtrim( rtrim( number_format( $n, 4, '.', '' ), '0' ), '.' );
	}

	/**
	 * Flat map name => value of every variable (for pickers and MCP).
	 */
	public static function flat() {
		$out = array();
		foreach ( self::resolved() as $group => $list ) {
			foreach ( $list as $var ) {
				if ( ! empty( $var['name'] ) ) {
					$out[ $var['name'] ] = isset( $var['css'] ) ? $var['css'] : $var['value'];
				}
			}
		}
		return $out;
	}

	public static function css() {
		$saved = self::saved();
		$root  = '';
		foreach ( self::flat() as $name => $value ) {
			$value = Style::clean( $value );
			if ( '' !== $value ) {
				$root .= '--' . $name . ':' . $value . ';';
			}
		}
		$css = '' !== $root ? ':root{' . $root . '}' : '';

		// Heading styles, only when set; :where() keeps them below any element setting.
		$props = array(
			'size'           => 'font-size',
			'weight'         => 'font-weight',
			'line_height'    => 'line-height',
			'letter_spacing' => 'letter-spacing',
		);
		foreach ( isset( $saved['headings'] ) ? (array) $saved['headings'] : array() as $tag => $style ) {
			$decls = array();
			foreach ( $props as $key => $prop ) {
				if ( ! empty( $style[ $key ] ) ) {
					$decls[] = $prop . ':' . Style::clean( $style[ $key ] );
				}
			}
			if ( $decls && preg_match( '/^h[1-6]$/', $tag ) ) {
				$css .= ':where(.brik) ' . $tag . '{' . implode( ';', $decls ) . '}';
			}
		}
		return $css;
	}

	public static function sanitize_name( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '/^-+/', '', $name );
		$name = preg_replace( '/[^a-z0-9-]+/', '-', $name );
		return trim( substr( $name, 0, 60 ), '-' );
	}

	public static function sanitize( $value ) {
		$value  = is_array( $value ) ? $value : array();
		$out    = array();
		$scales = self::scales();
		foreach ( $scales as $group => $scale ) {
			if ( empty( $value[ $group ] ) || ! is_array( $value[ $group ] ) ) {
				continue;
			}
			foreach ( $value[ $group ] as $step => $v ) {
				$v = Style::clean( $v );
				// Values equal to the default are not stored, so later default changes reach them.
				if ( isset( $scale['steps'][ $step ] ) && '' !== $v && $v !== $scale['steps'][ $step ] ) {
					$out[ $group ][ (string) $step ] = $v;
				}
			}
		}
		$out['fluid'] = ! empty( $value['fluid'] );

		if ( ! empty( $value['headings'] ) && is_array( $value['headings'] ) ) {
			foreach ( $value['headings'] as $tag => $style ) {
				if ( ! preg_match( '/^h[1-6]$/', (string) $tag ) || ! is_array( $style ) ) {
					continue;
				}
				foreach ( array( 'size', 'weight', 'line_height', 'letter_spacing' ) as $key ) {
					if ( isset( $style[ $key ] ) && '' !== Style::clean( $style[ $key ] ) ) {
						$out['headings'][ $tag ][ $key ] = Style::clean( $style[ $key ] );
					}
				}
			}
		}

		$custom   = array();
		$reserved = array_merge( Settings::token_names(), array( 'radius' ) );
		foreach ( isset( $value['custom'] ) ? (array) $value['custom'] : array() as $var ) {
			if ( ! is_array( $var ) ) {
				continue;
			}
			$name = self::sanitize_name( isset( $var['name'] ) ? $var['name'] : '' );
			$val  = Style::clean( isset( $var['value'] ) ? $var['value'] : '' );
			if ( '' === $name || '' === $val || in_array( $name, $reserved, true ) || isset( $custom[ $name ] ) || preg_match( '/^(space|radius|text|shadow)-/', $name ) && self::is_scale_name( $name ) ) {
				continue;
			}
			$group           = isset( $var['group'] ) ? sanitize_key( $var['group'] ) : 'other';
			$custom[ $name ] = array(
				'name'  => $name,
				'value' => $val,
				'group' => isset( self::groups()[ $group ] ) ? $group : 'other',
			);
		}
		$out['custom'] = array_values( $custom );
		return $out;
	}

	private static function is_scale_name( $name ) {
		foreach ( self::scales() as $group => $scale ) {
			foreach ( array_keys( $scale['steps'] ) as $step ) {
				if ( $group . '-' . $step === $name ) {
					return true;
				}
			}
		}
		return false;
	}
}
