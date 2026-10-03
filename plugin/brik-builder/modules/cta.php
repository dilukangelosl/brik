<?php
/**
 * Call to action box.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'cta',
	'title'       => __( 'Call to Action', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'megaphone',
	'description' => 'Call-to-action box. eyebrow, title, text, primary_text/primary_link/primary_variant/primary_icon, secondary_text/secondary_link/secondary_variant (empty text hides a button). layout: stacked|inline (text left, buttons right on desktop). align: left|center (stacked). style: muted|card|primary|plain|gradient. Button variants: default|secondary|outline|ghost|link.',
	'fields'      => array_merge(
		array(
			'eyebrow'           => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content', array( 'default' => __( 'Get started today', 'brik-builder' ), 'inline' => true ) ),
			'title'             => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Ready to build something great?', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'         => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) ) ),
			'text'              => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Join thousands of teams shipping faster with a builder that stays out of the way. Free for 14 days, no credit card required.', 'brik-builder' ), 'inline' => true ) ),
			'primary_text'      => Fields::field( 'text', __( 'Primary button', 'brik-builder' ), 'primary', array( 'default' => __( 'Start free trial', 'brik-builder' ), 'group_label' => __( 'Primary button', 'brik-builder' ) ) ),
			'primary_link'      => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'primary', array( 'default' => array( 'url' => '#' ), 'group_label' => __( 'Primary button', 'brik-builder' ) ) ),
			'primary_variant'   => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'primary', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ), 'group_label' => __( 'Primary button', 'brik-builder' ) ) ),
			'primary_icon'      => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'primary', array( 'default' => 'arrow-right', 'group_label' => __( 'Primary button', 'brik-builder' ) ) ),
			'secondary_text'    => Fields::field( 'text', __( 'Secondary button', 'brik-builder' ), 'secondary', array( 'default' => __( 'Talk to sales', 'brik-builder' ), 'group_label' => __( 'Secondary button', 'brik-builder' ) ) ),
			'secondary_link'    => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'secondary', array( 'default' => array( 'url' => '#' ), 'group_label' => __( 'Secondary button', 'brik-builder' ) ) ),
			'secondary_variant' => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'secondary', array( 'default' => 'outline', 'options' => Fields::opts( brik_button_variants_labels() ), 'group_label' => __( 'Secondary button', 'brik-builder' ) ) ),
			'secondary_icon'    => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'secondary', array( 'group_label' => __( 'Secondary button', 'brik-builder' ) ) ),
			'button_size'       => Fields::field( 'select', __( 'Button size', 'brik-builder' ), 'content', array( 'default' => 'lg', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'layout'            => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'stacked', 'options' => Fields::opts( array( 'stacked' => __( 'Stacked', 'brik-builder' ), 'inline' => __( 'Inline', 'brik-builder' ) ) ) ) ),
			'align'             => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'center', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ), 'show_if' => array( 'layout' => 'stacked' ) ) ),
			'style'             => Fields::field(
				'select',
				__( 'Style', 'brik-builder' ),
				'content',
				array(
					'default' => 'muted',
					'options' => Fields::opts(
						array(
							'muted'    => __( 'Muted', 'brik-builder' ),
							'card'     => __( 'Card', 'brik-builder' ),
							'primary'  => __( 'Primary', 'brik-builder' ),
							'gradient' => __( 'Gradient', 'brik-builder' ),
							'plain'    => __( 'Plain', 'brik-builder' ),
						)
					),
				)
			),
		),
		Fields::box( 'box', __( 'Box', 'brik-builder' ), Fields::WRAP . ' .brik-cta-box' ),
		Fields::typography( 'eyebrow', __( 'Eyebrow', 'brik-builder' ), Fields::WRAP . ' .brik-cta-eyebrow' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-cta-title' ),
		Fields::typography( 'body', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-cta-text' )
	),
	'render'      => static function ( $a, $ctx ) {
		$style  = in_array( $a['style'], array( 'muted', 'card', 'primary', 'gradient', 'plain' ), true ) ? $a['style'] : 'muted';
		$inline = 'inline' === $a['layout'];
		$center = ! $inline && 'center' === $a['align'];
		$styles = array(
			'muted'    => 'rounded-2xl bg-muted px-6 py-10 md:px-12 md:py-14',
			'card'     => 'rounded-2xl border bg-card px-6 py-10 text-card-foreground shadow-sm md:px-12 md:py-14',
			// Re-point the tokens the button recipes use so outline/ghost buttons read on the dark fill.
			'primary'  => 'rounded-2xl bg-primary px-6 py-10 text-primary-foreground md:px-12 md:py-14 [--background:transparent] [--input:color-mix(in_oklab,var(--primary-foreground)_35%,transparent)] [--accent:color-mix(in_oklab,var(--primary-foreground)_12%,transparent)] [--accent-foreground:var(--primary-foreground)] [--muted-foreground:color-mix(in_oklab,var(--primary-foreground)_75%,transparent)]',
			'gradient' => 'rounded-2xl border bg-linear-to-br from-primary/10 via-background to-primary/5 px-6 py-10 md:px-12 md:py-14',
			'plain'    => 'py-4',
		);

		$tag  = in_array( $a['title_tag'], array( 'h2', 'h3', 'h4', 'div' ), true ) ? $a['title_tag'] : 'h2';
		$text = '';
		if ( '' !== trim( (string) $a['eyebrow'] ) ) {
			$text .= '<p class="' . esc_attr( brik_cls( 'brik-cta-eyebrow text-sm font-semibold tracking-wide', 'primary' === $style ? 'text-primary-foreground/80' : 'text-primary' ) ) . '"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		if ( '' !== trim( (string) $a['title'] ) ) {
			$text .= '<' . $tag . ' class="brik-cta-title font-heading text-3xl font-semibold tracking-tight text-balance md:text-4xl"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</' . $tag . '>';
		}
		if ( '' !== trim( (string) $a['text'] ) ) {
			$text .= '<p class="' . esc_attr( brik_cls( 'brik-cta-text max-w-2xl text-base leading-relaxed text-muted-foreground md:text-lg', array( 'mx-auto' => $center ) ) ) . '"' . $ctx->inline( 'text' ) . '>' . nl2br( brik_inline( $a['text'] ) ) . '</p>';
		}

		$buttons = '';
		foreach ( array( 'primary', 'secondary' ) as $which ) {
			if ( '' === trim( (string) $a[ $which . '_text' ] ) ) {
				continue;
			}
			$variant = $a[ $which . '_variant' ];
			if ( 'primary' === $style && 'default' === $variant ) {
				$variant = 'secondary';
			}
			$icon     = ! empty( $a[ $which . '_icon' ] ) ? brik_icon( $a[ $which . '_icon' ] ) : '';
			$buttons .= '<a' . brik_link_attrs( $a[ $which . '_link' ], array( 'class' => brik_button_class( $variant, $a['button_size'] ) ) ) . '><span' . $ctx->inline( $which . '_text' ) . '>' . brik_inline( $a[ $which . '_text' ] ) . '</span>' . $icon . '</a>';
		}
		if ( '' !== $buttons ) {
			$buttons = '<div class="' . esc_attr( brik_cls( 'brik-cta-actions flex flex-wrap gap-3', array( 'justify-center' => $center, 'md:shrink-0 md:justify-end' => $inline ) ) ) . '">' . $buttons . '</div>';
		}

		$box = brik_cls(
			'brik-cta-box flex flex-col gap-8',
			$styles[ $style ],
			array(
				'items-center text-center'                  => $center,
				'md:flex-row md:items-center md:justify-between md:gap-12' => $inline,
			)
		);
		return '<div class="' . esc_attr( $box ) . '"><div class="' . esc_attr( brik_cls( 'brik-cta-content grid gap-3', array( 'justify-items-center' => $center ) ) ) . '">' . $text . '</div>' . $buttons . '</div>';
	},
);
