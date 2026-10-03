<?php
/**
 * Helpers shared by the 3D, WebGL and scroll-driven showpiece modules (globe, world map,
 * device frames, scroll scenes).
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'brik/module_categories',
	static function ( $categories ) {
		if ( ! isset( $categories['effects'] ) ) {
			$categories['effects'] = __( 'Effects', 'brik-builder' );
		}
		return $categories;
	}
);

/**
 * Land mask: a 180×90 equirectangular bitmap (2° cells, row 0 at 90°N, column 0 at 180°W),
 * one bit per cell, most significant bit first, base64 encoded. Rasterised from the
 * public-domain Natural Earth 1:110m land polygons. The globe script decodes the same string.
 */
function brik_3d_land_mask() {
	return 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAB/+Af/AAAAAAAAAAAAAAAAAAAAAAAA//////+AAHgAAAADgAAAAAAAAAAAAN//////4AB/gAAAAB8AAAAAAAAAAB7Cz4////wAAZAAAMAAPwAAAAAAAAACPm/wAf//wAAAAADwAf/4AHIAAAAAAH/n38AP//gAAAAAOBD///8DgAAgH4ACf73/gP//gAAA4AMHf//////wAgf////79nwH//AAAP/gL//////////8f//////h+H/gAAAf/7///////////c///////n8D8A+AB+/////////////Af/////508D4AIAH9/////////////Af/////gHgB4AAAP5///////////v4AHoP///gH2AAAAAH4//////////+MAADQB///8H+AAAAGCx/////////8A8AAMAA////v/gAAAPDv/////////wA4AAAABf///v/wAAAbv///////////AwAAAAAP/////wAAAH////////////AAAAAAAH////8YAAAD///////////9AAAAAAAD////+cAAAB///////////9AAAAAAAD/////AAAAB//5ff//////5AAAAAAAD////gAAAAf5vwPP//////jAAAAAAAD////gAAAAfi///n/////+CAAAAAAAD///+AAAAAfALf/n////+MCAAAAAAAB///8AAAAAOeRP///////mOAAAAAAAB///8AAAAAH+AC///////G8AAAAAAAAf//4AAAAAf/iB///////hgAAAAAAAAP//wAAAAAf/7////////gAAAAAAAAAP/gwAAAAA/////f/////gAAAAAAAAAF/AQAAAAB///+/v/////AAAAAAAAAAC+AAAAAAD/////1/////gAAAAAAAAAAeA4AAAAD////f/D///8gAAAAAAAAAAfGOAAAAH////v/B/z/wAAAAAAAgAAAfcBgAAAD////v+A/h+gAAAAAAAAAAAH8AAAAAD////34A+B/AgAAAAAAAAAAAfAAAAAH/////wAcAfggAAAAAAAAAAAHgAAAAH////+AAcAfgQAAAAAAAAAAABD4AAAD/////wAMAXgYAAAAAAAAAAAA//AAAB/////gAKASAYAAAAAAAAAAAAH/gAAA/////gACAIAIAAAAAAAAAAAAH/8AAAfP///AAAAsGAAAAAAAAAAAAAH/+AAAAB///AAAA8OAAAAAAAAAAAAAP/+AAAAD//8AAAAc+kAAAAAAAAAAAAf//gAAAD//4AAAAMfggAAAAAAAAAAAP//8AAAB//wAAAAGdi/AAAAAAAAAAAf///AAAA//wAAAACAgPkAAAAAAAAAAP///gAAA//wAAAAB4AHwAAAAAAAAAAP///gAAA//wAAAAAIIDYAAAAAAAAAAH///AAAAf/wAAAAAAAAAAAAAAAAAAAH//+AAAA//4gAAAAABxAAAAAAAAAAAD//+AAAA//xgAAAAAPxgAAAAAAAAAAA//+AAAA//zgAAAAAf/gAAAAAAAAAAAf/8AAAA//DgAAAAA//wAAAAAAAAAAAf/8AAAAf/DAAAAAD//4CAAAAAAAAAAf/4AAAAf/DAAAAAH//8AAAAAAAAAAA//AAAAAf+DAAAAAH//8AAAAAAAAAAA//AAAAAf+AAAAAAH//+AAAAAAAAAAA//AAAAAP8AAAAAAH//+AAAAAAAAAAA/+AAAAAH4AAAAAAD//+AAAAAAAAAAA/8AAAAAHwAAAAAAD4f8AAAAAAAAAAA/4AAAAAAAAAAAAACAH8AAAAAAAAAAB/wAAAAAAAAAAAAAAAD4AEAAAAAAAAB/gAAAAAAAAAAAAAAABQAGAAAAAAAAB+AAAAAAAAAAAAAAAAAwAMAAAAAAAAB8AAAAAAAAAAAAAAAAAQAYAAAAAAAAD4AAAAAAAAAAAAAAAAAABwAAAAAAAAD4AAAAAAAAAAAAAAAAAAAgAAAAAAAAD4AAAAAAAAAACAAAAAAAAAAAAAAAAADwgAAAAAAAAAAAAAAAAAAAAAAAAAAABwAAAAAAAAAAAAAAAAAAAAAAAAAAAAA4AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAAAAAAAB/AB/////gAAAAAAAAAAAsAAAAAAADv/+P//////gAAAAAAAAAB+AAAAf/////8////////gAAAABAP/J/AAAD///////////////gAAB//////+AAAP//////////////+AAB///////gAAP///////////////8AAM//////4ABw////////////////+AAAf//////9Hn////////////////4AAAf//////////////////////////A//////////////////////////////////////////////////////////////////////////////////////////';
}

