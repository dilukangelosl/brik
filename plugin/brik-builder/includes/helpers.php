<?php
/**
 * Template helpers available to modules, themes and extensions.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Join class names, skipping empty values. Accepts strings, lists and [class => bool] maps.
 */
function brik_cls( ...$args ) {
	$out = array();
	foreach ( $args as $arg ) {
		if ( is_array( $arg ) ) {
			foreach ( $arg as $k => $v ) {
				if ( is_int( $k ) ) {
					$out[] = brik_cls( $v );
				} elseif ( $v ) {
					$out[] = $k;
				}
			}
		} elseif ( is_string( $arg ) && '' !== trim( $arg ) ) {
			$out[] = trim( $arg );
		}
	}
	return implode( ' ', array_filter( $out ) );
}

/**
 * Build an HTML attribute string. Null/false values are skipped, true renders a bare attribute.
 */
function brik_attrs( array $attrs ) {
	$out = '';
	foreach ( $attrs as $name => $value ) {
		if ( null === $value || false === $value ) {
			continue;
		}
		$name = preg_replace( '/[^a-zA-Z0-9_:\-@.]/', '', $name );
		if ( true === $value ) {
			$out .= ' ' . $name;
			continue;
		}
		if ( is_array( $value ) ) {
			$value = wp_json_encode( $value );
		}
		$out .= ' ' . $name . '="' . ( in_array( $name, array( 'href', 'src', 'action' ), true ) ? esc_url( $value ) : esc_attr( $value ) ) . '"';
	}
	return $out;
}

/**
 * Inline HTML allowed in headings, labels and short text fields.
 */
function brik_inline( $text ) {
	static $allowed = null;
	if ( null === $allowed ) {
		$common  = array(
			'class' => true,
			'style' => true,
		);
		$allowed = array(
			'a'      => array_merge( $common, array( 'href' => true, 'target' => true, 'rel' => true ) ),
			'b'      => $common,
			'strong' => $common,
			'em'     => $common,
			'i'      => $common,
			'u'      => $common,
			's'      => $common,
			'span'   => $common,
			'mark'   => $common,
			'code'   => $common,
			'small'  => $common,
			'sub'    => $common,
			'sup'    => $common,
			'br'     => array(),
		);
	}
	return wp_kses( (string) $text, $allowed );
}

/**
 * Rich text output (already sanitized on save for users without unfiltered_html).
 */
function brik_rich( $html ) {
	return wpautop( (string) $html );
}

/**
 * Render a Lucide icon as inline SVG.
 */
function brik_icon( $name, $class = 'size-4', array $attrs = array() ) {
	return Brik\Icons::svg( $name, array_merge( array( 'class' => $class ), $attrs ) );
}

/**
 * Attributes for an anchor from a link field value: ['url' => '', 'new_tab' => bool, 'nofollow' => bool].
 */
function brik_link_attrs( $link, array $extra = array() ) {
	if ( is_string( $link ) ) {
		$link = array( 'url' => $link );
	}
	$link  = is_array( $link ) ? $link : array();
	$attrs = array( 'href' => isset( $link['url'] ) && '' !== $link['url'] ? $link['url'] : '#' );
	$rel   = array();
	if ( ! empty( $link['new_tab'] ) ) {
		$attrs['target'] = '_blank';
		$rel[]           = 'noopener';
	}
	if ( ! empty( $link['nofollow'] ) ) {
		$rel[] = 'nofollow';
	}
	if ( $rel ) {
		$attrs['rel'] = implode( ' ', $rel );
	}
	return brik_attrs( array_merge( $attrs, $extra ) );
}

/**
 * Image markup from an image field value (URL string or ['id' => int, 'url' => string, 'alt' => string]).
 */
