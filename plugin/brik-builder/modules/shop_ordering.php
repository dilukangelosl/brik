<?php
/**
 * Shop ordering: the "Sort by" dropdown for a products module or WooCommerce's shop query.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'shop_ordering',
	'title'       => __( 'Sort by', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'arrow-down-wide-narrow',
	'description' => 'Sorting dropdown (popularity, rating, newest, price). target: css_id of a products module (sorts it over AJAX, ?bf_{target}_orderby); empty = WooCommerce\'s own ?orderby on shop and category archives (other query parameters are kept). label: text before the dropdown.',
	'fields'      => array(
		'target' => Fields::field( 'text', __( 'Products CSS id', 'brik-builder' ), 'content', array( 'placeholder' => 'shop' ) ),
		'label'  => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'default' => __( 'Sort by', 'brik-builder' ) ) ),
		'align'  => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-so', 'map' => array( 'left' => 'justify-content:flex-start', 'center' => 'justify-content:center', 'right' => 'justify-content:flex-end' ) ) ) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$key    = brik_listing_key( $a['target'], '' );
		$name   = '' !== $key ? brik_listing_param( $key, 'orderby' ) : 'orderby';
		$params = $ctx->canvas ? array() : brik_woo_request_params();
		$value  = isset( $params[ $name ] ) && is_string( $params[ $name ] ) ? $params[ $name ] : ( '' === $key && isset( $params['orderby'] ) && is_string( $params['orderby'] ) ? $params['orderby'] : '' );
		$id     = $ctx->uid( 'sort' );

		$opts = '';
		foreach ( brik_woo_sort_options() as $v => $o ) {
			$opts .= '<option value="' . esc_attr( 'menu_order' === $v ? '' : $v ) . '"' . selected( $value, $v, false ) . '>' . esc_html( $o[0] ) . '</option>';
		}

		$hidden = '';
		$action = '';
		if ( ! $ctx->canvas ) {
			$current = brik_listing_current_url();
			$action  = strtok( $current, '?' );
			$action  = preg_replace( '#/page/\d+/?$#', '/', $action );
			parse_str( (string) wp_parse_url( $current, PHP_URL_QUERY ), $vars );
			foreach ( $vars as $k => $v ) {
				if ( is_string( $k ) && is_string( $v ) && $k !== $name && 'paged' !== $k && ( '' === $key || brik_listing_param( $key, 'page' ) !== $k ) ) {
					$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
				}
			}
		}

		$label = '' !== trim( (string) $a['label'] ) ? '<label class="shrink-0 text-sm whitespace-nowrap text-muted-foreground max-sm:sr-only" for="' . esc_attr( $id ) . '">' . brik_inline( $a['label'] ) . '</label>' : '';
		return '<form' . brik_attrs(
			array(
				'class'              => 'brik-so flex items-center gap-2',
				'method'             => 'get',
				'action'             => $ctx->canvas ? null : $action,
				'data-brik-ordering' => $key,
			)
		) . '>' . $label
			. '<div class="relative min-w-0 sm:min-w-44"><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" class="' . esc_attr( brik_select_class() ) . '"' . ( '' === $label ? ' aria-label="' . esc_attr__( 'Sort products', 'brik-builder' ) . '"' : '' ) . '>' . $opts . '</select>'
			. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' ) . '</div>'
			. '<noscript><button type="submit" class="' . esc_attr( brik_button_class( 'outline', 'default' ) ) . '">' . esc_html__( 'Sort', 'brik-builder' ) . '</button></noscript>'
			. $hidden . '</form>';
	},
);
