<?php
/**
 * Section: full-width band that holds rows.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_dividers = array(
	''          => __( 'None', 'brik-builder' ),
	'wave'      => __( 'Wave', 'brik-builder' ),
	'waves'     => __( 'Layered waves', 'brik-builder' ),
	'curve'     => __( 'Curve', 'brik-builder' ),
	'curve-in'  => __( 'Curve (inverted)', 'brik-builder' ),
	'tilt'      => __( 'Tilt', 'brik-builder' ),
	'triangle'  => __( 'Triangle', 'brik-builder' ),
	'arrow'     => __( 'Arrow', 'brik-builder' ),
	'mountains' => __( 'Mountains', 'brik-builder' ),
	'zigzag'    => __( 'Zigzag', 'brik-builder' ),
);

$brik_divider_fields = static function ( $pos, $label ) use ( $brik_dividers ) {
	return array(
		"divider_{$pos}"        => Fields::field( 'select', $label, 'dividers', array( 'options' => Fields::opts( $brik_dividers ), 'tab' => 'design' ) ),
		"divider_{$pos}_color"  => Fields::field( 'color', __( 'Divider color', 'brik-builder' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ), 'description' => __( 'Leave empty to match the neighbouring section.', 'brik-builder' ) ) ),
		"divider_{$pos}_height" => Fields::field( 'unit', __( 'Divider height', 'brik-builder' ), 'dividers', array( 'tab' => 'design', 'responsive' => true, 'show_if' => array( "divider_{$pos}" => '!' ), 'css' => array( Fields::WRAP . " > .brik-shape-divider-{$pos}", 'height' ) ) ),
		"divider_{$pos}_flip"   => Fields::field( 'toggle', __( 'Flip horizontally', 'brik-builder' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ) ) ),
		"divider_{$pos}_front"  => Fields::field( 'toggle', __( 'Place above content', 'brik-builder' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ) ) ),
	);
};

return array(
	'type'        => 'section',
	'title'       => __( 'Section', 'brik-builder' ),
	'category'    => 'structure',
	'icon'        => 'rectangle-horizontal',
	'structural'  => true,
	'children'    => array( 'row' ),
	'description' => 'Top-level full-width band. Holds rows. Use for page sections (hero, features, footer...). Background, dividers (divider_top/bottom; color defaults to the neighbouring section) and video backgrounds live here. Header templates: sticky (bool), overlay (bool, floats over the next section for transparent headers), scroll_effect ""|shrink|hide|"shrink hide", scrolled_bg / scrolled_color / scrolled_shadow / scrolled_padding / scrolled_blur (styles applied once the page is scrolled).',
	'tag'         => static function ( $a ) {
		return in_array( $a['tag'], array( 'section', 'header', 'footer', 'div', 'aside', 'main', 'nav' ), true ) ? $a['tag'] : 'section';
	},
	'class'       => static function ( $a ) {
		return brik_cls(
			array(
				'brik-section--full'   => ! empty( $a['fullwidth'] ),
				'brik-section--sticky' => ! empty( $a['sticky'] ),
				'brik-section--overlay' => ! empty( $a['overlay'] ),
				'dark'                 => ! empty( $a['dark'] ),
			)
		);
	},
	'fields'      => array_merge(
		array(
			'tag'            => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'section', 'options' => Fields::opts( array( 'section' => 'section', 'header' => 'header', 'footer' => 'footer', 'main' => 'main', 'aside' => 'aside', 'nav' => 'nav', 'div' => 'div' ) ) ) ),
			'fullwidth'      => Fields::field( 'toggle', __( 'Full width rows', 'brik-builder' ), 'content', array( 'description' => __( 'Rows stretch edge to edge instead of the site container.', 'brik-builder' ) ) ),
			'dark'           => Fields::field( 'toggle', __( 'Dark color scheme', 'brik-builder' ), 'content', array( 'description' => __( 'Uses the dark design tokens inside this section.', 'brik-builder' ) ) ),
			'sticky'         => Fields::field( 'toggle', __( 'Sticky on scroll', 'brik-builder' ), 'content', array( 'description' => __( 'Sticks to the top of the window, handy for headers.', 'brik-builder' ) ) ),
			'overlay'        => Fields::field( 'toggle', __( 'Overlay the content below', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'description' => __( 'Floats over the next section, for transparent headers on a hero.', 'brik-builder' ) ) ),
			'scroll_effect'  => Fields::field(
				'select',
				__( 'On scroll', 'brik-builder' ),
				'header',
				array(
					'group_label' => __( 'Header behaviour', 'brik-builder' ),
					'options'     => Fields::opts(
						array(
							''            => __( 'Nothing', 'brik-builder' ),
							'shrink'      => __( 'Shrink', 'brik-builder' ),
							'hide'        => __( 'Hide when scrolling down, show when scrolling up', 'brik-builder' ),
							'shrink hide' => __( 'Shrink and hide on scroll down', 'brik-builder' ),
						)
					),
				)
			),
			'scrolled_bg'      => Fields::field( 'color', __( 'Background after scrolling', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'css' => array( Fields::WRAP . '.is-scrolled', 'background-color' ) ) ),
			'scrolled_color'   => Fields::field( 'color', __( 'Text color after scrolling', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'css' => array( Fields::WRAP . '.is-scrolled', 'color' ) ) ),
			'scrolled_shadow'  => Fields::field( 'shadow', __( 'Shadow after scrolling', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'css' => array( 'selector' => Fields::WRAP . '.is-scrolled', 'prop' => 'box-shadow', 'map' => Fields::shadow_map() ) ) ),
			'scrolled_padding' => Fields::field( 'spacing', __( 'Padding after scrolling', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'css' => array( Fields::WRAP . '.is-scrolled', 'padding' ) ) ),
			'scrolled_blur'    => Fields::field( 'range', __( 'Background blur after scrolling', 'brik-builder' ), 'header', array( 'group_label' => __( 'Header behaviour', 'brik-builder' ), 'min' => 0, 'max' => 40, 'unit' => 'px', 'css' => array( 'selector' => Fields::WRAP . '.is-scrolled', 'prop' => array( 'backdrop-filter', '-webkit-backdrop-filter' ), 'value' => 'blur({{v}})' ) ) ),
			'gap'            => Fields::field( 'unit', __( 'Space between rows', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'gap' ) ) ),
			'v_align'        => Fields::field( 'select', __( 'Vertical alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Top', 'brik-builder' ), 'center' => __( 'Middle', 'brik-builder' ), 'flex-end' => __( 'Bottom', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP, 'justify-content' ) ) ),
			'bg_video'       => Fields::field( 'video', __( 'Background video (MP4)', 'brik-builder' ), 'background', array( 'tab' => 'design' ) ),
			'bg_video_poster' => Fields::field( 'image', __( 'Video poster', 'brik-builder' ), 'background', array( 'tab' => 'design', 'show_if' => array( 'bg_video' => '!' ) ) ),
		),
		(array) apply_filters( 'brik/section_fields', array() ),
		$brik_divider_fields( 'top', __( 'Top divider', 'brik-builder' ) ),
		$brik_divider_fields( 'bottom', __( 'Bottom divider', 'brik-builder' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$out = '';
		if ( ! empty( $a['scroll_effect'] ) || ! empty( $a['scrolled_bg'] ) || ! empty( $a['scrolled_shadow'] ) || ! empty( $a['scrolled_padding'] ) || ! empty( $a['scrolled_color'] ) || ! empty( $a['scrolled_blur'] ) ) {
			$ctx->attrs['data-brik-scroll'] = trim( 'on ' . preg_replace( '/[^a-z ]/', '', (string) $a['scroll_effect'] ) );
		}
		if ( ! empty( $a['bg_video'] ) ) {
			$out .= sprintf(
				'<video class="brik-bg-video" autoplay muted loop playsinline%s><source src="%s" type="video/mp4"></video>',
				! empty( $a['bg_video_poster'] ) ? ' poster="' . esc_url( brik_image_url( $a['bg_video_poster'] ) ) . '"' : '',
				esc_url( is_array( $a['bg_video'] ) ? $a['bg_video']['url'] : $a['bg_video'] )
			);
		}
		// Background effects (aurora, particles, grids…) from includes/helpers/effects.php.
		$out .= (string) apply_filters( 'brik/section_background', '', $a, $ctx );
		foreach ( array( 'top', 'bottom' ) as $pos ) {
			if ( ! empty( $a[ "divider_{$pos}" ] ) ) {
				$out .= brik_divider_svg( $a[ "divider_{$pos}" ], $pos, $a, $ctx );
			}
		}
		return $out . $ctx->children();
	},
);
