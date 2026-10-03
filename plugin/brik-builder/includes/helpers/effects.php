<?php
/**
 * Animated section backgrounds: aurora, beams, particles, grids and friends.
 *
 * Hooks into the section module through `brik/section_fields` and
 * `brik/section_background`. CSS-only effects print markup styled by
 * assets/src/css/modules/effects-bg.css; canvas and WebGL effects load
 * assets/build/fx/bg-{name}.js on the pages that use them.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Effect catalogue: label, default colors, script (or false for CSS only),
 * whether it reacts to the pointer and the mask used when none is chosen.
 */
function brik_bg_effects() {
	static $effects = null;
	if ( null !== $effects ) {
		return $effects;
	}
	// Colorful effects get a curated palette by default: the stock primary token is
	// neutral grey, which turns an aurora into fog. Monochrome effects follow the tokens.
	$effects = array(
		'aurora'     => array( __( 'Aurora', 'brik-builder' ), '#6366f1', '#22d3ee', false, false, 'fade-bottom' ),
		'gradient'   => array( __( 'Gradient mesh', 'brik-builder' ), '#6366f1', '#ec4899', 'bg-gradient', true, 'none' ),
		'beams'      => array( __( 'Light beams', 'brik-builder' ), '#818cf8', '#e879f9', false, false, 'fade-edges' ),
		'meteors'    => array( __( 'Meteors', 'brik-builder' ), 'var(--primary)', '#a5b4fc', false, false, 'none' ),
		'stars'      => array( __( 'Stars', 'brik-builder' ), 'var(--primary)', '#a5b4fc', 'bg-stars', false, 'none' ),
		'sparkles'   => array( __( 'Sparkles', 'brik-builder' ), 'var(--primary)', '#a78bfa', 'bg-sparkles', true, 'fade-edges' ),
		'particles'  => array( __( 'Particles', 'brik-builder' ), 'var(--primary)', 'var(--primary)', 'bg-particles', true, 'none' ),
		'grid'       => array( __( 'Flickering grid', 'brik-builder' ), 'var(--primary)', '#818cf8', false, false, 'radial' ),
		'retro_grid' => array( __( 'Retro grid', 'brik-builder' ), '#ec4899', '#8b5cf6', false, false, 'none' ),
		'dots'       => array( __( 'Dot pattern', 'brik-builder' ), 'var(--primary)', '#818cf8', 'bg-dots', true, 'fade-edges' ),
		'rays'       => array( __( 'Light rays', 'brik-builder' ), '#c7d2fe', '#818cf8', false, false, 'fade-bottom' ),
		'waves'      => array( __( 'Waves', 'brik-builder' ), '#38bdf8', '#a78bfa', 'bg-waves', false, 'none' ),
		'vortex'     => array( __( 'Vortex', 'brik-builder' ), '#8b5cf6', '#22d3ee', 'bg-vortex', true, 'radial' ),
		'spotlight'  => array( __( 'Spotlight', 'brik-builder' ), '#e0e7ff', '#818cf8', 'bg-spotlight', true, 'none' ),
		'lamp'       => array( __( 'Lamp', 'brik-builder' ), '#22d3ee', '#6366f1', false, false, 'none' ),
		'ripple'     => array( __( 'Ripple', 'brik-builder' ), 'var(--primary)', 'var(--primary)', false, false, 'radial' ),
		'noise'      => array( __( 'Film grain', 'brik-builder' ), 'var(--primary)', 'var(--primary)', false, false, 'none' ),
	);
	foreach ( $effects as $key => $e ) {
		$effects[ $key ] = array(
			'label'       => $e[0],
			'color'       => $e[1],
			'color2'      => $e[2],
			'script'      => $e[3],
			'interactive' => $e[4],
			'mask'        => $e[5],
		);
	}
	return $effects;
}

/**
 * Keep color values to characters a CSS color can contain, so a value can't break
 * out of the inline style (no ; { } : or quotes).
 */
function brik_bg_effect_color( $value, $fallback ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value || preg_match( '/[^a-zA-Z0-9#%(),.\s\/\-]/', $value ) ) {
		return $fallback;
	}
	return $value;
}

