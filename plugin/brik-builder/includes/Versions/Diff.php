<?php
/**
 * Section-level comparison of Brik trees.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Versions;

use Brik\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * Compares two trees section by section (top-level nodes, matched by id) and describes the
 * differences in words people use: "Changed typography in Features", "Button color changed".
 */
final class Diff {

	/**
	 * Name of a top-level section: its admin label, the first heading inside it, the HTML
	 * tag when it is a header/footer, or "Section N".
	 */
	public static function section_name( array $node, $index = 0 ) {
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		if ( ! empty( $attrs['admin_label'] ) ) {
			return self::clip( $attrs['admin_label'] );
		}
		$heading = self::first_heading( isset( $node['children'] ) ? (array) $node['children'] : array() );
		if ( '' !== $heading ) {
			return $heading;
		}
		if ( ! empty( $attrs['tag'] ) && in_array( $attrs['tag'], array( 'header', 'footer', 'nav', 'aside' ), true ) ) {
			return ucfirst( $attrs['tag'] );
		}
		if ( ! empty( $attrs['css_id'] ) ) {
			return '#' . self::clip( $attrs['css_id'] );
		}
		if ( ! empty( $node['type'] ) && 'section' !== $node['type'] ) {
			return self::type_title( $node['type'] );
		}
		/* translators: %d: section position */
		return sprintf( __( 'Section %d', 'brik-builder' ), $index + 1 );
	}

