<?php
/**
 * Helpers shared by the media and interactive modules.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Unsplash sample photo used for module defaults.
 *
 * @param string $photo Unsplash photo id, e.g. "1506905925346-21bda4d32df4".
 */
function brik_sample_image( $photo, $width = 1200, $height = 0 ) {
	$url = 'https://images.unsplash.com/photo-' . rawurlencode( $photo ) . '?auto=format&fit=crop&q=80&w=' . (int) $width;
	if ( $height ) {
		$url .= '&h=' . (int) $height;
	}
	return $url;
}

/**
 * Aspect ratio choices for select fields.
 */
function brik_aspect_options( $with_auto = true ) {
	$opts = array(
		'16:9' => '16:9',
		'4:3'  => '4:3',
		'3:2'  => '3:2',
		'1:1'  => '1:1',
		'21:9' => '21:9',
		'2:3'  => '2:3',
		'3:4'  => '3:4',
		'4:5'  => '4:5',
		'9:16' => '9:16',
	);
	return $with_auto ? array( 'auto' => __( 'Original', 'brik-builder' ) ) + $opts : $opts;
}

/**
 * Tailwind aspect-ratio class for a ratio value ("16:9"), empty for auto.
 */
function brik_aspect_class( $ratio ) {
	$map = array(
		'16:9' => 'aspect-video',
		'4:3'  => 'aspect-[4/3]',
		'3:2'  => 'aspect-[3/2]',
		'1:1'  => 'aspect-square',
		'21:9' => 'aspect-[21/9]',
		'2:3'  => 'aspect-[2/3]',
		'3:4'  => 'aspect-[3/4]',
		'4:5'  => 'aspect-[4/5]',
		'9:16' => 'aspect-[9/16]',
	);
	return isset( $map[ $ratio ] ) ? $map[ $ratio ] : '';
}

function brik_radius_options() {
	return array(
		'none' => __( 'None', 'brik-builder' ),
		'sm'   => __( 'Small', 'brik-builder' ),
		'md'   => __( 'Medium', 'brik-builder' ),
		'lg'   => __( 'Large', 'brik-builder' ),
		'xl'   => __( 'Extra large', 'brik-builder' ),
		'2xl'  => '2XL',
		'full' => __( 'Circle / pill', 'brik-builder' ),
	);
}

function brik_radius_class( $radius ) {
	$map = array(
		'none' => 'rounded-none',
		'sm'   => 'rounded-sm',
		'md'   => 'rounded-md',
		'lg'   => 'rounded-lg',
		'xl'   => 'rounded-xl',
		'2xl'  => 'rounded-2xl',
		'full' => 'rounded-full',
	);
	return isset( $map[ $radius ] ) ? $map[ $radius ] : '';
}

function brik_shadow_options() {
	return array(
		'none' => __( 'None', 'brik-builder' ),
		'sm'   => __( 'Small', 'brik-builder' ),
		'md'   => __( 'Medium', 'brik-builder' ),
		'lg'   => __( 'Large', 'brik-builder' ),
		'xl'   => __( 'Extra large', 'brik-builder' ),
		'2xl'  => '2XL',
	);
}

function brik_shadow_class( $shadow ) {
	$map = array(
		'sm'  => 'shadow-sm',
		'md'  => 'shadow-md',
		'lg'  => 'shadow-lg',
		'xl'  => 'shadow-xl',
		'2xl' => 'shadow-2xl',
	);
	return isset( $map[ $shadow ] ) ? $map[ $shadow ] : '';
}

function brik_object_fit_class( $fit ) {
	$map = array(
		'cover'   => 'object-cover',
		'contain' => 'object-contain',
		'fill'    => 'object-fill',
		'none'    => 'object-none',
	);
	return isset( $map[ $fit ] ) ? $map[ $fit ] : 'object-cover';
}

/**
 * Registered WordPress image sizes for a select field.
 */
function brik_image_size_options() {
	$out = array();
	if ( function_exists( 'wp_get_registered_image_subsizes' ) ) {
		foreach ( wp_get_registered_image_subsizes() as $name => $size ) {
			$out[ $name ] = sprintf( '%s (%d×%d)', ucwords( str_replace( array( '_', '-' ), ' ', $name ) ), $size['width'], $size['height'] );
		}
	} else {
		$out = array(
			'thumbnail' => __( 'Thumbnail', 'brik-builder' ),
			'medium'    => __( 'Medium', 'brik-builder' ),
			'large'     => __( 'Large', 'brik-builder' ),
		);
	}
	$out['full'] = __( 'Full size', 'brik-builder' );
	return $out;
}

