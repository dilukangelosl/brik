<?php
/**
 * Listings: query building, filter parsing and result markup shared by the listing and
 * listing_filter modules and the brik/v1/listing endpoint.
 *
 * Filter values always arrive as query parameters named bf_{listing}_{filter} (from the URL
 * without JavaScript, or from the AJAX request). They are only ever matched against filter
 * definitions stored on the server, so a visitor can't query fields nobody exposed.
 *
 * @package Brik
 */

use Brik\Data;
use Brik\Forms;
use Brik\Library;
use Brik\Modules;
use Brik\Renderer;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Small helpers.
 * ----------------------------------------------------------------------- */

/**
 * The id a listing is addressed by: its CSS id, or the node id when none is set.
 */
function brik_listing_key( $css_id, $node_id ) {
	$key = strtolower( preg_replace( '/[^A-Za-z0-9_-]/', '', ltrim( (string) $css_id, '#' ) ) );
	return '' !== $key ? substr( $key, 0, 40 ) : (string) $node_id;
}

function brik_listing_param( $key, $filter ) {
	return 'bf_' . $key . '_' . $filter;
}

/**
 * Meta keys we accept from settings: plain names, no protected (underscore) keys.
 */
function brik_listing_meta_key( $name ) {
	$name = trim( (string) $name );
	return preg_match( '/^[A-Za-z0-9][A-Za-z0-9_\-]{0,63}$/', $name ) ? $name : '';
}

/**
 * Comma separated or array input as a list of trimmed strings.
 */
function brik_listing_list( $value, $max = 20 ) {
	if ( is_string( $value ) || is_numeric( $value ) ) {
		$value = explode( ',', (string) $value );
	}
	if ( ! is_array( $value ) ) {
		return array();
	}
	$out = array();
	foreach ( $value as $v ) {
		if ( is_string( $v ) || is_numeric( $v ) ) {
			$v = trim( sanitize_text_field( (string) $v ) );
			if ( '' !== $v && ! in_array( $v, $out, true ) ) {
				$out[] = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 100 ) : substr( $v, 0, 100 );
			}
		}
	}
	return array_slice( $out, 0, $max );
}

/**
 * Post types a listing may query: anything registered as viewable.
 */
function brik_listing_post_type( $type ) {
	$type = sanitize_key( (string) $type );
	return $type && post_type_exists( $type ) && is_post_type_viewable( $type ) ? $type : 'post';
}

/**
 * Bumped whenever content changes so cached option lists and ranges refresh.
 */
function brik_listing_cache_version() {
	return (int) get_option( 'brik_listing_cache', 1 );
}

function brik_listing_cache_bump() {
	static $done = false;
	if ( ! $done ) {
		$done = true;
		update_option( 'brik_listing_cache', brik_listing_cache_version() + 1, false );
	}
}
add_action( 'save_post', 'brik_listing_cache_bump' );
add_action( 'deleted_post', 'brik_listing_cache_bump' );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $brik_hook ) {
	add_action(
		$brik_hook,
		static function ( $mid, $post_id, $key ) {
			if ( is_string( $key ) && '_' !== substr( $key, 0, 1 ) ) {
				brik_listing_cache_bump();
			}
		},
		10,
		3
	);
}

/**
 * A field value of a post: the content API when it's loaded, plain post meta otherwise.
 */
function brik_listing_raw_field( $name, $post_id ) {
	if ( function_exists( 'brik_raw_field' ) ) {
		$value = brik_raw_field( $name, $post_id );
		if ( null !== $value && '' !== $value ) {
			return $value;
		}
	}
	return get_post_meta( $post_id, $name, true );
}

/**
 * Choice labels of a registered field ({value: label}), or an empty list.
 */
function brik_listing_field_choices( $name, $post_type = null ) {
	if ( ! function_exists( 'brik_field_object' ) ) {
		return array();
	}
	$field = null;
	if ( $post_type && class_exists( 'Brik\\Content\\Registry' ) && method_exists( 'Brik\\Content\\Registry', 'find_field' ) ) {
		$field = Brik\Content\Registry::find_field( $name, $post_type );
	}
	if ( ! $field ) {
		$field = brik_field_object( $name );
	}
	$out = array();
	if ( is_array( $field ) && ! empty( $field['options']['choices'] ) && is_array( $field['options']['choices'] ) ) {
		foreach ( $field['options']['choices'] as $choice ) {
			if ( is_array( $choice ) && isset( $choice['value'] ) ) {
				$out[ (string) $choice['value'] ] = isset( $choice['label'] ) && '' !== $choice['label'] ? (string) $choice['label'] : (string) $choice['value'];
			}
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Query building.
 * ----------------------------------------------------------------------- */

function brik_listing_compares() {
	return array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'EXISTS', 'NOT EXISTS' );
}

function brik_listing_orderbys() {
	return array( 'date', 'title', 'menu_order', 'rand', 'modified', 'comment_count', 'meta_value', 'meta_value_num' );
}

/**
 * One meta condition from the settings repeater, or null when it is incomplete.
 */
function brik_listing_meta_clause( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}
	$key     = brik_listing_meta_key( isset( $row['field'] ) ? $row['field'] : '' );
	$compare = isset( $row['compare'] ) ? strtoupper( trim( (string) $row['compare'] ) ) : '=';
	$type    = isset( $row['type'] ) ? strtoupper( (string) $row['type'] ) : 'CHAR';
	if ( '' === $key || ! in_array( $compare, brik_listing_compares(), true ) ) {
		return null;
	}
	$type   = in_array( $type, array( 'CHAR', 'NUMERIC', 'DATE', 'DECIMAL' ), true ) ? $type : 'CHAR';
	$clause = array(
		'key'     => $key,
		'compare' => $compare,
	);
	if ( in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
		return $clause;
	}

	$raw = isset( $row['value'] ) ? (string) $row['value'] : '';
	if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN' ), true ) ) {
		$value = brik_listing_list( wp_strip_all_tags( $raw ), 50 );
		if ( 'BETWEEN' === $compare && 2 !== count( $value ) ) {
			return null;
		}
		if ( ! $value ) {
			return null;
		}
	} else {
		$value = sanitize_text_field( $raw );
	}
	if ( 'NUMERIC' === $type || 'DECIMAL' === $type ) {
		foreach ( (array) $value as $v ) {
			if ( ! is_numeric( $v ) ) {
				return null;
			}
		}
		$type = 'DECIMAL' === $type ? 'DECIMAL(20,4)' : 'NUMERIC';
	}
	$clause['value'] = $value;
	$clause['type']  = $type;
	return $clause;
}

