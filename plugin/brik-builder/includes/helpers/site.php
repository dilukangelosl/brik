<?php
/**
 * Helpers for the site and post modules (theme builder parts, post loops). Menus live in navigation.php.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a post id belongs to a theme builder template or a library item.
 */
function brik_site_is_layout( $post_id ) {
	return $post_id && in_array( get_post_type( $post_id ), array( Brik\ThemeBuilder::POST_TYPE, Brik\Library::POST_TYPE ), true );
}

/**
 * Latest published post, used as sample data while a template is edited.
 */
function brik_site_sample_post() {
	static $sample = null;
	if ( null === $sample ) {
		$args = array(
			'numberposts'      => 1,
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'suppress_filters' => false,
		);
		// Prefer a post with a featured image so image modules have something to show.
		$posts  = get_posts( array_merge( $args, array( 'meta_key' => '_thumbnail_id' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$posts  = $posts ? $posts : get_posts( $args );
		$sample = $posts ? $posts[0] : false;
	}
	return $sample ? $sample : null;
}

/**
 * Whether the module shows sample data: a template or library item open in the builder.
 */
function brik_site_is_preview( Brik\Context $ctx ) {
	return $ctx->canvas && brik_site_is_layout( $ctx->post_id );
}

/**
 * Whether a template open in the builder is meant for listings (archives, blog, search)
 * rather than single posts, judging by its display conditions.
 */
function brik_site_preview_is_listing( Brik\Context $ctx ) {
	if ( ! brik_site_is_preview( $ctx ) || 'body' !== Brik\ThemeBuilder::area( $ctx->post_id ) ) {
		return false;
	}
	$listing = false;
	foreach ( Brik\ThemeBuilder::conditions( $ctx->post_id ) as $rule ) {
		if ( 'include' !== $rule['type'] ) {
			continue;
		}
		if ( in_array( $rule['rule'], array( 'singular', 'post', 'in_term', 'front_page', '404' ), true ) ) {
			return false;
		}
		$listing = $listing || in_array( $rule['rule'], array( 'blog', 'archive', 'term', 'author', 'date', 'search' ), true );
	}
	return $listing;
}

/**
 * The post a post_* module describes.
 *
 * Templates describe the queried post (or the loop post), regular pages describe themselves,
 * and templates open in the builder borrow the latest post so the preview looks real.
 */
function brik_site_post( Brik\Context $ctx ) {
	if ( brik_site_is_layout( $ctx->post_id ) ) {
		if ( $ctx->canvas ) {
			return brik_site_sample_post();
		}
		if ( in_the_loop() ) {
			return get_post();
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			return $post instanceof WP_Post ? $post : null;
		}
		return null;
	}
	$post = get_post( $ctx->post_id ? $ctx->post_id : null );
	return $post instanceof WP_Post ? $post : null;
}

/**
 * Whether the request is an archive-like listing rendered through a template.
 */
function brik_site_is_listing( Brik\Context $ctx ) {
	return ! $ctx->canvas && brik_site_is_layout( $ctx->post_id ) && ! in_the_loop() && ( is_archive() || is_home() || is_search() || is_404() );
}

/**
 * Run a callback with the global post set to $post, so template tags and filters see it.
 */
function brik_site_with_post( $target, callable $callback ) {
	global $post;
	$previous = $post;
	$post     = get_post( $target ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$result = $callback( $post );
	$post   = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	if ( $previous ) {
		setup_postdata( $previous );
	}
	return $result;
}

/**
 * Estimated reading time in minutes.
 */
function brik_site_reading_time( $post, $wpm = 220 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 1;
	}
	$words = count( preg_split( '/\s+/u', brik_site_plain_text( $post->post_content ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) ceil( $words / max( 60, (int) $wpm ) ) );
}

/**
 * Readable text from HTML. Tags become spaces so rendered layouts don't run words together.
 */
function brik_site_plain_text( $html ) {
	$html = preg_replace( '@<(script|style|svg)[^>]*?>.*?</\1>@si', ' ', strip_shortcodes( (string) $html ) );
	$text = wp_strip_all_tags( str_replace( '<', ' <', $html ) );
	return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) );
}

/**
 * Excerpt trimmed to a word count, falling back to the content.
 */
function brik_site_excerpt( $post, $words = 25 ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
	$text = brik_site_plain_text( excerpt_remove_blocks( $text ) );
	return $words > 0 ? wp_trim_words( $text, (int) $words, '…' ) : $text;
}

/**
 * shadcn badge classes.
 */
function brik_site_badge_class( $variant = 'secondary', $extra = '' ) {
	$variants = array(
		'default'   => 'border-transparent bg-primary text-primary-foreground hover:bg-primary/90',
		'secondary' => 'border-transparent bg-secondary text-secondary-foreground hover:bg-secondary/80',
		'outline'   => 'border-border text-foreground hover:bg-accent hover:text-accent-foreground',
	);
	return brik_cls(
		'brik-badge inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap transition-colors [&>svg]:size-3',
		isset( $variants[ $variant ] ) ? $variants[ $variant ] : $variants['secondary'],
		$extra
	);
}

/**
 * Term links for a post: badges, or plain comma separated links.
 */
function brik_site_terms( $post, $taxonomy, $style = 'secondary', $limit = 0 ) {
	$terms = get_the_terms( $post, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	if ( $limit > 0 ) {
		$terms = array_slice( $terms, 0, $limit );
	}
	$out = array();
	foreach ( $terms as $term ) {
		$url = get_term_link( $term );
		$url = is_wp_error( $url ) ? '#' : $url;
		if ( 'plain' === $style ) {
			$out[] = '<a class="brik-term transition-colors hover:text-foreground" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
		} else {
			$out[] = '<a class="' . esc_attr( brik_site_badge_class( $style, 'brik-term' ) ) . '" href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
		}
	}
	return 'plain' === $style ? implode( ', ', $out ) : '<span class="inline-flex flex-wrap gap-1.5">' . implode( '', $out ) . '</span>';
}

/**
 * Avatar image for a user.
 */
function brik_site_avatar( $user_id, $size = 40, $class = 'size-6' ) {
	$url = get_avatar_url( $user_id, array( 'size' => (int) $size * 2 ) );
	if ( ! $url ) {
		return '';
	}
	return '<img class="' . esc_attr( brik_cls( 'brik-avatar shrink-0 rounded-full bg-muted object-cover', $class ) ) . '" src="' . esc_url( $url ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async">';
}

/* -------------------------------------------------------------------------
 * Archives and breadcrumbs.
 * ---------------------------------------------------------------------- */

/**
 * Archive title, optionally without the "Category:" style prefix.
 */
function brik_site_archive_title( $prefix = false ) {
	if ( is_search() ) {
		/* translators: %s: search query */
		return sprintf( __( 'Search results for “%s”', 'brik-builder' ), get_search_query() );
	}
	if ( is_404() ) {
		return __( 'Page not found', 'brik-builder' );
	}
	if ( is_home() ) {
		$page = (int) get_option( 'page_for_posts' );
		return $page ? get_the_title( $page ) : __( 'Latest posts', 'brik-builder' );
	}
	if ( ! $prefix ) {
		add_filter( 'get_the_archive_title_prefix', '__return_empty_string', 99 );
	}
	$title = wp_strip_all_tags( get_the_archive_title() );
	if ( ! $prefix ) {
		remove_filter( 'get_the_archive_title_prefix', '__return_empty_string', 99 );
	}
	return $title;
}

/**
 * Short label naming the kind of listing ("Category", "Tag", "Author" …).
 */
function brik_site_archive_kind() {
	if ( is_search() ) {
		return __( 'Search', 'brik-builder' );
	}
	if ( is_category() ) {
		return __( 'Category', 'brik-builder' );
	}
	if ( is_tag() ) {
		return __( 'Tag', 'brik-builder' );
	}
	if ( is_author() ) {
		return __( 'Author', 'brik-builder' );
	}
	if ( is_date() ) {
		return __( 'Archive', 'brik-builder' );
	}
	if ( is_post_type_archive() ) {
		return __( 'Archive', 'brik-builder' );
	}
	if ( is_tax() ) {
		$tax = get_taxonomy( get_queried_object()->taxonomy );
		return $tax ? $tax->labels->singular_name : '';
	}
	if ( is_home() ) {
		return __( 'Blog', 'brik-builder' );
	}
	if ( is_404() ) {
		return __( 'Error 404', 'brik-builder' );
	}
	return '';
}

/**
 * Primary term of a post: Yoast/Rank Math primary category if set, else the first term.
 */
function brik_site_primary_term( $post, $taxonomy = 'category' ) {
	foreach ( array( '_yoast_wpseo_primary_' . $taxonomy, 'rank_math_primary_' . $taxonomy ) as $key ) {
		$id = (int) get_post_meta( $post->ID, $key, true );
		if ( $id && has_term( $id, $taxonomy, $post ) ) {
			$term = get_term( $id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term;
			}
		}
	}
	$terms = get_the_terms( $post, $taxonomy );
	return $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
}

/**
 * Term with its ancestors, top-most first, as trail entries.
 */
function brik_site_term_trail( $term ) {
	$trail = array();
	foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) ) as $id ) {
		$parent = get_term( $id, $term->taxonomy );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$trail[] = array( $parent->name, get_term_link( $parent ) );
		}
	}
	$trail[] = array( $term->name, get_term_link( $term ) );
	return $trail;
}

