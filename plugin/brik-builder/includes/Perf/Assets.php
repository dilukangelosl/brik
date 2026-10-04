<?php
namespace Brik\Perf;

use Brik\Builder;
use Brik\Frontend;
use Brik\Settings;
use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * The optimized front-end pipeline for a request.
 *
 * Everything Brik renders passes through the "brik/rendered" filter; we keep what was rendered
 * and, instead of the full library stylesheet plus inline element CSS, serve one per-page file
 * (with the above-the-fold part inlined) and only the module scripts the markup needs.
 * The builder canvas always gets the full assets.
 */
final class Assets {

	/** Rendered items for this request, keyed like Frontend's cache. */
	private static $items = array();

	/** Result of PageCss::build() once the head stylesheet is decided. */
	private static $page = null;

	/** Item keys whose CSS is inside the page file. */
	private static $included = array();

	public static function init() {
		add_filter( 'brik/rendered', array( __CLASS__, 'collect' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 100 );
		add_filter( 'brik/head_css', array( __CLASS__, 'head_css' ) );
		add_filter( 'style_loader_tag', array( __CLASS__, 'style_tag' ), 10, 4 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 1 );
	}

	/** Whether this request gets optimized assets. */
	public static function active() {
		static $active = null;
		if ( null !== $active ) {
			return $active;
		}
		$active = ! is_admin()
			&& ! Builder::is_canvas()
			&& Settings::get( 'perf_assets' )
			&& ! wp_doing_ajax()
			&& ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
			&& ! is_feed()
			&& ! is_customize_preview()
			&& Scripts::available();
		$active = (bool) apply_filters( 'brik/perf/optimize', $active );
		return $active;
	}

	/**
	 * Keep each rendered item and tidy its markup (loading priorities, dimensions, lazy embeds).
	 */
	public static function collect( $result, $post_id, $renderer ) {
		if ( $renderer->canvas ) {
			return $result;
		}
		$area    = ThemeBuilder::is_template( $post_id ) ? ThemeBuilder::area( $post_id ) : '';
		$main    = self::is_main( $post_id, $area );
		$root    = array();
		foreach ( $renderer->root as $node ) {
			$root[] = isset( $node['id'] ) ? $node['id'] : '';
		}
		if ( Settings::get( 'perf_lazy' ) && ! is_admin() ) {
			$result['html'] = Media::process( $result['html'], $main ? 'main' : ( 'header' === $area ? 'header' : 'other' ), $root );
		}
		self::$items[ (string) $post_id ] = array(
			'post_id' => (int) $post_id,
			'html'    => $result['html'],
			'css'     => $result['css'],
			'fonts'   => $result['fonts'],
			'fx'      => array_keys( $renderer->scripts ),
			'effects' => $renderer->effects,
			'types'   => $renderer->types,
			'root'    => $root,
			'area'    => $area,
			'main'    => $main,
		);
		return $result;
	}

	/** The item holding the first screen of content: a body template, else the page itself. */
	private static function is_main( $post_id, $area ) {
		if ( 'body' === $area ) {
			return true;
		}
		if ( $area || ! is_singular() || (int) get_queried_object_id() !== (int) $post_id ) {
			return false;
		}
		return ! ThemeBuilder::resolve( 'body' );
	}

	public static function items() {
		return self::$items;
	}

	/**
	 * Swap the full bundle for the core script and the library stylesheet for the page file.
	 * Runs after everything else has enqueued (the theme's font link included).
	 */
	public static function enqueue() {
		if ( ! self::active() || ! wp_script_is( 'brik', 'registered' ) ) {
			return;
		}
		$scripts = wp_scripts();
		if ( isset( $scripts->registered['brik'] ) ) {
			$scripts->registered['brik']->src = BRIK_URL . 'assets/build/frontend/core.js';
			$scripts->registered['brik']->ver = Frontend::ver( 'assets/build/frontend/core.js' );
		}

		if ( ! self::$items || ! wp_style_is( 'brik', 'enqueued' ) ) {
			return;
		}
		$page = PageCss::build(
			array_values( self::$items ),
			array(
				'key'          => self::key(),
				'body_classes' => get_body_class(),
				'woo'          => self::woo(),
			)
		);
		if ( ! $page ) {
			return; // Uploads not writable: keep the full stylesheet.
		}
		self::$page     = $page;
		self::$included = array_fill_keys( array_keys( self::$items ), true );

		$styles = wp_styles();
		$styles->registered['brik']->src = $page['url'];
		$styles->registered['brik']->ver = null;
		// Tokens and global CSS are inside the file now.
		unset( $styles->registered['brik']->extra['after'] );
		wp_dequeue_style( 'brik-effects' );
		if ( 'local' === LocalFonts::mode() || 'system' === LocalFonts::mode() ) {
			wp_dequeue_style( 'brik-fonts' );
			LocalFonts::$inlined = true;
		}
	}

	private static function key() {
		if ( is_singular() ) {
			return (string) get_queried_object_id();
		}
		if ( is_front_page() || is_home() ) {
			return 'home';
		}
		return 'site';
	}

	private static function woo() {
		return class_exists( 'WooCommerce' ) && function_exists( 'is_woocommerce' ) && \Brik\Woo\Woo::is_shop_request();
	}

	/** Element CSS printed in the head: only what isn't in the page file. */
	public static function head_css( $css ) {
		if ( ! self::$page ) {
			return $css;
		}
		$out = '';
		foreach ( self::$items as $key => $item ) {
			if ( empty( self::$included[ $key ] ) ) {
				$out .= $item['css'];
			}
		}
		return $out;
	}

	/**
	 * Inline the above-the-fold CSS and load the page file without blocking rendering.
	 */
	public static function style_tag( $tag, $handle, $href, $media ) {
		if ( 'brik' !== $handle || ! self::$page || '' === self::$page['critical'] ) {
			return $tag;
		}
		$url = esc_url( $href );
		// Scripts start once the stylesheet is in (see frontend/core.js), never later than 3s.
		$gate = '<script>window.brikCss=new Promise(function(r){window.brikCssLoaded=r;setTimeout(r,3000)});</script>';
		return '<style id="brik-critical-css">' . str_ireplace( '</style', '', self::$page['critical'] ) . "</style>\n" // phpcs:ignore WordPress.Security.EscapeOutput -- generated CSS.
			. $gate . "\n"
			. '<link rel="stylesheet" id="brik-css" href="' . $url . '" media="print" onload="this.media=\'all\';this.onload=null;window.brikCssLoaded&&brikCssLoaded()" onerror="window.brikCssLoaded&&brikCssLoaded()">' . "\n"
			. '<noscript><link rel="stylesheet" href="' . $url . '"></noscript>' . "\n";
	}

	/**
	 * Module scripts, decided once all markup (including late renders) is known.
	 */
	public static function footer() {
		if ( ! self::active() || ! wp_script_is( 'brik', 'enqueued' ) ) {
			return;
		}
		$html = '';
		$late = false;
		foreach ( self::$items as $key => $item ) {
			$html .= $item['html'];
			if ( self::$page && empty( self::$included[ $key ] ) ) {
				$late = true;
			}
		}
		Scripts::enqueue( Scripts::needed( $html, self::woo() ) );

		// Content rendered after the head (shortcodes, widgets) may use classes the page file
		// left out; bring in the full library for it.
		if ( $late ) {
			wp_enqueue_style( 'brik-library', BRIK_URL . 'assets/build/frontend.css', array(), Frontend::ver( 'assets/build/frontend.css' ) );
		}
	}

	/** For tests: forget this request's state. */
	public static function reset() {
		self::$items    = array();
		self::$page     = null;
		self::$included = array();
		LocalFonts::$inlined = false;
	}

	public static function page() {
		return self::$page;
	}
}
