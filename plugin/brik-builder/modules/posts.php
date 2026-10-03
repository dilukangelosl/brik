<?php
/**
 * Posts: blog grid, list, masonry or minimal list from a custom query or the main query.
 *
 * @package Brik
 */

use Brik\Fields;

defined( 'ABSPATH' ) || exit;

$brik_off = array( '', 'false', '0' );

$brik_posts_card = static function ( WP_Post $p, array $a, $layout, $level, $terms_attr ) {
	$url   = get_permalink( $p );
	$title = esc_html( wp_strip_all_tags( get_the_title( $p ) ) );
	$card  = 'card' === $a['card_style'];

	if ( 'minimal' === $layout ) {
		return '<article class="brik-post-row group relative flex flex-col gap-1 py-4 sm:flex-row sm:items-baseline sm:justify-between sm:gap-6"' . $terms_attr . '>'
			. '<' . $level . ' class="brik-post-title font-medium leading-snug"><a class="underline-offset-4 after:absolute after:inset-0 group-hover:underline" href="' . esc_url( $url ) . '">' . $title . '</a></' . $level . '>'
			. ( ! empty( $a['show_date'] ) ? '<time class="brik-post-date shrink-0 text-sm text-muted-foreground tabular-nums" datetime="' . esc_attr( get_the_date( 'c', $p ) ) . '">' . esc_html( get_the_date( '', $p ) ) . '</time>' : '' )
			. '</article>';
	}

	$list  = 'list' === $layout;
	$media = '';
	if ( ! empty( $a['show_image'] ) && has_post_thumbnail( $p ) ) {
		$size  = in_array( $a['image_size'], array( 'medium', 'medium_large', 'large', 'full' ), true ) ? $a['image_size'] : 'medium_large';
		$img   = get_the_post_thumbnail( $p, $size, array( 'class' => 'size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03]', 'alt' => '' ) );
		$mcls  = brik_cls(
			'brik-post-media relative block shrink-0 overflow-hidden bg-muted',
			array(
				'aspect-video'                                => 'masonry' !== $layout,
				'sm:aspect-auto sm:min-h-48 sm:w-2/5'         => $list,
				'rounded-xl'                                  => ! $card,
				'border-b'                                    => $card && ! $list,
				'sm:border-r'                                 => $card && $list,
			)
		);
		$media = '<a class="' . esc_attr( $mcls ) . '" href="' . esc_url( $url ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
	}

	$badges = '';
	if ( ! empty( $a['show_category'] ) ) {
		$tax    = 'post' === $p->post_type ? 'category' : ( $a['filter_taxonomy'] ? $a['filter_taxonomy'] : '' );
		$badges = $tax && taxonomy_exists( $tax ) ? brik_site_terms( $p, $tax, 'secondary', 2 ) : '';
	}

	$meta_keys = array();
	foreach ( array( 'author' => 'show_author', 'date' => 'show_date', 'reading' => 'show_reading_time' ) as $key => $toggle ) {
		if ( ! empty( $a[ $toggle ] ) ) {
			$meta_keys[] = $key;
		}
	}
	$meta = $meta_keys ? implode( brik_site_meta_sep( 'dot' ), brik_site_meta_parts( $p, $meta_keys, array( 'avatar' => true ) ) ) : '';

	$excerpt = '';
	if ( ! empty( $a['show_excerpt'] ) ) {
		$text    = brik_site_excerpt( $p, max( 1, (int) $a['excerpt_length'] ) );
		$excerpt = '' !== $text ? '<p class="brik-post-excerpt text-sm leading-relaxed text-muted-foreground">' . esc_html( $text ) . '</p>' : '';
	}

	$more = '';
	if ( ! empty( $a['show_read_more'] ) && '' !== trim( (string) $a['read_more_text'] ) ) {
		$more = '<span class="brik-post-more inline-flex items-center gap-1 text-sm font-medium text-primary" aria-hidden="true">' . brik_inline( $a['read_more_text'] ) . brik_icon( 'arrow-right', 'size-3.5 transition-transform group-hover:translate-x-0.5' ) . '</span>';
	}

	$footer = '' !== $meta || '' !== $more ? '<div class="brik-post-footer mt-auto flex flex-col items-start gap-3 pt-2"><div class="brik-post-meta flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">' . $meta . '</div>' . $more . '</div>' : '';

	$body_cls = brik_cls(
		'brik-post-body flex flex-1 flex-col gap-3',
		array(
			'p-6'                        => $card,
			'pt-5'                       => ! $card && ! $list && '' !== $media,
			'pt-4 sm:pt-0 sm:pl-6'       => ! $card && $list && '' !== $media,
			'sm:justify-center'          => $list,
		)
	);
	$art_cls = brik_cls(
		'brik-post-card group relative flex flex-col',
		array(
			'overflow-hidden rounded-xl border bg-card text-card-foreground shadow-xs transition-shadow hover:shadow-md' => $card,
			'sm:flex-row'                                                                                              => $list,
		)
	);

	return '<article class="' . esc_attr( $art_cls ) . '"' . $terms_attr . '>' . $media
		. '<div class="' . esc_attr( $body_cls ) . '">'
		. ( '' !== $badges ? '<div class="brik-post-terms">' . $badges . '</div>' : '' )
		. '<' . $level . ' class="brik-post-title font-heading text-lg font-semibold leading-snug tracking-tight text-balance"><a class="after:absolute after:inset-0" href="' . esc_url( $url ) . '">' . $title . '</a></' . $level . '>'
		. $excerpt . $footer
		. '</div></article>';
};

return array(
	'type'        => 'posts',
	'title'       => __( 'Posts', 'brik-builder' ),
	'category'    => 'post',
	'icon'        => 'layout-grid',
	'description' => 'Blog / portfolio loop. Query: use_main_query (bool, for archive/blog templates) or post_type, taxonomy + terms (comma separated ids or slugs), orderby: date|modified|title|comment_count|menu_order|rand, order: DESC|ASC, posts_per_page, offset, exclude_current. layout: grid|list|masonry|minimal; columns (responsive, default 3/2/1); gap. Card: card_style card|plain, show_image, aspect_ratio ""|16/9|4/3|3/2|1/1|auto, show_category, title_level, show_excerpt + excerpt_length, show_author, show_date, show_reading_time, show_read_more + read_more_text. pagination: none|numbered|load_more. show_filter: client-side category filter bar (filter_taxonomy). empty_text shown when nothing matches.',
	'fields'      => array_merge(
		array(
			'use_main_query'    => Fields::field( 'toggle', __( 'Use the page\'s own posts', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'description' => __( 'For blog, archive and search templates: shows the posts WordPress picked for the page.', 'brik-builder' ) ) ),
			'post_type'         => Fields::field( 'post_type', __( 'Post type', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'default' => 'post', 'show_if' => array( 'use_main_query' => $brik_off ) ) ),
			'taxonomy'          => Fields::field( 'taxonomy', __( 'Filter by taxonomy', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'show_if' => array( 'use_main_query' => $brik_off ) ) ),
			'terms'             => Fields::field( 'text', __( 'Terms', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'placeholder' => 'news, 12, 14', 'description' => __( 'Term slugs or IDs, separated by commas.', 'brik-builder' ), 'show_if' => array( 'taxonomy' => '!', 'use_main_query' => $brik_off ) ) ),
			'orderby'           => Fields::field( 'select', __( 'Order by', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'default' => 'date', 'show_if' => array( 'use_main_query' => $brik_off ), 'options' => Fields::opts( array( 'date' => __( 'Date', 'brik-builder' ), 'modified' => __( 'Last updated', 'brik-builder' ), 'title' => __( 'Title', 'brik-builder' ), 'comment_count' => __( 'Comments', 'brik-builder' ), 'menu_order' => __( 'Menu order', 'brik-builder' ), 'rand' => __( 'Random', 'brik-builder' ) ) ) ) ),
			'order'             => Fields::field( 'select', __( 'Order', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'default' => 'DESC', 'show_if' => array( 'use_main_query' => $brik_off ), 'options' => Fields::opts( array( 'DESC' => __( 'Descending', 'brik-builder' ), 'ASC' => __( 'Ascending', 'brik-builder' ) ) ) ) ),
			'posts_per_page'    => Fields::field( 'number', __( 'Posts per page', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'default' => 6, 'min' => 1, 'max' => 50, 'show_if' => array( 'use_main_query' => $brik_off ) ) ),
			'offset'            => Fields::field( 'number', __( 'Skip posts', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'min' => 0, 'show_if' => array( 'use_main_query' => $brik_off ) ) ),
			'exclude_current'   => Fields::field( 'toggle', __( 'Exclude the current post', 'brik-builder' ), 'query', array( 'group_label' => __( 'Query', 'brik-builder' ), 'default' => true, 'show_if' => array( 'use_main_query' => $brik_off ) ) ),

			'layout'            => Fields::field( 'select', __( 'Layout', 'brik-builder' ), 'content', array( 'default' => 'grid', 'options' => Fields::opts( array( 'grid' => __( 'Grid', 'brik-builder' ), 'list' => __( 'List (image left)', 'brik-builder' ), 'masonry' => __( 'Masonry', 'brik-builder' ), 'minimal' => __( 'Minimal (titles only)', 'brik-builder' ) ) ) ) ),
			'columns'           => Fields::field( 'number', __( 'Columns', 'brik-builder' ), 'content', array( 'default' => 3, 'min' => 1, 'max' => 6, 'responsive' => true, 'show_if' => array( 'layout' => array( 'grid', 'masonry' ) ) ) ),
			'gap'               => Fields::field( 'unit', __( 'Gap', 'brik-builder' ), 'content', array( 'responsive' => true, 'css' => array( Fields::WRAP . ' .brik-posts-items', '--brik-posts-gap' ) ) ),
			'card_style'        => Fields::field( 'select', __( 'Card style', 'brik-builder' ), 'content', array( 'default' => 'card', 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ), 'options' => Fields::opts( array( 'card' => __( 'Card', 'brik-builder' ), 'plain' => __( 'Plain', 'brik-builder' ) ) ) ) ),
			'show_image'        => Fields::field( 'toggle', __( 'Featured image', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'image_size'        => Fields::field( 'select', __( 'Image size', 'brik-builder' ), 'content', array( 'default' => 'medium_large', 'show_if' => array( 'show_image' => true ), 'options' => Fields::opts( array( 'medium' => __( 'Medium', 'brik-builder' ), 'medium_large' => __( 'Medium large', 'brik-builder' ), 'large' => __( 'Large', 'brik-builder' ), 'full' => __( 'Full', 'brik-builder' ) ) ) ) ),
			'aspect_ratio'      => Fields::field( 'select', __( 'Image aspect ratio', 'brik-builder' ), 'content', array( 'responsive' => true, 'show_if' => array( 'show_image' => true ), 'options' => Fields::opts( array( '' => __( 'Layout default', 'brik-builder' ), '16/9' => '16:9', '3/2' => '3:2', '4/3' => '4:3', '1/1' => '1:1', '3/4' => '3:4', 'auto' => __( 'Original', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP . ' .brik-post-media', 'aspect-ratio' ) ) ),
			'show_category'     => Fields::field( 'toggle', __( 'Category badge', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'title_level'       => Fields::field( 'select', __( 'Title tag', 'brik-builder' ), 'content', array( 'default' => 'h3', 'options' => Fields::opts( array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ) ) ) ),
			'show_excerpt'      => Fields::field( 'toggle', __( 'Excerpt', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'excerpt_length'    => Fields::field( 'number', __( 'Excerpt length (words)', 'brik-builder' ), 'content', array( 'default' => 20, 'min' => 1, 'max' => 100, 'show_if' => array( 'show_excerpt' => true ) ) ),
			'show_author'       => Fields::field( 'toggle', __( 'Author', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'show_date'         => Fields::field( 'toggle', __( 'Date', 'brik-builder' ), 'content', array( 'default' => true ) ),
			'show_reading_time' => Fields::field( 'toggle', __( 'Reading time', 'brik-builder' ), 'content', array( 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'show_read_more'    => Fields::field( 'toggle', __( 'Read more link', 'brik-builder' ), 'content', array( 'default' => true, 'show_if' => array( 'layout' => array( 'grid', 'list', 'masonry' ) ) ) ),
			'read_more_text'    => Fields::field( 'text', __( 'Read more text', 'brik-builder' ), 'content', array( 'default' => __( 'Read more', 'brik-builder' ), 'show_if' => array( 'show_read_more' => true ) ) ),

			'pagination'        => Fields::field( 'select', __( 'Pagination', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'default' => 'none', 'options' => Fields::opts( array( 'none' => __( 'None', 'brik-builder' ), 'numbered' => __( 'Page numbers', 'brik-builder' ), 'load_more' => __( 'Load more button', 'brik-builder' ) ) ) ) ),
			'load_more_text'    => Fields::field( 'text', __( 'Button text', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'default' => __( 'Load more', 'brik-builder' ), 'show_if' => array( 'pagination' => 'load_more' ) ) ),
			'show_filter'       => Fields::field( 'toggle', __( 'Filter bar', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'description' => __( 'Buttons that filter the visible posts by term, without reloading.', 'brik-builder' ) ) ),
			'filter_taxonomy'   => Fields::field( 'taxonomy', __( 'Filter by', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'default' => 'category', 'show_if' => array( 'show_filter' => true ) ) ),
			'filter_all'        => Fields::field( 'text', __( '"All" button text', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'default' => __( 'All', 'brik-builder' ), 'show_if' => array( 'show_filter' => true ) ) ),
			'filter_align'      => Fields::field( 'select', __( 'Filter alignment', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'responsive' => true, 'show_if' => array( 'show_filter' => true ), 'options' => Fields::opts( array( '' => __( 'Left', 'brik-builder' ), 'center' => __( 'Center', 'brik-builder' ), 'flex-end' => __( 'Right', 'brik-builder' ) ) ), 'css' => array( Fields::WRAP . ' .brik-posts-filter', 'justify-content' ) ) ),
			'empty_text'        => Fields::field( 'text', __( 'No posts message', 'brik-builder' ), 'pagination', array( 'group_label' => __( 'Pagination & filter', 'brik-builder' ), 'default' => __( 'No posts found.', 'brik-builder' ) ) ),
		),
		Fields::box( 'card', __( 'Cards', 'brik-builder' ), Fields::WRAP . ' .brik-post-card' ),
		Fields::typography( 'title', __( 'Titles', 'brik-builder' ), Fields::WRAP . ' .brik-post-title' ),
		Fields::typography( 'excerpt', __( 'Excerpts', 'brik-builder' ), Fields::WRAP . ' .brik-post-excerpt' ),
		Fields::typography( 'meta', __( 'Meta', 'brik-builder' ), Fields::WRAP . ' .brik-post-meta' ),
		Fields::typography( 'more', __( 'Read more', 'brik-builder' ), Fields::WRAP . ' .brik-post-more' )
	),
	'css'         => static function ( $a, $wrap ) {
		$out   = '';
		$rules = array(
			'desktop' => '%s',
			'tablet'  => '@media (max-width:' . Brik\Style::TABLET . 'px){%s}',
			'mobile'  => '@media (max-width:' . Brik\Style::MOBILE . 'px){%s}',
		);
		$vars  = array(
			'desktop' => '--brik-posts-cols',
			'tablet'  => '--brik-posts-cols-t',
			'mobile'  => '--brik-posts-cols-m',
		);
		foreach ( $rules as $state => $rule ) {
			$v = (int) Brik\Style::raw_value( $a, 'columns', $state );
			if ( $v > 0 ) {
				$out .= sprintf( $rule, $wrap . '{' . $vars[ $state ] . ':' . min( 6, $v ) . '}' );
			}
		}
		return $out;
	},
	'render'      => static function ( $a, $ctx ) use ( $brik_posts_card ) {
		$layout     = in_array( $a['layout'], array( 'grid', 'list', 'masonry', 'minimal' ), true ) ? $a['layout'] : 'grid';
		$pagination = in_array( $a['pagination'], array( 'numbered', 'load_more' ), true ) ? $a['pagination'] : '';
		$level      = in_array( $a['title_level'], array( 'h2', 'h3', 'h4', 'p' ), true ) ? $a['title_level'] : 'h3';
		$var        = 'brik_page_' . $ctx->id;
		$main       = ! empty( $a['use_main_query'] ) && ! $ctx->canvas && ( is_home() || is_archive() || is_search() );

		if ( $main ) {
			global $wp_query;
			$query   = $wp_query;
			$current = max( 1, (int) get_query_var( 'paged' ) );
			$total   = (int) $wp_query->max_num_pages;
			$url     = static function ( $n ) {
				return get_pagenum_link( $n );
			};
		} else {
			$ppp     = min( 50, max( 1, (int) ( '' !== $a['posts_per_page'] ? $a['posts_per_page'] : 6 ) ) );
			$offset  = max( 0, (int) $a['offset'] );
			$current = $pagination && isset( $_GET[ $var ] ) ? max( 1, absint( $_GET[ $var ] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
			$type    = $a['post_type'] && post_type_exists( $a['post_type'] ) ? $a['post_type'] : 'post';
			$orderby = in_array( $a['orderby'], array( 'date', 'modified', 'title', 'comment_count', 'menu_order', 'rand' ), true ) ? $a['orderby'] : 'date';
			$args    = array(
				'post_type'           => $type,
				'post_status'         => 'publish',
				'posts_per_page'      => $ppp,
				'offset'              => $offset + ( $current - 1 ) * $ppp,
				'orderby'             => $orderby,
				'order'               => 'ASC' === $a['order'] ? 'ASC' : 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => ! $pagination,
			);
			if ( $a['taxonomy'] && taxonomy_exists( $a['taxonomy'] ) && '' !== trim( (string) $a['terms'] ) ) {
				$terms = array_filter( array_map( 'trim', explode( ',', wp_strip_all_tags( (string) $a['terms'] ) ) ) );
				$ids   = array_filter( $terms, 'is_numeric' );
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => $a['taxonomy'],
						'field'    => count( $ids ) === count( $terms ) ? 'term_id' : 'slug',
						'terms'    => count( $ids ) === count( $terms ) ? array_map( 'intval', $ids ) : array_map( 'sanitize_title', $terms ),
					),
				);
			}
			if ( ! empty( $a['exclude_current'] ) ) {
				$self = brik_site_post( $ctx );
				if ( $self ) {
					$args['post__not_in'] = array( $self->ID ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams
				}
			}
			$query = new WP_Query( apply_filters( 'brik/posts_query_args', $args, $a, $ctx ) );
			$total = $pagination ? (int) ceil( max( 0, $query->found_posts - $offset ) / $ppp ) : 1;
			$url   = static function ( $n ) use ( $var, $ctx ) {
				if ( $ctx->canvas ) {
					return '#';
				}
				return 1 === $n ? remove_query_arg( $var ) : add_query_arg( $var, $n );
			};
		}

		if ( ! $query->posts ) {
			$text = '' !== trim( (string) $a['empty_text'] ) ? $a['empty_text'] : __( 'No posts found.', 'brik-builder' );
			return '<div class="brik-posts-empty flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center text-muted-foreground">' . brik_icon( 'newspaper', 'size-8 opacity-50' ) . '<p' . $ctx->inline( 'empty_text' ) . '>' . brik_inline( $text ) . '</p></div>';
		}

		$filter_tax = ! empty( $a['show_filter'] ) && $a['filter_taxonomy'] && taxonomy_exists( $a['filter_taxonomy'] ) ? $a['filter_taxonomy'] : '';
		$found      = array();
		$items      = '';
		foreach ( $query->posts as $p ) {
			$p     = get_post( $p );
			$attr  = '';
			if ( $filter_tax ) {
				$slugs = array();
				$terms = get_the_terms( $p, $filter_tax );
				foreach ( $terms && ! is_wp_error( $terms ) ? $terms : array() as $term ) {
					$slugs[]              = $term->slug;
					$found[ $term->slug ] = $term->name;
				}
				$attr = ' data-terms="' . esc_attr( implode( ' ', $slugs ) ) . '"';
			}
			$items .= brik_site_with_post(
				$p,
				static function ( $post ) use ( $brik_posts_card, $a, $layout, $level, $attr ) {
					return $brik_posts_card( $post, $a, $layout, $level, $attr );
				}
			);
		}

		$html = '';
		if ( $filter_tax && $found ) {
			asort( $found );
			$all   = '' !== trim( (string) $a['filter_all'] ) ? $a['filter_all'] : __( 'All', 'brik-builder' );
			$html .= '<div class="brik-posts-filter flex flex-wrap gap-2" role="group" aria-label="' . esc_attr__( 'Filter posts', 'brik-builder' ) . '">';
			$html .= '<button type="button" class="brik-filter-btn" data-brik-filter="" aria-pressed="true">' . brik_inline( $all ) . '</button>';
			foreach ( $found as $slug => $name ) {
				$html .= '<button type="button" class="brik-filter-btn" data-brik-filter="' . esc_attr( $slug ) . '" aria-pressed="false">' . esc_html( $name ) . '</button>';
			}
			$html .= '</div>';
		}

		$html .= '<div class="brik-posts-items brik-posts-items--' . esc_attr( $layout ) . '">' . $items . '</div>';

		if ( 'numbered' === $pagination ) {
			$html .= brik_site_pagination( $current, $total, $url );
		} elseif ( 'load_more' === $pagination && $current < $total ) {
			$text  = '' !== trim( (string) $a['load_more_text'] ) ? $a['load_more_text'] : __( 'Load more', 'brik-builder' );
			$html .= '<div class="brik-posts-more flex justify-center"><a class="' . esc_attr( brik_button_class( 'outline', 'lg' ) ) . '" href="' . esc_url( $url( $current + 1 ) ) . '" data-brik-loadmore><span' . $ctx->inline( 'load_more_text' ) . '>' . brik_inline( $text ) . '</span></a></div>';
		}

		return '<div class="brik-posts flex flex-col gap-10" data-brik-posts="' . esc_attr( $ctx->id ) . '">' . $html . '</div>';
	},
);
