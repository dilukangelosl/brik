<?php
/**
 * Search form.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

$brik_theme_field_id = wp_unique_id( 'search-field-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $brik_theme_field_id ); ?>"><?php esc_html_e( 'Search for:', 'brik' ); ?></label>
	<div class="search-form-field">
		<?php brik_theme_the_icon( 'search' ); ?>
		<input type="search" id="<?php echo esc_attr( $brik_theme_field_id ); ?>" class="input search-field" placeholder="<?php esc_attr_e( 'Search…', 'brik' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	</div>
	<button type="submit" class="btn search-submit"><?php echo esc_html_x( 'Search', 'submit button', 'brik' ); ?></button>
</form>