/**
 * Breadcrumb trail (without Home) for a single post.
 */
function brik_site_post_trail( WP_Post $post ) {
	$trail = array();
	if ( 'page' === $post->post_type ) {
		foreach ( array_reverse( get_post_ancestors( $post ) ) as $id ) {
			$trail[] = array( get_the_title( $id ), get_permalink( $id ) );
		}
	} elseif ( 'attachment' === $post->post_type && $post->post_parent ) {
		$trail[] = array( get_the_title( $post->post_parent ), get_permalink( $post->post_parent ) );
	} else {
		$type = get_post_type_object( $post->post_type );
		if ( 'post' !== $post->post_type && $type && $type->has_archive ) {
			$trail[] = array( $type->labels->name, get_post_type_archive_link( $post->post_type ) );
		}
		$taxonomy = 'post' === $post->post_type ? 'category' : '';
		if ( ! $taxonomy ) {
			foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
				if ( $tax->hierarchical && $tax->public ) {
					$taxonomy = $tax->name;
					break;
				}
			}
		}
		$term = $taxonomy ? brik_site_primary_term( $post, $taxonomy ) : null;
		if ( $term ) {
			$trail = array_merge( $trail, brik_site_term_trail( $term ) );
		}
	}
	$trail[] = array( get_the_title( $post ), get_permalink( $post ) );
	return $trail;
}

