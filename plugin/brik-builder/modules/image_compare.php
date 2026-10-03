<?php
/**
 * Before / after image comparison slider.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'image_compare',
	'title'       => __( 'Before / After', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'square-split-horizontal',
	'description' => 'Before/after comparison with a draggable, keyboard accessible handle. before, after: images (when both are the same image the "before" side is shown in grayscale). before_label, after_label: text (empty hides). start: 0-100 initial divider position. orientation: horizontal|vertical. ratio: auto|16:9|4:3|3:2|1:1… rounded: none|sm|md|lg|xl|2xl.',
	'fields'      => array_merge(
		array(
			'before'       => Fields::field( 'image', __( 'Before image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1500964757637-c85e8a162699', 1600, 1000 ), 'alt' => __( 'Landscape before editing', 'brik-builder' ) ) ) ),
			'after'        => Fields::field( 'image', __( 'After image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1500964757637-c85e8a162699', 1600, 1000 ), 'alt' => __( 'Landscape after editing', 'brik-builder' ) ) ) ),
			'before_label' => Fields::field( 'text', __( 'Before label', 'brik-builder' ), 'content', array( 'default' => __( 'Before', 'brik-builder' ) ) ),
			'after_label'  => Fields::field( 'text', __( 'After label', 'brik-builder' ), 'content', array( 'default' => __( 'After', 'brik-builder' ) ) ),
			'start'        => Fields::field( 'range', __( 'Start position', 'brik-builder' ), 'content', array( 'default' => 50, 'min' => 0, 'max' => 100, 'unit' => '%' ) ),
			'orientation'  => Fields::field( 'select', __( 'Direction', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Side by side', 'brik-builder' ), 'vertical' => __( 'Top and bottom', 'brik-builder' ) ) ) ) ),
			'ratio'        => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => '16:9', 'options' => Fields::opts( brik_aspect_options() ) ) ),
			'rounded'      => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'compare_style', array( 'tab' => 'design', 'group_label' => __( 'Frame', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'line_color'   => Fields::field( 'color', __( 'Divider color', 'brik-builder' ), 'compare_style', array( 'tab' => 'design', 'group_label' => __( 'Frame', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-compare', '--brik-line' ) ) ),
		),
		Fields::box( 'handle', __( 'Handle', 'brik-builder' ), Fields::WRAP . ' .brik-compare-handle', array( 'bg', 'color', 'border_color', 'shadow' ) ),
		Fields::box( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-compare-label', array( 'bg', 'color', 'radius', 'padding' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$before = brik_media_items( $a['before'] );
		$after  = brik_media_items( $a['after'] );
		if ( ! $before || ! $after ) {
			return $ctx->placeholder( __( 'Choose a before and an after image', 'brik-builder' ) );
		}
		$before   = $before[0];
		$after    = $after[0];
		$vertical = 'vertical' === $a['orientation'];
		$start    = max( 0, min( 100, '' === $a['start'] ? 50 : (float) $a['start'] ) );
		$ratio    = brik_aspect_class( $a['ratio'] );
		$same     = $before['src'] === $after['src'];
		$img      = $ratio ? 'absolute inset-0 h-full w-full object-cover' : 'block h-auto w-full';

		$after_img  = brik_media_img( $after, 'large', array( 'class' => 'brik-compare-after pointer-events-none select-none ' . $img, 'draggable' => 'false' ) );
		$before_img = brik_media_img( $before, 'large', array( 'class' => brik_cls( 'brik-compare-before pointer-events-none absolute inset-0 h-full w-full select-none object-cover', array( 'grayscale' => $same ) ), 'draggable' => 'false' ) );

		$label = 'brik-compare-label pointer-events-none absolute z-10 rounded-md bg-black/55 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm';
		$labels = '';
		if ( '' !== $a['before_label'] ) {
			$labels .= '<span class="' . esc_attr( $label . ( $vertical ? ' top-3 left-3' : ' top-3 left-3' ) ) . '">' . brik_inline( $a['before_label'] ) . '</span>';
		}
		if ( '' !== $a['after_label'] ) {
			$labels .= '<span class="' . esc_attr( $label . ( $vertical ? ' bottom-3 left-3' : ' top-3 right-3' ) ) . '">' . brik_inline( $a['after_label'] ) . '</span>';
		}

		$icon = $vertical ? brik_icon( 'chevron-left', 'size-4 rotate-90' ) . brik_icon( 'chevron-right', 'size-4 rotate-90' ) : brik_icon( 'chevron-left', 'size-4' ) . brik_icon( 'chevron-right', 'size-4' );

		return sprintf(
			'<div class="%1$s" style="--brik-pos:%2$s%%" data-brik-compare>%3$s<div class="brik-compare-clip absolute inset-0">%4$s</div>%5$s<div class="brik-compare-line pointer-events-none absolute z-10" aria-hidden="true"><span class="brik-compare-handle absolute top-1/2 left-1/2 inline-flex size-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-white/70 bg-white text-neutral-900 shadow-lg %6$s">%7$s</span></div><input type="range" class="brik-compare-range absolute inset-0 z-20 m-0 h-full w-full pointer-events-none opacity-0" min="0" max="100" step="1" value="%11$s" aria-label="%9$s"%10$s></div>',
			esc_attr( brik_cls( 'brik-compare group relative isolate w-full overflow-hidden bg-muted select-none', $vertical ? 'brik-compare--v cursor-ns-resize touch-pan-x' : 'brik-compare--h cursor-ew-resize touch-pan-y', $ratio, brik_radius_class( $a['rounded'] ) ) ),
			esc_attr( $start ),
			$after_img,
			$before_img,
			$labels,
			$vertical ? 'flex-col' : '',
			$icon,
			'',
			esc_attr__( 'Comparison position', 'brik-builder' ),
			$vertical ? ' aria-orientation="vertical"' : '',
			esc_attr( $vertical ? 100 - $start : $start )
		);
	},
);
