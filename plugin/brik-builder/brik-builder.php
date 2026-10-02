<?php
/**
 * Plugin Name:       Brik Builder
 * Plugin URI:        https://github.com/dilukangelo/brik
 * Description:       Visual drag & drop site builder with a shadcn-inspired component library, theme builder and a built-in MCP server.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Diluk Angelo
 * Author URI:        https://github.com/dilukangelo
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       brik
 * Domain Path:       /languages
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

define( 'BRIK_VERSION', '1.0.0' );
define( 'BRIK_FILE', __FILE__ );
define( 'BRIK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BRIK_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'Brik\\' ) ) {
			return;
		}
		$file = BRIK_DIR . 'includes/' . str_replace( '\\', '/', substr( $class, 5 ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

require BRIK_DIR . 'includes/helpers.php';
foreach ( glob( BRIK_DIR . 'includes/helpers/*.php' ) as $brik_helper ) {
	require $brik_helper;
}

register_activation_hook( __FILE__, array( 'Brik\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Brik\\Plugin', 'boot' ) );
