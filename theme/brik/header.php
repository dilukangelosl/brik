<?php
/**
 * Document head and site header.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'brik' ); ?></a>

<div id="page" class="site">
<?php
if ( ! brik_theme_location( 'header' ) ) :
	?>
	<header id="masthead" class="site-header">
		<div class="wrap site-header-inner">
			<?php brik_theme_site_branding(); ?>

			<div class="site-header-actions">
				<nav id="site-navigation" class="site-nav" aria-label="<?php esc_attr_e( 'Primary', 'brik' ); ?>">
					<div class="site-nav-panel" id="site-nav-panel">
						<div class="site-nav-panel-head">
							<?php brik_theme_site_branding( false ); ?>
							<button type="button" class="icon-button menu-close" aria-label="<?php esc_attr_e( 'Close menu', 'brik' ); ?>">
								<?php brik_theme_the_icon( 'x' ); ?>
							</button>
						</div>
						<?php brik_theme_primary_menu(); ?>
					</div>
				</nav>

				<?php
				if ( function_exists( 'brik_theme_cart_link' ) ) {
					brik_theme_cart_link();
				}
				?>

				<?php if ( brik_theme_show_mode_toggle() ) : ?>
					<button type="button" class="icon-button mode-toggle" aria-label="<?php esc_attr_e( 'Toggle dark mode', 'brik' ); ?>" aria-pressed="false">
						<?php brik_theme_the_icon( 'sun' ); ?>
						<?php brik_theme_the_icon( 'moon' ); ?>
					</button>
				<?php endif; ?>

				<button type="button" class="icon-button menu-toggle" aria-controls="site-nav-panel" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open menu', 'brik' ); ?>">
					<?php brik_theme_the_icon( 'menu' ); ?>
				</button>
			</div>
		</div>
		<div class="site-nav-backdrop" hidden></div>
	</header>
	<?php
endif;
?>

<div id="content" class="site-content" tabindex="-1">
