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
 * Section shape divider markup.
 */
function brik_divider_svg( $shape, $pos, array $a ) {
	$paths = array(
		'wave'      => '<path d="M0,64C240,112,480,112,720,72C960,32,1200,16,1440,48L1440,100L0,100Z"/>',
		'waves'     => '<path opacity=".33" d="M0,40C320,100,560,0,860,40C1100,72,1280,30,1440,20L1440,100L0,100Z"/><path opacity=".66" d="M0,70C240,30,520,100,800,60C1060,24,1260,80,1440,50L1440,100L0,100Z"/><path d="M0,85C360,60,720,100,1080,80C1260,70,1360,75,1440,82L1440,100L0,100Z"/>',
		'curve'     => '<path d="M0,0Q720,140,1440,0L1440,100L0,100Z"/>',
		'tilt'      => '<path d="M0,100L1440,0L1440,100Z"/>',
		'triangle'  => '<path d="M0,100L720,0L1440,100Z"/>',
		'arrow'     => '<path d="M0,100L0,40L680,40L720,0L760,40L1440,40L1440,100Z"/>',
		'mountains' => '<path opacity=".5" d="M0,100L0,50L180,20L420,65L640,15L900,60L1150,25L1440,55L1440,100Z"/><path d="M0,100L0,70L240,40L480,80L720,35L960,75L1200,45L1440,80L1440,100Z"/>',
		'zigzag'    => '<path d="M0,100L0,60' . implode( '', array_map( static function ( $i ) {
			return 'L' . ( $i * 60 + 30 ) . ',30L' . ( $i * 60 + 60 ) . ',60';
		}, range( 0, 23 ) ) ) . 'L1440,100Z"/>',
	);
	if ( ! isset( $paths[ $shape ] ) ) {
		return '';
	}
	$color = ! empty( $a[ "divider_{$pos}_color" ] ) ? Brik\Style::clean( $a[ "divider_{$pos}_color" ] ) : 'var(--background)';
	$class = brik_cls(
		'brik-divider brik-divider-' . $pos,
		array(
			'brik-divider--flip'  => ! empty( $a[ "divider_{$pos}_flip" ] ),
			'brik-divider--front' => ! empty( $a[ "divider_{$pos}_front" ] ),
		)
	);
	return '<div class="' . esc_attr( $class ) . '" aria-hidden="true"><svg viewBox="0 0 1440 100" preserveAspectRatio="none" fill="' . esc_attr( $color ) . '">' . $paths[ $shape ] . '</svg></div>';
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
		'default'     => __( 'Primary', 'brik' ),
		'secondary'   => __( 'Secondary', 'brik' ),
		'outline'     => __( 'Outline', 'brik' ),
		'ghost'       => __( 'Ghost', 'brik' ),
		'link'        => __( 'Link', 'brik' ),
		'destructive' => __( 'Destructive', 'brik' ),
	);
}
