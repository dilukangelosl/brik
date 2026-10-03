<?php
/**
 * WooCommerce shop helpers: the product card, product queries and filters, the mini cart
 * and its fragments, cart/checkout/account rendering and the endpoints behind them.
 *
 * Helpers load before WooCommerce, so nothing here touches WooCommerce at load time: every
 * hook checks brik_woo_shop_enabled() first, and the shop modules don't register without it.
 *
 * @package Brik
 */

use Brik\Data;
use Brik\Forms;
use Brik\Modules;
use Brik\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the shop modules and helpers should run.
 */
function brik_woo_shop_enabled() {
	return (bool) apply_filters( 'brik/woo_shop_enabled', class_exists( 'WooCommerce' ) && function_exists( 'WC' ) );
}

/**
 * Make sure the session and cart exist. REST requests (builder previews, the quick view)
 * don't load them by default.
 */
function brik_woo_ensure_cart() {
	if ( ! brik_woo_shop_enabled() ) {
		return false;
	}
	if ( null === WC()->cart && function_exists( 'wc_load_cart' ) && did_action( 'woocommerce_init' ) ) {
		wc_load_cart();
	}
	return null !== WC()->cart;
}

/**
 * Remembers which WooCommerce screens the current request rendered, so is_cart(),
 * is_checkout() and the asset loading can follow Brik pages and templates.
 */
function brik_woo_flag( $name = null ) {
	static $flags = array();
	if ( null !== $name ) {
		$flags[ $name ] = true;
	}
	return $flags;
}

/**
 * Cart, checkout and account modules render the real WooCommerce screens only for a live
 * page view. When the tree is saved (the static copy in post_content) they leave the
 * shortcode instead, which keeps the page working without Brik and lets WooCommerce
 * recognise the page.
 */
function brik_woo_is_static_render( Brik\Context $ctx ) {
	return ! $ctx->canvas && ! did_action( 'template_redirect' );
}

/**
 * Product a single product template or page shows. Uses the product context helper when
 * it is loaded; falls back to the queried product.
 */
function brik_woo_context_product( $ctx = null ) {
	if ( $ctx && function_exists( 'brik_woo_product' ) ) {
		$product = brik_woo_product( $ctx );
		if ( $product instanceof WC_Product ) {
			return $product;
		}
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_queried_object_id() );
		return $product instanceof WC_Product ? $product : null;
	}
	if ( $ctx && $ctx->canvas ) {
		$ids = wc_get_products(
			array(
				'limit'  => 1,
				'status' => 'publish',
				'return' => 'ids',
			)
		);
		return $ids ? wc_get_product( $ids[0] ) : null;
	}
	return null;
}

/* -------------------------------------------------------------------------
 * Product card.
 * ----------------------------------------------------------------------- */

function brik_woo_card_styles() {
	return array(
		'card'       => __( 'Card', 'brik-builder' ),
		'minimal'    => __( 'Minimal', 'brik-builder' ),
		'overlay'    => __( 'Overlay (info over image)', 'brik-builder' ),
		'horizontal' => __( 'Horizontal', 'brik-builder' ),
	);
}

function brik_woo_card_defaults() {
	return array(
		'style'        => 'card',
		'ratio'        => '1:1',
		'image_size'   => 'woocommerce_thumbnail',
		'hover_image'  => true,
		'category'     => true,
		'rating'       => true,
		'badges'       => true,
		'new_days'     => 30,
		'button'       => true,
		'button_style' => 'full',
		'quick_view'   => false,
		'excerpt'      => false,
		'title_tag'    => 'h3',
		'index'        => 0,
	);
}

/**
 * Card arguments from module attributes that use the card_* naming.
 */
function brik_woo_card_args( array $a ) {
	$get = static function ( $key, $default ) use ( $a ) {
		return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $default;
	};
	$d = brik_woo_card_defaults();
	return array(
		'style'        => (string) $get( 'card_style', $d['style'] ),
		'ratio'        => (string) $get( 'card_ratio', $d['ratio'] ),
		'hover_image'  => brik_form_bool( $get( 'card_hover_image', $d['hover_image'] ) ),
		'category'     => brik_form_bool( $get( 'card_category', $d['category'] ) ),
		'rating'       => brik_form_bool( $get( 'card_rating', $d['rating'] ) ),
		'badges'       => brik_form_bool( $get( 'card_badges', $d['badges'] ) ),
		'new_days'     => (int) $get( 'card_new_days', $d['new_days'] ),
		'button'       => brik_form_bool( $get( 'card_button', $d['button'] ) ),
		'button_style' => (string) $get( 'card_button_style', $d['button_style'] ),
		'quick_view'   => brik_form_bool( $get( 'quick_view', $d['quick_view'] ) ),
		'excerpt'      => brik_form_bool( $get( 'card_excerpt', $d['excerpt'] ) ),
		'title_tag'    => (string) $get( 'title_tag', $d['title_tag'] ),
	);
}

/**
 * Sale discount in whole percent (the largest one for variable products), or 0.
 */
function brik_woo_sale_percent( WC_Product $product ) {
	if ( ! $product->is_on_sale() ) {
		return 0;
	}
	$best = 0;
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices();
		foreach ( $prices['regular_price'] as $id => $regular ) {
			$sale = isset( $prices['sale_price'][ $id ] ) ? (float) $prices['sale_price'][ $id ] : 0;
			if ( (float) $regular > 0 && $sale > 0 && $sale < (float) $regular ) {
				$best = max( $best, ( (float) $regular - $sale ) / (float) $regular );
			}
		}
	} else {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
			$best = ( $regular - $sale ) / $regular;
		}
	}
	return (int) round( $best * 100 );
}

/**
 * Sale / new / out-of-stock badges.
 */
function brik_woo_badges( WC_Product $product, $new_days = 30, $extra = '' ) {
	$out = '';
	if ( ! $product->is_in_stock() ) {
		$out .= '<span class="' . esc_attr( brik_badge_class( 'secondary', 'default', 'brik-pc-badge brik-pc-badge--stock bg-background/90 text-foreground shadow-xs backdrop-blur' ) ) . '">' . esc_html__( 'Sold out', 'brik-builder' ) . '</span>';
	} elseif ( $product->is_on_sale() ) {
		$pct  = brik_woo_sale_percent( $product );
		/* translators: %d: discount percentage */
		$text = $pct > 0 ? sprintf( __( '−%d%%', 'brik-builder' ), $pct ) : __( 'Sale', 'brik-builder' );
		$out .= '<span class="' . esc_attr( brik_badge_class( 'destructive', 'default', 'brik-pc-badge brik-pc-badge--sale tabular-nums shadow-xs' ) ) . '">' . esc_html( $text ) . '</span>';
	}
	$created = $product->get_date_created();
	if ( $new_days > 0 && $created && $created->getTimestamp() > time() - $new_days * DAY_IN_SECONDS ) {
		$out .= '<span class="' . esc_attr( brik_badge_class( 'default', 'default', 'brik-pc-badge brik-pc-badge--new shadow-xs' ) ) . '">' . esc_html__( 'New', 'brik-builder' ) . '</span>';
	}
	return '' !== $out ? '<div class="' . esc_attr( brik_cls( 'brik-pc-badges pointer-events-none absolute top-3 left-3 z-10 flex flex-col items-start gap-1.5', $extra ) ) . '">' . $out . '</div>' : '';
}

/**
 * The primary category name of a product (skipping "Uncategorized").
 */
function brik_woo_primary_category( WC_Product $product ) {
	$id    = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	$terms = get_the_terms( $id, 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return null;
	}
	$default = (int) get_option( 'default_product_cat' );
	foreach ( $terms as $term ) {
		if ( (int) $term->term_id !== $default ) {
			return $term;
		}
	}
	return null;
}

/**
 * Add-to-cart control for a card. Simple products add over AJAX (with a plain link as the
 * no-JS fallback); everything else links to the product.
 *
 * @param string $variant full|icon|overlay
 */
function brik_woo_add_to_cart_button( WC_Product $product, $variant = 'full', $extra = '' ) {
	$ajax  = $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock();
	$text  = $product->add_to_cart_text();
	$label = $product->add_to_cart_description();
	$url   = $product->add_to_cart_url();
	$icon  = $ajax ? 'shopping-bag' : ( $product->is_type( 'external' ) ? 'external-link' : 'arrow-right' );
	$attrs = array(
		'href'       => $url,
		'aria-label' => $label ? $label : $text,
		'rel'        => 'nofollow',
	);
	if ( $product->is_type( 'external' ) ) {
		$attrs['target'] = '_blank';
		$attrs['rel']    = 'nofollow noopener';
	}
	if ( $ajax ) {
		$attrs['data-brik-add-to-cart'] = (string) $product->get_id();
		$attrs['data-quantity']         = '1';
		$attrs['role']                  = 'button';
	}

	if ( 'icon' === $variant ) {
		$attrs['class'] = brik_button_class( 'outline', 'icon', brik_cls( 'brik-pc-add brik-atc relative z-10 rounded-full', $extra ) );
		$inner          = '<span class="brik-atc-label">' . brik_icon( $ajax ? 'plus' : $icon ) . '</span>';
	} else {
		$attrs['class'] = brik_button_class( 'overlay' === $variant ? 'secondary' : ( $ajax ? 'default' : 'outline' ), 'default', brik_cls( 'brik-pc-add brik-atc relative z-10', $extra ) );
		$inner          = '<span class="brik-atc-label inline-flex items-center gap-2">' . brik_icon( $icon ) . '<span>' . esc_html( $text ) . '</span></span>';
	}
	if ( $ajax ) {
		$inner .= '<span class="brik-atc-busy" aria-hidden="true">' . brik_icon( 'loader-circle', 'size-4 animate-spin' ) . '</span>'
			. '<span class="brik-atc-done inline-flex items-center gap-2" aria-hidden="true">' . brik_icon( 'check' ) . ( 'icon' === $variant ? '' : '<span>' . esc_html__( 'Added', 'brik-builder' ) . '</span>' ) . '</span>';
	}
	return '<a' . brik_attrs( $attrs ) . '><span class="brik-atc-stack">' . $inner . '</span></a>';
}

/**
 * Product card markup. Shared by the products module, the listing module (for products)
 * and the single product modules (related products, upsells).
 *
 * @param WC_Product $product Product.
 * @param array      $args    See brik_woo_card_defaults().
 */
