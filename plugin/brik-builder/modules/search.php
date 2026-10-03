<?php
/**
 * Search form.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'search',
	'title'       => __( 'Search', 'brik-builder' ),
	'category'    => 'forms',
	'icon'        => 'search',
	'description' => 'Site search form (GET ?s=). placeholder, button: text|icon|none, button_text, button_variant: default|secondary|outline|ghost, post_type (limit results to one post type slug, empty = all), size: default|lg, show_icon (search icon inside the input).',
	'fields'      => array_merge(
		array(
			'placeholder'    => Fields::field( 'text', __( 'Placeholder', 'brik-builder' ), 'content', array( 'default' => __( 'Search articles, docs and more…', 'brik-builder' ) ) ),
			'button'         => Fields::field( 'select', __( 'Button', 'brik-builder' ), 'content', array( 'default' => 'text', 'options' => Fields::opts( array( 'text' => __( 'Text', 'brik-builder' ), 'icon' => __( 'Icon only', 'brik-builder' ), 'none' => __( 'No button', 'brik-builder' ) ) ) ) ),
			'button_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content', array( 'default' => __( 'Search', 'brik-builder' ), 'show_if' => array( 'button' => 'text' ) ) ),
			'button_variant' => Fields::field( 'select', __( 'Button variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ), 'show_if' => array( 'button' => array( 'text', 'icon' ) ) ) ),
			'show_icon'      => Fields::field( 'toggle', __( 'Search icon in field', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'size'           => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
			'post_type'      => Fields::field( 'post_type', __( 'Search in', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty to search everything.', 'brik-builder' ) ) ),
		),
		Fields::box( 'input', __( 'Input', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::typography( 'input', __( 'Input text', 'brik-builder' ), Fields::WRAP . ' .brik-input' ),
		Fields::box( 'button', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-button' ),
		Fields::typography( 'button', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-button' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$large  = 'lg' === $a['size'];
		$icon   = brik_form_bool( $a['show_icon'] );
		$id     = $ctx->uid( 'q' );
		$button = in_array( $a['button'], array( 'text', 'icon', 'none' ), true ) ? $a['button'] : 'text';
		$value  = $ctx->canvas ? '' : get_search_query();

		$input = '<input' . brik_attrs(
			array(
				'id'          => $id,
				'type'        => 'search',
				'name'        => 's',
				'value'       => $value,
				'placeholder' => $a['placeholder'],
				'class'       => brik_input_class( brik_cls( $large ? 'h-11 text-base md:text-base' : '', $icon ? 'pl-9' : '' ) ),
			)
		) . '>';

		$field = '<div class="relative min-w-0 flex-1"><label class="sr-only" for="' . esc_attr( $id ) . '">' . esc_html__( 'Search for:', 'brik-builder' ) . '</label>'
			. ( $icon ? brik_icon( 'search', 'pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground' ) : '' )
			. $input . '</div>';

		$submit = '';
		if ( 'text' === $button ) {
			$submit = '<button type="submit" class="' . esc_attr( brik_button_class( $a['button_variant'], $large ? 'lg' : 'default', $large ? 'h-11' : '' ) ) . '">' . brik_inline( $a['button_text'] ) . '</button>';
		} elseif ( 'icon' === $button ) {
			$submit = '<button type="submit" class="' . esc_attr( brik_button_class( $a['button_variant'], 'icon', $large ? 'size-11' : '' ) ) . '" aria-label="' . esc_attr__( 'Search', 'brik-builder' ) . '">' . brik_icon( 'search' ) . '</button>';
		}

		$type = sanitize_key( (string) $a['post_type'] );
		$type = $type && post_type_exists( $type ) ? '<input type="hidden" name="post_type" value="' . esc_attr( $type ) . '">' : '';

		return '<form role="search" method="get" class="brik-search-form flex w-full items-center gap-2" action="' . esc_url( home_url( '/' ) ) . '">'
			. $field . $submit . $type . '</form>';
	},
);
