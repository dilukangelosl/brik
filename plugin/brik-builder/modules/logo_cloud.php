<?php
/**
 * Logo cloud: a grid of client or partner logos.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'logo_cloud',
	'title'       => __( 'Logo Cloud', 'brik-builder' ),
	'category'    => 'content',
	'icon'        => 'images',
	'description' => 'Grid of logos. title (optional heading above), logos repeater [{image, name (alt text / wordmark), brand (brand slug used when no image, e.g. stripe|figma|github), link}]. columns: 2-6 (responsive). style: plain|bordered (cells with dividers)|cards. gray_logos: toggle (colour on hover). logo_height: CSS length.',
	'fields'      => array_merge(
		array(
			'title'     => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Trusted by teams at', 'brik-builder' ), 'inline' => true ) ),
			'logos'     => Fields::field(
				'repeater',
				__( 'Logos', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'name',
					'fields'      => array(
						'image' => Fields::field( 'image', __( 'Logo image', 'brik-builder' ) ),
						'name'  => Fields::field( 'text', __( 'Name', 'brik-builder' ) ),
						'brand' => Fields::field( 'select', __( 'Brand icon', 'brik-builder' ), 'content', array( 'description' => __( 'Shown with the name when no image is set.', 'brik-builder' ), 'options' => Fields::opts( array_merge( array( '' => __( 'None', 'brik-builder' ) ), brik_brand_options() ) ) ) ),
						'link'  => Fields::field( 'link', __( 'Link', 'brik-builder' ) ),
					),
					'default'     => array(
						array( 'name' => 'Stripe', 'brand' => 'stripe' ),
						array( 'name' => 'Figma', 'brand' => 'figma' ),
						array( 'name' => 'GitHub', 'brand' => 'github' ),
						array( 'name' => 'Spotify', 'brand' => 'spotify' ),
						array( 'name' => 'Discord', 'brand' => 'discord' ),
						array( 'name' => 'Twitch', 'brand' => 'twitch' ),
					),
				)
			),
			'columns'   => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 6, 'min' => 2, 'max' => 6, 'responsive' => true ) ),
			'style'     => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'plain', 'options' => Fields::opts( array( 'plain' => __( 'Plain', 'brik-builder' ), 'bordered' => __( 'Bordered grid', 'brik-builder' ), 'cards' => __( 'Cards', 'brik-builder' ) ) ) ) ),
			'gray_logos' => Fields::field( 'toggle', __( 'Grayscale until hover', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'logo_height' => Fields::field( 'unit', __( 'Logo height', 'brik-builder' ), 'logo', array( 'tab' => 'design', 'group_label' => __( 'Logos', 'brik-builder' ), 'responsive' => true, 'placeholder' => '32px', 'css' => array( Fields::WRAP . ' .brik-logo-cloud', '--brik-logo-h' ) ) ),
			'logo_opacity' => Fields::field( 'range', __( 'Opacity', 'brik-builder' ), 'logo', array( 'tab' => 'design', 'group_label' => __( 'Logos', 'brik-builder' ), 'min' => 0, 'max' => 1, 'step' => 0.05, 'hover' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-logo-item', 'prop' => 'opacity', 'hover_selector' => Fields::WRAP . ' .brik-logo-item:hover' ) ) ),
			'gap'       => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'logo', array( 'tab' => 'design', 'group_label' => __( 'Logos', 'brik-builder' ), 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-logo-cloud', 'gap' ) ) ),
		),
		Fields::box( 'cell', __( 'Logo cell', 'brik-builder' ), Fields::WRAP . ' .brik-logo-cell' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-logo-title' ),
		Fields::typography( 'wordmark', __( 'Wordmark', 'brik-builder' ), Fields::WRAP . ' .brik-logo-name' )
	),
	'css'         => static function ( $a, $wrap ) {
		return brik_grid_css( $a, $wrap . ' .brik-logo-cloud', 'columns', 6, 3, 2 );
	},
	'render'      => static function ( $a, $ctx ) {
		$logos = brik_items( $a['logos'] );
		if ( ! $logos ) {
			return $ctx->placeholder( __( 'Add logos', 'brik-builder' ) );
		}
		$style = in_array( $a['style'], array( 'plain', 'bordered', 'cards' ), true ) ? $a['style'] : 'plain';
		$gray  = ! empty( $a['gray_logos'] );

		$cells = '';
		foreach ( $logos as $logo ) {
			$name = brik_item( $logo, 'name' );
			if ( brik_has_image( brik_item( $logo, 'image' ) ) ) {
				$inner = brik_image( $logo['image'], 'medium', array( 'class' => 'h-[var(--brik-logo-h,2rem)] w-auto max-w-full object-contain', 'alt' => wp_strip_all_tags( (string) $name ) ) );
			} else {
				$brand = brik_item( $logo, 'brand' );
				$icon  = '' !== $brand ? brik_icon( brik_brand( $brand )['icon'], 'size-[calc(var(--brik-logo-h,2rem)*0.8)] shrink-0' ) : '';
				$inner = '<span class="inline-flex items-center gap-2">' . $icon . ( '' !== $name ? '<span class="brik-logo-name font-heading text-[calc(var(--brik-logo-h,2rem)*0.6)] font-semibold tracking-tight whitespace-nowrap">' . brik_inline( $name ) . '</span>' : '' ) . '</span>';
			}
			$item = '<span class="' . esc_attr( brik_cls( 'brik-logo-item inline-flex items-center justify-center transition-[filter,opacity,color] duration-300', $gray ? 'text-muted-foreground opacity-70 grayscale hover:text-foreground hover:opacity-100 hover:grayscale-0' : 'text-foreground' ) ) . '">' . $inner . '</span>';
			if ( brik_has_link( brik_item( $logo, 'link' ) ) ) {
				$item = '<a' . brik_link_attrs( $logo['link'], array( 'class' => 'inline-flex rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50', 'aria-label' => wp_strip_all_tags( (string) $name ) ) ) . '>' . $item . '</a>';
			}
			$cell_class = array(
				'plain'    => 'py-2',
				'bordered' => 'bg-background px-6 py-8',
				'cards'    => 'rounded-xl border bg-card px-6 py-6 shadow-xs',
			);
			$cells     .= '<div class="' . esc_attr( brik_cls( 'brik-logo-cell flex min-w-0 items-center justify-center', $cell_class[ $style ] ) ) . '">' . $item . '</div>';
		}

		$grid = array(
			'plain'    => 'gap-x-8 gap-y-6',
			'bordered' => 'gap-px overflow-hidden rounded-xl border bg-border',
			'cards'    => 'gap-4',
		);
		$title = '';
		if ( '' !== trim( (string) $a['title'] ) ) {
			$title = '<p class="brik-logo-title mb-8 text-center text-sm font-medium text-muted-foreground"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</p>';
		}
		return $title . '<div class="' . esc_attr( brik_cls( 'brik-logo-cloud grid items-center', $grid[ $style ] ) ) . '">' . $cells . '</div>';
	},
);
