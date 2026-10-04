<?php
namespace Brik\Design;

use Brik\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Scans every stored Brik tree (pages, posts, templates, library items) for class and
 * component usage, and applies site-wide tree rewrites.
 */
final class Usage {

	/**
	 * Stored trees: [ post_id => nodes ].
	 */
	public static function documents() {
		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type <> 'revision' AND p.post_status NOT IN ('trash','auto-draft')",
				Data::META
			)
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$nodes = json_decode( $row->meta_value, true );
			if ( is_array( $nodes ) ) {
				$out[ (int) $row->post_id ] = $nodes;
			}
		}
		return $out;
	}

	public static function walk( array $nodes, callable $fn ) {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$fn( $node );
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::walk( $node['children'], $fn );
			}
		}
	}

	/**
	 * Usage counts: { classes: { id: n }, components: { ref: { count, posts: [ { id, title } ] } } }.
	 */
	public static function scan() {
		$classes    = array();
		$per_post   = array();
		$components = array();
		foreach ( self::documents() as $post_id => $nodes ) {
			self::walk(
				$nodes,
				static function ( $node ) use ( $post_id, &$classes, &$per_post, &$components ) {
					if ( ! empty( $node['attrs']['classes'] ) && is_array( $node['attrs']['classes'] ) ) {
						foreach ( $node['attrs']['classes'] as $id ) {
							if ( is_string( $id ) ) {
								$classes[ $id ]              = isset( $classes[ $id ] ) ? $classes[ $id ] + 1 : 1;
								$per_post[ $id ][ $post_id ] = isset( $per_post[ $id ][ $post_id ] ) ? $per_post[ $id ][ $post_id ] + 1 : 1;
							}
						}
					}
					if ( isset( $node['type'] ) && 'global' === $node['type'] && ! empty( $node['attrs']['ref'] ) ) {
						$ref = (int) $node['attrs']['ref'];
						if ( ! isset( $components[ $ref ] ) ) {
							$components[ $ref ] = array(
								'count' => 0,
								'posts' => array(),
							);
						}
						++$components[ $ref ]['count'];
						$components[ $ref ]['posts'][ $post_id ] = isset( $components[ $ref ]['posts'][ $post_id ] ) ? $components[ $ref ]['posts'][ $post_id ] + 1 : 1;
					}
				}
			);
		}
		foreach ( $components as &$c ) {
			$c['posts'] = array_map(
				static function ( $id, $count ) {
					return array(
						'id'    => $id,
						'title' => get_the_title( $id ),
						'type'  => get_post_type( $id ),
						'count' => $count,
					);
				},
				array_keys( $c['posts'] ),
				array_values( $c['posts'] )
			);
		}
		unset( $c );
		return array(
			'classes'     => $classes,
			'class_posts' => $per_post,
			'components'  => $components,
		);
	}

	/**
	 * Run $fn over every node of every stored tree; trees whose nodes changed are saved.
	 * Returns the number of documents changed.
	 */
	public static function map_nodes( callable $fn ) {
		$changed = 0;
		foreach ( self::documents() as $post_id => $nodes ) {
			$new = self::map( $nodes, $fn );
			if ( $new !== $nodes ) {
				update_post_meta( $post_id, Data::META, wp_slash( wp_json_encode( $new ) ) );
				clean_post_cache( $post_id );
				++$changed;
			}
		}
		return $changed;
	}

	public static function map( array $nodes, callable $fn ) {
		foreach ( $nodes as $i => $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$node = $fn( $node );
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				$node['children'] = self::map( $node['children'], $fn );
			}
			$nodes[ $i ] = $node;
		}
		return $nodes;
	}
}
