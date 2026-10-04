<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Global settings: design tokens, global colors, fonts, style presets.
 */
final class Settings {

	const OPTION = 'brik_settings';

	private static $cache;

	public static function defaults() {
		return array(
			'post_types'   => array( 'page', 'post' ),
			'base'         => 'neutral',
			'accent'       => '',
			'tokens'       => array(
				'light' => array(),
				'dark'  => array(),
			),
			'radius'       => '0.625rem',
			'font_body'    => 'Inter',
			'font_heading' => '',
			'container'    => '1200px',
			'colors'       => array(),
			'presets'      => array(),
			'custom_css'   => '',
			'google_fonts' => true,
			'mcp_enabled'  => true,
			// Performance (see Perf\Perf).
			'perf_assets'   => true,
			'perf_critical' => true,
			'fonts_mode'    => 'local',
			'image_format'  => '',
			'perf_lazy'     => true,
			'perf_picture'  => false,
			'variables'    => array(),
			'classes'      => array(),
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Merge and store settings. Unknown keys are dropped.
	 */
	public static function update( array $changes ) {
		$current = self::all();
		$allowed = array_keys( self::defaults() );
		foreach ( $changes as $key => $value ) {
			if ( in_array( $key, $allowed, true ) ) {
				$current[ $key ] = self::sanitize( $key, $value );
			}
		}
		update_option( self::OPTION, $current );
		self::$cache = null;
		return self::all();
	}

	private static function sanitize( $key, $value ) {
		switch ( $key ) {
			case 'post_types':
				return array_values( array_filter( array_map( 'sanitize_key', (array) $value ), 'post_type_exists' ) );
			case 'google_fonts':
			case 'mcp_enabled':
			case 'perf_assets':
			case 'perf_critical':
			case 'perf_lazy':
			case 'perf_picture':
				return (bool) $value;
			case 'fonts_mode':
				return in_array( $value, array( 'google', 'local', 'system' ), true ) ? $value : 'local';
			case 'image_format':
				return in_array( $value, array( 'webp', 'avif' ), true ) ? $value : '';
			case 'custom_css':
				return str_ireplace( '</style', '', (string) $value );
			case 'variables':
				return Design\Variables::sanitize( $value );
			case 'classes':
				return Design\Classes::sanitize( $value );
			case 'tokens':
				$out = array(
					'light' => array(),
					'dark'  => array(),
				);
				foreach ( array( 'light', 'dark' ) as $mode ) {
					if ( ! empty( $value[ $mode ] ) && is_array( $value[ $mode ] ) ) {
						foreach ( $value[ $mode ] as $name => $color ) {
							if ( in_array( $name, self::token_names(), true ) && '' !== trim( (string) $color ) ) {
								$out[ $mode ][ $name ] = Style::clean( $color );
							}
						}
					}
				}
				return $out;
			case 'colors':
				$out = array();
				foreach ( (array) $value as $color ) {
					if ( empty( $color['value'] ) ) {
						continue;
					}
					$out[] = array(
						'id'    => sanitize_key( ! empty( $color['id'] ) ? $color['id'] : Data::id() ),
						'name'  => sanitize_text_field( isset( $color['name'] ) ? $color['name'] : '' ),
						'value' => Style::clean( $color['value'] ),
					);
				}
				return $out;
			case 'presets':
				$out     = array();
				$trusted = current_user_can( 'unfiltered_html' );
				foreach ( (array) $value as $type => $presets ) {
					$def = Modules::get( $type );
					if ( ! $def ) {
						continue;
					}
					foreach ( (array) $presets as $id => $preset ) {
						$out[ $type ][ sanitize_key( $id ) ] = array(
							'name'    => sanitize_text_field( isset( $preset['name'] ) ? $preset['name'] : $id ),
							'default' => ! empty( $preset['default'] ),
							'attrs'   => Data::sanitize_attrs( isset( $preset['attrs'] ) ? (array) $preset['attrs'] : array(), $def['fields'], $trusted ),
						);
					}
				}
				return $out;
			default:
				return is_scalar( $value ) ? Style::clean( $value ) : '';
		}
	}

	public static function preset( $type, $id ) {
		$presets = self::get( 'presets' );
		return isset( $presets[ $type ][ $id ]['attrs'] ) ? $presets[ $type ][ $id ]['attrs'] : null;
	}

	public static function default_preset( $type ) {
		$presets = self::get( 'presets' );
		if ( empty( $presets[ $type ] ) ) {
			return null;
		}
		foreach ( $presets[ $type ] as $preset ) {
			if ( ! empty( $preset['default'] ) ) {
				return $preset['attrs'];
			}
		}
		return null;
	}

	/* ---------------------------------------------------------------------
	 * Design tokens (shadcn/ui variables).
	 * ------------------------------------------------------------------- */

	public static function token_names() {
		return array(
			'background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground',
			'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground',
			'accent', 'accent-foreground', 'destructive', 'border', 'input', 'ring',
		);
	}

	/**
	 * shadcn/ui base colors. Each entry: [ light, dark ] where every mode lists
	 * background, foreground, surface, primary, subtle, muted-foreground, border, ring.
	 */
	public static function bases() {
		return array(
			'neutral' => array(
				'label' => 'Neutral',
				'light' => array( 'oklch(1 0 0)', 'oklch(0.145 0 0)', 'oklch(1 0 0)', 'oklch(0.205 0 0)', 'oklch(0.97 0 0)', 'oklch(0.556 0 0)', 'oklch(0.922 0 0)', 'oklch(0.708 0 0)' ),
				'dark'  => array( 'oklch(0.145 0 0)', 'oklch(0.985 0 0)', 'oklch(0.205 0 0)', 'oklch(0.922 0 0)', 'oklch(0.269 0 0)', 'oklch(0.708 0 0)', 'oklch(1 0 0 / 10%)', 'oklch(0.556 0 0)' ),
			),
			'zinc'    => array(
				'label' => 'Zinc',
				'light' => array( 'oklch(1 0 0)', 'oklch(0.141 0.005 285.823)', 'oklch(1 0 0)', 'oklch(0.21 0.006 285.885)', 'oklch(0.967 0.001 286.375)', 'oklch(0.552 0.016 285.938)', 'oklch(0.92 0.004 286.32)', 'oklch(0.705 0.015 286.067)' ),
				'dark'  => array( 'oklch(0.141 0.005 285.823)', 'oklch(0.985 0 0)', 'oklch(0.21 0.006 285.885)', 'oklch(0.92 0.004 286.32)', 'oklch(0.274 0.006 286.033)', 'oklch(0.705 0.015 286.067)', 'oklch(1 0 0 / 10%)', 'oklch(0.552 0.016 285.938)' ),
			),
			'slate'   => array(
				'label' => 'Slate',
				'light' => array( 'oklch(1 0 0)', 'oklch(0.129 0.042 264.695)', 'oklch(1 0 0)', 'oklch(0.208 0.042 265.755)', 'oklch(0.968 0.007 247.896)', 'oklch(0.554 0.046 257.417)', 'oklch(0.929 0.013 255.508)', 'oklch(0.704 0.04 256.788)' ),
				'dark'  => array( 'oklch(0.129 0.042 264.695)', 'oklch(0.984 0.003 247.858)', 'oklch(0.208 0.042 265.755)', 'oklch(0.929 0.013 255.508)', 'oklch(0.279 0.041 260.031)', 'oklch(0.704 0.04 256.788)', 'oklch(1 0 0 / 10%)', 'oklch(0.551 0.027 264.364)' ),
			),
			'stone'   => array(
				'label' => 'Stone',
				'light' => array( 'oklch(1 0 0)', 'oklch(0.147 0.004 49.25)', 'oklch(1 0 0)', 'oklch(0.216 0.006 56.043)', 'oklch(0.97 0.001 106.424)', 'oklch(0.553 0.013 58.071)', 'oklch(0.923 0.003 48.717)', 'oklch(0.709 0.01 56.259)' ),
				'dark'  => array( 'oklch(0.147 0.004 49.25)', 'oklch(0.985 0.001 106.423)', 'oklch(0.216 0.006 56.043)', 'oklch(0.923 0.003 48.717)', 'oklch(0.268 0.007 34.298)', 'oklch(0.709 0.01 56.259)', 'oklch(1 0 0 / 10%)', 'oklch(0.553 0.013 58.071)' ),
			),
		);
	}

	/**
	 * Accent themes override primary and ring. [ light primary, light fg, dark primary, dark fg ].
	 */
	public static function accents() {
		return array(
			'blue'   => array( 'Blue', 'oklch(0.546 0.245 262.881)', 'oklch(0.97 0.014 254.604)', 'oklch(0.623 0.214 259.815)', 'oklch(0.97 0.014 254.604)' ),
			'green'  => array( 'Green', 'oklch(0.627 0.194 149.214)', 'oklch(0.982 0.018 155.826)', 'oklch(0.696 0.17 162.48)', 'oklch(0.393 0.095 152.535)' ),
			'rose'   => array( 'Rose', 'oklch(0.645 0.246 16.439)', 'oklch(0.969 0.015 12.422)', 'oklch(0.645 0.246 16.439)', 'oklch(0.969 0.015 12.422)' ),
			'orange' => array( 'Orange', 'oklch(0.705 0.213 47.604)', 'oklch(0.98 0.016 73.684)', 'oklch(0.646 0.222 41.116)', 'oklch(0.98 0.016 73.684)' ),
			'violet' => array( 'Violet', 'oklch(0.606 0.25 292.717)', 'oklch(0.969 0.016 293.756)', 'oklch(0.541 0.281 293.009)', 'oklch(0.969 0.016 293.756)' ),
			'red'    => array( 'Red', 'oklch(0.637 0.237 25.331)', 'oklch(0.971 0.013 17.38)', 'oklch(0.637 0.237 25.331)', 'oklch(0.971 0.013 17.38)' ),
			'yellow' => array( 'Yellow', 'oklch(0.795 0.184 86.047)', 'oklch(0.421 0.095 57.708)', 'oklch(0.795 0.184 86.047)', 'oklch(0.421 0.095 57.708)' ),
		);
	}

	/**
	 * Resolved token values for a mode, after base, accent and user overrides.
	 */
	public static function tokens( $mode ) {
		$bases = self::bases();
		$base  = isset( $bases[ self::get( 'base' ) ] ) ? $bases[ self::get( 'base' ) ] : $bases['neutral'];
		list( $bg, $fg, $surface, $primary, $subtle, $muted_fg, $border, $ring ) = $base[ $mode ];
		$light = 'light' === $mode;

		$t = array(
			'background'           => $bg,
			'foreground'           => $fg,
			'card'                 => $surface,
			'card-foreground'      => $fg,
			'popover'              => $surface,
			'popover-foreground'   => $fg,
			'primary'              => $primary,
			'primary-foreground'   => $light ? $base['dark'][1] : $base['light'][3],
			'secondary'            => $subtle,
			'secondary-foreground' => $light ? $primary : $fg,
			'muted'                => $subtle,
			'muted-foreground'     => $muted_fg,
			'accent'               => $subtle,
			'accent-foreground'    => $light ? $primary : $fg,
			'destructive'          => $light ? 'oklch(0.577 0.245 27.325)' : 'oklch(0.704 0.191 22.216)',
			'border'               => $border,
			'input'                => $light ? $border : 'oklch(1 0 0 / 15%)',
			'ring'                 => $ring,
		);

		$accents = self::accents();
		$accent  = self::get( 'accent' );
		if ( isset( $accents[ $accent ] ) ) {
			$a                       = $accents[ $accent ];
			$t['primary']            = $light ? $a[1] : $a[3];
			$t['primary-foreground'] = $light ? $a[2] : $a[4];
			$t['ring']               = $t['primary'];
		}

		$tokens = self::get( 'tokens' );
		if ( ! empty( $tokens[ $mode ] ) ) {
			$t = array_merge( $t, $tokens[ $mode ] );
		}
		return $t;
	}

	/**
	 * CSS custom properties for the front end and the builder canvas.
	 */
	public static function css() {
		$vars = static function ( array $tokens ) {
			$out = '';
			foreach ( $tokens as $name => $value ) {
				$out .= '--' . $name . ':' . $value . ';';
			}
			return $out;
		};

		$root  = $vars( self::tokens( 'light' ) );
		$root .= '--radius:' . Style::clean( self::get( 'radius' ) ) . ';';
		$root .= '--brik-container:' . Style::clean( self::get( 'container' ) ) . ';';
		if ( self::get( 'font_body' ) ) {
			$root .= '--brik-font-body:' . Style::font_stack( self::get( 'font_body' ) ) . ';';
		}
		if ( self::get( 'font_heading' ) ) {
			$root .= '--brik-font-heading:' . Style::font_stack( self::get( 'font_heading' ) ) . ';';
		}
		foreach ( (array) self::get( 'colors' ) as $color ) {
			$root .= '--brik-color-' . $color['id'] . ':' . $color['value'] . ';';
		}

		$css  = ':root{' . $root . '}';
		$css .= '.dark{' . $vars( self::tokens( 'dark' ) ) . 'color-scheme:dark}';
		// Design variables and global CSS classes come before custom CSS so it can override them.
		$css .= Design\Variables::css() . Design\Classes::css();
		$css .= (string) self::get( 'custom_css' );
		return $css;
	}

	public static function fonts() {
		return array_filter( array( self::get( 'font_body' ), self::get( 'font_heading' ) ) );
	}

	/**
	 * Settings payload for the builder app (presets included).
	 */
	public static function for_client() {
		$all            = self::all();
		$all['bases']   = array_map(
			static function ( $b ) {
				return $b['label'];
			},
			self::bases()
		);
		$all['accents'] = array_map(
			static function ( $a ) {
				return $a[0];
			},
			self::accents()
		);
		$all['resolved'] = array(
			'light' => self::tokens( 'light' ),
			'dark'  => self::tokens( 'dark' ),
		);
		return $all;
	}
}
