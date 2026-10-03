<?php
/**
 * Site footer and document end.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;
?>
</div><!-- #content -->

<?php
if ( ! brik_theme_location( 'footer' ) ) :
	$brik_theme_columns = array_filter(
		array( 'footer-1', 'footer-2', 'footer-3' ),
		'is_active_sidebar'
	);
	?>
	<footer id="colophon" class="site-footer">
		<?php if ( $brik_theme_columns ) : ?>
			<div class="wrap footer-widgets footer-widgets--<?php echo count( $brik_theme_columns ); ?>">
				<?php foreach ( $brik_theme_columns as $brik_theme_column ) : ?>
					<div class="footer-widgets-column">
						<?php dynamic_sidebar( $brik_theme_column ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="wrap site-info">
			<p class="site-copyright"><?php echo brik_theme_copyright(); // phpcs:ignore WordPress.Security.EscapeOutput -- kses'd in brik_theme_copyright(). ?></p>
			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="footer-nav" aria-label="<?php esc_attr_e( 'Footer', 'brik' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer',
							'menu_class'     => 'menu footer-menu',
							'container'      => false,
							'depth'          => 1,
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</div>
	</footer>
	<?php
endif;
?>
</div><!-- #page -->

<?php wp_footer(); ?>
</body>
</html>
