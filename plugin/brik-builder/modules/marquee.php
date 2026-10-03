<?php
/**
 * Marquee: endlessly scrolling row of logos, images or text.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_marquee_default = array();
foreach ( array( 'Northwind' => 'wind', 'Hexa' => 'hexagon', 'Lumen' => 'aperture', 'Evergreen' => 'leaf', 'Summit' => 'mountain', 'Orbital' => 'orbit', 'Voltage' => 'zap', 'Featherly' => 'feather' ) as $brik_name => $brik_icon ) {
	$brik_marquee_default[] = array(
		'kind'  => 'text',
		'text'  => $brik_name,
		'icon'  => $brik_icon,
		'image' => '',
		'link'  => '',
	);
}

return array(
	'type'        => 'marquee',
	'title'       => __( 'Marquee', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'gallery-horizontal',
	'description' => 'Infinite scrolling row (logo wall, ticker). items: repeater of {kind: text|image, text, icon (Lucide name), image, link}. duration: seconds per loop (lower = faster). direction: left|right. pause_on_hover: bool. gap: CSS length. fade: bool, fades the edges. muted: bool, grayscale faded logos in color on hover. item_height: image height. Respects prefers-reduced-motion.',
	'fields'      => array_merge(
		array(
			'items'          => Fields::field(
				'repeater',
				__( 'Items', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'text',
					'default'     => $brik_marquee_default,
					'fields'      => array(
						'kind'  => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'text', 'options' => Fields::opts( array( 'text' => __( 'Text / icon', 'brik-builder' ), 'image' => __( 'Image / logo', 'brik-builder' ) ) ) ) ),
						'text'  => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'description' => __( 'Also used as alt text for images.', 'brik-builder' ) ) ),
						'icon'  => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => 'text' ) ) ),
						'image' => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => 'image' ) ) ),
						'link'  => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
					),
				)
			),
			'duration'       => Fields::field( 'range', __( 'Loop duration (seconds)', 'brik-builder' ), 'content', array( 'default' => 30, 'min' => 5, 'max' => 120, 'step' => 1 ) ),
			'direction'      => Fields::field( 'select', __( 'Direction', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Right to left', 'brik-builder' ), 'right' => __( 'Left to right', 'brik-builder' ) ) ) ) ),
			'pause_on_hover' => Fields::field( 'toggle', __( 'Pause on hover', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'gap'            => Fields::field( 'unit', __( 'Space between items', 'brik-builder' ), 'content', array( 'default' => '3rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-marquee', '--brik-gap' ) ) ),
			'fade'           => Fields::field( 'toggle', __( 'Fade edges', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'muted'          => Fields::field( 'toggle', __( 'Muted logos', 'brik-builder' ), 'marquee_style', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'description' => __( 'Grayscale and faded, full color on hover.', 'brik-builder' ) ) ),
			'item_height'    => Fields::field( 'unit', __( 'Image height', 'brik-builder' ), 'marquee_style', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'default' => '40px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-marquee-img', 'height' ) ) ),
			'icon_size'      => Fields::field( 'unit', __( 'Icon size', 'brik-builder' ), 'marquee_style', array( 'tab' => 'design', 'group_label' => __( 'Items', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-marquee-icon', array( 'width', 'height' ) ) ) ),
		),
		Fields::typography( 'item', __( 'Item text', 'brik-builder' ), Fields::WRAP . ' .brik-marquee-item' )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = is_array( $a['items'] ) ? array_values( array_filter( $a['items'], 'is_array' ) ) : array();
		$cells = array();
		foreach ( $items as $item ) {
			$item = array_merge( array( 'kind' => 'text', 'text' => '', 'icon' => '', 'image' => '', 'link' => '' ), $item );
			if ( 'image' === $item['kind'] ) {
				$body = brik_image( $item['image'], 'medium', array( 'class' => 'brik-marquee-img block h-10 w-auto max-w-none object-contain', 'alt' => wp_strip_all_tags( $item['text'] ) ) );
			} else {
				$body = ( $item['icon'] ? brik_icon( $item['icon'], 'brik-marquee-icon size-6 shrink-0 md:size-7' ) : '' ) . ( '' !== $item['text'] ? '<span>' . brik_inline( $item['text'] ) . '</span>' : '' );
			}
			if ( '' === $body ) {
				continue;
			}
			$class = 'brik-marquee-item inline-flex shrink-0 items-center gap-2.5 text-xl font-semibold md:text-2xl tracking-tight whitespace-nowrap text-foreground transition-all duration-300';
			$cells[] = ! empty( $item['link']['url'] )
				? '<li class="shrink-0"><a' . brik_link_attrs( $item['link'], array( 'class' => $class . ' hover:text-primary' ) ) . '>' . $body . '</a></li>'
				: '<li class="shrink-0"><span class="' . $class . '">' . $body . '</span></li>';
		}
		if ( ! $cells ) {
			return $ctx->placeholder( __( 'Add marquee items', 'brik-builder' ) );
		}

		// Each half of the track must be at least as wide as the viewport, so short lists repeat.
		$group = implode( '', $cells );
		$times = (int) ceil( 10 / count( $cells ) );
		$fill  = $times > 1 ? str_replace( array( '<li ', '<a ' ), array( '<li aria-hidden="true" ', '<a tabindex="-1" ' ), str_repeat( $group, $times - 1 ) ) : '';

		$duration = max( 2, (int) ( '' !== $a['duration'] ? $a['duration'] : 30 ) );
		$class    = brik_cls(
			'brik-marquee relative flex overflow-hidden',
			array(
				'brik-marquee--right' => 'right' === $a['direction'],
				'brik-marquee--pause' => ! empty( $a['pause_on_hover'] ),
				'brik-marquee--fade'  => ! empty( $a['fade'] ),
				'brik-marquee--muted' => ! empty( $a['muted'] ),
			)
		);
		$list = 'brik-marquee-group flex shrink-0 items-center';

		return sprintf(
			'<div class="%1$s" style="--brik-duration:%2$ss"><div class="brik-marquee-track flex w-max"><ul class="%3$s">%4$s</ul><ul class="%3$s brik-marquee-clone" aria-hidden="true">%5$s</ul></div></div>',
			esc_attr( $class ),
			esc_attr( $duration * $times ),
			esc_attr( $list ),
			$group . $fill,
			str_replace( '<a ', '<a tabindex="-1" ', $group ) . $fill
		);
	},
);