/**
 * The page's own query reduced to what a listing may reproduce over AJAX.
 */
function brik_listing_main_context() {
	$ctx = array();
	if ( is_search() ) {
		$ctx['s'] = get_search_query( false );
	}
	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$ctx['taxonomy'] = $term->taxonomy;
			$ctx['term']     = $term->term_id;
		}
	} elseif ( is_author() ) {
		$ctx['author'] = (int) get_queried_object_id();
	} elseif ( is_post_type_archive() ) {
		$type = get_query_var( 'post_type' );
		$ctx['post_type'] = is_array( $type ) ? reset( $type ) : $type;
	} elseif ( is_date() ) {
		$ctx['year']     = (int) get_query_var( 'year' );
		$ctx['monthnum'] = (int) get_query_var( 'monthnum' );
	}
	if ( empty( $ctx['post_type'] ) && ! is_search() ) {
		$ctx['post_type'] = is_home() || is_category() || is_tag() || is_author() || is_date() ? 'post' : '';
		if ( '' === $ctx['post_type'] && ! empty( $ctx['taxonomy'] ) ) {
			$tax              = get_taxonomy( $ctx['taxonomy'] );
			$ctx['post_type'] = $tax && $tax->object_type ? reset( $tax->object_type ) : 'post';
		}
	}
	return $ctx;
}

/**
 * Query arguments for a main-query context, validated (it may come from the client).
 */
function brik_listing_main_args( $ctx ) {
	$ctx  = is_array( $ctx ) ? $ctx : array();
	$args = array( 'post_type' => isset( $ctx['post_type'] ) && '' !== $ctx['post_type'] ? brik_listing_post_type( $ctx['post_type'] ) : 'any' );
	if ( 'any' === $args['post_type'] ) {
		$args['post_type'] = array_values( get_post_types( array( 'public' => true, 'exclude_from_search' => false ) ) );
	}
	if ( isset( $ctx['s'] ) && is_string( $ctx['s'] ) && '' !== trim( $ctx['s'] ) ) {
		$args['s'] = substr( sanitize_text_field( $ctx['s'] ), 0, 100 );
	}
	if ( ! empty( $ctx['taxonomy'] ) && ! empty( $ctx['term'] ) ) {
		$tax  = sanitize_key( (string) $ctx['taxonomy'] );
		$term = get_term( (int) $ctx['term'], $tax );
		if ( taxonomy_exists( $tax ) && is_taxonomy_viewable( $tax ) && $term instanceof WP_Term ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => $tax,
					'field'    => 'term_id',
					'terms'    => array( $term->term_id ),
				),
			);
		}
	}
	if ( ! empty( $ctx['author'] ) ) {
		$args['author'] = absint( $ctx['author'] );
	}
	if ( ! empty( $ctx['year'] ) ) {
		$args['year'] = absint( $ctx['year'] );
		if ( ! empty( $ctx['monthnum'] ) ) {
			$args['monthnum'] = min( 12, absint( $ctx['monthnum'] ) );
		}
	}
	return $args;
}

/**
 * Turn listing attributes (plus validated filter clauses) into WP_Query arguments.
 *
 * @param array $a   Listing attributes.
 * @param array $opt current (WP_Post|null), page (int), clauses (from brik_listing_parse()),
 *                   main (main-query context or null).
 */
