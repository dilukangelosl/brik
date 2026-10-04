<?php
/**
 * Visual query → WP_Query arguments.
 *
 *   {
 *     "post_type": "property",
 *     "where": { "relation": "AND", "rules": [
 *       { "field": "field:price", "op": "lt", "value": 500000 },
 *       { "relation": "OR", "rules": [ … ] }
 *     ] },
 *     "order": [ { "by": "field:price", "dir": "DESC" } ],
 *     "limit": 12, "offset": 0, "exclude_current": true, "search": "", "author": ""
 *   }
 *
 * Every field is checked against the source registry for the post type, so a query can only
 * touch fields that exist. Rules become meta_query / tax_query / date_query clauses (nested
 * with their relations), native post arguments, or a "brik_where" tree on post columns that is
 * compiled into prepared SQL. An OR group mixing kinds WP_Query can't combine is resolved to
 * post ids first.
 *
 * @package Brik
 */

namespace Brik\Data;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Query {

	const MAX_RULES = 50;

	const MAX_DEPTH = 4;

	const MAX_IDS = 1000;

	/** Post columns rules on post:* fields may use (brik_where). */
	const COLUMNS = array(
		'post:title'         => 'post_title',
		'post:slug'          => 'post_name',
		'post:id'            => 'ID',
		'post:author'        => 'post_author',
		'post:parent'        => 'post_parent',
		'post:menu_order'    => 'menu_order',
		'post:comment_count' => 'comment_count',
		'post:date'          => 'post_date',
		'post:modified'      => 'post_modified',
	);

	const ORDER_COLUMNS = array(
		'post:date'          => 'date',
		'post:modified'      => 'modified',
		'post:title'         => 'title',
		'post:slug'          => 'name',
		'post:id'            => 'ID',
		'post:author'        => 'author',
		'post:parent'        => 'parent',
		'post:menu_order'    => 'menu_order',
		'post:comment_count' => 'comment_count',
	);

	public static function init() {
		add_filter( 'posts_where', array( __CLASS__, 'posts_where' ), 10, 2 );
		add_filter( 'brik/attrs', array( __CLASS__, 'listing_attrs' ), 20, 3 );
		add_filter( 'brik/listing_query_args', array( __CLASS__, 'listing_args' ), 10, 3 );
	}

	/**
	 * Operators per field type: op => label.
	 */
	public static function ops() {
		$exists = array(
			'empty'     => __( 'is empty', 'brik-builder' ),
			'not_empty' => __( 'is not empty', 'brik-builder' ),
		);
		return array(
			'text'   => array(
				'eq'           => __( 'is', 'brik-builder' ),
				'neq'          => __( 'is not', 'brik-builder' ),
				'contains'     => __( 'contains', 'brik-builder' ),
				'not_contains' => __( 'does not contain', 'brik-builder' ),
				'starts'       => __( 'starts with', 'brik-builder' ),
				'in'           => __( 'is one of', 'brik-builder' ),
				'not_in'       => __( 'is none of', 'brik-builder' ),
			) + $exists,
			'number' => array(
				'eq'      => '=',
				'neq'     => '≠',
				'lt'      => '<',
				'lte'     => '≤',
				'gt'      => '>',
				'gte'     => '≥',
				'between' => __( 'between', 'brik-builder' ),
				'in'      => __( 'is one of', 'brik-builder' ),
				'not_in'  => __( 'is none of', 'brik-builder' ),
			) + $exists,
			'date'   => array(
				'on'          => __( 'is on', 'brik-builder' ),
				'before'      => __( 'is before', 'brik-builder' ),
				'after'       => __( 'is after', 'brik-builder' ),
				'between'     => __( 'is between', 'brik-builder' ),
				'last_days'   => __( 'is in the last … days', 'brik-builder' ),
				'next_days'   => __( 'is in the next … days', 'brik-builder' ),
				'older_days'  => __( 'is older than … days', 'brik-builder' ),
			) + $exists,
			'choice' => array(
				'eq'     => __( 'is', 'brik-builder' ),
				'neq'    => __( 'is not', 'brik-builder' ),
				'in'     => __( 'is any of', 'brik-builder' ),
				'not_in' => __( 'is none of', 'brik-builder' ),
			) + $exists,
			'term'   => array(
				'in'         => __( 'is any of', 'brik-builder' ),
				'all'        => __( 'is all of', 'brik-builder' ),
				'not_in'     => __( 'is none of', 'brik-builder' ),
				'exists'     => __( 'has any term', 'brik-builder' ),
				'not_exists' => __( 'has no terms', 'brik-builder' ),
			),
			'user'   => array(
				'eq'     => __( 'is', 'brik-builder' ),
				'neq'    => __( 'is not', 'brik-builder' ),
				'in'     => __( 'is any of', 'brik-builder' ),
				'not_in' => __( 'is none of', 'brik-builder' ),
			),
			'bool'   => array(
				'is_true'  => __( 'is on', 'brik-builder' ),
				'is_false' => __( 'is off', 'brik-builder' ),
			),
			'post'   => array(
				'contains'     => __( 'includes post ID', 'brik-builder' ),
				'not_contains' => __( 'does not include post ID', 'brik-builder' ),
			) + $exists,
		);
	}

