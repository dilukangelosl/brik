<?php
/**
 * Hover card (shadcn/ui hover-card): a preview card shown on hover or focus.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'hover_card',
	'title'       => __( 'Hover Card', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'id-card',
	'description' => 'Trigger that reveals a preview card on hover/focus/tap. trigger: avatar (image + name + handle) | text (link styled text). trigger_text: label for text trigger. link: where the trigger points. Card: image (avatar), cover (optional banner), title, subtitle, text, meta (small line with calendar icon). side: bottom|top|right|left. align: start|center|end.',
	'fields'      => array_merge(
		array(
			'trigger'      => Fields::field( 'select', __( 'Trigger', 'brik-builder' ), 'content', array( 'default' => 'avatar', 'options' => Fields::opts( array( 'avatar' => __( 'Avatar and name', 'brik-builder' ), 'text' => __( 'Text link', 'brik-builder' ) ) ) ) ),
			'trigger_text' => Fields::field( 'text', __( 'Trigger text', 'brik-builder' ), 'content', array( 'default' => '@mayachen', 'show_if' => array( 'trigger' => 'text' ) ) ),
			'link'         => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content', array( 'default' => array( 'url' => '#' ) ) ),
			'image'        => Fields::field( 'image', __( 'Avatar', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1494790108377-be9c29b29330', 160, 160 ), 'alt' => '' ) ) ),
			'cover'        => Fields::field( 'image', __( 'Cover image', 'brik-builder' ), 'content' ),
			'title'        => Fields::field( 'text', __( 'Name / title', 'brik-builder' ), 'content', array( 'default' => 'Maya Chen' ) ),
			'subtitle'     => Fields::field( 'text', __( 'Subtitle', 'brik-builder' ), 'content', array( 'default' => '@mayachen' ) ),
			'text'         => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Product designer at Northwind. Writing about design systems, typography and accessible interfaces.', 'brik-builder' ) ) ),
			'meta'         => Fields::field( 'text', __( 'Meta line', 'brik-builder' ), 'content', array( 'default' => __( 'Joined March 2021', 'brik-builder' ) ) ),
			'side'         => Fields::field( 'select', __( 'Side', 'brik-builder' ), 'content', array( 'default' => 'bottom', 'options' => Fields::opts( array( 'bottom' => __( 'Bottom', 'brik-builder' ), 'top' => __( 'Top', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'card_align'   => Fields::field( 'select', __( 'Card alignment', 'brik-builder' ), 'content', array( 'default' => 'start', 'options' => Fields::opts( array( 'start' => __( 'Start', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'end' => __( 'End', 'brik-builder' ) ) ), 'show_if' => array( 'side' => array( 'top', 'bottom' ) ) ) ),
			'card_width'   => Fields::field( 'unit', __( 'Card width', 'brik-builder' ), 'card', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-pop-content', 'width' ) ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-pop-content' ),
		Fields::typography( 'title', __( 'Card title', 'brik-builder' ), Fields::WRAP . ' .brik-hc-title' ),
		Fields::typography( 'text', __( 'Card text', 'brik-builder' ), Fields::WRAP . ' .brik-hc-text' ),
		Fields::typography( 'trigger', __( 'Trigger', 'brik-builder' ), Fields::WRAP . ' .brik-pop-trigger' )
	),
	'render'      => static function ( $a, $ctx ) {
		$id     = $ctx->uid( 'card' );
		$side   = in_array( $a['side'], array( 'top', 'right', 'bottom', 'left' ), true ) ? $a['side'] : 'bottom';
		$align  = in_array( $a['card_align'], array( 'start', 'center', 'end' ), true ) ? $a['card_align'] : 'start';
		$avatar = brik_image( $a['image'], 'thumbnail', array( 'class' => 'size-full object-cover', 'alt' => '' ) );
		$init   = '' !== $a['title'] ? strtoupper( mb_substr( wp_strip_all_tags( $a['title'] ), 0, 1 ) ) : '?';

		$avatar_sm = '<span class="relative flex size-8 shrink-0 overflow-hidden rounded-full bg-muted">' . ( $avatar ? $avatar : '<span class="flex size-full items-center justify-center text-xs font-medium">' . esc_html( $init ) . '</span>' ) . '</span>';
		$avatar_lg = '<span class="relative flex size-12 shrink-0 overflow-hidden rounded-full bg-muted' . ( ! empty( $a['cover'] ) ? ' -mt-9 ring-4 ring-popover' : '' ) . '">' . ( $avatar ? $avatar : '<span class="flex size-full items-center justify-center text-sm font-medium">' . esc_html( $init ) . '</span>' ) . '</span>';

		if ( 'text' === $a['trigger'] ) {
			$label = '<span' . $ctx->inline( 'trigger_text' ) . '>' . brik_inline( $a['trigger_text'] ) . '</span>';
			$class = 'brik-pop-trigger inline-flex items-center rounded-sm text-sm font-medium text-foreground underline-offset-4 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50';
		} else {
			$label = $avatar_sm . '<span class="flex flex-col text-left leading-tight"><span class="text-sm font-medium">' . brik_inline( $a['title'] ) . '</span>' . ( '' !== $a['subtitle'] ? '<span class="text-xs text-muted-foreground">' . brik_inline( $a['subtitle'] ) . '</span>' : '' ) . '</span>';
			$class = 'brik-pop-trigger inline-flex items-center gap-2.5 rounded-full py-1 pr-3 pl-1 transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50';
		}
		$trigger = '<a' . brik_link_attrs( $a['link'], array( 'class' => $class, 'aria-describedby' => $id ) ) . '>' . $label . '</a>';

		$card = '';
		if ( ! empty( $a['cover'] ) && ( $cover = brik_image( $a['cover'], 'medium', array( 'class' => 'h-full w-full object-cover', 'alt' => '' ) ) ) ) {
			$card .= '<div class="-mx-4 -mt-4 mb-3 h-20 overflow-hidden rounded-t-md bg-muted">' . $cover . '</div>';
		}
		$body = '';
		if ( '' !== $a['title'] ) {
			$body .= '<p class="brik-hc-title text-sm font-semibold"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</p>';
		}
		if ( '' !== $a['subtitle'] && 'text' === $a['trigger'] ) {
			$body .= '<p class="text-xs text-muted-foreground">' . brik_inline( $a['subtitle'] ) . '</p>';
		}
		if ( '' !== $a['text'] ) {
			$body .= '<p class="brik-hc-text text-sm"' . $ctx->inline( 'text' ) . '>' . esc_html( $a['text'] ) . '</p>';
		}
		if ( '' !== $a['meta'] ) {
			$body .= '<p class="brik-hc-meta flex items-center gap-1.5 pt-1 text-xs text-muted-foreground">' . brik_icon( 'calendar-days', 'size-3.5 opacity-70' ) . '<span' . $ctx->inline( 'meta' ) . '>' . brik_inline( $a['meta'] ) . '</span></p>';
		}
		$card .= '<div class="flex gap-4">' . $avatar_lg . '<div class="flex min-w-0 flex-col gap-1">' . $body . '</div></div>';

		return sprintf(
			'<span class="brik-pop brik-hover-card" data-brik-pop data-side="%1$s" data-align="%2$s" data-state="closed">%3$s<span id="%4$s" role="tooltip" class="brik-pop-content brik-hover-card-content w-80 max-w-[calc(100vw-2rem)] rounded-md border bg-popover p-4 text-left text-popover-foreground shadow-md">%5$s</span></span>',
			esc_attr( $side ),
			esc_attr( $align ),
			$trigger,
			esc_attr( $id ),
			$card
		);
	},
);