/**
 * Normalise an image or gallery field value into a list of display-ready items.
 *
 * Accepts attachment ids, URL strings, comma separated ids and arrays with
 * id/url/alt/caption/width/height keys.
 *
 * @return array[] Each item: src, full, alt, caption, width, height, id.
 */
function brik_media_items( $value, $size = 'large' ) {
	if ( is_string( $value ) && preg_match( '/^[\d,\s]+$/', $value ) ) {
		$value = array_filter( array_map( 'absint', explode( ',', $value ) ) );
	}
	if ( ! is_array( $value ) ) {
		$value = '' === (string) $value ? array() : array( $value );
	}
	if ( isset( $value['url'] ) || isset( $value['id'] ) ) {
		$value = array( $value );
	}

	$items = array();
	foreach ( $value as $raw ) {
		$item = brik_media_item( $raw, $size );
		if ( $item ) {
			$items[] = $item;
		}
	}
	return $items;
}

function brik_media_item( $raw, $size = 'large' ) {
	if ( is_numeric( $raw ) ) {
		$raw = array( 'id' => (int) $raw );
	} elseif ( is_string( $raw ) ) {
		$raw = array( 'url' => $raw );
	}
	if ( ! is_array( $raw ) ) {
		return null;
	}

	$item = array(
		'id'      => isset( $raw['id'] ) ? (int) $raw['id'] : 0,
		'src'     => isset( $raw['url'] ) ? (string) $raw['url'] : '',
		'full'    => isset( $raw['url'] ) ? (string) $raw['url'] : '',
		'alt'     => isset( $raw['alt'] ) ? (string) $raw['alt'] : '',
		'caption' => isset( $raw['caption'] ) ? (string) $raw['caption'] : '',
		'width'   => isset( $raw['width'] ) ? (int) $raw['width'] : 0,
		'height'  => isset( $raw['height'] ) ? (int) $raw['height'] : 0,
	);

	if ( $item['id'] && wp_attachment_is_image( $item['id'] ) ) {
		$src  = wp_get_attachment_image_src( $item['id'], $size );
		$full = wp_get_attachment_image_src( $item['id'], 'full' );
		if ( $src ) {
			$item['src']    = $src[0];
			$item['width']  = (int) $src[1];
			$item['height'] = (int) $src[2];
		}
		if ( $full ) {
			$item['full'] = $full[0];
		}
		if ( '' === $item['alt'] ) {
			$item['alt'] = (string) get_post_meta( $item['id'], '_wp_attachment_image_alt', true );
		}
		if ( '' === $item['caption'] ) {
			$item['caption'] = (string) wp_get_attachment_caption( $item['id'] );
		}
	} elseif ( $item['src'] && ( ! $item['width'] || ! $item['height'] ) && preg_match( '/[?&]w=(\d+).*?[?&]h=(\d+)/', $item['src'], $m ) ) {
		// Sample Unsplash URLs carry their crop size, handy for justified rows.
		$item['width']  = (int) $m[1];
		$item['height'] = (int) $m[2];
	}

	return '' === $item['src'] ? null : $item;
}

/**
 * <img> tag for a normalised media item.
 */
function brik_media_img( array $item, $size = 'large', array $attrs = array() ) {
	if ( $item['id'] && wp_attachment_is_image( $item['id'] ) ) {
		if ( ! isset( $attrs['alt'] ) ) {
			$attrs['alt'] = $item['alt'];
		}
		return wp_get_attachment_image( $item['id'], $size, false, array_merge( array( 'loading' => 'lazy', 'decoding' => 'async' ), $attrs ) );
	}
	$attrs = array_merge(
		array(
			'src'      => $item['src'],
			'alt'      => $item['alt'],
			'loading'  => 'lazy',
			'decoding' => 'async',
			'width'    => $item['width'] ? $item['width'] : null,
			'height'   => $item['height'] ? $item['height'] : null,
		),
		$attrs
	);
	return '<img' . brik_attrs( $attrs ) . '>';
}

/**
 * Parse a video URL into provider details.
 *
 * @return array provider (youtube|vimeo|file), id, url.
 */