/**
 * Breadcrumb trail (without Home) for the current request.
 */
function brik_site_request_trail() {
	$trail = array();
	if ( is_front_page() ) {
		return $trail;
	}
	if ( is_home() ) {
		$page    = (int) get_option( 'page_for_posts' );
		$trail[] = array( $page ? get_the_title( $page ) : __( 'Blog', 'brik-builder' ), $page ? get_permalink( $page ) : home_url( '/' ) );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$trail = brik_site_post_trail( $post );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term instanceof WP_Term ) {
			$trail = brik_site_term_trail( $term );
		}
	} elseif ( is_post_type_archive() ) {
		$type    = get_queried_object();
		$trail[] = array( $type && isset( $type->labels ) ? $type->labels->name : post_type_archive_title( '', false ), get_post_type_archive_link( get_query_var( 'post_type' ) ) );
	} elseif ( is_author() ) {
		$author  = get_queried_object();
		$trail[] = array( $author ? $author->display_name : '', $author ? get_author_posts_url( $author->ID ) : '' );
	} elseif ( is_date() ) {
		$year    = get_query_var( 'year' );
		$trail[] = array( $year, get_year_link( $year ) );
		if ( is_month() || is_day() ) {
			$month   = get_query_var( 'monthnum' );
			$trail[] = array( date_i18n( 'F', mktime( 0, 0, 0, (int) $month, 1 ) ), get_month_link( $year, $month ) );
		}
		if ( is_day() ) {
			$trail[] = array( get_query_var( 'day' ), '' );
		}
	} elseif ( is_search() ) {
		/* translators: %s: search query */
		$trail[] = array( sprintf( __( 'Search results for “%s”', 'brik-builder' ), get_search_query() ), '' );
	} elseif ( is_404() ) {
		$trail[] = array( __( 'Page not found', 'brik-builder' ), '' );
	}

	$paged = (int) get_query_var( 'paged' );
	if ( $paged > 1 && ! is_singular() ) {
		/* translators: %d: page number */
		$trail[] = array( sprintf( __( 'Page %d', 'brik-builder' ), $paged ), '' );
	}
	return $trail;
}

/* -------------------------------------------------------------------------
 * Pagination.
 * ---------------------------------------------------------------------- */

/**
 * Page numbers to show around the current page, with null for gaps.
 */
function brik_site_page_range( $current, $total ) {
	$pages = array();
	foreach ( range( 1, $total ) as $n ) {
		if ( 1 === $n || $total === $n || abs( $n - $current ) <= 1 ) {
			$pages[] = $n;
		} elseif ( end( $pages ) !== null ) {
			$pages[] = null;
		}
	}
	return $pages;
}

/**
 * shadcn pagination. $url is a callback returning the link for a page number.
 */
