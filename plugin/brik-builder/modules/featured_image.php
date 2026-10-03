<?php
/**
 * Featured image of the current post.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'featured_image',
	'title'       => __( 'Featured image', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'image',
	'description' => 'Featured image of the current post. size: thumbnail|medium|medium_large|large|full. aspect_ratio: auto|16/9|4/3|3/2|1/1|21/9|3/4 (responsive, image is cropped to fill). rounded: none|md|lg|xl|2xl. link: bool. caption: bool. fallback: image used when the post has none.',
	'fields'      => array_merge(
		array(
			'size'         => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array( 'default' => 'large', 'options' => Fields::opts( array( 'thumbnail' => __( 'Thumbnail', 'brik-builder' ), 'medium' => __( 'Medium', 'brik-builder' ), 'medium_large' => __( 'Medium large', 'brik-builder' ), 'large' => __( 'Large', 'brik-builder' ), 'full' => __( 'Full', 'brik-builder' ) ) ) ) ),
			'aspect_ratio' => Fields::field( 'select', __( 'Aspect ratio', 'brik-builder' ), 'content', array( 'default' => '16/9', 'responsive' => true, 'options' => Fields::opts( array( 'auto' => __( 'Original', 'brik-builder' ), '16/9' => '16:9', '21/9' => '21:9', '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1', '3/4' => '3:4' ) ), 'css' => array( Fields::WRAP . ' .brik-featured-media', 'aspect-ratio' ) ) ),
			'rounded'      => Fields::field( 'select', __( 'Corners', 'brik-builder' ), 'content', array( 'default' => 'xl', 'options' => Fields::opts( array( 'none' => __( 'Square', 'brik-builder' ), 'md' => __( 'Small', 'brik-builder' ), 'lg' => __( 'Medium', 'brik-builder' ), 'xl' => __( 'Large', 'brik-builder' ), '2xl' => __( 'Extra large', 'brik-builder' ) ) ) ) ),
			'link'         => Fields::field( 'toggle', __( 'Link to the post', 'brik-builder' ), 'content' ),
			'caption'      => Fields::field( 'toggle', __( 'Show caption', 'brik-builder' ), 'content' ),
			'fallback'     => Fields::field( 'image', __( 'Fallback image', 'brik-builder' ), 'content', array( 'description' => __( 'Shown when the post has no featured image.', 'brik-builder' ) ) ),
			'focus'        => Fields::field( 'select', __( 'Focus point', 'brik-builder' ), 'content', array( 'options' => Fields::opts( Fields::positions() ), 'css' => array( Fields::WRAP . ' .brik-featured-media img', 'object-position' ) ) ),
		),
		Fields::typography( 'caption', __( 'Caption', 'brik-builder' ), Fields::WRAP . ' .brik-featured-caption' )
	),
	'render'      => static function ( $a, $ctx ) {
		$rounded = array(
			'none' => 'rounded-none',
			'md'   => 'rounded-md',
			'lg'   => 'rounded-lg',
			'xl'   => 'rounded-xl',
			'2xl'  => 'rounded-2xl',
		);
		$radius = isset( $rounded[ $a['rounded'] ] ) ? $rounded[ $a['rounded'] ] : $rounded['xl'];
		$size   = in_array( $a['size'], array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), true ) ? $a['size'] : 'large';
		$post   = brik_site_post( $ctx );
		$id     = $post ? (int) get_post_thumbnail_id( $post ) : 0;
		$alt    = $post ? wp_strip_all_tags( get_the_title( $post ) ) : '';

		if ( $id ) {
			$img_alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$img     = wp_get_attachment_image( $id, $size, false, array( 'class' => 'size-full object-cover', 'alt' => '' !== $img_alt ? $img_alt : $alt, 'fetchpriority' => 'high', 'loading' => false, 'decoding' => 'async' ) );
		} elseif ( brik_site_has_image( $a['fallback'] ) ) {
			$img = brik_image( $a['fallback'], $size, array( 'class' => 'size-full object-cover', 'alt' => '' ) );
		} elseif ( $ctx->canvas ) {
			return '<div class="brik-featured-media brik-featured-empty flex min-h-48 items-center justify-center bg-muted text-muted-foreground ' . esc_attr( $radius ) . '"><span class="flex flex-col items-center gap-2 text-sm">' . brik_icon( 'image', 'size-8 opacity-60' ) . esc_html__( 'Featured image', 'brik-builder' ) . '</span></div>';
		} else {
			return '';
		}
		if ( ! $img ) {
			return '';
		}

		$media = '<div class="brik-featured-media overflow-hidden bg-muted ' . esc_attr( $radius ) . '">' . $img . '</div>';
		if ( $post && ! empty( $a['link'] ) ) {
			$media = '<a class="block" href="' . esc_url( get_permalink( $post ) ) . '">' . $media . '</a>';
		}
		$caption = $id && ! empty( $a['caption'] ) ? wp_get_attachment_caption( $id ) : '';
		if ( $caption ) {
			return '<figure class="brik-featured">' . $media . '<figcaption class="brik-featured-caption mt-3 text-center text-sm text-muted-foreground">' . esc_html( $caption ) . '</figcaption></figure>';
		}
		return $media;
	},
);