function brik_listing_query_args( array $a, array $opt = array() ) {
	$opt = wp_parse_args(
		$opt,
		array(
			'current' => null,
			'page'    => 1,
			'clauses' => array(),
			'main'    => null,
		)
	);
	$get = static function ( $key, $default = '' ) use ( $a ) {
		return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $default;
	};

	$ppp    = min( 100, max( 1, (int) $get( 'posts_per_page', 9 ) ) );
	$offset = max( 0, (int) $get( 'offset', 0 ) );
	$page   = max( 1, (int) $opt['page'] );

	if ( is_array( $opt['main'] ) ) {
		$args = brik_listing_main_args( $opt['main'] );
	} else {
		$args = array( 'post_type' => brik_listing_post_type( $get( 'post_type', 'post' ) ) );
	}

	$args = array_merge(
		$args,
		array(
			'post_status'         => 'publish',
			'posts_per_page'      => $ppp,
			'offset'              => $offset + ( $page - 1 ) * $ppp,
			'ignore_sticky_posts' => true,
		)
	);
	$tax_query  = isset( $args['tax_query'] ) ? $args['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
	$meta_query = array();
	$not_in     = array();
	$current    = $opt['current'] instanceof WP_Post ? $opt['current'] : null;

	if ( ! is_array( $opt['main'] ) ) {
		// Taxonomy terms (ids or slugs).
		$tax = sanitize_key( (string) $get( 'taxonomy' ) );
		if ( $tax && taxonomy_exists( $tax ) && '' !== trim( (string) $get( 'terms' ) ) ) {
			$terms = brik_listing_list( wp_strip_all_tags( (string) $get( 'terms' ) ), 50 );
			$ids   = array_filter( $terms, 'ctype_digit' );
			$op    = strtoupper( (string) $get( 'terms_operator', 'IN' ) );
			if ( $terms ) {
				$tax_query[] = array(
					'taxonomy' => $tax,
					'field'    => count( $ids ) === count( $terms ) ? 'term_id' : 'slug',
					'terms'    => count( $ids ) === count( $terms ) ? array_map( 'intval', $ids ) : array_map( 'sanitize_title', $terms ),
					'operator' => in_array( $op, array( 'IN', 'NOT IN', 'AND' ), true ) ? $op : 'IN',
				);
			}
		}

		// Meta conditions.
		$rows = $get( 'meta_query', array() );
		$own  = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$clause = brik_listing_meta_clause( $row );
			if ( $clause ) {
				$own[] = $clause;
			}
		}
		if ( $own ) {
			$own['relation'] = 'OR' === strtoupper( (string) $get( 'meta_relation', 'AND' ) ) ? 'OR' : 'AND';
			$meta_query[]    = $own;
		}

		// Search.
		$search = trim( wp_strip_all_tags( (string) $get( 'search' ) ) );
		if ( '' !== $search ) {
			$args['s'] = substr( $search, 0, 100 );
		}

		// Author.
		$author = (string) $get( 'author' );
		if ( 'current' === $author ) {
			$args['author__in'] = array( get_current_user_id() ); // 0 matches nothing for visitors.
		} elseif ( 'ids' === $author ) {
			$ids = array_filter( array_map( 'absint', brik_listing_list( (string) $get( 'author_ids' ), 50 ) ) );
			if ( $ids ) {
				$args['author__in'] = array_values( $ids );
			}
		}

		// Related to the current post.
		if ( brik_form_bool( $get( 'related', false ) ) && $current ) {
			$not_in[] = $current->ID;
			if ( 'field' === $get( 'related_by', 'terms' ) ) {
				$field = brik_listing_meta_key( $get( 'related_field' ) );
				$value = $field ? brik_listing_raw_field( $field, $current->ID ) : array();
				$ids   = array_values( array_filter( array_map( 'absint', is_array( $value ) ? $value : brik_listing_list( (string) $value, 100 ) ) ) );
				$args['post__in'] = $ids ? $ids : array( 0 );
			} else {
				$rtax = sanitize_key( (string) $get( 'related_taxonomy' ) );
				if ( ! $rtax || ! is_object_in_taxonomy( $current->post_type, $rtax ) ) {
					$rtax = '';
					foreach ( get_object_taxonomies( $current->post_type, 'objects' ) as $t ) {
						if ( $t->public && 'post_format' !== $t->name ) {
							$rtax = $t->name;
							break;
						}
					}
				}
				$ids = $rtax ? wp_get_object_terms( $current->ID, $rtax, array( 'fields' => 'ids' ) ) : array();
				if ( $ids && ! is_wp_error( $ids ) ) {
					$tax_query[] = array(
						'taxonomy' => $rtax,
						'field'    => 'term_id',
						'terms'    => array_map( 'intval', $ids ),
					);
				} else {
					$args['post__in'] = array( 0 );
				}
			}
		}
		if ( brik_form_bool( $get( 'exclude_current', false ) ) && $current ) {
			$not_in[] = $current->ID;
		}
	}

	// Order.
	$orderby = (string) $get( 'orderby', 'date' );
	$orderby = in_array( $orderby, brik_listing_orderbys(), true ) ? $orderby : 'date';
	$order   = 'ASC' === strtoupper( (string) $get( 'order', 'DESC' ) ) ? 'ASC' : 'DESC';
	$okey    = '';
	if ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) ) {
		$okey = brik_listing_meta_key( $get( 'orderby_field' ) );
		if ( '' === $okey ) {
			$orderby = 'date';
		}
	}

	// Filter clauses chosen by the visitor (already validated).
	foreach ( (array) $opt['clauses'] as $clause ) {
		switch ( isset( $clause['kind'] ) ? $clause['kind'] : '' ) {
			case 'search':
				$args['s'] = $clause['value'];
				break;
			case 'tax':
				$tax_query[] = array(
					'taxonomy' => $clause['taxonomy'],
					'field'    => 'term_id',
					'terms'    => $clause['terms'],
				);
				break;
			case 'meta':
				$meta_query[] = array(
					'key'     => $clause['key'],
					'value'   => $clause['value'],
					'compare' => 'IN',
				);
				break;
			case 'range':
				$type = 'DATE' === $clause['type'] ? 'DATE' : 'DECIMAL(20,4)';
				if ( null !== $clause['min'] ) {
					$meta_query[] = array( 'key' => $clause['key'], 'value' => $clause['min'], 'compare' => '>=', 'type' => $type );
				}
				if ( null !== $clause['max'] ) {
					$meta_query[] = array( 'key' => $clause['key'], 'value' => $clause['max'], 'compare' => '<=', 'type' => $type );
				}
				break;
			case 'sort':
				$orderby = $clause['orderby'];
				$order   = $clause['order'];
				$okey    = isset( $clause['meta_key'] ) ? $clause['meta_key'] : '';
				break;
		}
	}

	$args['orderby'] = $orderby;
	$args['order']   = $order;
	if ( $okey && in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) ) {
		$args['meta_key'] = $okey; // phpcs:ignore WordPress.DB.SlowDBQuery
	} elseif ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) ) {
		$args['orderby'] = 'date';
	}
	if ( 'rand' === $args['orderby'] && $page > 1 ) {
		// Random order can't be paged reliably; keep later pages stable per day.
		$args['orderby'] = 'RAND(' . (int) gmdate( 'Ymd' ) . ')';
	}

	if ( $tax_query ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( $meta_query ) {
		$meta_query['relation'] = 'AND';
		$args['meta_query']     = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	if ( $not_in ) {
		$args['post__not_in'] = array_values( array_unique( $not_in ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams
	}

	return $args;
}

/* -------------------------------------------------------------------------
 * Filters.
 * ----------------------------------------------------------------------- */

function brik_listing_filter_types() {
	return array(
		'search'   => __( 'Search', 'brik-builder' ),
		'taxonomy' => __( 'Taxonomy', 'brik-builder' ),
		'field'    => __( 'Field', 'brik-builder' ),
		'sort'     => __( 'Sort', 'brik-builder' ),
		'reset'    => __( 'Reset', 'brik-builder' ),
	);
}

function brik_listing_filter_uis() {
	return array(
		'select'     => __( 'Dropdown', 'brik-builder' ),
		'checkboxes' => __( 'Checkboxes', 'brik-builder' ),
		'radio'      => __( 'Radio buttons', 'brik-builder' ),
		'pills'      => __( 'Pills', 'brik-builder' ),
		'range'      => __( 'Range slider', 'brik-builder' ),
		'date_range' => __( 'Date range', 'brik-builder' ),
	);
}

/**
 * Sort choices: value => [label, orderby, order, meta key].
 */
function brik_listing_sort_options( $field = '' ) {
	$out = array(
		'newest' => array( __( 'Newest first', 'brik-builder' ), 'date', 'DESC', '' ),
		'oldest' => array( __( 'Oldest first', 'brik-builder' ), 'date', 'ASC', '' ),
		'az'     => array( __( 'Title A–Z', 'brik-builder' ), 'title', 'ASC', '' ),
		'za'     => array( __( 'Title Z–A', 'brik-builder' ), 'title', 'DESC', '' ),
	);
	$field = brik_listing_meta_key( $field );
	if ( $field ) {
		$label            = ucwords( str_replace( array( '_', '-' ), ' ', $field ) );
		/* translators: %s: field name */
		$out['field_asc'] = array( sprintf( __( '%s: low to high', 'brik-builder' ), $label ), 'meta_value_num', 'ASC', $field );
		/* translators: %s: field name */
		$out['field_desc'] = array( sprintf( __( '%s: high to low', 'brik-builder' ), $label ), 'meta_value_num', 'DESC', $field );
	}
	return $out;
}

/**
 * Normalize the filters repeater of a listing_filter node into definitions with unique keys.
 */
function brik_listing_filter_defs( $items ) {
	$defs  = array();
	$used  = array();
	$types = brik_listing_filter_types();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item = wp_parse_args(
			$item,
			array(
				'type'        => 'search',
				'label'       => '',
				'taxonomy'    => '',
				'field'       => '',
				'ui'          => 'select',
				'placeholder' => '',
				'show_counts' => false,
				'min'         => '',
				'max'         => '',
				'step'        => '',
				'prefix'      => '',
				'suffix'      => '',
			)
		);
		$type = isset( $types[ $item['type'] ] ) ? $item['type'] : 'search';
		$ui   = isset( brik_listing_filter_uis()[ $item['ui'] ] ) ? $item['ui'] : 'select';
		$def  = array(
			'type'        => $type,
			'label'       => wp_strip_all_tags( (string) $item['label'] ),
			'placeholder' => wp_strip_all_tags( (string) $item['placeholder'] ),
			'ui'          => $ui,
			'show_counts' => brik_form_bool( $item['show_counts'] ),
			'taxonomy'    => '',
			'field'       => '',
			'min'         => is_numeric( $item['min'] ) ? (float) $item['min'] : null,
			'max'         => is_numeric( $item['max'] ) ? (float) $item['max'] : null,
			'step'        => is_numeric( $item['step'] ) && $item['step'] > 0 ? (float) $item['step'] : null,
			'prefix'      => wp_strip_all_tags( (string) $item['prefix'] ),
			'suffix'      => wp_strip_all_tags( (string) $item['suffix'] ),
		);

		switch ( $type ) {
			case 'taxonomy':
				$def['taxonomy'] = sanitize_key( (string) $item['taxonomy'] );
				if ( ! $def['taxonomy'] ) {
					continue 2;
				}
				if ( in_array( $ui, array( 'range', 'date_range' ), true ) ) {
					$def['ui'] = 'select';
				}
				$base = $def['taxonomy'];
				break;
			case 'field':
				$def['field'] = brik_listing_meta_key( $item['field'] );
				if ( '' === $def['field'] ) {
					continue 2;
				}
				$base = $def['field'];
				break;
			case 'sort':
				$def['field'] = brik_listing_meta_key( $item['field'] );
				$base         = 'sort';
				break;
			default:
				$base = $type;
		}

		$key = substr( strtolower( preg_replace( '/[^a-z0-9_]/i', '_', $base ) ), 0, 32 );
		$n   = 2;
		while ( isset( $used[ $key ] ) ) {
			$key = $base . '_' . $n++;
		}
		$used[ $key ] = true;
		$def['key']   = $key;
		$defs[]       = $def;
	}
	return $defs;
}

/**
 * Validate filter values from query parameters against the definitions.
 *
 * @param array  $defs      From brik_listing_filter_defs().
 * @param array  $params    Query parameters (bf_{key}_{filter} => value), already unslashed.
 * @param string $key       Listing key.
 * @param string $post_type Listing post type (taxonomies must belong to it).
 * @return array [ clauses, state ] — clauses for the query, state for the controls.
 */
function brik_listing_parse( array $defs, array $params, $key, $post_type = '' ) {
	$clauses = array();
	$state   = array();
	$read    = static function ( $name ) use ( $params ) {
		return isset( $params[ $name ] ) ? $params[ $name ] : null;
	};

	foreach ( $defs as $def ) {
		$name = brik_listing_param( $key, $def['key'] );
		switch ( $def['type'] ) {
			case 'search':
				$raw = $read( $name );
				$v   = is_string( $raw ) ? trim( sanitize_text_field( $raw ) ) : '';
				$v   = function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 100 ) : substr( $v, 0, 100 );
				if ( '' !== $v ) {
					$clauses[]             = array( 'kind' => 'search', 'value' => $v );
					$state[ $def['key'] ] = $v;
				}
				break;

			case 'taxonomy':
				$tax = $def['taxonomy'];
				if ( ! taxonomy_exists( $tax ) || ( $post_type && 'any' !== $post_type && ! is_object_in_taxonomy( $post_type, $tax ) ) ) {
					break;
				}
				$ids   = array();
				$slugs = array();
				foreach ( brik_listing_list( $read( $name ) ) as $slug ) {
					$term = get_term_by( 'slug', sanitize_title( $slug ), $tax );
					if ( $term instanceof WP_Term ) {
						$ids[]   = (int) $term->term_id;
						$slugs[] = $term->slug;
					}
				}
				if ( in_array( $def['ui'], array( 'select', 'radio', 'pills' ), true ) ) {
					$ids   = array_slice( $ids, 0, 1 );
					$slugs = array_slice( $slugs, 0, 1 );
				}
				if ( $ids ) {
					$clauses[]             = array( 'kind' => 'tax', 'taxonomy' => $tax, 'terms' => $ids );
					$state[ $def['key'] ] = $slugs;
				}
				break;

			case 'field':
				if ( 'range' === $def['ui'] || 'date_range' === $def['ui'] ) {
					$date = 'date_range' === $def['ui'];
					$min  = $read( $name . '_min' );
					$max  = $read( $name . '_max' );
					$ok   = static function ( $v ) use ( $date ) {
						if ( ! is_string( $v ) && ! is_numeric( $v ) ) {
							return null;
						}
						$v = trim( (string) $v );
						if ( $date ) {
							return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : null;
						}
						return is_numeric( $v ) ? (float) $v : null;
					};
					$min = $ok( $min );
					$max = $ok( $max );
					if ( null !== $min || null !== $max ) {
						$clauses[]             = array(
							'kind' => 'range',
							'key'  => $def['field'],
							'min'  => $min,
							'max'  => $max,
							'type' => $date ? 'DATE' : 'NUMERIC',
						);
						$state[ $def['key'] ] = array( 'min' => $min, 'max' => $max );
					}
					break;
				}
				$values = brik_listing_list( $read( $name ) );
				if ( in_array( $def['ui'], array( 'select', 'radio', 'pills' ), true ) ) {
					$values = array_slice( $values, 0, 1 );
				}
				if ( $values ) {
					$clauses[]             = array( 'kind' => 'meta', 'key' => $def['field'], 'value' => $values );
					$state[ $def['key'] ] = $values;
				}
				break;

			case 'sort':
				$raw     = $read( $name );
				$options = brik_listing_sort_options( $def['field'] );
				if ( is_string( $raw ) && isset( $options[ $raw ] ) ) {
					$clauses[]             = array(
						'kind'     => 'sort',
						'orderby'  => $options[ $raw ][1],
						'order'    => $options[ $raw ][2],
						'meta_key' => $options[ $raw ][3], // phpcs:ignore WordPress.DB.SlowDBQuery
					);
					$state[ $def['key'] ] = $raw;
				}
				break;
		}
	}
	return array( $clauses, $state );
}

/**
 * Every node of a type in a tree, looking into global elements (library items) too.
 */
function brik_listing_nodes( array $tree, $type, $depth = 0, array &$out = array() ) {
	foreach ( $tree as $node ) {
		if ( ! is_array( $node ) || empty( $node['type'] ) ) {
			continue;
		}
		if ( $type === $node['type'] ) {
			$out[] = $node;
		}
		if ( 'global' === $node['type'] && $depth < 2 && ! empty( $node['attrs']['ref'] ) ) {
			$ref = (int) $node['attrs']['ref'];
			if ( get_post_type( $ref ) === Library::POST_TYPE ) {
				brik_listing_nodes( Data::get( $ref ), $type, $depth + 1, $out );
			}
		}
		if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
			brik_listing_nodes( $node['children'], $type, $depth, $out );
		}
	}
	return $out;
}

