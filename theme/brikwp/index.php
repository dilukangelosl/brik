<?php
/**
 * Blog index and generic fallback.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main wrap section">
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="page-header">
			<h1 class="page-title"><?php single_post_title(); ?></h1>
			<?php
			$brik_theme_intro = get_post_field( 'post_excerpt', get_queried_object_id() );
			if ( $brik_theme_intro ) :
				?>
				<div class="page-description"><?php echo wp_kses_post( wpautop( $brik_theme_intro ) ); ?></div>
			<?php endif; ?>
		</header>
	<?php elseif ( is_home() ) : ?>
		<header class="page-header">
			<h2 class="page-title"><?php esc_html_e( 'Latest posts', 'brikwp' ); ?></h2>
			<?php if ( get_bloginfo( 'description' ) ) : ?>
				<div class="page-description"><p><?php bloginfo( 'description' ); ?></p></div>
			<?php endif; ?>
		</header>
	<?php elseif ( is_archive() ) : ?>
		<?php brik_theme_archive_header(); ?>
	<?php endif; ?>

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
