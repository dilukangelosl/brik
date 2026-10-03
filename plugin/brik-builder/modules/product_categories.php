<?php
/**
 * Product categories: category cards with images and product counts, as a grid or carousel.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'product_categories',
	'title'       => __( 'Product categories', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'layout-grid',
	'description' => 'WooCommerce product categories with image, name and product count. source: top (top-level)|all|ids (ids: comma separated ids or slugs)|children (subcategories of the current category). orderby: menu_order|name|count. limit, hide_empty. style: card|overlay|circle|minimal. layout: grid|carousel, columns (responsive), card_ratio, show_count, show_arrow.',
	'fields'      => array_merge(
		array(
			'source'     => Fields::field( 'select', __( 'Categories', 'brik-builder' ), 'query', array( 'default' => 'top', 'group_label' => __( 'Query', 'brik-builder' ), 'options' => Fields::opts( array( 'top' => __( 'Top-level', 'brik-builder' ), 'all' => __( 'All', 'brik-builder' ), 'ids' => __( 'Specific categories', 'brik-builder' ), 'children' => __( 'Subcategories of the current category', 'brik-builder' ) ) ) ) ),
			'ids'        => Fields::field( 'text', __( 'Category IDs or slugs', 'brik-builder' ), 'query', array( 'placeholder' => 'hoodies, accessories', 'group_label' => __( 'Query', 'brik-builder' ), 'show_if' => array( 'source' => 'ids' ) ) ),
			'orderby'    => Fields::field( 'select', __( 'Order by', 'brik-builder' ), 'query', array( 'default' => 'menu_order', 'group_label' => __( 'Query', 'brik-builder' ), 'options' => Fields::opts( array( 'menu_order' => __( 'Category order', 'brik-builder' ), 'name' => __( 'Name', 'brik-builder' ), 'count' => __( 'Product count', 'brik-builder' ) ) ) ) ),
			'limit'      => Fields::field( 'number', __( 'Limit', 'brik-builder' ), 'query', array( 'default' => 8, 'min' => 1, 'max' => 50, 'group_label' => __( 'Query', 'brik-builder' ) ) ),
			'hide_empty' => Fields::field( 'toggle', __( 'Hide empty categories', 'brik-builder' ), 'query', array( 'default' => true, 'group_label' => __( 'Query', 'brik-builder' ) ) ),
			'style'      => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'overlay', 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'overlay' => __( 'Overlay (name over image)', 'brik-builder' ), 'circle' => __( 'Circle', 'brik-builder' ), 'minimal' => __( 'Minimal', 'brik-builder' ) ) ) ) ),
			'layout'     => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'grid', 'options' => Fields::opts( array( 'grid' => __( 'Grid', 'brik-builder' ), 'carousel' => __( 'Carousel', 'brik-builder' ) ) ) ) ),
			'columns'    => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 4, 'min' => 1, 'max' => 8, 'responsive' => true ) ),
			'gap'        => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'placeholder' => '16px', 'css' => array( Fields::WRAP . ' .brik-listing-items', '--brik-listing-gap' ) ) ),
			'card_ratio' => Fields::field( 'select', __( 'Image ratio', 'brik-builder' ), 'content', array( 'default' => '4:5', 'options' => Fields::opts( array( '1:1' => '1:1', '4:5' => '4:5', '3:4' => '3:4', '4:3' => '4:3', '16:9' => '16:9' ) ), 'show_if' => array( 'style' => array( 'card', 'overlay', 'minimal' ) ) ) ),
			'show_count' => Fields::field( 'toggle', __( 'Product count', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_arrow' => Fields::field( 'toggle', __( 'Arrow', 'brik-builder' ), 'content', array( 'default' => true ) ),
		),
		Fields::typography( 'name', __( 'Names', 'brik-builder' ), Fields::WRAP . ' .brik-cat-name' )
	),
	'css'         => static function ( $a, $wrap ) {
		$out   = '';
		$rules = array(
			'desktop' => '%s',
			'tablet'  => '@media (max-width:' . Brik\Style::TABLET . 'px){%s}',
			'mobile'  => '@media (max-width:' . Brik\Style::MOBILE . 'px){%s}',
		);
		$vars  = array(
			'desktop' => '--brik-listing-cols',
			'tablet'  => '--brik-listing-cols-t',
			'mobile'  => '--brik-listing-cols-m',
		);
		$desk  = max( 1, (int) Brik\Style::raw_value( $a, 'columns', 'desktop' ) );
		$fall  = array(
			'desktop' => $desk,
			'tablet'  => min( $desk, 3 ),
			'mobile'  => 'circle' === $a['style'] ? min( $desk, 3 ) : 2,
		);
		foreach ( $rules as $state => $rule ) {
			$v    = (int) Brik\Style::raw_value( $a, 'columns', $state );
			$v    = $v > 0 ? $v : $fall[ $state ];
			$out .= sprintf( $rule, $wrap . '{' . $vars[ $state ] . ':' . min( 8, $v ) . '}' );
		}
		return $out;
	},
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => brik_form_bool( $a['hide_empty'] ),
			'number'     => min( 50, max( 1, (int) $a['limit'] ) ),
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		);
		switch ( $a['orderby'] ) {
			case 'name':
				$args['orderby'] = 'name';
				break;
			case 'count':
				$args['orderby'] = 'count';
				$args['order']   = 'DESC';
				break;
			default:
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'order'; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		switch ( $a['source'] ) {
			case 'top':
				$args['parent'] = 0;
				break;
			case 'ids':
				$ids             = brik_woo_term_ids( 'product_cat', $a['ids'] );
				$args['include'] = $ids ? $ids : array( 0 );
				$args['orderby'] = 'include';
				unset( $args['meta_key'] );
				break;
			case 'children':
				$current        = ! $ctx->canvas && is_tax( 'product_cat' ) ? get_queried_object_id() : 0;
				$args['parent'] = $current;
				break;
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) || ! $terms ) {
			// Ordering by the "order" meta drops categories that never got one.
			if ( isset( $args['meta_key'] ) ) {
				unset( $args['meta_key'] );
				$args['orderby'] = 'name';
				$terms           = get_terms( $args );
			}
			if ( is_wp_error( $terms ) || ! $terms ) {
				return $ctx->placeholder( __( 'No product categories to show.', 'brik-builder' ) );
			}
		}

		$style  = in_array( $a['style'], array( 'card', 'overlay', 'circle', 'minimal' ), true ) ? $a['style'] : 'overlay';
		$ratio  = brik_aspect_class( (string) $a['card_ratio'] );
		$ratio  = $ratio ? $ratio : 'aspect-[4/5]';
		$layout = 'carousel' === $a['layout'] ? 'carousel' : 'grid';
		$items  = '';
		foreach ( $terms as $i => $term ) {
			$thumb = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
			if ( ! $thumb ) {
				// Fall back to the image of a product in the category.
				$ids   = get_posts(
					array(
						'post_type'      => 'product',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery
						'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'terms' => array( $term->term_id ) ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
					)
				);
				$thumb = $ids ? (int) get_post_thumbnail_id( $ids[0] ) : 0;
			}
			$img   = $thumb ? wp_get_attachment_image( $thumb, 'circle' === $style ? 'woocommerce_thumbnail' : 'medium_large', false, array( 'class' => 'absolute inset-0 size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105', 'alt' => '', 'loading' => $i > 3 ? 'lazy' : 'eager' ) ) : '<span class="absolute inset-0 grid place-items-center text-muted-foreground/40">' . brik_icon( 'image', 'size-8' ) . '</span>';
			$url   = get_term_link( $term );
			$name  = esc_html( $term->name );
			/* translators: %s: number of products */
			$count = brik_form_bool( $a['show_count'] ) ? sprintf( _n( '%s product', '%s products', $term->count, 'brik-builder' ), number_format_i18n( $term->count ) ) : '';
			$arrow = brik_form_bool( $a['show_arrow'] );
			$link  = '<a class="brik-cat-link outline-none after:absolute after:inset-0 after:z-[1] after:rounded-[inherit] focus-visible:after:ring-[3px] focus-visible:after:ring-ring/50" href="' . esc_url( $url ) . '">' . $name . '</a>';

			switch ( $style ) {
				case 'circle':
					$card = '<div class="brik-cat brik-cat--circle group relative flex flex-col items-center gap-3 text-center">'
						. '<div class="relative aspect-square w-full overflow-hidden rounded-full bg-muted ring-1 ring-border transition-shadow group-hover:ring-2 group-hover:ring-foreground/20">' . $img . '</div>'
						. '<div class="grid gap-0.5"><h3 class="brik-cat-name text-sm font-medium">' . $link . '</h3>' . ( $count ? '<p class="text-xs text-muted-foreground">' . esc_html( $count ) . '</p>' : '' ) . '</div></div>';
					break;
				case 'card':
					$card = '<div class="brik-cat brik-cat--card group relative flex h-full flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-shadow hover:shadow-md">'
						. '<div class="' . esc_attr( 'relative overflow-hidden bg-muted ' . $ratio ) . '">' . $img . '</div>'
						. '<div class="flex items-center justify-between gap-3 p-4"><div class="grid gap-0.5"><h3 class="brik-cat-name font-medium">' . $link . '</h3>' . ( $count ? '<p class="text-xs text-muted-foreground">' . esc_html( $count ) . '</p>' : '' ) . '</div>'
						. ( $arrow ? brik_icon( 'arrow-right', 'size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5' ) : '' ) . '</div></div>';
					break;
				case 'minimal':
					$card = '<div class="brik-cat brik-cat--minimal group relative flex flex-col gap-3">'
						. '<div class="' . esc_attr( 'relative overflow-hidden rounded-xl bg-muted ' . $ratio ) . '">' . $img . '</div>'
						. '<div class="flex items-baseline justify-between gap-2"><h3 class="brik-cat-name font-medium">' . $link . '</h3>' . ( $count ? '<span class="text-xs text-muted-foreground tabular-nums">' . esc_html( number_format_i18n( $term->count ) ) . '</span>' : '' ) . '</div></div>';
					break;
				default:
					$card = '<div class="' . esc_attr( 'brik-cat brik-cat--overlay group relative isolate flex overflow-hidden rounded-xl bg-muted text-white shadow-xs ' . $ratio ) . '">'
						. '<div class="absolute inset-0 -z-10">' . $img . '</div>'
						. '<div class="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/10 to-transparent transition-opacity group-hover:opacity-90" aria-hidden="true"></div>'
						. '<div class="mt-auto flex w-full items-end justify-between gap-3 p-4 md:p-5"><div class="grid gap-0.5"><h3 class="brik-cat-name text-lg font-semibold tracking-tight">' . $link . '</h3>' . ( $count ? '<p class="text-xs text-white/75">' . esc_html( $count ) . '</p>' : '' ) . '</div>'
						. ( $arrow ? '<span class="grid size-9 shrink-0 place-items-center rounded-full bg-white/15 backdrop-blur transition-colors group-hover:bg-white group-hover:text-black">' . brik_icon( 'arrow-up-right', 'size-4' ) . '</span>' : '' ) . '</div></div>';
			}
			$items .= '<div class="brik-listing-item">' . $card . '</div>';
		}

		$out = '<div class="' . esc_attr( 'brik-listing-items brik-listing-items--' . $layout . ' brik-cat-items' ) . '"' . ( 'carousel' === $layout ? ' tabindex="0" role="region" aria-label="' . esc_attr__( 'Product categories', 'brik-builder' ) . '" data-brik-scroller' : '' ) . '>' . $items . '</div>';
		if ( 'carousel' === $layout ) {
			$btn = brik_button_class( 'outline', 'icon', 'pointer-events-auto size-10 rounded-full bg-background/95 shadow-md backdrop-blur transition-opacity' );
			$out = '<div class="relative">' . $out
				. '<div class="brik-products-arrows pointer-events-none absolute inset-x-0 top-1/2 z-20 hidden -translate-y-1/2 justify-between sm:flex">'
				. '<button type="button" class="' . esc_attr( $btn . ' -ml-5' ) . '" data-brik-products-prev aria-label="' . esc_attr__( 'Previous', 'brik-builder' ) . '">' . brik_icon( 'chevron-left' ) . '</button>'
				. '<button type="button" class="' . esc_attr( $btn . ' -mr-5' ) . '" data-brik-products-next aria-label="' . esc_attr__( 'Next', 'brik-builder' ) . '">' . brik_icon( 'chevron-right' ) . '</button>'
				. '</div></div>';
			$ctx->attrs['data-brik-carousel-scroller'] = '';
		}
		return $out;
	},
);