	/** Operators that take a list, a range, a day count or nothing. */
	private static function value_shape( $op ) {
		if ( in_array( $op, array( 'in', 'not_in', 'all' ), true ) ) {
			return 'list';
		}
		if ( 'between' === $op ) {
			return 'range';
		}
		if ( in_array( $op, array( 'last_days', 'next_days', 'older_days' ), true ) ) {
			return 'days';
		}
		if ( in_array( $op, array( 'empty', 'not_empty', 'exists', 'not_exists', 'is_true', 'is_false' ), true ) ) {
			return 'none';
		}
		return 'single';
	}

	/* ---------------------------------------------------------------------
	 * Validation.
	 * ------------------------------------------------------------------- */

	/**
	 * Validated, normalized query or WP_Error.
	 */
	public static function normalize( $q ) {
		if ( is_string( $q ) ) {
			$q = json_decode( $q, true );
		}
		if ( ! is_array( $q ) ) {
			return new WP_Error( 'brik_query', __( 'The query must be an object.', 'brik-builder' ) );
		}
		$type = isset( $q['post_type'] ) ? sanitize_key( (string) $q['post_type'] ) : '';
		if ( ! $type || ! isset( Data::post_types()[ $type ] ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_query_type', sprintf( __( 'Unknown or non-public post type "%s".', 'brik-builder' ), $type ) );
		}
		$fields = Sources::fields( $type );
		$count  = 0;
		$where  = self::normalize_group( isset( $q['where'] ) ? $q['where'] : array(), $fields, $type, 0, $count );
		if ( is_wp_error( $where ) ) {
			return $where;
		}

		$order = array();
		foreach ( array_slice( isset( $q['order'] ) && is_array( $q['order'] ) ? ( isset( $q['order']['by'] ) ? array( $q['order'] ) : $q['order'] ) : array(), 0, 3 ) as $o ) {
			if ( ! is_array( $o ) || empty( $o['by'] ) ) {
				continue;
			}
			$by = (string) $o['by'];
			if ( 'rand' !== $by && ! isset( self::ORDER_COLUMNS[ $by ] ) && ( ! isset( $fields[ $by ] ) || empty( $fields[ $by ]['meta_key'] ) ) ) {
				/* translators: 1: field key, 2: post type */
				return new WP_Error( 'brik_query_field', sprintf( __( 'Cannot order by "%1$s" for post type "%2$s". Use list_data_sources to see the fields.', 'brik-builder' ), $by, $type ) );
			}
			$order[] = array(
				'by'  => $by,
				'dir' => isset( $o['dir'] ) && 'ASC' === strtoupper( (string) $o['dir'] ) ? 'ASC' : 'DESC',
			);
		}

		$author = isset( $q['author'] ) ? $q['author'] : '';
		if ( 'current' !== $author ) {
			$author = array_values( array_filter( array_map( 'absint', is_array( $author ) ? $author : explode( ',', (string) $author ) ) ) );
		}

		return array(
			'post_type'       => $type,
			'where'           => $where,
			'order'           => $order,
			'limit'           => isset( $q['limit'] ) && '' !== $q['limit'] ? max( 1, min( 100, (int) $q['limit'] ) ) : 10,
			'offset'          => isset( $q['offset'] ) ? max( 0, (int) $q['offset'] ) : 0,
			'exclude_current' => ! empty( $q['exclude_current'] ) && 'false' !== $q['exclude_current'],
			'search'          => isset( $q['search'] ) ? substr( sanitize_text_field( (string) $q['search'] ), 0, 100 ) : '',
			'author'          => $author,
		);
	}

	private static function normalize_group( $g, array $fields, $type, $depth, &$count ) {
		if ( ! is_array( $g ) ) {
			$g = array();
		}
		if ( $depth > self::MAX_DEPTH ) {
			return new WP_Error( 'brik_query_depth', __( 'Rule groups are nested too deeply.', 'brik-builder' ) );
		}
		$out = array(
			'relation' => isset( $g['relation'] ) && 'OR' === strtoupper( (string) $g['relation'] ) ? 'OR' : 'AND',
			'rules'    => array(),
		);
		foreach ( isset( $g['rules'] ) && is_array( $g['rules'] ) ? $g['rules'] : array() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			if ( isset( $rule['rules'] ) ) {
				$sub = self::normalize_group( $rule, $fields, $type, $depth + 1, $count );
				if ( is_wp_error( $sub ) ) {
					return $sub;
				}
				if ( $sub['rules'] ) {
					$out['rules'][] = $sub;
				}
				continue;
			}
			if ( ++$count > self::MAX_RULES ) {
				return new WP_Error( 'brik_query_size', __( 'Too many rules in one query.', 'brik-builder' ) );
			}
			$rule = self::normalize_rule( $rule, $fields, $type );
			if ( is_wp_error( $rule ) ) {
				return $rule;
			}
			if ( $rule ) {
				$out['rules'][] = $rule;
			}
		}
		return $out;
	}

	private static function normalize_rule( array $rule, array $fields, $type ) {
		$key = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		if ( '' === $key ) {
			return null; // An unfinished row in the editor.
		}
		if ( ! isset( $fields[ $key ] ) ) {
			/* translators: 1: field key, 2: post type */
			return new WP_Error( 'brik_query_field', sprintf( __( 'Unknown field "%1$s" for post type "%2$s". Use list_data_sources to see the fields.', 'brik-builder' ), $key, $type ) );
		}
		$field = $fields[ $key ];
		$ops   = self::ops()[ $field['type'] ];
		$op    = isset( $rule['op'] ) ? (string) $rule['op'] : '';
		if ( '' === $op ) {
			$op = (string) key( $ops );
		}
		if ( ! isset( $ops[ $op ] ) ) {
			/* translators: 1: operator, 2: field label, 3: allowed operators */
			return new WP_Error( 'brik_query_op', sprintf( __( 'Operator "%1$s" is not available for %2$s. Use one of: %3$s.', 'brik-builder' ), $op, $field['label'], implode( ', ', array_keys( $ops ) ) ) );
		}
		$raw   = isset( $rule['value'] ) ? $rule['value'] : '';
		$shape = self::value_shape( $op );
		$clean = static function ( $v ) {
			return is_scalar( $v ) ? substr( sanitize_text_field( (string) $v ), 0, 200 ) : '';
		};
		if ( 'list' === $shape ) {
			$value = array_values( array_filter( array_map( $clean, is_array( $raw ) ? $raw : explode( ',', (string) $raw ) ), 'strlen' ) );
			$value = array_slice( array_map( 'trim', $value ), 0, 100 );
		} elseif ( 'range' === $shape ) {
			$value = is_array( $raw ) ? array_values( $raw ) : explode( ',', (string) $raw );
			$value = array( $clean( isset( $value[0] ) ? trim( (string) $value[0] ) : '' ), $clean( isset( $value[1] ) ? trim( (string) $value[1] ) : '' ) );
		} elseif ( 'days' === $shape ) {
			$value = max( 0, min( 36500, (int) ( is_scalar( $raw ) ? $raw : 0 ) ) );
		} elseif ( 'none' === $shape ) {
			$value = null;
		} else {
			$value = $clean( is_array( $raw ) ? reset( $raw ) : $raw );
		}
		return array(
			'field' => $key,
			'op'    => $op,
			'value' => $value,
		);
	}

	/* ---------------------------------------------------------------------
	 * Translation.
	 * ------------------------------------------------------------------- */

	/**
	 * WP_Query arguments for a query, or WP_Error.
	 *
	 * @param array|string $q   Query.
	 * @param array        $opt current (WP_Post the page shows, for exclude_current),
	 *                          post_id (resolves dynamic tags in values).
	 */
	public static function args( $q, array $opt = array() ) {
		$n = self::normalize( $q );
		if ( is_wp_error( $n ) ) {
			return $n;
		}
		$opt = wp_parse_args(
			$opt,
			array(
				'current' => null,
				'post_id' => 0,
			)
		);

		$fields = Sources::fields( $n['post_type'] );
		$ctx    = array(
			'fields'    => $fields,
			'post_type' => $n['post_type'],
			'post_id'   => (int) $opt['post_id'],
		);
		$args   = array(
			'post_type'           => $n['post_type'],
			'post_status'         => 'publish',
			'posts_per_page'      => $n['limit'],
			'ignore_sticky_posts' => true,
		);
		if ( $n['offset'] ) {
			$args['offset'] = $n['offset'];
		}

		$parts = self::group_parts( $n['where'], $ctx );
		$args  = self::assemble( $args, $parts );

		if ( '' !== $n['search'] ) {
			$args['s'] = self::resolve_value( $n['search'], $ctx );
		}
		if ( 'current' === $n['author'] ) {
			$args = self::merge_in( $args, 'author__in', array( get_current_user_id() ) );
		} elseif ( $n['author'] ) {
			$args = self::merge_in( $args, 'author__in', $n['author'] );
		}
		if ( $n['exclude_current'] && $opt['current'] instanceof \WP_Post ) {
			$args['post__not_in'] = array_values( array_unique( array_merge( isset( $args['post__not_in'] ) ? $args['post__not_in'] : array(), array( (int) $opt['current']->ID ) ) ) );
		}

		return self::order( $args, $n['order'], $fields );
	}

	/**
	 * Parts (AND-ed) for a group. A part is [ family => meta|tax|date|sql|args|ids, clause, sql ].
	 */
	private static function group_parts( array $g, array $ctx ) {
		$children = array();
		foreach ( $g['rules'] as $rule ) {
			$children[] = isset( $rule['rules'] ) ? self::group_parts( $rule, $ctx ) : array( self::rule_part( $rule, $ctx ) );
		}
		if ( 'AND' === $g['relation'] || count( $children ) < 2 ) {
			return $children ? array_merge( ...$children ) : array();
		}
		return array( self::either( $children, $ctx ) );
	}

	/**
	 * One part matching any of the children (each a list of AND-ed parts).
	 */
	private static function either( array $children, array $ctx ) {
		// All children in one nestable family: nest with relation OR.
		foreach ( array( 'meta', 'tax', 'date' ) as $family ) {
			$clauses = array();
			foreach ( $children as $parts ) {
				$same = array_filter(
					$parts,
					static function ( $p ) use ( $family ) {
						return $p['family'] === $family;
					}
				);
				if ( ! $parts || count( $same ) !== count( $parts ) ) {
					$clauses = null;
					break;
				}
				$clauses[] = 1 === count( $parts ) ? $parts[0]['clause'] : array_merge( array( 'relation' => 'AND' ), wp_list_pluck( $parts, 'clause' ) );
			}
			if ( $clauses ) {
				return array(
					'family' => $family,
					'clause' => array_merge( array( 'relation' => 'OR' ), $clauses ),
					'sql'    => null,
				);
			}
		}

		// Post columns (and post dates) can always be expressed in SQL.
		$sql = array( 'relation' => 'OR' );
		foreach ( $children as $parts ) {
			$group = array( 'relation' => 'AND' );
			foreach ( $parts as $p ) {
				if ( empty( $p['sql'] ) ) {
					$sql = null;
					break 2;
				}
				$group[] = $p['sql'];
			}
			$sql[] = $group;
		}
		if ( $sql ) {
			return array(
				'family' => 'sql',
				'clause' => $sql,
				'sql'    => $sql,
			);
		}

		// Mixed kinds: find each branch's posts, then match any of them.
		$ids = array();
		foreach ( $children as $parts ) {
			$args = self::assemble(
				array(
					'post_type'              => $ctx['post_type'],
					'post_status'            => 'publish',
					'posts_per_page'         => self::MAX_IDS,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				),
				$parts
			);
			$ids  = array_merge( $ids, ( new \WP_Query( $args ) )->posts );
		}
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		return array(
			'family' => 'ids',
			'clause' => $ids ? $ids : array( 0 ),
			'sql'    => array(
				'column'  => 'ID',
				'compare' => 'IN',
				'value'   => $ids ? $ids : array( 0 ),
				'numeric' => true,
			),
		);
	}

	private static function assemble( array $args, array $parts ) {
		$by = array();
		foreach ( $parts as $p ) {
			$by[ $p['family'] ][] = $p;
		}
		foreach ( array(
			'meta' => 'meta_query',
			'tax'  => 'tax_query',
			'date' => 'date_query',
			'sql'  => 'brik_where',
		) as $family => $arg ) {
			if ( ! empty( $by[ $family ] ) ) {
				$args[ $arg ] = array_merge( array( 'relation' => 'AND' ), wp_list_pluck( $by[ $family ], 'clause' ) );
			}
		}
		foreach ( isset( $by['args'] ) ? $by['args'] : array() as $p ) {
			foreach ( $p['clause'] as $key => $value ) {
				if ( '__in' === substr( $key, -4 ) ) {
					$args = self::merge_in( $args, $key, $value );
				} elseif ( '__not_in' === substr( $key, -8 ) ) {
					$args[ $key ] = array_values( array_unique( array_merge( isset( $args[ $key ] ) ? $args[ $key ] : array(), $value ) ) );
				} else {
					$args[ $key ] = $value;
				}
			}
		}
		foreach ( isset( $by['ids'] ) ? $by['ids'] : array() as $p ) {
			$args = self::merge_in( $args, 'post__in', $p['clause'] );
		}
		return $args;
	}

	/**
	 * Add an "__in" constraint; two constraints on the same key must both hold.
	 */
	private static function merge_in( array $args, $key, array $values ) {
		$values = array_values( array_unique( $values ) );
		if ( isset( $args[ $key ] ) ) {
			$values = array_values( array_intersect( $args[ $key ], $values ) );
			if ( ! $values ) {
				$values = 'post__in' === $key ? array( 0 ) : array( -1 );
			}
		}
		$args[ $key ] = $values;
		return $args;
	}

	/**
	 * Values may hold dynamic tags ("{param:city}", "{post:id}").
	 */
	private static function resolve_value( $v, array $ctx ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $item ) {
				$out[] = self::resolve_value( $item, $ctx );
			}
			return $out;
		}
		if ( ! is_string( $v ) || false === strpos( $v, '{' ) ) {
			return $v;
		}
		return trim( html_entity_decode( wp_strip_all_tags( \Brik\Dynamic::replace( $v, $ctx['post_id'] ) ), ENT_QUOTES, 'UTF-8' ) );
	}

	private static function rule_part( array $rule, array $ctx ) {
		$field = $ctx['fields'][ $rule['field'] ];
		$op    = $rule['op'];
		$value = self::resolve_value( $rule['value'], $ctx );
		$key   = $rule['field'];

		if ( 'date' === $field['type'] && in_array( $key, array( 'post:date', 'post:modified' ), true ) ) {
			return self::date_part( $key, $op, $value );
		}
		if ( isset( self::COLUMNS[ $key ] ) ) {
			return self::column_part( $key, $field, $op, $value );
		}
		if ( 'term' === $field['type'] ) {
			return self::tax_part( substr( $key, 4 ), $op, $value );
		}
		return self::meta_part( $field, $op, $value );
	}

	/**
	 * Local date bounds for a date operator: [ after, before ] as "Y-m-d H:i:s" (or null).
	 */
	private static function date_bounds( $op, $value ) {
		$day = static function ( $v, $end = false ) {
			$ts = Tags::timestamp( $v );
			if ( ! $ts ) {
				return null;
			}
			$has_time = (bool) preg_match( '/\d{1,2}:\d{2}/', (string) $v );
			return wp_date( $has_time ? 'Y-m-d H:i:s' : ( $end ? 'Y-m-d 23:59:59' : 'Y-m-d 00:00:00' ), $ts );
		};
		$now = time();
		switch ( $op ) {
			case 'on':
				return array( $day( $value ), $day( $value, true ) );
			case 'before':
				$b = $day( $value );
				return array( null, $b ? wp_date( 'Y-m-d H:i:s', strtotime( $b . ' -1 second' ) ) : null );
			case 'after':
				$a = $day( $value, true );
				return array( $a ? wp_date( 'Y-m-d H:i:s', strtotime( $a . ' +1 second' ) ) : null, null );
			case 'between':
				return array( $day( $value[0] ), $day( $value[1], true ) );
			case 'last_days':
				return array( wp_date( 'Y-m-d 00:00:00', $now - (int) $value * DAY_IN_SECONDS ), wp_date( 'Y-m-d H:i:s', $now ) );
			case 'next_days':
				return array( wp_date( 'Y-m-d H:i:s', $now ), wp_date( 'Y-m-d 23:59:59', $now + (int) $value * DAY_IN_SECONDS ) );
			case 'older_days':
				return array( null, wp_date( 'Y-m-d 00:00:00', $now - (int) $value * DAY_IN_SECONDS ) );
		}
		return array( null, null );
	}

	private static function date_part( $key, $op, $value ) {
		$column = self::COLUMNS[ $key ];
		if ( in_array( $op, array( 'empty', 'not_empty' ), true ) ) {
			// Posts always have dates.
			$sql = array(
				'column'  => $column,
				'compare' => 'empty' === $op ? '=' : '!=',
				'value'   => '0000-00-00 00:00:00',
			);
			return array(
				'family' => 'sql',
				'clause' => $sql,
				'sql'    => $sql,
			);
		}
		list( $after, $before ) = self::date_bounds( $op, $value );
		$clause                 = array(
			'column'    => $column,
			'inclusive' => true,
		);
		$sql                    = array( 'relation' => 'AND' );
		if ( $after ) {
			$clause['after'] = $after;
			$sql[]           = array(
				'column'  => $column,
				'compare' => '>=',
				'value'   => $after,
			);
		}
		if ( $before ) {
			$clause['before'] = $before;
			$sql[]            = array(
				'column'  => $column,
				'compare' => '<=',
				'value'   => $before,
			);
		}
		if ( ! $after && ! $before ) {
			// An incomplete date matches nothing rather than everything.
			$clause = array(
				'column' => $column,
				'year'   => 1000,
			);
			$sql[]  = array(
				'column'  => 'ID',
				'compare' => '=',
				'value'   => 0,
				'numeric' => true,
			);
		}
		return array(
			'family' => 'date',
			'clause' => $clause,
			'sql'    => $sql,
		);
	}

	private static function column_part( $key, array $field, $op, $value ) {
		$column  = self::COLUMNS[ $key ];
		$numeric = 'post:title' !== $key && 'post:slug' !== $key;
		if ( 'post:author' === $key ) {
			$value = is_array( $value ) ? $value : array( $value );
			$value = array_map(
				static function ( $v ) {
					return 'current' === $v ? get_current_user_id() : (int) $v;
				},
				$value
			);
			$value = in_array( $op, array( 'eq', 'neq' ), true ) ? $value[0] : $value;
		}
		$cast = static function ( $v ) use ( $numeric ) {
			return $numeric ? 0 + ( is_numeric( $v ) ? $v : 0 ) : (string) $v;
		};
		$like = static function ( $v, $pattern ) {
			global $wpdb;
			return str_replace( '*', $wpdb->esc_like( (string) $v ), $pattern );
		};

		$sql = array(
			'column'  => $column,
			'numeric' => $numeric,
		);
		switch ( $op ) {
			case 'eq':
			case 'neq':
			case 'lt':
			case 'lte':
			case 'gt':
			case 'gte':
				$map            = array(
					'eq'  => '=',
					'neq' => '!=',
					'lt'  => '<',
					'lte' => '<=',
					'gt'  => '>',
					'gte' => '>=',
				);
				$sql['compare'] = $map[ $op ];
				$sql['value']   = $cast( $value );
				break;
			case 'contains':
			case 'not_contains':
				$sql['compare'] = 'contains' === $op ? 'LIKE' : 'NOT LIKE';
				$sql['value']   = $like( $value, '%*%' );
				$sql['numeric'] = false;
				break;
			case 'starts':
				$sql['compare'] = 'LIKE';
				$sql['value']   = $like( $value, '*%' );
				$sql['numeric'] = false;
				break;
			case 'in':
			case 'not_in':
				$sql['compare'] = 'in' === $op ? 'IN' : 'NOT IN';
				$sql['value']   = array_map( $cast, (array) $value );
				break;
			case 'between':
				$sql['compare'] = 'BETWEEN';
				$sql['value']   = array_map( $cast, $value );
				break;
			case 'empty':
			case 'not_empty':
				$sql['compare'] = 'empty' === $op ? '=' : '!=';
				$sql['value']   = $numeric ? 0 : '';
				break;
		}

		// Native arguments where WP_Query has them (they read better in exported code).
		$native = null;
		$list   = array_map( $cast, (array) $value );
		$natives = array(
			'post:id'     => array( 'post__in', 'post__not_in' ),
			'post:author' => array( 'author__in', 'author__not_in' ),
			'post:parent' => array( 'post_parent__in', 'post_parent__not_in' ),
			'post:slug'   => array( 'post_name__in', null ),
		);
		if ( isset( $natives[ $key ] ) && in_array( $op, array( 'eq', 'in' ), true ) ) {
			$native = array( $natives[ $key ][0] => $list );
		} elseif ( isset( $natives[ $key ] ) && $natives[ $key ][1] && in_array( $op, array( 'neq', 'not_in' ), true ) ) {
			$native = array( $natives[ $key ][1] => $list );
		} elseif ( 'post:title' === $key && 'eq' === $op ) {
			$native = array( 'title' => (string) $value );
		} elseif ( 'post:comment_count' === $key && in_array( $op, array( 'eq', 'neq', 'lt', 'lte', 'gt', 'gte' ), true ) ) {
			$native = array(
				'comment_count' => array(
					'value'   => (int) $value,
					'compare' => $sql['compare'],
				),
			);
		}

		return array(
			'family' => $native ? 'args' : 'sql',
			'clause' => $native ? $native : $sql,
			'sql'    => $sql,
		);
	}

	private static function tax_part( $taxonomy, $op, $value ) {
		$terms  = array_values( (array) $value );
		$by_id  = $terms && count( array_filter( $terms, 'is_numeric' ) ) === count( $terms );
		$clause = array( 'taxonomy' => $taxonomy );
		switch ( $op ) {
			case 'exists':
			case 'not_exists':
				$clause['operator'] = 'exists' === $op ? 'EXISTS' : 'NOT EXISTS';
				break;
			default:
				$clause['field']    = $by_id ? 'term_id' : 'slug';
				$clause['terms']    = $by_id ? array_map( 'intval', $terms ) : array_map( 'sanitize_title', $terms );
				$clause['operator'] = 'all' === $op ? 'AND' : ( 'not_in' === $op ? 'NOT IN' : 'IN' );
				if ( ! $terms ) {
					$clause['terms'] = array( 0 );
					$clause['field'] = 'term_id';
				}
		}
		return array(
			'family' => 'tax',
			'clause' => $clause,
			'sql'    => null,
		);
	}

	private static function meta_part( array $field, $op, $value ) {
		global $wpdb;
		$key      = $field['meta_key'];
		$type     = $field['type'];
		$multiple = ! empty( $field['multiple'] );
		$cast     = 'number' === $type ? 'DECIMAL(20,6)' : ( 'date' === $type ? 'DATE' : 'CHAR' );
		if ( 'date' === $type && isset( $field['date_format'] ) && 'Y-m-d H:i:s' === $field['date_format'] ) {
			$cast = 'DATETIME';
		}
		$clause = static function ( $compare, $v = null, $t = null ) use ( $key, $cast ) {
			$c = array(
				'key'     => $key,
				'compare' => $compare,
			);
			if ( null !== $v ) {
				$c['value'] = $v;
			}
			if ( ! in_array( $compare, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
				$c['type'] = $t ? $t : $cast;
			}
			return $c;
		};
		// Lists are stored serialized: a value is "in" the list when its quoted form appears.
		// (WP_Meta_Query escapes LIKE values and adds the wildcards itself.)
		$has = static function ( $v, $not = false ) use ( $clause ) {
			$quoted = $clause( $not ? 'NOT LIKE' : 'LIKE', '"' . (string) $v . '"', 'CHAR' );
			if ( ! is_numeric( $v ) ) {
				return $quoted;
			}
			$int = $clause( $not ? 'NOT LIKE' : 'LIKE', 'i:' . (int) $v . ';', 'CHAR' );
			return array( 'relation' => $not ? 'AND' : 'OR', $quoted, $int );
		};
		// A list without the value includes posts that have no list at all.
		$missing_or = static function ( $c ) use ( $clause ) {
			return array(
				'relation' => 'OR',
				$clause( 'NOT EXISTS' ),
				$c,
			);
		};
		$part = static function ( $c ) {
			return array(
				'family' => 'meta',
				'clause' => $c,
				'sql'    => null,
			);
		};

		if ( 'date' === $type && in_array( $op, array( 'on', 'before', 'after', 'between', 'last_days', 'next_days', 'older_days' ), true ) ) {
			list( $after, $before ) = self::date_bounds( $op, $value );
			$fmt                    = 'DATETIME' === $cast ? 'Y-m-d H:i:s' : 'Y-m-d';
			$a                      = $after ? gmdate( $fmt, strtotime( $after ) ) : null;
			$b                      = $before ? gmdate( $fmt, strtotime( $before ) ) : null;
			if ( $a && $b ) {
				return $part( $clause( 'BETWEEN', array( $a, $b ) ) );
			}
			if ( $a ) {
				return $part( $clause( '>=', $a ) );
			}
			if ( $b ) {
				return $part( $clause( '<=', $b ) );
			}
			return $part( $clause( '=', '__brik_never__', 'CHAR' ) );
		}

		switch ( $op ) {
			case 'eq':
				return $part( $multiple ? $has( $value ) : $clause( '=', $value ) );
			case 'neq':
				return $part( $multiple ? $missing_or( $has( $value, true ) ) : $clause( '!=', $value ) );
			case 'lt':
				return $part( $clause( '<', $value ) );
			case 'lte':
				return $part( $clause( '<=', $value ) );
			case 'gt':
				return $part( $clause( '>', $value ) );
			case 'gte':
				return $part( $clause( '>=', $value ) );
			case 'contains':
			case 'not_contains':
				if ( 'post' === $type ) {
					return $part( 'contains' === $op ? $has( $value ) : $missing_or( $has( $value, true ) ) );
				}
				return $part( $clause( 'contains' === $op ? 'LIKE' : 'NOT LIKE', (string) $value, 'CHAR' ) );
			case 'starts':
				return $part( $clause( 'REGEXP', '^' . preg_quote( (string) $value, '' ), 'CHAR' ) );
			case 'in':
			case 'not_in':
				if ( $multiple ) {
					$group = array( 'relation' => 'in' === $op ? 'OR' : 'AND' );
					foreach ( (array) $value as $v ) {
						$group[] = $has( $v, 'not_in' === $op );
					}
					return $part( 'not_in' === $op ? $missing_or( $group ) : $group );
				}
				return $part( $clause( 'in' === $op ? 'IN' : 'NOT IN', $value ? (array) $value : array( '__brik_never__' ) ) );
			case 'between':
				return $part( $clause( 'BETWEEN', $value ) );
			case 'empty':
				return $part(
					array(
						'relation' => 'OR',
						$clause( 'NOT EXISTS' ),
						$clause( '=', '', 'CHAR' ),
						$clause( '=', 'a:0:{}', 'CHAR' ),
					)
				);
			case 'not_empty':
				return $part(
					array(
						'relation' => 'AND',
						$clause( '!=', '', 'CHAR' ),
						$clause( '!=', 'a:0:{}', 'CHAR' ),
					)
				);
			case 'is_true':
				return $part( $clause( 'IN', array( '1', 'true', 'on', 'yes' ), 'CHAR' ) );
			case 'is_false':
				return $part(
					array(
						'relation' => 'OR',
						$clause( 'NOT EXISTS' ),
						$clause( 'NOT IN', array( '1', 'true', 'on', 'yes' ), 'CHAR' ),
					)
				);
		}
		return $part( $clause( 'EXISTS' ) );
	}

	private static function order( array $args, array $order, array $fields ) {
		if ( ! $order ) {
			$args['orderby'] = array( 'date' => 'DESC' );
			return $args;
		}
		$orderby = array();
		foreach ( $order as $i => $o ) {
			if ( 'rand' === $o['by'] ) {
				$args['orderby'] = 'rand';
				return $args;
			}
			if ( isset( self::ORDER_COLUMNS[ $o['by'] ] ) ) {
				$orderby[ self::ORDER_COLUMNS[ $o['by'] ] ] = $o['dir'];
				continue;
			}
			$field = $fields[ $o['by'] ];
			$name  = 'brik_order_' . $i;
			$cast  = 'number' === $field['type'] ? 'DECIMAL(20,6)' : ( 'date' === $field['type'] ? 'DATE' : 'CHAR' );
			// Posts without the field still show up (sorted last when descending).
			$sort = array(
				'relation' => 'OR',
				$name      => array(
					'key'     => $field['meta_key'],
					'compare' => 'EXISTS',
					'type'    => $cast,
				),
				array(
					'key'     => $field['meta_key'],
					'compare' => 'NOT EXISTS',
				),
			);
			$args['meta_query'] = isset( $args['meta_query'] ) ? array_merge( $args['meta_query'], array( $sort ) ) : array(
				'relation' => 'AND',
				$sort,
			);
			$orderby[ $name ] = $o['dir'];
		}
		if ( ! isset( $orderby['date'] ) && ! isset( $orderby['ID'] ) ) {
			$orderby['date'] = 'DESC'; // Stable ties.
		}
		$args['orderby'] = $orderby;
		return $args;
	}

	/* ---------------------------------------------------------------------
	 * SQL for post columns.
	 * ------------------------------------------------------------------- */

	public static function posts_where( $where, $query ) {
		$tree = $query instanceof \WP_Query ? $query->get( 'brik_where' ) : null;
		if ( ! $tree || ! is_array( $tree ) ) {
			return $where;
		}
		$sql = self::compile( $tree, 0 );
		return '' === $sql ? $where : $where . ' AND ( ' . $sql . ' )';
	}

	/**
	 * Prepared SQL for a brik_where tree. Columns and operators are allow-listed; every value
	 * goes through $wpdb->prepare().
	 */
	public static function compile( array $node, $depth ) {
		global $wpdb;
		if ( $depth > 8 ) {
			return '0=1';
		}
		if ( isset( $node['column'] ) ) {
			$columns = array_values( self::COLUMNS );
			$ops     = array( '=', '!=', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN' );
			$compare = isset( $node['compare'] ) ? strtoupper( (string) $node['compare'] ) : '=';
			if ( ! in_array( $node['column'], $columns, true ) || ! in_array( $compare, $ops, true ) || ! array_key_exists( 'value', $node ) ) {
				return '0=1';
			}
			$col = $wpdb->posts . '.' . $node['column'];
			$ph  = ! empty( $node['numeric'] ) ? '%f' : '%s';
			if ( in_array( $compare, array( 'IN', 'NOT IN' ), true ) ) {
				$values = array_values( (array) $node['value'] );
				if ( ! $values ) {
					return 'IN' === $compare ? '0=1' : '1=1';
				}
				// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders
				return $wpdb->prepare( "$col $compare (" . implode( ',', array_fill( 0, count( $values ), $ph ) ) . ')', $values );
			}
			if ( 'BETWEEN' === $compare ) {
				$values = array_values( (array) $node['value'] );
				if ( count( $values ) < 2 ) {
					return '0=1';
				}
				// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders
				return $wpdb->prepare( "$col BETWEEN $ph AND $ph", $values[0], $values[1] );
			}
			$value = is_scalar( $node['value'] ) ? $node['value'] : '';
			// phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders
			return $wpdb->prepare( "$col $compare $ph", $value );
		}
		$relation = isset( $node['relation'] ) && 'OR' === strtoupper( (string) $node['relation'] ) ? 'OR' : 'AND';
		$parts    = array();
		foreach ( $node as $k => $child ) {
			if ( 'relation' !== $k && is_array( $child ) ) {
				$sql = self::compile( $child, $depth + 1 );
				if ( '' !== $sql ) {
					$parts[] = '(' . $sql . ')';
				}
			}
		}
		return implode( ' ' . $relation . ' ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Preview and export.
	 * ------------------------------------------------------------------- */

	/**
	 * Count and the first items for a query.
	 */
	public static function preview( $q, $post_id = 0, $sample = 5 ) {
		$current = $post_id ? get_post( $post_id ) : null;
		$args    = self::args(
			$q,
			array(
				'current' => $current,
				'post_id' => (int) $post_id,
			)
		);
		if ( is_wp_error( $args ) ) {
			return $args;
		}
		$run                   = $args;
		$run['posts_per_page'] = max( 1, min( (int) $sample, (int) $args['posts_per_page'] ) );
		$query                 = new \WP_Query( $run );
		$offset                = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
		$total                 = max( 0, (int) $query->found_posts - $offset );
		$items                 = array();
		foreach ( $query->posts as $p ) {
			$items[] = array(
				'id'    => (int) $p->ID,
				'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ),
				'url'   => (string) get_permalink( $p ),
				'image' => (string) get_the_post_thumbnail_url( $p, 'thumbnail' ),
			);
		}
		return array(
			'count' => min( $total, (int) $args['posts_per_page'] ),
			'total' => $total,
			'items' => $items,
			'args'  => $args,
			'code'  => self::code( $args ),
		);
	}

	/**
	 * The arguments as PHP code.
	 */
	public static function code( array $args ) {
		$code = '$args = ' . self::export( $args, 0 ) . ";\n\$query = new WP_Query( \$args );\n";
		if ( isset( $args['brik_where'] ) ) {
			$code = "// brik_where filters post columns; it is applied by Brik's posts_where filter.\n" . $code;
		}
		return $code;
	}

	private static function export( $v, $level ) {
		if ( is_array( $v ) ) {
			if ( ! $v ) {
				return 'array()';
			}
			$list = array_keys( $v ) === range( 0, count( $v ) - 1 );
			$pad  = str_repeat( "\t", $level + 1 );
			$out  = array();
			foreach ( $v as $k => $item ) {
				$out[] = $pad . ( $list ? '' : var_export( $k, true ) . ' => ' ) . self::export( $item, $level + 1 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
			}
			return "array(\n" . implode( ",\n", $out ) . ",\n" . str_repeat( "\t", $level ) . ')';
		}
		if ( is_bool( $v ) ) {
			return $v ? 'true' : 'false';
		}
		if ( null === $v ) {
			return 'null';
		}
		return var_export( $v, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	/* ---------------------------------------------------------------------
	 * Listing module.
	 * ------------------------------------------------------------------- */

	private static function has_query( $attrs ) {
		return isset( $attrs['query'] ) && is_array( $attrs['query'] ) && ! empty( $attrs['query']['post_type'] );
	}

	/**
	 * A listing with a visual query: point the basic settings at it (post type, page size,
	 * offset) and switch the rest off, so filters and pagination keep working.
	 */
	public static function listing_attrs( $attrs, $node, $renderer ) {
		if ( ! is_array( $node ) || ! isset( $node['type'] ) || 'listing' !== $node['type'] || ! self::has_query( $attrs ) ) {
			return $attrs;
		}
		$n = self::normalize( $attrs['query'] );
		if ( is_wp_error( $n ) ) {
			return $attrs;
		}
		$attrs['post_type']       = $n['post_type'];
		$attrs['posts_per_page']  = $n['limit'];
		$attrs['offset']          = $n['offset'];
		$attrs['exclude_current'] = $n['exclude_current'];
		foreach ( array( 'use_main_query', 'taxonomy', 'terms', 'search', 'author', 'author_ids', 'related' ) as $key ) {
			$attrs[ $key ] = '';
		}
		$attrs['meta_query'] = array();
		$attrs['orderby']    = 'date';
		return $attrs;
	}

	public static function listing_args( $args, $a, $opt ) {
		if ( ! self::has_query( $a ) ) {
			return $args;
		}
		$current = isset( $opt['current'] ) && $opt['current'] instanceof \WP_Post ? $opt['current'] : null;
		$q       = self::args(
			$a['query'],
			array(
				'current' => $current,
				'post_id' => $current ? $current->ID : 0,
			)
		);
		if ( is_wp_error( $q ) ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		$sorted   = false;
		$searched = false;
		foreach ( isset( $opt['clauses'] ) ? (array) $opt['clauses'] : array() as $clause ) {
			$kind     = isset( $clause['kind'] ) ? $clause['kind'] : '';
			$sorted   = $sorted || 'sort' === $kind;
			$searched = $searched || 'search' === $kind;
		}

		foreach ( array( 'meta_query', 'tax_query', 'date_query' ) as $key ) {
			if ( empty( $q[ $key ] ) ) {
				continue;
			}
			$args[ $key ] = empty( $args[ $key ] ) ? $q[ $key ] : array(
				'relation' => 'AND',
				$args[ $key ],
				$q[ $key ],
			);
		}
		$args['post_type'] = $q['post_type'];
		foreach ( array( 'brik_where', 'title', 'comment_count' ) as $key ) {
			if ( isset( $q[ $key ] ) ) {
				$args[ $key ] = $q[ $key ];
			}
		}
		foreach ( $q as $key => $value ) {
			if ( '__in' === substr( $key, -4 ) ) {
				$args = self::merge_in( $args, $key, $value );
			} elseif ( '__not_in' === substr( $key, -8 ) ) {
				$args[ $key ] = array_values( array_unique( array_merge( isset( $args[ $key ] ) ? (array) $args[ $key ] : array(), $value ) ) );
			}
		}
		if ( ! $searched && isset( $q['s'] ) ) {
			$args['s'] = $q['s'];
		}
		if ( ! $sorted ) {
			$args['orderby'] = $q['orderby'];
			unset( $args['order'], $args['meta_key'] );
		}
		return $args;
	}
}
