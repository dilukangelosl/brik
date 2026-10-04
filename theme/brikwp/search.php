<?php
/**
 * Search results.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;
?>
<main id="primary" class="site-main wrap section">
	<header class="page-header page-header--search">
		<p class="page-eyebrow"><?php esc_html_e( 'Search', 'brikwp' ); ?></p>
		<h1 class="page-title">
			<?php
			/* translators: %s: search query. */
			printf( esc_html__( 'Results for “%s”', 'brikwp' ), '<span class="search-term">' . esc_html( get_search_query() ) . '</span>' );
			?>
		</h1>
		<?php if ( have_posts() ) : ?>
			<p class="page-description">
				<?php
				$brik_theme_found = (int) $wp_query->found_posts;
				/* translators: %s: number of results. */
				echo esc_html( sprintf( _n( '%s result found.', '%s results found.', $brik_theme_found, 'brikwp' ), number_format_i18n( $brik_theme_found ) ) );
				?>
			</p>
		<?php endif; ?>
		<?php get_search_form(); ?>
	</header>

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
