<?php
/**
 * Menu markup.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add a disclosure button after primary menu items that have a submenu. Submenus also open
 * on hover and focus-within, but the button makes them reachable on touch screens and
 * gives screen reader users an explicit expanded state.
 */
function brik_theme_submenu_toggle( $output, $item, $depth, $args ) {
	if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $output;
	}
	if ( ! in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
		return $output;
	}
	$label = sprintf(
		/* translators: %s: menu item title. */
		__( 'Show submenu for %s', 'brikwp' ),
		wp_strip_all_tags( $item->title )
	);
	return $output . sprintf(
		'<button type="button" class="submenu-toggle" aria-expanded="false" aria-label="%1$s">%2$s</button>',
		esc_attr( $label ),
		brik_theme_icon( 'chevron-down' )
	);
}
add_filter( 'walker_nav_menu_start_el', 'brik_theme_submenu_toggle', 10, 4 );

/**
 * Primary menu, or a page list fallback so a fresh install still has navigation.
 */
function brik_theme_primary_menu() {
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'menu_id'        => 'primary-menu',
			'menu_class'     => 'menu primary-menu',
			'container'      => false,
			'fallback_cb'    => 'brik_theme_menu_fallback',
		)
	);
}

/**
 * Fallback for an unassigned primary menu: top-level pages only.
 */
function brik_theme_menu_fallback() {
	$pages = wp_list_pages(
		array(
			'depth'    => 1,
			'title_li' => '',
			'echo'     => false,
			'number'   => 6,
		)
	);
	if ( $pages ) {
		echo '<ul id="primary-menu" class="menu primary-menu">' . wp_kses_post( $pages ) . '</ul>';
	}
}
