<?php
/**
 * Post title: the current post's title, or the archive title on listings.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'post_title',
	'title'       => __( 'Post title', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'heading-1',
	'description' => 'Title of the current post (in templates: the viewed post; on archives: the archive title). level: h1-h6|p (default h1). size: display|h1|h2|h3|h4. link: bool, links to the post. show_meta: bool, adds a date · author · reading time line below.',
	'fields'      => array_merge(
		array(
			'level'     => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5', 'h6' => 'H6', 'p' => 'p' ) ) ) ),
			'size'      => Fields::field( 'select', __( 'Size', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( array( 'display' => __( 'Display', 'brik-builder' ), 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ) ) ) ),
			'link'      => Fields::field( 'toggle', __( 'Link to the post', 'brik-builder' ), 'content' ),
			'show_meta' => Fields::field( 'toggle', __( 'Show date, author and reading time', 'brik-builder' ), 'content' ),
			'align'     => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP, 'prop' => 'text-align' ) ) ),
		),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-post-title' ),
		Fields::typography( 'meta', __( 'Meta line', 'brik-builder' ), Fields::WRAP . ' .brik-post-meta' )
	),
	'render'      => static function ( $a, $ctx ) {
		$sizes = array(
			'display' => 'text-4xl md:text-6xl font-bold tracking-tight text-balance',
			'h1'      => 'text-3xl md:text-5xl font-extrabold tracking-tight text-balance',
			'h2'      => 'text-2xl md:text-4xl font-semibold tracking-tight text-balance',
			'h3'      => 'text-2xl font-semibold tracking-tight',
			'h4'      => 'text-xl font-semibold tracking-tight',
		);
		$tag   = in_array( $a['level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ), true ) ? $a['level'] : 'h1';
		$size  = isset( $sizes[ $a['size'] ] ) ? $sizes[ $a['size'] ] : $sizes['h1'];
		$post  = null;
		$title = '';
		if ( brik_site_is_listing( $ctx ) ) {
			$title = brik_site_archive_title();
		} elseif ( brik_site_preview_is_listing( $ctx ) ) {
			$sample = brik_site_sample_post();
			$term   = $sample ? brik_site_primary_term( $sample, 'category' ) : null;
			$title  = $term ? $term->name : __( 'Latest posts', 'brik-builder' );
		} else {
			$post  = brik_site_post( $ctx );
			$title = $post ? get_the_title( $post ) : '';
		}
		if ( '' === $title ) {
			return $ctx->placeholder( __( 'Post title', 'brik-builder' ) );
		}

		$text = esc_html( wp_strip_all_tags( $title ) );
		if ( $post && ! empty( $a['link'] ) ) {
			$text = '<a class="transition-colors hover:text-foreground/80" href="' . esc_url( get_permalink( $post ) ) . '">' . $text . '</a>';
		}
		$html = sprintf( '<%1$s class="brik-post-title font-heading %2$s">%3$s</%1$s>', $tag, esc_attr( $size ), $text );

		if ( $post && ! empty( $a['show_meta'] ) ) {
			$parts = brik_site_meta_parts( $post, array( 'date', 'author', 'reading' ) );
			$html .= '<div class="brik-post-meta mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-muted-foreground">' . implode( brik_site_meta_sep( 'dot' ), $parts ) . '</div>';
		}
		return $html;
	},
);