function brik_woo_product_card( WC_Product $product, array $args = array() ) {
	$args  = wp_parse_args( $args, brik_woo_card_defaults() );
	$style = isset( brik_woo_card_styles()[ $args['style'] ] ) ? $args['style'] : 'card';
	$tag   = in_array( $args['title_tag'], array( 'h2', 'h3', 'h4', 'p' ), true ) ? $args['title_tag'] : 'h3';
	$url   = $product->get_permalink();
	$name  = wp_strip_all_tags( $product->get_name() );
	$ratio = brik_aspect_class( (string) $args['ratio'] );
	$ratio = $ratio ? $ratio : 'aspect-square';
	$size  = $args['image_size'] ? $args['image_size'] : 'woocommerce_thumbnail';
	$oos   = ! $product->is_in_stock();
	$lazy  = (int) $args['index'] > 3 ? 'lazy' : 'eager';

	// Images: the main one and, for hover, the first gallery image.
	$img_cls = 'brik-pc-img absolute inset-0 size-full object-cover transition-[scale,opacity] duration-500 ease-out';
	$image   = $product->get_image_id()
		? wp_get_attachment_image( $product->get_image_id(), $size, false, array( 'class' => $img_cls, 'alt' => $name, 'loading' => $lazy, 'decoding' => 'async' ) )
		: '<span class="absolute inset-0 grid place-items-center text-muted-foreground/40">' . brik_icon( 'image', 'size-10' ) . '</span>';
	$alt     = '';
	$gallery = $product->get_gallery_image_ids();
	if ( $args['hover_image'] && $gallery && $product->get_image_id() ) {
		$alt = wp_get_attachment_image( (int) $gallery[0], $size, false, array( 'class' => 'brik-pc-img-alt absolute inset-0 size-full object-cover opacity-0 transition-opacity duration-500 ease-out', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async', 'aria-hidden' => 'true' ) );
	}

	$badges = $args['badges'] ? brik_woo_badges( $product, (int) $args['new_days'] ) : '';
	$term   = $args['category'] ? brik_woo_primary_category( $product ) : null;
	$cat    = $term ? '<p class="brik-pc-cat truncate text-xs font-medium text-muted-foreground">' . esc_html( $term->name ) . '</p>' : '';
	$title  = '<' . $tag . ' class="brik-pc-title text-sm leading-snug font-medium text-pretty"><a class="brik-pc-link outline-none after:absolute after:inset-0 after:z-[1] after:rounded-[inherit] focus-visible:after:ring-[3px] focus-visible:after:ring-ring/50" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></' . $tag . '>';

	$rating = '';
	if ( $args['rating'] && wc_review_ratings_enabled() && $product->get_rating_count() > 0 ) {
		$rating = '<div class="brik-pc-rating flex items-center gap-1.5">' . brik_stars( (float) $product->get_average_rating(), 5, 'size-3.5' ) . '<span class="text-xs text-muted-foreground tabular-nums">(' . (int) $product->get_review_count() . ')</span></div>';
	}
	$price_html = $product->get_price_html();
	$price      = '' !== $price_html ? '<div class="brik-pc-price brik-woo-price text-sm font-semibold tabular-nums">' . $price_html . '</div>' : '';

	$qv = '';
	if ( $args['quick_view'] ) {
		/* translators: %s: product name */
		$qv_label = sprintf( __( 'Quick view: %s', 'brik-builder' ), $name );
		$qv       = '<button type="button" class="brik-pc-qv" data-brik-quick-view="' . (int) $product->get_id() . '" aria-label="' . esc_attr( $qv_label ) . '" aria-haspopup="dialog">' . brik_icon( 'eye', 'size-4' ) . '<span class="brik-pc-qv-text">' . esc_html__( 'Quick view', 'brik-builder' ) . '</span></button>';
	}

	$media_cls = brik_cls( 'brik-pc-media block shrink-0 overflow-hidden bg-muted', array( 'opacity-70' => $oos ) );
	$media_in  = '<a class="absolute inset-0" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $image . $alt . '</a>';

	$button = '';
	switch ( $style ) {
		case 'overlay':
			$button = $args['button'] ? brik_woo_add_to_cart_button( $product, 'icon', 'bg-background/90 backdrop-blur border-transparent' ) : '';
			return '<article class="' . esc_attr( brik_cls( 'brik-pc brik-pc--overlay group/pc relative isolate flex h-full flex-col overflow-hidden rounded-xl bg-muted text-white shadow-xs', $ratio, array( 'is-oos' => $oos ) ) ) . '" data-product-id="' . (int) $product->get_id() . '">'
				. '<div class="' . esc_attr( brik_cls( $media_cls, 'absolute inset-0 -z-10' ) ) . '">' . $media_in . '</div>'
				. '<div class="pointer-events-none absolute inset-0 -z-[5] bg-gradient-to-t from-black/80 via-black/25 to-transparent" aria-hidden="true"></div>'
				. $badges
				. ( $qv ? '<div class="absolute top-3 right-3 z-10">' . $qv . '</div>' : '' )
				. '<div class="brik-pc-body mt-auto flex items-end justify-between gap-3 p-4">'
				. '<div class="grid min-w-0 gap-1 [&_.brik-pc-cat]:text-white/70 [&_.text-muted-foreground]:text-white/70">' . $cat . $title . $rating . str_replace( 'brik-pc-price', 'brik-pc-price text-white', $price ) . '</div>'
				. $button
				. '</div></article>';

		case 'horizontal':
			$button  = $args['button'] ? brik_woo_add_to_cart_button( $product, 'full', 'h-8 px-3 text-xs' ) : '';
			$excerpt = '';
			if ( $args['excerpt'] ) {
				$text    = wp_trim_words( wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : $product->get_description() ), 18 );
				$excerpt = '' !== $text ? '<p class="brik-pc-excerpt line-clamp-2 text-sm text-muted-foreground">' . esc_html( $text ) . '</p>' : '';
			}
			return '<article class="' . esc_attr( brik_cls( 'brik-pc brik-pc--horizontal group/pc relative flex h-full items-stretch gap-4 overflow-hidden rounded-xl border bg-card p-3 text-card-foreground shadow-xs transition-shadow duration-300 hover:shadow-md', array( 'is-oos' => $oos ) ) ) . '" data-product-id="' . (int) $product->get_id() . '">'
				. '<div class="' . esc_attr( brik_cls( $media_cls, 'relative w-28 self-start rounded-lg sm:w-36', $ratio ) ) . '">' . $media_in . ( $badges ? str_replace( 'top-3 left-3', 'top-2 left-2', $badges ) : '' ) . '</div>'
				. '<div class="brik-pc-body flex min-w-0 flex-1 flex-col gap-1 py-1 pr-1">' . $cat . $title . $rating . $excerpt
				. '<div class="brik-pc-foot mt-auto flex flex-wrap items-center justify-between gap-2 pt-2">' . $price . '<div class="flex items-center gap-1.5">' . ( $qv ? str_replace( 'brik-pc-qv"', 'brik-pc-qv brik-pc-qv--inline"', $qv ) : '' ) . $button . '</div></div>'
				. '</div></article>';

		case 'minimal':
			$button = $args['button'] ? brik_woo_add_to_cart_button( $product, 'overlay', 'w-full shadow-sm' ) : '';
			return '<article class="' . esc_attr( brik_cls( 'brik-pc brik-pc--minimal group/pc relative flex h-full flex-col gap-3', array( 'is-oos' => $oos ) ) ) . '" data-product-id="' . (int) $product->get_id() . '">'
				. '<div class="' . esc_attr( brik_cls( $media_cls, 'relative rounded-xl', $ratio ) ) . '">' . $media_in . $badges
				. ( $qv ? '<div class="absolute top-3 right-3 z-10">' . $qv . '</div>' : '' )
				. ( $button ? '<div class="brik-pc-reveal absolute inset-x-3 bottom-3 z-10">' . $button . '</div>' : '' )
				. '</div>'
				. '<div class="brik-pc-body flex flex-1 flex-col gap-1">' . $cat . $title . $rating . $price . '</div>'
				. '</article>';
	}

	// Card.
	$icon_btn = 'icon' === $args['button_style'];
	$button   = $args['button'] ? brik_woo_add_to_cart_button( $product, $icon_btn ? 'icon' : 'full', $icon_btn ? '' : 'w-full' ) : '';
	return '<article class="' . esc_attr( brik_cls( 'brik-pc brik-pc--card group/pc relative flex h-full flex-col overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-[box-shadow,border-color] duration-300 hover:border-foreground/15 hover:shadow-lg', array( 'is-oos' => $oos ) ) ) . '" data-product-id="' . (int) $product->get_id() . '">'
		. '<div class="' . esc_attr( brik_cls( $media_cls, 'relative', $ratio ) ) . '">' . $media_in . $badges
		. ( $qv ? '<div class="brik-pc-reveal absolute inset-x-0 bottom-3 z-10 flex justify-center">' . $qv . '</div>' : '' )
		. '</div>'
		. '<div class="brik-pc-body flex flex-1 flex-col gap-1 p-4">' . $cat . $title . $rating
		. '<div class="brik-pc-foot mt-auto flex items-center justify-between gap-2 pt-2">' . $price . ( $icon_btn ? $button : '' ) . '</div>'
		. ( ! $icon_btn && $button ? '<div class="pt-3">' . $button . '</div>' : '' )
		. '</div></article>';
}

/**
 * Listing module support: products get the shop card instead of the blog card. See the
 * brik/listing_card filter in brik_listing_results().
 */
add_filter(
	'brik/listing_card',
	static function ( $html, $post, $a ) {
		if ( null !== $html || ! $post instanceof WP_Post || 'product' !== $post->post_type || ! brik_woo_shop_enabled() ) {
			return $html;
		}
		$product = wc_get_product( $post );
		if ( ! $product ) {
			return $html;
		}
		$ratio = isset( $a['card_ratio'] ) && '' !== $a['card_ratio'] && '16:9' !== $a['card_ratio'] ? $a['card_ratio'] : '1:1';
		return brik_woo_product_card(
			$product,
			array(
				'style'    => isset( $a['layout'] ) && 'list' === $a['layout'] ? 'horizontal' : 'card',
				'ratio'    => $ratio,
				'category' => ! isset( $a['card_terms'] ) || brik_form_bool( $a['card_terms'] ),
				'excerpt'  => isset( $a['card_excerpt'] ) && brik_form_bool( $a['card_excerpt'] ),
			)
		);
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Product queries.
 * ----------------------------------------------------------------------- */

function brik_woo_sources() {
	return array(
		'all'          => __( 'All products', 'brik-builder' ),
		'featured'     => __( 'Featured', 'brik-builder' ),
		'sale'         => __( 'On sale', 'brik-builder' ),
		'best_selling' => __( 'Best selling', 'brik-builder' ),
		'top_rated'    => __( 'Top rated', 'brik-builder' ),
		'newest'       => __( 'Newest', 'brik-builder' ),
		'ids'          => __( 'Specific products', 'brik-builder' ),
		'categories'   => __( 'Categories', 'brik-builder' ),
		'tags'         => __( 'Tags', 'brik-builder' ),
		'attribute'    => __( 'Attribute terms', 'brik-builder' ),
		'related'      => __( 'Related to the current product', 'brik-builder' ),
		'upsells'      => __( 'Upsells of the current product', 'brik-builder' ),
		'cross_sells'  => __( 'Cross-sells (current product or cart)', 'brik-builder' ),
		'current'      => __( 'Current query (shop and archive templates)', 'brik-builder' ),
	);
}

function brik_woo_orderbys() {
	return array(
		'default'    => __( 'Default sorting', 'brik-builder' ),
		'date'       => __( 'Date', 'brik-builder' ),
		'title'      => __( 'Name', 'brik-builder' ),
		'price'      => __( 'Price', 'brik-builder' ),
		'popularity' => __( 'Popularity (sales)', 'brik-builder' ),
		'rating'     => __( 'Average rating', 'brik-builder' ),
		'menu_order' => __( 'Menu order', 'brik-builder' ),
		'rand'       => __( 'Random', 'brik-builder' ),
	);
}

/**
 * Sort choices offered to shoppers, keyed like WooCommerce's ?orderby values:
 * value => [label, orderby, order].
 */
function brik_woo_sort_options() {
	$out = array(
		'menu_order' => array( __( 'Default sorting', 'brik-builder' ), 'default', 'ASC' ),
		'popularity' => array( __( 'Popularity', 'brik-builder' ), 'popularity', 'DESC' ),
		'rating'     => array( __( 'Average rating', 'brik-builder' ), 'rating', 'DESC' ),
		'date'       => array( __( 'Newest', 'brik-builder' ), 'date', 'DESC' ),
		'price'      => array( __( 'Price: low to high', 'brik-builder' ), 'price', 'ASC' ),
		'price-desc' => array( __( 'Price: high to low', 'brik-builder' ), 'price', 'DESC' ),
	);
	if ( ! wc_review_ratings_enabled() ) {
		unset( $out['rating'] );
	}
	return apply_filters( 'brik/woo_sort_options', $out );
}

/**
 * Comma separated ids/slugs as term ids of a taxonomy.
 */
function brik_woo_term_ids( $taxonomy, $value ) {
	$ids = array();
	foreach ( brik_listing_list( wp_strip_all_tags( is_array( $value ) ? implode( ',', $value ) : (string) $value ), 50 ) as $v ) {
		$term = ctype_digit( $v ) ? get_term( (int) $v, $taxonomy ) : get_term_by( 'slug', sanitize_title( $v ), $taxonomy );
		if ( $term instanceof WP_Term ) {
			$ids[] = (int) $term->term_id;
		}
	}
	return array_values( array_unique( $ids ) );
}

/**
 * Attribute taxonomies (pa_*) as name => label.
 */
function brik_woo_attribute_options() {
	$out = array();
	if ( brik_woo_shop_enabled() ) {
		foreach ( wc_get_attribute_taxonomies() as $tax ) {
			$out[ wc_attribute_taxonomy_name( $tax->attribute_name ) ] = $tax->attribute_label;
		}
	}
	return $out;
}

function brik_woo_is_attribute( $taxonomy ) {
	return is_string( $taxonomy ) && 0 === strpos( $taxonomy, 'pa_' ) && taxonomy_exists( $taxonomy );
}

/**
 * Restrict a post__in list. null means "no restriction yet".
 */
function brik_woo_intersect( $current, array $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	return null === $current ? $ids : array_values( array_intersect( $current, $ids ) );
}

/**
 * The product archive WordPress picked for the page, reduced to what can be replayed over AJAX.
 */
function brik_woo_main_context() {
	$ctx = array();
	if ( is_search() ) {
		$ctx['s'] = get_search_query( false );
	}
	if ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$ctx['taxonomy'] = $term->taxonomy;
			$ctx['term']     = (int) $term->term_id;
		}
	}
	return $ctx;
}

function brik_woo_is_product_archive() {
	return ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) || ( is_search() && 'product' === get_query_var( 'post_type' ) );
}


