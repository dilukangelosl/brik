<?php
/**
 * Template tags.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logo, or the site title as a link.
 *
 * @param bool $primary False for repeat copies (the mobile panel), which never use h1.
 */
function brik_theme_site_branding( $primary = true ) {
	echo '<div class="site-branding">';
	if ( has_custom_logo() ) {
		the_custom_logo();
	} else {
		$tag = $primary && is_front_page() && is_home() ? 'h1' : 'p';
		printf(
			'<%1$s class="site-title"><a href="%2$s" rel="home">%3$s</a></%1$s>',
			tag_escape( $tag ),
			esc_url( home_url( '/' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
	}
	echo '</div>';
}

/**
 * Estimated reading time in minutes.
 */
function brik_theme_reading_time( $post = null ) {
	$text  = wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post ) ) );
	$words = count( preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) );
	return max( 1, (int) round( $words / 220 ) );
}

/**
 * Publication date as a <time> element.
 */
function brik_theme_posted_on() {
	$time = sprintf(
		'<time class="entry-date published" datetime="%1$s">%2$s</time>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() )
	);
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
		$time .= sprintf(
			'<time class="updated screen-reader-text" datetime="%1$s">%2$s</time>',
			esc_attr( get_the_modified_date( DATE_W3C ) ),
			esc_html( get_the_modified_date() )
		);
	}
	return $time;
}

/**
 * Category badges for the current post.
 */
function brik_theme_category_badges( $limit = 0 ) {
	if ( 'post' !== get_post_type() ) {
		return;
	}
	$cats = get_the_category();
	if ( ! $cats ) {
		return;
	}
	if ( $limit ) {
		$cats = array_slice( $cats, 0, $limit );
	}
	echo '<div class="entry-badges">';
	foreach ( $cats as $cat ) {
		printf(
			'<a class="badge badge--secondary" href="%1$s">%2$s</a>',
			esc_url( get_category_link( $cat ) ),
			esc_html( $cat->name )
		);
	}
	echo '</div>';
}

/**
 * Byline: author, date, reading time and comment count.
 *
 * @param string $context 'card' keeps it to date and reading time.
 */
function brik_theme_entry_meta( $context = 'single' ) {
	$is_card = 'card' === $context;
	$items   = array();

	if ( ! $is_card && post_type_supports( get_post_type(), 'author' ) ) {
		$items[] = sprintf(
			'<span class="byline"><span class="screen-reader-text">%1$s </span><span class="author vcard"><a class="url fn n" href="%2$s">%3$s</a></span></span>',
			esc_html__( 'By', 'brikwp' ),
			esc_url( get_author_posts_url( (int) get_the_author_meta( 'ID' ) ) ),
			esc_html( get_the_author() )
		);
	}

	$items[] = '<span class="posted-on">' . brik_theme_icon( 'calendar' ) . brik_theme_posted_on() . '</span>';

	$minutes = brik_theme_reading_time();
	$items[] = '<span class="reading-time">' . brik_theme_icon( 'clock' ) . esc_html(
		/* translators: %d: minutes. */
		sprintf( _n( '%d min read', '%d min read', $minutes, 'brikwp' ), $minutes )
	) . '</span>';

	if ( ! $is_card && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
		$count   = (int) get_comments_number();
		$items[] = sprintf(
			'<span class="comments-link">%1$s<a href="%2$s">%3$s</a></span>',
			brik_theme_icon( 'message' ),
			esc_url( get_comments_link() ),
			esc_html(
				$count
					/* translators: %s: number of comments. */
					? sprintf( _n( '%s comment', '%s comments', $count, 'brikwp' ), number_format_i18n( $count ) )
					: __( 'Leave a comment', 'brikwp' )
			)
		);
	}

	$allowed = array_merge(
		brik_theme_icon_kses(),
		array(
			'span' => array( 'class' => true ),
			'a'    => array(
				'class' => true,
				'href'  => true,
			),
			'time' => array(
				'class'    => true,
				'datetime' => true,
			),
		)
	);

	if ( ! $is_card && post_type_supports( get_post_type(), 'author' ) ) {
		echo wp_kses_post( get_avatar( get_the_author_meta( 'ID' ), 32, '', '', array( 'class' => 'entry-meta-avatar' ) ) );
	}
	echo '<span class="entry-meta-items">' . wp_kses( implode( '<span class="sep" aria-hidden="true"></span>', $items ), $allowed ) . '</span>';
}

