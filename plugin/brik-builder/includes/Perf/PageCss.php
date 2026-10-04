<?php
namespace Brik\Perf;

use Brik\Fonts;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * One optimized stylesheet per page: the front-end library shaken down to the rules the page
 * can use, followed by the design tokens, fonts and the page's own element CSS, minified and
 * cached in uploads/brik/css/{key}-{hash}.css. The hash covers every input, so a stale file is
 * never served; old files are cleaned up when pages or settings change.
 */
final class PageCss {

	const DIR = 'brik/css';

	/**
	 * Above this share of the page file, inlining the first screen saves little and costs
	 * bytes on every view, so the page file loads normally instead.
	 */
	const CRITICAL_MAX = 0.6;

	/** Files kept per page; older variants (logged in/out, settings history) are pruned. */
	const KEEP = 8;

	/**
	 * Markup that some modules load later over AJAX (more posts, cart drawer, quick view, form
	 * replies) isn't in the first render. While their script is on the page, every class
	 * their PHP can print counts as used.
	 */
	const DYNAMIC = array(
		'listing'      => array( 'modules/listing.php', 'modules/listing_filter.php', 'includes/helpers/listing.php' ),
		'posts-filter' => array( 'modules/posts.php' ),
		'woo-shop'     => array( 'includes/helpers/woo-shop.php', 'modules/mini_cart.php', 'modules/products.php', 'modules/product_filters.php', 'includes/Woo/Cart.php' ),
		'woo-product'  => array( 'includes/helpers/woo-product.php', 'includes/Woo/Product.php', 'includes/Woo/AddToCart.php' ),
		'forms'        => array( 'includes/helpers/forms.php' ),
	);

	/** WooCommerce's own markup (notices, cart tables, variation forms) arrives from its templates. */
	const WOO_PREFIXES = array( 'woocommerce', 'wc-', 'wc_', 'select2', 'blockUI', 'blockOverlay', 'added_to_cart', 'single_add_to_cart', 'single_variation', 'variations', 'reset_variations', 'shop_table', 'cart', 'product', 'quantity', 'qty', 'amount', 'price', 'onsale', 'star-rating', 'stock', 'shipping', 'payment', 'checkout', 'order', 'form-row', 'input-text', 'button', 'coupon', 'remove', 'showcoupon', 'screen-reader-text', 'return-to-shop', 'wp-element-button', 'restore-item', 'actions', 'woocommerce-' );

	private static $library = array();

	/* ---------------------------------------------------------------------
	 * Building.
	 * ------------------------------------------------------------------- */