/**
 * WP_Query arguments for a products module.
 *
 * Price, rating, stock and the WooCommerce sort orders go through the product lookup table
 * (see brik_woo_posts_clauses()), the same data WooCommerce's own catalog uses.
 *
 * @param array $a   Module attributes.
 * @param array $opt page, clauses (from brik_woo_filter_parse()), main (archive context or
 *                   null), product (WC_Product for related/upsells/cross-sells).
 */
function brik_woo_products_query_args( array $a, array $opt = array() ) {
	$opt = wp_parse_args(
		$opt,
		array(
			'page'    => 1,
			'clauses' => array(),
			'main'    => null,
			'product' => null,
		)
	);
	$get = static function ( $key, $default = '' ) use ( $a ) {
		return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $default;
	};

	$ppp     = min( 100, max( 1, (int) $get( 'per_page', 8 ) ) );
	$offset  = max( 0, (int) $get( 'offset', 0 ) );
	$page    = max( 1, (int) $opt['page'] );
	$source  = (string) $get( 'source', 'all' );
	$source  = isset( brik_woo_sources()[ $source ] ) ? $source : 'all';
	$main    = is_array( $opt['main'] ) ? $opt['main'] : null;
	$product = $opt['product'] instanceof WC_Product ? $opt['product'] : null;
	if ( 'current' === $source && ! $main ) {
		$source = 'all';
	}

	$args   = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => $ppp,
		'offset'              => $offset + ( $page - 1 ) * $ppp,
		'ignore_sticky_posts' => true,
	);
	$tax    = array();
	$in     = null;
	$not_in = array();
	$wc     = array();
	$vis    = wc_get_product_visibility_term_ids();

	$orderby = (string) $get( 'orderby', 'default' );
	$orderby = isset( brik_woo_orderbys()[ $orderby ] ) ? $orderby : 'default';
	$order   = strtoupper( (string) $get( 'order', '' ) );
	$order   = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : '';

	switch ( $source ) {
		case 'featured':
			$tax[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => array( (int) $vis['featured'] ),
			);
			break;
		case 'sale':
			$in = brik_woo_intersect( $in, wc_get_product_ids_on_sale() );
			break;
		case 'best_selling':
			$orderby = 'default' === $orderby ? 'popularity' : $orderby;
			break;
		case 'top_rated':
			$orderby = 'default' === $orderby ? 'rating' : $orderby;
			break;
		case 'newest':
			$orderby = 'default' === $orderby ? 'date' : $orderby;
			break;
		case 'ids':
			$in      = brik_woo_intersect( $in, brik_listing_list( (string) $get( 'ids' ), 100 ) );
			$orderby = 'default' === $orderby ? 'post__in' : $orderby;
			break;
		case 'categories':
		case 'tags':
		case 'attribute':
			if ( 'attribute' === $source ) {
				$taxonomy = sanitize_key( (string) $get( 'attribute' ) );
				$taxonomy = brik_woo_is_attribute( $taxonomy ) ? $taxonomy : '';
			} else {
				$taxonomy = 'categories' === $source ? 'product_cat' : 'product_tag';
			}
			$field = 'categories' === $source ? 'categories' : ( 'tags' === $source ? 'tags' : 'attribute_terms' );
			$ids   = $taxonomy ? brik_woo_term_ids( $taxonomy, $get( $field ) ) : array();
			if ( $ids ) {
				$op    = strtoupper( (string) $get( 'terms_operator', 'IN' ) );
				$tax[] = array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $ids,
					'operator'         => in_array( $op, array( 'IN', 'NOT IN', 'AND' ), true ) ? $op : 'IN',
					'include_children' => 'product_cat' === $taxonomy,
				);
			} else {
				$in = array();
			}
			break;
		case 'related':
		case 'upsells':
		case 'cross_sells':
			$ids = array();
			if ( $product ) {
				if ( 'related' === $source ) {
					$ids = wc_get_related_products( $product->get_id(), max( $ppp * 3, 12 ) );
				} elseif ( 'upsells' === $source ) {
					$ids = $product->get_upsell_ids();
				} else {
					$ids = $product->get_cross_sell_ids();
				}
				$not_in[] = $product->get_id();
			} elseif ( 'cross_sells' === $source && WC()->cart ) {
				$ids = WC()->cart->get_cross_sells();
				foreach ( WC()->cart->get_cart() as $item ) {
					$not_in[] = (int) $item['product_id'];
				}
			}
			$in = brik_woo_intersect( $in, $ids );
			if ( 'related' === $source && 'default' === $orderby ) {
				$orderby = 'rand';
			}
			break;
		case 'current':
			if ( isset( $main['s'] ) && is_string( $main['s'] ) && '' !== trim( $main['s'] ) ) {
				$args['s'] = substr( sanitize_text_field( $main['s'] ), 0, 100 );
			}
			if ( ! empty( $main['taxonomy'] ) && ! empty( $main['term'] ) ) {
				$taxonomy = sanitize_key( (string) $main['taxonomy'] );
				$term     = get_term( (int) $main['term'], $taxonomy );
				if ( is_object_in_taxonomy( 'product', $taxonomy ) && is_taxonomy_viewable( $taxonomy ) && $term instanceof WP_Term ) {
					$tax[] = array(
						'taxonomy'         => $taxonomy,
						'field'            => 'term_id',
						'terms'            => array( (int) $term->term_id ),
						'include_children' => is_taxonomy_hierarchical( $taxonomy ),
					);
				}
			}
			break;
	}

	// Catalog visibility, like the shop itself.
	$hidden = array( (int) ( isset( $args['s'] ) ? $vis['exclude-from-search'] : $vis['exclude-from-catalog'] ) );
	$hide   = (string) $get( 'hide_out_of_stock', '' );
	if ( 'yes' === $hide || ( '' === $hide && 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) ) {
		$hidden[] = (int) $vis['outofstock'];
	}
	$tax[] = array(
		'taxonomy' => 'product_visibility',
		'field'    => 'term_taxonomy_id',
		'terms'    => array_values( array_filter( $hidden ) ),
		'operator' => 'NOT IN',
	);

	// Filters chosen by the shopper (already validated).
	foreach ( (array) $opt['clauses'] as $clause ) {
		switch ( isset( $clause['kind'] ) ? $clause['kind'] : '' ) {
			case 'search':
				$args['s'] = $clause['value'];
				break;
			case 'price':
				if ( null !== $clause['min'] ) {
					$wc['min_price'] = (float) $clause['min'];
				}
				if ( null !== $clause['max'] ) {
					$wc['max_price'] = (float) $clause['max'];
				}
				break;
			case 'tax':
				$tax[] = array(
					'taxonomy'         => $clause['taxonomy'],
					'field'            => 'term_id',
					'terms'            => $clause['terms'],
					'operator'         => 'IN',
					'include_children' => 'product_cat' === $clause['taxonomy'],
				);
				break;
			case 'rating':
				$wc['rating'] = (int) $clause['min'];
				break;
			case 'stock':
				$wc['instock'] = true;
				break;
			case 'sale':
				$in = brik_woo_intersect( $in, wc_get_product_ids_on_sale() );
				break;
			case 'sort':
				$orderby = $clause['orderby'];
				$order   = $clause['order'];
				break;
		}
	}

	// Order.
	switch ( $orderby ) {
		case 'price':
		case 'popularity':
		case 'rating':
			$wc['order'] = $orderby;
			$wc['dir']   = $order ? $order : ( 'price' === $orderby ? 'ASC' : 'DESC' );
			break;
		case 'date':
			$args['orderby'] = array(
				'date' => $order ? $order : 'DESC',
				'ID'   => $order ? $order : 'DESC',
			);
			break;
		case 'title':
			$args['orderby'] = 'title';
			$args['order']   = $order ? $order : 'ASC';
			break;
		case 'rand':
			// Random order can't be paged reliably; keep later pages stable for the day.
			$args['orderby'] = $page > 1 ? 'RAND(' . (int) gmdate( 'Ymd' ) . ')' : 'rand';
			break;
		case 'post__in':
			$args['orderby'] = 'post__in';
			break;
		default:
			$args['orderby'] = array(
				'menu_order' => $order ? $order : 'ASC',
				'title'      => 'ASC',
			);
	}

	$tax['relation']   = 'AND';
	$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
	if ( null !== $in ) {
		$in               = array_values( array_diff( $in, $not_in ) );
		$args['post__in'] = $in ? $in : array( 0 );
	} elseif ( $not_in ) {
		$args['post__not_in'] = array_values( array_unique( $not_in ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams
	}
	if ( $wc ) {
		$args['brik_wc'] = $wc;
	}
	return apply_filters( 'brik/woo_products_query_args', $args, $a, $opt );
}

/**
 * Price range, rating, stock and lookup-table ordering for queries built above.
 */
add_filter(
	'posts_clauses',
	static function ( $clauses, $query ) {
		$wc = $query->get( 'brik_wc' );
		if ( ! is_array( $wc ) || ! $wc ) {
			return $clauses;
		}
		global $wpdb;
		$lookup             = $wpdb->prefix . 'wc_product_meta_lookup';
		$clauses['join']   .= " LEFT JOIN {$lookup} brik_pl ON {$wpdb->posts}.ID = brik_pl.product_id ";
		if ( isset( $wc['min_price'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND brik_pl.max_price >= %f', $wc['min_price'] );
		}
		if ( isset( $wc['max_price'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND brik_pl.min_price <= %f', $wc['max_price'] );
		}
		if ( ! empty( $wc['rating'] ) ) {
			$clauses['where'] .= $wpdb->prepare( ' AND brik_pl.average_rating >= %f', (float) $wc['rating'] );
		}
		if ( ! empty( $wc['instock'] ) ) {
			$clauses['where'] .= " AND brik_pl.stock_status IN ('instock','onbackorder')";
		}
		if ( ! empty( $wc['order'] ) ) {
			$dir = isset( $wc['dir'] ) && 'ASC' === $wc['dir'] ? 'ASC' : 'DESC';
			$id  = "{$wpdb->posts}.ID {$dir}";
			switch ( $wc['order'] ) {
				case 'price':
					$clauses['orderby'] = ( 'ASC' === $dir ? 'brik_pl.min_price ASC' : 'brik_pl.max_price DESC' ) . ', ' . $id;
					break;
				case 'popularity':
					$clauses['orderby'] = "brik_pl.total_sales {$dir}, {$id}";
					break;
				case 'rating':
					$clauses['orderby'] = "brik_pl.average_rating {$dir}, brik_pl.rating_count {$dir}, {$id}";
					break;
			}
		}
		return $clauses;
	},
	10,
	2
);

/* -------------------------------------------------------------------------
 * Product filters.
 * ----------------------------------------------------------------------- */

function brik_woo_filter_types() {
	return array(
		'price'     => __( 'Price range', 'brik-builder' ),
		'category'  => __( 'Categories', 'brik-builder' ),
		'attribute' => __( 'Attribute', 'brik-builder' ),
		'rating'    => __( 'Rating', 'brik-builder' ),
		'stock'     => __( 'In stock only', 'brik-builder' ),
		'sale'      => __( 'On sale only', 'brik-builder' ),
		'search'    => __( 'Search', 'brik-builder' ),
		'sort'      => __( 'Sort', 'brik-builder' ),
	);
}

function brik_woo_filter_uis() {
	return array(
		'auto'       => __( 'Automatic', 'brik-builder' ),
		'checkboxes' => __( 'Checkboxes', 'brik-builder' ),
		'pills'      => __( 'Pills', 'brik-builder' ),
		'swatches'   => __( 'Color swatches', 'brik-builder' ),
		'select'     => __( 'Dropdown', 'brik-builder' ),
	);
}

/**
 * Normalize a product_filters repeater into definitions with unique keys.
 */
function brik_woo_filter_defs( $items ) {
	$defs  = array();
	$types = brik_woo_filter_types();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item = wp_parse_args(
			$item,
			array(
				'type'        => '',
				'label'       => '',
				'attribute'   => '',
				'ui'          => 'auto',
				'show_counts' => true,
				'collapsed'   => false,
			)
		);
		$type = (string) $item['type'];
		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}
		$def = array(
			'type'        => $type,
			'label'       => wp_strip_all_tags( (string) $item['label'] ),
			'ui'          => isset( brik_woo_filter_uis()[ $item['ui'] ] ) ? $item['ui'] : 'auto',
			'show_counts' => brik_form_bool( $item['show_counts'] ),
			'collapsed'   => brik_form_bool( $item['collapsed'] ),
			'taxonomy'    => '',
		);
		switch ( $type ) {
			case 'category':
				$def['taxonomy'] = 'product_cat';
				$def['key']      = 'cat';
				break;
			case 'attribute':
				$tax = sanitize_key( (string) $item['attribute'] );
				if ( ! brik_woo_is_attribute( $tax ) ) {
					continue 2;
				}
				$def['taxonomy'] = $tax;
				$def['key']      = $tax;
				break;
			case 'sort':
				$def['key'] = 'orderby';
				break;
			default:
				$def['key'] = $type;
		}
		if ( 'auto' === $def['ui'] ) {
			$def['ui'] = 'attribute' === $type && preg_match( '/colou?r/i', $def['taxonomy'] ) ? 'swatches' : ( 'attribute' === $type ? 'pills' : 'checkboxes' );
		}
		if ( '' === $def['label'] ) {
			$def['label'] = 'attribute' === $type ? wc_attribute_label( $def['taxonomy'] ) : $types[ $type ];
		}
		$defs[ $def['key'] ] = $def;
	}
	return array_values( $defs );
}

/**
 * Query parameter name of a filter: bf_{products}_{filter}, or WooCommerce's own name in
 * "shop query" mode (a filter bar without a target works on WooCommerce's archive).
 */
function brik_woo_filter_param( $key, $def_key, $native = false, $part = '' ) {
	if ( $native ) {
		switch ( $def_key ) {
			case 'price':
				return 'max' === $part ? 'max_price' : 'min_price';
			case 'rating':
				return 'rating_filter';
			case 'orderby':
				return 'orderby';
			case 'search':
				return 's';
			default:
				return 0 === strpos( $def_key, 'pa_' ) ? 'filter_' . substr( $def_key, 3 ) : '';
		}
	}
	return brik_listing_param( $key, $def_key . ( $part ? '_' . $part : '' ) );
}

/**
 * Filter parameters of the current request: bf_* plus WooCommerce's catalog parameters.
 */
function brik_woo_request_params() {
	$out = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only filtering.
		if ( ! is_string( $k ) ) {
			continue;
		}
		if ( 0 === strpos( $k, 'bf_' ) || 0 === strpos( $k, 'filter_' ) || in_array( $k, array( 'min_price', 'max_price', 'rating_filter', 'orderby' ), true ) ) {
			$out[ $k ] = wp_unslash( $v );
		}
	}
	return $out;
}

/**
 * Validate filter values against the definitions.
 *
 * Values are read from bf_{key}_{filter}; WooCommerce's own names (min_price, max_price,
 * filter_{attribute}, rating_filter, orderby) work as fallbacks. Sorting is always accepted
 * because its values are a fixed list.
 *
 * @return array [ clauses, state ]
 */
function brik_woo_filter_parse( array $defs, array $params, $key ) {
	$clauses = array();
	$state   = array();
	$read    = static function ( $name, $alias = '' ) use ( $params ) {
		if ( isset( $params[ $name ] ) && '' !== $params[ $name ] ) {
			return $params[ $name ];
		}
		return $alias && isset( $params[ $alias ] ) ? $params[ $alias ] : null;
	};
	$num     = static function ( $v ) {
		return ( is_string( $v ) || is_numeric( $v ) ) && is_numeric( trim( (string) $v ) ) && (float) $v >= 0 ? (float) $v : null;
	};

	$by_key = array();
	foreach ( $defs as $def ) {
		$by_key[ $def['key'] ] = $def;
	}
	if ( ! isset( $by_key['orderby'] ) ) {
		$by_key['orderby'] = array(
			'type' => 'sort',
			'key'  => 'orderby',
			'ui'   => 'select',
		);
	}

	foreach ( $by_key as $k => $def ) {
		switch ( $def['type'] ) {
			case 'price':
				$min = $num( $read( brik_listing_param( $key, 'price_min' ), 'min_price' ) );
				$max = $num( $read( brik_listing_param( $key, 'price_max' ), 'max_price' ) );
				if ( null !== $min && null !== $max && $min > $max ) {
					list( $min, $max ) = array( $max, $min );
				}
				if ( null !== $min || null !== $max ) {
					$clauses[]   = array( 'kind' => 'price', 'min' => $min, 'max' => $max );
					$state[ $k ] = array( 'min' => $min, 'max' => $max );
				}
				break;

			case 'category':
			case 'attribute':
				$tax   = $def['taxonomy'];
				$alias = 'attribute' === $def['type'] ? 'filter_' . substr( $tax, 3 ) : '';
				$raw   = $read( brik_listing_param( $key, $k ), $alias );
				$ids   = array();
				$slugs = array();
				foreach ( brik_listing_list( $raw ) as $slug ) {
					$term = get_term_by( 'slug', sanitize_title( $slug ), $tax );
					if ( $term instanceof WP_Term ) {
						$ids[]   = (int) $term->term_id;
						$slugs[] = $term->slug;
					}
				}
				if ( 'select' === $def['ui'] ) {
					$ids   = array_slice( $ids, 0, 1 );
					$slugs = array_slice( $slugs, 0, 1 );
				}
				if ( $ids ) {
					$clauses[]   = array( 'kind' => 'tax', 'taxonomy' => $tax, 'terms' => $ids );
					$state[ $k ] = $slugs;
				}
				break;

			case 'rating':
				$list   = brik_listing_list( $read( brik_listing_param( $key, 'rating' ), 'rating_filter' ), 5 );
				$values = array();
				foreach ( $list as $v ) {
					if ( ctype_digit( $v ) && (int) $v >= 1 && (int) $v <= 5 ) {
						$values[] = (int) $v;
					}
				}
				if ( $values ) {
					$clauses[]   = array( 'kind' => 'rating', 'min' => min( $values ) );
					$state[ $k ] = (string) min( $values );
				}
				break;

			case 'stock':
			case 'sale':
				$v = $read( brik_listing_param( $key, $k ) );
				if ( is_string( $v ) && in_array( $v, array( '1', 'yes', 'on' ), true ) ) {
					$clauses[]   = array( 'kind' => $def['type'] );
					$state[ $k ] = '1';
				}
				break;

			case 'search':
				$v = $read( brik_listing_param( $key, 'search' ) );
				$v = is_string( $v ) ? trim( sanitize_text_field( $v ) ) : '';
				$v = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 100 ) : substr( $v, 0, 100 );
				if ( '' !== $v ) {
					$clauses[]   = array( 'kind' => 'search', 'value' => $v );
					$state[ $k ] = $v;
				}
				break;

			case 'sort':
				$v       = $read( brik_listing_param( $key, 'orderby' ), 'orderby' );
				$options = brik_woo_sort_options();
				if ( is_string( $v ) && isset( $options[ $v ] ) ) {
					$clauses[]   = array( 'kind' => 'sort', 'orderby' => $options[ $v ][1], 'order' => $options[ $v ][2] );
					$state[ $k ] = $v;
				}
				break;
		}
	}
	return array( $clauses, $state );
}

/**
 * Filter definitions of every product_filters node in the tree that targets a products module.
 */
function brik_woo_filters_for( Renderer $renderer, array $tree, $key ) {
	$def = Modules::get( 'product_filters' );
	if ( ! $def ) {
		return array();
	}
	$defs = array();
	foreach ( brik_listing_nodes( $tree, 'product_filters' ) as $node ) {
		$node  = wp_parse_args( $node, array( 'attrs' => array(), 'children' => array() ) );
		$attrs = $renderer->resolve_attrs( $node, $def );
		if ( brik_listing_key( $attrs['target'], '' ) === $key ) {
			foreach ( brik_woo_filter_defs( $attrs['filters'] ) as $d ) {
				if ( ! isset( $defs[ $d['key'] ] ) ) {
					$defs[ $d['key'] ] = $d;
				}
			}
		}
	}
	return array_values( $defs );
}

/**
 * Attributes of the products module with the given key, or null.
 */
function brik_woo_target_attrs( Renderer $renderer, array $tree, $key ) {
	$def = Modules::get( 'products' );
	foreach ( $def ? brik_listing_nodes( $tree, 'products' ) : array() as $node ) {
		$node  = wp_parse_args( $node, array( 'id' => '', 'attrs' => array(), 'children' => array() ) );
		$attrs = $renderer->resolve_attrs( $node, $def );
		if ( brik_listing_key( $attrs['css_id'], $node['id'] ) === $key ) {
			$attrs['_node'] = $node['id'];
			return $attrs;
		}
	}
	return null;
}

/**
 * Lowest and highest price in the catalog, for the price slider.
 *
 * @return array [ min, max ] rounded outwards to whole numbers, or [ null, null ].
 */
function brik_woo_price_bounds() {
	global $wpdb;
	$cache  = 'brik_wpb_' . brik_listing_cache_version();
	$cached = get_transient( $cache );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$lookup = $wpdb->prefix . 'wc_product_meta_lookup';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row( "SELECT MIN(l.min_price) AS lo, MAX(l.max_price) AS hi FROM {$lookup} l INNER JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE p.post_type = 'product' AND p.post_status = 'publish'" );
	$out = $row && null !== $row->lo ? array( (float) floor( $row->lo ), (float) ceil( $row->hi ) ) : array( null, null );
	set_transient( $cache, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/**
 * A swatch color for an attribute term: term meta (color, brik_color, swatch) or the term
 * name when it is a CSS color keyword.
 */
function brik_woo_swatch_color( WP_Term $term ) {
	foreach ( array( 'brik_color', 'color', 'swatch', 'product_attribute_color' ) as $meta ) {
		$v = get_term_meta( $term->term_id, $meta, true );
		if ( is_string( $v ) && preg_match( '/^#[0-9a-f]{3,8}$/i', trim( $v ) ) ) {
			return trim( $v );
		}
	}
	$named = array( 'black', 'white', 'gray', 'grey', 'silver', 'red', 'maroon', 'orange', 'yellow', 'gold', 'olive', 'lime', 'green', 'teal', 'cyan', 'aqua', 'blue', 'navy', 'indigo', 'purple', 'violet', 'magenta', 'fuchsia', 'pink', 'brown', 'beige', 'tan', 'khaki', 'coral', 'salmon', 'crimson', 'turquoise', 'lavender', 'ivory', 'mint' );
	$slug  = strtolower( preg_replace( '/[^a-z]/i', '', $term->slug ) );
	return in_array( $slug, $named, true ) ? $slug : '';
}

/* -------------------------------------------------------------------------
 * Results markup.
 * ----------------------------------------------------------------------- */

/**
 * Run (once per request) the query of a products module.
 */
function brik_woo_products_query( array $a, array $opt ) {
	static $cache = array();
	$args = brik_woo_products_query_args( $a, $opt );
	$hash = md5( wp_json_encode( $args ) );
	if ( ! isset( $cache[ $hash ] ) ) {
		$cache[ $hash ] = new WP_Query( $args );
	}
	return array( $cache[ $hash ], $args );
}

/**
 * Items, pagination and empty state of a products module.
 *
 * @param array $a   Module attributes.
 * @param array $opt key, page, clauses, main, product, base_url, canvas, filtered (bool).
 * @return array html, total, pages, page, per_page
 */
function brik_woo_products_results( array $a, array $opt ) {
	$opt = wp_parse_args(
		$opt,
		array(
			'key'      => '',
			'page'     => 1,
			'clauses'  => array(),
			'main'     => null,
			'product'  => null,
			'base_url' => '',
			'canvas'   => false,
			'filtered' => false,
		)
	);
	$get = static function ( $key, $default = '' ) use ( $a ) {
		return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $default;
	};

	$layout     = in_array( $get( 'layout', 'grid' ), array( 'grid', 'carousel', 'list' ), true ) ? $get( 'layout', 'grid' ) : 'grid';
	$pagination = 'carousel' === $layout ? '' : ( in_array( $get( 'pagination' ), array( 'numbered', 'load_more', 'infinite' ), true ) ? $get( 'pagination' ) : '' );
	$page       = $pagination ? max( 1, (int) $opt['page'] ) : 1;

	list( $query, $args ) = brik_woo_products_query(
		$a,
		array(
			'page'    => $page,
			'clauses' => $opt['clauses'],
			'main'    => $opt['main'],
			'product' => $opt['product'],
		)
	);
	$ppp   = max( 1, (int) $args['posts_per_page'] );
	$total = max( 0, (int) $query->found_posts - max( 0, (int) $get( 'offset', 0 ) ) );
	$pages = (int) ceil( $total / $ppp );

	if ( ! $query->posts ) {
		$text  = '' !== trim( (string) $get( 'empty_message' ) ) ? $get( 'empty_message' ) : __( 'No products match your selection.', 'brik-builder' );
		$clear = '';
		if ( $opt['filtered'] && ! $opt['canvas'] && $opt['base_url'] ) {
			$clean = remove_query_arg( array_merge( array( 'min_price', 'max_price', 'rating_filter' ), array_keys( brik_woo_request_params() ) ), $opt['base_url'] );
			$clear = '<a class="' . esc_attr( brik_button_class( 'outline', 'sm', 'mt-1' ) ) . '" href="' . esc_url( $clean ) . '" data-brik-products-clear>' . esc_html__( 'Clear filters', 'brik-builder' ) . '</a>';
		}
		return array(
			'html'     => '<div class="brik-products-empty flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-16 text-center">'
				. '<span class="grid size-12 place-items-center rounded-full bg-muted text-muted-foreground">' . brik_icon( 'package-search', 'size-6' ) . '</span>'
				. '<p class="text-sm text-muted-foreground">' . brik_inline( $text ) . '</p>' . $clear . '</div>',
			'total'    => 0,
			'pages'    => 0,
			'page'     => $page,
			'per_page' => $ppp,
		);
	}

	$card = brik_woo_card_args( $a );
	if ( 'list' === $layout ) {
		$card['style'] = 'horizontal';
	}
	$items = '';
	foreach ( $query->posts as $i => $post ) {
		$product = wc_get_product( $post );
		if ( ! $product ) {
			continue;
		}
		$card['index'] = $i;
		$items        .= '<div class="brik-listing-item brik-products-item" data-product-id="' . (int) $product->get_id() . '" style="--brik-i:' . (int) $i . '">' . brik_woo_product_card( $product, $card ) . '</div>';
	}

	$cls  = brik_cls(
		'brik-listing-items brik-products-items is-equal',
		'brik-listing-items--' . ( 'list' === $layout ? 'grid' : $layout ),
		array(
			'brik-products-items--list' => 'list' === $layout,
			'is-stagger'                => brik_form_bool( $get( 'animate', false ) ),
		)
	);
	$html = '<div class="' . esc_attr( $cls ) . '"' . ( 'carousel' === $layout ? ' tabindex="0" role="region" aria-label="' . esc_attr__( 'Products', 'brik-builder' ) . '"' : '' ) . '>' . $items . '</div>';

	$key  = $opt['key'];
	$base = $opt['base_url'];
	$url  = static function ( $n ) use ( $key, $base, $opt ) {
		if ( $opt['canvas'] ) {
			return '#';
		}
		$param = brik_listing_param( $key, 'page' );
		$from  = remove_query_arg( $param, $base );
		return 1 === (int) $n ? $from : add_query_arg( $param, (int) $n, $from );
	};

	if ( 'carousel' === $layout && brik_form_bool( $get( 'arrows', true ) ) ) {
		$btn  = brik_button_class( 'outline', 'icon', 'pointer-events-auto size-10 rounded-full bg-background/95 shadow-md backdrop-blur transition-opacity' );
		$html = '<div class="relative">' . $html
			. '<div class="brik-products-arrows pointer-events-none absolute inset-x-0 top-[38%] z-20 hidden -translate-y-1/2 justify-between sm:flex">'
			. '<button type="button" class="' . esc_attr( $btn . ' -ml-5' ) . '" data-brik-products-prev aria-label="' . esc_attr__( 'Previous products', 'brik-builder' ) . '">' . brik_icon( 'chevron-left' ) . '</button>'
			. '<button type="button" class="' . esc_attr( $btn . ' -mr-5' ) . '" data-brik-products-next aria-label="' . esc_attr__( 'Next products', 'brik-builder' ) . '">' . brik_icon( 'chevron-right' ) . '</button>'
			. '</div></div>';
	}

	if ( 'numbered' === $pagination ) {
		$html .= brik_site_pagination( $page, $pages, $url );
	} elseif ( in_array( $pagination, array( 'load_more', 'infinite' ), true ) && $page < $pages ) {
		$text  = '' !== trim( (string) $get( 'load_more_text' ) ) ? $get( 'load_more_text' ) : __( 'Load more', 'brik-builder' );
		/* translators: 1: products shown, 2: total products */
		$shown = sprintf( __( 'Showing %1$d of %2$d', 'brik-builder' ), min( $total, $page * $ppp ), $total );
		$html .= '<div class="brik-products-more flex flex-col items-center gap-3">'
			. '<p class="text-xs text-muted-foreground tabular-nums">' . esc_html( $shown ) . '</p>'
			. '<div class="h-1 w-40 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-foreground/70" style="width:' . esc_attr( round( min( 1, $page * $ppp / max( 1, $total ) ) * 100, 2 ) ) . '%"></div></div>'
			. '<a class="' . esc_attr( brik_button_class( 'outline', 'lg', 'group/more mt-1 min-w-40' ) ) . '" href="' . esc_url( $url( $page + 1 ) ) . '" data-brik-products-more data-page="' . (int) ( $page + 1 ) . '">'
			. brik_icon( 'loader-circle', 'hidden size-4 animate-spin group-aria-busy/more:block' )
			. '<span>' . brik_inline( $text ) . '</span></a></div>';
	}

	return array(
		'html'     => $html,
		'total'    => $total,
		'pages'    => $pages,
		'page'     => $page,
		'per_page' => $ppp,
	);
}

/* -------------------------------------------------------------------------
 * REST: POST brik/v1/products, GET brik/v1/quick-view/{id}
 * ----------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	static function () {
		if ( ! brik_woo_shop_enabled() ) {
			return;
		}
		register_rest_route(
			Brik\Rest::NS,
			'/products',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'brik_woo_products_rest',
				// Results only contain published, catalog-visible products, like the page itself.
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'node_id' => array(
						'required'          => true,
						'sanitize_callback' => static function ( $v ) {
							return is_string( $v ) && preg_match( '/^[a-z0-9]{4,24}$/', $v ) ? $v : '';
						},
					),
					'page'    => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'current' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
		register_rest_route(
			Brik\Rest::NS,
			'/quick-view/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'brik_woo_quick_view_rest',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}
);

/**
 * Re-render a products module with new filter values or another page. The module and its
 * filters are read from the stored tree; the client only says which module and which values.
 */
function brik_woo_products_rest( WP_REST_Request $r ) {
	$post_id = (int) $r['post_id'];
	$node_id = (string) $r['node_id'];
	$found   = $node_id ? Forms::locate( $post_id, $node_id, 0, array( 'products' ) ) : null;
	if ( ! $found ) {
		return new WP_Error( 'brik_not_found', __( 'These products are no longer available.', 'brik-builder' ), array( 'status' => 404 ) );
	}
	list( $node, $def ) = $found;

	$tree           = Data::get( $post_id );
	$renderer       = new Renderer( $post_id );
	$renderer->root = $tree;
	$attrs          = $renderer->resolve_attrs( $node, $def );
	$key            = brik_listing_key( $attrs['css_id'], $node['id'] );

	$params = array();
	foreach ( is_array( $r['filters'] ) ? $r['filters'] : array() as $k => $v ) {
		$named = is_string( $k ) && ( 0 === strpos( $k, 'bf_' ) || 0 === strpos( $k, 'filter_' ) || in_array( $k, array( 'min_price', 'max_price', 'rating_filter', 'orderby' ), true ) );
		if ( $named && ( is_string( $v ) || is_numeric( $v ) || is_array( $v ) ) ) {
			$params[ $k ] = $v;
		}
	}

	$product = null;
	if ( (int) $r['current'] ) {
		$candidate = wc_get_product( (int) $r['current'] );
		if ( $candidate && 'publish' === get_post_status( $candidate->get_id() ) ) {
			$product = $candidate;
		}
	}
	if ( 'cross_sells' === $attrs['source'] ) {
		brik_woo_ensure_cart();
	}

	$main = null;
	if ( 'current' === $attrs['source'] && is_array( $r['main'] ) ) {
		$main = $r['main'];
	}

	list( $clauses, $state ) = brik_woo_filter_parse( brik_woo_filters_for( $renderer, $tree, $key ), $params, $key );
	unset( $state['orderby'] );

	$base = esc_url_raw( (string) $r['page_url'] );
	$base = $base && wp_validate_redirect( $base, false ) ? $base : ( brik_site_is_layout( $post_id ) ? home_url( '/' ) : (string) get_permalink( $post_id ) );

	$result = brik_woo_products_results(
		$attrs,
		array(
			'key'      => $key,
			'page'     => max( 1, (int) $r['page'] ),
			'clauses'  => $clauses,
			'main'     => $main,
			'product'  => $product,
			'base_url' => $base,
			'filtered' => (bool) $state,
		)
	);
	return new WP_REST_Response( $result );
}

function brik_woo_quick_view_rest( WP_REST_Request $r ) {
	$product = wc_get_product( (int) $r['id'] );
	if ( ! $product || $product->is_type( 'variation' ) || 'publish' !== get_post_status( $product->get_id() ) || ! $product->is_visible() || post_password_required( $product->get_id() ) ) {
		return new WP_Error( 'brik_not_found', __( 'This product is not available.', 'brik-builder' ), array( 'status' => 404 ) );
	}
	brik_woo_ensure_cart();
	return new WP_REST_Response(
		array(
			'id'    => $product->get_id(),
			'title' => wp_strip_all_tags( $product->get_name() ),
			'html'  => brik_woo_quick_view_html( $product ),
		)
	);
}

/**
 * Quick view body: gallery, price, short description and the product's real add-to-cart form.
 */
function brik_woo_quick_view_html( WC_Product $product ) {
	global $post;
	$prev_post    = $post;
	$prev_product = isset( $GLOBALS['product'] ) ? $GLOBALS['product'] : null;
	$post         = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$GLOBALS['product'] = $product;

	$name   = wp_strip_all_tags( $product->get_name() );
	$images = array_values( array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) ) );
	$main   = $images
		? wp_get_attachment_image( $images[0], 'woocommerce_single', false, array( 'class' => 'size-full object-cover', 'alt' => $name, 'data-qv-main' => '' ) )
		: '<span class="grid size-full place-items-center text-muted-foreground/40">' . brik_icon( 'image', 'size-12' ) . '</span>';
	$thumbs = '';
	if ( count( $images ) > 1 ) {
		foreach ( array_slice( $images, 0, 6 ) as $i => $id ) {
			$full    = wp_get_attachment_image_src( $id, 'woocommerce_single' );
			$thumbs .= '<button type="button" class="brik-qv-thumb relative aspect-square overflow-hidden rounded-md border bg-muted outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50" data-qv-thumb="' . esc_url( $full ? $full[0] : '' ) . '" aria-label="' . esc_attr( sprintf( /* translators: %d: image number */ __( 'Show image %d', 'brik-builder' ), $i + 1 ) ) . '"' . ( 0 === $i ? ' aria-current="true"' : '' ) . '>'
				. wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail', false, array( 'class' => 'size-full object-cover', 'alt' => '' ) ) . '</button>';
		}
		$thumbs = '<div class="brik-qv-thumbs grid grid-cols-5 gap-2">' . $thumbs . '</div>';
	}

	$term   = brik_woo_primary_category( $product );
	$rating = '';
	if ( wc_review_ratings_enabled() && $product->get_rating_count() > 0 ) {
		/* translators: %d: number of reviews */
		$rating = '<div class="flex items-center gap-2">' . brik_stars( (float) $product->get_average_rating(), 5, 'size-4' ) . '<span class="text-sm text-muted-foreground">' . esc_html( sprintf( _n( '%d review', '%d reviews', $product->get_review_count(), 'brik-builder' ), $product->get_review_count() ) ) . '</span></div>';
	}
	$short = $product->get_short_description();
	$short = '' !== $short ? '<div class="brik-qv-desc text-sm leading-relaxed text-muted-foreground [&_p:not(:last-child)]:mb-3">' . apply_filters( 'woocommerce_short_description', $short ) . '</div>' : ''; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals

	ob_start();
	woocommerce_template_single_add_to_cart();
	$form = ob_get_clean();

	$post = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	if ( $prev_post instanceof WP_Post ) {
		setup_postdata( $prev_post );
	} else {
		wp_reset_postdata();
	}
	$GLOBALS['product'] = $prev_product;

	return '<div class="brik-qv grid gap-6 md:grid-cols-2 md:gap-8" data-product-id="' . (int) $product->get_id() . '">'
		. '<div class="grid content-start gap-2"><div class="brik-qv-media relative aspect-square overflow-hidden rounded-lg bg-muted">' . $main . brik_woo_badges( $product, 0 ) . '</div>' . $thumbs . '</div>'
		. '<div class="flex min-w-0 flex-col gap-4">'
		. ( $term ? '<p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">' . esc_html( $term->name ) . '</p>' : '' )
		. '<h2 class="brik-qv-title -mt-2 font-heading text-2xl leading-tight font-semibold tracking-tight" id="brik-qv-title">' . esc_html( $name ) . '</h2>'
		. $rating
		. '<div class="brik-woo-price text-xl font-semibold tabular-nums">' . $product->get_price_html() . '</div>'
		. $short
		. '<div class="brik-qv-form brik-woo">' . $form . '</div>'
		. '<a class="mt-auto inline-flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'View full details', 'brik-builder' ) . brik_icon( 'arrow-right', 'size-3.5' ) . '</a>'
		. '</div></div>';
}

/* -------------------------------------------------------------------------
 * Mini cart and fragments.
 * ----------------------------------------------------------------------- */

/**
 * Smallest "free shipping from" amount configured in the shipping zones, or 0.
 */
function brik_woo_free_shipping_threshold() {
	static $min = null;
	if ( null !== $min ) {
		return $min;
	}
	$min = 0.0;
	if ( brik_woo_shop_enabled() && class_exists( 'WC_Shipping_Zones' ) ) {
		$zones   = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'shipping_methods' => ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) );
		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				if ( 'free_shipping' === $method->id && 'yes' === $method->enabled && in_array( $method->requires, array( 'min_amount', 'either' ), true ) && (float) $method->min_amount > 0 ) {
					$min = $min > 0 ? min( $min, (float) $method->min_amount ) : (float) $method->min_amount;
				}
			}
		}
	}
	$min = (float) apply_filters( 'brik/woo_free_shipping_threshold', $min );
	return $min;
}

