<?php
/**
 * MCP tools and guide section for data sources, visual queries and display conditions.
 *
 * @package Brik
 */

namespace Brik\Data;

use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Mcp {

	public static function init() {
		add_filter( 'brik/mcp_tools', array( __CLASS__, 'tools' ) );
		add_filter( 'brik/mcp_guide', array( __CLASS__, 'guide' ) );
	}

	public static function tools( $defs ) {
		$defs['list_data_sources'] = array(
			'title'       => 'List data sources',
			'description' => 'Dynamic data available for a post type: every tag usable in text/image/link attributes ({post:title}, {acf:price}, {term:category|links} …) with a live preview value, the fields a listing "query" can filter/sort on (keys, types, operators, choice values) and the display condition rule types for "visibility_rules". Call it before building a listing query or a conditional section.',
			'read_only'   => true,
			'props'       => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type to describe, e.g. "post", "product" or a custom type. Defaults to the type of post_id, else "post".',
				),
				'post_id'   => array(
					'type'        => 'integer',
					'description' => 'Preview values with this post.',
					'minimum'     => 1,
				),
			),
			'callback'    => array( __CLASS__, 'list_data_sources' ),
		);

		$defs['preview_query'] = array(
			'title'       => 'Preview a listing query',
			'description' => 'Validates a visual query (the listing module\'s "query" attribute) and returns the number of matching posts, the first items and the generated WP_Query arguments. Unknown fields or operators come back as errors naming the allowed ones.',
			'read_only'   => true,
			'props'       => array(
				'query'   => array(
					'type'        => 'object',
					'description' => '{ post_type, where: { relation: AND|OR, rules: [ { field, op, value } | nested group ] }, order: [ { by, dir } ], limit, offset, exclude_current, search, author }. See get_guide (Data section).',
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The page the listing sits on (for exclude_current and dynamic tags in values).',
					'minimum'     => 1,
				),
			),
			'required'    => array( 'query' ),
			'callback'    => array( __CLASS__, 'preview_query' ),
		);
		return $defs;
	}

	public static function list_data_sources( array $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to do this.', 'brik-builder' ) );
		}
		$post_id = isset( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			$post_id = 0;
		}
		$type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : '';
		if ( $type && ! isset( Data::post_types()[ $type ] ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_data_type', sprintf( __( 'Unknown or non-public post type "%1$s". Available: %2$s.', 'brik-builder' ), $type, implode( ', ', array_keys( Data::post_types() ) ) ) );
		}
		$tree = Sources::tree( $post_id, $type );
		$tags = array();
		$walk = static function ( array $items, $group ) use ( &$walk, &$tags ) {
			foreach ( $items as $item ) {
				if ( isset( $item['items'] ) ) {
					$walk( $item['items'], $group . ' › ' . $item['label'] );
					continue;
				}
				$row = array(
					'tag'   => ! empty( $item['input'] ) ? '{' . $item['tag'] . 'NAME}' : '{' . $item['tag'] . '}',
					'label' => $group . ' › ' . $item['label'],
					'kind'  => $item['kind'],
				);
				if ( isset( $item['preview'] ) && '' !== $item['preview'] ) {
					$row['preview'] = $item['preview'];
				}
				$tags[] = $row;
			}
		};
		foreach ( $tree['groups'] as $g ) {
			$walk( $g['items'], $g['label'] );
		}

		$payload = Rest::fields_payload( $tree['context']['post_type'] );
		$fields  = array();
		foreach ( $payload['fields'] as $f ) {
			$row = array(
				'field' => $f['key'],
				'label' => $f['label'],
				'type'  => $f['type'],
				'ops'   => array_keys( $payload['ops'][ $f['type'] ] ),
			);
			if ( ! empty( $f['options'] ) ) {
				$row['values'] = array_slice( wp_list_pluck( $f['options'], 'value' ), 0, 40 );
			}
			$fields[] = $row;
		}

		$conditions = array();
		foreach ( Conditions::types() as $key => $t ) {
			$row = array(
				'type'  => $key,
				'label' => $t['label'],
				'value' => $t['value'],
			);
			if ( ! empty( $t['ops'] ) ) {
				$row['ops'] = array_keys( $t['ops'] );
			}
			if ( ! empty( $t['options'] ) ) {
				$row['values'] = wp_list_pluck( $t['options'], 'value' );
			}
			if ( ! empty( $t['client'] ) ) {
				$row['client_side'] = true;
			}
			$conditions[] = $row;
		}

		return array(
			'preview_post'    => $tree['context'],
			'modifiers'       => $tree['modifiers'],
			'tags'            => $tags,
			'query_fields'    => $fields,
			'order_by'        => wp_list_pluck( $payload['order'], 'key' ),
			'condition_types' => $conditions,
		);
	}

	public static function preview_query( array $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to do this.', 'brik-builder' ) );
		}
		$post_id = isset( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$result  = Query::preview( $args['query'], $post_id, 10 );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		unset( $result['code'] );
		return $result;
	}

	public static function guide( $guide ) {
		return (string) $guide . <<<'MD'


## Data: dynamic tags, listing queries, display conditions

Call `list_data_sources` (post_type or post_id) to see every tag, query field and condition type for a post type, with preview values.

### Tags
Tags work in text, textarea, richtext, image and link attributes: `"text": "{post:title}"`, `"image": "{post:image}"`, `"link": { "url": "{post:url}" }`.
`{post:title|excerpt|content|date|modified|url|image|id|slug|status|comment_count|type_label|parent_title|parent_url}`,
`{author:name|first_name|bio|avatar|url|website}`, `{user:name|email|role|avatar|meta.KEY}` (current visitor; empty for guests),
`{term:TAXONOMY}` (post terms), `{meta:KEY}`, `{field:NAME}` (Brik fields), `{acf:NAME}` / `{acf:GROUP.SUB}` / `{acf:REPEATER.SUB}` (first row),
`{metabox:NAME}`, `{pods:NAME}`, `{product:price}` …, `{site:name|tagline|url|logo}`, `{option:NAME}`, `{param:URL_PARAM}`, `{now:date|time|year|Y-m-d}`.
Modifiers after `|`: images `url|id|alt`; dates `date|time|datetime|relative|iso|year`; numbers `number|int|raw`; terms/posts `list|first|links|count|url|slug`; any `raw`.
Protected meta (keys starting with "_") only renders when registered for REST or allow-listed (WooCommerce prices, SKU, stock).

### Listing query (`listing` attribute `query`)
When set it replaces post_type, taxonomy, meta_query, search, author, orderby, order, posts_per_page and offset.
```json
{ "post_type": "property",
  "where": { "relation": "AND", "rules": [
    { "field": "field:price", "op": "lt", "value": 500000 },
    { "field": "field:bedrooms", "op": "gte", "value": 3 },
    { "relation": "OR", "rules": [
      { "field": "tax:location", "op": "in", "value": ["colombo", "kandy"] },
      { "field": "post:date", "op": "last_days", "value": 30 } ] } ] },
  "order": [ { "by": "field:price", "dir": "DESC" } ],
  "limit": 12, "offset": 0, "exclude_current": true, "search": "", "author": "" }
```
Field keys: `post:title|date|modified|author|id|slug|parent|menu_order|comment_count`, `tax:TAXONOMY`, `field:NAME`, `acf:NAME`, `metabox:NAME`, `pods:NAME`, `meta:KEY`, `product:price|regular_price|sku|stock_status|rating|sales`.
Operators by field type — text: eq neq contains not_contains starts in not_in empty not_empty; number: eq neq lt lte gt gte between in not_in empty not_empty;
date: on before after between last_days next_days older_days empty not_empty (values "Y-m-d"; *_days take a number); choice: eq neq in not_in empty not_empty;
term: in all not_in exists not_exists (values are term slugs or ids); user: eq neq in not_in (ids or "current"); bool: is_true is_false; post: contains not_contains empty not_empty.
"in"/"not_in" take arrays, "between" takes [min, max]. Values may contain tags, e.g. `"{param:city}"`. Order "by" takes a field key or "rand".
Unknown fields are rejected: use `preview_query` to check a query (count, first items, WP_Query args) before saving.

### Display conditions (`visibility_rules`, any element)
```json
"visibility_rules": { "relation": "AND", "rules": [
  { "type": "user_status", "value": "logged_in" },
  { "type": "user_role", "op": "in", "value": ["customer"] },
  { "type": "cart_total", "op": "gt", "value": 100 },
  { "relation": "OR", "rules": [
    { "type": "data", "field": "acf:featured", "op": "eq", "value": "Yes" },
    { "type": "device", "op": "in", "value": ["mobile"] } ] } ] }
```
Types: user_status (logged_in|logged_out), user_role, user_id, post_type, post_id, data (field: any tag without braces, e.g. "meta:price", "post:title", "field:color"; ops eq neq gt gte lt lte contains not_contains starts in not_in before after empty not_empty),
term (taxonomy + term slugs/ids), page_template, url_param (key), referrer, cookie (key), language (codes/locales), date_range (from, to), time_of_day (from, to "HH:MM"), day_of_week (1=Mon … 7=Sun),
device (desktop|tablet|mobile) and viewport (min, max px) — these two are applied with CSS — and with WooCommerce cart_total, cart_count, cart_contains (kind product|category), purchased (product ids), product_status (in_stock|out_of_stock|on_backorder|on_sale|not_on_sale|featured).
"in"/"not_in" ops take arrays. Elements with rules are always visible in the builder. Page caches can serve one visitor's version to another; pages using user, cart, cookie or URL rules ask caches not to store them.
MD;
	}
}
