<?php
namespace Brik\Audit;

use Brik\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Applies audit fixes to a tree. Fixes are attribute patches produced by the Scanner, so the
 * builder and the server apply exactly the same change.
 */
final class Fixer {

	const PASSES = 4;

	/**
	 * Apply fixes to a tree without saving.
	 *
	 * @param int        $post_id   Post id (for rendering context).
	 * @param array      $tree      Tree.
	 * @param array|null $selection null = every safe fix; else a list of
	 *                              { rule, node, fix?: fix id, value?: string for prompted fixes }.
	 * @return array { tree, applied: [], skipped: [] }
	 */
	public static function apply_to_tree( $post_id, array $tree, $selection = null ) {
		$applied = array();
		$skipped = array();
		$done    = array();
		$wanted  = null;
		if ( is_array( $selection ) ) {
			$wanted = array();
			foreach ( $selection as $s ) {
				if ( is_array( $s ) && ! empty( $s['rule'] ) ) {
					$wanted[ $s['rule'] . '|' . ( isset( $s['node'] ) ? (string) $s['node'] : '' ) ] = $s;
				}
			}
		}

		// Fixes can change what later checks see (a demoted H1 changes the heading outline),
		// so re-scan between passes.
		for ( $pass = 0; $pass < self::PASSES; $pass++ ) {
			$report = Scanner::run( $post_id, $tree );
			$did    = false;
			foreach ( $report['findings'] as $f ) {
				$key  = $f['rule'] . '|' . (string) $f['node'];
				$once = $key . '|' . $f['message'];
				if ( isset( $done[ $once ] ) || empty( $f['node'] ) ) {
					continue;
				}
				$want = null;
				if ( null !== $wanted ) {
					if ( ! isset( $wanted[ $key ] ) ) {
						continue;
					}
					$want = $wanted[ $key ];
				}
				$fix = self::choose( $f, $want );
				if ( ! $fix ) {
					if ( $want || empty( $f['fixes'] ) ) {
						if ( $want ) {
							$skipped[ $key ] = array(
								'rule'   => $f['rule'],
								'node'   => $f['node'],
								'reason' => empty( $f['fixes'] ) ? 'no_fix' : 'needs_value',
							);
						}
					}
					continue;
				}
				$patch = $fix['patch'];
				if ( ! empty( $fix['prompt'] ) ) {
					$value = $want && isset( $want['value'] ) ? trim( (string) $want['value'] ) : (string) $fix['prompt']['value'];
					if ( '' === $value ) {
						$skipped[ $key ] = array(
							'rule'   => $f['rule'],
							'node'   => $f['node'],
							'reason' => 'needs_value',
						);
						continue;
					}
					$patch[ $fix['prompt']['key'] ] = sanitize_text_field( $value );
				}
				$next = self::patch( $tree, $f['node'], $patch );
				if ( null === $next ) {
					continue;
				}
				$tree          = $next;
				$done[ $once ] = true;
				$done[ $key ]  = true;
				unset( $skipped[ $key ] );
				$applied[] = array(
					'rule'  => $f['rule'],
					'node'  => $f['node'],
					'fix'   => $fix['id'],
					'label' => $fix['label'],
					'patch' => $patch,
				);
				$did       = true;
			}
			if ( ! $did ) {
				break;
			}
		}

		if ( null !== $wanted ) {
			foreach ( $wanted as $key => $s ) {
				if ( ! isset( $done[ $key ] ) && ! isset( $skipped[ $key ] ) ) {
					$skipped[ $key ] = array(
						'rule'   => $s['rule'],
						'node'   => isset( $s['node'] ) ? $s['node'] : null,
						'reason' => 'not_found',
					);
				}
			}
		}

		return array(
			'tree'    => $tree,
			'applied' => $applied,
			'skipped' => array_values( $skipped ),
		);
	}

	/**
	 * Pick the fix to apply for a finding.
	 */
	private static function choose( array $finding, $want ) {
		$fixes = isset( $finding['fixes'] ) ? $finding['fixes'] : array();
		if ( ! $fixes ) {
			return null;
		}
		if ( $want && ! empty( $want['fix'] ) ) {
			foreach ( $fixes as $fix ) {
				if ( $fix['id'] === $want['fix'] ) {
					return $fix;
				}
			}
			return null;
		}
		if ( $want ) {
			// An explicit value goes to the prompted fix; otherwise the first fix.
			if ( isset( $want['value'] ) ) {
				foreach ( $fixes as $fix ) {
					if ( ! empty( $fix['prompt'] ) ) {
						return $fix;
					}
				}
			}
			return $fixes[0];
		}
		foreach ( $fixes as $fix ) {
			if ( ! empty( $fix['safe'] ) ) {
				return $fix;
			}
		}
		return null;
	}

	/**
	 * Merge an attribute patch into a node (null removes a key). Returns null if the node is missing.
	 */
	public static function patch( array $tree, $node_id, array $patch ) {
		$node = &Data::find( $tree, (string) $node_id );
		if ( null === $node ) {
			return null;
		}
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		foreach ( $patch as $k => $v ) {
			if ( null === $v ) {
				unset( $attrs[ $k ] );
			} else {
				$attrs[ $k ] = $v;
			}
		}
		$node['attrs'] = $attrs;
		unset( $node );
		return $tree;
	}

	/**
	 * Apply fixes and save the post.
	 */
	public static function apply( $post_id, $selection = null ) {
		$tree   = Data::get( $post_id );
		$before = Scanner::run( $post_id, $tree );
		$result = self::apply_to_tree( $post_id, $tree, $selection );
		if ( $result['applied'] ) {
			Data::save( $post_id, $result['tree'] );
		}
		$after = Scanner::run( $post_id, $result['tree'] );
		return array(
			'post_id'   => (int) $post_id,
			'applied'   => $result['applied'],
			'skipped'   => $result['skipped'],
			'before'    => $before['scores'],
			'after'     => $after['scores'],
			'remaining' => count( $after['findings'] ),
		);
	}
}