/**
 * Small deterministic random generator so CSS effects lay out the same on every
 * render of the same element (stable markup, stable caches).
 */
function brik_bg_effect_rand( $seed ) {
	$state = crc32( (string) $seed ) ?: 1;
	return static function ( $min = 0, $max = 1 ) use ( &$state ) {
		$state = ( $state * 1103515245 + 12345 ) & 0x7fffffff;
		return $min + ( $state / 0x7fffffff ) * ( $max - $min );
	};
}

add_filter(
	'brik/section_fields',
	static function ( $fields ) {
		$effects     = brik_bg_effects();
		$options     = array( '' => __( 'None', 'brik-builder' ) );
		$interactive = array();
		foreach ( $effects as $key => $e ) {
			$options[ $key ] = $e['label'];
			if ( $e['interactive'] ) {
				$interactive[] = $key;
			}
		}
		$common = array(
			'tab'         => 'design',
			'group_label' => __( 'Background effect', 'brik-builder' ),
			'show_if'     => array( 'bg_effect' => '!' ),
		);

		$fields['bg_effect'] = Fields::field(
			'select',
			__( 'Effect', 'brik-builder' ),
			'bg_effect',
			array(
				'tab'         => 'design',
				'group_label' => __( 'Background effect', 'brik-builder' ),
				'options'     => Fields::opts( $options ),
				'description' => __( 'Animated layer behind the section content. Looks best on dark sections.', 'brik-builder' ),
			)
		);
		$fields['bg_effect_color'] = Fields::field(
			'color',
			__( 'Color', 'brik-builder' ),
			'bg_effect',
			array_merge( $common, array( 'description' => __( 'Empty uses the effect palette (or the primary color for monochrome effects). Try var(--primary) to follow the theme.', 'brik-builder' ) ) )
		);
		$fields['bg_effect_color2'] = Fields::field( 'color', __( 'Second color', 'brik-builder' ), 'bg_effect', $common );
		$fields['bg_effect_intensity'] = Fields::field(
			'range',
			__( 'Intensity', 'brik-builder' ),
			'bg_effect',
			array_merge( $common, array( 'min' => 0, 'max' => 100, 'step' => 1, 'default' => 60, 'description' => __( 'Brightness and density.', 'brik-builder' ) ) )
		);
		$fields['bg_effect_speed'] = Fields::field(
			'range',
			__( 'Speed', 'brik-builder' ),
			'bg_effect',
			array_merge( $common, array( 'min' => 0, 'max' => 200, 'step' => 5, 'unit' => '%', 'default' => 100 ) )
		);
		$fields['bg_effect_mask'] = Fields::field(
			'select',
			__( 'Fade', 'brik-builder' ),
			'bg_effect',
			array_merge(
				$common,
				array(
					'options' => Fields::opts(
						array(
							''            => __( 'Effect default', 'brik-builder' ),
							'none'        => __( 'None', 'brik-builder' ),
							'fade-top'    => __( 'Fade out at the top', 'brik-builder' ),
							'fade-bottom' => __( 'Fade out at the bottom', 'brik-builder' ),
							'fade-edges'  => __( 'Fade out at the edges', 'brik-builder' ),
							'radial'      => __( 'Radial (center only)', 'brik-builder' ),
						)
					),
				)
			)
		);
		$fields['bg_effect_interactive'] = Fields::field(
			'toggle',
			__( 'React to the mouse', 'brik-builder' ),
			'bg_effect',
			array_merge( $common, array( 'default' => true, 'show_if' => array( 'bg_effect' => $interactive ) ) )
		);
		$fields['bg_effect_opacity'] = Fields::field(
			'range',
			__( 'Opacity', 'brik-builder' ),
			'bg_effect',
			array_merge( $common, array( 'min' => 0, 'max' => 100, 'step' => 1, 'unit' => '%', 'default' => 100 ) )
		);
		return $fields;
	}
);

