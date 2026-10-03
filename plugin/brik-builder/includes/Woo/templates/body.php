<?php
/**
 * Body template wrapper for shop pages rendered by a Brik template.
 *
 * Same as the plugin's body template, plus the WooCommerce hooks that normally surround a
 * product (notices, structured data, before/after single product).
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

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

get_footer( 'shop' );
