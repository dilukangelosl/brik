<?php
/**
 * Email opt-in. Submits like the contact form; connect a list provider through the webhook.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'signup',
	'title'       => __( 'Email signup', 'brik-builder' ),
	'category'    => 'forms',
	'icon'        => 'mail-plus',
	'description' => 'Newsletter / email opt-in form. title, description, show_name (bool) + name_required, email_placeholder, name_placeholder, button_text, button_variant: default|secondary|outline, layout: inline|stacked, style: card|muted|plain, align: left|center, privacy (inline HTML, links allowed). Delivery: form_name, success_message, redirect, send_email (bool, off by default), email_to, email_subject, save_entries, webhook_url (JSON POST of {fields: {email, name}} for Mailchimp/Zapier/Make).',
	'form_fields' => static function ( array $a ) {
		return brik_signup_schema( $a );
	},
	'fields'      => array_merge(
		array(
			'title'             => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Subscribe to our newsletter', 'brik-builder' ), 'inline' => true ) ),
			'description'       => Fields::field( 'textarea', __( 'Description', 'brik-builder' ), 'content', array( 'default' => __( 'Product updates, practical guides and early access to new features. One email a month, no spam.', 'brik-builder' ) ) ),
			'show_name'         => Fields::field( 'toggle', __( 'Ask for name', 'brik-builder' ), 'content' ),
			'name_required'     => Fields::field( 'toggle', __( 'Name required', 'brik-builder' ), 'content', array( 'show_if' => array( 'show_name' => '!' ) ) ),
			'name_placeholder'  => Fields::field( 'text', __( 'Name placeholder', 'brik-builder' ), 'content', array( 'default' => __( 'Your name', 'brik-builder' ), 'show_if' => array( 'show_name' => '!' ) ) ),
			'email_placeholder' => Fields::field( 'text', __( 'Email placeholder', 'brik-builder' ), 'content', array( 'default' => __( 'you@example.com', 'brik-builder' ) ) ),
			'button_text'       => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Subscribe', 'brik-builder' ), 'inline' => true ) ),
			'button_variant'    => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
			'privacy'           => Fields::field( 'text', __( 'Privacy note', 'brik-builder' ), 'content', array( 'default' => __( 'We care about your data. Unsubscribe at any time.', 'brik-builder' ) ) ),
			'layout'            => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'inline', 'options' => Fields::opts( array( 'inline' => __( 'Inline', 'brik-builder' ), 'stacked' => __( 'Stacked', 'brik-builder' ) ) ) ) ),
			'style'             => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'muted' => __( 'Muted panel', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'align'             => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ) ) ),
		),
		brik_form_settings_fields( __( 'Newsletter signup', 'brik-builder' ), __( 'New subscriber: {email}', 'brik-builder' ), false ),
		array(
			'success_message' => Fields::field( 'textarea', __( 'Success message', 'brik-builder' ), 'delivery', array( 'default' => __( 'You\'re on the list! Check your inbox for a welcome email.', 'brik-builder' ) ) ),
		),
		Fields::box( 'form', __( 'Container', 'brik-builder' ), Fields::WRAP . ' .brik-signup-box' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-form-title' ),
		Fields::typography( 'description', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-form-description' ),
		Fields::box( 'input', __( 'Inputs', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::typography( 'input', __( 'Input text', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::box( 'submit', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' ),
		Fields::typography( 'submit', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' ),
		Fields::typography( 'privacy', __( 'Privacy note', 'brik-builder' ), Fields::WRAP . ' .brik-signup-privacy' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$inline = 'stacked' !== $a['layout'];
		$center = 'center' === $a['align'];
		$styles = array(
			'card'  => 'rounded-xl border bg-card text-card-foreground p-6 shadow-sm sm:p-8',
			'muted' => 'rounded-xl bg-muted p-6 sm:p-8',
			'plain' => '',
		);

		$head = '';
		if ( '' !== trim( (string) $a['title'] ) ) {
			$head .= '<h3 class="brik-form-title font-heading text-xl font-semibold tracking-tight sm:text-2xl"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h3>';
		}
		if ( '' !== trim( (string) $a['description'] ) ) {
			$head .= '<p class="brik-form-description text-sm text-muted-foreground sm:text-base"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>';
		}
		if ( $head ) {
			$head = '<div class="' . esc_attr( brik_cls( 'brik-form-head grid gap-2', $center ? 'mx-auto max-w-xl text-center' : '' ) ) . '">' . $head . '</div>';
		}

		$inputs = '';
		foreach ( brik_signup_schema( $a ) as $f ) {
			$id      = $ctx->uid( 'f-' . $f['name'] );
			$inputs .= '<div class="brik-form-field grid min-w-0 flex-1 gap-1.5" data-field="' . esc_attr( $f['name'] ) . '">'
				. '<label class="sr-only" for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>'
				. '<input' . brik_attrs(
					array(
						'id'               => $id,
						'type'             => $f['type'],
						'name'             => 'fields[' . $f['name'] . ']',
						'class'            => brik_input_class( 'h-10' ),
						'placeholder'      => $f['placeholder'],
						'required'         => $f['required'],
						'autocomplete'     => 'email' === $f['type'] ? 'email' : 'name',
						'maxlength'        => 200,
						'aria-describedby' => $id . '-error',
					)
				) . '>'
				. '<p class="brik-form-error text-sm text-destructive" id="' . esc_attr( $id . '-error' ) . '" hidden></p></div>';
		}

		$row = brik_cls(
			'brik-signup-fields flex flex-col gap-3',
			$inline ? 'sm:flex-row sm:items-start' : '',
			$center ? 'mx-auto w-full max-w-lg' : ''
		);
		$button  = brik_form_submit( $a['button_text'], $a['button_variant'], 'lg', $inline ? 'h-10 w-full sm:w-auto' : 'h-10 w-full', '', $ctx->inline( 'button_text' ) );
		$privacy = '' !== trim( (string) $a['privacy'] ) ? '<p class="' . esc_attr( brik_cls( 'brik-signup-privacy text-xs text-muted-foreground [&_a]:underline [&_a]:underline-offset-4', $center ? 'text-center' : '' ) ) . '"' . $ctx->inline( 'privacy' ) . '>' . brik_inline( $a['privacy'] ) . '</p>' : '';

		$box = brik_cls( 'brik-signup-box grid gap-5', isset( $styles[ $a['style'] ] ) ? $styles[ $a['style'] ] : $styles['card'] );

		return '<div class="' . esc_attr( $box ) . '">' . $head
			. brik_form_open( $ctx, 'brik-form grid gap-3', array( 'id' => $ctx->uid( 'form' ) ) )
			. '<div class="' . esc_attr( $row ) . '">' . $inputs . '<div class="brik-form-actions flex shrink-0">' . $button . '</div></div>'
			. brik_form_hidden( $ctx )
			. '<div class="' . esc_attr( $center ? 'mx-auto w-full max-w-lg' : '' ) . '">' . brik_form_alerts( $a['success_message'], $ctx ) . '</div>'
			. $privacy
			. '</form></div>';
	},
);
