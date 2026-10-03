<?php
/**
 * Stats: key numbers in a grid.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'stats',
	'title'       => __( 'Stats', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'chart-no-axes-column',
	'description' => 'Grid of key numbers. items repeater [{value (e.g. "$45,231" or "99.9%"), label, description, icon (optional), trend: none|up|down, trend_value (e.g. "+20.1%")}]. columns: 1-6 (responsive). style: card|plain|divided. align: left|center.',
	'fields'      => array_merge(
		array(
			'items'   => Fields::field(
				'repeater',
				__( 'Stats', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'fields'      => array(
						'value'       => Fields::field( 'text', __( 'Value', 'brik-builder' ) ),
						'label'       => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'description' => Fields::field( 'text', __( 'Description', 'brik-builder' ) ),
						'icon'        => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'trend'       => Fields::field( 'select', __( 'Trend', 'brik-builder' ), 'content', array( 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'None', 'brik-builder' ), 'up' => __( 'Up', 'brik-builder' ), 'down' => __( 'Down', 'brik-builder' ) ) ) ) ),
						'trend_value' => Fields::field( 'text', __( 'Trend value', 'brik-builder' ), 'content', array( 'placeholder' => '+12%' ) ),
					),
					'default'     => array(
						array( 'value' => '$45,231', 'label' => __( 'Total revenue', 'brik-builder' ), 'description' => __( 'from last month', 'brik-builder' ), 'icon' => 'dollar-sign', 'trend' => 'up', 'trend_value' => '+20.1%' ),
						array( 'value' => '2,350', 'label' => __( 'New customers', 'brik-builder' ), 'description' => __( 'from last month', 'brik-builder' ), 'icon' => 'users', 'trend' => 'up', 'trend_value' => '+18.0%' ),
						array( 'value' => '99.98%', 'label' => __( 'Uptime', 'brik-builder' ), 'description' => __( 'over the last 90 days', 'brik-builder' ), 'icon' => 'activity', 'trend' => 'none', 'trend_value' => '' ),
						array( 'value' => '1.2s', 'label' => __( 'Avg. load time', 'brik-builder' ), 'description' => __( 'faster than last quarter', 'brik-builder' ), 'icon' => 'gauge', 'trend' => 'down', 'trend_value' => '-0.4s' ),
					),
				)
			),
			'columns' => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 6, 'responsive' => true ) ),
			'style'   => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Cards', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ), 'divided' => __( 'Divided', 'brik-builder' ) ) ) ) ),
			'align'   => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ) ) ),
			'invert_down' => Fields::field( 'toggle', __( 'Down is good', 'brik-builder' ), 'content', array( 'default' => true, 'description' => __( 'Show downward trends in green (e.g. load time, costs).', 'brik-builder' ) ) ),
			'gap'     => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'stat', array( 'tab' => 'design', 'group_label' => __( 'Stat', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-stats-grid', 'gap' ) ) ),
			'icon_color' => Fields::field( 'color', __( 'Icon color', 'brik-builder' ), 'stat', array( 'tab' => 'design', 'group_label' => __( 'Stat', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-stat-icon', 'color' ) ) ),
		),
		Fields::box( 'stat', __( 'Stat', 'brik-builder' ), Fields::WRAP . ' .brik-stat' ),
		Fields::typography( 'value', __( 'Value', 'brik-builder' ), Fields::WRAP . ' .brik-stat-value' ),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-stat-label' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-stat-desc' )
	),
	'css'         => static function ( $a, $wrap ) {
		return brik_grid_css( $a, $wrap . ' .brik-stats-grid', 'columns', 4, 2, 1 );
	},
	'render'      => static function ( $a, $ctx ) {
		$items = brik_items( $a['items'] );
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add stats', 'brik-builder' ) );
		}
		$style  = in_array( $a['style'], array( 'card', 'plain', 'divided' ), true ) ? $a['style'] : 'card';
		$center = 'center' === $a['align'];
		$card   = 'card' === $style;

		$html = '';
		foreach ( $items as $item ) {
			$trend = brik_item( $item, 'trend', 'none' );
			$tval  = brik_item( $item, 'trend_value' );
			$meta  = '';
			if ( in_array( $trend, array( 'up', 'down' ), true ) && '' !== $tval ) {
				$good  = 'up' === $trend || ! empty( $a['invert_down'] );
				$meta .= '<span class="' . esc_attr( brik_cls( 'brik-stat-trend inline-flex items-center gap-0.5 font-medium', $good ? 'text-emerald-600 dark:text-emerald-400' : 'text-destructive' ) ) . '">' . brik_icon( 'up' === $trend ? 'trending-up' : 'trending-down', 'size-3.5' ) . esc_html( $tval ) . '</span>';
			}
			if ( '' !== brik_item( $item, 'description' ) ) {
				$meta .= '<span>' . brik_inline( $item['description'] ) . '</span>';
			}

			$icon  = brik_item( $item, 'icon' ) ? brik_icon( $item['icon'], 'brik-stat-icon size-4 shrink-0 text-muted-foreground' ) : '';
			$label = '' !== brik_item( $item, 'label' ) ? '<span class="' . esc_attr( $card ? 'brik-stat-label text-sm font-medium' : 'brik-stat-label text-sm font-medium text-muted-foreground' ) . '">' . brik_inline( $item['label'] ) . '</span>' : '';
			$value = '<span class="' . esc_attr( brik_cls( 'brik-stat-value font-heading font-bold tracking-tight tabular-nums', $card ? 'text-3xl' : ( 'divided' === $style ? 'text-3xl md:text-4xl' : 'text-4xl md:text-5xl' ) ) ) . '">' . brik_inline( brik_item( $item, 'value' ) ) . '</span>';
			$meta  = '' !== $meta ? '<span class="' . esc_attr( brik_cls( 'brik-stat-desc flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground', array( 'justify-center' => $center ) ) ) . '">' . $meta . '</span>' : '';

			if ( $card ) {
				$inner = '<div class="' . esc_attr( brik_cls( 'flex items-center gap-2', $center ? 'justify-center' : 'justify-between' ) ) . '">' . $label . $icon . '</div>' . $value . $meta;
			} else {
				$inner = ( '' !== $icon ? '<span class="' . esc_attr( brik_cls( 'mb-2 flex', array( 'justify-center' => $center ) ) ) . '">' . brik_icon_shape( $item['icon'], 'rounded', 'soft', 'default', 'brik-stat-icon' ) . '</span>' : '' ) . $value . $label . $meta;
			}

			$html .= '<div class="' . esc_attr(
				brik_cls(
					'brik-stat flex flex-col gap-1',
					array(
						'rounded-xl border bg-card p-6 text-card-foreground shadow-sm' => $card,
						'bg-background p-6'        => 'divided' === $style,
						'items-center text-center' => $center,
					)
				)
			) . '">' . $inner . '</div>';
		}
		$grids = array(
			'card'    => 'gap-4',
			'plain'   => 'gap-x-6 gap-y-10',
			'divided' => 'gap-px overflow-hidden rounded-xl border bg-border',
		);
		return '<div class="' . esc_attr( brik_cls( 'brik-stats-grid grid', $grids[ $style ] ) ) . '">' . $html . '</div>';
	},
);
