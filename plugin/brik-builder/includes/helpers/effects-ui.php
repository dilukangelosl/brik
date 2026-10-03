<?php
/**
 * Helpers shared by the animated text and interaction modules (category "effects").
 *
 * @package Brik
 * @author  Diluk Angelo
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'brik/module_categories',
	static function ( $categories ) {
		return $categories + array( 'effects' => __( 'Effects', 'brik-builder' ) );
	}
);

/**
 * A CSS value from a field, safe to drop into a style attribute. Empty when unusable.
 */
function brik_fx_css_value( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$value = trim( (string) $value );
	if ( '' === $value || preg_match( '/[;{}<>\\\\]|url\s*\(|expression|javascript:/i', $value ) ) {
		return '';
	}
	return $value;
}

/**
 * Build a style attribute value from custom properties, skipping empty values.
 *
 * @param array $vars '--name' => value.
 */
function brik_fx_vars( array $vars ) {
	$out = array();
	foreach ( $vars as $name => $value ) {
		$value = brik_fx_css_value( $value );
		if ( '' !== $value ) {
			$out[] = $name . ':' . $value;
		}
	}
	return implode( ';', $out );
}

/**
 * Number from an attribute, clamped. Empty values fall back to $default.
 */
function brik_fx_num( $value, $default, $min, $max ) {
	if ( ! is_numeric( $value ) ) {
		return $default;
	}
	return max( $min, min( $max, (float) $value ) );
}

/**
 * Whitelisted tag name.
 */
function brik_fx_tag( $tag, array $allowed, $default ) {
	return in_array( $tag, $allowed, true ) ? $tag : $default;
}

/**
 * Size presets shared with the heading module.
 */
function brik_fx_size_classes() {
	return array(
		'display' => 'text-5xl md:text-6xl lg:text-7xl font-bold tracking-tight',
		'h1'      => 'text-4xl md:text-5xl font-extrabold tracking-tight',
		'h2'      => 'text-3xl md:text-4xl font-semibold tracking-tight',
		'h3'      => 'text-2xl font-semibold tracking-tight',
		'lead'    => 'text-xl text-muted-foreground',
	);
}

function brik_fx_size_options() {
	return array(
		'display' => __( 'Display', 'brik-builder' ),
		'h1'      => 'H1',
		'h2'      => 'H2',
		'h3'      => 'H3',
		'lead'    => __( 'Lead', 'brik-builder' ),
	);
}

/**
 * Icon or image for cards and lists. Returns '' when the item has neither.
 *
 * @param string $icon  Lucide or brand icon name.
 * @param mixed  $image Image field value.
 * @param string $class Classes for the icon wrapper.
 */
function brik_fx_media( $icon, $image, $class = '' ) {
	if ( brik_has_image( $image ) ) {
		return '<span class="' . esc_attr( brik_cls( 'brik-fx-media inline-flex shrink-0 items-center justify-center overflow-hidden', $class ) ) . '">' . brik_image( $image, 'thumbnail', array( 'class' => 'size-full object-contain' ) ) . '</span>';
	}
	if ( '' !== (string) $icon ) {
		$svg = brik_icon( $icon, 'size-[var(--brik-fx-icon,1.25rem)]' );
		if ( '' !== $svg ) {
			return '<span class="' . esc_attr( brik_cls( 'brik-fx-media inline-flex shrink-0 items-center justify-center', $class ) ) . '">' . $svg . '</span>';
		}
	}
	return '';
}

/**
 * Brand color for a "brand:slug" icon, or ''.
 *
 * Near-black brand colors (GitHub, X, Apple…) return '' so the icon follows the text
 * color and stays visible in dark sections.
 */
