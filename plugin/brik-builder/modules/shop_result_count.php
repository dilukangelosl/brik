<?php
/**
 * Shop result count: "Showing 1–12 of 48 results" for a products module or the shop query.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

if ( ! brik_woo_shop_enabled() ) {
	return null;
}

return array(
	'type'        => 'shop_result_count',
	'title'       => __( 'Result count', 'brik-builder' ),
	'category'    => 'shop',
	'icon'        => 'list-ordered',
	'description' => 'Shows "Showing 1–12 of 48 results". target: css_id of a products module (empty = WooCommerce\'s shop/archive query). Updates live when filters or pagination change.',
	'fields'      => array_merge(
		array(
			'target' => Fields::field( 'text', __( 'Products CSS id', 'brik-builder' ), 'content', array( 'placeholder' => 'shop' ) ),
			'align'  => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP, 'text-align' ) ) ),
		),
		Fields::typography( 'count', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-src' )
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$key   = brik_listing_key( $a['target'], '' );
		$total = null;
		$page  = 1;
		$per   = 1;
		$more  = false;
		if ( '' !== $key && ! $ctx->canvas ) {
			$renderer = $ctx->renderer;
			$target   = brik_woo_target_attrs( $renderer, $renderer->root, $key );
			if ( $target ) {
				$params          = brik_woo_request_params();
				list( $clauses ) = brik_woo_filter_parse( brik_woo_filters_for( $renderer, $renderer->root, $key ), $params, $key );
				$paged           = 'none' !== $target['pagination'] && 'carousel' !== $target['layout'];
				$page            = $paged && isset( $params[ brik_listing_param( $key, 'page' ) ] ) ? max( 1, absint( $params[ brik_listing_param( $key, 'page' ) ] ) ) : 1;
				list( $query, $args ) = brik_woo_products_query(
					$target,
					array(
						'page'    => $page,
						'clauses' => $clauses,
						'main'    => 'current' === $target['source'] && brik_woo_is_product_archive() ? brik_woo_main_context() : null,
						'product' => in_array( $target['source'], array( 'related', 'upsells', 'cross_sells' ), true ) ? brik_woo_context_product( $ctx ) : null,
					)
				);
				$total = max( 0, (int) $query->found_posts - max( 0, (int) $target['offset'] ) );
				$per   = (int) $args['posts_per_page'];
				$more  = in_array( $target['pagination'], array( 'load_more', 'infinite' ), true );
			}
		} elseif ( ! $ctx->canvas && brik_woo_is_product_archive() ) {
			global $wp_query;
			$total = (int) $wp_query->found_posts;
			$per   = max( 1, (int) $wp_query->get( 'posts_per_page' ) );
			$page  = max( 1, (int) $wp_query->get( 'paged' ) );
		} elseif ( $ctx->canvas ) {
			$total = 48;
			$per   = 12;
		}
		if ( null === $total ) {
			return '';
		}

		$tpl = array(
			'none'  => __( 'No products found', 'brik-builder' ),
			'one'   => __( 'Showing the single result', 'brik-builder' ),
			/* translators: %d: total results */
			'all'   => __( 'Showing all %d results', 'brik-builder' ),
			/* translators: 1: first result, 2: last result, 3: total results */
			'range' => _x( 'Showing %1$d–%2$d of %3$d results', 'with first and last result', 'brik-builder' ),
		);
		$first = $more ? 1 : ( $page - 1 ) * $per + 1;
		$last  = min( $total, $page * $per );
		if ( 0 === $total ) {
			$text = $tpl['none'];
		} elseif ( 1 === $total ) {
			$text = $tpl['one'];
		} elseif ( $total <= $per || ( 1 === $first && $last >= $total ) ) {
			$text = sprintf( $tpl['all'], $total );
		} else {
			$text = sprintf( $tpl['range'], $first, $last, $total );
		}

		return '<p class="brik-src text-sm text-muted-foreground tabular-nums" aria-live="polite"' . brik_attrs(
			array(
				'data-brik-result-count' => $key,
				'data-tpl'               => wp_json_encode( $tpl ),
				'data-append'            => $more ? '1' : null,
			)
		) . '>' . esc_html( $text ) . '</p>';
	},
);