	private static function first_heading( array $nodes ) {
		$fallback = '';
		foreach ( $nodes as $node ) {
			$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
			if ( isset( $node['type'] ) && 'heading' === $node['type'] && ! empty( $attrs['text'] ) && is_string( $attrs['text'] ) ) {
				return self::clip( $attrs['text'] );
			}
			if ( ! empty( $node['children'] ) ) {
				$found = self::first_heading( (array) $node['children'] );
				if ( '' !== $found ) {
					return $found;
				}
			}
			foreach ( array( 'title', 'heading' ) as $key ) {
				if ( '' === $fallback && ! empty( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
					$fallback = self::clip( $attrs[ $key ] );
				}
			}
		}
		return $fallback;
	}

	private static function clip( $text ) {
		$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
		return mb_strlen( $text ) > 40 ? mb_substr( $text, 0, 38 ) . '…' : $text;
	}

	private static function type_title( $type ) {
		$def = Modules::get( $type );
		return $def ? (string) $def['title'] : ucfirst( str_replace( '_', ' ', (string) $type ) );
	}

	private static function node_label( array $node ) {
		if ( ! empty( $node['attrs']['admin_label'] ) ) {
			return self::clip( $node['attrs']['admin_label'] );
		}
		return self::type_title( $node['type'] );
	}

	/**
	 * Which aspect an attribute belongs to, by key (works for prefixed keys such as
	 * title_font_size or button_bg).
	 */
	public static function aspect( $key ) {
		$k = strtok( (string) $key, '@' );
		$rules = array(
			'typography' => '/(font_|line_height|letter_spacing|text_transform|text_align|text_decoration|text_shadow)/',
			'color'      => '/(color|(^|_)bg$|gradient)/',
			'background' => '/^bg_|background|overlay/',
			'spacing'    => '/(margin|padding|gap)/',
			'size'       => '/(width|height|size$|aspect)/',
			'border'     => '/(border|radius)/',
			'shadow'     => '/shadow/',
			'animation'  => '/(animation|transition|transform|filter)/',
			'visibility' => '/(hide|visib|condition)/',
			'custom CSS' => '/(custom_css|css_class|css_id)/',
		);
		foreach ( $rules as $aspect => $re ) {
			if ( preg_match( $re, $k ) ) {
				return $aspect;
			}
		}
		return 'content';
	}

	/** Index nodes of a subtree by id. */
	private static function index( array $nodes, array &$out = array() ) {
		foreach ( $nodes as $node ) {
			if ( isset( $node['id'] ) ) {
				$out[ $node['id'] ] = $node;
			}
			if ( ! empty( $node['children'] ) ) {
				self::index( (array) $node['children'], $out );
			}
		}
		return $out;
	}

	private static function children_ids( array $node ) {
		$ids = array();
		foreach ( isset( $node['children'] ) ? (array) $node['children'] : array() as $child ) {
			$ids[] = isset( $child['id'] ) ? $child['id'] : '';
		}
		return $ids;
	}

	/**
	 * What changed inside one section: [ 'aspects' => [aspect => true], 'changes' => [strings] ].
	 */
	public static function section_changes( array $a, array $b ) {
		$old     = self::index( array( $a ) );
		$new     = self::index( array( $b ) );
		$aspects = array();
		$changes = array();
		$add     = static function ( $text ) use ( &$changes ) {
			if ( ! in_array( $text, $changes, true ) ) {
				$changes[] = $text;
			}
		};

		foreach ( $new as $id => $node ) {
			if ( ! isset( $old[ $id ] ) ) {
				// Only report the top of an added subtree.
				$aspects['structure'] = true;
				continue;
			}
			$prev  = $old[ $id ];
			$pa    = isset( $prev['attrs'] ) ? (array) $prev['attrs'] : array();
			$na    = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
			$label = self::node_label( $node );
			$keys  = array_unique( array_merge( array_keys( $pa ), array_keys( $na ) ) );
			$by    = array();
			foreach ( $keys as $key ) {
				$x = isset( $pa[ $key ] ) ? $pa[ $key ] : null;
				$y = isset( $na[ $key ] ) ? $na[ $key ] : null;
				if ( wp_json_encode( $x ) !== wp_json_encode( $y ) && 'admin_label' !== $key ) {
					$by[ self::aspect( $key ) ] = true;
				}
			}
			foreach ( array_keys( $by ) as $aspect ) {
				$aspects[ $aspect ] = true;
				/* translators: 1: element name, 2: aspect such as typography or color */
				$add( sprintf( __( '%1$s %2$s changed', 'brik-builder' ), $label, self::aspect_label( $aspect ) ) );
			}
			if ( self::children_ids( $prev ) !== self::children_ids( $node ) ) {
				$aspects['structure'] = true;
				$gone  = array_diff( self::children_ids( $prev ), self::children_ids( $node ) );
				$added = array_diff( self::children_ids( $node ), self::children_ids( $prev ) );
				foreach ( $added as $cid ) {
					if ( isset( $new[ $cid ] ) && ! isset( $old[ $cid ] ) ) {
						/* translators: %s: element name */
						$add( sprintf( __( '%s added', 'brik-builder' ), self::node_label( $new[ $cid ] ) ) );
					}
				}
				foreach ( $gone as $cid ) {
					if ( isset( $old[ $cid ] ) && ! isset( $new[ $cid ] ) ) {
						/* translators: %s: element name */
						$add( sprintf( __( '%s removed', 'brik-builder' ), self::node_label( $old[ $cid ] ) ) );
					}
				}
				if ( ! $added && ! $gone ) {
					/* translators: %s: element name */
					$add( sprintf( __( 'Elements reordered in %s', 'brik-builder' ), $label ) );
				}
			}
		}
		foreach ( $old as $id => $node ) {
			if ( ! isset( $new[ $id ] ) ) {
				$aspects['structure'] = true;
			}
		}
		return array(
			'aspects' => $aspects,
			'changes' => $changes,
		);
	}

	private static function aspect_label( $aspect ) {
		$labels = array(
			'typography' => __( 'typography', 'brik-builder' ),
			'color'      => __( 'color', 'brik-builder' ),
			'background' => __( 'background', 'brik-builder' ),
			'spacing'    => __( 'spacing', 'brik-builder' ),
			'size'       => __( 'size', 'brik-builder' ),
			'border'     => __( 'border', 'brik-builder' ),
			'shadow'     => __( 'shadow', 'brik-builder' ),
			'animation'  => __( 'animation', 'brik-builder' ),
			'visibility' => __( 'visibility', 'brik-builder' ),
			'custom CSS' => __( 'custom CSS', 'brik-builder' ),
			'content'    => __( 'content', 'brik-builder' ),
			'structure'  => __( 'layout', 'brik-builder' ),
		);
		return isset( $labels[ $aspect ] ) ? $labels[ $aspect ] : $aspect;
	}

	/**
	 * Section by section comparison of tree $a (before) and $b (after).
	 * Rows follow $b's order; removed sections are slotted in at their old position.
	 */
	public static function compare( array $a, array $b, $page_a = array(), $page_b = array() ) {
		$by_a = array();
		foreach ( array_values( $a ) as $i => $node ) {
			$by_a[ isset( $node['id'] ) ? $node['id'] : 'i' . $i ] = array( $i, $node );
		}
		$by_b = array();
		foreach ( array_values( $b ) as $i => $node ) {
			$by_b[ isset( $node['id'] ) ? $node['id'] : 'i' . $i ] = array( $i, $node );
		}

		$rows = array();
		foreach ( $by_b as $id => list( $i, $node ) ) {
			$row = array(
				'id'      => (string) $id,
				'name'    => self::section_name( $node, $i ),
				'index_a' => isset( $by_a[ $id ] ) ? $by_a[ $id ][0] : null,
				'index_b' => $i,
				'changes' => array(),
			);
			if ( ! isset( $by_a[ $id ] ) ) {
				$row['status']    = 'added';
				$row['changes'][] = __( 'New section', 'brik-builder' );
			} elseif ( wp_json_encode( $by_a[ $id ][1] ) === wp_json_encode( $node ) ) {
				$row['status'] = 'unchanged';
			} else {
				$diff           = self::section_changes( $by_a[ $id ][1], $node );
				$row['status']  = 'changed';
				$row['aspects'] = array_keys( $diff['aspects'] );
				$row['changes'] = $diff['changes'] ? $diff['changes'] : array( __( 'Layout changed', 'brik-builder' ) );
				$row['name_a']  = self::section_name( $by_a[ $id ][1], $by_a[ $id ][0] );
			}
			$rows[] = $row;
		}

		// Removed sections go back where they were relative to their old neighbours.
		foreach ( $by_a as $id => list( $i, $node ) ) {
			if ( isset( $by_b[ $id ] ) ) {
				continue;
			}
			$row = array(
				'id'      => (string) $id,
				'name'    => self::section_name( $node, $i ),
				'status'  => 'removed',
				'index_a' => $i,
				'index_b' => null,
				'changes' => array( __( 'Section removed', 'brik-builder' ) ),
			);
			$at = count( $rows );
			foreach ( $rows as $k => $r ) {
				if ( null !== $r['index_a'] && $r['index_a'] > $i ) {
					$at = $k;
					break;
				}
			}
			array_splice( $rows, $at, 0, array( $row ) );
		}

		$counts = array(
			'added'     => 0,
			'removed'   => 0,
			'changed'   => 0,
			'unchanged' => 0,
		);
		foreach ( $rows as $r ) {
			++$counts[ $r['status'] ];
		}

		// Sections kept on both sides but in a different order.
		$kept_a = array_values( array_intersect( array_keys( $by_a ), array_keys( $by_b ) ) );
		$kept_b = array_values( array_intersect( array_keys( $by_b ), array_keys( $by_a ) ) );

		$page_changed = wp_json_encode( self::page_norm( $page_a ) ) !== wp_json_encode( self::page_norm( $page_b ) );

		return array(
			'sections'     => $rows,
			'counts'       => $counts,
			'reordered'    => $kept_a !== $kept_b,
			'page_changed' => $page_changed,
			'identical'    => ! $counts['added'] && ! $counts['removed'] && ! $counts['changed'] && $kept_a === $kept_b && ! $page_changed,
		);
	}

	private static function page_norm( $page ) {
		$page = array_filter( (array) $page );
		ksort( $page );
		return $page;
	}

	/**
	 * A one-line summary of a save, e.g. "Changed hero, Added Pricing section".
	 */
	public static function summary( $previous, array $tree, $prev_page = null, $page = null ) {
		if ( null === $previous ) {
			return $tree ? __( 'First version', 'brik-builder' ) : __( 'Created (empty)', 'brik-builder' );
		}
		$cmp   = self::compare( $previous, $tree, (array) $prev_page, (array) $page );
		$parts = array();
		foreach ( $cmp['sections'] as $row ) {
			if ( 'added' === $row['status'] ) {
				/* translators: %s: section name */
				$parts[] = sprintf( __( 'Added %s section', 'brik-builder' ), $row['name'] );
			} elseif ( 'removed' === $row['status'] ) {
				/* translators: %s: section name */
				$parts[] = sprintf( __( 'Removed %s', 'brik-builder' ), $row['name'] );
			} elseif ( 'changed' === $row['status'] ) {
				$aspects = array_values( array_diff( isset( $row['aspects'] ) ? $row['aspects'] : array(), array( 'content' ) ) );
				$only    = isset( $row['aspects'] ) && 1 === count( $row['aspects'] ) && 1 === count( $aspects ) && 'structure' !== $aspects[0];
				$parts[] = $only
					/* translators: 1: aspect such as typography, 2: section name */
					? sprintf( __( 'Changed %1$s in %2$s', 'brik-builder' ), self::aspect_label( $aspects[0] ), $row['name'] )
					/* translators: %s: section name */
					: sprintf( __( 'Changed %s', 'brik-builder' ), $row['name'] );
			}
		}
		if ( $cmp['reordered'] && ! $cmp['counts']['added'] && ! $cmp['counts']['removed'] ) {
			$parts[] = __( 'Reordered sections', 'brik-builder' );
		}
		if ( $cmp['page_changed'] ) {
			$parts[] = __( 'Changed page settings', 'brik-builder' );
		}
		if ( ! $parts ) {
			return __( 'No visible changes', 'brik-builder' );
		}
		if ( count( $parts ) > 3 ) {
			$more  = count( $parts ) - 2;
			$parts = array_slice( $parts, 0, 2 );
			/* translators: %d: number of further changes */
			$parts[] = sprintf( _n( '%d more change', '%d more changes', $more, 'brik-builder' ), $more );
		}
		return implode( ', ', $parts );
	}

	/**
	 * Restore selected top-level sections from $old into $current: changed sections are
	 * replaced in place, removed ones re-inserted at their old index, and sections that
	 * did not exist in $old are taken out.
	 */
	public static function restore_sections( array $current, array $old, array $ids ) {
		$current = array_values( $current );
		$old     = array_values( $old );
		$old_pos = array();
		foreach ( $old as $i => $node ) {
			$old_pos[ $node['id'] ] = $i;
		}

		foreach ( $ids as $id ) {
			$at = null;
			foreach ( $current as $i => $node ) {
				if ( $node['id'] === $id ) {
					$at = $i;
					break;
				}
			}
			if ( isset( $old_pos[ $id ] ) ) {
				$section = $old[ $old_pos[ $id ] ];
				if ( null !== $at ) {
					$current[ $at ] = $section;
				} else {
					$index = min( $old_pos[ $id ], count( $current ) );
					array_splice( $current, $index, 0, array( $section ) );
				}
			} elseif ( null !== $at ) {
				array_splice( $current, $at, 1 );
			}
		}
		return $current;
	}
}
