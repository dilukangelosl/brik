<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Compiles element attributes into scoped CSS.
 *
 * Attribute keys carry an optional state suffix: "padding@tablet", "bg_color@hover".
 * Tablet falls back to desktop, mobile falls back to tablet.
 */
final class Style {

	const TABLET = 980;
	const MOBILE = 767;

	private $rules = array(
		'desktop' => array(),
		'tablet'  => array(),
		'mobile'  => array(),
		'hover'   => array(),
	);

	private $raw = array();

	private $fonts = array();

	public static function states() {
		return array( 'desktop', 'tablet', 'mobile', 'hover' );
	}

	public static function selector( $node ) {
		return '.brik .brik-n-' . $node['id'];
	}

	/**
	 * Read a value for a state, without fallbacks.
	 */
	public static function raw_value( array $attrs, $key, $state ) {
		$k = 'desktop' === $state ? $key : $key . '@' . $state;
		if ( ! isset( $attrs[ $k ] ) || '' === $attrs[ $k ] || null === $attrs[ $k ] ) {
			return null;
		}
		return $attrs[ $k ];
	}

	/**
	 * Read a value for a state, falling back through the responsive chain.
	 */
	public static function value( array $attrs, $key, $state ) {
		$chain = array(
			'desktop' => array( 'desktop' ),
			'tablet'  => array( 'tablet', 'desktop' ),
			'mobile'  => array( 'mobile', 'tablet', 'desktop' ),
			'hover'   => array( 'hover', 'desktop' ),
		);
		foreach ( $chain[ $state ] as $s ) {
			$v = self::raw_value( $attrs, $key, $s );
			if ( null !== $v ) {
				return $v;
			}
		}
		return null;
	}

	public function add_node( array $node, array $def, array $attrs ) {
		$wrap      = self::selector( $node );
		$has_hover = false;
		$composite = array();

		foreach ( $def['fields'] as $key => $field ) {
			if ( ! empty( $field['composite'] ) ) {
				$composite[ $field['composite'] ][ $key ] = $field;
				continue;
			}
			if ( empty( $field['css'] ) ) {
				continue;
			}
			$css = $field['css'];
			foreach ( self::states() as $state ) {
				$v = self::raw_value( $attrs, $key, $state );
				if ( null === $v || is_array( $v ) ) {
					continue;
				}
				if ( 'hover' === $state ) {
					$has_hover = true;
					$sel       = isset( $css['hover_selector'] ) ? $css['hover_selector'] : Fields::hover_selector( $css['selector'] );
				} else {
					$sel = $css['selector'];
				}
				$decl = $this->declaration( $css, $v, $field );
				if ( '' !== $decl ) {
					$this->push( $state, str_replace( Fields::WRAP, $wrap, $sel ), $decl );
				}
				if ( ! empty( $css['font'] ) ) {
					$this->fonts[] = (string) $v;
				}
			}
		}

		foreach ( $composite as $kind => $fields ) {
			foreach ( self::states() as $state ) {
				if ( ! $this->state_touches( $attrs, array_keys( $fields ), $state ) ) {
					continue;
				}
				if ( 'hover' === $state ) {
					$has_hover = true;
				}
				$decl = $this->composite( $kind, $fields, $attrs, $state );
				if ( '' !== $decl ) {
					$this->push( $state, 'hover' === $state ? $wrap . ':hover' : $wrap, $decl );
				}
			}
		}

		$transition = isset( $attrs['transition'] ) ? $attrs['transition'] : '';
		if ( $has_hover || '' !== $transition ) {
			$ms = '' !== $transition ? (int) $transition : 300;
			$this->push( 'desktop', $wrap, 'transition:all ' . $ms . 'ms ease' );
		}

		if ( ! empty( $attrs['animation'] ) ) {
			$anim = array();
			if ( ! empty( $attrs['animation_duration'] ) ) {
				$anim[] = '--brik-anim-duration:' . (int) $attrs['animation_duration'] . 'ms';
			}
			if ( ! empty( $attrs['animation_delay'] ) ) {
				$anim[] = '--brik-anim-delay:' . (int) $attrs['animation_delay'] . 'ms';
			}
			if ( $anim ) {
				$this->push( 'desktop', $wrap, implode( ';', $anim ) );
			}
		}

		if ( ! empty( $attrs['hide_on'] ) ) {
			foreach ( (array) $attrs['hide_on'] as $device ) {
				if ( 'desktop' === $device ) {
					$this->raw[] = '@media (min-width:' . ( self::TABLET + 1 ) . 'px){' . $wrap . '{display:none!important}}';
				} elseif ( 'tablet' === $device ) {
					$this->raw[] = '@media (min-width:' . ( self::MOBILE + 1 ) . 'px) and (max-width:' . self::TABLET . 'px){' . $wrap . '{display:none!important}}';
				} elseif ( 'mobile' === $device ) {
					$this->raw[] = '@media (max-width:' . self::MOBILE . 'px){' . $wrap . '{display:none!important}}';
				}
			}
		}

		if ( ! empty( $attrs['custom_css'] ) ) {
			$this->raw[] = self::custom_css( $attrs['custom_css'], $wrap );
		}

		if ( isset( $def['css'] ) && is_callable( $def['css'] ) ) {
			$extra = call_user_func( $def['css'], $attrs, $wrap, $node );
			if ( $extra ) {
				$this->raw[] = $extra;
			}
		}
	}

