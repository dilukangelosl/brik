<?php
/**
 * Full post or page.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry' ); ?>>
	<?php get_template_part( 'template-parts/entry', 'header' ); ?>

	<?php if ( has_post_thumbnail() && ! post_password_required() ) : ?>
		<figure class="entry-thumbnail">
			<?php the_post_thumbnail( 'post-thumbnail', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?>
			<?php if ( wp_get_attachment_caption( get_post_thumbnail_id() ) ) : ?>
				<figcaption class="wp-caption-text"><?php echo wp_kses_post( wp_get_attachment_caption( get_post_thumbnail_id() ) ); ?></figcaption>
			<?php endif; ?>
		</figure>
	<?php endif; ?>

	<div class="entry-content">
		<?php
		the_content(
			sprintf(
				/* translators: %s: post title, only visible to screen readers. */
				esc_html__( 'Continue reading %s', 'brik' ),
				'<span class="screen-reader-text">' . get_the_title() . '</span>'
			)
		);

		wp_link_pages(
			array(
				'before'      => '<nav class="page-links" aria-label="' . esc_attr__( 'Pages', 'brik' ) . '"><span class="page-links-title">' . esc_html__( 'Pages:', 'brik' ) . '</span>',
				'after'       => '</nav>',
				'link_before' => '<span class="page-number">',
				'link_after'  => '</span>',
			)
		);
		?>
	</div>

	<?php if ( is_singular( 'post' ) ) : ?>
		<footer class="entry-footer">
			<?php brik_theme_entry_tags(); ?>
			<?php brik_theme_author_box(); ?>
		</footer>
	<?php endif; ?>

	<?php edit_post_link( __( 'Edit', 'brik' ), '<p class="edit-link">', '</p>' ); ?>
</article>
