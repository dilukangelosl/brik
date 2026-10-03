<?php
/**
 * Carousel: full-width hero slides or a row of cards, on CSS scroll snap.
 *
 * @package Brik
 */

use Brik\Fields;
use Brik\Style;

defined( 'ABSPATH' ) || exit;

$brik_slide_defaults = array(
	array(
		'image'       => array( 'url' => brik_sample_image( '1501785888041-af3ef285b470', 1800, 1000 ), 'alt' => '' ),
		'eyebrow'     => __( 'Guided trips', 'brik-builder' ),
		'title'       => __( 'Lakes, peaks and quiet trails', 'brik-builder' ),
		'text'        => __( 'Small groups, local guides and routes you will not find in a guidebook. Spring dates are open.', 'brik-builder' ),
		'button_text' => __( 'See the trips', 'brik-builder' ),
		'link'        => array( 'url' => '#' ),
	),
	array(
		'image'       => array( 'url' => brik_sample_image( '1519681393784-d120267933ba', 1800, 1000 ), 'alt' => '' ),
		'eyebrow'     => __( 'New collection', 'brik-builder' ),
		'title'       => __( 'Gear up for the long nights', 'brik-builder' ),
		'text'        => __( 'Warm layers, headlamps and tents tested above the tree line. Free shipping on orders over $80.', 'brik-builder' ),
		'button_text' => __( 'Shop the collection', 'brik-builder' ),
		'link'        => array( 'url' => '#' ),
	),
	array(
		'image'       => array( 'url' => brik_sample_image( '1472214103451-9374bd1c798e', 1800, 1000 ), 'alt' => '' ),
		'eyebrow'     => __( 'Journal', 'brik-builder' ),
		'title'       => __( 'Stories from the valley', 'brik-builder' ),
		'text'        => __( 'Field notes, photo essays and practical advice from people who spend their weekends outside.', 'brik-builder' ),
		'button_text' => __( 'Read the journal', 'brik-builder' ),
		'link'        => array( 'url' => '#' ),
	),
	array(
		'image'       => array( 'url' => brik_sample_image( '1507525428034-b723cf961d3e', 1800, 1000 ), 'alt' => '' ),
		'eyebrow'     => __( 'Summer', 'brik-builder' ),
		'title'       => __( 'Coastal escapes', 'brik-builder' ),
		'text'        => __( 'Sea kayaking, cliff walks and campsites a few steps from the water.', 'brik-builder' ),
		'button_text' => __( 'Plan a trip', 'brik-builder' ),
		'link'        => array( 'url' => '#' ),
	),
);

