<?php
/**
 * Body template wrapper. Keeps the theme (or Brik) header and footer.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header();

echo '<main id="brik-main" class="brik-main">';
if ( ! brik_location( 'body' ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
echo '</main>';

get_footer();
