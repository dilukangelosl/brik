<?php
namespace Brik\Perf;

defined( 'ABSPATH' ) || exit;

/**
 * A small CSS parser, tree-shaker and minifier.
 *
 * Good enough for what Tailwind and Brik emit (and hand-written custom CSS): style rules,
 * conditional group rules (@media, @supports, @layer, @container, @starting-style), and opaque
 * at-rules (@keyframes, @font-face, @property). It never rewrites declarations, so the worst a
 * parsing edge case can do is keep a rule that wasn't needed.
 */
final class Css {

	/** At-rules whose blocks hold rules we can shake recursively. */
	const GROUPS = array( 'media', 'supports', 'layer', 'container', 'starting-style', 'scope', 'document', '-moz-document' );

	/** Pseudo-classes whose argument is a selector list that must match. */
	const MATCHING = array( 'is', 'where', 'matches', '-webkit-any', '-moz-any', 'has' );

	private static $req_cache = array();

	/* ---------------------------------------------------------------------
	 * Parsing.
	 * ------------------------------------------------------------------- */

	/**
	 * Parse a stylesheet into nodes:
	 * [ 'sel' => '…', 'body' => '…' ], [ 'at' => 'media', 'prelude' => '…', 'children' => [ … ] ],
	 * [ 'at' => 'keyframes', 'prelude' => '…', 'body' => '…' ] or [ 'raw' => '@layer a,b;' ].
	 */
	public static function parse( $css ) {
		$css = self::strip_comments( (string) $css );
		$i   = 0;
		return self::block( $css, $i, strlen( $css ) );
	}

	private static function block( $s, &$i, $n ) {
		$nodes = array();
		while ( $i < $n ) {
			$i += strspn( $s, " \t\r\n\f", $i );
			if ( $i >= $n ) {
				break;
			}
			if ( '}' === $s[ $i ] ) {
				++$i;
				break;
			}
			$start = $i;
			$stop  = self::scan( $s, $i, $n, '{;}' );
			$pre   = trim( substr( $s, $start, $i - $start ) );
			if ( ';' === $stop || '' === $stop ) {
				if ( '' !== $pre ) {
					$nodes[] = array( 'raw' => $pre . ';' );
				}
				++$i;
				continue;
			}
			if ( '}' === $stop ) {
				// A declaration without a trailing semicolon (only inside nested blocks).
				if ( '' !== $pre ) {
					$nodes[] = array( 'raw' => $pre );
				}
				continue;
			}
			++$i; // past "{".
			if ( '' !== $pre && '@' === $pre[0] ) {
				preg_match( '/^@([\w-]+)\s*(.*)$/s', $pre, $m );
				$name    = isset( $m[1] ) ? strtolower( $m[1] ) : '';
				$prelude = isset( $m[2] ) ? trim( $m[2] ) : '';
				if ( in_array( $name, self::GROUPS, true ) ) {
					$nodes[] = array(
						'at'       => $name,
						'prelude'  => $prelude,
						'children' => self::block( $s, $i, $n ),
					);
				} else {
					$nodes[] = array(
						'at'      => $name,
						'prelude' => $prelude,
						'body'    => self::raw_block( $s, $i, $n ),
					);
				}
				continue;
			}
			$nodes[] = array(
				'sel'  => $pre,
				'body' => self::raw_block( $s, $i, $n ),
			);
		}
		return $nodes;
	}