function brik_fx_brand_hex( $icon ) {
	if ( 0 !== strpos( (string) $icon, 'brand:' ) ) {
		return '';
	}
	$brands = Brik\Icons::brands();
	$slug   = substr( $icon, 6 );
	if ( empty( $brands[ $slug ]['hex'] ) || 6 !== strlen( $brands[ $slug ]['hex'] ) ) {
		return '';
	}
	$hex = $brands[ $slug ]['hex'];
	$lum = ( 0.2126 * hexdec( substr( $hex, 0, 2 ) ) + 0.7152 * hexdec( substr( $hex, 2, 2 ) ) + 0.0722 * hexdec( substr( $hex, 4, 2 ) ) ) / 255;
	return $lum < 0.18 ? '' : '#' . $hex;
}

/**
 * Effects available on effect cards.
 */
function brik_fx_card_effects() {
	return array(
		'spotlight'       => __( 'Spotlight (glow follows cursor)', 'brik-builder' ),
		'tilt'            => __( '3D tilt with glare', 'brik-builder' ),
		'border_beam'     => __( 'Border beam', 'brik-builder' ),
		'shine'           => __( 'Shine border', 'brik-builder' ),
		'glow'            => __( 'Gradient glow', 'brik-builder' ),
		'magnetic'        => __( 'Magnetic', 'brik-builder' ),
		'direction_aware' => __( 'Direction-aware overlay', 'brik-builder' ),
		'encrypted'       => __( 'Encrypted reveal', 'brik-builder' ),
	);
}

/**
 * One effect card.
 *
 * @param array        $c      Card data: icon, image, title, text, link, link_text, badge.
 * @param string       $effect Effect key.
 * @param Brik\Context $ctx    Render context.
 * @param bool         $edit   Mark title/text as inline editable (single card mode only).
 * @param string       $tag    Title tag.
 */