function brik_site_pagination( $current, $total, callable $url ) {
	$current = max( 1, (int) $current );
	$total   = (int) $total;
	if ( $total < 2 ) {
		return '';
	}
	$items = '';
	if ( $current > 1 ) {
		$items .= '<li><a class="' . esc_attr( brik_button_class( 'ghost', 'default', 'brik-page-link gap-1 px-2.5 sm:pl-2.5' ) ) . '" href="' . esc_url( $url( $current - 1 ) ) . '" rel="prev" aria-label="' . esc_attr__( 'Go to previous page', 'brik-builder' ) . '">' . brik_icon( 'chevron-left' ) . '<span class="hidden sm:block">' . esc_html__( 'Previous', 'brik-builder' ) . '</span></a></li>';
	}
	foreach ( brik_site_page_range( $current, $total ) as $n ) {
		if ( null === $n ) {
			$items .= '<li><span class="flex size-9 items-center justify-center" aria-hidden="true">' . brik_icon( 'ellipsis' ) . '</span></li>';
		} elseif ( $n === $current ) {
			$items .= '<li><a class="' . esc_attr( brik_button_class( 'outline', 'icon', 'brik-page-link is-current' ) ) . '" href="' . esc_url( $url( $n ) ) . '" aria-current="page">' . (int) $n . '</a></li>';
		} else {
			$items .= '<li><a class="' . esc_attr( brik_button_class( 'ghost', 'icon', 'brik-page-link' ) ) . '" href="' . esc_url( $url( $n ) ) . '">' . (int) $n . '</a></li>';
		}
	}
	if ( $current < $total ) {
		$items .= '<li><a class="' . esc_attr( brik_button_class( 'ghost', 'default', 'brik-page-link gap-1 px-2.5 sm:pr-2.5' ) ) . '" href="' . esc_url( $url( $current + 1 ) ) . '" rel="next" aria-label="' . esc_attr__( 'Go to next page', 'brik-builder' ) . '"><span class="hidden sm:block">' . esc_html__( 'Next', 'brik-builder' ) . '</span>' . brik_icon( 'chevron-right' ) . '</a></li>';
	}
	return '<nav class="brik-pagination mx-auto flex w-full justify-center" aria-label="' . esc_attr__( 'Pagination', 'brik-builder' ) . '"><ul class="flex flex-row flex-wrap items-center gap-1">' . $items . '</ul></nav>';
}

/**
 * Paragraphs that stand in for post content in the builder.
 */
function brik_site_sample_content() {
	return '<p>' . esc_html__( 'This is where the post content appears. When a visitor opens a post, its text, images and blocks are shown here using the typography of this template.', 'brik-builder' ) . '</p>'
		. '<h2>' . esc_html__( 'A section heading', 'brik-builder' ) . '</h2>'
		. '<p>' . esc_html__( 'Good writing is easy to scan. Short paragraphs, clear headings and the occasional list make long articles feel lighter and help readers find what they came for.', 'brik-builder' ) . '</p>'
		. '<ul><li>' . esc_html__( 'Lists keep related points together', 'brik-builder' ) . '</li><li>' . esc_html__( 'Links look like', 'brik-builder' ) . ' <a href="#">' . esc_html__( 'this example', 'brik-builder' ) . '</a></li><li>' . esc_html__( 'Bold text adds', 'brik-builder' ) . ' <strong>' . esc_html__( 'emphasis', 'brik-builder' ) . '</strong></li></ul>'
		. '<blockquote><p>' . esc_html__( 'Quotes stand out from the surrounding text so a key idea is hard to miss.', 'brik-builder' ) . '</p></blockquote>'
		. '<p>' . esc_html__( 'Use the Design tab to adjust fonts, sizes and colors for the text, headings and links inside the content.', 'brik-builder' ) . '</p>';
}

/**
 * Whether an image field value points at an image.
 */
function brik_site_has_image( $image ) {
	if ( is_string( $image ) ) {
		return '' !== trim( $image );
	}
	return is_array( $image ) && ( ! empty( $image['id'] ) || ! empty( $image['url'] ) );
}

/**
 * Meta items for a post, in the order given.
 *
 * Keys: categories, author, date, modified, reading, comments, tags.
 * Options: icons (bool), avatar (bool), terms (badge variant or "plain"), date_format.
 */