return array(
	'type'        => 'carousel',
	'title'       => __( 'Carousel', 'brik-builder' ),
	'category'    => 'interactive',
	'icon'        => 'gallery-horizontal-end',
	'description' => 'Slider. mode: slides (full-width image slides with overlay text) | cards (several cards per view). items: repeater of {image, eyebrow, title, text, button_text, link}. per_view: cards per view (responsive, cards mode). gap: CSS length. slide_height: CSS length (slides mode, responsive). content_align: left|center. arrows: bool. arrow_position: auto|inside|outside|bottom. dots: bool. autoplay: bool, interval: seconds. loop: bool. pause_on_hover: bool. card_ratio: image ratio in cards mode. Swipe and keyboard arrows work; autoplay never runs in the builder or with reduced motion.',
	'fields'      => array_merge(
		array(
			'mode'           => Fields::field( 'select', __( 'Type', 'brik-builder' ), 'content', array( 'default' => 'slides', 'options' => Fields::opts( array( 'slides' => __( 'Slides', 'brik-builder' ), 'cards' => __( 'Cards', 'brik-builder' ) ) ) ) ),
			'items'          => Fields::field(
				'repeater',
				__( 'Slides', 'brik-builder' ),
				'content',
				array(
					'title_field' => 'title',
					'default'     => $brik_slide_defaults,
					'fields'      => array(
						'image'       => Fields::field( 'image', __( 'Image', 'brik-builder' ), 'content' ),
						'eyebrow'     => Fields::field( 'text', __( 'Eyebrow', 'brik-builder' ), 'content' ),
						'title'       => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content' ),
						'text'        => Fields::field( 'textarea', __( 'Text', 'brik-builder' ), 'content' ),
						'button_text' => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'content' ),
						'link'        => Fields::field( 'link', __( 'Link', 'brik-builder' ), 'content' ),
					),
				)
			),
			'per_view'       => Fields::field( 'number', __( 'Cards per view', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 6, 'step' => 0.1, 'responsive' => true, 'show_if' => array( 'mode' => 'cards' ) ) ),
			'gap'            => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-carousel', '--brik-gap' ) ) ),
			'slide_height'   => Fields::field( 'unit', __( 'Slide height', 'brik-builder' ), 'content', array( 'default' => '520px', 'responsive' => true, 'show_if' => array( 'mode' => 'slides' ), 'css' => array( Fields::WRAP . ' .brik-slide', 'min-height' ) ) ),
			'content_align'  => Fields::field( 'select', __( 'Text alignment', 'brik-builder' ), 'content', array( 'default' => 'left', 'options' => Fields::opts( array( 'left' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ) ) ), 'show_if' => array( 'mode' => 'slides' ) ) ),
			'card_ratio'     => Fields::field( 'select', __( 'Card image ratio', 'brik-builder' ), 'content', array( 'default' => '4:3', 'options' => Fields::opts( brik_aspect_options( false ) ), 'show_if' => array( 'mode' => 'cards' ) ) ),
			'arrows'         => Fields::field( 'toggle', __( 'Arrows', 'brik-builder' ), 'navigation', array( 'default' => true, 'group_label' => __( 'Navigation', 'brik-builder' ) ) ),
			'arrow_position' => Fields::field( 'select', __( 'Arrow position', 'brik-builder' ), 'navigation', array( 'default' => 'auto', 'group_label' => __( 'Navigation', 'brik-builder' ), 'options' => Fields::opts( array( 'auto' => __( 'Automatic', 'brik-builder' ), 'inside' => __( 'Inside, on the sides', 'brik-builder' ), 'outside' => __( 'Outside, on the sides', 'brik-builder' ), 'bottom' => __( 'Below, with the dots', 'brik-builder' ) ) ), 'show_if' => array( 'arrows' => '!' ) ) ),
			'dots'           => Fields::field( 'toggle', __( 'Dots', 'brik-builder' ), 'navigation', array( 'default' => true, 'group_label' => __( 'Navigation', 'brik-builder' ) ) ),
			'autoplay'       => Fields::field( 'toggle', __( 'Autoplay', 'brik-builder' ), 'navigation', array( 'group_label' => __( 'Navigation', 'brik-builder' ) ) ),
			'interval'       => Fields::field( 'range', __( 'Interval (seconds)', 'brik-builder' ), 'navigation', array( 'default' => 6, 'min' => 2, 'max' => 20, 'step' => 0.5, 'group_label' => __( 'Navigation', 'brik-builder' ), 'show_if' => array( 'autoplay' => '!' ) ) ),
			'pause_on_hover' => Fields::field( 'toggle', __( 'Pause on hover', 'brik-builder' ), 'navigation', array( 'default' => true, 'group_label' => __( 'Navigation', 'brik-builder' ), 'show_if' => array( 'autoplay' => '!' ) ) ),
			'loop'           => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'navigation', array( 'default' => true, 'group_label' => __( 'Navigation', 'brik-builder' ) ) ),
			'label'          => Fields::field( 'text', __( 'Accessible name', 'brik-builder' ), 'navigation', array( 'group_label' => __( 'Navigation', 'brik-builder' ), 'default' => __( 'Featured', 'brik-builder' ) ) ),
			'overlay'        => Fields::field( 'color', __( 'Image overlay', 'brik-builder' ), 'slide_style', array( 'tab' => 'design', 'group_label' => __( 'Slides', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-slide-overlay', 'background' ) ) ),
			'rounded'        => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'slide_style', array( 'tab' => 'design', 'group_label' => __( 'Slides', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'dot_color'      => Fields::field( 'color', __( 'Active dot color', 'brik-builder' ), 'dots_style', array( 'tab' => 'design', 'group_label' => __( 'Dots', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-carousel-dot[aria-current="true"]', 'background-color' ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-carousel-card' ),
		Fields::box( 'arrow', __( 'Arrows', 'brik-builder' ), Fields::WRAP . ' .brik-carousel-arrow', array( 'bg', 'color', 'border_color', 'radius', 'shadow' ) ),
		Fields::typography( 'eyebrow', __( 'Eyebrow', 'brik-builder' ), Fields::WRAP . ' .brik-slide-eyebrow' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-slide-title' ),
		Fields::typography( 'text', __( 'Text', 'brik-builder' ), Fields::WRAP . ' .brik-slide-text' ),
		Fields::box( 'button', __( 'Button', 'brik-builder' ), Fields::WRAP . ' .brik-slide-button', array( 'bg', 'color', 'border_color', 'radius', 'padding' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$items = is_array( $a['items'] ) ? array_values( array_filter( $a['items'], 'is_array' ) ) : array();
		if ( ! $items ) {
			return $ctx->placeholder( __( 'Add slides', 'brik-builder' ) );
		}
		$cards  = 'cards' === $a['mode'];
		$count  = count( $items );
		$radius = brik_radius_class( $a['rounded'] );

		if ( $cards ) {
			// Cards per view step down on smaller screens unless set explicitly.
			$pv  = max( 1, min( 6, (float) $a['per_view'] ? (float) $a['per_view'] : 3 ) );
			$tab = Style::raw_value( $a, 'per_view', 'tablet' );
			$tab = null !== $tab ? max( 1, (float) $tab ) : min( $pv, 2 );
			$mob = Style::raw_value( $a, 'per_view', 'mobile' );
			$mob = null !== $mob ? max( 1, (float) $mob ) : min( $tab, 1.15 );
			$ctx->css( Fields::WRAP . ' .brik-carousel', '--brik-per-view:' . $pv );
			$ctx->css( Fields::WRAP . ' .brik-carousel', '--brik-per-view:' . $tab, 'tablet' );
			$ctx->css( Fields::WRAP . ' .brik-carousel', '--brik-per-view:' . $mob, 'mobile' );
		}

		$slides = '';
		foreach ( $items as $i => $item ) {
			$item = array_merge( array( 'image' => '', 'eyebrow' => '', 'title' => '', 'text' => '', 'button_text' => '', 'link' => '' ), $item );
			$url  = ! empty( $item['link']['url'] ) ? $item['link'] : '';
			/* translators: 1: slide number, 2: total slides */
			$label = sprintf( __( '%1$d of %2$d', 'brik-builder' ), $i + 1, $count );

			if ( $cards ) {
				$img   = brik_image( $item['image'], 'medium_large', array( 'class' => 'h-full w-full object-cover transition-transform duration-500 group-hover:scale-105', 'alt' => '', 'loading' => $i < 4 ? 'eager' : 'lazy' ) );
				$body  = '';
				$body .= '' !== $item['eyebrow'] ? '<p class="brik-slide-eyebrow text-xs font-semibold tracking-wider text-primary uppercase">' . brik_inline( $item['eyebrow'] ) . '</p>' : '';
				$body .= '' !== $item['title'] ? '<h3 class="brik-slide-title text-lg leading-snug font-semibold tracking-tight">' . ( $url ? '<a' . brik_link_attrs( $url, array( 'class' => 'outline-none after:absolute after:inset-0 after:content-[\'\'] focus-visible:underline' ) ) . '>' . brik_inline( $item['title'] ) . '</a>' : brik_inline( $item['title'] ) ) . '</h3>' : '';
				$body .= '' !== $item['text'] ? '<p class="brik-slide-text text-sm text-muted-foreground">' . esc_html( $item['text'] ) . '</p>' : '';
				$body .= '' !== $item['button_text'] && $url ? '<span class="brik-slide-button mt-auto inline-flex items-center gap-1 pt-2 text-sm font-medium text-foreground">' . brik_inline( $item['button_text'] ) . brik_icon( 'arrow-right', 'size-4 transition-transform group-hover:translate-x-0.5' ) . '</span>' : '';
				$inner = sprintf(
					'<article class="brik-carousel-card group relative flex h-full flex-col overflow-hidden border bg-card text-card-foreground shadow-xs transition-shadow hover:shadow-md %1$s">%2$s<div class="flex flex-1 flex-col gap-2 p-5">%3$s</div></article>',
					esc_attr( $radius ),
					$img ? '<div class="overflow-hidden bg-muted ' . esc_attr( brik_aspect_class( $a['card_ratio'] ? $a['card_ratio'] : '4:3' ) ) . '">' . $img . '</div>' : '',
					$body
				);
			} else {
				$img   = brik_image( $item['image'], 'full', array( 'class' => 'brik-slide-img absolute inset-0 h-full w-full object-cover', 'alt' => '', 'loading' => 0 === $i ? 'eager' : 'lazy', 'fetchpriority' => 0 === $i ? 'high' : null ) );
				$center = 'center' === $a['content_align'];
				$body  = '';
				$body .= '' !== $item['eyebrow'] ? '<p class="brik-slide-eyebrow mb-3 text-sm font-semibold tracking-wider text-white/80 uppercase">' . brik_inline( $item['eyebrow'] ) . '</p>' : '';
				$body .= '' !== $item['title'] ? '<h2 class="brik-slide-title font-heading text-3xl leading-tight font-bold tracking-tight text-balance md:text-5xl">' . brik_inline( $item['title'] ) . '</h2>' : '';
				$body .= '' !== $item['text'] ? '<p class="brik-slide-text mt-4 text-base text-white/85 md:text-lg">' . esc_html( $item['text'] ) . '</p>' : '';
				$body .= '' !== $item['button_text'] && $url ? '<div class="mt-8"><a' . brik_link_attrs( $url, array( 'class' => 'brik-slide-button inline-flex h-10 items-center justify-center gap-2 rounded-md bg-white px-6 text-sm font-medium whitespace-nowrap text-neutral-900 shadow-xs transition-colors outline-none hover:bg-white/90 focus-visible:ring-[3px] focus-visible:ring-white/50' ) ) . '>' . brik_inline( $item['button_text'] ) . '</a></div>' : '';
				$inner = sprintf(
					'<div class="brik-slide relative isolate flex min-h-[520px] h-full items-end overflow-hidden bg-neutral-900 text-white md:items-center %1$s">%2$s<div class="brik-slide-overlay absolute inset-0 bg-linear-to-t from-black/75 via-black/35 to-black/10 md:bg-linear-to-r md:from-black/70 md:via-black/35 md:to-transparent"></div><div class="brik-slide-content relative z-10 w-full max-w-2xl px-6 pt-16 pb-16 md:px-16 %3$s">%4$s</div></div>',
					esc_attr( $radius ),
					$img,
					$center ? 'mx-auto text-center' : '',
					$body
				);
			}

			$slides .= '<li class="brik-carousel-slide" role="group" aria-roledescription="' . esc_attr__( 'slide', 'brik-builder' ) . '" aria-label="' . esc_attr( $label ) . '">' . $inner . '</li>';
		}

		$pos = $a['arrow_position'];
		if ( 'auto' === $pos || ! in_array( $pos, array( 'inside', 'outside', 'bottom' ), true ) ) {
			$pos = $cards ? 'bottom' : 'inside';
		}
		$nav    = '';
		$arrows = '';
		if ( ! empty( $a['arrows'] ) && $count > 1 ) {
			$btn = 'bottom' === $pos
				? 'brik-carousel-arrow inline-flex size-9 items-center justify-center rounded-full border bg-background text-foreground shadow-xs transition-all outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-40'
				: 'brik-carousel-arrow absolute top-1/2 z-10 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full border bg-background/90 text-foreground shadow-md backdrop-blur transition-all outline-none hover:bg-background focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-0';
			$side   = array(
				'inside'  => array( 'left-4 max-md:hidden', 'right-4 max-md:hidden' ),
				'outside' => array( 'left-0 md:-left-14', 'right-0 md:-right-14' ),
				'bottom'  => array( '', '' ),
			);
			$arrows = sprintf(
				'<button type="button" class="%1$s" data-dir="prev" aria-label="%2$s">%3$s</button><button type="button" class="%4$s" data-dir="next" aria-label="%5$s">%6$s</button>',
				esc_attr( brik_cls( $btn, $side[ $pos ][0], 'brik-carousel-prev' ) ),
				esc_attr__( 'Previous slide', 'brik-builder' ),
				brik_icon( 'chevron-left', 'size-4' ),
				esc_attr( brik_cls( $btn, $side[ $pos ][1], 'brik-carousel-next' ) ),
				esc_attr__( 'Next slide', 'brik-builder' ),
				brik_icon( 'chevron-right', 'size-4' )
			);
		}
		$dots = ! empty( $a['dots'] ) && $count > 1 ? '<div class="brik-carousel-dots flex items-center gap-1.5" role="group" aria-label="' . esc_attr__( 'Choose slide', 'brik-builder' ) . '"></div>' : '';
		$play = ! empty( $a['autoplay'] ) && $count > 1 ? '<button type="button" class="brik-carousel-play inline-flex size-8 items-center justify-center rounded-full text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50" aria-label="' . esc_attr__( 'Pause autoplay', 'brik-builder' ) . '" data-pause-label="' . esc_attr__( 'Pause autoplay', 'brik-builder' ) . '" data-play-label="' . esc_attr__( 'Start autoplay', 'brik-builder' ) . '">' . brik_icon( 'pause', 'brik-carousel-pause-icon size-4' ) . brik_icon( 'play', 'brik-carousel-play-icon hidden size-4' ) . '</button>' : '';

		if ( $dots || $play || 'bottom' === $pos ) {
			$nav = '<div class="brik-carousel-nav mt-5 flex items-center gap-3 ' . ( 'bottom' === $pos && $arrows ? 'justify-between' : 'justify-center' ) . '"><div class="flex items-center gap-2">' . $dots . $play . '</div>' . ( 'bottom' === $pos ? '<div class="flex items-center gap-2">' . $arrows . '</div>' : '' ) . '</div>';
		}

		return sprintf(
			'<div class="%1$s" data-brik-carousel role="region" aria-roledescription="%2$s" aria-label="%3$s"%4$s%5$s%6$s><div class="relative">%7$s<ul class="brik-carousel-track" tabindex="0">%8$s</ul></div>%9$s</div>',
			esc_attr( brik_cls( 'brik-carousel', $cards ? 'brik-carousel--cards' : 'brik-carousel--slides', 'outside' === $pos ? 'md:px-14' : '' ) ),
			esc_attr__( 'carousel', 'brik-builder' ),
			esc_attr( '' !== $a['label'] ? wp_strip_all_tags( $a['label'] ) : __( 'Carousel', 'brik-builder' ) ),
			! empty( $a['autoplay'] ) ? ' data-autoplay="' . esc_attr( (int) ( (float) ( $a['interval'] ? $a['interval'] : 6 ) * 1000 ) ) . '"' : '',
			! empty( $a['loop'] ) ? ' data-loop' : '',
			! empty( $a['pause_on_hover'] ) ? ' data-pause-hover' : '',
			'bottom' === $pos ? '' : $arrows,
			$slides,
			$nav
		);
	},
);
