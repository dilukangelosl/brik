<?php
/**
 * Post card for the blog, archives and search results.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;

// The newest post on the first blog page gets a wide, horizontal card.
$brik_theme_featured = is_home() && ! is_paged() && 0 === $wp_query->current_post && $wp_query->post_count > 2 && has_post_thumbnail();
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( array( 'card', 'post-card', $brik_theme_featured ? 'post-card--featured' : '' ) ); ?>>
	<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?>
		<a class="post-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
			<?php the_post_thumbnail( $brik_theme_featured ? 'post-thumbnail' : 'brik-card', array( 'alt' => '' ) ); ?>
		</a>
	<?php elseif ( 'post' === get_post_type() ) : ?>
		<div class="post-card-media post-card-media--placeholder" aria-hidden="true">
			<span><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( get_the_title(), 0, 1 ) : substr( get_the_title(), 0, 1 ) ); ?></span>
		</div>
	<?php endif; ?>

	<div class="post-card-body">
		<?php if ( is_sticky() && is_home() && ! is_paged() ) : ?>
			<span class="badge badge--default post-card-sticky"><?php brik_theme_the_icon( 'pin' ); ?><?php esc_html_e( 'Featured', 'brik' ); ?></span>
		<?php endif; ?>
		<?php brik_theme_category_badges( 2 ); ?>

		<?php the_title( sprintf( '<h2 class="post-card-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>

		<?php if ( '' !== trim( (string) get_the_excerpt() ) ) : ?>
			<div class="post-card-excerpt"><?php the_excerpt(); ?></div>
		<?php endif; ?>

		<?php if ( 'post' === get_post_type() ) : ?>
			<div class="post-card-meta entry-meta">
				<?php get_template_part( 'template-parts/entry', 'meta', array( 'context' => 'card' ) ); ?>
			</div>
		<?php else : ?>
			<p class="post-card-type"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></p>
		<?php endif; ?>
	</div>
</article>
