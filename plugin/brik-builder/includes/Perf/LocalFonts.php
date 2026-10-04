<?php
namespace Brik\Perf;

use Brik\Fonts;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Font loading modes (setting "fonts_mode"):
 *  - google: the Google Fonts stylesheet, as before;
 *  - local:  the used families are downloaded once (woff2) into uploads/brik/fonts and served
 *            from the site, so visitors never talk to Google;
 *  - system: no web fonts, the font stacks fall back to the platform UI fonts.
 */
final class LocalFonts {

	const DIR = 'brik/fonts';

	/** Google serves woff2 (and unicode-range subsets) to current browsers only. */
	const AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

	const SYSTEM = 'system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';

	/** Set while the page stylesheet already carries the @font-face rules. */
	public static $inlined = false;

	private static $bypass = false;

	public static function init() {
		add_filter( 'brik/fonts_url', array( __CLASS__, 'filter_url' ), 10, 2 );
	}

	/** google|local|system, or off when web fonts are disabled altogether. */
	public static function mode() {
		if ( ! Settings::get( 'google_fonts' ) ) {
			return 'off';
		}
		$mode = Settings::get( 'fonts_mode' );
		return in_array( $mode, array( 'google', 'local', 'system' ), true ) ? $mode : 'local';
	}

	public static function filter_url( $url, $families = array() ) {
		if ( self::$bypass || '' === $url ) {
			return $url;
		}
		if ( self::$inlined ) {
			return '';
		}
		switch ( self::mode() ) {
			case 'google':
				return $url;
			case 'local':
				$local = self::stylesheet( $url );
				return $local ? $local['url'] : '';
		}
		return '';
	}

	/** The Google Fonts URL for these families, ignoring the mode. */
	public static function google_url( array $families ) {
		self::$bypass = true;
		$url          = Fonts::url( $families );
		self::$bypass = false;
		return $url;
	}

	/** @font-face rules (local files) for the families, '' when nothing applies or the download failed. */
	public static function css( array $families ) {
		$url = self::google_url( $families );
		if ( ! $url ) {
			return '';
		}
		$local = self::stylesheet( $url );
		return $local ? $local['css'] : '';
	}

