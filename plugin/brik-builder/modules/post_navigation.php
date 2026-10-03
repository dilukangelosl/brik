<?php
/**
 * Previous / next post links as cards.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'post_navigation',
	'title'       => __( 'Post navigation', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'arrow-left-right',
	'description' => 'Previous and next post cards for single posts. in_same_term: bool (with "taxonomy", default category). show_thumbnail: bool. prev_label / next_label: small labels above the titles.',
	'fields'      => array_merge(
		array(
			'in_same_term'   => Fields::field( 'toggle', __( 'Stay in the same category', 'brik-builder' ), 'content' ),
			'taxonomy'       => Fields::field( 'taxonomy', __( 'Taxonomy', 'brik-builder' ), 'content', array( 'default' => 'category', 'show_if' => array( 'in_same_term' => true ) ) ),
			'show_thumbnail' => Fields::field( 'toggle', __( 'Thumbnails', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'prev_label'     => Fields::field( 'text', __( 'Previous label', 'brik-builder' ), 'content', array( 'default' => __( 'Previous', 'brik-builder' ) ) ),
			'next_label'     => Fields::field( 'text', __( 'Next label', 'brik-builder' ), 'content', array( 'default' => __( 'Next', 'brik-builder' ) ) ),
		),
		Fields::box( 'card', __( 'Cards', 'brik-builder' ), Fields::WRAP . ' .brik-post-nav-card' ),
		Fields::typography( 'label', __( 'Labels', 'brik-builder' ), Fields::WRAP . ' .brik-post-nav-label' ),
		Fields::typography( 'title', __( 'Titles', 'brik-builder' ), Fields::WRAP . ' .brik-post-nav-title' )
	),
	'render'      => static function ( $a, $ctx ) {
		$post = brik_site_post( $ctx );
		if ( ! $post ) {
			return $ctx->placeholder( __( 'Links to the previous and next post appear here', 'brik-builder' ) );
		}
		$same = ! empty( $a['in_same_term'] );
		$tax  = $a['taxonomy'] && taxonomy_exists( $a['taxonomy'] ) ? $a['taxonomy'] : 'category';

		list( $prev, $next ) = brik_site_with_post(
			$post,
			static function () use ( $same, $tax ) {
				return array( get_previous_post( $same, '', $tax ), get_next_post( $same, '', $tax ) );
			}
		);

		// The newest post has no "next"; borrow neighbours so the preview shows both cards.
		if ( $ctx->canvas && ( ! $prev || ! $next ) ) {
			$others = get_posts(
				array(
					'numberposts' => 2,
					'post_type'   => $post->post_type,
					'exclude'     => array_filter( array( $post->ID, $prev ? $prev->ID : 0, $next ? $next->ID : 0 ) ),
				)
			);
			$prev   = $prev ? $prev : array_shift( $others );
			$next   = $next ? $next : array_shift( $others );
		}
		if ( ! $prev && ! $next ) {
			return $ctx->placeholder( __( 'There are no other posts to link to yet', 'brik-builder' ) );
		}

		$card = static function ( $target, $dir ) use ( $a, $ctx ) {
			$is_next = 'next' === $dir;
			$thumb   = '';
			if ( ! empty( $a['show_thumbnail'] ) && has_post_thumbnail( $target ) ) {
				$thumb = get_the_post_thumbnail( $target, 'thumbnail', array( 'class' => 'brik-post-nav-thumb size-16 shrink-0 rounded-lg bg-muted object-cover', 'alt' => '' ) );
			}
			$label_key = $is_next ? 'next_label' : 'prev_label';
			$arrow     = brik_icon( $is_next ? 'arrow-right' : 'arrow-left', 'size-3.5' );
			$label     = '<span' . $ctx->inline( $label_key ) . '>' . brik_inline( $a[ $label_key ] ) . '</span>';
			$label     = '<span class="brik-post-nav-label inline-flex items-center gap-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">' . ( $is_next ? $label . $arrow : $arrow . $label ) . '</span>';
			$class     = brik_cls(
				'brik-post-nav-card group flex items-center gap-4 rounded-xl border bg-card p-4 text-card-foreground shadow-xs transition-colors hover:bg-accent/60',
				$is_next ? 'brik-post-nav-next flex-row-reverse text-right sm:col-start-2' : 'brik-post-nav-prev'
			);
			return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( get_permalink( $target ) ) . '" rel="' . ( $is_next ? 'next' : 'prev' ) . '">' . $thumb
				. '<span class="flex min-w-0 flex-1 flex-col gap-1' . ( $is_next ? ' items-end' : '' ) . '">' . $label
				. '<span class="brik-post-nav-title line-clamp-2 font-medium leading-snug">' . esc_html( wp_strip_all_tags( get_the_title( $target ) ) ) . '</span></span></a>';
		};

		return '<nav class="brik-post-nav grid gap-4 sm:grid-cols-2" aria-label="' . esc_attr__( 'More posts', 'brik-builder' ) . '">'
			. ( $prev ? $card( $prev, 'prev' ) : '' )
			. ( $next ? $card( $next, 'next' ) : '' )
			. '</nav>';
	},
);
