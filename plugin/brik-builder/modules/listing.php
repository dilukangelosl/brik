<?php
/**
 * Listing: posts of any type rendered with a loop item (a library item of kind "loop"),
 * with a query builder, layouts, pagination and listing_filter support.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_off = array( '', 'false', '0' );
$brik_q   = array( 'group_label' => __( 'Query', 'brik-builder' ) );
$brik_ql  = array_merge( $brik_q, array( 'show_if' => array( 'use_main_query' => $brik_off ) ) );
$brik_p   = array( 'group_label' => __( 'Pagination', 'brik-builder' ) );
$brik_c   = array(
	'group_label' => __( 'Built-in card', 'brik-builder' ),
	'show_if'     => array( 'loop_item' => array( '', '0' ) ),
);

return array(
	'type'        => 'listing',
	'title'       => __( 'Listing', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'layout-list',
	'description' => 'Query-driven list of posts of any type (incl. custom post types), each rendered with a LOOP ITEM: a library item of kind "loop" designed in the builder (create it with save_to_library kind "loop"; inside it {post_title}, {post_url}, {featured_image}, {field:name}, {meta:key} and post_* modules resolve to each listed post). loop_item: library item id; empty = built-in card (image, term badge, title, excerpt, date/author, read more; card_image, card_ratio 16:9|4:3|3:2|1:1, card_terms, card_taxonomy, card_excerpt, card_excerpt_length, card_meta, card_more_text, title_tag). '
		. 'Query: use_main_query (bool; archive/search templates use the page\'s own posts), post_type, taxonomy + terms (comma separated ids or slugs) + terms_operator IN|NOT IN|AND, meta_query repeater [{field, compare: =|!=|>|>=|<|<=|LIKE|NOT LIKE|IN|NOT IN|BETWEEN|EXISTS|NOT EXISTS, value (comma separated for IN/BETWEEN), type: CHAR|NUMERIC|DATE}] + meta_relation AND|OR, search, author: ""|current|ids + author_ids, orderby: date|title|menu_order|rand|modified|comment_count|meta_value|meta_value_num (+ orderby_field), order DESC|ASC, posts_per_page (1-100), offset, exclude_current, related (bool) + related_by terms|field + related_taxonomy / related_field (relationship field holding post ids). '
		. 'Layout: layout grid|list|masonry|carousel (CSS scroll snap, arrows bool), columns (responsive, default 3/2/1), gap (responsive), equal_height. pagination: none|numbered|load_more|infinite (+ load_more_text). empty_message. animate (bool, staggered entrance) + stagger (ms). '
		. 'Set css_id (e.g. "projects") so a listing_filter can target it; without one the node id is used. Filter values travel as ?bf_{css_id}_{filter}=… and are read server-side too, so filtered pages work without JavaScript.',
	'fields'      => array_merge(
		array(
			'loop_item'       => Fields::field( 'library', __( 'Loop item', 'brik-builder' ), 'content', array( 'kind' => 'loop', 'description' => __( 'A library item of kind "Loop item". Leave empty for the built-in card.', 'brik-builder' ) ) ),
			'layout'          => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'grid', 'options' => Fields::opts( array( 'grid' => __( 'Grid', 'brik-builder' ), 'list' => __( 'List', 'brik-builder' ), 'masonry' => __( 'Masonry', 'brik-builder' ), 'carousel' => __( 'Carousel', 'brik-builder' ) ) ) ) ),
			'columns'         => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 6, 'responsive' => true, 'show_if' => array( 'layout' => array( 'grid', 'masonry', 'carousel' ) ) ) ),
			'gap'             => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'placeholder' => '24px', 'css' => array( Fields::WRAP . ' .brik-listing-items', '--brik-listing-gap' ) ) ),
			'equal_height'    => Fields::field( 'toggle', __( 'Equal height items', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'carousel' ) ) ) ),
			'arrows'          => Fields::field( 'toggle', __( 'Arrows', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => 'carousel' ) ) ),
			'animate'         => Fields::field( 'toggle', __( 'Animate items in', 'brik-builder' ), 'content', array( 'description' => __( 'Items fade up one after another.', 'brik-builder' ) ) ),
			'stagger'         => Fields::field( 'number', __( 'Stagger (ms)', 'brik-builder' ), 'content', array( 'default' => 60, 'min' => 0, 'max' => 500, 'show_if' => array( 'animate' => true ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-listing-items', 'prop' => '--brik-stagger', 'value' => '{{v}}ms' ) ) ),
			'empty_message'   => Fields::field( 'text', __( 'Empty message', 'brik-builder' ), 'content', array( 'default' => __( 'Nothing found. Try adjusting your filters.', 'brik-builder' ) ) ),

			'use_main_query'  => Fields::field( 'toggle', __( 'Use the page\'s own posts', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'description' => __( 'For archive and search templates.', 'brik-builder' ) ) ) ),
			'post_type'       => Fields::field( 'post_type', __( 'Post type', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'default' => 'post' ) ) ),
			'taxonomy'        => Fields::field( 'taxonomy', __( 'Taxonomy', 'brik-builder' ), 'query', $brik_ql ),
			'terms'           => Fields::field( 'text', __( 'Terms', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'placeholder' => 'branding, 12', 'description' => __( 'Term slugs or IDs, separated by commas.', 'brik-builder' ), 'show_if' => array( 'taxonomy' => '!', 'use_main_query' => $brik_off ) ) ) ),
			'terms_operator'  => Fields::field( 'select', __( 'Match', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'default' => 'IN', 'show_if' => array( 'taxonomy' => '!', 'use_main_query' => $brik_off ), 'options' => Fields::opts( array( 'IN' => __( 'Any of the terms', 'brik-builder' ), 'AND' => __( 'All of the terms', 'brik-builder' ), 'NOT IN' => __( 'None of the terms', 'brik-builder' ) ) ) ) ) ),
			'meta_query'      => Fields::field(
				'repeater',
				__( 'Field conditions', 'brik-builder' ),
				'query',
				array_merge(
					$brik_ql,
					array(
						'title_field' => 'field',
						'item_label'  => __( 'condition', 'brik-builder' ),
						'fields'      => array(
							'field'   => Fields::field( 'text', __( 'Field name', 'brik-builder' ), 'content', array( 'placeholder' => 'price' ) ),
							'compare' => Fields::field( 'select', __( 'Compare', 'brik-builder' ), 'content', array( 'default' => '=', 'options' => Fields::opts( array_combine( brik_listing_compares(), brik_listing_compares() ) ) ) ),
							'value'   => Fields::field( 'text', __( 'Value', 'brik-builder' ), 'content', array( 'description' => __( 'Comma separated for IN and BETWEEN. Dynamic tags work.', 'brik-builder' ), 'show_if' => array( 'compare' => array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN' ) ) ) ),
							'type'    => Fields::field( 'select', __( 'Compare as', 'brik-builder' ), 'content', array( 'default' => 'CHAR', 'options' => Fields::opts( array( 'CHAR' => __( 'Text', 'brik-builder' ), 'NUMERIC' => __( 'Number', 'brik-builder' ), 'DATE' => __( 'Date', 'brik-builder' ) ) ) ) ),
						),
					)
				)
			),
			'meta_relation'   => Fields::field( 'select', __( 'Conditions must', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'default' => 'AND', 'show_if' => array( 'meta_query' => '!', 'use_main_query' => $brik_off ), 'options' => Fields::opts( array( 'AND' => __( 'All match', 'brik-builder' ), 'OR' => __( 'Any match', 'brik-builder' ) ) ) ) ) ),
			'search'          => Fields::field( 'text', __( 'Search', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'placeholder' => '{search_query}' ) ) ),
			'author'          => Fields::field( 'select', __( 'Author', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'options' => Fields::opts( array( '' => __( 'Anyone', 'brik-builder' ), 'current' => __( 'Logged-in user', 'brik-builder' ), 'ids' => __( 'Specific users', 'brik-builder' ) ) ) ) ) ),
			'author_ids'      => Fields::field( 'text', __( 'User IDs', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'placeholder' => '1, 4', 'show_if' => array( 'author' => 'ids' ) ) ) ),
			'orderby'         => Fields::field( 'select', __( 'Order by', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 'date', 'options' => Fields::opts( array( 'date' => __( 'Date', 'brik-builder' ), 'modified' => __( 'Last updated', 'brik-builder' ), 'title' => __( 'Title', 'brik-builder' ), 'menu_order' => __( 'Menu order', 'brik-builder' ), 'comment_count' => __( 'Comments', 'brik-builder' ), 'rand' => __( 'Random', 'brik-builder' ), 'meta_value' => __( 'Field (text)', 'brik-builder' ), 'meta_value_num' => __( 'Field (number)', 'brik-builder' ) ) ) ) ) ),
			'orderby_field'   => Fields::field( 'text', __( 'Order by field', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'placeholder' => 'price', 'show_if' => array( 'orderby' => array( 'meta_value', 'meta_value_num' ) ) ) ) ),
			'order'           => Fields::field( 'select', __( 'Order', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 'DESC', 'options' => Fields::opts( array( 'DESC' => __( 'Descending', 'brik-builder' ), 'ASC' => __( 'Ascending', 'brik-builder' ) ) ) ) ) ),
			'posts_per_page'  => Fields::field( 'number', __( 'Items per page', 'brik-builder' ), 'query', array_merge( $brik_q, array( 'default' => 9, 'min' => 1, 'max' => 100 ) ) ),
			'offset'          => Fields::field( 'number', __( 'Skip items', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'min' => 0 ) ) ),
			'exclude_current' => Fields::field( 'toggle', __( 'Exclude the current post', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'default' => true ) ) ),
			'related'         => Fields::field( 'toggle', __( 'Related to the current post', 'brik-builder' ), 'query', $brik_ql ),
			'related_by'      => Fields::field( 'select', __( 'Related by', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'default' => 'terms', 'show_if' => array( 'related' => true ), 'options' => Fields::opts( array( 'terms' => __( 'Shared terms', 'brik-builder' ), 'field' => __( 'Relationship field', 'brik-builder' ) ) ) ) ) ),
			'related_taxonomy' => Fields::field( 'taxonomy', __( 'Shared taxonomy', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'show_if' => array( 'related' => true, 'related_by' => 'terms' ) ) ) ),
			'related_field'   => Fields::field( 'text', __( 'Relationship field', 'brik-builder' ), 'query', array_merge( $brik_ql, array( 'placeholder' => 'related_projects', 'show_if' => array( 'related' => true, 'related_by' => 'field' ) ) ) ),

			'pagination'      => Fields::field( 'select', __( 'Pagination', 'brik-builder' ), 'pagination', array_merge( $brik_p, array( 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'None', 'brik-builder' ), 'numbered' => __( 'Page numbers', 'brik-builder' ), 'load_more' => __( 'Load more button', 'brik-builder' ), 'infinite' => __( 'Infinite scroll', 'brik-builder' ) ) ) ) ) ),
			'load_more_text'  => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'pagination', array_merge( $brik_p, array( 'default' => __( 'Load more', 'brik-builder' ), 'show_if' => array( 'pagination' => array( 'load_more', 'infinite' ) ) ) ) ),

			'card_image'          => Fields::field( 'toggle', __( 'Image', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_ratio'          => Fields::field( 'select', __( 'Image ratio', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => '16:9', 'options' => Fields::opts( brik_aspect_options( false ) ) ) ) ),
			'card_terms'          => Fields::field( 'toggle', __( 'Term badges', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_taxonomy'       => Fields::field( 'taxonomy', __( 'Badge taxonomy', 'brik-builder' ), 'card', $brik_c ),
			'title_tag'           => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ) ) ) ) ),
			'card_excerpt'        => Fields::field( 'toggle', __( 'Excerpt', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_excerpt_length' => Fields::field( 'number', __( 'Excerpt length (words)', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => 18, 'min' => 1, 'max' => 80 ) ) ),
			'card_meta'           => Fields::field( 'toggle', __( 'Date and author', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => true ) ) ),
			'card_more_text'      => Fields::field( 'text', __( 'Read more text', 'brik-builder' ), 'card', array_merge( $brik_c, array( 'default' => __( 'Read more', 'brik-builder' ) ) ) ),
		),
		Fields::box( 'card', __( 'Cards', 'brik-builder' ), Fields::WRAP . ' .brik-listing-card' ),
		Fields::typography( 'title', __( 'Card titles', 'brik-builder' ), Fields::WRAP . ' .brik-listing-card-title' )
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
		foreach ( $rules as $state => $rule ) {
			$v = (int) Brik\Style::raw_value( $a, 'columns', $state );
			if ( $v > 0 ) {
				$out .= sprintf( $rule, $wrap . '{' . $vars[ $state ] . ':' . min( 6, $v ) . '}' );
			}
		}
		return $out;
	},
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		$renderer = $ctx->renderer;
		$key      = brik_listing_key( $a['css_id'], $ctx->id );
		$canvas   = $ctx->canvas;

		// The settings may use main query only where WordPress picked posts for the page.
		$main = null;
		if ( brik_form_bool( $a['use_main_query'] ) && ! $canvas && ( is_archive() || is_home() || is_search() ) ) {
			$main = brik_listing_main_context();
		}
		$post_type = $main ? ( isset( $main['post_type'] ) ? $main['post_type'] : '' ) : brik_listing_post_type( $a['post_type'] );

		$params  = $canvas ? array() : brik_listing_request_params();
		$defs    = brik_listing_filters_for( $renderer, $renderer->root, $key );
		$parsed  = brik_listing_parse( $defs, $params, $key, $post_type );
		$page    = $canvas || ! isset( $params[ brik_listing_param( $key, 'page' ) ] ) ? 1 : max( 1, absint( $params[ brik_listing_param( $key, 'page' ) ] ) );
		$current = brik_site_post( $ctx );

		$result = brik_listing_results(
			$a,
			$renderer,
			array(
				'key'      => $key,
				'current'  => $current,
				'page'     => $page,
				'clauses'  => $parsed[0],
				'main'     => $main,
				'base_url' => $canvas ? '' : brik_listing_current_url(),
				'canvas'   => $canvas,
			)
		);

		if ( empty( $a['css_id'] ) ) {
			$ctx->attrs['id'] = $key;
		}
		$ctx->attrs['data-brik-listing'] = $key;
		$ctx->attrs['data-node']         = $ctx->id;
		$ctx->attrs['data-post']         = (int) $ctx->post_id;
		$ctx->attrs['data-pages']        = (int) $result['pages'];
		$ctx->attrs['data-page']         = (int) $result['page'];
		$ctx->attrs['data-total']        = (int) $result['total'];
		$ctx->attrs['data-pagination']   = 'carousel' === $a['layout'] ? 'none' : sanitize_key( (string) $a['pagination'] );
		if ( $current && ! $canvas ) {
			$ctx->attrs['data-current'] = (int) $current->ID;
		}
		if ( $main ) {
			$ctx->attrs['data-main'] = wp_json_encode( $main );
		}

		$note = '';
		if ( $canvas && ! empty( $a['loop_item'] ) && ! Brik\Library::item( (int) $a['loop_item'] ) ) {
			$note = $ctx->placeholder( __( 'The chosen loop item no longer exists; showing the built-in card.', 'brik-builder' ) );
		}

		return $note . '<div class="brik-listing-results grid gap-8" aria-live="polite">' . $result['html'] . '</div>';
	},
);
