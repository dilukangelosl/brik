<?php
namespace Brik\Perf;

defined( 'ABSPATH' ) || exit;

/**
 * The classes and attributes a page can have: what its markup contains, plus whatever its
 * scripts may add later (state classes, markup built in JS) and a few always-on hooks.
 */
final class Usage {

	/** Classes that can appear on <html>/<body> or be toggled by inline scripts on any page. */
	const ALWAYS = array( 'dark', 'light', 'brik-anim-ready', 'brik-page', 'admin-bar', 'is-open', 'is-active', 'is-visible', 'is-scrolled', 'is-hidden', 'is-in', 'is-current', 'is-ancestor', 'is-loading', 'is-selected', 'is-playing', 'is-stuck' );

	/** State class prefixes that scripts compose at runtime. */
	const PREFIXES = array( 'is-', 'has-' );

	private $classes = array();

	private $attrs = array();

	private $tokens = array();

	private $prefixes = array();

	public function __construct( $always = true ) {
		if ( $always ) {
			$this->add_classes( self::ALWAYS );
			$this->add_prefixes( self::PREFIXES );
		}
	}

	/** Classes, data attributes and data-attribute values found in markup. */
	public function add_html( $html ) {
		if ( preg_match_all( '/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $html, $m ) ) {
			foreach ( $m[0] as $i => $unused ) {
				$value = $m[1][ $i ] . $m[2][ $i ] . $m[3][ $i ];
				$this->add_classes( preg_split( '/\s+/', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY ) );
			}
		}
		if ( preg_match_all( '/\s(data-[\w-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?/i', $html, $m ) ) {
			foreach ( $m[1] as $i => $name ) {
				$this->attrs[ strtolower( $name ) ] = true;
				$value = $m[2][ $i ] . $m[3][ $i ];
				// Scripts sometimes read class names out of data attributes.
				if ( '' !== $value && strlen( $value ) < 200 && false === strpos( $value, '{' ) ) {
					foreach ( preg_split( '/\s+/', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), -1, PREG_SPLIT_NO_EMPTY ) as $token ) {
						// Class-like words only: ids, nonces and timestamps would make every
						// request look different (and never name a class anyway).
						if ( preg_match( '/^[a-z_\-!@\[]/i', $token ) && ! ( preg_match( '/\d/', $token ) && ! preg_match( '/[-:\/\[]/', $token ) ) ) {
							$this->tokens[ $token ] = true;
						}
					}
				}
			}
		}
		return $this;
	}

	public function add_classes( array $classes ) {
		foreach ( $classes as $class ) {
			$this->classes[ (string) $class ] = true;
		}
		return $this;
	}

	/** Tokens from scripts: may be classes or attribute names. */
	public function add_tokens( array $tokens ) {
		foreach ( $tokens as $token ) {
			$token                   = (string) $token;
			$this->tokens[ $token ]  = true;
			$this->classes[ $token ] = true;
			if ( 0 === strpos( $token, 'data-' ) ) {
				$this->attrs[ strtolower( $token ) ] = true;
			}
		}
		return $this;
	}

	public function add_prefixes( array $prefixes ) {
		foreach ( $prefixes as $prefix ) {
			if ( '' !== (string) $prefix ) {
				$this->prefixes[ (string) $prefix ] = true;
			}
		}
		return $this;
	}

	public function merge( Usage $other ) {
		$this->classes  += $other->classes;
		$this->attrs    += $other->attrs;
		$this->tokens   += $other->tokens;
		$this->prefixes += $other->prefixes;
		return $this;
	}

	public function has_class( $class ) {
		if ( isset( $this->classes[ $class ] ) || isset( $this->tokens[ $class ] ) ) {
			return true;
		}
		foreach ( $this->prefixes as $prefix => $unused ) {
			if ( 0 === strpos( $class, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Only Brik's own data attributes narrow things down. Everything else (aria-*, open,
	 * data-state, hidden…) is set by scripts or the browser too often to be worth the risk.
	 */
	public function has_attr( $attr ) {
		if ( 0 !== strpos( $attr, 'data-brik-' ) ) {
			return true;
		}
		return isset( $this->attrs[ $attr ] );
	}

	public function has_token( $token ) {
		return isset( $this->tokens[ $token ] ) || isset( $this->classes[ $token ] );
	}

	/** Stable fingerprint for cache keys. */
	public function signature() {
		$c = array_keys( $this->classes );
		$a = array_keys( $this->attrs );
		$t = array_keys( $this->tokens );
		$p = array_keys( $this->prefixes );
		sort( $c );
		sort( $a );
		sort( $t );
		sort( $p );
		return md5( implode( ' ', $c ) . '|' . implode( ' ', $a ) . '|' . implode( ' ', $t ) . '|' . implode( ' ', $p ) );
	}

	public function count_classes() {
		return count( $this->classes );
	}
}
