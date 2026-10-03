<?php
/**
 * Customizer settings. Kept deliberately small; design tokens are edited in Brik Builder.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register theme settings.
 */
function brik_theme_customize_register( $wp_customize ) {
	$wp_customize->get_setting( 'blogname' )->transport = 'postMessage';

	$wp_customize->add_section(
		'brik_theme_options',
		array(
			'title'    => __( 'Theme options', 'brik' ),
			'priority' => 130,
		)
	);

	$wp_customize->add_setting(
		'brik_theme_mode_toggle',
		array(
			'default'           => true,
			'sanitize_callback' => 'brik_theme_sanitize_checkbox',
		)
	);
	$wp_customize->add_control(
		'brik_theme_mode_toggle',
		array(
			'type'    => 'checkbox',
			'section' => 'brik_theme_options',
			'label'   => __( 'Show dark mode toggle', 'brik' ),
		)
	);

	$wp_customize->add_setting(
		'brik_theme_copyright',
		array(
			'default'           => '&copy; {year} {site}',
			'sanitize_callback' => 'brik_theme_sanitize_copyright',
		)
	);
	$wp_customize->add_control(
		'brik_theme_copyright',
		array(
			'type'        => 'text',
			'section'     => 'brik_theme_options',
			'label'       => __( 'Footer copyright text', 'brik' ),
			'description' => __( 'Use {year} for the current year and {site} for the site title. Links are allowed.', 'brik' ),
		)
	);
}
add_action( 'customize_register', 'brik_theme_customize_register' );

function brik_theme_sanitize_checkbox( $value ) {
	return (bool) $value;
}

function brik_theme_sanitize_copyright( $value ) {
	return wp_kses( (string) $value, brik_theme_copyright_kses() );
}

/**
 * Live preview of the site title in the header.
 */
function brik_theme_customize_preview_js() {
	wp_add_inline_script(
		'customize-preview',
		"wp.customize('blogname',function(v){v.bind(function(t){document.querySelectorAll('.site-title a').forEach(function(a){a.textContent=t;});});});"
	);
}
add_action( 'customize_preview_init', 'brik_theme_customize_preview_js' );

/**
 * Whether the dark mode toggle is shown.
 */
function brik_theme_show_mode_toggle() {
	return (bool) get_theme_mod( 'brik_theme_mode_toggle', true );
}
