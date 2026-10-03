<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end output: content replacement, styles, scripts and fonts.
 */
final class Frontend {

	/** Rendered results keyed by post id: [ html, css, fonts ]. */
	private static $rendered = array();

	private static $printed = array();

	private static $needed = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'template_redirect', array( __CLASS__, 'prepare' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_head' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'print_late' ), 5 );
		add_filter( 'the_content', array( __CLASS__, 'the_content' ), 9999 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_shortcode( 'brik', array( __CLASS__, 'shortcode' ) );
	}

	public static function register_assets() {
		wp_register_style( 'brik', BRIK_URL . 'assets/build/frontend.css', array(), self::ver( 'assets/build/frontend.css' ) );
		wp_register_script( 'brik', BRIK_URL . 'assets/build/frontend.js', array(), self::ver( 'assets/build/frontend.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script(
			'brik',
			'brikFront',
			array(
				'rest'  => esc_url_raw( rest_url( 'brik/v1/' ) ),
				'i18n'  => array(
					'sent'  => __( 'Thanks! Your message has been sent.', 'brik-builder' ),
					'error' => __( 'Something went wrong. Please try again.', 'brik-builder' ),
				),
			)
		);
	}

	/**
	 * Stylesheet for effect modules and section background effects.
	 */
	public static function effects_css() {
		if ( ! wp_style_is( 'brik-effects', 'enqueued' ) ) {
			wp_enqueue_style( 'brik-effects', BRIK_URL . 'assets/build/effects.css', array( 'brik' ), self::ver( 'assets/build/effects.css' ) );
		}
	}

	public static function fx_url( $name ) {
		return BRIK_URL . 'assets/build/fx/' . sanitize_key( $name ) . '.js';
	}

	/**
	 * Enqueue an effect script. Safe to call while rendering the body; it prints in the footer.
	 */
	public static function fx( $name ) {
		$name   = sanitize_key( $name );
		$handle = 'brik-fx-' . $name;
		if ( ! wp_script_is( $handle, 'registered' ) ) {
			wp_register_script( $handle, self::fx_url( $name ), array( 'brik' ), self::ver( 'assets/build/fx/' . $name . '.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		}
		wp_enqueue_script( $handle );
		self::effects_css();
	}

	public static function ver( $file ) {
		$path = BRIK_DIR . $file;
		return file_exists( $path ) ? BRIK_VERSION . '.' . filemtime( $path ) : BRIK_VERSION;
	}

	/**
	 * Render everything the page needs before <head> is printed, so its CSS can go in the head.
	 */
	public static function prepare() {
		if ( is_singular() ) {
			$id = get_queried_object_id();
			$canvas = Builder::is_canvas() && ! Builder::is_canvas_template();
			if ( ( $canvas || Data::enabled( $id ) ) && ! post_password_required( $id ) ) {
				self::render( $id, $canvas );
			}
		}
		ThemeBuilder::prepare();

		if ( self::$rendered ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		if ( self::$needed ) {
			return;
		}
		self::$needed = true;
		wp_enqueue_style( 'brik' );
		wp_enqueue_script( 'brik' );
		wp_add_inline_style( 'brik', Settings::css() );
		$fonts = Fonts::url( Settings::fonts() );
		if ( $fonts ) {
			wp_enqueue_style( 'brik-fonts', $fonts, array(), null );
		}
	}

	/**
	 * Render a post's tree once per request.
	 */
	public static function render( $post_id, $canvas = false ) {
		$key = $post_id . ( $canvas ? ':c' : '' );
		if ( isset( self::$rendered[ $key ] ) ) {
			return self::$rendered[ $key ];
		}

		$renderer = new Renderer( $post_id, $canvas );
		$page     = Data::page_settings( $post_id );
		$html     = $renderer->render_root( Data::get( $post_id ), ThemeBuilder::is_template( $post_id ) ? 'brik-template' : 'brik-content' );
		$css      = $renderer->style->css();
		if ( ! empty( $page['custom_css'] ) ) {
			$css .= $page['custom_css'];
		}

		self::$rendered[ $key ] = array(
			'html'  => $html,
			'css'   => $css,
			'fonts' => $renderer->style->fonts(),
		);
		return self::$rendered[ $key ];
	}

	public static function print_head() {
		$css   = '';
		$fonts = array();
		foreach ( self::$rendered as $key => $item ) {
			$css  .= $item['css'];
			$fonts = array_merge( $fonts, $item['fonts'] );
			self::$printed[ $key ] = true;
		}
		if ( $fonts && ( $url = Fonts::url( $fonts ) ) ) {
			printf( "<link rel=\"stylesheet\" id=\"brik-element-fonts\" href=\"%s\">\n", esc_url( $url ) );
		}
		if ( self::$needed && ! Builder::is_canvas() ) {
			echo "<script>document.documentElement.classList.add('brik-anim-ready')</script>\n";
		}
		if ( '' !== $css ) {
			echo '<style id="brik-css">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- generated, values cleaned in Style.
		}
	}

	/**
	 * CSS for content rendered after the head (shortcodes, widgets).
	 */
	public static function print_late() {
		foreach ( self::$rendered as $key => $item ) {
			if ( empty( self::$printed[ $key ] ) && '' !== $item['css'] ) {
				echo '<style class="brik-css-late">' . $item['css'] . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
				self::$printed[ $key ] = true;
			}
		}
	}

	public static function the_content( $content ) {
		$id     = get_the_ID();
		$canvas = Builder::is_canvas() && ! Builder::is_canvas_template() && get_queried_object_id() === $id;
		if ( ! $id || ! ( $canvas || Data::enabled( $id ) ) || post_password_required( $id ) ) {
			return $content;
		}
		// Only the main content of the post, not other loops borrowing the filter.
		if ( ! in_the_loop() && ! is_singular() ) {
			return $content;
		}
		self::enqueue();
		$result = self::render( $id, $canvas );
		return $result['html'];
	}

	public static function body_class( $classes ) {
		if ( is_singular() && Data::enabled( get_queried_object_id() ) ) {
			$classes[] = 'brik-page';
			$page      = Data::page_settings( get_queried_object_id() );
			if ( ! empty( $page['dark'] ) ) {
				$classes[] = 'dark';
			}
			if ( ! empty( $page['body_class'] ) ) {
				$classes = array_merge( $classes, explode( ' ', $page['body_class'] ) );
			}
		}
		return $classes;
	}

	/**
	 * [brik id="123"] renders a library item or template anywhere.
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'brik' );
		$id   = (int) $atts['id'];
		$post = get_post( $id );
		if ( ! $post || ! in_array( $post->post_type, array( Library::POST_TYPE, ThemeBuilder::POST_TYPE ), true ) || 'publish' !== $post->post_status ) {
			return '';
		}
		self::enqueue();
		$result = self::render( $id );
		return $result['html'];
	}
}
