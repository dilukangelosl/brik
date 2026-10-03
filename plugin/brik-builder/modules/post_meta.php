<?php
/**
 * Post meta: date, author, categories, tags, comments and reading time.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'post_meta',
	'title'       => __( 'Post meta', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'calendar-days',
	'description' => 'Meta line for the current post. Toggles: show_categories, show_author, show_avatar, show_date, show_modified, show_reading_time, show_comments, show_tags. terms_style: secondary|outline|default (badges) or plain (comma links). separator: dot|slash|pipe|none. icons: bool. date_format: PHP date format, empty uses the site setting.',
	'fields'      => array_merge(
		array(
			'show_categories'   => Fields::field( 'toggle', __( 'Categories', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_author'       => Fields::field( 'toggle', __( 'Author', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_avatar'       => Fields::field( 'toggle', __( 'Author avatar', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'show_author' => true ) ) ),
			'show_date'         => Fields::field( 'toggle', __( 'Date', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_modified'     => Fields::field( 'toggle', __( 'Last updated date', 'brik-builder' ), 'content' ),
			'show_reading_time' => Fields::field( 'toggle', __( 'Reading time', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_comments'     => Fields::field( 'toggle', __( 'Comment count', 'brik-builder' ), 'content' ),
			'show_tags'         => Fields::field( 'toggle', __( 'Tags', 'brik-builder' ), 'content' ),
			'terms_style'       => Fields::field( 'select', __( 'Category style', 'brik-builder' ), 'content', array( 'default' => 'secondary', 'options' => Fields::opts( array( 'secondary' => __( 'Badge', 'brik-builder' ), 'outline' => __( 'Outline badge', 'brik-builder' ), 'default' => __( 'Solid badge', 'brik-builder' ), 'plain' => __( 'Plain links', 'brik-builder' ) ) ) ) ),
			'separator'         => Fields::field( 'select', __( 'Separator', 'brik-builder' ), 'content', array( 'default' => 'dot', 'options' => Fields::opts( array( 'dot' => __( 'Dot', 'brik-builder' ), 'slash' => __( 'Slash', 'brik-builder' ), 'pipe' => __( 'Line', 'brik-builder' ), 'none' => __( 'None', 'brik-builder' ) ) ) ) ),
			'icons'             => Fields::field( 'toggle', __( 'Icons', 'brik-builder' ), 'content' ),
			'date_format'       => Fields::field( 'text', __( 'Date format', 'brik-builder' ), 'content', array( 'placeholder' => 'F j, Y' ) ),
			'align'             => Fields::field( 'select', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'options' => Fields::opts( array( '' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'flex-end' => __( 'Right', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP . ' .brik-post-meta', 'justify-content' ) ) ),
			'gap'               => Fields::field( 'unit', __( 'Space between items', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-post-meta', 'column-gap' ) ) ),
		),
		Fields::typography( 'meta', __( 'Meta text', 'brik-builder' ), Fields::WRAP . ' .brik-post-meta' ),
		Fields::typography( 'meta_link', __( 'Meta links', 'brik-builder' ), Fields::WRAP . ' .brik-meta-link' )
	),
	'render'      => static function ( $a, $ctx ) {
		$post = brik_site_post( $ctx );
		if ( ! $post ) {
			return $ctx->placeholder( __( 'Post meta appears on posts', 'brik-builder' ) );
		}
		$map  = array(
			'categories' => 'show_categories',
			'author'     => 'show_author',
			'date'       => 'show_date',
			'modified'   => 'show_modified',
			'reading'    => 'show_reading_time',
			'comments'   => 'show_comments',
			'tags'       => 'show_tags',
		);
		$keys = array();
		foreach ( $map as $key => $toggle ) {
			if ( ! empty( $a[ $toggle ] ) ) {
				$keys[] = $key;
			}
		}
		$parts = brik_site_meta_parts(
			$post,
			$keys,
			array(
				'icons'       => ! empty( $a['icons'] ),
				'avatar'      => ! empty( $a['show_avatar'] ),
				'terms'       => in_array( $a['terms_style'], array( 'secondary', 'outline', 'default', 'plain' ), true ) ? $a['terms_style'] : 'secondary',
				'date_format' => trim( wp_strip_all_tags( (string) $a['date_format'] ) ),
			)
		);
		if ( ! $parts ) {
			return $ctx->placeholder( $keys ? __( 'This post has none of the selected meta items', 'brik-builder' ) : __( 'Turn on at least one meta item', 'brik-builder' ) );
		}
		return '<div class="brik-post-meta flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted-foreground">' . implode( brik_site_meta_sep( $a['separator'] ), $parts ) . '</div>';
	},
);