function brik_video_info( $url ) {
	$url = trim( (string) ( is_array( $url ) ? ( isset( $url['url'] ) ? $url['url'] : '' ) : $url ) );
	if ( preg_match( '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([\w-]{11})~i', $url, $m ) ) {
		return array( 'provider' => 'youtube', 'id' => $m[1], 'url' => $url );
	}
	if ( preg_match( '~vimeo\.com/(?:video/|channels/[\w-]+/|groups/[\w-]+/videos/)?(\d+)(?:/([\da-f]+))?~i', $url, $m ) ) {
		return array( 'provider' => 'vimeo', 'id' => $m[1], 'hash' => isset( $m[2] ) ? $m[2] : '', 'url' => $url );
	}
	return array( 'provider' => 'file', 'id' => '', 'url' => $url );
}

/**
 * Embed URL for a YouTube or Vimeo video.
 */
function brik_video_embed_url( array $info, array $opts = array() ) {
	$opts = array_merge(
		array(
			'autoplay' => false,
			'muted'    => false,
			'loop'     => false,
			'controls' => true,
			'start'    => 0,
		),
		$opts
	);
	if ( 'youtube' === $info['provider'] ) {
		$args = array(
			'rel'            => 0,
			'modestbranding' => 1,
			'playsinline'    => 1,
		);
		if ( $opts['autoplay'] ) {
			$args['autoplay'] = 1;
		}
		if ( $opts['muted'] ) {
			$args['mute'] = 1;
		}
		if ( $opts['loop'] ) {
			$args['loop']     = 1;
			$args['playlist'] = $info['id'];
		}
		if ( ! $opts['controls'] ) {
			$args['controls'] = 0;
		}
		if ( $opts['start'] ) {
			$args['start'] = (int) $opts['start'];
		}
		return add_query_arg( $args, 'https://www.youtube-nocookie.com/embed/' . $info['id'] );
	}
	if ( 'vimeo' === $info['provider'] ) {
		$args = array( 'dnt' => 1 );
		if ( ! empty( $info['hash'] ) ) {
			$args['h'] = $info['hash'];
		}
		if ( $opts['autoplay'] ) {
			$args['autoplay'] = 1;
		}
		if ( $opts['muted'] ) {
			$args['muted'] = 1;
		}
		if ( $opts['loop'] ) {
			$args['loop'] = 1;
		}
		if ( ! $opts['controls'] ) {
			$args['controls'] = 0;
		}
		$url = add_query_arg( $args, 'https://player.vimeo.com/video/' . $info['id'] );
		return $opts['start'] ? $url . '#t=' . (int) $opts['start'] . 's' : $url;
	}
	return '';
}

/**
 * Thumbnail for a YouTube or Vimeo video. Vimeo needs an oEmbed lookup, cached for a week.
 */
function brik_video_thumb( array $info ) {
	if ( 'youtube' === $info['provider'] ) {
		return 'https://i.ytimg.com/vi/' . $info['id'] . '/maxresdefault.jpg';
	}
	if ( 'vimeo' === $info['provider'] ) {
		$data = brik_oembed_data( 'https://vimeo.com/' . $info['id'] . ( ! empty( $info['hash'] ) ? '/' . $info['hash'] : '' ) );
		if ( $data && ! empty( $data->thumbnail_url ) ) {
			// Ask for a larger rendition than the 640px default.
			return preg_replace( '/_\d+x\d+(\.\w+)?$/', '_1280x720$1', $data->thumbnail_url );
		}
	}
	return '';
}

/**
 * oEmbed data object for a URL, cached in a transient (failures are cached briefly).
 */
