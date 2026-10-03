<?php
/**
 * Full page output for block themes when a Brik template renders a shop page.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

require BRIK_DIR . 'templates/header.php';

echo '<main id="brik-main" class="brik-main brik-woo-main">';
Brik\Woo\Woo::before_body();
if ( ! brik_location( 'body' ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
Brik\Woo\Woo::after_body();
echo '</main>';

require BRIK_DIR . 'templates/footer.php';