	/**
	 * Push a declaration for a selector and state. Exposed for modules with computed styles.
	 */
	public function push( $state, $selector, $declaration ) {
		$this->rules[ $state ][ $selector ][] = $declaration;
	}

	public function fonts() {
		return array_values( array_unique( array_filter( $this->fonts ) ) );
	}

	public function css() {
		$out = '';
		foreach ( $this->rules as $state => $selectors ) {
			$block = '';
			foreach ( $selectors as $sel => $decls ) {
				$block .= $sel . '{' . implode( ';', $decls ) . '}';
			}
			if ( '' === $block ) {
				continue;
			}
			if ( 'tablet' === $state ) {
				$block = '@media (max-width:' . self::TABLET . 'px){' . $block . '}';
			} elseif ( 'mobile' === $state ) {
				$block = '@media (max-width:' . self::MOBILE . 'px){' . $block . '}';
			} elseif ( 'hover' === $state ) {
				$block = '@media (hover:hover){' . $block . '}';
			}
			$out .= $block;
		}
		return $out . implode( '', $this->raw );
	}

	private function declaration( array $css, $value, array $field ) {
		$value = self::clean( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( isset( $css['map'] ) && isset( $css['map'][ $value ] ) ) {
			$value = $css['map'][ $value ];
			if ( empty( $css['prop'] ) ) {
				return $value;
			}
		}
		if ( empty( $css['prop'] ) ) {
			return '';
		}
		if ( 'font-family' === $css['prop'] ) {
			$value = self::font_stack( $value );
		} elseif ( isset( $field['unit'] ) && is_numeric( $value ) ) {
			$value .= $field['unit'];
		} elseif ( 'unit' === $field['type'] && is_numeric( $value ) && 0 != $value && ! in_array( $css['prop'], array( 'line-height', 'z-index', 'opacity' ), true ) ) {
			$value .= 'px';
		}
		if ( isset( $css['value'] ) ) {
			$value = str_replace( '{{v}}', $value, $css['value'] );
		}
		$props = (array) $css['prop'];
		$out   = array();
		foreach ( $props as $prop ) {
			$out[] = $prop . ':' . $value;
		}
		return implode( ';', $out );
	}

	private function state_touches( array $attrs, array $keys, $state ) {
		foreach ( $keys as $key ) {
			if ( null !== self::raw_value( $attrs, $key, $state ) ) {
				return true;
			}
		}
		return false;
	}

	private function composite( $kind, array $fields, array $attrs, $state ) {
		$get = static function ( $key ) use ( $attrs, $state ) {
			$v = Style::value( $attrs, $key, $state );
			return null === $v ? '' : Style::clean( $v );
		};

		if ( 'background' === $kind ) {
			$layers  = array();
			$overlay = $get( 'bg_overlay' );
			$grad    = $get( 'bg_gradient' );
			$image   = $get( 'bg_image' );
			if ( '' !== $overlay && '' !== $image ) {
				$layers[] = 'linear-gradient(' . $overlay . ',' . $overlay . ')';
			}
			if ( '' !== $grad ) {
				$layers[] = $grad;
			}
			if ( '' !== $image ) {
				$layers[] = 'url("' . esc_url_raw( $image ) . '")';
			}
			if ( ! $layers ) {
				return '';
			}
			$decl = 'background-image:' . implode( ',', $layers );
			if ( '' !== $image && null === self::value( $attrs, 'bg_size', $state ) ) {
				$decl .= ';background-size:cover;background-position:center';
			}
			return $decl;
		}

		if ( 'filter' === $kind ) {
			$parts = array();
			$names = array(
				'blur'       => 'blur',
				'brightness' => 'brightness',
				'contrast'   => 'contrast',
				'grayscale'  => 'grayscale',
				'saturate'   => 'saturate',
				'hue_rotate' => 'hue-rotate',
			);
			foreach ( $names as $key => $fn ) {
				$v = $get( $key );
				if ( '' !== $v ) {
					$parts[] = $fn . '(' . $v . ( is_numeric( $v ) ? $fields[ $key ]['unit'] : '' ) . ')';
				}
			}
			return $parts ? 'filter:' . implode( ' ', $parts ) : '';
		}

		if ( 'transform' === $kind ) {
			$parts = array();
			$tx    = $get( 'translate_x' );
			$ty    = $get( 'translate_y' );
			if ( '' !== $tx || '' !== $ty ) {
				$parts[] = 'translate(' . self::px( $tx ? $tx : '0' ) . ',' . self::px( $ty ? $ty : '0' ) . ')';
			}
			if ( '' !== ( $v = $get( 'rotate' ) ) ) {
				$parts[] = 'rotate(' . ( is_numeric( $v ) ? $v . 'deg' : $v ) . ')';
			}
			if ( '' !== ( $v = $get( 'scale' ) ) ) {
				$parts[] = 'scale(' . $v . ')';
			}
			if ( '' !== ( $v = $get( 'skew_x' ) ) ) {
				$parts[] = 'skewX(' . ( is_numeric( $v ) ? $v . 'deg' : $v ) . ')';
			}
			return $parts ? 'transform:' . implode( ' ', $parts ) : '';
		}

		return '';
	}

	private static function px( $v ) {
		return is_numeric( $v ) ? $v . 'px' : $v;
	}

	/**
	 * Strip characters that could break out of a declaration.
	 */
	public static function clean( $value ) {
		$value = trim( (string) $value );
		$value = str_replace( array( '{', '}', ';', '<', '>' ), '', $value );
		return preg_replace( '/\/\*|\*\/|expression\s*\(|javascript:/i', '', $value );
	}

	public static function font_stack( $family ) {
		$family = trim( $family );
		if ( '' === $family || preg_match( '/^(var\(|inherit|initial)/', $family ) || false !== strpos( $family, ',' ) ) {
			return $family;
		}
		return '"' . str_replace( '"', '', $family ) . '",' . ( Fonts::is_serif( $family ) ? 'serif' : 'sans-serif' );
	}

	public static function custom_css( $css, $wrap ) {
		$css = str_ireplace( '</style', '', (string) $css );
		if ( false === strpos( $css, '{' ) ) {
			return $wrap . '{' . $css . '}';
		}
		return preg_replace( '/\bselector\b/', $wrap, $css );
	}
}
