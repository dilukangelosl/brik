<?php
/**
 * Section: full-width band that holds rows.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_dividers = array(
	''          => __( 'None', 'brik' ),
	'wave'      => __( 'Wave', 'brik' ),
	'waves'     => __( 'Layered waves', 'brik' ),
	'curve'     => __( 'Curve', 'brik' ),
	'tilt'      => __( 'Tilt', 'brik' ),
	'triangle'  => __( 'Triangle', 'brik' ),
	'arrow'     => __( 'Arrow', 'brik' ),
	'mountains' => __( 'Mountains', 'brik' ),
	'zigzag'    => __( 'Zigzag', 'brik' ),
);

$brik_divider_fields = static function ( $pos, $label ) use ( $brik_dividers ) {
	return array(
		"divider_{$pos}"        => Fields::field( 'select', $label, 'dividers', array( 'options' => Fields::opts( $brik_dividers ), 'tab' => 'design' ) ),
		"divider_{$pos}_color"  => Fields::field( 'color', __( 'Divider color', 'brik' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ) ) ),
		"divider_{$pos}_height" => Fields::field( 'unit', __( 'Divider height', 'brik' ), 'dividers', array( 'tab' => 'design', 'responsive' => true, 'show_if' => array( "divider_{$pos}" => '!' ), 'css' => array( Fields::WRAP . " > .brik-divider-{$pos}", 'height' ) ) ),
		"divider_{$pos}_flip"   => Fields::field( 'toggle', __( 'Flip horizontally', 'brik' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ) ) ),
		"divider_{$pos}_front"  => Fields::field( 'toggle', __( 'Place above content', 'brik' ), 'dividers', array( 'tab' => 'design', 'show_if' => array( "divider_{$pos}" => '!' ) ) ),
	);
};

return array(
	'type'        => 'section',
	'title'       => __( 'Section', 'brik' ),
	'category'    => 'structure',
	'icon'        => 'rectangle-horizontal',
	'structural'  => true,
	'children'    => array( 'row' ),
	'description' => 'Top-level full-width band. Holds rows. Use for page sections (hero, features, footer...). Background, dividers and video backgrounds live here.',
	'tag'         => static function ( $a ) {
		return in_array( $a['tag'], array( 'section', 'header', 'footer', 'div', 'aside', 'main', 'nav' ), true ) ? $a['tag'] : 'section';
	},
	'class'       => static function ( $a ) {
		return brik_cls(
			array(
				'brik-section--full'   => ! empty( $a['fullwidth'] ),
				'brik-section--sticky' => ! empty( $a['sticky'] ),
				'dark'                 => ! empty( $a['dark'] ),
			)
		);
	},
	'fields'      => array_merge(
		array(
			'tag'            => Fields::field( 'select', __( 'HTML tag', 'brik' ), 'content', array( 'default' => 'section', 'options' => Fields::opts( array( 'section' => 'section', 'header' => 'header', 'footer' => 'footer', 'main' => 'main', 'aside' => 'aside', 'nav' => 'nav', 'div' => 'div' ) ) ) ),
			'fullwidth'      => Fields::field( 'toggle', __( 'Full width rows', 'brik' ), 'content', array( 'description' => __( 'Rows stretch edge to edge instead of the site container.', 'brik' ) ) ),
			'dark'           => Fields::field( 'toggle', __( 'Dark color scheme', 'brik' ), 'content', array( 'description' => __( 'Uses the dark design tokens inside this section.', 'brik' ) ) ),
			'sticky'         => Fields::field( 'toggle', __( 'Sticky on scroll', 'brik' ), 'content', array( 'description' => __( 'Sticks to the top of the window, handy for headers.', 'brik' ) ) ),
			'v_align'        => Fields::field( 'select', __( 'Vertical alignment', 'brik' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Top', 'brik' ), 'center' => __( 'Middle', 'brik' ), 'flex-end' => __( 'Bottom', 'brik' ) ) ), 'css' => array( Fields::WRAP, 'justify-content' ) ) ),
			'bg_video'       => Fields::field( 'video', __( 'Background video (MP4)', 'brik' ), 'background', array( 'tab' => 'design' ) ),
			'bg_video_poster' => Fields::field( 'image', __( 'Video poster', 'brik' ), 'background', array( 'tab' => 'design', 'show_if' => array( 'bg_video' => '!' ) ) ),
		),
		$brik_divider_fields( 'top', __( 'Top divider', 'brik' ) ),
		$brik_divider_fields( 'bottom', __( 'Bottom divider', 'brik' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$out = '';
		if ( ! empty( $a['bg_video'] ) ) {
			$out .= sprintf(
				'<video class="brik-bg-video" autoplay muted loop playsinline%s><source src="%s" type="video/mp4"></video>',
				! empty( $a['bg_video_poster'] ) ? ' poster="' . esc_url( brik_image_url( $a['bg_video_poster'] ) ) . '"' : '',
				esc_url( is_array( $a['bg_video'] ) ? $a['bg_video']['url'] : $a['bg_video'] )
			);
		}
		foreach ( array( 'top', 'bottom' ) as $pos ) {
			if ( ! empty( $a[ "divider_{$pos}" ] ) ) {
				$out .= brik_divider_svg( $a[ "divider_{$pos}" ], $pos, $a );
			}
		}
		return $out . $ctx->children();
	},
);
