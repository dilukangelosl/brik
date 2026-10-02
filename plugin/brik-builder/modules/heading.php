<?php
/**
 * Heading.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'heading',
	'title'       => __( 'Heading', 'brik' ),
	'category'    => 'basic',
	'icon'        => 'heading',
	'description' => 'Title text. "text" accepts inline HTML (<strong>, <em>, <span class="text-primary">). "style" picks a preset size; leave empty to size by level.',
	'fields'      => array_merge(
		array(
			'text'  => Fields::field( 'text', __( 'Text', 'brik' ), 'content', array( 'default' => __( 'Your heading here', 'brik' ), 'inline' => true ) ),
			'level' => Fields::field( 'select', __( 'HTML tag', 'brik' ), 'content', array( 'default' => 'h2', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p', 'div' => 'div' ) ) ) ),
			'style' => Fields::field(
				'select',
				__( 'Size', 'brik' ),
				'content',
				array(
					'options' => Fields::opts(
						array(
							''        => __( 'By tag', 'brik' ),
							'display' => __( 'Display', 'brik' ),
							'h1'      => 'H1',
							'h2'      => 'H2',
							'h3'      => 'H3',
							'h4'      => 'H4',
							'lead'    => __( 'Lead', 'brik' ),
							'eyebrow' => __( 'Eyebrow', 'brik' ),
						)
					),
				)
			),
			'link'  => Fields::field( 'link', __( 'Link', 'brik' ), 'content' ),
			'gradient_text' => Fields::field( 'gradient', __( 'Gradient text', 'brik' ), 'text_effects', array( 'tab' => 'design', 'css' => array( 'selector' => Fields::WRAP . ' .brik-heading-text', 'prop' => 'background-image', 'value' => '{{v}};-webkit-background-clip:text;background-clip:text;color:transparent' ) ) ),
		),
		Fields::typography( 'title', __( 'Heading text', 'brik' ), Fields::WRAP . ' .brik-heading-text' )
	),
	'render'      => static function ( $a ) {
		$styles = array(
			'display' => 'text-5xl md:text-6xl lg:text-7xl font-bold tracking-tight text-balance',
			'h1'      => 'text-4xl md:text-5xl font-extrabold tracking-tight text-balance',
			'h2'      => 'text-3xl md:text-4xl font-semibold tracking-tight text-balance',
			'h3'      => 'text-2xl font-semibold tracking-tight',
			'h4'      => 'text-xl font-semibold tracking-tight',
			'h5'      => 'text-lg font-semibold',
			'h6'      => 'text-base font-semibold',
			'lead'    => 'text-xl text-muted-foreground',
			'eyebrow' => 'text-sm font-semibold uppercase tracking-wider text-primary',
		);
		$tag   = in_array( $a['level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ), true ) ? $a['level'] : 'h2';
		$style = ! empty( $a['style'] ) && isset( $styles[ $a['style'] ] ) ? $a['style'] : ( isset( $styles[ $tag ] ) ? $tag : 'h2' );
		$text  = brik_inline( $a['text'] );
		if ( ! empty( $a['link']['url'] ) ) {
			$text = '<a' . brik_link_attrs( $a['link'], array( 'class' => 'hover:underline underline-offset-4' ) ) . '>' . $text . '</a>';
		}
		return sprintf( '<%1$s class="brik-heading-text font-heading %2$s">%3$s</%1$s>', $tag, esc_attr( $styles[ $style ] ), $text );
	},
);
