<?php
/**
 * Helpers shared by the basic & content modules.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repeater value as a list of item arrays (empty attrs arrive as '').
 */
function brik_items( $value ) {
	if ( ! is_array( $value ) ) {
		return array();
	}
	return array_values( array_filter( $value, 'is_array' ) );
}

/**
 * Read a key from a repeater item without notices.
 */
function brik_item( array $item, $key, $fallback = '' ) {
	return isset( $item[ $key ] ) && '' !== $item[ $key ] && null !== $item[ $key ] ? $item[ $key ] : $fallback;
}

/**
 * Whether a link field value points somewhere.
 */
function brik_has_link( $link ) {
	if ( is_string( $link ) ) {
		return '' !== trim( $link );
	}
	return is_array( $link ) && ! empty( $link['url'] );
}

/**
 * Wrap markup in an anchor when the link is set.
 */
function brik_maybe_link( $html, $link, array $extra = array() ) {
	if ( ! brik_has_link( $link ) ) {
		return $html;
	}
	return '<a' . brik_link_attrs( $link, $extra ) . '>' . $html . '</a>';
}

/**
 * Non-empty, trimmed lines of a textarea value.
 */
function brik_lines( $text ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
	return array_values( array_filter( array_map( 'trim', $lines ), 'strlen' ) );
}

/**
 * Whether an image field value holds an image.
 */
function brik_has_image( $image ) {
	if ( is_string( $image ) ) {
		return '' !== trim( $image );
	}
	return is_array( $image ) && ( ! empty( $image['url'] ) || ! empty( $image['id'] ) );
}

/**
 * Up to two initials from a name.
 */
function brik_initials( $name ) {
	$name  = trim( wp_strip_all_tags( (string) $name ) );
	$parts = preg_split( '/\s+/', $name );
	$out   = '';
	foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $part ) {
		$out .= function_exists( 'mb_substr' ) ? mb_substr( $part, 0, 1 ) : substr( $part, 0, 1 );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
}

/**
 * shadcn avatar: image with an initials fallback.
 *
 * @param mixed  $image Image field value.
 * @param string $name  Used for alt text and initials.
 * @param string $size  xs|sm|default|lg|xl|2xl.
 * @param string $shape circle|rounded|square.
 * @param string $extra    Extra classes.
 * @param string $fallback Text shown instead of the initials (e.g. "+3").
 */
function brik_avatar( $image, $name = '', $size = 'default', $shape = 'circle', $extra = '', $fallback = null ) {
	$sizes  = array(
		'xs'      => 'size-6 text-[10px]',
		'sm'      => 'size-8 text-xs',
		'default' => 'size-10 text-sm',
		'lg'      => 'size-12 text-base',
		'xl'      => 'size-16 text-lg',
		'2xl'     => 'size-24 text-2xl',
		'3xl'     => 'size-32 text-3xl',
	);
	$shapes = array(
		'circle'  => 'rounded-full',
		'rounded' => 'rounded-lg',
		'square'  => 'rounded-none',
	);
	$class  = brik_cls(
		'brik-avatar relative flex shrink-0 items-center justify-center overflow-hidden bg-muted font-medium text-muted-foreground select-none',
		isset( $sizes[ $size ] ) ? $sizes[ $size ] : $sizes['default'],
		isset( $shapes[ $shape ] ) ? $shapes[ $shape ] : $shapes['circle'],
		$extra
	);
	if ( brik_has_image( $image ) ) {
		$inner = brik_image( $image, 'thumbnail', array( 'class' => 'aspect-square size-full object-cover', 'alt' => wp_strip_all_tags( (string) $name ) ) );
	} else {
		$initials = null !== $fallback ? (string) $fallback : brik_initials( $name );
		$inner    = '' !== $initials ? '<span aria-hidden="true">' . esc_html( $initials ) . '</span>' : brik_icon( 'user', 'size-1/2' );
		if ( '' !== trim( (string) $name ) ) {
			$inner .= '<span class="sr-only">' . esc_html( wp_strip_all_tags( (string) $name ) ) . '</span>';
		}
	}
	return '<span class="' . esc_attr( $class ) . '">' . $inner . '</span>';
}

/**
 * Star rating as inline SVGs; fractional ratings fill the last star partially.
 *
 * @param float  $rating Rating value.
 * @param int    $max    Number of stars.
 * @param string $class  Size classes for each star.
 */
