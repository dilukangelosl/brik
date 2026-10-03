<?php
/**
 * Shop archive header: title, description, category image, breadcrumbs and subcategory links
 * of the shop page, a product category or tag, or a product search.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'shop_archive_header',
	'title'       => __( 'Shop header', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'store',
	'description' => 'Header for shop and product category templates: archive title, description, category image, product count, breadcrumbs and subcategory pills. style: simple|banner (image behind the text)|split (image beside the text). shop_title / shop_description: used on the main shop page (and in the builder). show_breadcrumbs, show_description, show_image, show_count, show_subcategories, align left|center.',
	'fields'      => array_merge(
		array(
			'style'              => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'simple', 'options' => Fields::opts( array( 'simple' => __( 'Simple', 'brik-builder' ), 'banner' => __( 'Banner (image behind)', 'brik-builder' ), 'split' => __( 'Split (image beside)', 'brik-builder' ) ) ) ) ),
			'shop_title'         => Fields::field( 'text', __( 'Shop page title', 'brik-builder' ), 'content', array( 'default' => __( 'Shop', 'brik-builder' ) ) ),
			'shop_description'   => Fields::field( 'textarea', __( 'Shop page description', 'brik-builder' ), 'content', array( 'default' => __( 'Thoughtfully made essentials, picked for everyday use.', 'brik-builder' ) ) ),
			'shop_image'         => Fields::field( 'image', __( 'Shop page image', 'brik-builder' ), 'content', array( 'show_if' => array( 'style' => array( 'banner', 'split' ) ) ) ),
			'show_breadcrumbs'   => Fields::field( 'toggle', __( 'Breadcrumbs', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_description'   => Fields::field( 'toggle', __( 'Description', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_image'         => Fields::field( 'toggle', __( 'Category image', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_count'         => Fields::field( 'toggle', __( 'Product count', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_subcategories' => Fields::field( 'toggle', __( 'Subcategory links', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'              => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ) ) ),
		),
		Fields::typography( 'heading', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-sah-title' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-sah-desc' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$term   = null;
		$title  = '' !== trim( (string) $a['shop_title'] ) ? $a['shop_title'] : __( 'Shop', 'brik-builder' );
		$desc   = esc_html( (string) $a['shop_description'] );
		$image  = brik_image_url( $a['shop_image'], 'full' );
		$count  = null;
		$parent = 0;
		$canvas = $ctx->canvas;

		if ( ! $canvas && function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$term = get_queried_object();
		} elseif ( $canvas && brik_site_is_layout( $ctx->post_id ) ) {
			// Templates preview with a real category, when there is one.
			$terms = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'hide_empty' => true,
					'number'     => 1,
					'parent'     => 0,
					'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
				)
			);
			$term  = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
		}

		if ( $term instanceof WP_Term ) {
			$title  = $term->name;
			$desc   = wp_kses_post( wpautop( term_description( $term ) ) );
			$thumb  = 'product_cat' === $term->taxonomy ? (int) get_term_meta( $term->term_id, 'thumbnail_id', true ) : 0;
			$image  = $thumb ? (string) wp_get_attachment_image_url( $thumb, 'full' ) : $image;
			$count  = (int) $term->count;
			$parent = (int) $term->term_id;
		} elseif ( ! $canvas && is_search() ) {
			/* translators: %s: search query */
			$title = sprintf( __( 'Results for “%s”', 'brik-builder' ), get_search_query() );
			$desc  = '';
		}
		// The archive's own total respects catalog visibility, unlike the term count.
		if ( ! $canvas && brik_woo_is_product_archive() ) {
			global $wp_query;
			$count = (int) $wp_query->found_posts;
		} elseif ( null === $count ) {
			$count = (int) wp_count_posts( 'product' )->publish;
		}

		$center = 'center' === $a['align'];
		$style  = in_array( $a['style'], array( 'simple', 'banner', 'split' ), true ) ? $a['style'] : 'simple';
		$image  = brik_form_bool( $a['show_image'] ) ? $image : '';
		$banner = 'banner' === $style && '' !== $image;

		$crumbs = '';
		if ( brik_form_bool( $a['show_breadcrumbs'] ) ) {
			$items = array( array( __( 'Home', 'brik-builder' ), home_url( '/' ) ) );
			$shop  = wc_get_page_id( 'shop' );
			if ( $shop > 0 ) {
				$items[] = array( get_the_title( $shop ), get_permalink( $shop ) );
			}
			if ( $term instanceof WP_Term ) {
				foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $anc ) {
					$t = get_term( $anc, $term->taxonomy );
					if ( $t instanceof WP_Term ) {
						$items[] = array( $t->name, get_term_link( $t ) );
					}
				}
				$items[] = array( $term->name, '' );
			} else {
				$items[ count( $items ) - 1 ][1] = '';
			}
			$li = '';
			foreach ( $items as $i => $item ) {
				$li .= ( $i ? '<li aria-hidden="true">' . brik_icon( 'chevron-right', 'size-3.5 opacity-60' ) . '</li>' : '' )
					. '<li>' . ( '' !== $item[1] && ! is_wp_error( $item[1] ) ? '<a class="transition-colors hover:text-foreground' . ( $banner ? ' hover:text-white' : '' ) . '" href="' . esc_url( $item[1] ) . '">' . esc_html( $item[0] ) . '</a>' : '<span aria-current="page" class="' . ( $banner ? 'text-white' : 'text-foreground' ) . '">' . esc_html( $item[0] ) . '</span>' ) . '</li>';
			}
			$crumbs = '<nav aria-label="' . esc_attr__( 'Breadcrumb', 'brik-builder' ) . '"><ol class="' . esc_attr( brik_cls( 'flex flex-wrap items-center gap-1.5 text-sm', $banner ? 'text-white/70' : 'text-muted-foreground', array( 'justify-center' => $center ) ) ) . '">' . $li . '</ol></nav>';
		}

		$subs = '';
		if ( brik_form_bool( $a['show_subcategories'] ) ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => $term instanceof WP_Term && 'product_cat' === $term->taxonomy ? $parent : 0,
					'hide_empty' => true,
					'number'     => 12,
					'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
				)
			);
			if ( $children && ! is_wp_error( $children ) && ( $term instanceof WP_Term || $canvas || ( function_exists( 'is_shop' ) && is_shop() ) || ! brik_woo_is_product_archive() ) ) {
				$pills = '';
				foreach ( $children as $child ) {
					$pills .= '<a class="' . esc_attr( $banner ? 'inline-flex h-8 items-center rounded-full border border-white/25 bg-white/10 px-3.5 text-sm font-medium text-white backdrop-blur transition-colors hover:bg-white/20' : 'inline-flex h-8 items-center rounded-full border bg-background px-3.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground dark:bg-input/30' ) . '" href="' . esc_url( get_term_link( $child ) ) . '">' . esc_html( $child->name ) . '</a>';
				}
				$subs = '<div class="' . esc_attr( brik_cls( 'brik-sah-subs flex flex-wrap gap-2 pt-2', array( 'justify-center' => $center ) ) ) . '">' . $pills . '</div>';
			}
		}

		$meta = '';
		if ( brik_form_bool( $a['show_count'] ) && null !== $count ) {
			/* translators: %s: number of products */
			$meta = '<p class="' . esc_attr( $banner ? 'text-sm text-white/70' : 'text-sm text-muted-foreground' ) . '">' . esc_html( sprintf( _n( '%s product', '%s products', $count, 'brik-builder' ), number_format_i18n( $count ) ) ) . '</p>';
		}

		$text = '<div class="' . esc_attr( brik_cls( 'brik-sah-text grid gap-3', array( 'justify-items-center text-center' => $center ) ) ) . '">'
			. $crumbs
			. '<h1 class="' . esc_attr( brik_cls( 'brik-sah-title font-heading text-4xl font-semibold tracking-tight text-balance md:text-5xl', $banner ? 'text-white' : '' ) ) . '"' . ( $term ? '' : $ctx->inline( 'shop_title' ) ) . '>' . esc_html( wp_strip_all_tags( $title ) ) . '</h1>'
			. $meta
			. ( brik_form_bool( $a['show_description'] ) && '' !== trim( wp_strip_all_tags( $desc ) ) ? '<div class="' . esc_attr( brik_cls( 'brik-sah-desc max-w-2xl text-base leading-relaxed text-pretty [&_p:not(:last-child)]:mb-3', $banner ? 'text-white/85' : 'text-muted-foreground' ) ) . '">' . ( false === strpos( $desc, '<p' ) ? '<p>' . $desc . '</p>' : $desc ) . '</div>' : '' )
			. $subs
			. '</div>';

		if ( $banner ) {
			return '<div class="brik-sah brik-sah--banner relative isolate flex min-h-72 items-end overflow-hidden rounded-2xl bg-muted p-8 md:min-h-96 md:p-12">'
				. '<img class="absolute inset-0 -z-10 size-full object-cover" src="' . esc_url( $image ) . '" alt="" loading="eager">'
				. '<div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/75 via-black/35 to-black/10" aria-hidden="true"></div>'
				. $text . '</div>';
		}
		if ( 'split' === $style && '' !== $image ) {
			return '<div class="brik-sah brik-sah--split grid items-center gap-8 md:grid-cols-2 md:gap-12">' . $text
				. '<div class="aspect-[4/3] overflow-hidden rounded-2xl bg-muted"><img class="size-full object-cover" src="' . esc_url( $image ) . '" alt="" loading="eager"></div></div>';
		}
		return '<div class="brik-sah brik-sah--simple">' . $text . '</div>';
	},
);