add_filter(
	'brik/section_background',
	static function ( $html, $a, $ctx ) {
		$name    = isset( $a['bg_effect'] ) ? (string) $a['bg_effect'] : '';
		$effects = brik_bg_effects();
		if ( ! isset( $effects[ $name ] ) ) {
			return $html;
		}
		$e         = $effects[ $name ];
		$intensity = isset( $a['bg_effect_intensity'] ) && '' !== $a['bg_effect_intensity'] ? max( 0, min( 100, (int) $a['bg_effect_intensity'] ) ) : 60;
		$speed     = isset( $a['bg_effect_speed'] ) && '' !== $a['bg_effect_speed'] ? max( 0, min( 200, (int) $a['bg_effect_speed'] ) ) : 100;
		$opacity   = isset( $a['bg_effect_opacity'] ) && '' !== $a['bg_effect_opacity'] ? max( 0, min( 100, (int) $a['bg_effect_opacity'] ) ) : 100;
		$mask      = isset( $a['bg_effect_mask'] ) ? (string) $a['bg_effect_mask'] : '';
		if ( ! in_array( $mask, array( 'none', 'fade-top', 'fade-bottom', 'fade-edges', 'radial' ), true ) ) {
			$mask = $e['mask'];
		}
		$interactive = $e['interactive'] && ( ! isset( $a['bg_effect_interactive'] ) || ! empty( $a['bg_effect_interactive'] ) );

		$style = sprintf(
			'--fx-color:%s;--fx-color2:%s;--fx-intensity:%s;--fx-speed:%s;--fx-opacity:%s',
			brik_bg_effect_color( isset( $a['bg_effect_color'] ) ? $a['bg_effect_color'] : '', $e['color'] ),
			brik_bg_effect_color( isset( $a['bg_effect_color2'] ) ? $a['bg_effect_color2'] : '', $e['color2'] ),
			round( $intensity / 100, 2 ),
			// CSS durations divide by the speed; keep it above zero and pause instead.
			round( max( 5, $speed ) / 100, 2 ),
			round( $opacity / 100, 2 )
		);

		$classes = array(
			'brik-bg-effect',
			'brik-bg-effect--' . str_replace( '_', '-', $name ),
			'none' !== $mask ? 'brik-bg-effect--mask-' . $mask : '',
			0 === $speed ? 'brik-bg-effect--paused' : '',
		);

		if ( $e['script'] ) {
			$ctx->script( $e['script'] );
		}

		return $html . sprintf(
			'<div class="%1$s" data-brik-effect="%2$s" data-intensity="%3$d" data-speed="%4$d" data-interactive="%5$d" style="%6$s" aria-hidden="true">%7$s</div>',
			esc_attr( implode( ' ', array_filter( $classes ) ) ),
			esc_attr( $name ),
			$intensity,
			$speed,
			$interactive ? 1 : 0,
			esc_attr( $style ),
			brik_bg_effect_markup( $name, $intensity, $ctx )
		);
	},
	10,
	3
);

/**
 * Inner markup for each effect. Only fixed markup built here, never user input.
 */