function brik_stars( $rating, $max = 5, $class = 'size-4' ) {
	$max    = max( 1, min( 10, (int) $max ) );
	$rating = max( 0, min( $max, (float) $rating ) );
	$path   = '<path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"/>';
	$out    = '';
	for ( $i = 1; $i <= $max; $i++ ) {
		$fill = max( 0, min( 1, $rating - ( $i - 1 ) ) );
		$out .= '<span class="brik-star relative inline-block shrink-0 ' . esc_attr( $class ) . '">';
		$out .= '<svg class="brik-star-empty absolute inset-0 size-full text-muted-foreground/30" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $path . '</svg>';
		if ( $fill > 0 ) {
			$out .= '<span class="absolute inset-y-0 left-0 overflow-hidden" style="width:' . esc_attr( round( $fill * 100, 2 ) ) . '%"><svg class="brik-star-full h-full aspect-square text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $path . '</svg></span>';
		}
		$out .= '</span>';
	}
	/* translators: 1: rating, 2: maximum rating */
	$label = sprintf( __( 'Rated %1$s out of %2$s', 'brik-builder' ), rtrim( rtrim( number_format( $rating, 1, '.', '' ), '0' ), '.' ), $max );
	return '<span class="brik-stars inline-flex items-center gap-0.5" role="img" aria-label="' . esc_attr( $label ) . '">' . $out . '</span>';
}

/**
 * shadcn badge classes, plus tinted success/warning/info variants.
 */
function brik_badge_class( $variant = 'default', $size = 'default', $extra = '' ) {
	$variants = array(
		'default'     => 'border-transparent bg-primary text-primary-foreground [a&]:hover:bg-primary/90',
		'secondary'   => 'border-transparent bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90',
		'outline'     => 'border-border text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
		'destructive' => 'border-transparent bg-destructive text-white dark:bg-destructive/60 [a&]:hover:bg-destructive/90',
		'success'     => 'border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 [a&]:hover:bg-emerald-500/25',
		'warning'     => 'border-transparent bg-amber-500/15 text-amber-700 dark:text-amber-300 [a&]:hover:bg-amber-500/25',
		'info'        => 'border-transparent bg-sky-500/15 text-sky-700 dark:text-sky-300 [a&]:hover:bg-sky-500/25',
	);
	$sizes    = array(
		'sm'      => 'px-1.5 py-px text-[11px] [&>svg]:size-3',
		'default' => 'px-2 py-0.5 text-xs [&>svg]:size-3',
		'lg'      => 'px-3 py-1 text-sm [&>svg]:size-3.5',
	);
	return brik_cls(
		'brik-badge-pill inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border font-medium whitespace-nowrap no-underline transition-[color,box-shadow,background-color] outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [&>svg]:pointer-events-none',
		isset( $variants[ $variant ] ) ? $variants[ $variant ] : $variants['default'],
		isset( $sizes[ $size ] ) ? $sizes[ $size ] : $sizes['default'],
		$extra
	);
}

function brik_badge_variant_options() {
	return array(
		'default'     => __( 'Default', 'brik-builder' ),
		'secondary'   => __( 'Secondary', 'brik-builder' ),
		'outline'     => __( 'Outline', 'brik-builder' ),
		'destructive' => __( 'Destructive', 'brik-builder' ),
		'success'     => __( 'Success', 'brik-builder' ),
		'warning'     => __( 'Warning', 'brik-builder' ),
		'info'        => __( 'Info', 'brik-builder' ),
	);
}

/**
 * Icon inside an optional shape (used by icon, blurb, stats, timeline, icon list).
 *
 * @param string $icon  Icon name.
 * @param string $shape none|circle|rounded|square.
 * @param string $tone  soft|solid|outline|muted.
 * @param string $size  sm|default|lg|xl sets the shape and default icon size; auto pads around the icon.
 * @param string $extra Extra classes on the shape.
 */
function brik_icon_shape( $icon, $shape = 'rounded', $tone = 'soft', $size = 'default', $extra = '' ) {
	$svg = brik_icon( $icon, 'brik-icon-svg size-[var(--brik-icon-size,1.25rem)]' );
	if ( '' === $svg ) {
		return '';
	}
	if ( 'none' === $shape || '' === $shape ) {
		$tones = array(
			'soft'    => 'text-primary',
			'solid'   => 'text-primary',
			'outline' => 'text-foreground',
			'muted'   => 'text-muted-foreground',
		);
		return '<span class="' . esc_attr( brik_cls( 'brik-icon-shape inline-flex shrink-0 items-center justify-center', isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tones['soft'], $extra ) ) . '">' . $svg . '</span>';
	}
	$shapes = array(
		'circle'  => 'rounded-full',
		'rounded' => 'rounded-lg',
		'square'  => 'rounded-none',
	);
	$tones  = array(
		'soft'    => 'bg-primary/10 text-primary',
		'solid'   => 'bg-primary text-primary-foreground shadow-xs',
		'outline' => 'border bg-background text-foreground shadow-xs',
		'muted'   => 'bg-muted text-foreground',
	);
	$sizes  = array(
		'sm'      => 'size-8 [--brik-icon-size:1rem]',
		'default' => 'size-11 [--brik-icon-size:1.25rem]',
		'lg'      => 'size-14 [--brik-icon-size:1.75rem]',
		'xl'      => 'size-20 [--brik-icon-size:2.5rem]',
		'auto'    => 'p-[calc(var(--brik-icon-size,1.25rem)*0.5)]',
	);
	$class  = brik_cls(
		'brik-icon-shape inline-flex shrink-0 items-center justify-center transition-colors',
		isset( $shapes[ $shape ] ) ? $shapes[ $shape ] : $shapes['rounded'],
		isset( $tones[ $tone ] ) ? $tones[ $tone ] : $tones['soft'],
		isset( $sizes[ $size ] ) ? $sizes[ $size ] : $sizes['default'],
		$extra
	);
	return '<span class="' . esc_attr( $class ) . '">' . $svg . '</span>';
}

