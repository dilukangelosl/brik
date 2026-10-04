<?php
namespace Brik\Design;

use Brik\Builder;
use Brik\Fonts;

defined( 'ABSPATH' ) || exit;

/**
 * Design system: variables, global CSS classes and components.
 *
 * Variables and classes are compiled into the global stylesheet (Settings::css()), components
 * render through the `global` module with per-instance overrides.
 */
final class Design {

	public static function init() {
		add_action( 'rest_api_init', array( RestDesign::class, 'routes' ) );
		add_filter( 'brik/mcp_tools', array( McpDesign::class, 'tools' ) );
		add_filter( 'brik/mcp_guide', array( McpDesign::class, 'guide' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'class_fonts' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'canvas_css' ), 40 );
	}

	/**
	 * Fonts picked in class styles load with the page, like element fonts do.
	 */
	public static function class_fonts() {
		if ( ! wp_style_is( 'brik', 'enqueued' ) ) {
			return;
		}
		$url = Fonts::url( Classes::fonts() );
		if ( $url ) {
			wp_enqueue_style( 'brik-class-fonts', $url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	/**
	 * Component instance badge in the builder canvas.
	 */
	public static function canvas_css() {
		if ( ! Builder::is_canvas() ) {
			return;
		}
		echo '<style id="brik-design-canvas">'
			. '.brik-is-component{position:relative;outline:1px dashed color-mix(in oklab,#8b5cf6 55%,transparent);outline-offset:2px}'
			. '.brik-is-component::before{content:"\25C7  " attr(data-brik-component);position:absolute;z-index:30;top:-9px;left:8px;padding:1px 7px;border-radius:999px;background:#8b5cf6;color:#fff;font:600 10px/16px ui-sans-serif,system-ui,sans-serif;letter-spacing:.01em;white-space:nowrap;pointer-events:none;box-shadow:0 1px 2px rgb(0 0 0/.15)}'
			. "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
}
