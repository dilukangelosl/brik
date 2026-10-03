<?php
/**
 * Site logo: the custom logo (or a chosen image) linking home, with the site name as fallback.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'site_logo',
	'title'       => __( 'Site logo', 'brik-builder' ),
	'category'    => 'site',
	'icon'        => 'image',
	'description' => 'Site logo linking to the home page. Uses "image" when set, else the Customizer logo, else the site name as text. image_dark: optional logo for dark sections. max_height: CSS length (responsive). link_to: home|custom|none.',
	'fields'      => array_merge(
		array(
			'image'      => Fields::field( 'image', __( 'Logo', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty to use the logo from Appearance → Customize.', 'brik-builder' ) ) ),
			'image_dark' => Fields::field( 'image', __( 'Logo on dark backgrounds', 'brik-builder' ), 'content' ),
			'max_height' => Fields::field( 'unit', __( 'Max height', 'brik-builder' ), 'content', array( 'default' => '36px', 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-logo-img', 'max-height' ) ) ),
			'link_to'    => Fields::field( 'select', __( 'Link', 'brik-builder' ), 'content', array( 'default' => 'home', 'options' => Fields::opts( array( 'home' => __( 'Home page', 'brik-builder' ), 'custom' => __( 'Custom link', 'brik-builder' ), 'none' => __( 'No link', 'brik-builder' ) ) ) ) ),
			'link'       => Fields::field( 'link', __( 'Custom link', 'brik-builder' ), 'content', array( 'show_if' => array( 'link_to' => 'custom' ) ) ),
			'alt'        => Fields::field( 'text', __( 'Alt text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Site name', 'brik-builder' ) ) ),
			'align'      => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP, 'map' => array( 'left' => 'justify-content:flex-start', 'center' => 'justify-content:center', 'right' => 'justify-content:flex-end' ) ) ) ),
		),
		Fields::typography( 'name', __( 'Site name (no logo)', 'brik-builder' ), Fields::WRAP . ' .brik-logo-text' )
	),
	'class'       => 'flex items-center',
	'render'      => static function ( $a ) {
		$name = get_bloginfo( 'name' );
		$alt  = '' !== trim( (string) $a['alt'] ) ? wp_strip_all_tags( $a['alt'] ) : $name;

		$image = brik_site_has_image( $a['image'] ) ? $a['image'] : null;
		if ( ! $image && get_theme_mod( 'custom_logo' ) ) {
			$image = array( 'id' => (int) get_theme_mod( 'custom_logo' ) );
		}

		$attrs = array(
			'alt'           => $alt,
			'loading'       => false,
			'fetchpriority' => 'high',
		);
		if ( $image ) {
			$dark = brik_site_has_image( $a['image_dark'] ) ? brik_image( $a['image_dark'], 'full', array_merge( $attrs, array( 'class' => 'brik-logo-img brik-logo-dark h-auto w-auto max-w-full' ) ) ) : '';
			$body = brik_image( $image, 'full', array_merge( $attrs, array( 'class' => brik_cls( 'brik-logo-img h-auto w-auto max-w-full', array( 'brik-logo-light' => '' !== $dark ) ) ) ) ) . $dark;
		} else {
			$body = '<span class="brik-logo-text font-heading text-lg font-semibold tracking-tight whitespace-nowrap">' . esc_html( $name ) . '</span>';
		}

		if ( 'none' === $a['link_to'] ) {
			return '<span class="brik-logo inline-flex items-center">' . $body . '</span>';
		}
		$link  = 'custom' === $a['link_to'] && ! empty( $a['link']['url'] ) ? $a['link'] : array( 'url' => home_url( '/' ) );
		$extra = array( 'class' => 'brik-logo inline-flex items-center rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50' );
		if ( 'home' === $a['link_to'] ) {
			$extra['rel'] = 'home';
			if ( is_front_page() ) {
				$extra['aria-current'] = 'page';
			}
		}
		return '<a' . brik_link_attrs( $link, $extra ) . '>' . $body . '</a>';
	},
);
