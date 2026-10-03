<?php
/**
 * Audio player card: cover art, title, artist and the native audio controls.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'audio',
	'title'       => __( 'Audio', 'brik-builder' ),
	'category'    => 'media',
	'icon'        => 'music',
	'description' => 'Audio player card. file: audio URL or {url} (mp3/ogg/wav/m4a). title, artist: text. cover: image. layout: horizontal|stacked|compact (compact = player only). loop, autoplay: bools. preload: none|metadata.',
	'fields'      => array_merge(
		array(
			'file'     => Fields::field( 'video', __( 'Audio file', 'brik-builder' ), 'content', array( 'media_type' => 'audio', 'default' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3' ) ),
			'title'    => Fields::field( 'text', __( 'Title', 'brik-builder' ), 'content', array( 'default' => __( 'Midnight Drive', 'brik-builder' ), 'inline' => true ) ),
			'artist'   => Fields::field( 'text', __( 'Artist', 'brik-builder' ), 'content', array( 'default' => __( 'The Night Shift · Neon Roads', 'brik-builder' ), 'inline' => true ) ),
			'cover'    => Fields::field( 'image', __( 'Cover image', 'brik-builder' ), 'content', array( 'default' => array( 'url' => brik_sample_image( '1493246507139-91e8fad9978e', 400, 400 ), 'alt' => '' ) ) ),
			'layout'   => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'options' => Fields::opts( array( 'horizontal' => __( 'Cover beside', 'brik-builder' ), 'stacked' => __( 'Cover on top', 'brik-builder' ), 'compact' => __( 'Player only', 'brik-builder' ) ) ) ) ),
			'loop'     => Fields::field( 'toggle', __( 'Loop', 'brik-builder' ), 'content' ),
			'autoplay' => Fields::field( 'toggle', __( 'Autoplay', 'brik-builder' ), 'content', array( 'description' => __( 'Most browsers block autoplay with sound.', 'brik-builder' ) ) ),
			'preload'  => Fields::field( 'select', __( 'Preload', 'brik-builder' ), 'content', array( 'default' => 'metadata', 'options' => Fields::opts( array( 'metadata' => __( 'Duration only', 'brik-builder' ), 'none' => __( 'Nothing', 'brik-builder' ) ) ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-audio-card' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-audio-title' ),
		Fields::typography( 'artist', __( 'Artist', 'brik-builder' ), Fields::WRAP . ' .brik-audio-artist' )
	),
	'render'      => static function ( $a, $ctx ) {
		$url = is_array( $a['file'] ) ? ( isset( $a['file']['url'] ) ? $a['file']['url'] : '' ) : (string) $a['file'];
		if ( '' === trim( $url ) ) {
			return $ctx->placeholder( __( 'Choose an audio file', 'brik-builder' ) );
		}

		$audio = '<audio class="brik-audio-player w-full" controls' . brik_attrs(
			array(
				'src'      => $url,
				'loop'     => ! empty( $a['loop'] ),
				'autoplay' => ! empty( $a['autoplay'] ) && ! $ctx->canvas,
				'preload'  => 'none' === $a['preload'] ? 'none' : 'metadata',
				'aria-label' => '' !== $a['title'] ? wp_strip_all_tags( $a['title'] ) : null,
			)
		) . '></audio>';

		if ( 'compact' === $a['layout'] ) {
			return '<div class="brik-audio-card">' . $audio . '</div>';
		}

		$stacked = 'stacked' === $a['layout'];
		$cover   = brik_image( $a['cover'], 'medium', array( 'class' => $stacked ? 'brik-audio-cover aspect-square w-full rounded-lg object-cover' : 'brik-audio-cover size-16 shrink-0 rounded-lg object-cover shadow-sm sm:size-24', 'alt' => '' ) );
		if ( '' === $cover ) {
			$cover = '<span class="brik-audio-cover inline-flex ' . ( $stacked ? 'aspect-square w-full' : 'size-16 shrink-0 sm:size-24' ) . ' items-center justify-center rounded-lg bg-muted text-muted-foreground">' . brik_icon( 'music', 'size-8' ) . '</span>';
		}

		$meta = '';
		if ( '' !== $a['title'] ) {
			$meta .= '<p class="brik-audio-title truncate text-base font-semibold leading-tight"' . $ctx->inline( 'title' ) . '>' . brik_inline( $a['title'] ) . '</p>';
		}
		if ( '' !== $a['artist'] ) {
			$meta .= '<p class="brik-audio-artist mt-1 truncate text-sm text-muted-foreground"' . $ctx->inline( 'artist' ) . '>' . brik_inline( $a['artist'] ) . '</p>';
		}

		if ( $stacked ) {
			return '<div class="brik-audio-card flex max-w-sm flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground shadow-sm">' . $cover . '<div class="min-w-0 px-1">' . $meta . '</div>' . $audio . '</div>';
		}
		// Narrow screens move the player below the cover so its timeline keeps room.
		return '<div class="brik-audio-card grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3 rounded-xl border bg-card p-4 text-card-foreground shadow-sm">' . '<div class="sm:row-span-2">' . $cover . '</div><div class="min-w-0 sm:self-end">' . $meta . '</div><div class="col-span-2 sm:col-span-1 sm:self-start">' . $audio . '</div></div>';
	},
);
