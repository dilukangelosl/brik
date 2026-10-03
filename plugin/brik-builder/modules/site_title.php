<?php
/**
 * Site title and tagline.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'site_title',
	'title'       => __( 'Site title', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'type',
	'description' => 'Site name from Settings → General, optionally with the tagline. level: h1|h2|p|div (default p). show_tagline: bool. layout: stacked|inline. link: bool, links to the home page.',
	'fields'      => array_merge(
		array(
			'level'        => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'p', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'p' => 'p', 'div' => 'div' ) ) ) ),
			'show_tagline' => Fields::field( 'toggle', __( 'Show tagline', 'brik-builder' ), 'content' ),
			'layout'       => Fields::field( 'select', __( 'Tagline position', 'brik-builder' ), 'content', array( 'default' => 'stacked', 'show_if' => array( 'show_tagline' => true ), 'options' => Fields::opts( array( 'stacked' => __( 'Below the name', 'brik-builder' ), 'inline' => __( 'Next to the name', 'brik-builder' ) ) ) ) ),
			'link'         => Fields::field( 'toggle', __( 'Link to the home page', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'        => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::typography( 'title', __( 'Site name', 'brik-builder' ), Fields::WRAP . ' .brik-site-name' ),
		Fields::typography( 'tagline', __( 'Tagline', 'brik-builder' ), Fields::WRAP . ' .brik-site-tagline' )
	),
	'render'      => static function ( $a ) {
		$tag  = in_array( $a['level'], array( 'h1', 'h2', 'p', 'div' ), true ) ? $a['level'] : 'p';
		$name = esc_html( get_bloginfo( 'name' ) );
		if ( ! empty( $a['link'] ) ) {
			$name = '<a href="' . esc_url( home_url( '/' ) ) . '" rel="home" class="transition-opacity hover:opacity-80">' . $name . '</a>';
		}
		$html    = sprintf( '<%1$s class="brik-site-name font-heading text-lg font-semibold tracking-tight">%2$s</%1$s>', $tag, $name );
		$tagline = get_bloginfo( 'description' );
		if ( ! empty( $a['show_tagline'] ) && '' !== $tagline ) {
			$html .= '<p class="brik-site-tagline text-sm text-muted-foreground">' . esc_html( $tagline ) . '</p>';
			$class = 'inline' === $a['layout'] ? 'flex flex-wrap items-baseline gap-x-3 gap-y-1' : 'flex flex-col gap-0.5';
			return '<div class="' . esc_attr( $class ) . '">' . $html . '</div>';
		}
		return $html;
	},
);