/**
 * Filter definitions of every listing_filter in the tree that targets a listing.
 */
function brik_listing_filters_for( Renderer $renderer, array $tree, $key ) {
	$def = Modules::get( 'listing_filter' );
	if ( ! $def ) {
		return array();
	}
	$defs = array();
	foreach ( brik_listing_nodes( $tree, 'listing_filter' ) as $node ) {
		$node  = wp_parse_args( $node, array( 'attrs' => array(), 'children' => array() ) );
		$attrs = $renderer->resolve_attrs( $node, $def );
		if ( brik_listing_key( $attrs['target'], '' ) === $key ) {
			$defs = array_merge( $defs, brik_listing_filter_defs( $attrs['filters'] ) );
		}
	}
	// Two filter bars may expose the same filter; the first definition wins.
	$seen = array();
	$out  = array();
	foreach ( $defs as $d ) {
		if ( ! isset( $seen[ $d['key'] ] ) ) {
			$seen[ $d['key'] ] = true;
			$out[]             = $d;
		}
	}
	return $out;
}

/**
 * The attributes of the listing a filter bar targets, or null.
 */
function brik_listing_target_attrs( Renderer $renderer, array $tree, $key ) {
	$def = Modules::get( 'listing' );
	foreach ( $def ? brik_listing_nodes( $tree, 'listing' ) : array() as $node ) {
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
 * URL of the current request, used as the base for pagination links.
 */
function brik_listing_current_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$url = wp_parse_url( home_url() );
	$out = ( isset( $url['scheme'] ) ? $url['scheme'] : 'http' ) . '://' . ( isset( $url['host'] ) ? $url['host'] : '' ) . ( isset( $url['port'] ) ? ':' . $url['port'] : '' );
	return $out . '/' . ltrim( $uri, '/' );
}

/**
 * Query parameters of the current request, unslashed.
 */
function brik_listing_request_params() {
	$out = array();
	foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification -- read-only filtering.
		if ( is_string( $k ) && 0 === strpos( $k, 'bf_' ) ) {
			$out[ $k ] = wp_unslash( $v );
		}
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Option lists for field filters (cached).
 * ----------------------------------------------------------------------- */

/**
 * Distinct values of a meta key for a post type with their counts.
 *
 * @return array value => count
 */
function brik_listing_meta_values( $post_type, $key ) {
	global $wpdb;
	$key = brik_listing_meta_key( $key );
	if ( '' === $key ) {
		return array();
	}
	$post_type = brik_listing_post_type( $post_type );
	$cache     = 'brik_lv_' . md5( $post_type . '|' . $key . '|' . brik_listing_cache_version() );
	$cached    = get_transient( $cache );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.meta_value AS v, COUNT(DISTINCT p.ID) AS c FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value <> '' GROUP BY pm.meta_value ORDER BY pm.meta_value ASC LIMIT 200",
			$key,
			$post_type
		)
	);
	$out = array();
	foreach ( (array) $rows as $row ) {
		$value = maybe_unserialize( $row->v );
		// Serialized lists (checkbox fields) are counted per item.
		foreach ( is_array( $value ) ? $value : array( $value ) as $v ) {
			if ( is_scalar( $v ) && '' !== (string) $v ) {
				$out[ (string) $v ] = ( isset( $out[ (string) $v ] ) ? $out[ (string) $v ] : 0 ) + (int) $row->c;
			}
		}
	}
	uksort( $out, 'strnatcasecmp' );
	set_transient( $cache, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/**
 * Smallest and largest numeric value of a meta key for a post type.
 *
 * @return array [ min, max ] (null when there are no values)
 */
function brik_listing_meta_range( $post_type, $key ) {
	global $wpdb;
	$key = brik_listing_meta_key( $key );
	if ( '' === $key ) {
		return array( null, null );
	}
	$post_type = brik_listing_post_type( $post_type );
	$cache     = 'brik_lr_' . md5( $post_type . '|' . $key . '|' . brik_listing_cache_version() );
	$cached    = get_transient( $cache );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT MIN(CAST(pm.meta_value AS DECIMAL(20,4))) AS lo, MAX(CAST(pm.meta_value AS DECIMAL(20,4))) AS hi FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value REGEXP '^-?[0-9]+(\\\\.[0-9]+)?$'",
			$key,
			$post_type
		)
	);
	$out = $row && null !== $row->lo ? array( (float) $row->lo, (float) $row->hi ) : array( null, null );
	set_transient( $cache, $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

/* -------------------------------------------------------------------------
 * Results markup.
 * ----------------------------------------------------------------------- */

/**
 * Built-in card for listings without a loop item.
 */
function brik_listing_card( WP_Post $p, array $a ) {
	$get   = static function ( $key, $default = '' ) use ( $a ) {
		return isset( $a[ $key ] ) && '' !== $a[ $key ] && null !== $a[ $key ] ? $a[ $key ] : $default;
	};
	$url   = get_permalink( $p );
	$title = esc_html( wp_strip_all_tags( get_the_title( $p ) ) );
	$list  = 'list' === $get( 'layout', 'grid' );
	$level = in_array( $get( 'title_tag', 'h3' ), array( 'h2', 'h3', 'h4', 'p' ), true ) ? $get( 'title_tag', 'h3' ) : 'h3';

	$media = '';
	if ( brik_form_bool( $get( 'card_image', true ) ) ) {
		$ratio = brik_aspect_class( $get( 'card_ratio', '16:9' ) );
		$mcls  = brik_cls( 'brik-listing-card-media relative block shrink-0 overflow-hidden bg-muted', $ratio ? $ratio : 'aspect-video', array( 'sm:aspect-auto sm:w-2/5 sm:min-h-44 border-b sm:border-b-0 sm:border-r' => $list, 'border-b' => ! $list ) );
		$img   = has_post_thumbnail( $p )
			? get_the_post_thumbnail( $p, 'medium_large', array( 'class' => 'absolute inset-0 size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]', 'alt' => '', 'loading' => 'lazy' ) )
			: '<span class="absolute inset-0 grid place-items-center text-muted-foreground/40">' . brik_icon( 'image', 'size-8' ) . '</span>';
		$media = '<a class="' . esc_attr( $mcls ) . '" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
	}

	$badges = '';
	if ( brik_form_bool( $get( 'card_terms', true ) ) ) {
		$tax = sanitize_key( (string) $get( 'card_taxonomy' ) );
		if ( ! $tax || ! is_object_in_taxonomy( $p->post_type, $tax ) ) {
			$tax = 'post' === $p->post_type ? 'category' : '';
			foreach ( $tax ? array() : get_object_taxonomies( $p->post_type, 'objects' ) as $t ) {
				if ( $t->public && 'post_format' !== $t->name ) {
					$tax = $t->name;
					break;
				}
			}
		}
		$badges = $tax ? brik_site_terms( $p, $tax, 'secondary', 2 ) : '';
	}

	$excerpt = '';
	if ( brik_form_bool( $get( 'card_excerpt', true ) ) ) {
		$text    = brik_site_excerpt( $p, max( 1, (int) $get( 'card_excerpt_length', 18 ) ) );
		$excerpt = '' !== $text ? '<p class="brik-listing-card-excerpt text-sm leading-relaxed text-muted-foreground">' . esc_html( $text ) . '</p>' : '';
	}

	$meta = '';
	if ( brik_form_bool( $get( 'card_meta', true ) ) ) {
		$meta = implode( brik_site_meta_sep( 'dot' ), brik_site_meta_parts( $p, array( 'date', 'author' ) ) );
	}
	$more = '';
	$more_text = (string) $get( 'card_more_text', __( 'Read more', 'brik-builder' ) );
	if ( '' !== trim( $more_text ) ) {
		$more = '<span class="brik-listing-card-more inline-flex items-center gap-1 text-sm font-medium text-primary" aria-hidden="true">' . brik_inline( $more_text ) . brik_icon( 'arrow-right', 'size-3.5 transition-transform group-hover:translate-x-0.5' ) . '</span>';
	}
	$footer = '' !== $meta || '' !== $more
		? '<div class="brik-listing-card-footer mt-auto flex flex-wrap items-center justify-between gap-3 pt-3"><div class="brik-listing-card-meta flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">' . $meta . '</div>' . $more . '</div>'
		: '';

	return '<article class="' . esc_attr( brik_cls( 'brik-listing-card group relative flex h-full overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-[box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:shadow-md', $list ? 'flex-col sm:flex-row' : 'flex-col' ) ) . '">'
		. $media
		. '<div class="brik-listing-card-body flex flex-1 flex-col gap-2.5 p-5">'
		. ( '' !== $badges ? '<div class="brik-listing-card-terms relative z-10">' . $badges . '</div>' : '' )
		. '<' . $level . ' class="brik-listing-card-title font-heading text-lg font-semibold leading-snug tracking-tight text-balance"><a class="outline-none after:absolute after:inset-0 focus-visible:underline" href="' . esc_url( $url ) . '">' . $title . '</a></' . $level . '>'
		. $excerpt . $footer
		. '</div></article>';
}

/**
 * Render the items, pagination and empty state of a listing.
 *
 * @param array    $a        Listing attributes.
 * @param Renderer $renderer Renderer of the page (loop items render through it).
 * @param array    $opt      key, current, page, clauses, main, base_url, canvas.
 * @return array html, total, pages, page
 */
function brik_listing_results( array $a, Renderer $renderer, array $opt ) {
	$opt = wp_parse_args(
		$opt,
		array(
			'key'      => '',
			'current'  => null,
			'page'     => 1,
			'clauses'  => array(),
			'main'     => null,
			'base_url' => '',
			'canvas'   => false,
		)
	);

	$layout     = in_array( $a['layout'], array( 'grid', 'list', 'masonry', 'carousel' ), true ) ? $a['layout'] : 'grid';
	$pagination = 'carousel' === $layout ? '' : ( in_array( $a['pagination'], array( 'numbered', 'load_more', 'infinite' ), true ) ? $a['pagination'] : '' );
	$page       = $pagination ? max( 1, (int) $opt['page'] ) : 1;

	$args = brik_listing_query_args(
		$a,
		array(
			'current' => $opt['current'],
			'page'    => $page,
			'clauses' => $opt['clauses'],
			'main'    => $opt['main'],
		)
	);
	$args  = apply_filters( 'brik/listing_query_args', $args, $a, $opt );
	$query = new WP_Query( $args );
	$ppp   = max( 1, (int) $args['posts_per_page'] );
	$total = max( 0, (int) $query->found_posts - max( 0, (int) $a['offset'] ) );
	$pages = (int) ceil( $total / $ppp );

	$loop_id = isset( $a['loop_item'] ) ? (int) $a['loop_item'] : 0;
	$tree    = $loop_id && Library::item( $loop_id ) ? Data::get( $loop_id ) : array();

	if ( ! $query->posts ) {
		// Loop item CSS still has to reach the page for results that arrive later over AJAX.
		if ( $tree && $renderer->enter( 'loop:' . $loop_id ) ) {
			$canvas           = $renderer->canvas;
			$renderer->canvas = false;
			$renderer->render_nodes( $tree );
			$renderer->canvas = $canvas;
			$renderer->leave( 'loop:' . $loop_id );
		}
		$text = '' !== trim( (string) $a['empty_message'] ) ? $a['empty_message'] : __( 'Nothing found. Try adjusting your filters.', 'brik-builder' );
		return array(
			'html'  => '<div class="brik-listing-empty flex flex-col items-center gap-3 rounded-xl border border-dashed px-6 py-14 text-center text-muted-foreground">' . brik_icon( 'search-x', 'size-8 opacity-50' ) . '<p class="text-sm">' . brik_inline( $text ) . '</p></div>',
			'total' => 0,
			'pages' => 0,
			'page'  => $page,
		);
	}

	global $post;
	$previous = $post;
	$use_loop = $tree && $renderer->enter( 'loop:' . $loop_id );
	$saved    = array( $renderer->post_id, $renderer->canvas, $renderer->style );
	$stagger  = brik_form_bool( $a['animate'] );
	$items    = '';

	foreach ( $query->posts as $i => $p ) {
		$p    = get_post( $p );
		$post = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $p );

		if ( $use_loop ) {
			// Items share the loop item's classes, so its CSS is collected from the first one only.
			$renderer->post_id = $p->ID;
			$renderer->canvas  = false;
			if ( $i > 0 ) {
				$renderer->style = new Style();
			}
			$inner = $renderer->render_nodes( $tree );
		} else {
			// Lets other card renderers take over for their post types (the shop card for products).
			$inner = apply_filters( 'brik/listing_card', null, $p, $a );
			$inner = null !== $inner ? $inner : brik_listing_card( $p, $a );
		}
		$style  = $stagger ? ' style="--brik-i:' . (int) $i . '"' : '';
		$items .= '<div class="brik-listing-item" data-post-id="' . (int) $p->ID . '"' . $style . '>' . $inner . '</div>';
	}

	list( $renderer->post_id, $renderer->canvas, $renderer->style ) = $saved;
	if ( $use_loop ) {
		$renderer->leave( 'loop:' . $loop_id );
	}
	$post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	if ( $previous instanceof WP_Post ) {
		setup_postdata( $previous );
	} else {
		wp_reset_postdata();
	}

	$cls  = brik_cls(
		'brik-listing-items',
		'brik-listing-items--' . $layout,
		array(
			'is-equal'   => brik_form_bool( $a['equal_height'] ) && 'masonry' !== $layout,
			'is-stagger' => $stagger,
		)
	);
	$html = '<div class="' . esc_attr( $cls ) . '"' . ( 'carousel' === $layout ? ' tabindex="0" role="region" aria-label="' . esc_attr__( 'Results', 'brik-builder' ) . '"' : '' ) . '>' . $items . '</div>';

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

	if ( 'carousel' === $layout && brik_form_bool( $a['arrows'] ) ) {
		$html .= '<div class="brik-listing-arrows flex justify-end gap-2">'
			. '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'icon', 'rounded-full' ) ) . '" data-brik-listing-prev aria-label="' . esc_attr__( 'Previous', 'brik-builder' ) . '">' . brik_icon( 'chevron-left' ) . '</button>'
			. '<button type="button" class="' . esc_attr( brik_button_class( 'outline', 'icon', 'rounded-full' ) ) . '" data-brik-listing-next aria-label="' . esc_attr__( 'Next', 'brik-builder' ) . '">' . brik_icon( 'chevron-right' ) . '</button>'
			. '</div>';
	}

	if ( 'numbered' === $pagination ) {
		$html .= brik_site_pagination( $page, $pages, $url );
	} elseif ( in_array( $pagination, array( 'load_more', 'infinite' ), true ) && $page < $pages ) {
		$text  = '' !== trim( (string) $a['load_more_text'] ) ? $a['load_more_text'] : __( 'Load more', 'brik-builder' );
		$html .= '<div class="brik-listing-more flex justify-center">'
			. '<a class="' . esc_attr( brik_button_class( 'outline', 'lg', 'group/more' ) ) . '" href="' . esc_url( $url( $page + 1 ) ) . '" data-brik-listing-more data-page="' . (int) ( $page + 1 ) . '">'
			. brik_icon( 'loader-circle', 'hidden size-4 animate-spin group-aria-busy/more:block' )
			. '<span>' . brik_inline( $text ) . '</span></a></div>';
	}

	return array(
		'html'  => $html,
		'total' => $total,
		'pages' => $pages,
		'page'  => $page,
	);
}

/* -------------------------------------------------------------------------
 * REST: POST brik/v1/listing
 * ----------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			Brik\Rest::NS,
			'/listing',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'brik_listing_rest',
				// Results only ever contain published posts, like the page itself.
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
	}
);

/**
 * Re-render a listing with new filter values. The listing and its filters are read from
 * the stored tree; the client only says which listing and which values.
 */
function brik_listing_rest( WP_REST_Request $r ) {
	$post_id = (int) $r['post_id'];
	$node_id = (string) $r['node_id'];
	$found   = $node_id ? Forms::locate( $post_id, $node_id, 0, array( 'listing' ) ) : null;
	if ( ! $found ) {
		return new WP_Error( 'brik_not_found', __( 'This listing is no longer available.', 'brik-builder' ), array( 'status' => 404 ) );
	}
	list( $node, $def ) = $found;

	$tree           = Data::get( $post_id );
	$renderer       = new Renderer( $post_id );
	$renderer->root = $tree;
	$attrs          = $renderer->resolve_attrs( $node, $def );
	$key            = brik_listing_key( $attrs['css_id'], $node['id'] );

	$params = array();
	foreach ( is_array( $r['filters'] ) ? $r['filters'] : array() as $k => $v ) {
		if ( is_string( $k ) && 0 === strpos( $k, 'bf_' ) && ( is_string( $v ) || is_numeric( $v ) || is_array( $v ) ) ) {
			$params[ $k ] = $v;
		}
	}

	// The post a single template shows (for "exclude current" and "related"): only published ones.
	$current = null;
	if ( (int) $r['current'] ) {
		$candidate = get_post( (int) $r['current'] );
		if ( $candidate instanceof WP_Post && 'publish' === $candidate->post_status && is_post_type_viewable( $candidate->post_type ) ) {
			$current = $candidate;
		}
	}
	if ( ! $current && ! brik_site_is_layout( $post_id ) ) {
		$current = get_post( $post_id );
	}

	$main = null;
	if ( brik_form_bool( $attrs['use_main_query'] ) && is_array( $r['main'] ) ) {
		$main = $r['main'];
	}
	$post_type = $main ? ( isset( $main['post_type'] ) ? sanitize_key( (string) $main['post_type'] ) : '' ) : brik_listing_post_type( $attrs['post_type'] );

	list( $clauses ) = brik_listing_parse( brik_listing_filters_for( $renderer, $tree, $key ), $params, $key, $post_type );

	$base = esc_url_raw( (string) $r['page_url'] );
	$base = $base && wp_validate_redirect( $base, false ) ? $base : ( brik_site_is_layout( $post_id ) ? home_url( '/' ) : (string) get_permalink( $post_id ) );

	$result = brik_listing_results(
		$attrs,
		$renderer,
		array(
			'key'      => $key,
			'current'  => $current,
			'page'     => max( 1, (int) $r['page'] ),
			'clauses'  => $clauses,
			'main'     => $main,
			'base_url' => $base,
		)
	);
	return new WP_REST_Response( $result );
}
