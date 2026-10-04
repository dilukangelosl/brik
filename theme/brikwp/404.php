<?php
/**
 * Not found.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main wrap section">
	<section class="error-404 empty-state">
		<p class="error-404-code" aria-hidden="true">404</p>
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'brikwp' ); ?></h1>
		<p class="empty-state-text"><?php esc_html_e( 'The page you are looking for doesn’t exist or has been moved. Try a search, or head back to the home page.', 'brikwp' ); ?></p>
		<?php get_search_form(); ?>
		<p class="empty-state-actions">
			<a class="btn btn--outline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php brik_theme_the_icon( 'arrow-left' ); ?>
				<?php esc_html_e( 'Back to home', 'brikwp' ); ?>
			</a>
		</p>
	</section>
</main>
<?php
get_footer();