/**
 * Cart amount that counts towards free shipping.
 */
function brik_woo_cart_amount() {
	if ( ! WC()->cart ) {
		return 0.0;
	}
	return max( 0, (float) WC()->cart->get_displayed_subtotal() - (float) WC()->cart->get_discount_total() - ( WC()->cart->display_prices_including_tax() ? (float) WC()->cart->get_discount_tax() : 0 ) );
}

function brik_woo_shipping_bar( $amount, $threshold ) {
	if ( $threshold <= 0 ) {
		return '';
	}
	$pct  = min( 100, round( $amount / $threshold * 100, 1 ) );
	$left = $threshold - $amount;
	if ( $left > 0 ) {
		/* translators: %s: amount left to spend */
		$text = sprintf( esc_html__( 'Add %s more for free shipping', 'brik-builder' ), '<strong class="font-semibold text-foreground">' . wp_strip_all_tags( wc_price( $left ) ) . '</strong>' );
		$icon = 'truck';
	} else {
		$text = esc_html__( 'You’ve unlocked free shipping', 'brik-builder' );
		$icon = 'circle-check';
	}
	return '<div class="brik-mc-ship grid gap-2" data-threshold="' . esc_attr( $threshold ) . '">'
		. '<p class="flex items-center gap-2 text-sm text-muted-foreground">' . brik_icon( $icon, 'size-4 shrink-0 ' . ( $left > 0 ? '' : 'text-emerald-600 dark:text-emerald-400' ) ) . '<span>' . $text . '</span></p>'
		. '<div class="h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( $pct ) . '"><div class="brik-mc-ship-fill h-full rounded-full bg-primary transition-[width] duration-500" style="width:' . esc_attr( $pct ) . '%"></div></div>'
		. '</div>';
}

