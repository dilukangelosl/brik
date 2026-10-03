<?php
/**
 * Card (shadcn/ui card): image, header, body and footer buttons.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'card',
	'title'       => __( 'Card', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'layout-panel-top',
	'description' => 'shadcn card. image (optional), image_ratio: auto|16/9|4/3|1/1|3/2, title, description, badge (optional pill text above the title), content (rich text body), buttons: repeater [{text, link, variant: default|secondary|outline|ghost|link, icon}], layout: vertical|horizontal (image beside content on desktop), image_side: left|right, footer_align: start|end|between|stretch, hover_lift: toggle.',
	'fields'      => array_merge(
		array(
			'image'        => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content' ),
			'image_ratio'  => Fields::field( 'select', __( 'Image ratio', 'brik-builder' ), 'content', array( 'default' => '16/9', 'options' => Fields::opts( array( 'auto' => __( 'Original', 'brik-builder' ), '16/9' => '16:9', '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1' ) ), 'show_if' => array( 'image' => '!' ) ) ),
			'badge'        => Fields::field( 'text', __( 'Badge', 'brik-builder' ), 'content', array( 'inline' => true ) ),
			'title'        => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Launch your next project', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'    => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) ) ),
			'description'  => Fields::field( 'text', __( 'Description', 'brik-builder' ), 'content', array( 'default' => __( 'Deploy in one click and scale without thinking about servers.', 'brik-builder' ), 'inline' => true ) ),
			'content'      => Fields::field( 'richtext', __( 'Body', 'brik-builder' ), 'content', array( 'default' => '<p>' . __( 'Everything you need to go from idea to production: preview environments, analytics and a global edge network, all configured out of the box.', 'brik-builder' ) . '</p>', 'inline' => true ) ),
			'buttons'      => Fields::field(
				'repeater',
				__( 'Footer buttons', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'text',
					'fields'      => array(
						'text'    => Fields::field( 'text', __( 'Text', 'brik-builder' ) ),
						'link'    => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
						'variant' => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
						'icon'    => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'text' => __( 'Get started', 'brik-builder' ), 'link' => array( 'url' => '#' ), 'variant' => 'default', 'icon' => 'arrow-right' ),
						array( 'text' => __( 'Learn more', 'brik-builder' ), 'link' => array( 'url' => '#' ), 'variant' => 'outline', 'icon' => '' ),
					),
				)
			),
			'layout'       => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'vertical', 'options' => Fields::opts( array( 'vertical' => __( 'Vertical', 'brik-builder' ), 'horizontal' => __( 'Horizontal', 'brik-builder' ) ) ) ) ),
			'image_side'   => Fields::field( 'select', __( 'Image side', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'right' => __( 'Right', 'brik-builder' ) ) ), 'show_if' => array( 'layout' => 'horizontal' ) ) ),
			'footer_align' => Fields::field( 'select', __( 'Footer alignment', 'brik-builder' ), 'content', array( 'default' => 'start', 'options' => Fields::opts( array( 'start' => __( 'Start', 'brik-builder' ), 'end' => __( 'End', 'brik-builder' ), 'between' => __( 'Space between', 'brik-builder' ), 'stretch' => __( 'Stretch', 'brik-builder' ) ) ) ) ),
			'hover_lift'   => Fields::field( 'toggle', __( 'Lift on hover', 'brik-builder' ), 'content' ),
			'image_width'  => Fields::field( 'unit', __( 'Image width', 'brik-builder' ), 'card', array( 'tab' => 'design', 'group_label' => __( 'Card', 'brik-builder' ), 'placeholder' => '40%', 'show_if' => array( 'layout' => 'horizontal' ), 'css' => array( Fields::WRAP . ' .brik-card-media', '--brik-card-media' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-card-box' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-card-title' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-card-desc' ),
		Fields::typography( 'body', __( 'Body', 'brik-builder' ), Fields::WRAP . ' .brik-card-content' )
	),
	'render'      => static function ( $a, $ctx ) {
		$horizontal = 'horizontal' === $a['layout'];
		$ratios     = array(
			'16/9' => 'aspect-video',
			'3/2'  => 'aspect-[3/2]',
			'4/3'  => 'aspect-[4/3]',
			'1/1'  => 'aspect-square',
			'auto' => '',
		);

		$media = '';
		if ( brik_has_image( $a['image'] ) ) {
			$ratio = isset( $ratios[ $a['image_ratio'] ] ) ? $ratios[ $a['image_ratio'] ] : $ratios['16/9'];
			$img   = brik_image( $a['image'], 'large', array( 'class' => brik_cls( 'size-full object-cover', $horizontal ? 'md:absolute md:inset-0' : '' ) ) );
			$media = '<div class="' . esc_attr( brik_cls( 'brik-card-media relative overflow-hidden bg-muted', $ratio, $horizontal ? 'md:aspect-auto md:w-[var(--brik-card-media,40%)] md:shrink-0' : '' ) ) . '">' . $img . '</div>';
		}

		$tag    = in_array( $a['title_tag'], array( 'h2', 'h3', 'h4', 'div' ), true ) ? $a['title_tag'] : 'h3';
		$header = '';
		if ( '' !== trim( (string) $a['badge'] ) ) {
			$header .= '<span class="' . esc_attr( brik_badge_class( 'secondary', 'default', 'mb-1' ) ) . '"' . $ctx->inline( 'badge' ) . '>' . brik_inline( $a['badge'] ) . '</span>';
		}
		if ( '' !== trim( (string) $a['title'] ) ) {
			$header .= '<' . $tag . ' class="brik-card-title font-heading text-lg leading-snug font-semibold tracking-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</' . $tag . '>';
		}
		if ( '' !== trim( (string) $a['description'] ) ) {
			$header .= '<p class="brik-card-desc text-sm text-muted-foreground"' . $ctx->inline( 'description' ) . '>' . brik_inline( $a['description'] ) . '</p>';
		}

		$body = '';
		if ( '' !== $header ) {
			$body .= '<div class="brik-card-header grid auto-rows-min items-start gap-1.5 px-6">' . $header . '</div>';
		}
		if ( '' !== trim( wp_strip_all_tags( (string) $a['content'] ) ) ) {
			$body .= '<div class="brik-card-content brik-prose px-6 text-sm leading-relaxed"' . $ctx->inline( 'content' ) . '>' . brik_rich( $a['content'] ) . '</div>';
		}

		$buttons = '';
		$stretch = 'stretch' === $a['footer_align'];
		foreach ( brik_items( $a['buttons'] ) as $btn ) {
			if ( '' === trim( (string) brik_item( $btn, 'text' ) ) ) {
				continue;
			}
			$icon     = brik_item( $btn, 'icon' ) ? brik_icon( $btn['icon'] ) : '';
			$buttons .= '<a' . brik_link_attrs( brik_item( $btn, 'link', '#' ), array( 'class' => brik_button_class( brik_item( $btn, 'variant', 'default' ), 'default', $stretch ? 'flex-1' : '' ) ) ) . '><span>' . brik_inline( $btn['text'] ) . '</span>' . $icon . '</a>';
		}
		if ( '' !== $buttons ) {
			$justify = array(
				'start'   => 'justify-start',
				'end'     => 'justify-end',
				'between' => 'justify-between',
				'stretch' => '',
			);
			$body   .= '<div class="' . esc_attr( brik_cls( 'brik-card-footer mt-auto flex flex-wrap items-center gap-2 px-6', isset( $justify[ $a['footer_align'] ] ) ? $justify[ $a['footer_align'] ] : '' ) ) . '">' . $buttons . '</div>';
		}

		$box = brik_cls(
			'brik-card-box flex h-full flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-sm',
			array(
				'md:flex-row'         => $horizontal && '' !== $media && 'right' !== $a['image_side'],
				'md:flex-row-reverse' => $horizontal && '' !== $media && 'right' === $a['image_side'],
				'transition-[translate,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-lg' => ! empty( $a['hover_lift'] ),
			)
		);
		return '<div class="' . esc_attr( $box ) . '">' . $media . '<div class="brik-card-body flex flex-1 flex-col gap-6 py-6">' . $body . '</div></div>';
	},
);