	/**
	 * Local copy of a Google Fonts stylesheet: downloaded on first use, then served from uploads.
	 *
	 * @return array|null url, path, css.
	 */
	public static function stylesheet( $google_url ) {
		$dir = self::dir();
		if ( ! $dir ) {
			return null;
		}
		$hash = substr( md5( $google_url ), 0, 12 );
		$path = $dir['path'] . '/' . $hash . '.css';
		$url  = $dir['url'] . '/' . $hash . '.css';
		if ( is_readable( $path ) ) {
			return array(
				'url'  => $url,
				'path' => $path,
				'css'  => (string) file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			);
		}
		// Don't retry a failed download on every page view.
		if ( get_transient( 'brik_fonts_fail_' . $hash ) ) {
			return null;
		}
		$css = self::download( $google_url, $dir );
		if ( null === $css ) {
			set_transient( 'brik_fonts_fail_' . $hash, 1, HOUR_IN_SECONDS );
			return null;
		}
		file_put_contents( $path, $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return array(
			'url'  => $url,
			'path' => $path,
			'css'  => $css,
		);
	}

	private static function download( $google_url, array $dir ) {
		$res = wp_remote_get(
			$google_url,
			array(
				'timeout'    => 10,
				'user-agent' => self::AGENT,
			)
		);
		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			return null;
		}
		$css = (string) wp_remote_retrieve_body( $res );
		if ( ! preg_match_all( '/url\((https:\/\/fonts\.gstatic\.com\/[^)\s]+)\)/', $css, $m ) ) {
			return null;
		}
		foreach ( array_unique( $m[1] ) as $remote ) {
			$family = 'font';
			if ( preg_match( '#/s/([a-z0-9]+)/#', $remote, $fm ) ) {
				$family = $fm[1];
			}
			$ext  = pathinfo( wp_parse_url( $remote, PHP_URL_PATH ), PATHINFO_EXTENSION );
			$name = $family . '/' . substr( md5( $remote ), 0, 16 ) . '.' . ( $ext ? sanitize_key( $ext ) : 'woff2' );
			$file = $dir['path'] . '/' . $name;
			if ( ! is_readable( $file ) ) {
				$font = wp_remote_get(
					$remote,
					array(
						'timeout'    => 15,
						'user-agent' => self::AGENT,
					)
				);
				if ( is_wp_error( $font ) || 200 !== (int) wp_remote_retrieve_response_code( $font ) ) {
					return null;
				}
				wp_mkdir_p( dirname( $file ) );
				if ( false === file_put_contents( $file, wp_remote_retrieve_body( $font ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					return null;
				}
			}
			$css = str_replace( $remote, $dir['url'] . '/' . $name, $css );
		}
		if ( false === strpos( $css, 'font-display' ) ) {
			$css = str_replace( '@font-face {', '@font-face {font-display:swap;', $css );
		}
		return "/* Self-hosted copy of Google Fonts (" . esc_url_raw( $google_url ) . ") */\n" . self::compact( $css );
	}

	/**
	 * Variable fonts come back as one @font-face per requested weight, all pointing at the
	 * same file. Merge those into a single rule with a weight range.
	 */
	public static function compact( $css ) {
		$groups = array();
		foreach ( Css::parse( $css ) as $node ) {
			if ( ! isset( $node['at'] ) || 'font-face' !== $node['at'] ) {
				return $css; // Unexpected shape: leave it alone.
			}
			$decls = array();
			foreach ( Css::declarations( $node['body'] ) as $decl ) {
				$pos = strpos( $decl, ':' );
				if ( false !== $pos ) {
					$decls[ strtolower( trim( substr( $decl, 0, $pos ) ) ) ] = trim( substr( $decl, $pos + 1 ) );
				}
			}
			$weight = isset( $decls['font-weight'] ) ? $decls['font-weight'] : '400';
			unset( $decls['font-weight'] );
			ksort( $decls );
			$key = md5( wp_json_encode( $decls ) );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'decls'   => $decls,
					'weights' => array(),
				);
			}
			foreach ( preg_split( '/\s+/', $weight ) as $w ) {
				$groups[ $key ]['weights'][] = (int) $w;
			}
		}
		$out = '';
		foreach ( $groups as $group ) {
			$min   = min( $group['weights'] );
			$max   = max( $group['weights'] );
			$body  = 'font-weight:' . ( $min === $max ? $min : $min . ' ' . $max );
			foreach ( $group['decls'] as $prop => $value ) {
				$body .= ';' . $prop . ':' . $value;
			}
			$out .= '@font-face{' . $body . '}';
		}
		return $out;
	}

	/**
	 * Swap Google font stacks ("Inter",sans-serif) for the system UI stack.
	 */
	public static function system_stacks( $css ) {
		$google = Fonts::google();
		return preg_replace_callback(
			'/"([^"]+)",(sans-serif|serif)\b/',
			static function ( $m ) use ( $google ) {
				if ( ! isset( $google[ $m[1] ] ) ) {
					return $m[0];
				}
				return 'serif' === $m[2] ? '"' . $m[1] . '",ui-serif,Georgia,"Times New Roman",serif' : '"' . $m[1] . '",' . self::SYSTEM;
			},
			$css
		);
	}

	public static function dir() {
		static $dir = false;
		if ( false !== $dir ) {
			return $dir;
		}
		$uploads = wp_upload_dir( null, false );
		$path    = empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . self::DIR : '';
		if ( ! $path || ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) || ! wp_is_writable( $path ) ) {
			$dir = null;
			return $dir;
		}
		$dir = array(
			'path' => $path,
			'url'  => set_url_scheme( trailingslashit( $uploads['baseurl'] ) . self::DIR ),
		);
		return $dir;
	}

	/** Families and how they load, for the analyzer. */
	public static function describe( array $families ) {
		$google = Fonts::google();
		$out    = array();
		foreach ( array_unique( $families ) as $family ) {
			$family = trim( $family, " \"'" );
			if ( '' === $family ) {
				continue;
			}
			$out[] = array(
				'family'  => $family,
				'weights' => isset( $google[ $family ] ) ? array( 300, 400, 500, 600, 700, 800 ) : array(),
				'source'  => isset( $google[ $family ] ) ? self::mode() : 'site',
			);
		}
		return $out;
	}
}