function brik_woo_cart_count() {
	return WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function brik_woo_mc_count_html() {
	$n = brik_woo_cart_count();
	return '<span class="brik-mc-count" data-count="' . $n . '">' . ( $n > 99 ? '99+' : $n ) . '</span>';
}

function brik_woo_mc_total_html() {
	return '<span class="brik-mc-total tabular-nums">' . ( WC()->cart ? wp_kses_post( WC()->cart->get_cart_subtotal() ) : '' ) . '</span>';
}

/**
 * Quantity stepper around a number input.
 */
function brik_woo_qty_stepper( $value, $max, $name, $label, $extra = '' ) {
	$max = (int) $max;
	return '<div class="' . esc_attr( brik_cls( 'brik-qty inline-flex h-8 items-center rounded-md border border-input bg-background shadow-xs dark:bg-input/30', $extra ) ) . '" data-brik-qty>'
		. '<button type="button" class="grid size-8 place-items-center rounded-l-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-40" data-qty-step="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'brik-builder' ) . '">' . brik_icon( 'minus', 'size-3.5' ) . '</button>'
		. '<input' . brik_attrs(
			array(
				'type'       => 'number',
				'class'      => 'brik-qty-input h-full w-9 border-x border-input bg-transparent text-center text-sm font-medium tabular-nums outline-none focus-visible:bg-accent',
				'name'       => $name,
				'value'      => (int) $value,
				'min'        => 0,
				'max'        => $max > 0 ? $max : null,
				'step'       => 1,
				'inputmode'  => 'numeric',
				'aria-label' => $label,
			)
		) . '>'
		. '<button type="button" class="grid size-8 place-items-center rounded-r-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-40" data-qty-step="1" aria-label="' . esc_attr__( 'Increase quantity', 'brik-builder' ) . '"' . ( $max > 0 && $value >= $max ? ' disabled' : '' ) . '>' . brik_icon( 'plus', 'size-3.5' ) . '</button>'
		. '</div>';
}

/**
 * Cross-sell suggestions for the drawer.
 */
function brik_woo_mc_cross_sells( $limit = 3 ) {
	if ( ! WC()->cart || WC()->cart->is_empty() ) {
		return '';
	}
	$in_cart = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$in_cart[] = (int) $item['product_id'];
	}
	$rows = '';
	$n    = 0;
	foreach ( WC()->cart->get_cross_sells() as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p || in_array( (int) $id, $in_cart, true ) || ! $p->is_visible() || ! $p->is_in_stock() ) {
			continue;
		}
		$rows .= '<li class="relative flex items-center gap-3">'
			. '<span class="size-12 shrink-0 overflow-hidden rounded-md border bg-muted">' . $p->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'size-full object-cover', 'alt' => '' ) ) . '</span>'
			. '<span class="grid min-w-0 flex-1"><a class="truncate text-sm font-medium after:absolute after:inset-0" href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( wp_strip_all_tags( $p->get_name() ) ) . '</a><span class="brik-woo-price text-xs text-muted-foreground tabular-nums">' . $p->get_price_html() . '</span></span>'
			. brik_woo_add_to_cart_button( $p, 'icon', 'size-8' )
			. '</li>';
		if ( ++$n >= $limit ) {
			break;
		}
	}
	return '' !== $rows ? '<div class="brik-mc-xsell grid gap-3 border-t bg-muted/40 px-5 py-4"><p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">' . esc_html__( 'You may also like', 'brik-builder' ) . '</p><ul class="grid gap-3">' . $rows . '</ul></div>' : '';
}