/**
 * Tag badges below a post.
 */
function brik_theme_entry_tags() {
	$tags = get_the_tags();
	if ( ! $tags || is_wp_error( $tags ) ) {
		return;
	}
	echo '<div class="entry-tags"><span class="screen-reader-text">' . esc_html__( 'Tags:', 'brikwp' ) . '</span>';
	foreach ( $tags as $tag ) {
		printf(
			'<a class="badge badge--outline" href="%1$s" rel="tag">#%2$s</a>',
			esc_url( get_tag_link( $tag ) ),
			esc_html( $tag->name )
		);
	}
	echo '</div>';
}

/**
 * Author card under single posts. Skipped when the author has no bio.
 */
function brik_theme_author_box() {
	$bio = get_the_author_meta( 'description' );
	if ( '' === trim( (string) $bio ) ) {
		return;
	}
	$id = (int) get_the_author_meta( 'ID' );
	?>
	<aside class="author-box card" aria-label="<?php esc_attr_e( 'About the author', 'brikwp' ); ?>">
		<?php echo get_avatar( $id, 56 ); ?>
		<div class="author-box-body">
			<p class="author-box-label"><?php esc_html_e( 'Written by', 'brikwp' ); ?></p>
			<p class="author-box-name"><a href="<?php echo esc_url( get_author_posts_url( $id ) ); ?>"><?php echo esc_html( get_the_author() ); ?></a></p>
			<p class="author-box-bio"><?php echo wp_kses_post( $bio ); ?></p>
		</div>
	</aside>
	<?php
}

/**
 * Previous/next post cards.
 */
function brik_theme_post_navigation() {
	the_post_navigation(
		array(
			'prev_text'          => '<span class="nav-label">' . brik_theme_icon( 'arrow-left' ) . esc_html__( 'Previous', 'brikwp' ) . '</span><span class="nav-title">%title</span>',
			'next_text'          => '<span class="nav-label">' . esc_html__( 'Next', 'brikwp' ) . brik_theme_icon( 'arrow-right' ) . '</span><span class="nav-title">%title</span>',
			'screen_reader_text' => __( 'Post navigation', 'brikwp' ),
		)
	);
}

/**
 * Numbered pagination for archives.
 */
function brik_theme_pagination() {
	the_posts_pagination(
		array(
			'mid_size'           => 1,
			'prev_text'          => brik_theme_icon( 'chevron-left' ) . '<span>' . esc_html__( 'Previous', 'brikwp' ) . '</span>',
			'next_text'          => '<span>' . esc_html__( 'Next', 'brikwp' ) . '</span>' . brik_theme_icon( 'chevron-right' ),
			'screen_reader_text' => __( 'Posts navigation', 'brikwp' ),
		)
	);
}

/**
 * Footer copyright line. Supports {year} and {site} placeholders.
 */
function brik_theme_copyright() {
	$default = '© {year} {site}';
	$text    = get_theme_mod( 'brik_theme_copyright', $default );
	if ( '' === trim( (string) $text ) ) {
		$text = $default;
	}
	$text = strtr(
		$text,
		array(
			'{year}' => wp_date( 'Y' ),
			'{site}' => get_bloginfo( 'name' ),
		)
	);
	return wp_kses( $text, brik_theme_copyright_kses() );
}

/**
 * Markup allowed in the copyright line.
 */
