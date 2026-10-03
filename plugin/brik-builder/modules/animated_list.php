<?php
/**
 * Animated list: notification-style cards that pop in one after another.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'animated_list',
	'title'       => __( 'Animated List', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'bell-ring',
	'description' => 'Notification feed where items pop in one by one at the top, pushing older ones down. items: repeater [{icon (Lucide or brand:slug), color (icon background), title, text, time}]. interval: ms between items. loop: bool, keep cycling. list_height: fixed box height (CSS length), list_width: max width, so nothing around it moves. align: left|center. Without JavaScript or with reduced motion all items are listed statically.',
	'fields'      => array_merge(
		array(
			'items'    => Fields::field(
				'repeater',
				__( 'Items', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'fields'      => array(
						'icon'  => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'color' => Fields::field( 'color', __( 'Icon background', 'brik-builder' ) ),
						'title' => Fields::field( 'text', __( 'Title', 'brik-builder' ) ),
						'text'  => Fields::field( 'text', __( 'Text', 'brik-builder' ) ),
						'time'  => Fields::field( 'text', __( 'Time', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'icon' => 'credit-card', 'color' => '#00C9A7', 'title' => __( 'Payment received', 'brik-builder' ), 'text' => __( '$1,240.00 from Northwind Ltd.', 'brik-builder' ), 'time' => __( '2m ago', 'brik-builder' ) ),
						array( 'icon' => 'user-plus', 'color' => '#FFB800', 'title' => __( 'New signup', 'brik-builder' ), 'text' => __( 'Maya joined the Pro plan', 'brik-builder' ), 'time' => __( '5m ago', 'brik-builder' ) ),
						array( 'icon' => 'message-square', 'color' => '#FF3D71', 'title' => __( 'New message', 'brik-builder' ), 'text' => __( '“Love the new dashboard!”', 'brik-builder' ), 'time' => __( '9m ago', 'brik-builder' ) ),
						array( 'icon' => 'rocket', 'color' => '#1E86FF', 'title' => __( 'Deploy finished', 'brik-builder' ), 'text' => __( 'brik.dev is live in 38 regions', 'brik-builder' ), 'time' => __( '12m ago', 'brik-builder' ) ),
						array( 'icon' => 'star', 'color' => '#8B5CF6', 'title' => __( 'New review', 'brik-builder' ), 'text' => __( '5 stars from Lumen Studio', 'brik-builder' ), 'time' => __( '20m ago', 'brik-builder' ) ),
					),
				)
			),
			'interval' => Fields::field( 'range', __( 'Interval (ms)', 'brik-builder' ), 'content', array( 'default' => 1600, 'min' => 400, 'max' => 6000, 'step' => 100 ) ),
			'loop'     => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'list_height' => Fields::field( 'unit', __( 'Height', 'brik-builder' ), 'content', array( 'default' => '26rem', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-alist', 'height' ) ) ),
			'list_width'  => Fields::field( 'unit', __( 'Max width', 'brik-builder' ), 'content', array( 'default' => '420px', 'css' => array( Fields::WRAP . ' .brik-alist', 'max-width' ) ) ),
			'align'    => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ) ) ),
		),
		Fields::box( 'item', __( 'Item', 'brik-builder' ), Fields::WRAP . ' .brik-alist-item' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-alist-title' ),
		Fields::typography( 'body', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-alist-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$rows = '';
		foreach ( brik_items( $a['items'] ) as $item ) {
			$title = trim( (string) brik_item( $item, 'title' ) );
			$text  = trim( (string) brik_item( $item, 'text' ) );
			if ( '' === $title && '' === $text ) {
				continue;
			}
			$icon  = brik_item( $item, 'icon' ) ? brik_icon( $item['icon'], 'size-5' ) : '';
			$color = brik_fx_css_value( brik_item( $item, 'color' ) );
			$time  = trim( (string) brik_item( $item, 'time' ) );
			$rows .= '<li class="brik-alist-item flex items-center gap-3 rounded-2xl border bg-card p-3.5 text-card-foreground">'
				. ( '' !== $icon ? '<span class="brik-alist-icon flex size-10 shrink-0 items-center justify-center rounded-xl text-white" style="' . esc_attr( 'background:' . ( '' !== $color ? $color : 'var(--primary)' ) ) . '">' . $icon . '</span>' : '' )
				. '<span class="flex min-w-0 flex-col">'
				. '<span class="flex items-center gap-1.5 text-sm"><span class="brik-alist-title truncate font-medium">' . brik_inline( $title ) . '</span>'
				. ( '' !== $time ? '<span class="text-muted-foreground" aria-hidden="true">·</span><span class="brik-alist-time shrink-0 text-xs text-muted-foreground">' . esc_html( wp_strip_all_tags( $time ) ) . '</span>' : '' ) . '</span>'
				. ( '' !== $text ? '<span class="brik-alist-text truncate text-sm text-muted-foreground">' . brik_inline( $text ) . '</span>' : '' )
				. '</span></li>';
		}
		if ( '' === $rows ) {
			return $ctx->placeholder( __( 'Add list items', 'brik-builder' ) );
		}
		$ctx->script( 'animated-list' );
		return '<div class="' . esc_attr( brik_cls( 'brik-alist relative h-[26rem] w-full max-w-[420px] overflow-hidden', 'left' === $a['align'] ? '' : 'mx-auto' ) ) . '" data-interval="' . esc_attr( (string) (int) brik_fx_num( $a['interval'], 1600, 400, 6000 ) ) . '" data-loop="' . ( ! empty( $a['loop'] ) ? '1' : '0' ) . '">'
			. '<ul class="brik-alist-list flex flex-col gap-3">' . $rows . '</ul></div>';
	},
);