/**
 * Whether the 2° cell at a column/row of the land mask is land.
 */
function brik_3d_is_land_cell( $col, $row ) {
	static $bytes = null;
	if ( null === $bytes ) {
		$bytes = base64_decode( brik_3d_land_mask() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- static bitmap, not obfuscation.
	}
	if ( $col < 0 || $col >= 180 || $row < 0 || $row >= 90 ) {
		return false;
	}
	$bit = $row * 180 + $col;
	return (bool) ( ord( $bytes[ $bit >> 3 ] ) & ( 0x80 >> ( $bit & 7 ) ) );
}

/**
 * SVG path covering the land cells between 84°N and 58°S, in map units where
 * x = longitude + 180 and y = 90 - latitude. Filled with a dot pattern it draws the dotted map.
 * Consecutive land cells merge into one rectangle per run, which keeps the markup small.
 */
function brik_3d_world_path() {
	static $path = null;
	if ( null !== $path ) {
		return $path;
	}
	$path = '';
	for ( $row = 3; $row < 74; $row++ ) {
		$col = 0;
		while ( $col < 180 ) {
			if ( ! brik_3d_is_land_cell( $col, $row ) ) {
				++$col;
				continue;
			}
			$start = $col;
			while ( $col < 180 && brik_3d_is_land_cell( $col, $row ) ) {
				++$col;
			}
			$path .= 'M' . ( $start * 2 ) . ' ' . ( $row * 2 ) . 'h' . ( ( $col - $start ) * 2 ) . 'v2h-' . ( ( $col - $start ) * 2 ) . 'z';
		}
	}
	return $path;
}

/**
 * Clamp a latitude or longitude coming from a number field.
 */
function brik_3d_coord( $value, $limit ) {
	$value = is_numeric( $value ) ? (float) $value : 0.0;
	return round( max( -$limit, min( $limit, $value ) ), 4 );
}

/**
 * Repeater rows that are arrays, re-indexed.
 */
function brik_3d_rows( $value ) {
	return is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
}

/**
 * Normalised connections (arcs) from a routes repeater.
 *
 * @return array[] Each: from [lat, lng], to [lat, lng], from_label, to_label, color.
 */
function brik_3d_routes( $value ) {
	$out = array();
	foreach ( brik_3d_rows( $value ) as $row ) {
		$row   = array_merge( array( 'from_lat' => 0, 'from_lng' => 0, 'to_lat' => 0, 'to_lng' => 0, 'from_label' => '', 'to_label' => '', 'color' => '' ), $row );
		$out[] = array(
			'from'       => array( brik_3d_coord( $row['from_lat'], 90 ), brik_3d_coord( $row['from_lng'], 180 ) ),
			'to'         => array( brik_3d_coord( $row['to_lat'], 90 ), brik_3d_coord( $row['to_lng'], 180 ) ),
			'from_label' => wp_strip_all_tags( (string) $row['from_label'] ),
			'to_label'   => wp_strip_all_tags( (string) $row['to_label'] ),
			'color'      => brik_3d_color( $row['color'] ),
		);
	}
	return $out;
}

/**
 * A few well-known cities, used for default arcs.
 */
function brik_3d_city( $key ) {
	$cities = array(
		'nyc' => array( 40.71, -74.01, __( 'New York', 'brik-builder' ) ),
		'lon' => array( 51.51, -0.13, __( 'London', 'brik-builder' ) ),
		'dxb' => array( 25.2, 55.27, __( 'Dubai', 'brik-builder' ) ),
		'sin' => array( 1.35, 103.82, __( 'Singapore', 'brik-builder' ) ),
		'syd' => array( -33.87, 151.21, __( 'Sydney', 'brik-builder' ) ),
		'sfo' => array( 37.77, -122.42, __( 'San Francisco', 'brik-builder' ) ),
		'tyo' => array( 35.68, 139.69, __( 'Tokyo', 'brik-builder' ) ),
		'sao' => array( -23.55, -46.63, __( 'São Paulo', 'brik-builder' ) ),
		'los' => array( 6.52, 3.38, __( 'Lagos', 'brik-builder' ) ),
		'nbo' => array( -1.29, 36.82, __( 'Nairobi', 'brik-builder' ) ),
		'bom' => array( 19.08, 72.88, __( 'Mumbai', 'brik-builder' ) ),
	);
	return $cities[ $key ];
}

/**
 * Default routes repeater value from pairs of city keys.
 */
function brik_3d_default_routes( array $pairs ) {
	$out = array();
	foreach ( $pairs as $pair ) {
		$a     = brik_3d_city( $pair[0] );
		$b     = brik_3d_city( $pair[1] );
		$out[] = array(
			'from_lat'   => $a[0],
			'from_lng'   => $a[1],
			'from_label' => $a[2],
			'to_lat'     => $b[0],
			'to_lng'     => $b[1],
			'to_label'   => $b[2],
			'color'      => '',
		);
	}
	return $out;
}

/**
 * Sub-fields of the routes repeater shared by the globe and the world map.
 */
function brik_3d_route_fields() {
	$f = static function ( $label, $limit ) {
		return Brik\Fields::field( 'number', $label, 'content', array( 'min' => -$limit, 'max' => $limit, 'step' => 0.01 ) );
	};
	return array(
		'from_label' => Brik\Fields::field( 'text', __( 'From (label)', 'brik-builder' ), 'content' ),
		'from_lat'   => $f( __( 'From latitude', 'brik-builder' ), 90 ),
		'from_lng'   => $f( __( 'From longitude', 'brik-builder' ), 180 ),
		'to_label'   => Brik\Fields::field( 'text', __( 'To (label)', 'brik-builder' ), 'content' ),
		'to_lat'     => $f( __( 'To latitude', 'brik-builder' ), 90 ),
		'to_lng'     => $f( __( 'To longitude', 'brik-builder' ), 180 ),
		'color'      => Brik\Fields::field( 'color', __( 'Arc color', 'brik-builder' ), 'content', array( 'description' => __( 'Leave empty to use the module arc colors.', 'brik-builder' ) ) ),
	);
}

/**
 * Sanitise a color value for use inside a style attribute or a data attribute read by
 * the effect scripts. Allows hex, rgb()/hsl()/oklch() functions, var(--token) and names.
 */
function brik_3d_color( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || ! preg_match( '/^[#a-zA-Z0-9(),.%\s\/+-]+$/', $value ) ) {
		return '';
	}
	return $value;
}

