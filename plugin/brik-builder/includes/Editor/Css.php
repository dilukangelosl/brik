<?php
/**
 * A small CSS reader used to ship only the rules an exported page needs.
 *
 * It understands rules, grouping at-rules (@media, @supports, @layer, @container,
 * @starting-style) and block at-rules (@keyframes, @font-face, @property). It is not
 * a validating parser: anything it can't classify is kept.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Editor;

defined( 'ABSPATH' ) || exit;

final class Css {

	const GROUPS = array( '@media', '@supports', '@layer', '@container', '@starting-style', '@scope', '@document' );

	/**
	 * Split a stylesheet into top-level items:
	 * [ 'kind' => 'rule'|'at'|'stmt', 'head' => selector or prelude, 'body' => inside braces ].
	 */
	public static function parse( $css ) {
		$css   = preg_replace( '#/\*.*?\*/#s', '', (string) $css );
		$items = array();
		$len   = strlen( $css );
		$start = 0;
		$i     = 0;
		while ( $i < $len ) {
			$c = $css[ $i ];
			// Escaped characters in selectors (Tailwind: .content-\[\'x\'\]) are literal.
			if ( '\\' === $c ) {
				$i += 2;
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$i = self::skip_string( $css, $i );
				continue;
			}
			if ( ';' === $c ) {
				$text = trim( substr( $css, $start, $i - $start ) );
				if ( '' !== $text ) {
					$items[] = array(
						'kind' => 'stmt',
						'head' => $text,
						'body' => '',
					);
				}
				$start = ++$i;
				continue;
			}
			if ( '{' === $c ) {
				$head  = trim( substr( $css, $start, $i - $start ) );
				$depth = 1;
				$j     = $i + 1;
				while ( $j < $len && $depth > 0 ) {
					$d = $css[ $j ];
					if ( '\\' === $d ) {
						$j += 2;
						continue;
					}
					if ( '"' === $d || "'" === $d ) {
						$j = self::skip_string( $css, $j );
						continue;
					}
					if ( '{' === $d ) {
						++$depth;
					} elseif ( '}' === $d ) {
						--$depth;
					}
					++$j;
				}
				$items[] = array(
					'kind' => '@' === substr( $head, 0, 1 ) ? 'at' : 'rule',
					'head' => $head,
					'body' => substr( $css, $i + 1, max( 0, $j - $i - 2 ) ),
				);
				$start = $j;
				$i     = $j;
				continue;
			}
			++$i;
		}
		return $items;
	}

	private static function skip_string( $css, $i ) {
		$q   = $css[ $i ];
		$len = strlen( $css );
		for ( ++$i; $i < $len; $i++ ) {
			if ( '\\' === $css[ $i ] ) {
				++$i;
				continue;
			}
			if ( $q === $css[ $i ] ) {
				return $i + 1;
			}
		}
		return $len;
	}

	/**
	 * Keep the rules whose selectors only use classes found in $classes.
	 *
	 * @param string $css     Stylesheet.
	 * @param array  $classes Class names present in the markup (keys or values).
	 */
	public static function purge( $css, array $classes ) {
		$set = array();
		foreach ( $classes as $k => $v ) {
			$set[ is_string( $k ) ? $k : (string) $v ] = true;
		}
		$out = self::purge_items( self::parse( $css ), $set );

		// Second pass: keyframes and registered properties only when something uses them.
		$body  = '';
		$named = array();
		foreach ( self::parse( $out ) as $item ) {
			if ( 'at' === $item['kind'] && preg_match( '/^@(-webkit-)?keyframes\s+(\S+)/', $item['head'], $m ) ) {
				$named[] = array( $item, $m[2] );
			} elseif ( 'at' === $item['kind'] && preg_match( '/^@property\s+(\S+)/', $item['head'], $m ) ) {
				$named[] = array( $item, $m[1] );
			} else {
				$body .= self::write( $item );
			}
		}
		$result = $body;
		foreach ( $named as $pair ) {
			if ( false !== strpos( $body, $pair[1] ) ) {
				$result .= self::write( $pair[0] );
			}
		}
		return $result;
	}

	private static function purge_items( array $items, array $set ) {
		$out = '';
		foreach ( $items as $item ) {
			if ( 'stmt' === $item['kind'] ) {
				// @import of remote files, @charset…: not needed inline.
				if ( 0 === strpos( $item['head'], '@layer' ) ) {
					$out .= $item['head'] . ';';
				}
				continue;
			}
			if ( 'at' === $item['kind'] ) {
				$name = strtolower( (string) strtok( $item['head'], " (\t\n" ) );
				if ( in_array( $name, self::GROUPS, true ) ) {
					$inner = self::purge_items( self::parse( $item['body'] ), $set );
					if ( '' !== $inner ) {
						$out .= $item['head'] . '{' . $inner . '}';
					}
				} else {
					$out .= self::write( $item );
				}
				continue;
			}
			$kept = array();
			foreach ( self::split_selectors( $item['head'] ) as $sel ) {
				if ( self::matches( $sel, $set ) ) {
					$kept[] = $sel;
				}
			}
			if ( $kept ) {
				$out .= implode( ',', $kept ) . '{' . $item['body'] . '}';
			}
		}
		return $out;
	}

	private static function write( array $item ) {
		if ( 'stmt' === $item['kind'] ) {
			return $item['head'] . ';';
		}
		return $item['head'] . '{' . $item['body'] . '}';
	}

	/**
	 * Split a selector list on top-level commas (not inside :is(), :where()…).
	 */
	public static function split_selectors( $selector ) {
		$parts = array();
		$depth = 0;
		$cur   = '';
		$len   = strlen( $selector );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $selector[ $i ];
			if ( '\\' === $c && $i + 1 < $len ) {
				$cur .= $c . $selector[ ++$i ];
				continue;
			}
			if ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ')' === $c || ']' === $c ) {
				--$depth;
			} elseif ( ',' === $c && 0 === $depth ) {
				$parts[] = trim( $cur );
				$cur     = '';
				continue;
			}
			$cur .= $c;
		}
		if ( '' !== trim( $cur ) ) {
			$parts[] = trim( $cur );
		}
		return $parts;
	}

	/**
	 * Class names a selector needs (escapes resolved). Classes inside :not() are ignored.
	 */
	public static function classes_in( $selector ) {
		$selector = preg_replace( '/:not\((?:[^()]|\([^()]*\))*\)/', '', $selector );
		// Attribute selectors, but not escaped brackets of arbitrary-value classes.
		$selector = preg_replace( '/(?<!\\\\)\[(?:\\\\.|[^\]\\\\])*\]/', '', $selector );
		preg_match_all( '/\.((?:\\\\.|[A-Za-z0-9_-])+)/', $selector, $m );
		return array_map(
			static function ( $c ) {
				return preg_replace( '/\\\\(.)/', '$1', $c );
			},
			$m[1]
		);
	}

	private static function matches( $selector, array $set ) {
		foreach ( self::classes_in( $selector ) as $class ) {
			if ( ! isset( $set[ $class ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Every class name used in a piece of markup.
	 */
	public static function markup_classes( $html ) {
		$set = array();
		if ( preg_match_all( '/\sclass\s*=\s*(["\'])(.*?)\1/s', (string) $html, $m ) ) {
			foreach ( $m[2] as $list ) {
				foreach ( preg_split( '/\s+/', html_entity_decode( $list, ENT_QUOTES ) ) as $c ) {
					if ( '' !== $c ) {
						$set[ $c ] = true;
					}
				}
			}
		}
		return $set;
	}
}
