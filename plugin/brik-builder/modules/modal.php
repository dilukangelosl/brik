<?php
/**
 * Modal: a trigger that opens a shadcn dialog or a sheet sliding in from an edge.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'modal',
	'title'       => __( 'Modal', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'app-window',
	'description' => 'Button or link that opens a dialog (native <dialog>). trigger: button|link. trigger_text, trigger_icon (Lucide), trigger_variant: default|secondary|outline|ghost|link|destructive, trigger_size: sm|default|lg|xl. mode: dialog|sheet. size (dialog): sm|md|lg|xl|full. side (sheet): right|left|top|bottom. title, description: text. content: HTML body. action_text + action_link: footer button (empty hides). cancel_text: footer close button (empty hides). auto_open: seconds after page load (0 = off), once per session when once is on. Other elements can open it with a link to #<modal_id>.',
	'fields'      => array_merge(
		array(
			'trigger'         => Fields::field( 'select', __( 'Trigger', 'brik-builder' ), 'trigger', array( 'default' => 'button', 'options' => Fields::opts( array( 'button' => __( 'Button', 'brik-builder' ), 'link' => __( 'Text link', 'brik-builder' ), 'none' => __( 'None (open by link or timer)', 'brik-builder' ) ) ), 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'trigger_text'    => Fields::field( 'text', __( 'Trigger text', 'brik-builder' ), 'trigger', array( 'default' => __( 'Open dialog', 'brik-builder' ), 'inline' => true, 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'trigger_icon'    => Fields::field( 'icon', __( 'Trigger icon', 'brik-builder' ), 'trigger', array( 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'trigger_variant' => Fields::field( 'select', __( 'Button style', 'brik-builder' ), 'trigger', array( 'default' => 'outline', 'options' => Fields::opts( brik_button_variants_labels() ), 'show_if' => array( 'trigger' => 'button' ), 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'trigger_size'    => Fields::field( 'select', __( 'Button size', 'brik-builder' ), 'trigger', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ), 'show_if' => array( 'trigger' => 'button' ), 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'align'           => Fields::field( 'align', __( 'Trigger alignment', 'brik-builder' ), 'trigger', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ), 'group_label' => __( 'Trigger', 'brik-builder' ) ) ),
			'mode'            => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'dialog', 'options' => Fields::opts( array( 'dialog' => __( 'Dialog', 'brik-builder' ), 'sheet' => __( 'Sheet (slide-in panel)', 'brik-builder' ) ) ) ) ),
			'size'            => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'md', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'md' => __( 'Medium', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ), 'full' => __( 'Full screen', 'brik-builder' ) ) ), 'show_if' => array( 'mode' => 'dialog' ) ) ),
			'side'            => Fields::field( 'select', __( 'Slide in from', 'brik-builder' ), 'content', array( 'default' => 'right', 'options' => Fields::opts( array( 'right' => __( 'Right', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ), 'top' => __( 'Top', 'brik-builder' ), 'bottom' => __( 'Bottom', 'brik-builder' ) ) ), 'show_if' => array( 'mode' => 'sheet' ) ) ),
			'title'           => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Stay in the loop', 'brik-builder' ) ) ),
			'description'     => Fields::field( 'textarea', __( 'Description', 'brik-builder' ), 'content', array( 'default' => __( 'One short email a month with new features and the best articles from the blog.', 'brik-builder' ) ) ),
			'content'         => Fields::field( 'richtext', __( 'Body', 'brik-builder' ), 'content', array( 'default' => '<p>' . __( 'No spam, ever. You can unsubscribe with one click, and we never share your address with anyone.', 'brik-builder' ) . '</p>' ) ),
			'action_text'     => Fields::field( 'text', __( 'Action button text', 'brik-builder' ), 'footer', array( 'default' => __( 'Subscribe', 'brik-builder' ), 'group_label' => __( 'Footer', 'brik-builder' ) ) ),
			'action_link'     => Fields::field( 'link', __( 'Action button link', 'brik-builder' ), 'footer', array( 'default' => array( 'url' => '#' ), 'group_label' => __( 'Footer', 'brik-builder' ) ) ),
			'action_variant'  => Fields::field( 'select', __( 'Action button style', 'brik-builder' ), 'footer', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ), 'group_label' => __( 'Footer', 'brik-builder' ) ) ),
			'cancel_text'     => Fields::field( 'text', __( 'Close button text', 'brik-builder' ), 'footer', array( 'default' => __( 'Maybe later', 'brik-builder' ), 'group_label' => __( 'Footer', 'brik-builder' ) ) ),
			'modal_id'        => Fields::field( 'text', __( 'Modal ID', 'brik-builder' ), 'behaviour', array( 'group_label' => __( 'Behaviour', 'brik-builder' ), 'description' => __( 'Link to #this-id from any button to open the modal.', 'brik-builder' ) ) ),
			'close_backdrop'  => Fields::field( 'toggle', __( 'Close on backdrop click', 'brik-builder' ), 'behaviour', array( 'default' => true, 'group_label' => __( 'Behaviour', 'brik-builder' ) ) ),
			'auto_open'       => Fields::field( 'number', __( 'Open automatically after (seconds)', 'brik-builder' ), 'behaviour', array( 'min' => 0, 'group_label' => __( 'Behaviour', 'brik-builder' ), 'description' => __( '0 or empty: only on click.', 'brik-builder' ) ) ),
			'once'            => Fields::field( 'toggle', __( 'Auto open once per session', 'brik-builder' ), 'behaviour', array( 'default' => true, 'group_label' => __( 'Behaviour', 'brik-builder' ), 'show_if' => array( 'auto_open' => '!' ) ) ),
		),
		Fields::box( 'trigger', __( 'Trigger', 'brik-builder' ), Fields::WRAP . ' .brik-modal-trigger' ),
		Fields::box( 'panel', __( 'Dialog panel', 'brik-builder' ), Fields::WRAP . ' .brik-modal-panel' ),
		Fields::typography( 'title', __( 'Dialog title', 'brik-builder' ), Fields::WRAP . ' .brik-modal-title' ),
		Fields::typography( 'body', __( 'Dialog body', 'brik-builder' ), Fields::WRAP . ' .brik-modal-body' ),
		array(
			'backdrop' => Fields::field( 'color', __( 'Backdrop color', 'brik-builder' ), 'panel', array( 'tab' => 'design', 'group_label' => __( 'Dialog panel', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-dialog::backdrop', 'background-color' ) ) ),
			'panel_width' => Fields::field( 'unit', __( 'Panel width', 'brik-builder' ), 'panel', array( 'tab' => 'design', 'group_label' => __( 'Dialog panel', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-dialog', 'max-width' ) ) ),
		)
	),
	'render'      => static function ( $a, $ctx ) {
		$id    = '' !== $a['modal_id'] ? sanitize_html_class( $a['modal_id'] ) : $ctx->uid( 'modal' );
		$sheet = 'sheet' === $a['mode'];
		$side  = in_array( $a['side'], array( 'left', 'right', 'top', 'bottom' ), true ) ? $a['side'] : 'right';
		$size  = in_array( $a['size'], array( 'sm', 'md', 'lg', 'xl', 'full' ), true ) ? $a['size'] : 'md';

		$trigger = '';
		if ( 'none' !== $a['trigger'] ) {
			$icon  = $a['trigger_icon'] ? brik_icon( $a['trigger_icon'] ) : '';
			$class = 'link' === $a['trigger']
				? 'brik-modal-trigger inline-flex items-center gap-1.5 font-medium text-primary underline underline-offset-4 outline-none hover:no-underline focus-visible:ring-[3px] focus-visible:ring-ring/50 rounded-sm cursor-pointer [&_svg]:size-4'
				: brik_button_class( $a['trigger_variant'], $a['trigger_size'], 'brik-modal-trigger' );
			$trigger = sprintf(
				'<button type="button" class="%1$s" data-brik-modal="%2$s" aria-haspopup="dialog" aria-controls="%2$s">%3$s<span%5$s>%4$s</span></button>',
				esc_attr( $class ),
				esc_attr( $id ),
				$icon,
				brik_inline( $a['trigger_text'] ),
				$ctx->inline( 'trigger_text' )
			);
		} elseif ( $ctx->canvas ) {
			$trigger = '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'sm', 'brik-modal-trigger border-dashed' ) ) . '" data-brik-modal="' . esc_attr( $id ) . '">' . brik_icon( 'app-window' ) . '<span>' . esc_html__( 'Preview modal', 'brik-builder' ) . '</span></button>';
		}

		$head = '';
		if ( '' !== $a['title'] ) {
			$head .= '<h2 id="' . esc_attr( $id . '-title' ) . '" class="brik-modal-title text-lg leading-none font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h2>';
		}
		if ( '' !== $a['description'] ) {
			$head .= '<p id="' . esc_attr( $id . '-desc' ) . '" class="brik-modal-desc text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . esc_html( $a['description'] ) . '</p>';
		}
		if ( $head ) {
			$head = '<div class="flex flex-col gap-2 ' . ( $sheet ? 'pr-8' : 'pr-6 text-center sm:text-left' ) . '">' . $head . '</div>';
		}
		$body = '' !== trim( (string) $a['content'] ) ? '<div class="brik-modal-body brik-prose text-sm' . ( $sheet ? ' flex-1 overflow-y-auto' : '' ) . '"' . $ctx->inline( 'content' ) . '>' . brik_rich( $a['content'] ) . '</div>' : '';

		$footer = '';
		if ( '' !== $a['cancel_text'] ) {
			$footer .= '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'default' ) ) . '" data-brik-close>' . esc_html( $a['cancel_text'] ) . '</button>';
		}
		if ( '' !== $a['action_text'] ) {
			$footer .= '<a' . brik_link_attrs( $a['action_link'], array( 'class' => brik_button_class( $a['action_variant'], 'default', 'brik-modal-action' ) ) ) . '>' . brik_inline( $a['action_text'] ) . '</a>';
		}
		if ( $footer ) {
			$footer = '<div class="brik-modal-footer flex flex-col-reverse gap-2 sm:flex-row ' . ( $sheet ? 'mt-auto' : 'sm:justify-end' ) . '">' . $footer . '</div>';
		}

		$close = '<button type="button" class="brik-modal-close absolute top-4 right-4 inline-flex size-6 items-center justify-center rounded-xs opacity-70 ring-offset-background transition-opacity outline-none hover:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2" data-brik-close aria-label="' . esc_attr__( 'Close', 'brik-builder' ) . '">' . brik_icon( 'x', 'size-4' ) . '</button>';

		$panel = $sheet
			? 'brik-modal-panel relative flex h-full flex-col gap-4 overflow-y-auto bg-background p-6 text-foreground shadow-lg'
			: 'brik-modal-panel relative grid gap-4 rounded-lg border bg-background p-6 text-foreground shadow-lg';

		$dialog = sprintf(
			'<dialog id="%1$s" class="%2$s"%3$s%4$s%5$s%6$s><div class="%7$s">%8$s</div></dialog>',
			esc_attr( $id ),
			esc_attr( $sheet ? 'brik-dialog brik-dialog--sheet brik-dialog--' . $side : 'brik-dialog brik-dialog--dialog brik-dialog--' . $size ),
			'' !== $a['title'] ? ' aria-labelledby="' . esc_attr( $id . '-title' ) . '"' : '',
			'' !== $a['description'] ? ' aria-describedby="' . esc_attr( $id . '-desc' ) . '"' : '',
			! empty( $a['close_backdrop'] ) ? ' data-backdrop-close' : '',
			! $ctx->canvas && (int) $a['auto_open'] > 0 ? ' data-auto-open="' . (int) $a['auto_open'] . '"' . ( ! empty( $a['once'] ) ? ' data-once' : '' ) : '',
			esc_attr( $panel ),
			$head . $body . $footer . $close
		);

		return $trigger . $dialog;
	},
);
