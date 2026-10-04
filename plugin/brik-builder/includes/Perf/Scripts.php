<?php
namespace Brik\Perf;

use Brik\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Per-module front-end scripts (assets/build/frontend/{name}.js).
 *
 * tools/build-js.mjs writes a manifest with the selectors each script mounts on and the class
 * and attribute names it may add to the DOM. A script loads when its selectors can match the
 * rendered markup, so the mapping can't drift from what the scripts actually do.
 */
final class Scripts {

	/** Scripts that bring markup needing another script (quick view renders product forms). */
	const COMPANIONS = array(
		'woo-shop' => array( 'woo-product' ),
	);

	private static $manifest;

	public static function manifest() {
		if ( null === self::$manifest ) {
			$file           = BRIK_DIR . 'assets/build/frontend/manifest.json';
			$data           = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			self::$manifest = is_array( $data ) ? $data : array();
		}
		return self::$manifest;
	}

	/** Whether the split build exists (it's optional for older builds). */
	public static function available() {
		$m = self::manifest();
		return ! empty( $m['modules'] ) && is_readable( BRIK_DIR . 'assets/build/frontend/core.js' );
	}

	public static function names() {
		$m = self::manifest();
		return isset( $m['modules'] ) ? array_keys( $m['modules'] ) : array();
	}

	/**
	 * Module scripts the markup needs.
	 *
	 * @param string $html Rendered markup.
	 * @param bool   $woo  Whether this is a WooCommerce page (its own templates use the shop scripts).
	 */
	public static function needed( $html, $woo = false ) {
		$m     = self::manifest();
		$usage = ( new Usage( false ) )->add_html( $html );
		$out   = array();
		foreach ( isset( $m['modules'] ) ? $m['modules'] : array() as $name => $info ) {
			foreach ( isset( $info['selectors'] ) ? $info['selectors'] : array() as $selector ) {
				if ( Css::satisfied( Css::requirement( $selector ), $usage ) ) {
					$out[ $name ] = true;
					break;
				}
			}
		}
		if ( $woo ) {
			foreach ( array( 'woo-shop', 'woo-product' ) as $name ) {
				if ( isset( $m['modules'][ $name ] ) ) {
					$out[ $name ] = true;
				}
			}
		}
		foreach ( self::COMPANIONS as $name => $with ) {
			if ( isset( $out[ $name ] ) ) {
				foreach ( $with as $other ) {
					$out[ $other ] = true;
				}
			}
		}
		$out = array_keys( $out );
		sort( $out );
		return $out;
	}

	public static function path( $name ) {
		return 'assets/build/frontend/' . sanitize_key( $name ) . '.js';
	}

	public static function bytes( $name ) {
		$file = BRIK_DIR . self::path( $name );
		return is_readable( $file ) ? (int) filesize( $file ) : 0;
	}

	public static function fx_bytes( $name ) {
		$file = BRIK_DIR . 'assets/build/fx/' . sanitize_key( $name ) . '.js';
		return is_readable( $file ) ? (int) filesize( $file ) : 0;
	}

	public static function handle( $name ) {
		return 'brik-m-' . sanitize_key( $name );
	}

	public static function enqueue( array $names ) {
		foreach ( $names as $name ) {
			$handle = self::handle( $name );
			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script(
					$handle,
					BRIK_URL . self::path( $name ),
					array( 'brik' ),
					Frontend::ver( self::path( $name ) ),
					array(
						'in_footer' => true,
						'strategy'  => 'defer',
					)
				);
			}
			wp_enqueue_script( $handle );
		}
	}

	/**
	 * Class/attribute names and class prefixes the given scripts may add at runtime.
	 *
	 * @return array [ tokens, prefixes ]
	 */
	/**
	 * The effect scripts whose markup appears in $html: one of their own brik-* classes is
	 * present. Shared state classes (is-in, is-active…) would match every script.
	 */
	public static function fx_in( array $fx, $html ) {
		$m     = self::manifest();
		$core  = isset( $m['core']['tokens'] ) ? array_flip( $m['core']['tokens'] ) : array();
		$usage = ( new Usage( false ) )->add_html( $html );
		return array_values(
			array_filter(
				$fx,
				static function ( $name ) use ( $m, $core, $usage ) {
					foreach ( isset( $m['fx'][ $name ]['tokens'] ) ? $m['fx'][ $name ]['tokens'] : array() as $token ) {
						if ( 0 === strpos( $token, 'brik-' ) && ! isset( $core[ $token ] ) && $usage->has_token( $token ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	public static function tokens( array $modules, array $fx = array() ) {
		$m        = self::manifest();
		$tokens   = isset( $m['core']['tokens'] ) ? $m['core']['tokens'] : array();
		$prefixes = array();
		$add      = static function ( $info ) use ( &$tokens, &$prefixes ) {
			if ( ! empty( $info['tokens'] ) ) {
				$tokens = array_merge( $tokens, $info['tokens'] );
			}
			if ( ! empty( $info['prefixes'] ) ) {
				$prefixes = array_merge( $prefixes, $info['prefixes'] );
			}
		};
		foreach ( $modules as $name ) {
			if ( isset( $m['modules'][ $name ] ) ) {
				$add( $m['modules'][ $name ] );
			}
		}
		foreach ( $fx as $name ) {
			if ( isset( $m['fx'][ $name ] ) ) {
				$add( $m['fx'][ $name ] );
			}
		}
		return array( array_values( array_unique( $tokens ) ), array_values( array_unique( $prefixes ) ) );
	}
}
