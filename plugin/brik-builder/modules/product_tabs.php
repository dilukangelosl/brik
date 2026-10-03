<?php
/**
 * Product tabs: description, additional information and reviews (plus tabs other plugins add
 * through woocommerce_product_tabs), as shadcn tabs or an accordion.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Woo\Product;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_active() ) {
	return null;
}

return array(
	'type'        => 'product_tabs',
	'title'       => __( 'Product tabs', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'panel-top',
	'description' => 'Description / Additional information / Reviews of the current product, plus tabs added by plugins (woocommerce_product_tabs). style: tabs|accordion. variant: underline|pills (tabs). list_align: start|center|stretch. panel_style: plain|card. description, additional, reviews: bool show each tab. reviews_summary: bool (rating breakdown next to the reviews). Links to #reviews open the reviews tab. product: optional fixed product id.',
	'fields'      => array_merge(
		array(
			'style'           => Fields::field( 'select', __( 'Style', 'brik-builder' ), 'content', array( 'default' => 'tabs', 'options' => Fields::opts( array( 'tabs' => __( 'Tabs', 'brik-builder' ), 'accordion' => __( 'Accordion', 'brik-builder' ) ) ) ) ),
			'variant'         => Fields::field( 'select', __( 'Tab style', 'brik-builder' ), 'content', array( 'default' => 'underline', 'options' => Fields::opts( array( 'underline' => __( 'Underline', 'brik-builder' ), 'pills' => __( 'Pills', 'brik-builder' ) ) ), 'show_if' => array( 'style' => 'tabs' ) ) ),
			'list_align'      => Fields::field( 'select', __( 'Tab alignment', 'brik-builder' ), 'content', array( 'default' => 'start', 'options' => Fields::opts( array( 'start' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'stretch' => __( 'Full width', 'brik-builder' ) ) ), 'show_if' => array( 'style' => 'tabs' ) ) ),
			'panel_style'     => Fields::field( 'select', __( 'Panel', 'brik-builder' ), 'content', array( 'default' => 'plain', 'options' => Fields::opts( array( 'plain' => __( 'Plain', 'brik-builder' ), 'card' => __( 'Card', 'brik-builder' ) ) ) ) ),
			'description'     => Fields::field( 'toggle', __( 'Description tab', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'additional'      => Fields::field( 'toggle', __( 'Additional information tab', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'reviews'         => Fields::field( 'toggle', __( 'Reviews tab', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'reviews_summary' => Fields::field( 'toggle', __( 'Rating summary', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'reviews' => true ) ) ),
			'open_first'      => Fields::field( 'toggle', __( 'First item open', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'style' => 'accordion' ) ) ),
		),
		Product::field(),
		Fields::typography( 'tab', __( 'Tab text', 'brik-builder' ), Fields::WRAP . ' .brik-ptabs-trigger' ),
		Fields::typography( 'panel', __( 'Panel text', 'brik-builder' ), Fields::WRAP . ' .brik-ptabs-panel' )
	),
	'render'      => static function ( $a, $ctx ) {
		$product = brik_woo_product( $ctx, $a );
		if ( ! $product ) {
			return $ctx->placeholder( __( 'Product tabs', 'brik-builder' ) );
		}

		$tabs = Product::with(
			$product,
			static function () {
				// WooCommerce drops the reviews tab for themes without WooCommerce support,
				// because their comments template can't render it. This module renders reviews itself.
				$cb      = array( 'WC_Template_Loader', 'unsupported_theme_remove_review_tab' );
				$removed = false !== has_filter( 'woocommerce_product_tabs', $cb ) && remove_filter( 'woocommerce_product_tabs', $cb, 10 );
				$tabs    = (array) apply_filters( 'woocommerce_product_tabs', array() );
				if ( $removed ) {
					add_filter( 'woocommerce_product_tabs', $cb, 10 );
				}
				return $tabs;
			}
		);
		$off  = array(
			'description'            => empty( $a['description'] ),
			'additional_information' => empty( $a['additional'] ),
			'reviews'                => empty( $a['reviews'] ),
		);

		$items = array();
		foreach ( $tabs as $key => $tab ) {
			$key = sanitize_key( $key );
			if ( ! is_array( $tab ) || ! empty( $off[ $key ] ) ) {
				continue;
			}
			$title = isset( $tab['title'] ) ? (string) $tab['title'] : $key;
			$count = '';
			switch ( $key ) {
				case 'description':
					$html = brik_woo_description_html( $product );
					$html = '' !== trim( $html ) ? '<div class="brik-product-description brik-prose">' . $html . '</div>' : '';
					break;
				case 'additional_information':
					$html = brik_woo_attributes_table( $product, 'striped' );
					break;
				case 'reviews':
					$title = __( 'Reviews', 'brik-builder' );
					$count = (string) (int) $product->get_review_count();
					$html  = '<div id="reviews" class="brik-reviews">' . Product::reviews_html(
						$product,
						array(
							'summary' => ! empty( $a['reviews_summary'] ),
							'uid'     => $ctx->uid( 'review' ),
							'canvas'  => $ctx->canvas,
						)
					) . '</div>';
					break;
				default:
					$html = isset( $tab['callback'] ) && is_callable( $tab['callback'] )
						? Product::capture(
							$product,
							static function () use ( $tab, $key ) {
								call_user_func( $tab['callback'], $key, $tab );
							}
						)
						: '';
			}
			if ( '' === trim( $html ) ) {
				continue;
			}
			$title   = Product::with(
				$product,
				static function () use ( $title, $key ) {
					return (string) apply_filters( 'woocommerce_product_' . $key . '_tab_title', $title, $key );
				}
			);
			$items[] = array(
				'key'   => $key,
				'title' => wp_strip_all_tags( $title ),
				'count' => $count,
				'html'  => $html,
			);
		}
		if ( ! $items ) {
			return $ctx->placeholder( __( 'This product has nothing to show in tabs yet', 'brik-builder' ) );
		}

		$card  = 'card' === $a['panel_style'];
		$badge = static function ( $count ) {
			return '' !== $count ? '<span class="brik-ptabs-count inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-muted px-1.5 text-xs font-medium text-muted-foreground tabular-nums">' . esc_html( $count ) . '</span>' : '';
		};

		if ( 'accordion' === $a['style'] ) {
			$out  = '';
			$name = $ctx->uid( 'acc' );
			foreach ( $items as $i => $item ) {
				$open = 0 === $i && ! empty( $a['open_first'] );
				$out .= '<details class="brik-ptabs-item group border-b last:border-b-0" name="' . esc_attr( $name ) . '" id="' . esc_attr( 'tab-' . $item['key'] ) . '"' . ( $open ? ' open' : '' ) . '>'
					. '<summary class="brik-ptabs-trigger flex cursor-pointer list-none items-center justify-between gap-4 py-4 text-left text-sm font-medium transition-all outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">'
					. '<span class="inline-flex items-center gap-2">' . esc_html( $item['title'] ) . $badge( $item['count'] ) . '</span>'
					. brik_icon( 'chevron-down', 'size-4 shrink-0 text-muted-foreground transition-transform duration-200 group-open:rotate-180' ) . '</summary>'
					. '<div class="brik-ptabs-panel pb-6">' . $item['html'] . '</div></details>';
			}
			return '<div class="brik-ptabs brik-ptabs--accordion' . ( $card ? ' rounded-xl border bg-card px-6 text-card-foreground shadow-xs' : ' border-t' ) . '">' . $out . '</div>';
		}

		$underline = 'pills' !== $a['variant'];
		$stretch   = 'stretch' === $a['list_align'];
		if ( $underline ) {
			$list    = brik_cls( 'brik-tabs-list brik-ptabs-list flex max-w-full items-center gap-6 overflow-x-auto border-b [scrollbar-width:none]', array( 'justify-center' => 'center' === $a['list_align'] ) );
			$trigger = brik_cls( 'brik-tabs-trigger brik-ptabs-trigger relative inline-flex items-center justify-center gap-2 pt-1 pb-3 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 rounded-t-md after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-full after:bg-foreground after:opacity-0 after:transition-opacity aria-selected:text-foreground aria-selected:after:opacity-100', array( 'flex-1' => $stretch ) );
		} else {
			$list    = brik_cls( 'brik-tabs-list brik-ptabs-list inline-flex h-9 max-w-full items-center rounded-lg bg-muted p-[3px] text-muted-foreground overflow-x-auto [scrollbar-width:none]', $stretch ? 'w-full' : 'w-fit', array( 'self-center' => 'center' === $a['list_align'] ) );
			$trigger = 'brik-tabs-trigger brik-ptabs-trigger relative inline-flex h-[calc(100%-1px)] flex-1 items-center justify-center gap-1.5 rounded-md border border-transparent px-3 py-1 text-sm font-medium whitespace-nowrap text-foreground/60 transition-all outline-none hover:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-selected:bg-background aria-selected:text-foreground aria-selected:shadow-sm dark:text-muted-foreground dark:aria-selected:border-input dark:aria-selected:bg-input/30 dark:aria-selected:text-foreground';
		}
		$panel = $card
			? 'brik-tabs-panel brik-ptabs-panel rounded-xl border bg-card p-6 text-card-foreground shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 md:p-8'
			: 'brik-tabs-panel brik-ptabs-panel pt-2 outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';

		$tabs_html   = '';
		$panels_html = '';
		foreach ( $items as $i => $item ) {
			$tab          = $ctx->uid( 'tab-' . $item['key'] );
			$pane         = 'tab-' . $item['key'];
			$on           = 0 === $i;
			$tabs_html   .= sprintf(
				'<button type="button" role="tab" id="%1$s" class="%2$s" aria-controls="%3$s" aria-selected="%4$s" tabindex="%5$s">%6$s%7$s</button>',
				esc_attr( $tab ),
				esc_attr( $trigger ),
				esc_attr( $pane ),
				$on ? 'true' : 'false',
				$on ? '0' : '-1',
				esc_html( $item['title'] ),
				$badge( $item['count'] )
			);
			$panels_html .= sprintf(
				'<div role="tabpanel" id="%1$s" class="%2$s" aria-labelledby="%3$s" tabindex="0"%4$s>%5$s</div>',
				esc_attr( $pane ),
				esc_attr( $panel ),
				esc_attr( $tab ),
				$on ? '' : ' hidden',
				$item['html']
			);
		}
		return '<div class="brik-ptabs brik-ptabs--tabs flex flex-col gap-6" data-brik-tabs data-orientation="horizontal"><div role="tablist" class="' . esc_attr( $list ) . '" aria-orientation="horizontal">' . $tabs_html . '</div>' . $panels_html . '</div>';
	},
);