	/**
	 * Build (or fetch from cache) the stylesheet for a set of rendered items.
	 *
	 * @param array $items Rendered items: html, css, fonts, fx (effect scripts), effects (bool),
	 *                     root (top-level node ids), area (header|body|footer|''), main (bool).
	 * @param array $opts  key, body_classes, woo, critical (bool), store (bool).
	 * @return array|null  css, critical, url, path, hash, scripts, usage; null when storage fails.
	 */
	public static function build( array $items, array $opts = array() ) {
		$opts = wp_parse_args(
			$opts,
			array(
				'key'          => '0',
				'body_classes' => array(),
				'woo'          => false,
				'critical'     => (bool) Settings::get( 'perf_critical' ),
				'store'        => true,
			)
		);

		$html    = '';
		$node    = '';
		$fx      = array();
		$effects = false;
		$fonts   = Settings::fonts();
		foreach ( $items as $item ) {
			$html   .= $item['html'];
			$node   .= $item['css'];
			$fx      = array_merge( $fx, isset( $item['fx'] ) ? (array) $item['fx'] : array() );
			$effects = $effects || ! empty( $item['effects'] ) || ! empty( $item['fx'] );
			$fonts   = array_merge( $fonts, isset( $item['fonts'] ) ? (array) $item['fonts'] : array() );
		}
		$scripts = Scripts::needed( $html, $opts['woo'] );

		$usage = self::usage( $html, $scripts, array_unique( $fx ), $opts );

		$settings_css = Settings::css();
		$fonts_css    = '';
		$mode         = LocalFonts::mode();
		if ( 'local' === $mode ) {
			$fonts_css = LocalFonts::css( $fonts );
		}
		if ( 'system' === $mode || ( 'local' === $mode && '' === $fonts_css && LocalFonts::google_url( $fonts ) ) ) {
			$settings_css = LocalFonts::system_stacks( $settings_css );
			$node         = LocalFonts::system_stacks( $node );
		}

		$crit_usage = null;
		if ( $opts['critical'] ) {
			$crit_usage = new Usage();
			$crit_usage->add_html( self::critical_html( $items ) )->add_classes( (array) $opts['body_classes'] );
		}

		// Inputs only, never raw markup: nonces and timestamps must not change the file name.
		$sig  = md5(
			implode(
				'|',
				array(
					BRIK_VERSION,
					self::mtime( 'frontend' ),
					$effects ? self::mtime( 'effects' ) : 0,
					$usage->signature(),
					md5( $node ),
					md5( $settings_css ),
					md5( $fonts_css ),
					$crit_usage ? $crit_usage->signature() : 0,
				)
			)
		);
		$hash = substr( $sig, 0, 12 );
		$key  = sanitize_file_name( (string) $opts['key'] );

		$result = array(
			'hash'     => $hash,
			'scripts'  => $scripts,
			'usage'    => $usage,
			'url'      => '',
			'path'     => '',
			'css'      => '',
			'critical' => '',
		);

		$dir = $opts['store'] ? self::dir() : null;
		if ( $opts['store'] && ! $dir ) {
			return null;
		}
		if ( $dir ) {
			$path = $dir['path'] . '/' . $key . '-' . $hash . '.css';
			$crit = $dir['path'] . '/' . $key . '-' . $hash . '.critical.css';
			if ( is_readable( $path ) && ( ! $opts['critical'] || is_readable( $crit ) ) ) {
				// Mark the file as in use (cleanup removes files unused for a day).
				if ( filemtime( $path ) < time() - HOUR_IN_SECONDS ) {
					@touch( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					if ( $opts['critical'] ) {
						@touch( $crit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
					}
				}
				$result['path']     = $path;
				$result['url']      = $dir['url'] . '/' . basename( $path );
				$result['css']      = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
				$result['critical'] = $opts['critical'] ? (string) file_get_contents( $crit ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
				return $result;
			}
		}

		$parts = array(
			'library'  => self::library( 'frontend' ),
			'settings' => $settings_css,
			'effects'  => $effects ? self::library( 'effects' ) : array(),
			'fonts'    => $fonts_css,
			'node'     => $node,
		);
		$result['css'] = self::compose( $parts, $usage );

		if ( $crit_usage ) {
			$result['critical'] = self::compose( $parts, $crit_usage, true );
			if ( strlen( $result['critical'] ) > self::CRITICAL_MAX * strlen( $result['css'] ) ) {
				$result['critical'] = '';
			}
		}

		if ( $dir ) {
			if ( ! self::write( $path, $result['css'] ) || ( $opts['critical'] && ! self::write( $crit, $result['critical'] ) ) ) {
				return null;
			}
			$result['path'] = $path;
			$result['url']  = $dir['url'] . '/' . basename( $path );
			self::prune( $dir['path'], $key );
		}
		return $result;
	}

	/**
	 * Everything the page can contain: its markup, body classes, what its scripts add and the
	 * markup some of them fetch later.
	 */
	public static function usage( $html, array $scripts, array $fx, array $opts ) {
		$usage = new Usage();
		$usage->add_html( $html )->add_classes( isset( $opts['body_classes'] ) ? (array) $opts['body_classes'] : array() );
		list( $tokens, $prefixes ) = Scripts::tokens( $scripts, $fx );
		$usage->add_tokens( $tokens )->add_prefixes( $prefixes );
		foreach ( $scripts as $name ) {
			if ( isset( self::DYNAMIC[ $name ] ) ) {
				$usage->add_tokens( self::php_tokens( self::DYNAMIC[ $name ] ) );
			}
		}
		if ( ! empty( $opts['woo'] ) || array_intersect( $scripts, array( 'woo-shop', 'woo-product' ) ) ) {
			$usage->add_prefixes( self::WOO_PREFIXES );
		}
		return $usage;
	}

	/**
	 * Shake the libraries, add everything page-specific, dedupe and minify.
	 *
	 * @param bool $node_too Also shake the element CSS (for critical CSS).
	 */
	private static function compose( array $parts, Usage $usage, $node_too = false ) {
		$node_text = $parts['node'] . $parts['settings'] . $parts['fonts'];
		$nodes     = Css::shake( $parts['library'], $usage, $node_text );
		$nodes     = array_merge( $nodes, Css::parse( $parts['settings'] ) );
		if ( $parts['effects'] ) {
			$nodes = array_merge( $nodes, Css::shake( $parts['effects'], $usage, $node_text ) );
		}
		if ( '' !== $parts['fonts'] ) {
			$nodes = array_merge( $nodes, Css::parse( $parts['fonts'] ) );
		}

		$own = self::balanced( $parts['node'] ) ? Css::parse( $parts['node'] ) : null;
		if ( null !== $own && $node_too ) {
			$own = Css::shake( $own, $usage );
		}
		$nodes = Css::dedupe( null !== $own ? array_merge( $nodes, $own ) : $nodes );
		$css   = Css::serialize( $nodes );
		if ( null === $own ) {
			// Hand-written CSS with unbalanced braces: pass it through untouched.
			$css .= $parts['node'];
		}
		return "/*! tailwindcss | MIT License | https://tailwindcss.com */\n" . $css;
	}

	private static function balanced( $css ) {
		$css = Css::strip_comments( $css );
		return substr_count( $css, '{' ) === substr_count( $css, '}' );
	}

	/** Parsed front-end library (frontend|effects), memoized per request. */
	public static function library( $name ) {
		if ( ! isset( self::$library[ $name ] ) ) {
			$file                   = BRIK_DIR . 'assets/build/' . $name . '.css';
			self::$library[ $name ] = is_readable( $file ) ? Css::parse( (string) file_get_contents( $file ) ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		}
		return self::$library[ $name ];
	}

	public static function library_bytes( $name ) {
		$file = BRIK_DIR . 'assets/build/' . $name . '.css';
		return is_readable( $file ) ? (int) filesize( $file ) : 0;
	}

	private static function mtime( $name ) {
		$file = BRIK_DIR . 'assets/build/' . $name . '.css';
		return is_readable( $file ) ? filemtime( $file ) : 0;
	}

	/**
	 * Markup of the first viewport: header templates in full plus the first two top-level
	 * sections of the main content (body template, else the page).
	 */
	public static function critical_html( array $items ) {
		$out  = '';
		$main = null;
		foreach ( $items as $item ) {
			if ( isset( $item['area'] ) && 'header' === $item['area'] ) {
				$out .= $item['html'];
			}
			if ( ! empty( $item['main'] ) && null === $main ) {
				$main = $item;
			}
		}
		if ( null === $main ) {
			foreach ( $items as $item ) {
				if ( empty( $item['area'] ) || 'body' === $item['area'] ) {
					$main = $item;
					break;
				}
			}
		}
		if ( $main ) {
			$out .= self::first_sections( $main['html'], isset( $main['root'] ) ? (array) $main['root'] : array(), 2 );
		}
		return $out;
	}

	/** The markup up to the start of top-level node number $count + 1. */
	public static function first_sections( $html, array $root, $count ) {
		if ( ! isset( $root[ $count ] ) ) {
			return $html;
		}
		$pos = strpos( $html, 'brik-n-' . $root[ $count ] . '"' );
		if ( false === $pos ) {
			$pos = strpos( $html, 'brik-n-' . $root[ $count ] . ' ' );
		}
		if ( false === $pos ) {
			return $html;
		}
		$start = strrpos( substr( $html, 0, $pos ), '<' );
		return false === $start ? $html : substr( $html, 0, $start );
	}

	/**
	 * Class-like tokens from string literals in PHP templates.
	 */
	public static function php_tokens( array $files ) {
		$key    = 'brik_perf_tokens_' . md5( implode( ',', $files ) . BRIK_VERSION );
		$stamp  = 0;
		foreach ( $files as $file ) {
			$path  = BRIK_DIR . $file;
			$stamp = max( $stamp, is_readable( $path ) ? filemtime( $path ) : 0 );
		}
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['stamp'] ) && $cached['stamp'] === $stamp ) {
			return $cached['tokens'];
		}
		$tokens = array();
		foreach ( $files as $file ) {
			$path = BRIK_DIR . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$src = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			if ( preg_match_all( '/\'((?:\\\\.|[^\'\\\\])*)\'|"((?:\\\\.|[^"\\\\])*)"/', $src, $m ) ) {
				foreach ( $m[0] as $i => $unused ) {
					foreach ( preg_split( '/[\s"\'=<>]+/', $m[1][ $i ] . ' ' . $m[2][ $i ], -1, PREG_SPLIT_NO_EMPTY ) as $t ) {
						if ( strlen( $t ) > 1 && strlen( $t ) < 100 && preg_match( '/^[a-z!@\[\-_][\w:\/\[\]\-.&%!@(),#*+~]*$/i', $t ) ) {
							$tokens[ $t ] = true;
						}
					}
				}
			}
		}
		$tokens = array_keys( $tokens );
		set_transient(
			$key,
			array(
				'stamp'  => $stamp,
				'tokens' => $tokens,
			),
			WEEK_IN_SECONDS
		);
		return $tokens;
	}

	/* ---------------------------------------------------------------------
	 * Storage.
	 * ------------------------------------------------------------------- */

	/**
	 * uploads/brik/css, created on demand. Null when it can't be written.
	 */
	public static function dir() {
		static $dir = false;
		if ( false !== $dir ) {
			return $dir;
		}
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			$dir = null;
			return $dir;
		}
		$path = trailingslashit( $uploads['basedir'] ) . self::DIR;
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			$dir = null;
			return $dir;
		}
		if ( ! wp_is_writable( $path ) ) {
			$dir = null;
			return $dir;
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			self::write( $path . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		$dir = array(
			'path' => $path,
			'url'  => set_url_scheme( trailingslashit( $uploads['baseurl'] ) . self::DIR ),
		);
		return $dir;
	}

	/** Write through a temporary file so readers never see half a stylesheet. */
	private static function write( $path, $contents ) {
		$tmp = $path . '.' . wp_generate_password( 6, false ) . '.tmp';
		if ( false === file_put_contents( $tmp, $contents ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return false;
		}
		if ( ! @rename( $tmp, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( $tmp );
			return false;
		}
		return true;
	}

	private static function prune( $path, $key ) {
		$files = glob( $path . '/' . $key . '-*.css' );
		if ( ! $files ) {
			return;
		}
		$files = array_filter(
			$files,
			static function ( $f ) {
				return false === strpos( $f, '.critical.css' );
			}
		);
		if ( count( $files ) <= self::KEEP ) {
			return;
		}
		usort(
			$files,
			static function ( $a, $b ) {
				return filemtime( $b ) - filemtime( $a );
			}
		);
		foreach ( array_slice( $files, self::KEEP ) as $file ) {
			wp_delete_file( $file );
			wp_delete_file( substr( $file, 0, -4 ) . '.critical.css' );
		}
	}

	/**
	 * Delete cached stylesheets: one page's ($key) or all of them.
	 *
	 * Pages served from a page cache may still point at an older file for a while, so by
	 * default only files untouched for $older_than seconds go. Fresh content never needs a
	 * purge: its hash, and so its file name, changes.
	 */
	public static function purge( $key = null, $older_than = DAY_IN_SECONDS ) {
		$dir = self::dir();
		if ( ! $dir ) {
			return 0;
		}
		$pattern = null === $key ? '/*.css' : '/' . sanitize_file_name( (string) $key ) . '-*.css';
		$limit   = time() - (int) $older_than;
		$count   = 0;
		foreach ( (array) glob( $dir['path'] . $pattern ) as $file ) {
			if ( $older_than > 0 && filemtime( $file ) > $limit ) {
				continue;
			}
			wp_delete_file( $file );
			++$count;
		}
		return $count;
	}
}
