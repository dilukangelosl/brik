<?php
/**
 * Badge (shadcn/ui badge).
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'badge',
	'title'       => __( 'Badge', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'tag',
	'description' => 'Small pill label. text. variant: default|secondary|outline|destructive|success|warning|info. size: sm|default|lg. icon: Lucide name, icon_position: start|end. link: optional. align: left|center|right.',
	'fields'      => array_merge(
		array(
			'text'          => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'New release', 'brik-builder' ), 'inline' => true ) ),
			'variant'       => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'content', array( 'default' => 'secondary', 'options' => Fields::opts( brik_badge_variant_options() ) ) ),
			'size'          => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ) ) ) ) ),
			'icon'          => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'sparkles' ) ),
			'icon_position' => Fields::field( 'select', __( 'Icon position', 'brik-builder' ), 'content', array( 'default' => 'start', 'options' => Fields::opts( array( 'start' => __( 'Before text', 'brik-builder' ), 'end' => __( 'After text', 'brik-builder' ) ) ), 'show_if' => array( 'icon' => '!' ) ) ),
			'link'          => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
			'align'         => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::box( 'badge', __( 'Badge', 'brik-builder' ), Fields::WRAP . ' .brik-badge-pill' ),
		Fields::typography( 'badge', __( 'Badge text', 'brik-builder' ), Fields::WRAP . ' .brik-badge-pill' )
	),
	'render'      => static function ( $a, $ctx ) {
		$icon = ! empty( $a['icon'] ) ? brik_icon( $a['icon'], '' ) : '';
		$text = '' !== trim( (string) $a['text'] ) ? '<span' . $ctx->inline( 'text' ) . '>' . brik_inline( $a['text'] ) . '</span>' : '';
		$body = 'end' === $a['icon_position'] ? $text . $icon : $icon . $text;
		if ( '' === $text && '' === $icon ) {
			return $ctx->placeholder( __( 'Add badge text', 'brik-builder' ) );
		}
		$cls  = brik_badge_class( $a['variant'], $a['size'] );
		if ( brik_has_link( $a['link'] ) ) {
			return '<a' . brik_link_attrs( $a['link'], array( 'class' => $cls ) ) . '>' . $body . '</a>';
		}
		return '<span class="' . esc_attr( $cls ) . '">' . $body . '</span>';
	},
);
