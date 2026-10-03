<?php
/**
 * Effect card: content card with a pointer or ambient effect. One card, or a grid of them.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_effect_card_defaults = array(
	array(
		'icon'  => 'zap',
		'title' => __( 'Instant previews', 'brik-builder' ),
		'text'  => __( 'Every change renders on the server in milliseconds, so what you see is exactly what ships.', 'brik-builder' ),
		'link'  => array( 'url' => '#' ),
	),
	array(
		'icon'  => 'shield-check',
		'title' => __( 'Secure by default', 'brik-builder' ),
		'text'  => __( 'Escaped output, capability checks and nonces everywhere. Nothing to configure.', 'brik-builder' ),
		'link'  => array( 'url' => '#' ),
	),
	array(
		'icon'  => 'sparkles',
		'title' => __( 'Built to delight', 'brik-builder' ),
		'text'  => __( 'Motion that respects reduced-motion settings and pauses itself when nobody is looking.', 'brik-builder' ),
		'link'  => array( 'url' => '#' ),
	),
);

return array(
	'type'        => 'effect_card',
	'title'       => __( 'Effect Card', 'brik-builder' ),
	'category'    => 'effects',
	'icon'        => 'square-mouse-pointer',
	'description' => 'Content card with an interaction effect. effect: spotlight (glow follows cursor) | tilt (3D tilt + glare) | border_beam (light travels around the border) | shine (rotating gradient border) | glow (animated gradient glow behind) | magnetic (leans toward cursor) | direction_aware (overlay slides in from the hover side) | encrypted (random characters revealed under the cursor). layout: single|grid. Single uses icon, image, badge, title, text, link, link_text. Grid uses cards: repeater [{icon, image, badge, title, text, link, link_text}] and columns (1-4, responsive). color_1, color_2: effect colors. title_tag: h2|h3|h4|div. Touch devices and reduced motion get a static card.',
	'fields'      => array_merge(
		array(
			'effect'    => Fields::field( 'select', __( 'Effect', 'brik-builder' ), 'content', array( 'default' => 'spotlight', 'options' => Fields::opts( brik_fx_card_effects() ) ) ),
			'layout'    => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'single', 'options' => Fields::opts( array( 'single' => __( 'Single card', 'brik-builder' ), 'grid' => __( 'Grid of cards', 'brik-builder' ) ) ) ) ),
			'icon'      => Fields::field( 'icon', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'zap', 'show_if' => array( 'layout' => 'single' ) ) ),
			'image'     => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content', array( 'show_if' => array( 'layout' => 'single' ) ) ),
			'badge'     => Fields::field( 'text', __( 'Badge', 'brik-builder' ), 'content', array( 'inline' => true, 'show_if' => array( 'layout' => 'single' ) ) ),
			'title'     => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Instant previews', 'brik-builder' ), 'inline' => true, 'show_if' => array( 'layout' => 'single' ) ) ),
			'text'      => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => __( 'Every change renders on the server in milliseconds, so what you see is exactly what ships.', 'brik-builder' ), 'inline' => true, 'show_if' => array( 'layout' => 'single' ) ) ),
			'link'      => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content', array( 'show_if' => array( 'layout' => 'single' ) ) ),
			'link_text' => Fields::field( 'text', __( 'Link text', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Learn more', 'brik-builder' ), 'show_if' => array( 'layout' => 'single' ) ) ),
			'cards'     => Fields::field(
				'repeater',
				__( 'Cards', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'default'     => $brik_effect_card_defaults,
					'show_if'     => array( 'layout' => 'grid' ),
					'fields'      => array(
						'icon'      => Fields::field( 'icon', __( 'Icon', 'brik-builder' ) ),
						'image'     => Fields::field( 'image', __( 'Image', 'brik-builder' ) ),
						'badge'     => Fields::field( 'text', __( 'Badge', 'brik-builder' ) ),
						'title'     => Fields::field( 'text', __( 'Title', 'brik-builder' ) ),
						'text'      => Fields::field( 'textarea', __( 'Text', 'brik-builder' ) ),
						'link'      => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
						'link_text' => Fields::field( 'text', __( 'Link text', 'brik-builder' ) ),
					),
				)
			),
			'columns'   => Fields::field( 'range', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 4, 'step' => 1, 'responsive' => true, 'show_if' => array( 'layout' => 'grid' ) ) ),
			'gap'       => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'default' => '1.5rem', 'responsive' => true, 'show_if' => array( 'layout' => 'grid' ), 'css' => array( Fields::WRAP . ' .brik-fx-cards', 'gap' ) ) ),
			'title_tag' => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'div' => 'div' ) ) ) ),
			'color_1'   => Fields::field( 'color', __( 'Effect color 1', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#6366f1' ) ),
			'color_2'   => Fields::field( 'color', __( 'Effect color 2', 'brik-builder' ), 'fx_colors', array( 'tab' => 'design', 'group_label' => __( 'Effect colors', 'brik-builder' ), 'placeholder' => '#ec4899' ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-fx-card-box' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-fx-card-title' ),
		Fields::typography( 'body', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-fx-card-text' )
	),
	'css'         => static function ( $a, $wrap ) {
		return 'grid' === $a['layout'] ? brik_grid_css( $a, $wrap . ' .brik-fx-cards', 'columns', 3, 2, 1 ) : '';
	},
	'render'      => static function ( $a, $ctx ) {
		$effect = array_key_exists( $a['effect'], brik_fx_card_effects() ) ? $a['effect'] : 'spotlight';
		$tag    = brik_fx_tag( $a['title_tag'], array( 'h2', 'h3', 'h4', 'div' ), 'h3' );
		$style  = brik_fx_vars(
			array(
				'--brik-fx-c1' => $a['color_1'],
				'--brik-fx-c2' => $a['color_2'],
			)
		);

		if ( 'grid' === $a['layout'] ) {
			$cards = '';
			foreach ( brik_items( $a['cards'] ) as $card ) {
				$cards .= brik_fx_card( $card, $effect, $ctx, false, $tag );
			}
			if ( '' === $cards ) {
				return $ctx->placeholder( __( 'Add cards', 'brik-builder' ) );
			}
			$html = '<div class="brik-fx-cards grid gap-6">' . $cards . '</div>';
		} else {
			$html = brik_fx_card(
				array(
					'icon'      => $a['icon'],
					'image'     => $a['image'],
					'badge'     => $a['badge'],
					'title'     => $a['title'],
					'text'      => $a['text'],
					'link'      => $a['link'],
					'link_text' => $a['link_text'],
				),
				$effect,
				$ctx,
				true,
				$tag
			);
		}

		if ( in_array( $effect, array( 'spotlight', 'tilt', 'magnetic', 'direction_aware', 'encrypted' ), true ) ) {
			$ctx->script( 'effect-card' );
		}
		$pause = in_array( $effect, array( 'border_beam', 'shine', 'glow' ), true ) ? ' data-brik-fx-pause' : '';
		return '<div class="brik-fx-scope h-full"' . ( '' !== $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . $pause . '>' . $html . '</div>';
	},
);
