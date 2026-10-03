<?php
/**
 * Blurb: icon or image with a title, text and optional link.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'blurb',
	'title'       => __( 'Blurb', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'box',
	'description' => 'Feature blurb. media: icon|image|none; icon (Lucide/brand), image. icon_shape: none|circle|rounded|square, icon_tone: soft|solid|outline|muted, icon_size: sm|default|lg|xl. title, content (rich text), link + link_text (shown as "Learn more" link; title links too). media_position: top|left. align: left|center (top position). boxed: toggle card look. hover_lift: toggle.',
	'fields'      => array_merge(
		array(
			'media'      => Fields::field( 'select', __( 'Media', 'brik-builder' ), 'content', array( 'default' => 'icon', 'options' => Fields::opts( array( 'icon' => __( 'Icon', 'brik-builder' ), 'image' => __( 'Image', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
			'icon'       => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'zap', 'show_if' => array( 'media' => 'icon' ) ) ),
			'image'      => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'show_if' => array( 'media' => 'image' ) ) ),
			'icon_shape' => Fields::field( 'select', __( 'Icon shape', 'brik-builder' ), 'content', array( 'default' => 'rounded', 'options' => Fields::opts( brik_icon_shape_options() ), 'show_if' => array( 'media' => 'icon' ) ) ),
			'icon_tone'  => Fields::field( 'select', __( 'Icon style', 'brik-builder' ), 'content', array( 'default' => 'soft', 'options' => Fields::opts( brik_icon_tone_options() ), 'show_if' => array( 'media' => 'icon' ) ) ),
			'icon_size'  => Fields::field( 'select', __( 'Icon size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ), 'show_if' => array( 'media' => 'icon' ) ) ),
			'title'      => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Lightning fast', 'brik-builder' ), 'inline' => true ) ),
			'title_tag'  => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'div' => 'div' ) ) ) ),
			'content'    => Fields::field( 'richtext', __( 'Text', 'brik-builder' ), 'content', array( 'default' => '<p>' . __( 'Pages render on the server and ship almost no JavaScript, so they load instantly on any device.', 'brik-builder' ) . '</p>', 'inline' => true ) ),
			'link'       => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
			'link_text'  => Fields::field( 'text', __( 'Link text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Learn more', 'brik-builder' ), 'show_if' => array( 'link' => '!' ) ) ),
			'media_position'   => Fields::field( 'select', __( 'Media position', 'brik-builder' ), 'content', array( 'default' => 'top', 'responsive' => true, 'options' => Fields::opts( array( 'top' => __( 'Top', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ) ) ),
			'align'      => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ), 'show_if' => array( 'media_position' => 'top' ) ) ),
			'boxed'      => Fields::field( 'toggle', __( 'Card style', 'brik-builder' ), 'content' ),
			'hover_lift' => Fields::field( 'toggle', __( 'Lift on hover', 'brik-builder' ), 'content' ),
			'icon_px'    => Fields::field( 'unit', __( 'Custom icon size', 'brik-builder' ), 'icon', array( 'tab' => 'design', 'group_label' => __( 'Icon', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-icon-shape', '--brik-icon-size' ) ) ),
			'shape_size' => Fields::field( 'unit', __( 'Shape size', 'brik-builder' ), 'icon', array( 'tab' => 'design', 'group_label' => __( 'Icon', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-icon-shape', array( 'width', 'height' ) ) ) ),
			'image_width' => Fields::field( 'unit', __( 'Image width', 'brik-builder' ), 'icon', array( 'tab' => 'design', 'group_label' => __( 'Icon', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-blurb-image', 'width' ) ) ),
		),
		Fields::box( 'icon', __( 'Icon', 'brik-builder' ), Fields::WRAP . ' .brik-icon-shape', array( 'bg', 'color', 'border_width', 'border_color', 'radius', 'shadow' ) ),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-blurb-box' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-blurb-title' ),
		Fields::typography( 'body', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-blurb-text' ),
		Fields::typography( 'link', __( 'Link', 'brik-builder' ), Fields::WRAP . ' .brik-blurb-more' )
	),
	'class'       => static function ( $a ) {
		$pos = array(
			'desktop' => $a['media_position'],
			'tablet'  => isset( $a['media_position@tablet'] ) && '' !== $a['media_position@tablet'] ? $a['media_position@tablet'] : '',
			'mobile'  => isset( $a['media_position@mobile'] ) && '' !== $a['media_position@mobile'] ? $a['media_position@mobile'] : '',
		);
		return brik_cls(
			array(
				'brik-blurb--left'        => 'left' === $pos['desktop'],
				'brik-blurb--t-left'      => 'left' === $pos['tablet'],
				'brik-blurb--t-top'       => 'top' === $pos['tablet'],
				'brik-blurb--m-left'      => 'left' === $pos['mobile'],
				'brik-blurb--m-top'       => 'top' === $pos['mobile'],
				'brik-blurb--center'      => 'center' === $a['align'],
			)
		);
	},
	'render'      => static function ( $a, $ctx ) {
		$media = '';
		if ( 'icon' === $a['media'] && ! empty( $a['icon'] ) ) {
			$media = brik_icon_shape( $a['icon'], $a['icon_shape'], $a['icon_tone'], $a['icon_size'] );
		} elseif ( 'image' === $a['media'] && brik_has_image( $a['image'] ) ) {
			$media = '<div class="brik-blurb-media shrink-0">' . brik_image( $a['image'], 'medium_large', array( 'class' => 'brik-blurb-image rounded-lg' ) ) . '</div>';
		}

		$has_link = brik_has_link( $a['link'] );
		$tag      = in_array( $a['title_tag'], array( 'h2', 'h3', 'h4', 'h5', 'div' ), true ) ? $a['title_tag'] : 'h3';
		$text     = '';
		if ( '' !== trim( (string) $a['title'] ) ) {
			$title = brik_inline( $a['title'] );
			if ( $has_link ) {
				$title = '<a' . brik_link_attrs( $a['link'], array( 'class' => 'hover:underline underline-offset-4' ) ) . '>' . $title . '</a>';
			}
			$text .= '<' . $tag . ' class="brik-blurb-title font-heading text-lg leading-snug font-semibold tracking-tight"' . ( $has_link ? '' : $ctx->inline( 'title' ) ) . '>' . $title . '</' . $tag . '>';
		}
		if ( '' !== trim( wp_strip_all_tags( (string) $a['content'] ) ) ) {
			$text .= '<div class="brik-blurb-text brik-prose text-sm leading-relaxed text-muted-foreground"' . $ctx->inline( 'content' ) . '>' . brik_rich( $a['content'] ) . '</div>';
		}
		if ( $has_link && '' !== trim( (string) $a['link_text'] ) ) {
			$text .= '<a' . brik_link_attrs( $a['link'], array( 'class' => 'brik-blurb-more group/more mt-1 inline-flex w-fit items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline' ) ) . '>' . brik_inline( $a['link_text'] ) . brik_icon( 'arrow-right', 'size-4 transition-transform group-hover/more:translate-x-0.5' ) . '</a>';
		}

		$box = brik_cls(
			'brik-blurb-box flex h-full gap-4',
			array(
				'rounded-xl border bg-card p-6 text-card-foreground shadow-sm' => ! empty( $a['boxed'] ),
				'transition-[translate,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-lg' => ! empty( $a['hover_lift'] ),
			)
		);
		return '<div class="' . esc_attr( $box ) . '">' . $media . '<div class="brik-blurb-body grid min-w-0 flex-1 content-start gap-2">' . $text . '</div></div>';
	},
);
