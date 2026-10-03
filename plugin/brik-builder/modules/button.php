<?php
/**
 * Button (shadcn/ui button).
 *
 * Module definition format:
 *   type, title, category, icon (Lucide name), description (also shown to AI clients),
 *   fields: key => Fields::field( type, label, group, extra ), see includes/Fields.php,
 *   render: fn( array $attrs, Brik\Context $ctx ) => inner HTML of the element wrapper.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'button',
	'title'       => __( 'Button', 'brik-builder' ),
	'category'    => 'basic',
	'icon'        => 'mouse-pointer-click',
	'description' => 'Call-to-action link styled as a shadcn button. variant: default|secondary|outline|ghost|link|destructive. size: sm|default|lg|xl. icon: any Lucide icon name.',
	'fields'      => array_merge(
		array(
			'text'          => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Get started', 'brik-builder' ), 'inline' => true ) ),
			'link'          => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content', array( 'default' => array( 'url' => '#' ) ) ),
			'variant'       => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
			'size'          => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'default', 'options' => Fields::opts( array( 'sm' => __( 'Small', 'brik-builder' ), 'default' => __( 'Default', 'brik-builder' ), 'lg' => __( 'Large', 'brik-builder' ), 'xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'icon'          => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content' ),
			'icon_position' => Fields::field( 'select', __( 'Icon position', 'brik-builder' ), 'content', array( 'default' => 'end', 'options' => Fields::opts( array( 'start' => __( 'Before text', 'brik-builder' ), 'end' => __( 'After text', 'brik-builder' ) ) ), 'show_if' => array( 'icon' => '!' ) ) ),
			'full'          => Fields::field( 'toggle', __( 'Full width', 'brik-builder' ), 'content', array( 'responsive' => true ) ),
			'align'         => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
			'rel'           => Fields::field( 'text', __( 'Rel attribute', 'brik-builder' ), 'attributes', array( 'tab' => 'advanced' ) ),
		),
		Fields::box( 'button', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-button' ),
		Fields::typography( 'button', __( 'Button text', 'brik-builder' ), Fields::WRAP . ' .brik-button' )
	),
	'render'      => static function ( $a, $ctx ) {
		$icon = ! empty( $a['icon'] ) ? brik_icon( $a['icon'] ) : '';
		$text = '<span' . $ctx->inline( 'text' ) . '>' . brik_inline( $a['text'] ) . '</span>';
		$body = 'start' === $a['icon_position'] ? $icon . $text : $text . $icon;

		$extra = array( 'class' => brik_button_class( $a['variant'], $a['size'], ! empty( $a['full'] ) ? 'w-full' : '' ) );
		if ( ! empty( $a['rel'] ) ) {
			$extra['rel'] = $a['rel'];
		}
		return '<a' . brik_link_attrs( $a['link'], $extra ) . '>' . $body . '</a>';
	},
);