	/**
	 * Advance $i to the next top-level character from $stops, skipping strings, escapes and
	 * parentheses/brackets. Returns the character found ('' at the end).
	 */
	private static function scan( $s, &$i, $n, $stops ) {
		$depth = 0;
		$set   = $stops . "\"'()[]\\";
		while ( $i < $n ) {
			$i += strcspn( $s, $set, $i );
			if ( $i >= $n ) {
				return '';
			}
			$c = $s[ $i ];
			if ( '\\' === $c ) {
				$i += 2;
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				self::skip_string( $s, $i, $n );
				continue;
			}
			if ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ')' === $c || ']' === $c ) {
				$depth = max( 0, $depth - 1 );
			} elseif ( 0 === $depth ) {
				return $c;
			}
			++$i;
		}
		return '';
	}

	private static function skip_string( $s, &$i, $n ) {
		$q = $s[ $i ];
		++$i;
		while ( $i < $n ) {
			$i += strcspn( $s, $q . "\\\n", $i );
			if ( $i >= $n ) {
				return;
			}
			if ( '\\' === $s[ $i ] ) {
				$i += 2;
				continue;
			}
			++$i;
			return;
		}
	}

	/** Contents of a block up to its matching "}"; $i ends after it. */
	private static function raw_block( $s, &$i, $n ) {
		$start = $i;
		$depth = 0;
		while ( $i < $n ) {
			$i += strcspn( $s, "{}\"'\\", $i );
			if ( $i >= $n ) {
				break;
			}
			$c = $s[ $i ];
			if ( '\\' === $c ) {
				$i += 2;
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				self::skip_string( $s, $i, $n );
				continue;
			}
			if ( '{' === $c ) {
				++$depth;
			} elseif ( 0 === $depth ) {
				$body = substr( $s, $start, $i - $start );
				++$i;
				return $body;
			} else {
				--$depth;
			}
			++$i;
		}
		return substr( $s, $start );
	}

	public static function strip_comments( $s ) {
		if ( false === strpos( $s, '/*' ) ) {
			return $s;
		}
		$out = '';
		$i   = 0;
		$n   = strlen( $s );
		while ( $i < $n ) {
			$j = $i + strcspn( $s, "/\"'", $i );
			$out .= substr( $s, $i, $j - $i );
			if ( $j >= $n ) {
				break;
			}
			$c = $s[ $j ];
			if ( '/' === $c && isset( $s[ $j + 1 ] ) && '*' === $s[ $j + 1 ] ) {
				$end = strpos( $s, '*/', $j + 2 );
				$i   = false === $end ? $n : $end + 2;
				continue;
			}
			if ( '"' === $c || "'" === $c ) {
				$k = $j;
				self::skip_string( $s, $k, $n );
				$out .= substr( $s, $j, $k - $j );
				$i    = $k;
				continue;
			}
			$out .= $c;
			$i    = $j + 1;
		}
		return $out;
	}

	/**
	 * Split a selector list on top-level commas.
	 */
	public static function split_list( $list ) {
		$out   = array();
		$depth = 0;
		$start = 0;
		$n     = strlen( $list );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = $list[ $i ];
			if ( '\\' === $c ) {
				++$i;
			} elseif ( '"' === $c || "'" === $c ) {
				self::skip_string( $list, $i, $n );
				--$i;
			} elseif ( '(' === $c || '[' === $c ) {
				++$depth;
			} elseif ( ')' === $c || ']' === $c ) {
				--$depth;
			} elseif ( ',' === $c && 0 === $depth ) {
				$out[] = trim( substr( $list, $start, $i - $start ) );
				$start = $i + 1;
			}
		}
		$out[] = trim( substr( $list, $start ) );
		return array_values( array_filter( $out, 'strlen' ) );
	}

	/* ---------------------------------------------------------------------
	 * Selector requirements.
	 * ------------------------------------------------------------------- */

	/**
	 * What a complex selector needs from the document to ever match:
	 * [ 'c' => classes, 'a' => attributes, 'or' => [ [ requirement, … ], … ] ].
	 * Classes inside :not() don't count; :is()/:where()/:has() need one alternative.
	 */
	public static function requirement( $sel ) {
		if ( isset( self::$req_cache[ $sel ] ) ) {
			return self::$req_cache[ $sel ];
		}
		$req = array(
			'c'  => array(),
			'a'  => array(),
			'or' => array(),
		);
		$n   = strlen( $sel );
		$i   = 0;
		while ( $i < $n ) {
			$i += strcspn( $sel, ".[:\\\"'", $i );
			if ( $i >= $n ) {
				break;
			}
			$c = $sel[ $i ];
			if ( '\\' === $c ) {
				$i += 2;
			} elseif ( '"' === $c || "'" === $c ) {
				self::skip_string( $sel, $i, $n );
			} elseif ( '.' === $c ) {
				++$i;
				$name = self::ident( $sel, $i, $n );
				if ( '' !== $name ) {
					$req['c'][] = $name;
				}
			} elseif ( '[' === $c ) {
				$start = $i + 1;
				$depth = 0;
				while ( $i < $n ) {
					$ch = $sel[ $i ];
					if ( '\\' === $ch ) {
						$i += 2;
						continue;
					}
					if ( '"' === $ch || "'" === $ch ) {
						self::skip_string( $sel, $i, $n );
						continue;
					}
					if ( '[' === $ch ) {
						++$depth;
					} elseif ( ']' === $ch && 0 === --$depth ) {
						break;
					}
					++$i;
				}
				$inner = substr( $sel, $start, $i - $start );
				++$i;
				$name = strtolower( trim( preg_split( '/[~|^$*]?=/', $inner, 2 )[0] ) );
				$name = ltrim( (string) preg_replace( '/^[\w*]*\|/', '', $name ) );
				if ( '' !== $name ) {
					$req['a'][] = $name;
				}
			} else {
				// Pseudo-class or pseudo-element.
				++$i;
				if ( $i < $n && ':' === $sel[ $i ] ) {
					++$i;
				}
				$name = strtolower( self::ident( $sel, $i, $n ) );
				if ( $i < $n && '(' === $sel[ $i ] ) {
					$start = $i + 1;
					$depth = 0;
					while ( $i < $n ) {
						$ch = $sel[ $i ];
						if ( '\\' === $ch ) {
							$i += 2;
							continue;
						}
						if ( '"' === $ch || "'" === $ch ) {
							self::skip_string( $sel, $i, $n );
							continue;
						}
						if ( '(' === $ch ) {
							++$depth;
						} elseif ( ')' === $ch && 0 === --$depth ) {
							break;
						}
						++$i;
					}
					$args = substr( $sel, $start, $i - $start );
					++$i;
					if ( in_array( $name, self::MATCHING, true ) ) {
						$alts = array();
						foreach ( self::split_list( $args ) as $alt ) {
							$alts[] = self::requirement( $alt );
						}
						if ( $alts ) {
							$req['or'][] = $alts;
						}
					}
				}
			}
		}
		self::$req_cache[ $sel ] = $req;
		return $req;
	}

	/** Read an identifier at $i, decoding CSS escapes (".hover\:bg-primary\/90" → "hover:bg-primary/90"). */
	private static function ident( $s, &$i, $n ) {
		$out = '';
		while ( $i < $n ) {
			$c = $s[ $i ];
			if ( '\\' === $c ) {
				if ( preg_match( '/\G\\\\([0-9a-fA-F]{1,6})[ \t\n\r\f]?/', $s, $m, 0, $i ) ) {
					$code = hexdec( $m[1] );
					$out .= function_exists( 'mb_chr' ) ? mb_chr( $code, 'UTF-8' ) : html_entity_decode( '&#' . $code . ';', ENT_QUOTES, 'UTF-8' );
					$i   += strlen( $m[0] );
				} else {
					$out .= isset( $s[ $i + 1 ] ) ? $s[ $i + 1 ] : '';
					$i   += 2;
				}
				continue;
			}
			if ( ctype_alnum( $c ) || '-' === $c || '_' === $c || ord( $c ) > 127 ) {
				$out .= $c;
				++$i;
				continue;
			}
			break;
		}
		return $out;
	}

	public static function satisfied( array $req, Usage $usage ) {
		foreach ( $req['c'] as $class ) {
			if ( ! $usage->has_class( $class ) ) {
				return false;
			}
		}
		foreach ( $req['a'] as $attr ) {
			if ( ! $usage->has_attr( $attr ) ) {
				return false;
			}
		}
		foreach ( $req['or'] as $alts ) {
			$any = false;
			foreach ( $alts as $alt ) {
				if ( self::satisfied( $alt, $usage ) ) {
					$any = true;
					break;
				}
			}
			if ( ! $any ) {
				return false;
			}
		}
		return true;
	}

	/* ---------------------------------------------------------------------
	 * Shaking.
	 * ------------------------------------------------------------------- */

	/**
	 * Drop rules that can't match a document described by $usage.
	 *
	 * @param array  $nodes  Parsed stylesheet.
	 * @param Usage  $usage  Classes and attributes in use.
	 * @param string $extra  Other CSS/JS text whose references keep @keyframes and custom properties alive.
	 */
	public static function shake( array $nodes, Usage $usage, $extra = '' ) {
		$nodes = self::filter( $nodes, $usage );

		// Second pass: keyframes, @property and the Tailwind defaults for custom properties
		// survive only when something we kept (or the extra text) refers to them.
		$text = self::serialize( self::without_deferred( $nodes ) ) . ' ' . $extra;
		preg_match_all( '/var\(\s*(--[\w-]+)/', $text, $m );
		$vars = array_flip( $m[1] );
		return self::prune( $nodes, $text, $vars, $usage );
	}

	private static function filter( array $nodes, Usage $usage ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['sel'] ) ) {
				$keep = array();
				foreach ( self::split_list( $node['sel'] ) as $sel ) {
					if ( self::satisfied( self::requirement( $sel ), $usage ) ) {
						$keep[] = $sel;
					}
				}
				if ( $keep ) {
					$node['sel'] = implode( ',', $keep );
					$out[]       = $node;
				}
			} elseif ( isset( $node['children'] ) ) {
				$node['children'] = self::filter( $node['children'], $usage );
				if ( $node['children'] ) {
					$out[] = $node;
				}
			} else {
				$out[] = $node;
			}
		}
		return $out;
	}

	private static function without_deferred( array $nodes ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['at'] ) && in_array( $node['at'], array( 'keyframes', '-webkit-keyframes', 'property' ), true ) ) {
				continue;
			}
			if ( isset( $node['sel'] ) && self::is_defaults_rule( $node ) ) {
				continue;
			}
			if ( isset( $node['children'] ) ) {
				$node['children'] = self::without_deferred( $node['children'] );
			}
			$out[] = $node;
		}
		return $out;
	}

	/** Tailwind's "*,:before,:after{--tw-…:initial}" style rules. */
	private static function is_defaults_rule( array $node ) {
		$req = self::requirement( $node['sel'] );
		return ! $req['c'] && ! $req['a'] && ! $req['or'] && preg_match( '/^\s*--tw-/', $node['body'] ) && ! preg_match( '/(^|;)\s*(?!--)[\w-]+\s*:/', $node['body'] );
	}

	private static function prune( array $nodes, $text, array $vars, Usage $usage ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['at'] ) && in_array( $node['at'], array( 'keyframes', '-webkit-keyframes' ), true ) ) {
				$name = trim( $node['prelude'], " \"'" );
				if ( ! preg_match( '/(?<![\w-])' . preg_quote( $name, '/' ) . '(?![\w-])/', $text ) && ! $usage->has_token( $name ) ) {
					continue;
				}
			} elseif ( isset( $node['at'] ) && 'property' === $node['at'] ) {
				if ( ! isset( $vars[ trim( $node['prelude'] ) ] ) ) {
					continue;
				}
			} elseif ( isset( $node['sel'] ) && self::is_defaults_rule( $node ) ) {
				$decls = array();
				foreach ( self::declarations( $node['body'] ) as $decl ) {
					$prop = trim( strtok( $decl, ':' ) );
					if ( isset( $vars[ $prop ] ) ) {
						$decls[] = $decl;
					}
				}
				if ( ! $decls ) {
					continue;
				}
				$node['body'] = implode( ';', $decls );
			} elseif ( isset( $node['children'] ) ) {
				$node['children'] = self::prune( $node['children'], $text, $vars, $usage );
				if ( ! $node['children'] ) {
					continue;
				}
			}
			$out[] = $node;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Output.
	 * ------------------------------------------------------------------- */

	/**
	 * Remove earlier copies of identical rules. Keeping the last one never changes the cascade.
	 */
	public static function dedupe( array $nodes ) {
		$seen = array();
		$out  = array();
		for ( $i = count( $nodes ) - 1; $i >= 0; $i-- ) {
			$node = $nodes[ $i ];
			if ( isset( $node['children'] ) ) {
				$node['children'] = self::dedupe( $node['children'] );
			}
			// Statements like "@layer a,b;" or "@import" must keep their position.
			if ( isset( $node['raw'] ) ) {
				$out[] = $node;
				continue;
			}
			$key = md5( self::serialize( array( $node ) ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $node;
		}
		return array_reverse( $out );
	}

	public static function serialize( array $nodes ) {
		$out = '';
		foreach ( $nodes as $node ) {
			if ( isset( $node['raw'] ) ) {
				$out .= self::squash( $node['raw'] );
			} elseif ( isset( $node['sel'] ) ) {
				$out .= self::min_selector( $node['sel'] ) . '{' . self::min_body( $node['body'] ) . '}';
			} elseif ( isset( $node['children'] ) ) {
				$out .= '@' . $node['at'] . ( '' !== $node['prelude'] ? ' ' . self::squash( $node['prelude'] ) : '' ) . '{' . self::serialize( $node['children'] ) . '}';
			} else {
				$body = 'font-face' === $node['at'] || 'property' === $node['at'] || 'page' === $node['at'] ? self::min_body( $node['body'] ) : self::min_nested( $node['body'] );
				$out .= '@' . $node['at'] . ( '' !== $node['prelude'] ? ' ' . self::squash( $node['prelude'] ) : '' ) . '{' . $body . '}';
			}
		}
		return $out;
	}

	public static function minify( $css ) {
		return self::serialize( self::dedupe( self::parse( $css ) ) );
	}

	private static function squash( $s ) {
		return trim( preg_replace( '/\s+/', ' ', $s ) );
	}

	private static function min_selector( $sel ) {
		$parts = array();
		foreach ( self::split_list( $sel ) as $one ) {
			$one = self::squash( $one );
			// Spaces around child/sibling combinators are optional (escaped characters excluded).
			$one     = preg_replace( '/(?<!\\\\)\s*([>+~])\s*(?![^\[]*\])/', '$1', $one );
			$parts[] = $one;
		}
		return implode( ',', $parts );
	}

	/** Declarations of a block, split on top-level semicolons. */
	public static function declarations( $body ) {
		$out   = array();
		$i     = 0;
		$n     = strlen( $body );
		$start = 0;
		while ( $i < $n ) {
			$stop = self::scan( $body, $i, $n, ';' );
			$decl = trim( substr( $body, $start, $i - $start ) );
			if ( '' !== $decl ) {
				$out[] = $decl;
			}
			if ( '' === $stop ) {
				break;
			}
			++$i;
			$start = $i;
		}
		return $out;
	}

	private static function min_body( $body ) {
		if ( false !== strpos( $body, '{' ) ) {
			return self::min_nested( $body );
		}
		$decls = array();
		foreach ( self::declarations( $body ) as $decl ) {
			$pos = strpos( $decl, ':' );
			if ( false === $pos ) {
				$decls[] = self::squash( $decl );
				continue;
			}
			$decls[] = trim( substr( $decl, 0, $pos ) ) . ':' . self::squash( substr( $decl, $pos + 1 ) );
		}
		return implode( ';', $decls );
	}

	/** Bodies with nested blocks (keyframes, CSS nesting): only whitespace is touched. */
	private static function min_nested( $body ) {
		$nodes = self::parse( $body );
		$out   = '';
		foreach ( $nodes as $node ) {
			if ( isset( $node['raw'] ) ) {
				$out .= rtrim( self::squash( $node['raw'] ), ';' ) . ';';
			} else {
				$out .= self::serialize( array( $node ) );
			}
		}
		return rtrim( $out, ';' );
	}
}
