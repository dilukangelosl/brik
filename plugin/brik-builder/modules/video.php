<?php
/**
 * Video: media library file, YouTube or Vimeo, with a click-to-play facade for embeds.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'video',
	'title'       => __( 'Video', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'video',
	'description' => 'Video player. source: url|file. url: YouTube or Vimeo link (an .mp4 URL also works). file: media library video {url}. poster: image shown before play (YouTube thumbnail used when empty). facade: bool, embeds load only after click. autoplay/muted/loop/controls: bools (autoplay forces muted). start: seconds. ratio: 16:9|4:3|1:1|21:9|9:16… rounded: none|sm|md|lg|xl|2xl. play_style: default|minimal.',
	'fields'      => array_merge(
		array(
			'source'     => Fields::field( 'select', __( 'Source', 'brik-builder' ), 'content', array( 'default' => 'url', 'options' => Fields::opts( array( 'url' => __( 'YouTube / Vimeo / URL', 'brik-builder' ), 'file' => __( 'Media library', 'brik-builder' ) ) ) ) ),
			'url'        => Fields::field( 'text', __( 'Video URL', 'brik-builder' ), 'content', array( 'default' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ', 'placeholder' => 'https://www.youtube.com/watch?v=…', 'show_if' => array( 'source' => 'url' ) ) ),
			'file'       => Fields::field( 'video', __( 'Video file', 'brik-builder' ), 'content', array( 'show_if' => array( 'source' => 'file' ) ) ),
			'poster'     => Fields::field( 'image', __( 'Poster image', 'brik-builder' ), 'content' ),
			'title'      => Fields::field( 'text', __( 'Accessible title', 'brik-builder' ), 'content', array( 'default' => __( 'Watch the video', 'brik-builder' ) ) ),
			'facade'     => Fields::field( 'toggle', __( 'Load player on click', 'brik-builder' ), 'content', array( 'default' => true, 'description' => __( 'Shows the poster with a play button and loads YouTube or Vimeo only when clicked. Faster pages, fewer third-party cookies.', 'brik-builder' ) ) ),
			'autoplay'   => Fields::field( 'toggle', __( 'Autoplay', 'brik-builder' ), 'content', array( 'description' => __( 'Browsers only autoplay muted videos.', 'brik-builder' ) ) ),
			'muted'      => Fields::field( 'toggle', __( 'Muted', 'brik-builder' ), 'content' ),
			'loop'       => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'content' ),
			'controls'   => Fields::field( 'toggle', __( 'Show controls', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'start'      => Fields::field( 'number', __( 'Start at (seconds)', 'brik-builder' ), 'content', array( 'min' => 0 ) ),
			'ratio'      => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => '16:9', 'options' => Fields::opts( brik_aspect_options( false ) ) ) ),
			'rounded'    => Fields::field( 'select', __( 'Rounded corners', 'brik-builder' ), 'video_style', array( 'tab' => 'design', 'group_label' => __( 'Player', 'brik-builder' ), 'default' => 'xl', 'options' => Fields::opts( brik_radius_options() ) ) ),
			'v_shadow'   => Fields::field( 'select', __( 'Shadow', 'brik-builder' ), 'video_style', array( 'tab' => 'design', 'group_label' => __( 'Player', 'brik-builder' ), 'default' => 'lg', 'options' => Fields::opts( brik_shadow_options() ) ) ),
			'play_style' => Fields::field( 'select', __( 'Play button', 'brik-builder' ), 'video_style', array( 'tab' => 'design', 'group_label' => __( 'Player', 'brik-builder' ), 'default' => 'default', 'options' => Fields::opts( array( 'default' => __( 'Solid', 'brik-builder' ), 'minimal' => __( 'Glass', 'brik-builder' ) ) ) ) ),
			'overlay'    => Fields::field( 'color', __( 'Poster overlay', 'brik-builder' ), 'video_style', array( 'tab' => 'design', 'group_label' => __( 'Player', 'brik-builder' ), 'css' => array( Fields::WRAP . ' .brik-video-overlay', 'background-color' ) ) ),
		),
		Fields::box( 'play', __( 'Play button', 'brik-builder' ), Fields::WRAP . ' .brik-video-play', array( 'bg', 'color', 'border_color', 'shadow' ) )
	),
	'render'      => static function ( $a, $ctx ) {
		$src  = 'file' === $a['source'] ? $a['file'] : $a['url'];
		$info = brik_video_info( $src );
		if ( '' === $info['url'] ) {
			return $ctx->placeholder( __( 'Add a video URL or choose a file', 'brik-builder' ) );
		}

		$autoplay = ! empty( $a['autoplay'] ) && ! $ctx->canvas;
		$muted    = ! empty( $a['muted'] ) || $autoplay;
		$title    = '' !== $a['title'] ? wp_strip_all_tags( $a['title'] ) : __( 'Video', 'brik-builder' );
		$poster   = brik_image_url( $a['poster'] );
		$frame    = brik_cls( 'brik-video-frame relative isolate w-full overflow-hidden bg-black', brik_aspect_class( $a['ratio'] ? $a['ratio'] : '16:9' ), brik_radius_class( $a['rounded'] ), brik_shadow_class( $a['v_shadow'] ) );

		if ( 'file' === $info['provider'] ) {
			$attrs = array(
				'class'       => 'brik-video-el absolute inset-0 h-full w-full object-cover',
				'src'         => $info['url'],
				'poster'      => $poster ? $poster : null,
				'controls'    => ! empty( $a['controls'] ),
				'autoplay'    => $autoplay,
				'muted'       => $muted,
				'loop'        => ! empty( $a['loop'] ),
				'playsinline' => true,
				'preload'     => $poster ? 'none' : 'metadata',
				'title'       => $title,
			);
			$src_attr = $attrs['src'];
			unset( $attrs['src'] );
			$url = esc_url( $src_attr ) . ( $a['start'] ? '#t=' . (int) $a['start'] : '' );
			return '<div class="' . esc_attr( $frame ) . '"><video' . brik_attrs( $attrs ) . ' src="' . $url . '"></video></div>';
		}

		$embed = brik_video_embed_url(
			$info,
			array(
				'autoplay' => $autoplay,
				'muted'    => $muted,
				'loop'     => ! empty( $a['loop'] ),
				'controls' => ! empty( $a['controls'] ),
				'start'    => (int) $a['start'],
			)
		);
		$iframe_attrs = 'class="absolute inset-0 h-full w-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" title="' . esc_attr( $title ) . '"';

		// Autoplay without a facade, or facade switched off: plain iframe.
		if ( empty( $a['facade'] ) || $autoplay ) {
			return '<div class="' . esc_attr( $frame ) . '"><iframe src="' . esc_url( $embed ) . '" loading="lazy" ' . $iframe_attrs . '></iframe></div>';
		}

		$thumb    = $poster ? $poster : brik_video_thumb( $info );
		$play_cls = 'minimal' === $a['play_style']
			? 'border border-white/30 bg-white/15 text-white backdrop-blur-md group-hover:bg-white/25'
			: 'bg-primary text-primary-foreground shadow-xl group-hover:scale-105';
		$autoplay_embed = brik_video_embed_url(
			$info,
			array(
				'autoplay' => true,
				'muted'    => $muted,
				'loop'     => ! empty( $a['loop'] ),
				'controls' => ! empty( $a['controls'] ),
				'start'    => (int) $a['start'],
			)
		);

		return sprintf(
			'<div class="%1$s" data-brik-video="%2$s" data-title="%3$s"><button type="button" class="brik-video-facade group absolute inset-0 flex h-full w-full cursor-pointer items-center justify-center outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset" aria-label="%4$s">%5$s<span class="brik-video-overlay absolute inset-0 bg-black/20 transition-colors group-hover:bg-black/30"></span><span class="brik-video-play relative z-10 inline-flex size-16 items-center justify-center rounded-full transition-all duration-200 md:size-20 %6$s">%7$s</span></button></div>',
			esc_attr( $frame ),
			esc_url( $autoplay_embed ),
			esc_attr( $title ),
			/* translators: %s: video title */
			esc_attr( sprintf( __( 'Play video: %s', 'brik-builder' ), $title ) ),
			$thumb ? '<img class="brik-video-thumb absolute inset-0 h-full w-full object-cover" src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async"' . ( 'youtube' === $info['provider'] && ! $poster ? ' data-fallback="' . esc_url( 'https://i.ytimg.com/vi/' . $info['id'] . '/hqdefault.jpg' ) . '"' : '' ) . '>' : '<span class="absolute inset-0 bg-linear-to-br from-neutral-800 to-neutral-950"></span>',
			esc_attr( $play_cls ),
			brik_icon( 'play', 'ml-1 size-6 fill-current md:size-7' )
		);
	},
);
