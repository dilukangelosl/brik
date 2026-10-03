<?php
/**
 * Tooltip (shadcn/ui tooltip) on an inline word, icon or button.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'tooltip',
	'title'       => __( 'Tooltip', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'message-square-more',
	'description' => 'Short hint shown on hover, focus or tap. before, after: plain text around the trigger so it can sit inside a sentence. trigger: text|icon|button. trigger_text: text. trigger_icon: Lucide name (default info). tip: tooltip text. side: top|right|bottom|left. arrow: bool.',
	'fields'      => array_merge(
		array(
			'before'       => Fields::field( 'text', __( 'Text before', 'brik-builder' ), 'content', array( 'default' => __( 'Every plan includes', 'brik-builder' ) ) ),
			'trigger'      => Fields::field( 'select', __( 'Trigger', 'brik-builder' ), 'content', array( 'default' => 'text', 'options' => Fields::opts( array( 'text' => __( 'Underlined text', 'brik-builder' ), 'icon' => __( 'Icon', 'brik-builder' ), 'button' => __( 'Button', 'brik-builder' ) ) ) ) ),
			'trigger_text' => Fields::field( 'text', __( 'Trigger text', 'brik-builder' ), 'content', array( 'default' => __( 'unlimited seats', 'brik-builder' ), 'show_if' => array( 'trigger' => array( 'text', 'button' ) ) ) ),
			'trigger_icon' => Fields::field( 'icon', __( 'Trigger icon', 'brik-builder' ), 'content', array( 'default' => 'info' ) ),
			'after'        => Fields::field( 'text', __( 'Text after', 'brik-builder' ), 'content', array( 'default' => __( 'and a 30-day money-back guarantee.', 'brik-builder' ) ) ),
			'tip'          => Fields::field( 'textarea', __( 'Tooltip text', 'brik-builder' ), 'content', array( 'default' => __( 'Invite your whole team. We never charge per user.', 'brik-builder' ) ) ),
			'side'         => Fields::field( 'select', __( 'Side', 'brik-builder' ), 'content', array( 'default' => 'top', 'options' => Fields::opts( array( 'top' => __( 'Top', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ), 'bottom' => __( 'Bottom', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'arrow'        => Fields::field( 'toggle', __( 'Arrow', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'tip_width'    => Fields::field( 'unit', __( 'Max width', 'brik-builder' ), 'tip', array( 'tab' => 'design', 'group_label' => __( 'Tooltip', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-pop-content', 'max-width' ) ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::box( 'tip', __( 'Tooltip', 'brik-builder' ), Fields::WRAP . ' .brik-pop-content', array( 'bg', 'color', 'radius', 'padding', 'shadow' ) ),
		Fields::typography( 'tip', __( 'Tooltip text', 'brik-builder' ), Fields::WRAP . ' .brik-pop-content' ),
		Fields::typography( 'trigger', __( 'Trigger', 'brik-builder' ), Fields::WRAP . ' .brik-pop-trigger' )
	),
	'render'      => static function ( $a, $ctx ) {
		$id   = $ctx->uid( 'tip' );
		$side = in_array( $a['side'], array( 'top', 'right', 'bottom', 'left' ), true ) ? $a['side'] : 'top';
		$icon = brik_icon( $a['trigger_icon'] ? $a['trigger_icon'] : 'info', 'size-4' );

		if ( 'icon' === $a['trigger'] ) {
			$trigger = '<button type="button" class="brik-pop-trigger inline-flex size-5 items-center justify-center rounded-full align-middle text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50" aria-describedby="' . esc_attr( $id ) . '" aria-label="' . esc_attr( wp_strip_all_tags( $a['tip'] ) ) . '">' . $icon . '</button>';
		} elseif ( 'button' === $a['trigger'] ) {
			$trigger = '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'default', 'brik-pop-trigger' ) ) . '" aria-describedby="' . esc_attr( $id ) . '"' . $ctx->inline( 'trigger_text' ) . '>' . brik_inline( $a['trigger_text'] ) . '</button>';
		} else {
			$trigger = '<button type="button" class="brik-pop-trigger inline cursor-help rounded-sm font-medium text-foreground underline decoration-muted-foreground/60 decoration-dotted underline-offset-4 outline-none hover:decoration-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50" aria-describedby="' . esc_attr( $id ) . '"' . $ctx->inline( 'trigger_text' ) . '>' . brik_inline( $a['trigger_text'] ) . '</button>';
		}

		$arrow = ! empty( $a['arrow'] ) ? '<span class="brik-pop-arrow" aria-hidden="true"></span>' : '';
		$pop   = sprintf(
			'<span class="brik-pop brik-tooltip" data-brik-pop data-side="%1$s" data-state="closed">%2$s<span role="tooltip" id="%3$s" class="brik-pop-content brik-tooltip-content w-max max-w-xs rounded-md bg-foreground px-3 py-1.5 text-xs leading-snug text-balance text-background shadow-md">%4$s%5$s</span></span>',
			esc_attr( $side ),
			$trigger,
			esc_attr( $id ),
			esc_html( $a['tip'] ),
			$arrow
		);

		$before = '' !== $a['before'] ? '<span' . $ctx->inline( 'before' ) . '>' . brik_inline( $a['before'] ) . '</span> ' : '';
		$after  = '' !== $a['after'] ? ' <span' . $ctx->inline( 'after' ) . '>' . brik_inline( $a['after'] ) . '</span>' : '';
		return '<p class="brik-tooltip-line">' . $before . $pop . $after . '</p>';
	},
);