function brik_site_meta_parts( WP_Post $post, array $keys, array $opt = array() ) {
	$opt  = wp_parse_args(
		$opt,
		array(
			'icons'       => false,
			'avatar'      => false,
			'terms'       => 'secondary',
			'date_format' => '',
		)
	);
	$icon = static function ( $name ) use ( $opt ) {
		return $opt['icons'] ? brik_icon( $name, 'brik-meta-icon size-3.5 shrink-0 opacity-70' ) : '';
	};
	$item = static function ( $html, $key ) {
		return '<span class="brik-meta-item brik-meta-' . esc_attr( $key ) . ' inline-flex items-center gap-1.5">' . $html . '</span>';
	};

	$parts = array();
	foreach ( $keys as $key ) {
		switch ( $key ) {
			case 'categories':
				$taxonomy = 'post' === $post->post_type ? 'category' : '';
				if ( ! $taxonomy ) {
					foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $tax ) {
						if ( $tax->hierarchical && $tax->public ) {
							$taxonomy = $tax->name;
							break;
						}
					}
				}
				$terms = $taxonomy ? brik_site_terms( $post, $taxonomy, $opt['terms'] ) : '';
				if ( '' !== $terms ) {
					$parts[ $key ] = $item( ( 'plain' === $opt['terms'] ? $icon( 'folder' ) : '' ) . $terms, $key );
				}
				break;
			case 'tags':
				$terms = brik_site_terms( $post, 'post_tag', 'plain' === $opt['terms'] ? 'plain' : 'outline' );
				if ( '' !== $terms ) {
					$parts[ $key ] = $item( ( 'plain' === $opt['terms'] ? $icon( 'tag' ) : '' ) . $terms, $key );
				}
				break;
			case 'author':
				$author = (int) $post->post_author;
				$name   = get_the_author_meta( 'display_name', $author );
				if ( '' !== $name ) {
					$lead          = $opt['avatar'] ? brik_site_avatar( $author, 24, 'size-6' ) : $icon( 'user' );
					$parts[ $key ] = $item( $lead . '<a class="brik-meta-link font-medium text-foreground transition-colors hover:underline underline-offset-4" href="' . esc_url( get_author_posts_url( $author ) ) . '">' . esc_html( $name ) . '</a>', $key );
				}
				break;
			case 'date':
				$parts[ $key ] = $item( $icon( 'calendar' ) . '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( get_the_date( $opt['date_format'], $post ) ) . '</time>', $key );
				break;
			case 'modified':
				/* translators: %s: date */
				$label         = sprintf( __( 'Updated %s', 'brik-builder' ), '<time datetime="' . esc_attr( get_the_modified_date( 'c', $post ) ) . '">' . esc_html( get_the_modified_date( $opt['date_format'], $post ) ) . '</time>' );
				$parts[ $key ] = $item( $icon( 'calendar-clock' ) . $label, $key );
				break;
			case 'reading':
				$minutes = brik_site_reading_time( $post );
				/* translators: %d: minutes */
				$parts[ $key ] = $item( $icon( 'clock' ) . esc_html( sprintf( _n( '%d min read', '%d min read', $minutes, 'brik-builder' ), $minutes ) ), $key );
				break;
			case 'comments':
				$count = (int) get_comments_number( $post );
				/* translators: %s: number of comments */
				$label         = $count ? sprintf( _n( '%s comment', '%s comments', $count, 'brik-builder' ), number_format_i18n( $count ) ) : __( 'No comments', 'brik-builder' );
				$parts[ $key ] = $item( $icon( 'message-circle' ) . '<a class="brik-meta-link transition-colors hover:text-foreground" href="' . esc_url( get_comments_link( $post ) ) . '">' . esc_html( $label ) . '</a>', $key );
				break;
		}
	}
	return $parts;
}

/**
 * Separator markup between meta items.
 */
function brik_site_meta_sep( $style ) {
	$map = array(
		'dot'   => '<span class="brik-meta-sep" aria-hidden="true">·</span>',
		'slash' => '<span class="brik-meta-sep opacity-50" aria-hidden="true">/</span>',
		'pipe'  => '<span class="brik-meta-sep h-3.5 w-px bg-border" aria-hidden="true"></span>',
	);
	return isset( $map[ $style ] ) ? $map[ $style ] : '';
}

/**
 * Initials from a name, for avatar placeholders.
 */
function brik_site_initials( $name ) {
	$words = preg_split( '/\s+/', trim( wp_strip_all_tags( (string) $name ) ) );
	$out   = '';
	foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
		$out .= function_exists( 'mb_substr' ) ? mb_substr( $word, 0, 1 ) : substr( $word, 0, 1 );
	}
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $out ) : strtoupper( $out );
}
