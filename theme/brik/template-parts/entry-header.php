<?php
/**
 * Title block for single posts and pages.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="entry-header">
	<?php brik_theme_category_badges(); ?>
	<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	<?php if ( has_excerpt() && is_singular( 'post' ) ) : ?>
		<p class="entry-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
	<?php endif; ?>
	<?php if ( 'post' === get_post_type() ) : ?>
		<div class="entry-meta">
			<?php get_template_part( 'template-parts/entry', 'meta' ); ?>
		</div>
	<?php endif; ?>
</header>