/**
 * Image or video markup for a media slot (device screens, scroll scenes).
 *
 * @param mixed $image Image field value.
 * @param mixed $video Video URL or {url}. Wins over the image when set.
 * @param array $args  class (applied to img/video/iframe), size, alt, eager.
 */
function brik_3d_media( $image, $video, array $args = array() ) {
	$args  = array_merge( array( 'class' => '', 'size' => 'large', 'alt' => null, 'eager' => false ), $args );
	$class = trim( 'brik-3d-media ' . $args['class'] );
	$info  = brik_video_info( $video );

	if ( '' !== $info['url'] ) {
		if ( 'file' !== $info['provider'] ) {
			$src = brik_video_embed_url( $info, array( 'autoplay' => true, 'muted' => true, 'loop' => true, 'controls' => false ) );
			return '<iframe class="' . esc_attr( $class . ' brik-3d-embed pointer-events-none' ) . '" src="' . esc_url( $src ) . '" title="' . esc_attr__( 'Video', 'brik-builder' ) . '" allow="autoplay; encrypted-media; picture-in-picture" loading="lazy" tabindex="-1"></iframe>';
		}
		$poster = brik_image_url( $image, $args['size'] );
		return '<video' . brik_attrs(
			array(
				'class'       => $class,
				'src'         => $info['url'],
				'poster'      => $poster ? esc_url_raw( $poster ) : null,
				'autoplay'    => true,
				'muted'       => true,
				'loop'        => true,
				'playsinline' => true,
				'preload'     => 'metadata',
				'aria-hidden' => 'true',
			)
		) . '></video>';
	}

	$items = brik_media_items( $image, $args['size'] );
	if ( ! $items ) {
		return '';
	}
	$attrs = array( 'class' => $class );
	if ( null !== $args['alt'] ) {
		$attrs['alt'] = $args['alt'];
	}
	if ( $args['eager'] ) {
		$attrs['loading']       = 'eager';
		$attrs['fetchpriority'] = 'high';
	}
	return brik_media_img( $items[0], $args['size'], $attrs );
}