function brik_theme_copyright_kses() {
	return array(
		'a'      => array(
			'href'   => true,
			'rel'    => true,
			'target' => true,
		),
		'strong' => array(),
		'em'     => array(),
		'span'   => array( 'class' => true ),
	);
}

/**
 * Archive heading markup: title with a small eyebrow and the term description.
 */
function brik_theme_archive_header() {
	$eyebrow = '';
	$title   = get_the_archive_title();

	if ( is_category() ) {
		$eyebrow = __( 'Category', 'brikwp' );
		$title   = single_cat_title( '', false );
	} elseif ( is_tag() ) {
		$eyebrow = __( 'Tag', 'brikwp' );
		$title   = single_tag_title( '', false );
	} elseif ( is_author() ) {
		$eyebrow = __( 'Author', 'brikwp' );
		$title   = get_the_author();
	} elseif ( is_tax() ) {
		$tax     = get_taxonomy( get_queried_object()->taxonomy );
		$eyebrow = $tax ? $tax->labels->singular_name : '';
		$title   = single_term_title( '', false );
	} elseif ( is_post_type_archive() ) {
		$eyebrow = __( 'Archive', 'brikwp' );
		$title   = post_type_archive_title( '', false );
	} elseif ( is_date() ) {
		$eyebrow = __( 'Archive', 'brikwp' );
		$title   = is_year() ? get_the_date( _x( 'Y', 'yearly archives date format', 'brikwp' ) ) : ( is_month() ? get_the_date( _x( 'F Y', 'monthly archives date format', 'brikwp' ) ) : get_the_date() );
	}
	?>
	<header class="page-header">
		<?php if ( $eyebrow ) : ?>
			<p class="page-eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h1 class="page-title"><?php echo wp_kses_post( $title ); ?></h1>
		<?php the_archive_description( '<div class="page-description">', '</div>' ); ?>
	</header>
	<?php
}

/**
 * Single comment markup (callback for wp_list_comments()).
 */
function brik_theme_comment( $comment, $args, $depth ) {
	$tag = 'div' === $args['style'] ? 'div' : 'li';
	?>
	<<?php echo tag_escape( $tag ); ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( $comment->comment_parent ? 'comment--reply' : '', $comment ); ?>>
		<article class="comment-body">
			<div class="comment-avatar"><?php echo get_avatar( $comment, 40 ); ?></div>
			<div class="comment-main">
				<header class="comment-meta">
					<span class="comment-author vcard"><?php echo wp_kses_post( get_comment_author_link( $comment ) ); ?></span>
					<a class="comment-date" href="<?php echo esc_url( get_comment_link( $comment, $args ) ); ?>">
						<time datetime="<?php comment_time( 'c' ); ?>">
							<?php
							/* translators: 1: comment date, 2: comment time. */
							printf( esc_html__( '%1$s at %2$s', 'brikwp' ), esc_html( get_comment_date( '', $comment ) ), esc_html( get_comment_time() ) );
							?>
						</time>
					</a>
					<?php edit_comment_link( __( 'Edit', 'brikwp' ), '<span class="comment-edit">', '</span>' ); ?>
				</header>
				<?php if ( '0' === $comment->comment_approved ) : ?>
					<p class="comment-awaiting-moderation"><?php esc_html_e( 'Your comment is awaiting moderation.', 'brikwp' ); ?></p>
				<?php endif; ?>
				<div class="comment-content"><?php comment_text(); ?></div>
				<?php
				comment_reply_link(
					array_merge(
						$args,
						array(
							'depth'      => $depth,
							'max_depth'  => $args['max_depth'],
							'reply_text' => brik_theme_icon( 'corner-reply' ) . esc_html__( 'Reply', 'brikwp' ),
							'before'     => '<div class="comment-reply">',
							'after'      => '</div>',
						)
					)
				);
				?>
			</div>
		</article>
	<?php
	// The closing tag is printed by Walker_Comment::end_el().
}
