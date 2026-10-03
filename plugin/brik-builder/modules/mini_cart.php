<?php
/**
 * Mini cart: a header cart button with a live count that opens a slide-out drawer or a
 * dropdown with the cart lines, free-shipping progress and checkout buttons.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'mini_cart',
	'title'       => __( 'Mini cart', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'shopping-cart',
	'description' => 'Cart button for headers: icon with a live item count (and optional total) that opens the cart. mode: drawer (slide-out sheet)|dropdown|link (straight to the cart page). icon: shopping-bag|shopping-cart|shopping-basket. show_total, label (text next to the icon), side right|left (drawer). '
		. 'Drawer: line items with quantity steppers and remove, free_shipping bar (threshold: empty = taken from the free shipping method in the shipping zones, 0 = off), cross_sells suggestions, View cart / Checkout buttons. auto_open: open after a product is added. Updates live through WooCommerce cart fragments.',
	'fields'      => array_merge(
		array(
			'mode'          => Fields::field( 'select', __( 'Opens', 'brik-builder' ), 'content', array( 'default' => 'drawer', 'options' => Fields::opts( array( 'drawer' => __( 'Slide-out drawer', 'brik-builder' ), 'dropdown' => __( 'Dropdown', 'brik-builder' ), 'link' => __( 'Cart page (link)', 'brik-builder' ) ) ) ) ),
			'side'          => Fields::field( 'select', __( 'Drawer side', 'brik-builder' ), 'content', array( 'default' => 'right', 'options' => Fields::opts( array( 'right' => __( 'Right', 'brik-builder' ), 'left' => __( 'Left', 'brik-builder' ) ) ), 'show_if' => array( 'mode' => 'drawer' ) ) ),
			'icon'          => Fields::field( 'select', __( 'Icon', 'brik-builder' ), 'content', array( 'default' => 'shopping-bag', 'options' => Fields::opts( array( 'shopping-bag' => __( 'Bag', 'brik-builder' ), 'shopping-cart' => __( 'Cart', 'brik-builder' ), 'shopping-basket' => __( 'Basket', 'brik-builder' ) ) ) ) ),
			'label'         => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'placeholder' => __( 'Cart', 'brik-builder' ) ) ),
			'show_total'    => Fields::field( 'toggle', __( 'Show cart total', 'brik-builder' ), 'content' ),
			'button_style'  => Fields::field( 'select', __( 'Button style', 'brik-builder' ), 'content', array( 'default' => 'ghost', 'options' => Fields::opts( brik_button_variants_labels() ) ) ),
			'title'         => Fields::field( 'text', __( 'Drawer title', 'brik-builder' ), 'content', array( 'default' => __( 'Your cart', 'brik-builder' ), 'show_if' => array( 'mode' => array( 'drawer', 'dropdown' ) ) ) ),
			'free_shipping' => Fields::field( 'toggle', __( 'Free shipping progress', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'mode' => array( 'drawer', 'dropdown' ) ) ) ),
			'threshold'     => Fields::field( 'number', __( 'Free shipping from', 'brik-builder' ), 'content', array( 'min' => 0, 'description' => __( 'Leave empty to use the free shipping method of your shipping zones.', 'brik-builder' ), 'show_if' => array( 'free_shipping' => true ) ) ),
			'cross_sells'   => Fields::field( 'toggle', __( 'Suggest cross-sells', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'mode' => array( 'drawer', 'dropdown' ) ) ) ),
			'auto_open'     => Fields::field( 'toggle', __( 'Open after adding to cart', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'mode' => array( 'drawer', 'dropdown' ) ) ) ),
			'align'         => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::box( 'trigger', __( 'Cart button', 'brik-builder' ), Fields::WRAP . ' .brik-mc-trigger', array( 'bg', 'color', 'border_color', 'radius' ) ),
		array(
			'badge_bg'     => Fields::field( 'color', __( 'Count background', 'brik-builder' ), 'badge', array( 'tab' => 'design', 'group_label' => __( 'Count badge', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-mc-count', 'background-color' ) ) ),
			'badge_color'  => Fields::field( 'color', __( 'Count text', 'brik-builder' ), 'badge', array( 'tab' => 'design', 'group_label' => __( 'Count badge', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-mc-count', 'color' ) ) ),
			'drawer_width' => Fields::field( 'unit', __( 'Drawer width', 'brik-builder' ), 'panel', array( 'tab' => 'design', 'group_label' => __( 'Drawer', 'brik-builder' ), 'placeholder' => '28rem', 'css' => array( Fields::WRAP . ' .brik-mc-drawer', 'max-width' ) ) ),
		)
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		brik_woo_ensure_cart();
		$mode  = in_array( $a['mode'], array( 'drawer', 'dropdown', 'link' ), true ) ? $a['mode'] : 'drawer';
		$id    = $ctx->uid( 'cart' );
		$icon  = in_array( $a['icon'], array( 'shopping-bag', 'shopping-cart', 'shopping-basket' ), true ) ? $a['icon'] : 'shopping-bag';
		$label = trim( (string) $a['label'] );
		$count = brik_woo_cart_count();

		$custom    = is_numeric( $a['threshold'] ) ? max( 0, (float) $a['threshold'] ) : null;
		$threshold = brik_form_bool( $a['free_shipping'] ) ? ( null !== $custom ? $custom : brik_woo_free_shipping_threshold() ) : 0;

		$ctx->attrs['data-brik-mini-cart'] = $mode;
		$ctx->attrs['data-threshold']      = (string) $threshold;
		$ctx->attrs['data-auto-open']      = brik_form_bool( $a['auto_open'] ) ? '1' : '0';
		$ctx->attrs['data-price']          = wp_json_encode(
			array(
				'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'decimals' => wc_get_price_decimals(),
				'dec'      => wc_get_price_decimal_separator(),
				'thousand' => wc_get_price_thousand_separator(),
			)
		);
		if ( ! brik_form_bool( $a['cross_sells'] ) ) {
			$ctx->classes[] = 'brik-mc--no-xsell';
		}

		/* translators: %d: number of items in the cart */
		$aria    = sprintf( _n( 'Cart, %d item', 'Cart, %d items', $count, 'brik-builder' ), $count );
		$variant = isset( brik_button_variants_labels()[ $a['button_style'] ] ) ? $a['button_style'] : 'ghost';
		$size    = '' === $label && ! brik_form_bool( $a['show_total'] ) ? 'icon' : 'default';
		$trigger = '<a' . brik_attrs(
			array(
				'href'          => wc_get_cart_url(),
				'class'         => brik_button_class( $variant, $size, 'brik-mc-trigger relative overflow-visible' ),
				'aria-label'    => $aria,
				'data-mc-open'  => 'link' === $mode ? null : $id,
				'aria-haspopup' => 'link' === $mode ? null : 'dialog',
				'aria-expanded' => 'dropdown' === $mode ? 'false' : null,
				'aria-controls' => 'link' === $mode ? null : $id,
			)
		) . '>'
			. '<span class="relative inline-flex">' . brik_icon( $icon, 'size-5' ) . brik_woo_mc_count_html() . '</span>'
			. ( '' !== $label ? '<span class="brik-mc-label"' . $ctx->inline( 'label' ) . '>' . brik_inline( $label ) . '</span>' : '' )
			. ( brik_form_bool( $a['show_total'] ) ? brik_woo_mc_total_html() : '' )
			. '</a>';

		if ( 'link' === $mode ) {
			return $trigger;
		}

		$title   = '' !== trim( (string) $a['title'] ) ? $a['title'] : __( 'Your cart', 'brik-builder' );
		$content = brik_woo_mc_content_html( $threshold );
		$head    = '<div class="brik-mc-head flex items-center gap-2 border-b px-5 py-4">'
			. '<h2 class="text-base font-semibold" id="' . esc_attr( $id . '-title' ) . '">' . brik_inline( $title ) . '</h2>'
			. '<span class="brik-mc-head-count rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums" data-mc-head-count>' . (int) $count . '</span>'
			. '<button type="button" class="ml-auto inline-flex size-8 items-center justify-center rounded-md opacity-70 transition-opacity outline-none hover:bg-accent hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50" data-brik-close data-mc-close aria-label="' . esc_attr__( 'Close cart', 'brik-builder' ) . '">' . brik_icon( 'x' ) . '</button>'
			. '</div>';

		if ( 'dropdown' === $mode ) {
			return '<div class="brik-mc-anchor relative inline-block text-left">' . $trigger
				. '<div class="brik-mc-dropdown" id="' . esc_attr( $id ) . '" role="dialog" aria-labelledby="' . esc_attr( $id . '-title' ) . '" hidden>'
				. '<div class="brik-mc-panel flex max-h-[min(36rem,80vh)] flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-xl">' . $head . $content . '</div>'
				. '</div></div>';
		}

		$side = 'left' === $a['side'] ? 'left' : 'right';
		return $trigger
			. '<dialog class="' . esc_attr( 'brik-dialog brik-dialog--sheet brik-dialog--' . $side . ' brik-mc-drawer' ) . '" id="' . esc_attr( $id ) . '" aria-labelledby="' . esc_attr( $id . '-title' ) . '" data-backdrop-close>'
			. '<div class="brik-mc-panel flex h-full flex-col bg-background text-foreground shadow-xl">' . $head . $content . '</div>'
			. '</dialog>';
	},
);
