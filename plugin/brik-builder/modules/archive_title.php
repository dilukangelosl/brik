<?php
/**
 * Archive title and description for category, tag, author, date and search pages.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'archive_title',
	'title'       => __( 'Archive title', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'folder-open',
	'description' => 'Title of the current archive (category, tag, author, date, post type, search, 404). show_label: bool, small eyebrow naming the archive kind ("Category"). show_prefix: bool, keeps "Category:" in the title. show_description: bool, term/author description. level: h1|h2|h3 (default h1).',
	'fields'      => array_merge(
		array(
			'level'            => Fields::field( 'select', __( 'HTML tag', 'brik-builder' ), 'content', array( 'default' => 'h1', 'options' => Fields::opts( array( 'h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3' ) ) ) ),
			'show_label'       => Fields::field( 'toggle', __( 'Show archive type label', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_prefix'      => Fields::field( 'toggle', __( 'Keep prefix in title ("Category:")', 'brik-builder' ), 'content' ),
			'show_description' => Fields::field( 'toggle', __( 'Description', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'align'            => Fields::field( 'align', __( 'Alignment', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( 'selector' => Fields::WRAP, 'map' => array( 'left' => 'text-align:left;--brik-archive-ml:0;--brik-archive-mr:auto', 'center' => 'text-align:center;--brik-archive-ml:auto;--brik-archive-mr:auto', 'right' => 'text-align:right;--brik-archive-ml:auto;--brik-archive-mr:0', 'justify' => 'text-align:justify' ) ) ) ),
		),
		Fields::typography( 'label', __( 'Label', 'brik-builder' ), Fields::WRAP . ' .brik-archive-label' ),
		Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-archive-title' ),
		Fields::typography( 'desc', __( 'Description', 'brik-builder' ), Fields::WRAP . ' .brik-archive-desc' )
	),
	'render'      => static function ( $a, $ctx ) {
		if ( $ctx->canvas || ! brik_site_is_layout( $ctx->post_id ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'category',
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 1,
					'hide_empty' => true,
				)
			);
			$term  = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
			$kind  = __( 'Category', 'brik-builder' );
			$title = $term ? $term->name : __( 'News', 'brik-builder' );
			$desc  = $term && '' !== $term->description ? $term->description : __( 'Stories, updates and ideas from the team. The archive description appears here.', 'brik-builder' );
			if ( ! empty( $a['show_prefix'] ) ) {
				/* translators: %s: category name */
				$title = sprintf( __( 'Category: %s', 'brik-builder' ), $title );
			}
		} else {
			$kind  = brik_site_archive_kind();
			$title = brik_site_archive_title( ! empty( $a['show_prefix'] ) );
			$desc  = is_author() ? get_the_author_meta( 'description', get_queried_object_id() ) : get_the_archive_description();
		}

		$tag   = in_array( $a['level'], array( 'h1', 'h2', 'h3' ), true ) ? $a['level'] : 'h1';
		$sizes = array(
			'h1' => 'text-3xl md:text-5xl font-extrabold',
			'h2' => 'text-2xl md:text-4xl font-bold',
			'h3' => 'text-2xl font-semibold',
		);
		$html = '';
		if ( ! empty( $a['show_label'] ) && '' !== $kind ) {
			$html .= '<p class="brik-archive-label mb-3 text-sm font-semibold uppercase tracking-wider text-primary">' . esc_html( $kind ) . '</p>';
		}
		$html .= '<' . $tag . ' class="brik-archive-title font-heading tracking-tight text-balance ' . esc_attr( $sizes[ $tag ] ) . '">' . esc_html( $title ) . '</' . $tag . '>';
		if ( ! empty( $a['show_description'] ) && '' !== trim( wp_strip_all_tags( (string) $desc ) ) ) {
			$html .= '<div class="brik-archive-desc mt-4 mr-[var(--brik-archive-mr,auto)] ml-[var(--brik-archive-ml,0)] max-w-2xl text-lg leading-relaxed text-muted-foreground [&_p]:m-0">' . wp_kses_post( wpautop( $desc ) ) . '</div>';
		}
		return $html;
	},
);
