<?php
/**
 * Progress bars (Divi bar counters) in the shadcn progress style.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'progress_bars',
	'title'       => __( 'Progress Bars', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'chart-bar',
	'description' => 'Labelled progress bars. bars repeater [{label, percent (0-100), color (optional)}]. show_percent: toggle. percent_position: top|inside. bar_height: CSS length (8px). animate: toggle fills bars when scrolled into view. style: default|soft|striped.',
	'fields'      => array_merge(
		array(
			'bars'             => Fields::field(
				'repeater',
				__( 'Bars', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'fields'      => array(
						'label'   => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'percent' => Fields::field( 'range', __( 'Percent', 'brik-builder' ), 'content', array( 'min' => 0, 'max' => 100, 'default' => 50 ) ),
						'color'   => Fields::field( 'color', __( 'Color', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'label' => __( 'Design', 'brik-builder' ), 'percent' => 92 ),
						array( 'label' => __( 'Development', 'brik-builder' ), 'percent' => 85 ),
						array( 'label' => __( 'Strategy', 'brik-builder' ), 'percent' => 74 ),
						array( 'label' => __( 'Copywriting', 'brik-builder' ), 'percent' => 60 ),
					),
				)
			),
			'show_percent'     => Fields::field( 'toggle', __( 'Show percentage', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'percent_position' => Fields::field( 'select', __( 'Percentage position', 'brik-builder' ), 'content', array( 'default' => 'top', 'show_if' => array( 'show_percent' => true ), 'options' => Fields::opts( array( 'top' => __( 'Above the bar', 'brik-builder' ), 'inside' => __( 'Inside the bar', 'brik-builder' ) ) ) ) ),
			'style'            => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'default' => __( 'Default', 'brik-builder' ), 'soft' => __( 'Soft', 'brik-builder' ), 'striped' => __( 'Striped', 'brik-builder' ) ) ) ) ),
			'animate'          => Fields::field( 'toggle', __( 'Animate on scroll', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'bar_height'       => Fields::field( 'unit', __( 'Bar height', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'responsive' => true, 'placeholder' => '8px', 'css' => array( Fields::WRAP . ' .brik-progress-track', 'height' ) ) ),
			'bar_color'        => Fields::field( 'color', __( 'Bar color', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-progress', '--brik-bar' ) ) ),
			'track_color'      => Fields::field( 'color', __( 'Track color', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-progress-track', 'background-color' ) ) ),
			'bar_radius'       => Fields::field( 'unit', __( 'Corner radius', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'css' => array( Fields::WRAP . ' :is(.brik-progress-track,.brik-progress-bar)', 'border-radius' ) ) ),
			'gap'              => Fields::field( 'unit', __( 'Space between bars', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-progress-list', 'gap' ) ) ),
			'duration'         => Fields::field( 'range', __( 'Animation duration (ms)', 'brik-builder' ), 'bar', array( 'tab' => 'design', 'group_label' => __( 'Bars', 'brik-builder' ), 'min' => 200, 'max' => 4000, 'step' => 100, 'show_if' => array( 'animate' => true ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-progress-list', 'prop' => '--brik-bar-ms', 'value' => '{{v}}ms' ) ) ),
		),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-progress-label' ),
		Fields::typography( 'percent', __( 'Percentage', 'brik-builder' ), Fields::WRAP . ' .brik-progress-value' )
	),
	'render'      => static function ( $a, $ctx ) {
		$bars = brik_items( $a['bars'] );
		if ( ! $bars ) {
			return $ctx->placeholder( __( 'Add progress bars', 'brik-builder' ) );
		}
		$show   = ! empty( $a['show_percent'] );
		$inside = $show && 'inside' === $a['percent_position'];
		$tracks = array(
			'default' => 'bg-primary/20',
			'soft'    => 'bg-muted',
			'striped' => 'bg-primary/15',
		);
		$track  = isset( $tracks[ $a['style'] ] ) ? $tracks[ $a['style'] ] : $tracks['default'];

		$html = '';
		foreach ( $bars as $bar ) {
			$pct   = max( 0, min( 100, (float) brik_item( $bar, 'percent', 0 ) ) );
			$label = brik_item( $bar, 'label' );
			$value = '<span class="' . esc_attr( $inside ? 'brik-progress-value tabular-nums' : 'brik-progress-value tabular-nums text-muted-foreground' ) . '" data-brik-pct="' . esc_attr( $pct ) . '">' . esc_html( round( $pct ) ) . '%</span>';
			$style = '--brik-pct:' . $pct . '%';
			if ( '' !== brik_item( $bar, 'color' ) ) {
				$style .= ';--brik-bar:' . Brik\Style::clean( $bar['color'] );
			}

			$head = '';
			if ( '' !== $label || ( $show && ! $inside ) ) {
				$head = '<div class="flex items-baseline justify-between gap-4 text-sm"><span class="brik-progress-label font-medium">' . brik_inline( $label ) . '</span>' . ( $show && ! $inside ? $value : '' ) . '</div>';
			}
			$fill = '<div class="' . esc_attr( brik_cls( 'brik-progress-bar h-full rounded-full bg-[var(--brik-bar,var(--primary))]', array( 'brik-progress-bar--striped' => 'striped' === $a['style'], 'flex items-center justify-end px-2 text-[11px] font-semibold text-primary-foreground' => $inside ) ) ) . '">' . ( $inside ? $value : '' ) . '</div>';

			$html .= '<div class="brik-progress grid gap-2" style="' . esc_attr( $style ) . '">' . $head
				. '<div class="' . esc_attr( brik_cls( 'brik-progress-track relative w-full overflow-hidden rounded-full', $track, $inside ? 'h-5' : 'h-2' ) ) . '" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( round( $pct ) ) . '"' . ( '' !== $label ? ' aria-label="' . esc_attr( wp_strip_all_tags( (string) $label ) ) . '"' : '' ) . '>' . $fill . '</div>'
				. '</div>';
		}
		$animate = ! empty( $a['animate'] ) && ! $ctx->canvas;
		return '<div class="' . esc_attr( brik_cls( 'brik-progress-list grid gap-5', array( 'brik-progress--animate' => $animate ) ) ) . '">' . $html . '</div>';
	},
);