function brik_image( $image, $size = 'large', array $attrs = array() ) {
	if ( is_string( $image ) ) {
		$image = array( 'url' => $image );
	}
	if ( empty( $image['url'] ) && empty( $image['id'] ) ) {
		return '';
	}
	$attrs = array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $attrs );
	if ( ! empty( $image['id'] ) && wp_attachment_is_image( $image['id'] ) ) {
		if ( isset( $image['alt'] ) && ! isset( $attrs['alt'] ) ) {
			$attrs['alt'] = $image['alt'];
		}
		return wp_get_attachment_image( (int) $image['id'], $size, false, $attrs );
	}
	$attrs['src'] = $image['url'];
	$attrs['alt'] = isset( $attrs['alt'] ) ? $attrs['alt'] : ( isset( $image['alt'] ) ? $image['alt'] : '' );
	return '<img' . brik_attrs( $attrs ) . '>';
}

/**
 * URL from an image field value.
 */
function brik_image_url( $image, $size = 'large' ) {
	if ( is_string( $image ) ) {
		return $image;
	}
	if ( ! empty( $image['id'] ) ) {
		$src = wp_get_attachment_image_url( (int) $image['id'], $size );
		if ( $src ) {
			return $src;
		}
	}
	return isset( $image['url'] ) ? $image['url'] : '';
}

/**
 * Whether a post is built with Brik.
 */
function brik_is_built( $post_id = 0 ) {
	return Brik\Data::enabled( $post_id ? $post_id : get_the_ID() );
}

/**
 * Render a theme builder location (header, footer). Returns false when no template applies.
 */
function brik_location( $area ) {
	return Brik\ThemeBuilder::render_location( $area );
}

/**
 * grid-template-columns from a column structure: "1/3,2/3", "3" (equal columns) or raw CSS.
 */
function brik_grid_template( $structure ) {
	$structure = trim( (string) $structure );
	if ( preg_match( '/^\d+$/', $structure ) ) {
		return 'repeat(' . max( 1, (int) $structure ) . ',minmax(0,1fr))';
	}
	if ( preg_match( '/fr|px|%|repeat|auto|minmax/', $structure ) ) {
		return Brik\Style::clean( $structure );
	}
	$cols = array();
	foreach ( explode( ',', $structure ) as $part ) {
		$part = trim( $part );
		if ( false !== strpos( $part, '/' ) ) {
			list( $num, $den ) = array_map( 'floatval', explode( '/', $part, 2 ) );
			$fr                = $den > 0 ? round( $num / $den * 12, 3 ) : 1;
		} else {
			$fr = (float) $part > 0 ? (float) $part : 1;
		}
		$cols[] = 'minmax(0,' . $fr . 'fr)';
	}
	return $cols ? implode( ' ', $cols ) : 'minmax(0,1fr)';
}

/**
 * Resolve a color as it appears outside a section: design tokens are looked up in the
 * page palette (or the dark palette when the neighbouring section is dark), because a
 * divider has to blend with what sits next to it, not with its own section.
 */
function brik_outside_color( $color, $dark ) {
	return preg_replace_callback(
		'/var\(--([a-z-]+)\)/',
		static function ( $m ) use ( $dark ) {
			if ( ! in_array( $m[1], Brik\Settings::token_names(), true ) ) {
				return $m[0];
			}
			if ( $dark ) {
				$tokens = Brik\Settings::tokens( 'dark' );
				return $tokens[ $m[1] ];
			}
			return 'var(--brik-page-' . $m[1] . ')';
		},
		$color
	);
}

/**
 * Fill color for a section divider: the chosen color, otherwise the background of the
 * section it touches (previous one for a top divider, next one for a bottom divider).
 */
function brik_divider_color( $pos, array $a, $ctx = null ) {
	$neighbour = $ctx ? $ctx->renderer->neighbour( $ctx->id, 'top' === $pos ? -1 : 1 ) : null;
	$n_attrs   = $neighbour && isset( $neighbour['attrs'] ) ? (array) $neighbour['attrs'] : array();
	$n_dark    = ! empty( $n_attrs['dark'] );

	$color = isset( $a[ "divider_{$pos}_color" ] ) ? trim( (string) $a[ "divider_{$pos}_color" ] ) : '';
	if ( '' === $color ) {
		$color = ! empty( $n_attrs['bg_color'] ) && is_string( $n_attrs['bg_color'] ) ? $n_attrs['bg_color'] : 'var(--background)';
	}
	return Brik\Style::clean( brik_outside_color( $color, $n_dark ) );
}

