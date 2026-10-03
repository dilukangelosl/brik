<?php
/**
 * Parallax layers: images, color blobs and text placed at different depths that drift with the
 * pointer and the scroll position, for depth-heavy heroes.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_layer = static function ( $kind, array $props ) {
	return array_merge(
		array(
			'kind'   => $kind,
			'image'  => '',
			'text'   => '',
			'color'  => '',
			'x'      => 50,
			'y'      => 50,
			'width'  => 30,
			'depth'  => 0.5,
			'rotate' => 0,
			'cover'  => false,
			'mobile' => true,
		),
		$props
	);
};

$brik_layers_default = array(
	$brik_layer( 'image', array( 'image' => array( 'url' => brik_sample_image( '1519681393784-d120267933ba', 2000, 1300 ), 'alt' => '' ), 'cover' => true, 'depth' => 0.2, 'width' => 100 ) ),
	$brik_layer( 'blob', array( 'color' => '#8b5cf6', 'x' => 22, 'y' => 30, 'width' => 38, 'depth' => 0.4 ) ),
	$brik_layer( 'blob', array( 'color' => '#06b6d4', 'x' => 80, 'y' => 72, 'width' => 34, 'depth' => 0.55 ) ),
	$brik_layer( 'image', array( 'image' => array( 'url' => brik_sample_image( '1464822759023-fed622ff2c3b', 600, 760 ), 'alt' => '' ), 'x' => 13, 'y' => 66, 'width' => 16, 'depth' => 1.1, 'rotate' => -8 ) ),
	$brik_layer( 'image', array( 'image' => array( 'url' => brik_sample_image( '1506905925346-21bda4d32df4', 700, 520 ), 'alt' => '' ), 'x' => 85, 'y' => 26, 'width' => 17, 'depth' => 1.4, 'rotate' => 7 ) ),
	$brik_layer( 'image', array( 'image' => array( 'url' => brik_sample_image( '1501785888041-af3ef285b470', 600, 460 ), 'alt' => '' ), 'x' => 84, 'y' => 80, 'width' => 13, 'depth' => 0.8, 'rotate' => -5, 'mobile' => false ) ),
	$brik_layer( 'image', array( 'image' => array( 'url' => brik_sample_image( '1470071459604-3b5ec3a7fe05', 600, 460 ), 'alt' => '' ), 'x' => 16, 'y' => 20, 'width' => 12, 'depth' => 0.9, 'rotate' => 6, 'mobile' => false ) ),
);

return array(
	'type'        => 'parallax_layers',
	'title'       => __( 'Parallax Layers', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'layers-2',
	'description' => 'Depth hero: layers that move at different speeds with the pointer and the scroll, plus centered content. layers: repeater of {kind: image|blob|text, image, text, color (blob/text), x, y (center, % of the box), width (% of the box), depth (-1 to 2; 0 = fixed, higher = closer and faster), rotate (deg), cover (bool, fills the box), mobile (bool, show on phones)}. box_height: CSS length (responsive). mouse / scroll: bools. intensity: 0-2 movement multiplier. eyebrow, title (inline HTML), text, button_text, link: overlay content. content_color: text color over the layers. rounded: box corners. Static with reduced motion.',
	'fields'      => array_merge(
		array(
			'layers'        => Fields::field(
				'repeater',
				__( 'Layers', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'kind',
					'default'     => $brik_layers_default,
					'fields'      => array(
						'kind'   => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'image', 'options' => Fields::opts( array( 'image' => __( 'Image', 'brik-builder' ), 'blob' => __( 'Color blob', 'brik-builder' ), 'text' => __( 'Text', 'brik-builder' ) ) ) ) ),
						'image'  => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => 'image' ) ) ),
						'text'   => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => 'text' ) ) ),
						'color'  => Fields::field( 'color', __( 'Color', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => array( 'blob', 'text' ) ) ) ),
						'cover'  => Fields::field( 'toggle', __( 'Fill the box', 'brik-builder' ), 'content', array( 'show_if' => array( 'kind' => 'image' ) ) ),
						'x'      => Fields::field( 'range', __( 'Horizontal position (%)', 'brik-builder' ), 'content', array( 'default' => 50, 'min' => -20, 'max' => 120 ) ),
						'y'      => Fields::field( 'range', __( 'Vertical position (%)', 'brik-builder' ), 'content', array( 'default' => 50, 'min' => -20, 'max' => 120 ) ),
						'width'  => Fields::field( 'range', __( 'Width (%)', 'brik-builder' ), 'content', array( 'default' => 30, 'min' => 2, 'max' => 120 ) ),
						'depth'  => Fields::field( 'range', __( 'Depth', 'brik-builder' ), 'content', array( 'default' => 0.5, 'min' => -1, 'max' => 2, 'step' => 0.05, 'description' => __( '0 stays put, higher values feel closer and move more.', 'brik-builder' ) ) ),
						'rotate' => Fields::field( 'range', __( 'Rotate', 'brik-builder' ), 'content', array( 'default' => 0, 'min' => -45, 'max' => 45, 'unit' => 'deg' ) ),
						'mobile' => Fields::field( 'toggle', __( 'Show on phones', 'brik-builder' ), 'content', array( 'default' => true ) ),
					),
				)
			),
			'eyebrow'       => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content', array( 'default' => __( 'Field journal', 'brik-builder' ), 'inline' => true ) ),
			'title'         => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Go further than the map', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'     => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'p' => 'p' ) ) ) ),
			'text'          => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Guided routes, wild camps and the stories behind them. New expeditions every season.', 'brik-builder' ) ) ),
			'button_text'   => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Explore routes', 'brik-builder' ) ) ),
			'link'          => Fields::field( 'link', __( 'Button link', 'brik-builder' ), 'content', array( 'default' => array( 'url' => '#' ), 'show_if' => array( 'button_text' => '!' ) ) ),
			'mouse'         => Fields::field( 'toggle', __( 'Follow the pointer', 'brik-builder' ), 'motion', array( 'group_label' => __( 'Motion', 'brik-builder' ), 'default' => true ) ),
			'scroll'        => Fields::field( 'toggle', __( 'Move on scroll', 'brik-builder' ), 'motion', array( 'group_label' => __( 'Motion', 'brik-builder' ), 'default' => true ) ),
			'intensity'     => Fields::field( 'range', __( 'Intensity', 'brik-builder' ), 'motion', array( 'group_label' => __( 'Motion', 'brik-builder' ), 'default' => 1, 'min' => 0, 'max' => 2, 'step' => 0.1 ) ),
			'box_height'    => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'box_style', array( 'tab' => 'design', 'group_label' => __( 'Box', 'brik-builder' ), 'default' => '680px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-pl', 'height' ) ) ),
			'rounded'       => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'box_style', array( 'tab' => 'design', 'group_label' => __( 'Box', 'brik-builder' ), 'default' => '2xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'content_color' => Fields::field( 'color', __( 'Content color', 'brik-builder' ), 'box_style', array( 'tab' => 'design', 'group_label' => __( 'Box', 'brik-builder' ), 'default' => '#ffffff', 'css' => array( Fields::WRAP . ' .brik-pl-content', 'color' ) ) ),
			'shade'         => Fields::field( 'color', __( 'Shade over the layers', 'brik-builder' ), 'box_style', array( 'tab' => 'design', 'group_label' => __( 'Box', 'brik-builder' ), 'default' => 'rgb(0 0 0 / 0.35)', 'css' => array( Fields::WRAP . ' .brik-pl-shade', 'background-color' ) ) ),
		),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-pl-title' ),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-pl-text' ),
		Fields::typography( 'layer', __( 'Text layers', 'brik-builder' ), Fields::WRAP . ' .brik-pl-word' )
	),
	'render'      => static function ( $a, $ctx ) {
		$ctx->script( 'parallax-layers' );

		$layers = '';
		foreach ( brik_3d_rows( $a['layers'] ) as $i => $layer ) {
			$layer = array_merge( array( 'kind' => 'image', 'image' => '', 'text' => '', 'color' => '', 'x' => 50, 'y' => 50, 'width' => 30, 'depth' => 0.5, 'rotate' => 0, 'cover' => false, 'mobile' => true ), $layer );
			$depth = brik_3d_num( $layer['depth'], 0.5, -1, 2 );
			$cover = 'image' === $layer['kind'] && ! empty( $layer['cover'] );
			$vars  = array(
				'--x'     => brik_3d_num( $layer['x'], 50, -20, 120 ) . '%',
				'--y'     => brik_3d_num( $layer['y'], 50, -20, 120 ) . '%',
				'--w'     => brik_3d_num( $layer['width'], 30, 2, 120 ) . '%',
				'--r'     => brik_3d_num( $layer['rotate'], 0, -45, 45 ) . 'deg',
				'--c'     => brik_3d_color( $layer['color'] ),
				'z-index' => (string) ( $i + 1 ),
			);
			if ( 'blob' === $layer['kind'] ) {
				$inner = '<span class="brik-pl-blob"></span>';
			} elseif ( 'text' === $layer['kind'] ) {
				if ( '' === (string) $layer['text'] ) {
					continue;
				}
				$inner = '<span class="brik-pl-word font-heading text-5xl font-black tracking-tighter whitespace-nowrap md:text-8xl">' . brik_inline( $layer['text'] ) . '</span>';
			} else {
				$inner = brik_image( $layer['image'], $cover ? 'full' : 'large', array( 'class' => $cover ? 'h-full w-full object-cover' : 'brik-pl-card block h-auto w-full rounded-xl shadow-2xl ring-1 ring-white/20', 'alt' => '', 'loading' => $cover ? 'eager' : 'lazy' ) );
				if ( '' === $inner ) {
					continue;
				}
			}
			$layers .= sprintf(
				'<div class="%1$s" data-depth="%2$s"%3$s aria-hidden="true">%4$s</div>',
				esc_attr( brik_cls( 'brik-pl-layer', $cover ? 'brik-pl-layer--cover' : 'brik-pl-layer--' . $layer['kind'], empty( $layer['mobile'] ) ? 'max-md:hidden' : '' ) ),
				esc_attr( (string) $depth ),
				brik_3d_vars( $vars ),
				$inner
			);
		}

		$tag     = in_array( $a['title_tag'], array( 'h1', 'h2', 'h3', 'p' ), true ) ? $a['title_tag'] : 'h2';
		$content = '';
		if ( '' !== (string) $a['eyebrow'] ) {
			$content .= '<p class="brik-pl-eyebrow mb-4 text-sm font-semibold tracking-[0.2em] uppercase opacity-80"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== (string) $a['title'] ) {
			$content .= sprintf( '<%1$s class="brik-pl-title font-heading text-4xl leading-[1.05] font-bold tracking-tight text-balance md:text-7xl"%3$s>%2$s</%1$s>', $tag, brik_inline( $a['title'] ), $ctx->inline( 'title' ) );
		}
		if ( '' !== (string) $a['text'] ) {
			$content .= '<p class="brik-pl-text mx-auto mt-5 max-w-xl text-base opacity-85 md:text-lg">' . esc_html( $a['text'] ) . '</p>';
		}
		if ( '' !== (string) $a['button_text'] && ! empty( $a['link']['url'] ) ) {
			$content .= '<div class="mt-8"><a' . brik_link_attrs( $a['link'], array( 'class' => 'brik-pl-button inline-flex h-11 items-center justify-center gap-2 rounded-full bg-white px-7 text-sm font-medium whitespace-nowrap text-neutral-900 shadow-lg transition-transform outline-none hover:scale-[1.03] focus-visible:ring-[3px] focus-visible:ring-white/50' ) ) . '>' . brik_inline( $a['button_text'] ) . brik_icon( 'arrow-right', 'size-4' ) . '</a></div>';
		}

		return sprintf(
			'<div class="%1$s" data-mouse="%2$d" data-scroll="%3$d" data-intensity="%4$s"><div class="brik-pl-layers" aria-hidden="true">%5$s</div><div class="brik-pl-shade" aria-hidden="true"></div>%6$s</div>',
			esc_attr( brik_cls( 'brik-pl relative isolate h-[680px] overflow-hidden bg-neutral-950', brik_radius_class( $a['rounded'] ) ) ),
			! empty( $a['mouse'] ) ? 1 : 0,
			! empty( $a['scroll'] ) ? 1 : 0,
			esc_attr( (string) brik_3d_num( $a['intensity'], 1, 0, 2 ) ),
			$layers,
			'' !== $content ? '<div class="brik-pl-content relative flex h-full flex-col items-center justify-center px-6 text-center text-white">' . $content . '</div>' : ''
		);
	},
);
