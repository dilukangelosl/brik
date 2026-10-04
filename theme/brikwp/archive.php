<?php
/**
 * Archives: categories, tags, authors, dates and custom post types.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main wrap section">
	<?php brik_theme_archive_header(); ?>

	<?php if ( have_posts() ) : ?>
		<div class="post-grid">
			<?php
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'card' );
			endwhile;
			?>
		</div>
		<?php brik_theme_pagination(); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/content', 'none' ); ?>
	<?php endif; ?>
</main>
<?php
get_footer();
