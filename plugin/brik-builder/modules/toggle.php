<?php
/**
 * Toggle: one collapsible block, or a "show more" clamp for long content.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'toggle',
	'title'       => __( 'Toggle', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'chevrons-up-down',
	'description' => 'Single collapsible block. title: text. content: HTML. open: bool (open by default). style: card|minimal|bordered|showmore (showmore clamps the content to collapsed_height with a fade and a "Show more" button; title is optional there). indicator: chevron|plus|none. more_label, less_label: button texts for showmore.',
	'fields'      => array_merge(
		array(
			'title'            => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'What happens after I place an order?', 'brik-builder' ), 'inline' => true ) ),
			'content'          => Fields::field( 'richtext', __( 'Content', 'brik-builder' ), 'content', array( 'default' => '<p>' . __( 'You will get a confirmation email right away. We pack orders within one business day and send a tracking link as soon as your parcel leaves the warehouse. Most orders arrive within three to five days.', 'brik-builder' ) . '</p><p>' . __( 'Need to change something? Reply to the confirmation email within two hours and we will update the order before it ships.', 'brik-builder' ) . '</p>' ) ),
			'open'             => Fields::field( 'toggle', __( 'Open by default', 'brik-builder' ), 'content' ),
			'style'            => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'bordered' => __( 'Bordered', 'brik-builder' ), 'minimal' => __( 'Minimal', 'brik-builder' ), 'showmore' => __( 'Show more', 'brik-builder' ) ) ) ) ),
			'indicator'        => Fields::field( 'select', __( 'Indicator', 'brik-builder' ), 'content', array( 'default' => 'chevron', 'options' => Fields::opts( array( 'chevron' => __( 'Chevron', 'brik-builder' ), 'plus' => __( 'Plus / minus', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ), 'show_if' => array( 'style' => array( 'card', 'bordered', 'minimal' ) ) ) ),
			'collapsed_height' => Fields::field( 'unit', __( 'Collapsed height', 'brik-builder' ), 'content', array( 'default' => '96px', 'responsive' => true, 'show_if' => array( 'style' => 'showmore' ), 'css' => array( Fields::WRAP . ' .brik-showmore', '--brik-collapsed' ) ) ),
			'more_label'       => Fields::field( 'text', __( 'Show more label', 'brik-builder' ), 'content', array( 'default' => __( 'Show more', 'brik-builder' ), 'show_if' => array( 'style' => 'showmore' ) ) ),
			'less_label'       => Fields::field( 'text', __( 'Show less label', 'brik-builder' ), 'content', array( 'default' => __( 'Show less', 'brik-builder' ), 'show_if' => array( 'style' => 'showmore' ) ) ),
		),
		Fields::box( 'panel', __( 'Panel', 'brik-builder' ), Fields::WRAP . ' .brik-acc-item' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' :is(.brik-acc-trigger, .brik-showmore-title)' ),
		Fields::typography( 'content', __( 'Content', 'brik-builder' ), Fields::WRAP . ' :is(.brik-acc-body, .brik-showmore-content)' ),
		Fields::typography( 'more', __( 'Show more button', 'brik-builder' ), Fields::WRAP . ' .brik-showmore-btn' )
	),
	'render'      => static function ( $a, $ctx ) {
		if ( 'showmore' === $a['style'] ) {
			$id    = $ctx->uid( 'content' );
			$title = '' !== $a['title'] ? '<p class="brik-showmore-title mb-2 text-base font-semibold"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</p>' : '';
			return sprintf(
				'<div class="brik-showmore" data-brik-showmore data-state="%1$s">%2$s<div id="%3$s" class="brik-showmore-content brik-prose text-muted-foreground"%10$s>%4$s</div><button type="button" class="brik-showmore-btn mt-3 inline-flex items-center gap-1 rounded-md text-sm font-medium text-primary underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50" aria-controls="%3$s" aria-expanded="%5$s" data-more="%6$s" data-less="%7$s"><span>%8$s</span>%9$s</button></div>',
				! empty( $a['open'] ) ? 'open' : 'closed',
				$title,
				esc_attr( $id ),
				brik_rich( $a['content'] ),
				! empty( $a['open'] ) ? 'true' : 'false',
				esc_attr( $a['more_label'] ),
				esc_attr( $a['less_label'] ),
				esc_html( ! empty( $a['open'] ) ? $a['less_label'] : $a['more_label'] ),
				brik_icon( 'chevron-down', 'brik-showmore-icon size-4 transition-transform duration-200' ),
				$ctx->inline( 'content' )
			);
		}

		$styles = array(
			'card'     => 'separated',
			'bordered' => 'bordered',
			'minimal'  => 'default',
		);
		return brik_disclosure_list(
			array(
				array(
					'title'   => $a['title'],
					'content' => $a['content'],
					'open'    => ! empty( $a['open'] ),
					'inline'  => array( $ctx->inline( 'title' ), $ctx->inline( 'content' ) ),
				),
			),
			array(
				'style'     => isset( $styles[ $a['style'] ] ) ? $styles[ $a['style'] ] : 'separated',
				'indicator' => $a['indicator'],
			)
		);
	},
);