/**
 * Section shape divider markup. Shapes are drawn in a 1440×100 box with the filled
 * area at the bottom; top dividers are flipped vertically in CSS.
 */
function brik_divider_svg( $shape, $pos, array $a, $ctx = null ) {
	$paths = array(
		'wave'      => '<path d="M0,60C180,90,360,100,540,86C720,72,900,30,1080,26C1260,22,1350,40,1440,52L1440,100L0,100Z"/>',
		'waves'     => '<path opacity=".25" d="M0,38C240,74,480,8,720,30C960,52,1200,76,1440,40L1440,100L0,100Z"/><path opacity=".5" d="M0,58C200,32,420,82,720,64C1020,46,1220,30,1440,56L1440,100L0,100Z"/><path d="M0,76C240,62,480,92,720,84C960,76,1200,64,1440,78L1440,100L0,100Z"/>',
		'curve'     => '<path d="M0,100C480,10,960,10,1440,100Z"/>',
		'curve-in'  => '<path d="M0,0C480,90,960,90,1440,0L1440,100L0,100Z"/>',
		'tilt'      => '<path d="M0,100L1440,20L1440,100Z"/>',
		'triangle'  => '<path d="M0,100L720,10L1440,100Z"/>',
		'arrow'     => '<path d="M0,100L0,50L670,50L720,0L770,50L1440,50L1440,100Z"/>',
		'mountains' => '<path opacity=".35" d="M0,100L0,46L160,18L380,60L620,8L880,56L1120,20L1440,52L1440,100Z"/><path d="M0,100L0,68L220,42L470,80L720,36L980,74L1210,46L1440,76L1440,100Z"/>',
		'zigzag'    => '<path d="M0,100L0,70' . implode( '', array_map( static function ( $i ) {
			return 'L' . ( $i * 60 + 30 ) . ',40L' . ( $i * 60 + 60 ) . ',70';
		}, range( 0, 23 ) ) ) . 'L1440,100Z"/>',
	);
	if ( ! isset( $paths[ $shape ] ) ) {
		return '';
	}
	$class = brik_cls(
		'brik-shape-divider brik-shape-divider-' . $pos,
		array(
			'brik-shape-divider--flip'  => ! empty( $a[ "divider_{$pos}_flip" ] ),
			'brik-shape-divider--front' => ! empty( $a[ "divider_{$pos}_front" ] ),
		)
	);
	return '<div class="' . esc_attr( $class ) . '" aria-hidden="true"><svg viewBox="0 0 1440 100" preserveAspectRatio="none" fill="' . esc_attr( brik_divider_color( $pos, $a, $ctx ) ) . '">' . $paths[ $shape ] . '</svg></div>';
}

/**
 * shadcn/ui button classes.
 */
function brik_button_class( $variant = 'default', $size = 'default', $extra = '' ) {
	$base = "brik-button inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap transition-all no-underline outline-none cursor-pointer focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4";

	$variants = array(
		'default'     => 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90',
		'destructive' => 'bg-destructive text-white shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20',
		'outline'     => 'border border-input bg-background shadow-xs hover:bg-accent hover:text-accent-foreground',
		'secondary'   => 'bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
		'ghost'       => 'hover:bg-accent hover:text-accent-foreground',
		'link'        => 'text-primary underline-offset-4 hover:underline',
	);
	$sizes = array(
		'sm'      => 'h-8 gap-1.5 rounded-md px-3',
		'default' => 'h-9 px-4 py-2',
		'lg'      => 'h-10 rounded-md px-6',
		'xl'      => 'h-12 rounded-lg px-8 text-base',
		'icon'    => 'size-9',
	);
	return brik_cls(
		$base,
		isset( $variants[ $variant ] ) ? $variants[ $variant ] : $variants['default'],
		isset( $sizes[ $size ] ) ? $sizes[ $size ] : $sizes['default'],
		$extra
	);
}