function brik_fx_card( array $c, $effect, $ctx, $edit, $tag = 'h3' ) {
	$c = array_merge(
		array(
			'icon'      => '',
			'image'     => '',
			'title'     => '',
			'text'      => '',
			'link'      => '',
			'link_text' => '',
			'badge'     => '',
		),
		$c
	);

	$effect   = array_key_exists( $effect, brik_fx_card_effects() ) ? $effect : 'spotlight';
	$has_link = brik_has_link( $c['link'] );
	$inline   = static function ( $field ) use ( $ctx, $edit ) {
		return $edit ? $ctx->inline( $field ) : '';
	};

	$body = '';
	if ( brik_has_image( $c['image'] ) ) {
		$body .= '<div class="brik-fx-card-image -mx-6 -mt-6 mb-2 aspect-[16/9] overflow-hidden rounded-t-[inherit] bg-muted">' . brik_image( $c['image'], 'medium_large', array( 'class' => 'size-full object-cover transition-transform duration-500 group-hover/fx:scale-105' ) ) . '</div>';
	} elseif ( '' !== (string) $c['icon'] ) {
		$body .= brik_fx_media( $c['icon'], '', 'brik-fx-card-icon size-11 rounded-lg border bg-background text-foreground shadow-xs' );
	}
	if ( '' !== trim( (string) $c['badge'] ) ) {
		$body .= '<span class="' . esc_attr( brik_badge_class( 'secondary', 'default', 'w-fit' ) ) . '"' . $inline( 'badge' ) . '>' . brik_inline( $c['badge'] ) . '</span>';
	}
	if ( '' !== trim( (string) $c['title'] ) ) {
		$body .= '<' . $tag . ' class="brik-fx-card-title font-heading text-lg leading-snug font-semibold tracking-tight"' . $inline( 'title' ) . '>' . brik_inline( $c['title'] ) . '</' . $tag . '>';
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $c['text'] ) ) ) {
		$body .= '<p class="brik-fx-card-text text-sm leading-relaxed text-muted-foreground"' . $inline( 'text' ) . '>' . brik_inline( $c['text'] ) . '</p>';
	}
	$more = '' !== trim( (string) $c['link_text'] ) ? $c['link_text'] : __( 'Learn more', 'brik-builder' );
	if ( $has_link ) {
		// The link stretches over the whole card, except in the builder where it would block inline editing.
		$stretch = $ctx->canvas ? '' : 'after:absolute after:inset-0 after:z-[3] after:content-[\'\']';
		$body   .= '<a' . brik_link_attrs( $c['link'], array( 'class' => brik_cls( 'brik-fx-card-link mt-auto inline-flex w-fit items-center gap-1 pt-2 text-sm font-medium text-foreground no-underline underline-offset-4 hover:underline', $stretch ) ) ) . '>' . brik_inline( $more ) . brik_icon( 'arrow-right', 'size-4 transition-transform group-hover/fx:translate-x-0.5' ) . '</a>';
	}

	// Decorative layers, one set per effect. All aria-hidden.
	$layers = '';
	$outer  = '';
	switch ( $effect ) {
		case 'spotlight':
			$layers = '<span class="brik-fx-spot" aria-hidden="true"></span><span class="brik-fx-spot-ring" aria-hidden="true"></span>';
			break;
		case 'tilt':
			$layers = '<span class="brik-fx-glare" aria-hidden="true"></span>';
			break;
		case 'border_beam':
			$layers = '<span class="brik-fx-beam" aria-hidden="true"><span></span></span>';
			break;
		case 'shine':
			$layers = '<span class="brik-fx-shine" aria-hidden="true"></span>';
			break;
		case 'glow':
			// Lives outside the clipped box so the blur can spill past the edges.
			$outer = '<span class="brik-fx-glow" aria-hidden="true"></span><span class="brik-fx-glow-edge" aria-hidden="true"></span>';
			break;
		case 'direction_aware':
			$overlay = '' !== trim( (string) $c['title'] ) ? '<span class="font-heading text-lg font-semibold tracking-tight">' . esc_html( wp_strip_all_tags( $c['title'] ) ) . '</span>' : '';
			if ( $has_link ) {
				$overlay .= '<span class="inline-flex items-center gap-1 text-sm font-medium">' . esc_html( wp_strip_all_tags( $more ) ) . brik_icon( 'arrow-up-right', 'size-4' ) . '</span>';
			}
			$layers = '<span class="brik-fx-dir" aria-hidden="true"><span class="brik-fx-dir-panel">' . $overlay . '</span></span>';
			break;
		case 'encrypted':
			$layers = '<span class="brik-fx-enc" aria-hidden="true"><span class="brik-fx-enc-chars"></span></span>';
			break;
	}

	$class = 'brik-fx-card brik-fx-card--' . str_replace( '_', '-', $effect ) . ' group/fx relative isolate h-full rounded-xl';
	return '<div class="' . esc_attr( $class ) . '" data-brik-fx-card="' . esc_attr( $effect ) . '">' . $outer
		. '<div class="brik-fx-card-box relative z-[1] flex h-full flex-col gap-3 overflow-hidden rounded-[inherit] border bg-card p-6 text-card-foreground shadow-sm">' . $layers . '<div class="brik-fx-card-body relative z-[2] flex h-full flex-col gap-3">' . $body . '</div></div>'
		. '</div>';
}

/**
 * Default brand set used by the icon cloud and orbiting circles.
 */
function brik_fx_default_brands() {
	return array( 'github', 'figma', 'stripe', 'discord', 'spotify', 'apple', 'wordpress', 'youtube', 'x', 'instagram', 'linkedin', 'gitlab', 'dribbble', 'behance', 'whatsapp', 'telegram', 'reddit', 'twitch', 'paypal', 'visa', 'mastercard', 'producthunt', 'stackoverflow', 'medium', 'mastodon', 'bluesky', 'tiktok', 'threads', 'pinterest', 'vimeo' );
}

/**
 * Repeater rows for a brand list.
 */
function brik_fx_brand_items( array $slugs, $extra = array() ) {
	$brands = Brik\Icons::brands();
	$items  = array();
	foreach ( $slugs as $slug ) {
		if ( isset( $brands[ $slug ] ) ) {
			$items[] = array_merge(
				array(
					'icon'  => 'brand:' . $slug,
					'label' => $brands[ $slug ]['title'],
				),
				$extra
			);
		}
	}
	return $items;
}
