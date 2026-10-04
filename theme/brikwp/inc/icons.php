<?php
/**
 * Inline SVG icons (Lucide, ISC license).
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Icon markup. Decorative by default; pass a label to expose it to assistive tech.
 */
function brik_theme_icon( $name, $label = '' ) {
	$paths = array(
		'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
		'moon'          => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
		'menu'          => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
		'x'             => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
		'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'arrow-left'    => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		'arrow-right'   => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'calendar'      => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
		'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'message'       => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>',
		'pin'           => '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>',
		'home'          => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
		'corner-reply'  => '<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}

	$a11y = '' === $label
		? ' aria-hidden="true" focusable="false"'
		: ' role="img" aria-label="' . esc_attr( $label ) . '"';

	return '<svg class="icon icon-' . esc_attr( $name ) . '" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $a11y . '>' . $paths[ $name ] . '</svg>';
}

/**
 * Allowed markup for brik_theme_icon() output, for wp_kses() calls.
 */
function brik_theme_icon_kses() {
	$shape = array(
		'd'      => true,
		'cx'     => true,
		'cy'     => true,
		'r'      => true,
		'x'      => true,
		'y'      => true,
		'rx'     => true,
		'width'  => true,
		'height' => true,
	);
	return array(
		'svg'    => array(
			'class'           => true,
			'xmlns'           => true,
			'width'           => true,
			'height'          => true,
			'viewbox'         => true,
			'fill'            => true,
			'stroke'          => true,
			'stroke-width'    => true,
			'stroke-linecap'  => true,
			'stroke-linejoin' => true,
			'aria-hidden'     => true,
			'aria-label'      => true,
			'role'            => true,
			'focusable'       => true,
		),
		'path'   => $shape,
		'circle' => $shape,
		'rect'   => $shape,
	);
}

/**
 * Echo an icon.
 */
function brik_theme_the_icon( $name, $label = '' ) {
	echo wp_kses( brik_theme_icon( $name, $label ), brik_theme_icon_kses() );
}