function brik_button_variants_labels() {
	return array(
		'default'     => __( 'Primary', 'brik-builder' ),
		'secondary'   => __( 'Secondary', 'brik-builder' ),
		'outline'     => __( 'Outline', 'brik-builder' ),
		'ghost'       => __( 'Ghost', 'brik-builder' ),
		'link'        => __( 'Link', 'brik-builder' ),
		'destructive' => __( 'Destructive', 'brik-builder' ),
	);
}

/**
 * Column weights from a structure: "1/3,2/3" → [4, 8], "3" → [1, 1, 1].
 */
function brik_column_weights( $structure, $count ) {
	$structure = trim( (string) $structure );
	$weights   = array();
	if ( ! preg_match( '/^\d+$/', $structure ) ) {
		foreach ( explode( ',', $structure ) as $part ) {
			$part = trim( $part );
			if ( false !== strpos( $part, '/' ) ) {
				list( $num, $den ) = array_map( 'floatval', explode( '/', $part, 2 ) );
				$weights[]         = $den > 0 ? round( $num / $den * 12, 3 ) : 1;
			} elseif ( is_numeric( $part ) && $part > 0 ) {
				$weights[] = (float) $part;
			}
		}
	}
	$out = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$out[] = isset( $weights[ $i ] ) ? $weights[ $i ] : 1;
	}
	return $out;
}

/**
 * Layout CSS for a row in one device state (grid for plain structures, flexbox otherwise).
 */
function brik_row_layout_css( array $a, $state, $wrap, array $ids ) {
	$get = static function ( $key ) use ( $a, $state ) {
		$v = Brik\Style::value( $a, $key, $state );
		return null === $v ? '' : (string) $v;
	};

	$direction = $get( 'direction' );
	$structure = '' !== $get( 'columns' ) ? $get( 'columns' ) : '1';
	$sizing    = $get( 'sizing' );
	$justify   = Brik\Style::clean( $get( 'justify' ) );
	$wraps     = ! empty( Brik\Style::value( $a, 'wrap', $state ) );

	// Phones stack unless the row says otherwise for small screens.
	if ( 'mobile' === $state ) {
		$own = false;
		foreach ( array( 'direction', 'columns', 'sizing' ) as $key ) {
			if ( null !== Brik\Style::raw_value( $a, $key, 'mobile' ) ) {
				$own = true;
			}
		}
		if ( ! $own && ! in_array( $direction, array( 'vertical', 'vertical-reverse' ), true ) ) {
			$direction = ! empty( $a['reverse'] ) ? 'vertical-reverse' : 'vertical';
		}
	}

	$kids = $wrap . '>.brik-column';
	if ( in_array( $direction, array( 'vertical', 'vertical-reverse' ), true ) ) {
		// Stacked columns take the full width unless the row asks them to fit their content.
		return $wrap . '{display:flex;flex-direction:' . ( 'vertical' === $direction ? 'column' : 'column-reverse' ) . ( $justify ? ';justify-content:' . $justify : '' ) . '}'
			. $kids . ( 'auto' === $sizing ? '{flex:0 0 auto;width:auto;max-width:100%}' : '{flex:0 0 auto;width:100%;max-width:100%}' );
	}

	if ( 'horizontal-reverse' !== $direction && '' === $sizing ) {
		return $wrap . '{display:grid;grid-template-columns:' . brik_grid_template( $structure ) . ( $justify ? ';justify-content:' . $justify : '' ) . '}'
			. $kids . '{flex:none}';
	}

	$css = $wrap . '{display:flex;flex-direction:' . ( 'horizontal-reverse' === $direction ? 'row-reverse' : 'row' ) . ';flex-wrap:' . ( $wraps ? 'wrap' : 'nowrap' ) . ( $justify ? ';justify-content:' . $justify : '' ) . '}';
	if ( 'auto' === $sizing ) {
		return $css . $kids . '{flex:0 1 auto}';
	}
	if ( 'equal' === $sizing ) {
		return $css . $kids . '{flex:1 1 0%}';
	}
	foreach ( brik_column_weights( $structure, count( $ids ) ) as $i => $weight ) {
		$css .= $wrap . '>.brik-n-' . $ids[ $i ] . '{flex:' . $weight . ' 1 0%}';
	}
	return $css;
}
