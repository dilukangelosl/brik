<?php
/**
 * Single posts, pages and attachments.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( brik_theme_is_built() ) :
	// Brik layouts bring their own sections, widths and spacing.
	?>
	<main id="primary" class="site-main site-main--brik">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>
	<?php
else :
	?>
	<div class="wrap section<?php echo brik_theme_has_sidebar() ? ' layout-sidebar' : ''; ?>">
		<main id="primary" class="site-main">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'singular' );

				if ( is_singular( 'post' ) ) {
					brik_theme_post_navigation();
				}

				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
			endwhile;
			?>
		</main>
		<?php if ( brik_theme_has_sidebar() ) : ?>
			<aside id="secondary" class="widget-area sidebar" aria-label="<?php esc_attr_e( 'Sidebar', 'brik' ); ?>">
				<?php dynamic_sidebar( 'sidebar' ); ?>
			</aside>
		<?php endif; ?>
	</div>
	<?php
endif;

get_footer();