/**
 * Inline style attribute from a map of CSS custom properties, skipping empty values.
 */
function brik_3d_vars( array $vars ) {
	$out = '';
	foreach ( $vars as $name => $value ) {
		if ( null === $value || '' === $value ) {
			continue;
		}
		$out .= $name . ':' . $value . ';';
	}
	return '' === $out ? '' : ' style="' . esc_attr( $out ) . '"';
}

/**
 * Number from an attribute, clamped, with a fallback for empty values.
 */
function brik_3d_num( $value, $fallback, $min, $max ) {
	if ( ! is_numeric( $value ) ) {
		return $fallback;
	}
	return max( $min, min( $max, (float) $value ) );
}

/**
 * Screen ratios offered by the device frames and scroll scenes.
 */
function brik_3d_ratio_options() {
	return array(
		'16:10' => '16:10',
		'16:9'  => '16:9',
		'4:3'   => '4:3',
		'3:2'   => '3:2',
		'21:9'  => '21:9',
		'1:1'   => '1:1',
		'3:4'   => '3:4',
		'9:16'  => '9:16',
	);
}

function brik_3d_ratio_class( $ratio ) {
	$map = array(
		'16:10' => 'aspect-[16/10]',
		'16:9'  => 'aspect-video',
		'4:3'   => 'aspect-[4/3]',
		'3:2'   => 'aspect-[3/2]',
		'21:9'  => 'aspect-[21/9]',
		'1:1'   => 'aspect-square',
		'3:4'   => 'aspect-[3/4]',
		'9:16'  => 'aspect-[9/16]',
	);
	return isset( $map[ $ratio ] ) ? $map[ $ratio ] : 'aspect-[16/10]';
}

/**
 * Device frame around a screen (browser window, phone, tablet, laptop or a plain card).
 * The frames are drawn in CSS (effects-3d.css) so they stay crisp at any size.
 *
 * @param string $device browser|phone|tablet|laptop|plain.
 * @param string $media  Screen contents (img, video or iframe markup).
 * @param array  $args   theme (dark|light), ratio, url, class.
 */
function brik_3d_device( $device, $media, array $args = array() ) {
	$args   = array_merge( array( 'theme' => 'dark', 'ratio' => '16:10', 'url' => '', 'class' => '' ), $args );
	$device = in_array( $device, array( 'browser', 'phone', 'tablet', 'laptop', 'plain' ), true ) ? $device : 'browser';
	$theme  = 'light' === $args['theme'] ? 'light' : 'dark';
	$ratio  = 'phone' === $device ? 'aspect-[9/19.5]' : brik_3d_ratio_class( $args['ratio'] );
	$screen = '<div class="' . esc_attr( 'brik-device-screen relative overflow-hidden ' . $ratio ) . '">' . $media . ( 'phone' === $device ? '<span class="brik-device-island" aria-hidden="true"></span>' : '' ) . '<span class="brik-device-glare" aria-hidden="true"></span></div>';

	if ( 'browser' === $device ) {
		$url    = '' !== (string) $args['url'] ? wp_strip_all_tags( $args['url'] ) : '';
		$bar    = '<div class="brik-device-bar flex items-center gap-3 px-4" aria-hidden="true"><span class="brik-device-lights flex shrink-0 gap-1.5"><i></i><i></i><i></i></span><span class="brik-device-url mx-auto flex min-w-0 items-center justify-center gap-1.5 truncate rounded-md px-3 text-xs">' . ( '' !== $url ? brik_icon( 'lock', 'size-3 shrink-0 opacity-60' ) . '<span class="truncate">' . esc_html( $url ) . '</span>' : '' ) . '</span><span class="w-10 shrink-0 max-sm:hidden"></span></div>';
		$screen = $bar . $screen;
	} elseif ( 'laptop' === $device ) {
		$screen = '<div class="brik-device-lid">' . $screen . '</div><div class="brik-device-base" aria-hidden="true"></div>';
	}

	return '<div class="' . esc_attr( brik_cls( 'brik-device', 'brik-device--' . $device, 'brik-device--' . $theme, $args['class'] ) ) . '">' . $screen . '</div>';
}
