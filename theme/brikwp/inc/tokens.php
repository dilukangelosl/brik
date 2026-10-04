<?php
/**
 * Design tokens.
 *
 * The theme ships the shadcn/ui neutral palette so it looks finished on its own. When
 * Brik Builder is active its configured tokens are printed after these and win.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fallback token values, keyed by mode.
 */
function brik_theme_fallback_tokens() {
	return array(
		'light' => array(
			'background'           => 'oklch(1 0 0)',
			'foreground'           => 'oklch(0.145 0 0)',
			'card'                 => 'oklch(1 0 0)',
			'card-foreground'      => 'oklch(0.145 0 0)',
			'popover'              => 'oklch(1 0 0)',
			'popover-foreground'   => 'oklch(0.145 0 0)',
			'primary'              => 'oklch(0.205 0 0)',
			'primary-foreground'   => 'oklch(0.985 0 0)',
			'secondary'            => 'oklch(0.97 0 0)',
			'secondary-foreground' => 'oklch(0.205 0 0)',
			'muted'                => 'oklch(0.97 0 0)',
			'muted-foreground'     => 'oklch(0.556 0 0)',
			'accent'               => 'oklch(0.97 0 0)',
			'accent-foreground'    => 'oklch(0.205 0 0)',
			'destructive'          => 'oklch(0.577 0.245 27.325)',
			'border'               => 'oklch(0.922 0 0)',
			'input'                => 'oklch(0.922 0 0)',
			'ring'                 => 'oklch(0.708 0 0)',
		),
		'dark'  => array(
			'background'           => 'oklch(0.145 0 0)',
			'foreground'           => 'oklch(0.985 0 0)',
			'card'                 => 'oklch(0.205 0 0)',
			'card-foreground'      => 'oklch(0.985 0 0)',
			'popover'              => 'oklch(0.205 0 0)',
			'popover-foreground'   => 'oklch(0.985 0 0)',
			'primary'              => 'oklch(0.922 0 0)',
			'primary-foreground'   => 'oklch(0.205 0 0)',
			'secondary'            => 'oklch(0.269 0 0)',
			'secondary-foreground' => 'oklch(0.985 0 0)',
			'muted'                => 'oklch(0.269 0 0)',
			'muted-foreground'     => 'oklch(0.708 0 0)',
			'accent'               => 'oklch(0.269 0 0)',
			'accent-foreground'    => 'oklch(0.985 0 0)',
			'destructive'          => 'oklch(0.704 0.191 22.216)',
			'border'               => 'oklch(1 0 0 / 10%)',
			'input'                => 'oklch(1 0 0 / 15%)',
			'ring'                 => 'oklch(0.556 0 0)',
		),
	);
}

/**
 * Token CSS for the front end: fallbacks first, then the plugin's settings when available.
 */
function brik_theme_tokens_css() {
	$tokens = brik_theme_fallback_tokens();
	$vars   = static function ( array $values ) {
		$out = '';
		foreach ( $values as $name => $value ) {
			$out .= '--' . $name . ':' . $value . ';';
		}
		return $out;
	};

	$css  = ':root{' . $vars( $tokens['light'] )
		. '--radius:0.625rem;--brik-container:1200px;'
		. '--brik-font-body:"Inter",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;}';
	$css .= '.dark{' . $vars( $tokens['dark'] ) . 'color-scheme:dark}';

	if ( brik_theme_has_builder() ) {
		$css .= Brik\Settings::css();
	}

	return apply_filters( 'brik_theme_tokens_css', $css );
}
