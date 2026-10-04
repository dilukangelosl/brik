<?php
/**
 * Empty results.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="no-results empty-state">
	<div class="empty-state-icon"><?php brik_theme_the_icon( 'search' ); ?></div>
	<h2 class="empty-state-title"><?php esc_html_e( 'Nothing found', 'brikwp' ); ?></h2>

	<?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>
		<p class="empty-state-text">
			<?php
			printf(
				/* translators: %s: link to the new post screen. */
				esc_html__( 'Ready to publish your first post? %s', 'brikwp' ),
				'<a href="' . esc_url( admin_url( 'post-new.php' ) ) . '">' . esc_html__( 'Get started here', 'brikwp' ) . '</a>'
			);
			?>
		</p>
	<?php elseif ( is_search() ) : ?>
		<p class="empty-state-text"><?php esc_html_e( 'No results matched your search. Try different keywords.', 'brikwp' ); ?></p>
	<?php else : ?>
		<p class="empty-state-text"><?php esc_html_e( 'There is nothing here yet. Try searching instead.', 'brikwp' ); ?></p>
		<?php get_search_form(); ?>
	<?php endif; ?>
</section>
