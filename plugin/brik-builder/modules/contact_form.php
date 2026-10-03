<?php
/**
 * Contact form. Submissions go through POST brik/v1/forms/submit; recipients, subject,
 * webhook and redirect are read from the stored node on the server.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'contact_form',
	'title'       => __( 'Contact form', 'brik-builder' ),
	'category'    => 'forms',
	'icon'        => 'mail',
	'description' => 'AJAX form with shadcn inputs: contact form, front-end post submission, post editing or user registration. fields: repeater of {label, name (auto from label), type: text|email|tel|url|number|textarea|select|checkbox|radio|date|password|file|image|hidden|consent, placeholder, options (one per line, for select/checkbox/radio), options_source: manual|taxonomy:<taxonomy>|post_type:<post type> (options from terms/posts; the value is the term/post id), accept (file: ".pdf,.zip" or "image/*"), max_size (MB, default 5), required (bool), width: full|half, value (hidden only)}. layout: stacked|floating|placeholder. style: card|plain. title/description optional. submit_text, submit_variant: default|secondary|outline|ghost|destructive, submit_size: sm|default|lg|xl, submit_full (bool), submit_align: left|center|right. '
		. 'actions: list or comma separated string of email|save_entry|webhook|create_post|update_post|register_user|redirect (empty = legacy toggles send_email, save_entries, webhook_url, redirect). Delivery: form_name, success_message (supports {post_title}, {post_url}), redirect (URL, supports {post_url}), send_email, email_to (comma separated, default admin email), email_subject ({site_name}, {form_name}, {field_name}), save_entries, webhook_url (JSON POST). '
		. 'create_post / update_post: post_type, post_status draft|pending|publish (default pending), post_author current|fixed + post_author_id, allow_guests (bool; off = only logged-in users who can create that post type), update_source current|query (?post_id=; the user must be able to edit it, inputs are prefilled), mapping repeater [{field: form field name, target: title|content|excerpt|featured_image|meta:<field name>|tax:<taxonomy>}] (custom fields are saved through the content API, file/image fields store the attachment id, uploads are attached to the post). '
		. 'register_user: register_email (field name, default email), register_username, register_password (optional), register_name, register_role (subscriber default; administrator/editor never allowed), register_login (auto login), allow_registration (allow when "Anyone can register" is off). The JSON response includes post_id and permalink.',
	'form_fields' => static function ( array $a ) {
		return brik_form_schema( $a['fields'] );
	},
	'fields'      => array_merge(
		array(
			'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'description' => Fields::field( 'textarea', __( 'Description', 'brik-builder' ), 'content' ),
			'fields'      => Fields::field(
				'repeater',
				__( 'Fields', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'label',
					'fields'      => array(
						'label'       => Fields::field( 'text', __( 'Label', 'brik-builder' ) ),
						'type'        => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'text', 'options' => Fields::opts( brik_form_field_types() ) ) ),
						'name'        => Fields::field( 'text', __( 'Field name', 'brik-builder' ), 'content', array( 'description' => __( 'Optional. Generated from the label.', 'brik-builder' ) ) ),
						'placeholder' => Fields::field( 'text', __( 'Placeholder', 'brik-builder' ), 'content', array( 'show_if' => array( 'type' => array( 'text', 'email', 'tel', 'url', 'number', 'textarea', 'select', 'date' ) ) ) ),
						'options_source' => Fields::field( 'select', __( 'Options from', 'brik-builder' ), 'content', array( 'default' => 'manual', 'options' => Fields::opts( brik_form_options_sources() ), 'show_if' => array( 'type' => array( 'select', 'checkbox', 'radio' ) ) ) ),
						'options'     => Fields::field( 'textarea', __( 'Options', 'brik-builder' ), 'content', array( 'description' => __( 'One per line.', 'brik-builder' ), 'show_if' => array( 'type' => array( 'select', 'checkbox', 'radio' ), 'options_source' => array( '', 'manual' ) ) ) ),
						'accept'      => Fields::field( 'text', __( 'Allowed types', 'brik-builder' ), 'content', array( 'placeholder' => '.pdf, .zip', 'description' => __( 'Extensions or mime types. Empty allows common documents and images.', 'brik-builder' ), 'show_if' => array( 'type' => 'file' ) ) ),
						'max_size'    => Fields::field( 'number', __( 'Max size (MB)', 'brik-builder' ), 'content', array( 'default' => 5, 'min' => 1, 'max' => 100, 'show_if' => array( 'type' => array( 'file', 'image' ) ) ) ),
						'value'       => Fields::field( 'text', __( 'Value', 'brik-builder' ), 'content', array( 'show_if' => array( 'type' => 'hidden' ), 'description' => __( 'Dynamic tags like {post_title} work here.', 'brik-builder' ) ) ),
						'required'    => Fields::field( 'toggle', __( 'Required', 'brik-builder' ) ),
						'width'       => Fields::field( 'select', __( 'Width', 'brik-builder' ), 'content', array( 'default' => 'full', 'options' => Fields::opts( array( 'full' => __( 'Full', 'brik-builder' ), 'half' => __( 'Half', 'brik-builder' ) ) ) ) ),
					),
					'default'     => array(
						array(
							'label'       => __( 'Name', 'brik-builder' ),
							'type'        => 'text',
							'placeholder' => __( 'Jane Cooper', 'brik-builder' ),
							'required'    => true,
							'width'       => 'half',
						),
						array(
							'label'       => __( 'Email', 'brik-builder' ),
							'type'        => 'email',
							'placeholder' => 'jane@example.com',
							'required'    => true,
							'width'       => 'half',
						),
						array(
							'label'   => __( 'Topic', 'brik-builder' ),
							'type'    => 'select',
							'options' => implode( "\n", array( __( 'General question', 'brik-builder' ), __( 'Sales & pricing', 'brik-builder' ), __( 'Technical support', 'brik-builder' ), __( 'Partnerships', 'brik-builder' ) ) ),
							'width'   => 'full',
						),
						array(
							'label'       => __( 'Message', 'brik-builder' ),
							'type'        => 'textarea',
							'placeholder' => __( 'Tell us a little about what you need…', 'brik-builder' ),
							'required'    => true,
							'width'       => 'full',
						),
					),
				)
			),
			'layout'         => Fields::field( 'select', __( 'Labels', 'brik-builder' ), 'content', array( 'default' => 'stacked', 'options' => Fields::opts( array( 'stacked' => __( 'Above fields', 'brik-builder' ), 'floating' => __( 'Floating', 'brik-builder' ), 'placeholder' => __( 'Placeholders only', 'brik-builder' ) ) ) ) ),
			'style'          => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'submit_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'button', array( 'default' => __( 'Send message', 'brik-builder' ), 'inline' => true ) ),
			'submit_icon'    => Fields::field( 'icon', __( 'Button icon', 'brik-builder' ), 'button', array( 'default' => 'send' ) ),
			'submit_variant' => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'button', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
			'submit_size'    => Fields::field( 'select', __( 'Button size', 'brik-builder' ), 'button', array( 'default' => 'lg', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'submit_full'    => Fields::field( 'toggle', __( 'Full width button', 'brik-builder' ), 'button', array( 'responsive' => true ) ),
			'submit_align'   => Fields::field(
				'select',
				__( 'Button alignment', 'brik-builder' ),
				'button',
				array(
					'default'    => 'left',
					'responsive' => true,
					'options'    => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ),
					'css'        => array(
						'selector' => Fields::WRAP . ' .brik-form-actions',
						'map'      => array(
							'left'   => 'justify-content:flex-start',
							'center' => 'justify-content:center',
							'right'  => 'justify-content:flex-end',
						),
					),
				)
			),
			'field_gap'      => Fields::field( 'unit', __( 'Space between fields', 'brik-builder' ), 'fields_layout', array( 'tab' => 'design', 'group_label' => __( 'Fields layout', 'brik-builder' ), 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-form-grid', 'prop' => 'row-gap' ) ) ),
			'column_gap'     => Fields::field( 'unit', __( 'Space between columns', 'brik-builder' ), 'fields_layout', array( 'tab' => 'design', 'group_label' => __( 'Fields layout', 'brik-builder' ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-form-grid', 'prop' => 'column-gap' ) ) ),
			'input_height'   => Fields::field( 'unit', __( 'Input height', 'brik-builder' ), 'input', array( 'tab' => 'design', 'group_label' => __( 'Inputs', 'brik-builder' ), 'css' => array( 'selector' => Fields::WRAP . ' :is(input.brik-input,select.brik-input)', 'prop' => 'height' ) ) ),
			'focus_color'    => Fields::field( 'color', __( 'Focus ring color', 'brik-builder' ), 'input', array( 'tab' => 'design', 'group_label' => __( 'Inputs', 'brik-builder' ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-input:focus-visible', 'prop' => 'border-color' ) ) ),
		),
		brik_form_settings_fields( __( 'Contact form', 'brik-builder' ), __( 'New message from {name}', 'brik-builder' ) ),
		brik_form_action_fields(),
		Fields::box( 'form', __( 'Form container', 'brik-builder' ), Fields::WRAP . ' .brik-form' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-form-title' ),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-form-label' ),
		Fields::box( 'input', __( 'Inputs', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::typography( 'input', __( 'Input text', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::box( 'submit', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' ),
		Fields::typography( 'submit', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-form-submit' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$fields = brik_form_schema( $a['fields'] );
		$layout = in_array( $a['layout'], array( 'stacked', 'floating', 'placeholder' ), true ) ? $a['layout'] : 'stacked';
		if ( ! $fields ) {
			return $ctx->placeholder( __( 'Add some fields to this form.', 'brik-builder' ) );
		}

		$styles = array(
			'card'  => 'rounded-xl border bg-card text-card-foreground p-6 shadow-sm sm:p-8',
			'plain' => '',
		);
		$class = brik_cls( 'brik-form @container grid gap-6', 'brik-form--' . $layout, isset( $styles[ $a['style'] ] ) ? $styles[ $a['style'] ] : $styles['card'] );

		$head = '';
		if ( '' !== trim( (string) $a['title'] ) || '' !== trim( (string) $a['description'] ) ) {
			$head = '<div class="brik-form-head grid gap-1.5">';
			if ( '' !== trim( (string) $a['title'] ) ) {
				$head .= '<h3 class="brik-form-title font-heading text-xl font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</h3>';
			}
			if ( '' !== trim( (string) $a['description'] ) ) {
				$head .= '<p class="brik-form-description text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>';
			}
			$head .= '</div>';
		}

		$actions = brik_form_actions( $a );
		$target  = $ctx->canvas ? 0 : brik_form_render_target( $a, $ctx );
		if ( $target ) {
			$fields = brik_form_apply_prefill( $fields, brik_form_prefill( $a, $target ) );
		}

		// Visitors who can't use a post form see why instead of a form that will refuse them.
		if ( ! $ctx->canvas ) {
			$denied = null;
			if ( in_array( 'update_post', $actions, true ) && ! $target && ! in_array( 'create_post', $actions, true ) ) {
				$denied = is_user_logged_in() ? __( 'You can\'t edit this item.', 'brik-builder' ) : __( 'Please log in to edit this item.', 'brik-builder' );
			} elseif ( in_array( 'create_post', $actions, true ) && ! $target ) {
				$check  = brik_form_can_create( $a );
				$denied = is_wp_error( $check ) ? $check->get_error_message() : null;
			}
			if ( $denied ) {
				$login = is_user_logged_in() ? '' : '<a class="' . esc_attr( brik_button_class( 'outline', 'sm' ) ) . '" href="' . esc_url( wp_login_url( brik_listing_current_url() ) ) . '">' . esc_html__( 'Log in', 'brik-builder' ) . '</a>';
				return '<div class="brik-form-denied flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-6 text-sm text-muted-foreground">'
					. '<span class="inline-flex items-center gap-2">' . brik_icon( 'lock', 'size-4' ) . esc_html( $denied ) . '</span>' . $login . '</div>';
			}
		}

		$grid    = '';
		$uploads = false;
		foreach ( $fields as $f ) {
			$grid   .= brik_form_field( $f, $layout, $ctx );
			$uploads = $uploads || in_array( $f['type'], array( 'file', 'image' ), true );
		}
		$extra = array( 'id' => $ctx->uid( 'form' ) );
		if ( $uploads ) {
			$extra['enctype']            = 'multipart/form-data';
			$extra['data-msg-size']      = __( 'This file is too large.', 'brik-builder' );
		}
		if ( $target ) {
			$extra['data-keep'] = true;
			$grid .= '<input type="hidden" name="brik_target" value="' . (int) $target . '">';
		}

		$button = brik_form_submit( $a['submit_text'], $a['submit_variant'], $a['submit_size'], brik_form_bool( $a['submit_full'] ) ? 'w-full' : '', $a['submit_icon'], $ctx->inline( 'submit_text' ) );
		foreach ( array( 'tablet', 'mobile' ) as $bp ) {
			if ( isset( $a[ 'submit_full@' . $bp ] ) && '' !== $a[ 'submit_full@' . $bp ] ) {
				$ctx->css( Fields::WRAP . ' .brik-form-submit', 'width:' . ( brik_form_bool( $a[ 'submit_full@' . $bp ] ) ? '100%' : 'auto' ), $bp );
			}
		}

		return brik_form_open( $ctx, $class, $extra )
			. $head
			. '<div class="brik-form-grid grid grid-cols-1 gap-x-4 gap-y-5 @md:grid-cols-2">' . $grid . '</div>'
			. brik_form_hidden( $ctx )
			. brik_form_alerts( $a['success_message'], $ctx )
			. '<div class="brik-form-actions flex">' . $button . '</div>'
			. '</form>';
	},
);