function brik_oembed_data( $url ) {
	$key    = 'brik_oe_' . md5( $url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return is_object( $cached ) ? $cached : null;
	}
	$data = _wp_oembed_get_object()->get_data( $url, array( 'width' => 1280 ) );
	set_transient( $key, $data ? $data : 'none', $data ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
	return $data ? $data : null;
}

/**
 * Embed HTML for a URL via oEmbed, cached like brik_oembed_data().
 */
function brik_oembed_html( $url ) {
	$key    = 'brik_oeh_' . md5( $url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return 'none' === $cached ? '' : $cached;
	}
	$html = wp_oembed_get( $url, array( 'width' => 1280 ) );
	set_transient( $key, $html ? $html : 'none', $html ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
	return $html ? $html : '';
}

/**
 * Keyless Google Maps embed URL for an address or "lat,lng".
 */
function brik_map_embed_url( $query, $zoom = 14, $type = 'roadmap' ) {
	return add_query_arg(
		array(
			'q'      => rawurlencode( trim( (string) $query ) ),
			't'      => 'satellite' === $type ? 'k' : ( 'hybrid' === $type ? 'h' : ( 'terrain' === $type ? 'p' : 'm' ) ),
			'z'      => max( 1, min( 21, (int) $zoom ) ),
			'ie'     => 'UTF8',
			'iwloc'  => '',
			'output' => 'embed',
		),
		'https://maps.google.com/maps'
	);
}

/**
 * Only allow http(s) iframe sources.
 */
function brik_safe_iframe_src( $url ) {
	$url = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
	return $url ? $url : '';
}

/**
 * Open/closed indicator for disclosure summaries (accordion, toggle).
 */
function brik_disclosure_indicator( $type ) {
	if ( 'plus' === $type ) {
		// The vertical bar folds away when open, turning the plus into a minus.
		return '<svg class="brik-acc-indicator size-4 shrink-0 text-muted-foreground" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/><path class="origin-center transition-transform duration-200 [transform-box:fill-box] group-open:rotate-90 group-open:opacity-0" d="M12 5v14"/></svg>';
	}
	if ( 'chevron' === $type ) {
		return brik_icon( 'chevron-down', 'brik-acc-indicator pointer-events-none size-4 shrink-0 translate-y-0.5 text-muted-foreground transition-transform duration-200 group-open:rotate-180' );
	}
	return '';
}

/**
 * Markup for a list of <details> items in one of the accordion styles.
 *
 * @param array $items Each: title, content, icon, open (optional).
 * @param array $opts  name (exclusive group), first, style, indicator, position.
 */
function brik_disclosure_list( array $items, array $opts ) {
	$opts = array_merge(
		array(
			'name'      => '',
			'first'     => false,
			'style'     => 'default',
			'indicator' => 'chevron',
			'position'  => 'end',
		),
		$opts
	);

	$wrap = array(
		'default'   => 'brik-accordion brik-accordion--default flex flex-col',
		'bordered'  => 'brik-accordion brik-accordion--bordered flex flex-col overflow-hidden rounded-lg border bg-card text-card-foreground',
		'separated' => 'brik-accordion brik-accordion--separated flex flex-col gap-3',
	);
	$item_cls = array(
		'default'   => 'brik-acc-item group border-b last:border-b-0',
		'bordered'  => 'brik-acc-item group border-b px-4 last:border-b-0',
		'separated' => 'brik-acc-item group rounded-lg border bg-card px-4 text-card-foreground shadow-xs transition-shadow open:shadow-sm',
	);
	$style = isset( $wrap[ $opts['style'] ] ) ? $opts['style'] : 'default';
	$ind   = brik_disclosure_indicator( $opts['indicator'] );
	$start = 'start' === $opts['position'];

	$out = '';
	foreach ( $items as $i => $item ) {
		$item  = array_merge( array( 'title' => '', 'content' => '', 'icon' => '', 'open' => null, 'inline' => array( '', '' ) ), $item );
		$open  = null !== $item['open'] ? (bool) $item['open'] : ( 0 === $i && $opts['first'] );
		$title = '<span class="brik-acc-title flex flex-1 items-center gap-3">' . ( $item['icon'] ? brik_icon( $item['icon'], 'brik-acc-icon size-4 shrink-0 text-muted-foreground' ) : '' ) . '<span' . $item['inline'][0] . '>' . brik_inline( $item['title'] ) . '</span></span>';

		$out .= sprintf(
			'<details class="%1$s"%2$s%3$s><summary class="brik-acc-trigger flex cursor-pointer list-none items-start justify-between gap-4 rounded-md py-4 text-left text-sm font-medium transition-all outline-none select-none hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">%4$s</summary><div class="brik-acc-content"><div class="brik-acc-body brik-prose pb-4 text-sm text-muted-foreground%5$s"%7$s>%6$s</div></div></details>',
			esc_attr( $item_cls[ $style ] ),
			$opts['name'] ? ' name="' . esc_attr( $opts['name'] ) . '"' : '',
			$open ? ' open' : '',
			$start ? $ind . $title : $title . $ind,
			$start && $ind ? ' pl-8' : '',
			brik_rich( $item['content'] ),
			$item['inline'][1]
		);
	}
	return '<div class="' . esc_attr( $wrap[ $style ] ) . '">' . $out . '</div>';
}

/**
 * FAQPage JSON-LD for accordion items.
 */
function brik_faq_schema( array $items ) {
	$entities = array();
	foreach ( $items as $item ) {
		if ( empty( $item['title'] ) || empty( $item['content'] ) ) {
			continue;
		}
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $item['title'] ),
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_kses_post( $item['content'] ),
			),
		);
	}
	if ( ! $entities ) {
		return '';
	}
	$data = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $entities,
	);
	return '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>';
}