/**
 * Drawer body: items, free-shipping progress, cross-sells, subtotal and buttons. Sent as a
 * cart fragment, so it is the same for every mini cart on the page; per-module options are
 * applied with data attributes on the module (see woo-shop.js).
 */
function brik_woo_mc_content_html( $threshold = null ) {
	if ( ! WC()->cart ) {
		return '<div class="brik-mc-content flex min-h-0 flex-1 flex-col"></div>';
	}
	$cart      = WC()->cart;
	$threshold = null === $threshold ? brik_woo_free_shipping_threshold() : (float) $threshold;
	$amount    = brik_woo_cart_amount();
	$attrs     = ' data-amount="' . esc_attr( $amount ) . '" data-count="' . (int) $cart->get_cart_contents_count() . '" data-nonce="' . esc_attr( wp_create_nonce( 'brik-woo-cart' ) ) . '"';

	if ( $cart->is_empty() ) {
		$shop = wc_get_page_permalink( 'shop' );
		return '<div class="brik-mc-content flex min-h-0 flex-1 flex-col"' . $attrs . '>'
			. '<div class="brik-mc-empty m-auto flex flex-col items-center gap-3 px-6 py-16 text-center">'
			. '<span class="grid size-14 place-items-center rounded-full bg-muted text-muted-foreground">' . brik_icon( 'shopping-bag', 'size-6' ) . '</span>'
			. '<p class="font-medium">' . esc_html__( 'Your cart is empty', 'brik-builder' ) . '</p>'
			. '<p class="text-sm text-muted-foreground">' . esc_html__( 'Looks like you haven’t added anything yet.', 'brik-builder' ) . '</p>'
			. '<a class="' . esc_attr( brik_button_class( 'default', 'default', 'mt-2' ) ) . '" href="' . esc_url( $shop ? $shop : home_url( '/' ) ) . '" data-brik-close>' . esc_html__( 'Continue shopping', 'brik-builder' ) . '</a>'
			. '</div></div>';
	}

	$items = '';
	foreach ( $cart->get_cart() as $key => $item ) {
		$product = apply_filters( 'woocommerce_cart_item_product', $item['data'], $item, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		if ( ! $product || ! $product->exists() || $item['quantity'] <= 0 || ! apply_filters( 'woocommerce_widget_cart_item_visible', true, $item, $key ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
			continue;
		}
		$name  = apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $item, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$link  = apply_filters( 'woocommerce_cart_item_permalink', $product->is_visible() ? $product->get_permalink( $item ) : '', $item, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$thumb = apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'size-full object-cover' ) ), $item, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$price = apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $product, $item['quantity'] ), $item, $key ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$meta  = trim( wc_get_formatted_cart_item_data( $item, true ) );
		$meta  = '' !== $meta ? implode( ' · ', array_map( 'trim', explode( "\n", $meta ) ) ) : '';
		$max   = $product->is_sold_individually() ? 1 : $product->get_max_purchase_quantity();
		$title = wp_strip_all_tags( $name );

		$qty = $product->is_sold_individually()
			? '<span class="text-xs text-muted-foreground">' . esc_html__( 'Qty 1', 'brik-builder' ) . '</span>'
			/* translators: %s: product name */
			: brik_woo_qty_stepper( $item['quantity'], $max, '', sprintf( __( 'Quantity of %s', 'brik-builder' ), $title ) );

		$items .= '<li class="brik-mc-item flex gap-4 py-4" data-key="' . esc_attr( $key ) . '">'
			. ( $link ? '<a class="brik-mc-thumb size-20 shrink-0 overflow-hidden rounded-lg border bg-muted" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>' : '<span class="brik-mc-thumb size-20 shrink-0 overflow-hidden rounded-lg border bg-muted">' . $thumb . '</span>' )
			. '<div class="flex min-w-0 flex-1 flex-col gap-1">'
			. '<div class="flex items-start justify-between gap-3">'
			. '<p class="line-clamp-2 text-sm leading-snug font-medium">' . ( $link ? '<a class="hover:underline" href="' . esc_url( $link ) . '">' . wp_kses_post( $name ) . '</a>' : wp_kses_post( $name ) ) . '</p>'
			. '<span class="brik-woo-price shrink-0 text-sm font-medium tabular-nums">' . wp_kses_post( $price ) . '</span></div>'
			. ( '' !== $meta ? '<p class="text-xs text-muted-foreground">' . esc_html( $meta ) . '</p>' : '' )
			. '<div class="mt-auto flex items-center justify-between gap-2 pt-1">' . $qty
			. '<a class="inline-flex items-center gap-1 rounded-sm text-xs text-muted-foreground transition-colors outline-none hover:text-destructive focus-visible:ring-[3px] focus-visible:ring-ring/50" href="' . esc_url( wc_get_cart_remove_url( $key ) ) . '" data-brik-mc-remove aria-label="' . esc_attr( sprintf( /* translators: %s: product name */ __( 'Remove %s from cart', 'brik-builder' ), $title ) ) . '">' . brik_icon( 'trash-2', 'size-3.5' ) . '<span>' . esc_html__( 'Remove', 'brik-builder' ) . '</span></a>'
			. '</div></div></li>';
	}

	$foot = '<div class="brik-mc-foot grid gap-3 border-t px-5 py-5">'
		. '<div class="flex items-center justify-between text-base font-semibold"><span>' . esc_html__( 'Subtotal', 'brik-builder' ) . '</span><span class="tabular-nums">' . wp_kses_post( $cart->get_cart_subtotal() ) . '</span></div>'
		. '<p class="-mt-1 text-xs text-muted-foreground">' . esc_html__( 'Shipping and taxes are calculated at checkout.', 'brik-builder' ) . '</p>'
		. '<div class="grid grid-cols-2 gap-2 pt-1">'
		. '<a class="' . esc_attr( brik_button_class( 'outline', 'lg' ) ) . '" href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'View cart', 'brik-builder' ) . '</a>'
		. '<a class="' . esc_attr( brik_button_class( 'default', 'lg' ) ) . '" href="' . esc_url( wc_get_checkout_url() ) . '">' . esc_html__( 'Checkout', 'brik-builder' ) . '</a>'
		. '</div></div>';

	$ship = brik_woo_shipping_bar( $amount, $threshold );
	return '<div class="brik-mc-content flex min-h-0 flex-1 flex-col"' . $attrs . '>'
		. '<div class="brik-mc-ship-slot border-b px-5 py-4' . ( '' === $ship ? ' hidden' : '' ) . '">' . $ship . '</div>'
		. '<ul class="brik-mc-items min-h-0 flex-1 divide-y overflow-y-auto overscroll-contain px-5" aria-label="' . esc_attr__( 'Cart items', 'brik-builder' ) . '">' . $items . '</ul>'
		. brik_woo_mc_cross_sells()
		. $foot
		. '</div>';
}

