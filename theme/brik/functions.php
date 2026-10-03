<?php
/**
 * Brik theme setup.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

define( 'BRIK_THEME_VERSION', '1.0.0' );

require get_template_directory() . '/inc/tokens.php';
require get_template_directory() . '/inc/icons.php';
require get_template_directory() . '/inc/template-tags.php';
require get_template_directory() . '/inc/navigation.php';
require get_template_directory() . '/inc/customizer.php';

/**
 * Theme supports, menus and editor styles.
 */
function brik_theme_setup() {
	load_theme_textdomain( 'brik', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 64,
			'width'       => 240,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	// Tells Brik Builder to leave header.php/footer.php alone; we print its locations ourselves.
	add_theme_support( 'brik' );

	set_post_thumbnail_size( 1200, 675, true );
	add_image_size( 'brik-card', 720, 405, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'brik' ),
			'footer'  => __( 'Footer menu', 'brik' ),
		)
	);

	add_editor_style( array( 'assets/css/editor.css', 'assets/css/content.css' ) );
}
add_action( 'after_setup_theme', 'brik_theme_setup' );

/**
 * Content width for oEmbeds and images inserted without explicit sizes.
 */
function brik_theme_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'brik_theme_content_width', 720 );
}
add_action( 'after_setup_theme', 'brik_theme_content_width', 0 );

/**
 * Sidebar and footer widget areas.
 */
function brik_theme_widgets_init() {
	$shared = array(
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h2 class="widget-title">',
		'after_title'   => '</h2>',
	);

	register_sidebar(
		array_merge(
			$shared,
			array(
				'name'        => __( 'Sidebar', 'brik' ),
				'id'          => 'sidebar',
				'description' => __( 'Shown next to single posts.', 'brik' ),
			)
		)
	);

	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar(
			array_merge(
				$shared,
				array(
					/* translators: %d: footer column number. */
					'name'        => sprintf( __( 'Footer column %d', 'brik' ), $i ),
					'id'          => 'footer-' . $i,
					'description' => __( 'Shown in the site footer.', 'brik' ),
				)
			)
		);
	}
}
add_action( 'widgets_init', 'brik_theme_widgets_init' );

/**
 * Whether Brik Builder is active.
 */
function brik_theme_has_builder() {
	return class_exists( 'Brik\Settings' ) && function_exists( 'brik_location' );
}

/**
 * Whether the current singular view is a Brik-built post. Those carry their own layout,
 * so the theme drops its title, container and padding.
 */
function brik_theme_is_built() {
	if ( ! is_singular() || ! function_exists( 'brik_is_built' ) ) {
		return false;
	}
	return (bool) brik_is_built( get_queried_object_id() );
}

/**
 * Print a Brik theme-builder location. False when the plugin is inactive or no template applies.
 */
function brik_theme_location( $area ) {
	if ( ! function_exists( 'brik_location' ) ) {
		return false;
	}
	return (bool) brik_location( $area );
}

/**
 * Whether the sidebar is shown on this view.
 */
function brik_theme_has_sidebar() {
	return is_singular( 'post' ) && ! brik_theme_is_built() && is_active_sidebar( 'sidebar' );
}

/**
 * Styles, scripts and fonts.
 */
function brik_theme_scripts() {
	$uri = get_template_directory_uri();

	if ( brik_theme_has_builder() && class_exists( 'Brik\Fonts' ) ) {
		// Same handle the plugin uses, so the font stylesheet is only printed once.
		$fonts = Brik\Fonts::url( Brik\Settings::fonts() );
		if ( $fonts ) {
			wp_enqueue_style( 'brik-fonts', $fonts, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		}
	}

	wp_enqueue_style( 'brik-theme', $uri . '/assets/css/theme.css', array(), brik_theme_asset_version( 'assets/css/theme.css' ) );
	wp_enqueue_style( 'brik-theme-content', $uri . '/assets/css/content.css', array( 'brik-theme' ), brik_theme_asset_version( 'assets/css/content.css' ) );
	wp_add_inline_style( 'brik-theme', brik_theme_tokens_css() );

	wp_enqueue_script(
		'brik-theme',
		$uri . '/assets/js/theme.js',
		array(),
		brik_theme_asset_version( 'assets/js/theme.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	wp_localize_script(
		'brik-theme',
		'brikTheme',
		array(
			'i18n' => array(
				'expand'   => __( 'Expand submenu', 'brik' ),
				'collapse' => __( 'Collapse submenu', 'brik' ),
				'dark'     => __( 'Switch to dark mode', 'brik' ),
				'light'    => __( 'Switch to light mode', 'brik' ),
			),
		)
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'brik_theme_scripts' );

/**
 * Cache-busting version that follows file edits during development.
 */
function brik_theme_asset_version( $file ) {
	$path = get_template_directory() . '/' . $file;
	return file_exists( $path ) ? BRIK_THEME_VERSION . '.' . filemtime( $path ) : BRIK_THEME_VERSION;
}

/**
 * Apply the saved color mode before first paint so dark mode doesn't flash.
 * Shares its storage key and values with Brik Builder's theme toggle.
 */
function brik_theme_mode_script() {
	$js = "(function(){try{var m=localStorage.getItem('brik-theme');}catch(e){}"
		. "var d=m==='dark'||(m!=='light'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);"
		. "document.documentElement.classList.toggle('dark',d);document.documentElement.style.colorScheme=d?'dark':'light';})();";
	wp_print_inline_script_tag( $js, array( 'id' => 'brik-theme-mode' ) );
}
add_action( 'wp_head', 'brik_theme_mode_script', 1 );

/**
 * Body classes the stylesheet keys off.
 */
function brik_theme_body_class( $classes ) {
	if ( brik_theme_has_builder() ) {
		$classes[] = 'has-brik-builder';
	}
	if ( brik_theme_is_built() ) {
		$classes[] = 'is-brik-built';
	}
	if ( brik_theme_has_sidebar() ) {
		$classes[] = 'has-sidebar';
	}
	if ( ! is_singular() ) {
		$classes[] = 'hfeed';
	}
	return $classes;
}
add_filter( 'body_class', 'brik_theme_body_class' );

/**
 * Token values in the block editor, so content there matches the front end.
 */
function brik_theme_editor_settings( $settings ) {
	if ( brik_theme_has_builder() ) {
		$settings['styles'][] = array( 'css' => Brik\Settings::css() );
	}
	return $settings;
}
add_filter( 'block_editor_settings_all', 'brik_theme_editor_settings' );

/**
 * Shorter excerpts read better in the card grid.
 */
function brik_theme_excerpt_length( $length ) {
	return is_admin() ? $length : 24;
}
add_filter( 'excerpt_length', 'brik_theme_excerpt_length' );

function brik_theme_excerpt_more( $more ) {
	return is_admin() ? $more : '&hellip;';
}
add_filter( 'excerpt_more', 'brik_theme_excerpt_more' );

/**
 * A pingback header for single posts that accept them.
 */
function brik_theme_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">' . "\n", esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'brik_theme_pingback_header' );
