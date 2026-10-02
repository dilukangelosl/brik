<?php
/**
 * Full page output for block themes when Brik templates apply.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/header.php';

echo '<main id="brik-main" class="brik-main">';
if ( ! brik_location( 'body' ) ) {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
echo '</main>';

require __DIR__ . '/footer.php';