add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		if ( ! brik_woo_shop_enabled() || ! is_array( $fragments ) ) {
			return $fragments;
		}
		$fragments['span.brik-mc-count']   = brik_woo_mc_count_html();
		$fragments['span.brik-mc-total']   = brik_woo_mc_total_html();
		$fragments['div.brik-mc-content']  = brik_woo_mc_content_html();
		return $fragments;
	}
);

/**
 * Fragments and cart hash, the same payload WooCommerce's own cart AJAX returns.
 */
function brik_woo_fragments() {
	ob_start();
	woocommerce_mini_cart();
	$mini = ob_get_clean();
	return array(
		'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array( 'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>' ) ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		'cart_hash' => WC()->cart->get_cart_hash(),
	);
}

/**
 * Error notices added while running a callback, as plain text. Other notices queued for the
 * shopper are left as they were.
 */
function brik_woo_collect_errors( callable $run ) {
	$before = WC()->session ? wc_get_notices() : array();
	wc_clear_notices();
	call_user_func( $run );
	$errors = array();
	foreach ( wc_get_notices( 'error' ) as $notice ) {
		$errors[] = trim( wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : (string) $notice ) );
	}
	wc_clear_notices();
	if ( $before ) {
		WC()->session->set( 'wc_notices', $before );
	}
	return array_values( array_filter( $errors ) );
}

/**
 * wc-ajax=brik_add_to_cart: adds any product type through WooCommerce's own form handler
 * (validation, variations, grouped products and extensions all apply) and answers with
 * fresh fragments. Like WooCommerce's add_to_cart endpoint it needs no nonce: it only ever
 * changes the visitor's own cart.
 */
