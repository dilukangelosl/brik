<?php
namespace Brik\Perf;

use Brik\Settings;
use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Performance: optimized per-page assets, font and image handling, the page analyzer and
 * the cleaner, exposed over REST, MCP and in the builder.
 */
final class Perf {

	const VERSION_OPTION = 'brik_perf_version';

	public static function init() {
		LocalFonts::init();
		Media::init();
		Assets::init();
		Api::init();

		// Cached stylesheets are keyed by content, so these only keep the folder tidy.
		add_action( 'brik/saved', array( __CLASS__, 'saved' ) );
		add_action( 'deleted_post', array( __CLASS__, 'saved' ) );
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'purge' ) );
		add_action( 'add_option_' . Settings::OPTION, array( __CLASS__, 'purge' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );

		add_action( 'brik/settings_form', array( __CLASS__, 'settings_form' ) );
		add_action( 'admin_post_brik_save_settings', array( __CLASS__, 'save_settings' ), 5 );
	}

	public static function saved( $post_id ) {
		if ( ThemeBuilder::is_template( $post_id ) ) {
			PageCss::purge();
		} else {
			PageCss::purge( (string) (int) $post_id );
		}
	}

	public static function purge() {
		PageCss::purge();
	}

	public static function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) !== BRIK_VERSION ) {
			PageCss::purge();
			update_option( self::VERSION_OPTION, BRIK_VERSION, false );
		}
	}

	/* ---------------------------------------------------------------------
	 * Settings page section.
	 * ------------------------------------------------------------------- */

	public static function settings_form() {
		$formats   = Media::supported();
		$format    = (string) Settings::get( 'image_format' );
		$fonts     = LocalFonts::mode();
		$dir       = PageCss::dir();
		$files     = $dir ? (array) glob( $dir['path'] . '/*.css' ) : array();
		$cache_kb  = 0;
		foreach ( $files as $file ) {
			$cache_kb += (int) filesize( $file );
		}
		?>
		<input type="hidden" name="brik_perf_form" value="1">
		<fieldset class="brik-field">
			<legend class="brik-field-label"><?php esc_html_e( 'Performance', 'brik-builder' ); ?></legend>
			<p class="brik-muted"><?php esc_html_e( 'Pages built with Brik load only what they use. The builder always works with the complete stylesheet.', 'brik-builder' ); ?></p>
		</fieldset>

		<?php
		self::toggle(
			'perf_assets',
			__( 'Optimized assets', 'brik-builder' ),
			$dir
				/* translators: 1: number of files, 2: size */
				? sprintf( __( 'One minified stylesheet per page with only the rules it uses, and scripts only for the elements on the page. Cached in uploads/brik/css (%1$d files, %2$s).', 'brik-builder' ), count( $files ), size_format( $cache_kb ) )
				: __( 'The uploads folder is not writable, so pages fall back to the full stylesheet.', 'brik-builder' )
		);
		self::toggle( 'perf_critical', __( 'Inline critical CSS', 'brik-builder' ), __( 'Inline the styles for the first screen (header and first sections) and load the rest without blocking rendering.', 'brik-builder' ) );
		self::toggle( 'perf_lazy', __( 'Image loading priorities', 'brik-builder' ), __( 'Lazy-load images and embeds below the fold, load the first image of the page with high priority, and add missing image dimensions.', 'brik-builder' ) );
		self::toggle( 'perf_picture', __( 'Serve AVIF/WebP copies', 'brik-builder' ), __( 'When an AVIF or WebP version of an uploaded JPEG/PNG exists next to it, offer it through a <picture> element.', 'brik-builder' ) );
		?>

		<div class="brik-field">
			<label class="brik-field-label" for="brik-fonts-mode"><?php esc_html_e( 'Font loading', 'brik-builder' ); ?></label>
			<p class="brik-muted"><?php esc_html_e( 'Self-hosted downloads the fonts you use once and serves them from your site, so visitors never contact Google.', 'brik-builder' ); ?></p>
			<select id="brik-fonts-mode" name="fonts_mode">
				<option value="local" <?php selected( Settings::get( 'fonts_mode' ), 'local' ); ?>><?php esc_html_e( 'Self-hosted (recommended)', 'brik-builder' ); ?></option>
				<option value="google" <?php selected( Settings::get( 'fonts_mode' ), 'google' ); ?>><?php esc_html_e( 'Google Fonts', 'brik-builder' ); ?></option>
				<option value="system" <?php selected( Settings::get( 'fonts_mode' ), 'system' ); ?>><?php esc_html_e( 'System fonts only', 'brik-builder' ); ?></option>
			</select>
			<?php if ( 'off' === $fonts ) : ?>
				<p class="brik-muted"><?php esc_html_e( 'Google Fonts is switched off above, so no web fonts load.', 'brik-builder' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="brik-field">
			<label class="brik-field-label" for="brik-image-format"><?php esc_html_e( 'Convert new uploads', 'brik-builder' ); ?></label>
			<p class="brik-muted"><?php esc_html_e( 'Save JPEG and PNG uploads (and their generated sizes) in a modern format. Existing images are not changed.', 'brik-builder' ); ?></p>
			<select id="brik-image-format" name="image_format">
				<option value="" <?php selected( $format, '' ); ?>><?php esc_html_e( 'Keep the original format', 'brik-builder' ); ?></option>
				<option value="webp" <?php selected( $format, 'webp' ); ?> <?php disabled( ! in_array( 'webp', $formats, true ) ); ?>><?php echo esc_html( in_array( 'webp', $formats, true ) ? __( 'WebP', 'brik-builder' ) : __( 'WebP (not supported by this server)', 'brik-builder' ) ); ?></option>
				<option value="avif" <?php selected( $format, 'avif' ); ?> <?php disabled( ! in_array( 'avif', $formats, true ) ); ?>><?php echo esc_html( in_array( 'avif', $formats, true ) ? __( 'AVIF', 'brik-builder' ) : __( 'AVIF (not supported by this server)', 'brik-builder' ) ); ?></option>
			</select>
		</div>
		<?php
	}

	private static function toggle( $key, $label, $help ) {
		?>
		<div class="brik-field">
			<label class="brik-toggle-row">
				<span>
					<span class="brik-field-label"><?php echo esc_html( $label ); ?></span>
					<span class="brik-muted"><?php echo esc_html( $help ); ?></span>
				</span>
				<span class="brik-switch">
					<input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( (bool) Settings::get( $key ) ); ?>>
					<span class="brik-switch-track" aria-hidden="true"></span>
				</span>
			</label>
		</div>
		<?php
	}

	/**
	 * Saves the Performance fields; runs before the main settings handler, which redirects.
	 */
	public static function save_settings() {
		if ( empty( $_POST['brik_perf_form'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- verified below.
			return;
		}
		check_admin_referer( 'brik_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		Settings::update(
			array(
				'perf_assets'   => ! empty( $_POST['perf_assets'] ),
				'perf_critical' => ! empty( $_POST['perf_critical'] ),
				'perf_lazy'     => ! empty( $_POST['perf_lazy'] ),
				'perf_picture'  => ! empty( $_POST['perf_picture'] ),
				'fonts_mode'    => isset( $_POST['fonts_mode'] ) ? sanitize_key( wp_unslash( $_POST['fonts_mode'] ) ) : 'local',
				'image_format'  => isset( $_POST['image_format'] ) ? sanitize_key( wp_unslash( $_POST['image_format'] ) ) : '',
			)
		);
	}
}
