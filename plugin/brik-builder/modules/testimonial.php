<?php
/**
 * Testimonial: quote with author, rating and company logo.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'testimonial',
	'title'       => __( 'Testimonial', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'quote',
	'description' => 'Customer quote. quote (text), name, role, avatar (image; initials fallback), rating (0-5, 0 hides stars), logo (company logo image), show_quote_icon toggle. style: card|plain|centered.',
	'fields'      => array_merge(
		array(
			'quote'           => Fields::field( 'textarea', __( 'Quote', 'brik-builder' ), 'content', array( 'default' => __( 'We rebuilt our marketing site in a weekend. The components look exactly like our product, and the team can ship landing pages without waiting on engineering.', 'brik-builder' ), 'inline' => true ) ),
			'name'            => Fields::field( 'text', __( 'Name', 'brik-builder' ), 'content', array( 'default' => 'Sofia Davis', 'inline' => true ) ),
			'role'            => Fields::field( 'text', __( 'Role', 'brik-builder' ), 'content', array( 'default' => __( 'Head of Marketing, Acme Inc.', 'brik-builder' ), 'inline' => true ) ),
			'avatar'          => Fields::field( 'image', __( 'Avatar', 'brik-builder' ), 'content' ),
			'rating'          => Fields::field( 'range', __( 'Rating', 'brik-builder' ), 'content', array( 'default' => 5, 'min' => 0, 'max' => 5, 'step' => 0.5 ) ),
			'logo'            => Fields::field( 'image', __( 'Company logo', 'brik-builder' ), 'content' ),
			'show_quote_icon' => Fields::field( 'toggle', __( 'Quote mark', 'brik-builder' ), 'content' ),
			'style'           => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'card', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ), 'centered' => __( 'Centered', 'brik-builder' ) ) ) ) ),
			'star_color'      => Fields::field( 'color', __( 'Star color', 'brik-builder' ), 'card', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-star-full', 'color' ) ) ),
			'logo_height'     => Fields::field( 'unit', __( 'Logo height', 'brik-builder' ), 'card', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'placeholder' => '28px', 'css' => array( Fields::WRAP . ' .brik-testimonial-logo', 'height' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-testimonial-box' ),
		Fields::typography( 'quote', __( 'Quote', 'brik-builder' ), Fields::WRAP . ' .brik-testimonial-quote' ),
		Fields::typography( 'name', __( 'Name', 'brik-builder' ), Fields::WRAP . ' .brik-testimonial-name' ),
		Fields::typography( 'role', __( 'Role', 'brik-builder' ), Fields::WRAP . ' .brik-testimonial-role' )
	),
	'render'      => static function ( $a, $ctx ) {
		$style    = in_array( $a['style'], array( 'card', 'plain', 'centered' ), true ) ? $a['style'] : 'card';
		$centered = 'centered' === $style;

		$top = '';
		if ( ! empty( $a['show_quote_icon'] ) ) {
			$top .= brik_icon( 'quote', 'brik-testimonial-mark size-8 text-primary/25 fill-current stroke-none' );
		}
		if ( (float) $a['rating'] > 0 ) {
			$top .= brik_stars( $a['rating'], 5, 'size-4' );
		}
		if ( $centered && brik_has_image( $a['logo'] ) ) {
			$top = brik_image( $a['logo'], 'medium', array( 'class' => 'brik-testimonial-logo h-8 w-auto opacity-80' ) ) . $top;
		}

		$quote_classes = array(
			'card'     => 'text-base leading-relaxed',
			'plain'    => 'text-lg leading-relaxed font-medium tracking-tight',
			'centered' => 'text-lg leading-relaxed font-medium tracking-tight text-balance md:text-xl',
		);
		$quote = '' === trim( (string) $a['quote'] ) ? '' : '<blockquote class="' . esc_attr( brik_cls( 'brik-testimonial-quote', $quote_classes[ $style ] ) ) . '"><p>&ldquo;<span' . $ctx->inline( 'quote' ) . '>' . nl2br( brik_inline( trim( (string) $a['quote'] ) ) ) . '</span>&rdquo;</p></blockquote>';

		$who = '';
		if ( '' !== trim( (string) $a['name'] ) ) {
			$who .= '<div class="brik-testimonial-name text-sm font-semibold"' . $ctx->inline( 'name' ) . '>' . brik_inline( $a['name'] ) . '</div>';
		}
		if ( '' !== trim( (string) $a['role'] ) ) {
			$who .= '<div class="brik-testimonial-role text-sm text-muted-foreground"' . $ctx->inline( 'role' ) . '>' . brik_inline( $a['role'] ) . '</div>';
		}
		$author = brik_avatar( $a['avatar'], $a['name'], $centered ? 'xl' : 'default' ) . '<div class="' . esc_attr( brik_cls( 'grid min-w-0 gap-0.5', array( 'text-left' => ! $centered ) ) ) . '">' . $who . '</div>';
		if ( ! $centered && brik_has_image( $a['logo'] ) ) {
			$author .= brik_image( $a['logo'], 'medium', array( 'class' => 'brik-testimonial-logo ml-auto h-7 w-auto shrink-0 opacity-80' ) );
		}

		$box = array(
			'card'     => 'rounded-xl border bg-card p-6 text-card-foreground shadow-sm',
			'plain'    => 'border-l-2 border-primary pl-6',
			'centered' => 'items-center text-center',
		);
		$caption = brik_cls( 'flex gap-3', $centered ? 'flex-col items-center' : 'mt-auto items-center' );
		return '<figure class="' . esc_attr( brik_cls( 'brik-testimonial-box flex h-full flex-col gap-5', $box[ $style ] ) ) . '">'
			. ( '' !== $top ? '<div class="' . esc_attr( brik_cls( 'flex items-center gap-3', $centered ? 'flex-col' : 'justify-between' ) ) . '">' . $top . '</div>' : '' )
			. $quote
			. '<figcaption class="' . esc_attr( $caption ) . '">' . $author . '</figcaption>'
			. '</figure>';
	},
);