function brik_woo_ajax_add_to_cart() {
	if ( ! brik_woo_shop_enabled() || ! WC()->cart ) {
		wp_send_json( array( 'error' => true ), 400 );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	// phpcs:enable
	$product = $id ? wc_get_product( $id ) : null;
	if ( ! $product ) {
		wp_send_json( array( 'error' => true, 'message' => __( 'This product is not available.', 'brik-builder' ) ), 404 );
	}
	// A posted add-to-cart form was already handled by WooCommerce on wp_loaded; adding it
	// again here would put the item in the cart twice. Only report the outcome.
	if ( isset( $_REQUEST['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notices = wc_get_notices( 'error' );
		wc_clear_notices();
		if ( $notices ) {
			$messages = array();
			foreach ( $notices as $notice ) {
				$messages[] = wp_strip_all_tags( is_array( $notice ) ? $notice['notice'] : $notice );
			}
			wp_send_json(
				array(
					'error'       => true,
					'message'     => implode( ' ', $messages ),
					'product_url' => $product->get_permalink(),
				)
			);
		}
		do_action( 'woocommerce_ajax_added_to_cart', $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		wp_send_json( brik_woo_fragments() );
	}

	$_REQUEST['add-to-cart'] = (string) $id;
	add_filter( 'woocommerce_add_to_cart_redirect', '__return_false', 999 );
	add_filter( 'pre_option_woocommerce_cart_redirect_after_add', static function () {
		return 'no';
	} );
	$count  = WC()->cart->get_cart_contents_count();
	$errors = brik_woo_collect_errors( array( 'WC_Form_Handler', 'add_to_cart_action' ) );
	if ( $errors || WC()->cart->get_cart_contents_count() === $count ) {
		wp_send_json(
			array(
				'error'       => true,
				'message'     => $errors ? implode( ' ', $errors ) : __( 'Please choose product options before adding this product to your cart.', 'brik-builder' ),
				'product_url' => $product->get_permalink(),
			)
		);
	}
	do_action( 'woocommerce_ajax_added_to_cart', $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	wp_send_json( brik_woo_fragments() );
}
add_action( 'wc_ajax_brik_add_to_cart', 'brik_woo_ajax_add_to_cart' );

/**
 * wc-ajax=brik_cart_qty: change the quantity of a cart line (0 removes it).
 */
function brik_woo_ajax_cart_qty() {
	if ( ! brik_woo_shop_enabled() || ! WC()->cart ) {
		wp_send_json( array( 'error' => true ), 400 );
	}
	if ( ! check_ajax_referer( 'brik-woo-cart', 'nonce', false ) ) {
		wp_send_json( array( 'error' => true, 'code' => 'nonce' ), 403 );
	}
	$key  = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$qty  = isset( $_POST['qty'] ) ? max( 0, (int) wc_stock_amount( wp_unslash( $_POST['qty'] ) ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$item = '' !== $key ? WC()->cart->get_cart_item( $key ) : array();

	$errors = brik_woo_collect_errors(
		static function () use ( $key, $qty, $item ) {
			if ( ! $item ) {
				return;
			}
			if ( 0 === $qty ) {
				WC()->cart->remove_cart_item( $key );
				return;
			}
			$product = $item['data'];
			$max     = $product->is_sold_individually() ? 1 : $product->get_max_purchase_quantity();
			$qty     = $max > 0 ? min( $qty, $max ) : $qty;
			if ( apply_filters( 'woocommerce_update_cart_validation', true, $key, $item, $qty ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
				WC()->cart->set_quantity( $key, $qty, true );
			}
		}
	);
	$out = brik_woo_fragments();
	if ( $errors ) {
		$out['error']   = true;
		$out['message'] = implode( ' ', $errors );
	}
	wp_send_json( $out );
}
add_action( 'wc_ajax_brik_cart_qty', 'brik_woo_ajax_cart_qty' );

/* -------------------------------------------------------------------------
 * Cart, checkout and account screens.
 * ----------------------------------------------------------------------- */

/**
 * Output of a WooCommerce shortcode for a module, or the shortcode itself in the static copy.
 */
function brik_woo_shortcode( $tag, Brik\Context $ctx ) {
	if ( brik_woo_is_static_render( $ctx ) ) {
		return '<!-- brik-woo -->[' . $tag . ']<!-- /brik-woo -->';
	}
	if ( ! brik_woo_ensure_cart() ) {
		return '';
	}
	brik_woo_flag( 'screen' );
	return do_shortcode( '[' . $tag . ']' );
}

/*
 * The static copy keeps the shortcode for WooCommerce and for sites without Brik. When Brik
 * renders the page anyway, don't let the_content run the shortcode a second time.
 */
add_filter(
	'the_content',
	static function ( $content ) {
		if ( false === strpos( $content, '<!-- brik-woo -->' ) ) {
			return $content;
		}
		$id = get_the_ID();
		if ( $id && Data::enabled( $id ) && ( in_the_loop() || is_singular() ) && ! post_password_required( $id ) ) {
			return preg_replace( '#<!-- brik-woo -->.*?<!-- /brik-woo -->#s', '', $content );
		}
		return $content;
	},
	5
);

/*
 * WooCommerce's stylesheets fight the module styles on cart, checkout and account screens,
 * so they are left out of pages where Brik renders those screens.
 */
add_filter(
	'woocommerce_enqueue_styles',
	static function ( $styles ) {
		$flags = brik_woo_flag();
		if ( ! empty( $flags['screen'] ) && is_array( $styles ) ) {
			unset( $styles['woocommerce-general'], $styles['woocommerce-layout'], $styles['woocommerce-smallscreen'] );
		}
		return $styles;
	}
);

// Brik pages and templates that render these screens count as the screens themselves.
add_filter(
	'woocommerce_is_checkout',
	static function ( $is ) {
		$flags = brik_woo_flag();
		return $is || ! empty( $flags['checkout'] );
	}
);
add_filter(
	'woocommerce_is_cart',
	static function ( $is ) {
		$flags = brik_woo_flag();
		return $is || ! empty( $flags['cart'] );
	}
);
add_filter(
	'woocommerce_is_account_page',
	static function ( $is ) {
		$flags = brik_woo_flag();
		return $is || ! empty( $flags['account'] );
	}
);

/**
 * Cards on the account dashboard.
 */
function brik_woo_account_dashboard_cards() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}
	$customer = new WC_Customer( $user_id );
	$orders   = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => 1,
			'paginate'    => true,
			'return'      => 'ids',
		)
	);
	$total    = is_object( $orders ) ? (int) $orders->total : 0;
	$last     = is_object( $orders ) && $orders->orders ? wc_get_order( $orders->orders[0] ) : null;
	$cards    = array();
	$items    = wc_get_account_menu_items();

	if ( isset( $items['orders'] ) ) {
		/* translators: 1: order number, 2: order status */
		$sub     = $last ? sprintf( __( 'Last order #%1$s · %2$s', 'brik-builder' ), $last->get_order_number(), wc_get_order_status_name( $last->get_status() ) ) : __( 'No orders yet', 'brik-builder' );
		$cards[] = array( 'package', __( 'Orders', 'brik-builder' ), (string) $total, $sub, wc_get_account_endpoint_url( 'orders' ) );
	}
	if ( isset( $items['edit-address'] ) ) {
		$city    = trim( $customer->get_billing_city() . ( $customer->get_billing_country() ? ', ' . $customer->get_billing_country() : '' ), ', ' );
		$cards[] = array( 'map-pin', __( 'Addresses', 'brik-builder' ), '' !== $city ? $city : __( 'Not set', 'brik-builder' ), __( 'Billing and shipping', 'brik-builder' ), wc_get_account_endpoint_url( 'edit-address' ) );
	}
	if ( isset( $items['edit-account'] ) ) {
		$cards[] = array( 'user-round', __( 'Account details', 'brik-builder' ), $customer->get_display_name() ? $customer->get_display_name() : $customer->get_email(), $customer->get_email(), wc_get_account_endpoint_url( 'edit-account' ) );
	}
	if ( ! $cards ) {
		return;
	}
	$out = '';
	foreach ( $cards as $c ) {
		$out .= '<a class="brik-acc-card group relative flex flex-col gap-3 rounded-xl border bg-card p-5 text-card-foreground shadow-xs transition-[box-shadow,border-color] hover:border-foreground/15 hover:shadow-md" href="' . esc_url( $c[4] ) . '">'
			. '<span class="flex items-center justify-between"><span class="grid size-9 place-items-center rounded-lg bg-muted text-foreground">' . brik_icon( $c[0], 'size-4' ) . '</span>' . brik_icon( 'arrow-up-right', 'size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5' ) . '</span>'
			. '<span class="grid gap-0.5"><span class="text-sm text-muted-foreground">' . esc_html( $c[1] ) . '</span><span class="truncate text-lg font-semibold tracking-tight">' . esc_html( $c[2] ) . '</span><span class="truncate text-xs text-muted-foreground">' . esc_html( $c[3] ) . '</span></span></a>';
	}
	echo '<div class="brik-acc-cards mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">' . $out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
}

/**
 * Account navigation as vertical tabs. Keeps WooCommerce's classes and menu filters, so
 * extensions that add endpoints show up here too.
 */
function brik_woo_account_nav() {
	$icons = array(
		'dashboard'       => 'layout-dashboard',
		'orders'          => 'package',
		'downloads'       => 'download',
		'edit-address'    => 'map-pin',
		'payment-methods' => 'credit-card',
		'edit-account'    => 'user-round',
		'customer-logout' => 'log-out',
	);
	$out = '';
	foreach ( wc_get_account_menu_items() as $endpoint => $label ) {
		$current = wc_is_current_account_menu_item( $endpoint );
		$out    .= '<li class="' . esc_attr( wc_get_account_menu_item_classes( $endpoint ) ) . '">'
			. '<a class="brik-acc-link" href="' . esc_url( wc_get_account_endpoint_url( $endpoint ) ) . '"' . ( $current ? ' aria-current="page"' : '' ) . '>'
			. brik_icon( isset( $icons[ $endpoint ] ) ? $icons[ $endpoint ] : 'circle-dot', 'size-4 shrink-0' ) . '<span>' . esc_html( $label ) . '</span></a></li>';
	}
	do_action( 'woocommerce_before_account_navigation' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
	echo '<nav class="woocommerce-MyAccount-navigation brik-acc-nav" aria-label="' . esc_attr__( 'Account pages', 'brik-builder' ) . '"><ul>' . $out . '</ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
	do_action( 'woocommerce_after_account_navigation' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
}

/**
 * The order on the order-received endpoint, when the visitor may see it.
 */
function brik_woo_received_order() {
	global $wp;
	if ( empty( $wp->query_vars['order-received'] ) ) {
		return null;
	}
	$order = wc_get_order( absint( $wp->query_vars['order-received'] ) );
	$key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $order || ! is_string( $key ) || ! hash_equals( $order->get_order_key(), $key ) ) {
		return null;
	}
	if ( $order->get_customer_id() && $order->get_customer_id() !== get_current_user_id() ) {
		return null;
	}
	return $order;
}

/**
 * Thank-you header above WooCommerce's order details.
 */
function brik_woo_received_header( WC_Order $order ) {
	$failed = $order->has_status( 'failed' );
	$name   = $order->get_billing_first_name();
	if ( $failed ) {
		$title = __( 'Payment failed', 'brik-builder' );
		$text  = __( 'Your order could not be processed because the payment was declined. Please try again.', 'brik-builder' );
	} else {
		/* translators: %s: customer first name */
		$title = '' !== $name ? sprintf( __( 'Thank you, %s!', 'brik-builder' ), $name ) : __( 'Thank you!', 'brik-builder' );
		/* translators: %s: email address */
		$text = $order->get_billing_email() ? sprintf( __( 'Your order has been received. A confirmation is on its way to %s.', 'brik-builder' ), $order->get_billing_email() ) : __( 'Your order has been received.', 'brik-builder' );
	}
	$facts = array(
		__( 'Order', 'brik-builder' )   => '#' . $order->get_order_number(),
		__( 'Date', 'brik-builder' )    => wc_format_datetime( $order->get_date_created() ),
		__( 'Total', 'brik-builder' )   => wp_strip_all_tags( $order->get_formatted_order_total() ),
		__( 'Payment', 'brik-builder' ) => $order->get_payment_method_title(),
	);
	$list = '';
	foreach ( array_filter( $facts ) as $label => $value ) {
		$list .= '<div class="grid gap-1 px-5 py-4"><dt class="text-xs text-muted-foreground">' . esc_html( $label ) . '</dt><dd class="truncate text-sm font-semibold tabular-nums">' . esc_html( $value ) . '</dd></div>';
	}
	$actions = $failed
		? '<a class="' . esc_attr( brik_button_class( 'default', 'lg' ) ) . '" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Try again', 'brik-builder' ) . '</a>'
		: '<a class="' . esc_attr( brik_button_class( 'default', 'lg' ) ) . '" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'brik-builder' ) . '</a>'
			. ( is_user_logged_in() ? '<a class="' . esc_attr( brik_button_class( 'outline', 'lg' ) ) . '" href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'View order', 'brik-builder' ) . '</a>' : '' );

	return '<div class="brik-or-head flex flex-col items-center gap-4 text-center">'
		. '<span class="' . esc_attr( $failed ? 'grid size-14 place-items-center rounded-full bg-destructive/10 text-destructive' : 'brik-or-check grid size-14 place-items-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' ) . '">' . brik_icon( $failed ? 'circle-x' : 'check', 'size-7' ) . '</span>'
		. '<h2 class="font-heading text-3xl font-semibold tracking-tight text-balance">' . esc_html( $title ) . '</h2>'
		. '<p class="max-w-md text-muted-foreground text-pretty">' . esc_html( $text ) . '</p>'
		. '<dl class="mt-2 grid w-full grid-cols-2 gap-px rounded-xl border bg-border text-left shadow-xs sm:grid-cols-4">' . $list . '</dl>'
		. '<div class="flex flex-wrap justify-center gap-2">' . $actions . '</div>'
		. '</div>';
}

/**
 * Order received screen: the header plus WooCommerce's thank-you template (payment
 * instructions, order details, addresses and every extension hooked into it).
 */
function brik_woo_received_html( WC_Order $order, $details = true ) {
	$out = brik_woo_received_header( $order );
	if ( $details ) {
		ob_start();
		wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
		$out .= '<div class="woocommerce brik-or-details">' . ob_get_clean() . '</div>';
	}
	return $out;
}

/**
 * Whether the checkout being rendered (or refreshed over AJAX) is a Brik checkout module.
 */
function brik_woo_is_brik_checkout() {
	$flags = brik_woo_flag();
	if ( ! empty( $flags['checkout'] ) ) {
		return true;
	}
	// update_order_review posts the serialized checkout form, which carries our marker.
	$data = isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ? wp_unslash( $_POST['post_data'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	return wp_doing_ajax() && false !== strpos( $data, 'brik_checkout=1' );
}

/**
 * Thumbnails with a quantity badge in the order summary of a Brik checkout.
 */
function brik_woo_review_items( $state = null ) {
	static $on = false;
	if ( null !== $state ) {
		$on = (bool) $state;
	}
	return $on;
}
add_action(
	'woocommerce_review_order_before_cart_contents',
	static function () {
		brik_woo_review_items( brik_woo_shop_enabled() && brik_woo_is_brik_checkout() );
	}
);
add_action(
	'woocommerce_review_order_after_cart_contents',
	static function () {
		brik_woo_review_items( false );
	}
);
add_filter(
	'woocommerce_cart_item_name',
	static function ( $name, $item, $key ) {
		if ( ! brik_woo_review_items() || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
			return $name;
		}
		$thumb = $item['data']->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) );
		return '<span class="brik-co-item"><span class="brik-co-thumb" data-qty="' . esc_attr( $item['quantity'] ) . '">' . $thumb . '</span><span class="min-w-0">' . $name . '</span></span>';
	},
	20,
	3
);

/*
 * Front-end settings for woo-shop.js.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! brik_woo_shop_enabled() || ! class_exists( 'WC_AJAX' ) ) {
			return;
		}
		$data = array(
			'wcAjax' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'i18n'   => array(
				'error'       => __( 'Something went wrong. Please try again.', 'brik-builder' ),
				/* translators: %s: amount left to spend */
				'shipLeft'    => __( 'Add %s more for free shipping', 'brik-builder' ),
				'shipDone'    => __( 'You’ve unlocked free shipping', 'brik-builder' ),
				/* translators: %d: number of items */
				'cartOne'     => __( 'Cart, %d item', 'brik-builder' ),
				/* translators: %d: number of items */
				'cartMany'    => __( 'Cart, %d items', 'brik-builder' ),
				'decrease'    => __( 'Decrease quantity', 'brik-builder' ),
				'increase'    => __( 'Increase quantity', 'brik-builder' ),
				'remove'      => __( 'Remove', 'brik-builder' ),
				'close'       => __( 'Close', 'brik-builder' ),
				'showResults' => __( 'Show results', 'brik-builder' ),
			),
		);
		wp_add_inline_script( 'brik', 'window.brikWoo=' . wp_json_encode( $data ) . ';', 'before' );
	},
	20
);