function brik_bg_effect_markup( $name, $intensity, $ctx ) {
	$rand = brik_bg_effect_rand( $ctx->id . $name );

	switch ( $name ) {
		case 'aurora':
			return '<div class="brik-bgfx-aurora"></div><div class="brik-bgfx-aurora brik-bgfx-aurora--b"></div>';

		case 'beams':
			return brik_bg_effect_beams( $rand );

		case 'meteors':
			$out   = '';
			$count = 8 + (int) round( $intensity / 5 );
			for ( $i = 0; $i < $count; $i++ ) {
				$out .= sprintf(
					'<span class="brik-bgfx-meteor" style="--x:%.1f%%;--y:%.1f%%;--d:%.2fs;--t:%.2fs;--l:%dpx"></span>',
					$rand( -10, 110 ),
					$rand( -30, 30 ),
					$rand( 0, 12 ),
					$rand( 3.5, 9 ),
					(int) $rand( 60, 180 )
				);
			}
			return $out;

		case 'grid':
			// Cells snap to the 48px grid; positions are in cells from the center.
			$out   = '<div class="brik-bgfx-grid-lines"></div><div class="brik-bgfx-grid-cells">';
			$count = 10 + (int) round( $intensity / 3 );
			for ( $i = 0; $i < $count; $i++ ) {
				$out .= sprintf(
					'<span style="--cx:%d;--cy:%d;--d:%.2fs;--t:%.2fs"></span>',
					(int) floor( $rand( -14, 14 ) ),
					(int) floor( $rand( -8, 8 ) ),
					$rand( 0, 8 ),
					$rand( 3, 7 )
				);
			}
			return $out . '</div>';

		case 'retro_grid':
			return '<div class="brik-bgfx-retro-sky"></div><div class="brik-bgfx-retro-plane"><div class="brik-bgfx-retro-lines"></div></div><div class="brik-bgfx-retro-horizon"></div>';

		case 'rays':
			$out = '<div class="brik-bgfx-rays-glow"></div>';
			for ( $i = 0; $i < 7; $i++ ) {
				$out .= sprintf(
					'<span class="brik-bgfx-ray" style="--x:%.1f%%;--a:%.1fdeg;--w:%.1f%%;--d:%.2fs;--t:%.2fs;--o:%.2f"></span>',
					$rand( 20, 80 ),
					$rand( -28, 28 ),
					$rand( 4, 12 ),
					$rand( -10, 0 ),
					$rand( 7, 14 ),
					$rand( 0.35, 0.9 )
				);
			}
			return $out;

		case 'lamp':
			return '<div class="brik-bgfx-lamp"><span class="brik-bgfx-lamp-cone"></span><span class="brik-bgfx-lamp-glow"></span><span class="brik-bgfx-lamp-core"></span><span class="brik-bgfx-lamp-line"></span></div>';

		case 'ripple':
			$out = '<div class="brik-bgfx-ripple">';
			for ( $i = 0; $i < 8; $i++ ) {
				$out .= sprintf( '<span style="--i:%d"></span>', $i );
			}
			return $out . '</div>';

		case 'noise':
			return '<div class="brik-bgfx-noise"></div>';

		case 'dots':
			return '<div class="brik-bgfx-dots"></div><div class="brik-bgfx-dots brik-bgfx-dots--lit"></div><div class="brik-bgfx-dots-glow"></div>';

		case 'spotlight':
			return '<div class="brik-bgfx-spot-beam"></div><div class="brik-bgfx-spot-beam brik-bgfx-spot-beam--b"></div><div class="brik-bgfx-spot-follow"></div>';
	}
	// Canvas and WebGL effects draw into a canvas their script adds.
	return '';
}

/**
 * Curved beams sweeping up from the lower left, with light pulses running along them.
 */
function brik_bg_effect_beams( $rand ) {
	$id    = 'brik-bgfx-beam-' . wp_unique_id();
	$paths = '';
	$pulse = '';
	for ( $i = 0; $i < 26; $i++ ) {
		$x0 = -380 + $i * 26;
		$y0 = 980 - $i * 6;
		$d  = sprintf(
			'M%d %d C%d %d %d %d %d %d',
			$x0,
			$y0,
			$x0 + 120,
			$y0 - 520 + $i * 4,
			$x0 + 640 + $i * 10,
			360 - $i * 10,
			$x0 + 1180 + $i * 22,
			-120 - $i * 4
		);
		$paths .= '<path d="' . esc_attr( $d ) . '" pathLength="1000"/>';
		if ( 0 === $i % 2 ) {
			$pulse .= sprintf(
				'<path d="%s" pathLength="1000" style="--d:%.2fs;--t:%.2fs"/>',
				esc_attr( $d ),
				$rand( -14, 0 ),
				$rand( 5, 11 )
			);
		}
	}
	return sprintf(
		'<div class="brik-bgfx-beams-glow"></div><svg class="brik-bgfx-beams" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" fill="none"><defs><linearGradient id="%1$s" x1="0" y1="800" x2="1200" y2="0" gradientUnits="userSpaceOnUse"><stop offset="0" style="stop-color:var(--fx-color2)"/><stop offset=".5" style="stop-color:var(--fx-color)"/><stop offset="1" style="stop-color:var(--fx-color2)"/></linearGradient></defs><g class="brik-bgfx-beams-rail">%2$s</g><g class="brik-bgfx-beams-halo" stroke="url(#%1$s)">%3$s</g><g class="brik-bgfx-beams-pulse" stroke="url(#%1$s)">%3$s</g></svg>',
		esc_attr( $id ),
		$paths,
		$pulse
	);
}
