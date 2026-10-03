<?php
/**
 * Author box: avatar, name, bio and a link to the author's posts.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'author_box',
	'title'       => __( 'Author box', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'square-user',
	'description' => 'Card about the current post\'s author (or the author of an author archive). layout: horizontal|stacked. eyebrow: small label above the name ("Written by"). show_bio, show_link: bool. link_text: text for the posts link. card: bool, wraps it in a bordered card. avatar_size: CSS length.',
	'fields'      => array_merge(
		array(
			'layout'      => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'horizontal', 'responsive' => true, 'options' => Fields::opts( array( 'horizontal' => __( 'Avatar beside text', 'brik-builder' ), 'stacked' => __( 'Avatar above text', 'brik-builder' ) ) ), 'css' => array( 'selector' => Fields::WRAP . ' .brik-author', 'map' => array( 'horizontal' => 'flex-direction:row;text-align:left;align-items:flex-start', 'stacked' => 'flex-direction:column;text-align:center;align-items:center' ) ) ) ),
			'eyebrow'     => Fields::field( 'text', __( 'Label', 'brik-builder' ), 'content', array( 'default' => __( 'Written by', 'brik-builder' ) ) ),
			'show_bio'    => Fields::field( 'toggle', __( 'Biography', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_link'   => Fields::field( 'toggle', __( 'Link to all posts', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'link_text'   => Fields::field( 'text', __( 'Link text', 'brik-builder' ), 'content', array( 'default' => __( 'View all posts', 'brik-builder' ), 'show_if' => array( 'show_link' => true ) ) ),
			'card'        => Fields::field( 'toggle', __( 'Card style', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'avatar_size' => Fields::field( 'unit', __( 'Avatar size', 'brik-builder' ), 'content', array( 'default' => '64px', 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP . ' .brik-author-avatar', 'prop' => array( 'width', 'height' ) ) ) ),
		),
		Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-author' ),
		Fields::typography( 'name', __( 'Name', 'brik-builder' ), Fields::WRAP . ' .brik-author-name' ),
		Fields::typography( 'bio', __( 'Biography', 'brik-builder' ), Fields::WRAP . ' .brik-author-bio' )
	),
	'render'      => static function ( $a, $ctx ) {
		$author = 0;
		if ( ! $ctx->canvas && brik_site_is_layout( $ctx->post_id ) && is_author() ) {
			$author = get_queried_object_id();
		} else {
			$post   = brik_site_post( $ctx );
			$author = $post ? (int) $post->post_author : 0;
		}
		if ( ! $author || ! get_userdata( $author ) ) {
			return $ctx->placeholder( __( 'The post author appears here', 'brik-builder' ) );
		}

		$name = get_the_author_meta( 'display_name', $author );
		$bio  = get_the_author_meta( 'description', $author );
		$url  = get_author_posts_url( $author );

		$body = '';
		if ( '' !== trim( (string) $a['eyebrow'] ) ) {
			$body .= '<p class="brik-author-eyebrow text-xs font-medium uppercase tracking-wider text-muted-foreground"' . $ctx->inline( 'eyebrow' ) . '>' . brik_inline( $a['eyebrow'] ) . '</p>';
		}
		$body .= '<p class="brik-author-name font-heading text-lg font-semibold tracking-tight"><a class="transition-colors hover:text-foreground/80" href="' . esc_url( $url ) . '" rel="author">' . esc_html( $name ) . '</a></p>';
		if ( ! empty( $a['show_bio'] ) && '' !== trim( $bio ) ) {
			$body .= '<p class="brik-author-bio text-sm leading-relaxed text-muted-foreground">' . wp_kses_post( $bio ) . '</p>';
		}
		if ( ! empty( $a['show_link'] ) && '' !== trim( (string) $a['link_text'] ) ) {
			$body .= '<a class="brik-author-link mt-1 inline-flex items-center gap-1 text-sm font-medium text-primary underline-offset-4 hover:underline" href="' . esc_url( $url ) . '"><span' . $ctx->inline( 'link_text' ) . '>' . brik_inline( $a['link_text'] ) . '</span>' . brik_icon( 'arrow-right', 'size-3.5' ) . '</a>';
		}

		$avatar = get_avatar_url( $author, array( 'size' => 192 ) );
		$class  = brik_cls(
			'brik-author flex gap-5',
			array( 'rounded-xl border bg-card p-6 text-card-foreground shadow-sm' => ! empty( $a['card'] ) )
		);
		return '<div class="' . esc_attr( $class ) . '">'
			. ( $avatar ? '<img class="brik-author-avatar size-16 shrink-0 rounded-full bg-muted object-cover" src="' . esc_url( $avatar ) . '" alt="" width="96" height="96" loading="lazy" decoding="async">' : '' )
			. '<div class="flex min-w-0 flex-col gap-1.5">' . $body . '</div></div>';
	},
);
