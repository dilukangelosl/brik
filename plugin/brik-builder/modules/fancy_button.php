<?php
/**
 * Fancy button: call-to-action link with an animated style.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'fancy_button',
	'title'       => __( 'Fancy Button', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'mouse-pointer-click',
	'description' => 'Animated call-to-action link. style: shimmer (light orbits a dark pill) | rainbow (animated rainbow border and glow) | moving_border (glowing dot travels the border) | glow_pulse (pulsing halo) | magnetic (follows the cursor slightly) | ripple (ripple from the click point) | gradient_fill (flowing gradient background) | shiny (sheen sweeps across) | arrow_slide (dot expands to fill, arrow slides in). text, link, icon (Lucide name), icon_position: start|end, size: sm|default|lg|xl, align: left|center|right, full: bool, color_1/color_2: effect colors, shape: pill|rounded.',
	'fields'      => array_merge(
		array(
			'text'          => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Get started', 'brik-builder' ), 'inline' => true ) ),
			'link'          => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content', array( 'default' => array( 'url' => '#' ) ) ),
			'style'         => Fields::field(
				'select',
				__( 'Style', 'brik-builder' ),
				'content',
				array(
					'default' => 'shimmer',
					'options' => Fields::opts(
						array(
							'shimmer'       => __( 'Shimmer', 'brik-builder' ),
							'rainbow'       => __( 'Rainbow', 'brik-builder' ),
							'moving_border' => __( 'Moving border', 'brik-builder' ),
							'glow_pulse'    => __( 'Glow pulse', 'brik-builder' ),
							'magnetic'      => __( 'Magnetic', 'brik-builder' ),
							'ripple'        => __( 'Ripple', 'brik-builder' ),
							'gradient_fill' => __( 'Gradient fill', 'brik-builder' ),
							'shiny'         => __( 'Shiny sweep', 'brik-builder' ),
							'arrow_slide'   => __( 'Arrow slide', 'brik-builder' ),
						)
					),
				)
			),
			'icon'          => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'arrow-right' ) ),
			'icon_position' => Fields::field( 'select', __( 'Icon position', 'brik-builder' ), 'content', array( 'default' => 'end', 'options' => Fields::opts( array( 'start' => __( 'Before text', 'brik-builder' ), 'end' => __( 'After text', 'brik-builder' ) ) ), 'show_if' => array( 'icon' => '!' ) ) ),
			'size'          => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'shape'         => Fields::field( 'select', __( 'Shape', 'brik-builder' ), 'content', array( 'default' => 'pill', 'options' => Fields::opts( array( 'pill' => __( 'Pill', 'brik-builder' ), 'rounded' => __( 'Rounded', 'brik-builder' ) ) ) ) ),
			'full'          => Fields::field( 'toggle', __( 'Full width', 'brik-builder' ), 'content' ),
			'align'         => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
			'color_1'       => Fields::field( 'color', __( 'Effect color 1', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#6366f1' ) ),
			'color_2'       => Fields::field( 'color', __( 'Effect color 2', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#ec4899' ) ),
		),
		Fields::box( 'button', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-fxb' ),
		Fields::typography( 'button', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-fxb' )
	),
	'render'      => static function ( $a, $ctx ) {
		$styles = array( 'shimmer', 'rainbow', 'moving_border', 'glow_pulse', 'magnetic', 'ripple', 'gradient_fill', 'shiny', 'arrow_slide' );
		$style  = in_array( $a['style'], $styles, true ) ? $a['style'] : 'shimmer';
		$sizes  = array(
			'sm'      => 'h-8 px-4 text-xs gap-1.5',
			'default' => 'h-10 px-5 text-sm gap-2',
			'lg'      => 'h-11 px-7 text-sm gap-2',
			'xl'      => 'h-13 px-9 text-base gap-2.5',
		);
		$size   = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['lg'];

		$icon  = ! empty( $a['icon'] ) ? brik_icon( $a['icon'], 'brik-fxb-icon size-4 shrink-0' ) : '';
		$label = '<span class="brik-fxb-text"' . $ctx->inline( 'text' ) . '>' . brik_inline( $a['text'] ) . '</span>';

		if ( 'arrow_slide' === $style ) {
			// The resting label and the hover label are swapped; the second copy is decorative.
			$arrow = brik_icon( ! empty( $a['icon'] ) ? $a['icon'] : 'arrow-right', 'size-4 shrink-0' );
			$inner = '<span class="brik-fxb-dot" aria-hidden="true"></span><span class="brik-fxb-label">' . $label . '</span>'
				. '<span class="brik-fxb-hover" aria-hidden="true"><span>' . esc_html( wp_strip_all_tags( $a['text'] ) ) . '</span>' . $arrow . '</span>';
		} else {
			$content = 'start' === $a['icon_position'] ? $icon . $label : $label . $icon;
			$inner   = '<span class="brik-fxb-label">' . $content . '</span>';
			$deco    = array(
				'shimmer'       => '<span class="brik-fxb-spark" aria-hidden="true"><span></span></span><span class="brik-fxb-backdrop" aria-hidden="true"></span>',
				'rainbow'       => '<span class="brik-fxb-rainbow" aria-hidden="true"></span>',
				'moving_border' => '<span class="brik-fxb-orbit" aria-hidden="true"><span></span></span>',
				'shiny'         => '<span class="brik-fxb-sheen" aria-hidden="true"></span>',
			);
			if ( isset( $deco[ $style ] ) ) {
				$inner = $deco[ $style ] . $inner;
			}
		}

		if ( in_array( $style, array( 'magnetic', 'ripple' ), true ) ) {
			$ctx->script( 'fancy-button' );
		}

		$class = brik_cls(
			'brik-fxb brik-fxb--' . str_replace( '_', '-', $style ) . ' relative isolate inline-flex shrink-0 items-center justify-center font-medium whitespace-nowrap no-underline outline-none cursor-pointer select-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
			$size,
			'rounded' === $a['shape'] ? 'rounded-lg' : 'rounded-full',
			! empty( $a['full'] ) ? 'w-full' : ''
		);
		$attrs = array(
			'class'             => $class,
			'data-brik-fxb'     => $style,
			'style'             => brik_fx_vars(
				array(
					'--brik-fxb-c1' => $a['color_1'],
					'--brik-fxb-c2' => $a['color_2'],
				)
			),
		);
		if ( '' === $attrs['style'] ) {
			unset( $attrs['style'] );
		}
		if ( in_array( $style, array( 'shimmer', 'rainbow', 'moving_border', 'glow_pulse', 'gradient_fill', 'shiny' ), true ) ) {
			$attrs['data-brik-fx-pause'] = true;
		}
		return '<a' . brik_link_attrs( $a['link'], $attrs ) . '>' . $inner . '</a>';
	},
);