/**
 * Options shared by modules with an icon shape.
 */
function brik_icon_shape_options() {
	return array(
		'none'    => __( 'None', 'brik-builder' ),
		'circle'  => __( 'Circle', 'brik-builder' ),
		'rounded' => __( 'Rounded square', 'brik-builder' ),
		'square'  => __( 'Square', 'brik-builder' ),
	);
}

function brik_icon_tone_options() {
	return array(
		'soft'    => __( 'Soft (tinted)', 'brik-builder' ),
		'solid'   => __( 'Solid', 'brik-builder' ),
		'outline' => __( 'Outline', 'brik-builder' ),
		'muted'   => __( 'Muted', 'brik-builder' ),
	);
}

/**
 * Brand networks from resources/brands.json as select options.
 */
function brik_brand_options() {
	$out = array();
	foreach ( Brik\Icons::brands() as $slug => $brand ) {
		$out[ $slug ] = $brand['title'];
	}
	$out['email']   = __( 'Email', 'brik-builder' );
	$out['website'] = __( 'Website', 'brik-builder' );
	$out['rss']     = __( 'RSS', 'brik-builder' );
	return $out;
}

/**
 * Icon, label and color for a social network slug. Non-brand entries use Lucide icons.
 */
function brik_brand( $slug ) {
	$brands = Brik\Icons::brands();
	if ( isset( $brands[ $slug ] ) ) {
		return array(
			'icon'  => 'brand:' . $slug,
			'title' => $brands[ $slug ]['title'],
			'hex'   => '#' . $brands[ $slug ]['hex'],
		);
	}
	$other = array(
		'email'   => array( 'mail', __( 'Email', 'brik-builder' ), '#525252' ),
		'website' => array( 'globe', __( 'Website', 'brik-builder' ), '#525252' ),
		'rss'     => array( 'rss', __( 'RSS', 'brik-builder' ), '#F26522' ),
	);
	$o = isset( $other[ $slug ] ) ? $other[ $slug ] : $other['website'];
	return array(
		'icon'  => $o[0],
		'title' => $o[1],
		'hex'   => $o[2],
	);
}

/**
 * Responsive grid columns CSS for modules with a "columns" setting.
 *
 * Tablet falls back to min(desktop, $tablet_max) and mobile to $mobile unless set explicitly,
 * so a 4-column layout doesn't get squeezed on small screens.
 */
function brik_grid_css( array $a, $selector, $key = 'columns', $default = 3, $tablet_max = 2, $mobile = 1 ) {
	$d = isset( $a[ $key ] ) && '' !== $a[ $key ] ? max( 1, min( 6, (int) $a[ $key ] ) ) : $default;
	$t = isset( $a[ $key . '@tablet' ] ) && '' !== $a[ $key . '@tablet' ] ? max( 1, min( 6, (int) $a[ $key . '@tablet' ] ) ) : min( $d, $tablet_max );
	$m = isset( $a[ $key . '@mobile' ] ) && '' !== $a[ $key . '@mobile' ] ? max( 1, min( 6, (int) $a[ $key . '@mobile' ] ) ) : min( $t, $mobile );

	$rule = static function ( $n ) use ( $selector ) {
		return $selector . '{grid-template-columns:repeat(' . $n . ',minmax(0,1fr))}';
	};
	return $rule( $d )
		. '@media (max-width:' . Brik\Style::TABLET . 'px){' . $rule( $t ) . '}'
		. '@media (max-width:' . Brik\Style::MOBILE . 'px){' . $rule( $m ) . '}';
}

/**
 * Parse CSV text into rows of cells. Supports quoted cells; delimiter is auto-detected (comma, semicolon, tab).
 */
function brik_parse_csv( $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return array();
	}
	$first = strtok( $text, "\n" );
	$delim = ',';
	if ( substr_count( $first, "\t" ) > substr_count( $first, $delim ) ) {
		$delim = "\t";
	} elseif ( substr_count( $first, ';' ) > substr_count( $first, $delim ) ) {
		$delim = ';';
	}
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
		if ( '' === trim( $line ) ) {
			continue;
		}
		$rows[] = array_map( 'trim', str_getcsv( $line, $delim, '"', '\\' ) );
	}
	return $rows;
}
