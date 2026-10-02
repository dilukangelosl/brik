<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * The visual builder: the full-screen editor app and the canvas (live page preview).
 */
final class Builder {

	public static function init() {
		add_action( 'post_action_brik', array( __CLASS__, 'app' ) );
		add_action( 'template_redirect', array( __CLASS__, 'canvas_setup' ), 1 );
	}

	public static function url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=brik' );
	}

	public static function canvas_url( $post_id ) {
		$post = get_post( $post_id );
		if ( in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return add_query_arg(
				array(
					'brik_canvas' => 1,
					'brik_post'   => $post->ID,
				),
				home_url( '/' )
			);
		}
		$url = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );
		return add_query_arg( 'brik_canvas', 1, $url );
	}

	public static function is_canvas() {
		static $is = null;
		if ( null === $is ) {
			$is = isset( $_GET['brik_canvas'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		return $is;
	}

	public static function canvas_post_id() {
		return isset( $_GET['brik_post'] ) ? absint( $_GET['brik_post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	/**
	 * Canvas for a template or library item (not a regular page).
	 */
	public static function is_canvas_template() {
		$id = self::canvas_post_id();
		return self::is_canvas() && $id && current_user_can( 'edit_post', $id );
	}

	public static function canvas_setup() {
		if ( ! self::is_canvas() ) {
			return;
		}
		$id = self::is_canvas_template() ? self::canvas_post_id() : get_queried_object_id();
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this item.', 'brik' ), 403 );
		}

		show_admin_bar( false );
		nocache_headers();
		add_filter( 'body_class', static function ( $c ) {
			$c[] = 'brik-canvas-mode';
			return $c;
		} );
		add_action(
			'wp_enqueue_scripts',
			static function () {
				Frontend::enqueue();
				wp_enqueue_style( 'brik-canvas', BRIK_URL . 'assets/build/canvas.css', array( 'brik' ), Frontend::ver( 'assets/build/canvas.css' ) );
			},
			20
		);
	}

	/**
	 * Print the builder app as its own document (no admin chrome).
	 */
	public static function app( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
			wp_die( esc_html__( 'You are not allowed to edit this item.', 'brik' ), 403 );
		}
		if ( ! Plugin::supports( $post ) ) {
			wp_die( esc_html__( 'Brik is not enabled for this post type. Enable it under Brik → Settings.', 'brik' ) );
		}

		if ( 'auto-draft' === $post->post_status ) {
			wp_update_post(
				array(
					'ID'          => $post->ID,
					'post_status' => 'draft',
					'post_title'  => $post->post_title ? $post->post_title : __( 'Untitled', 'brik' ),
				)
			);
			$post = get_post( $post->ID );
		}

		wp_enqueue_media( array( 'post' => $post->ID ) );
		wp_enqueue_style( 'brik-builder', BRIK_URL . 'assets/build/builder.css', array( 'wp-components' ), Frontend::ver( 'assets/build/builder.css' ) );
		wp_enqueue_script(
			'brik-builder',
			BRIK_URL . 'assets/build/builder.js',
			array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-hooks', 'media-editor' ),
			Frontend::ver( 'assets/build/builder.js' ),
			true
		);
		wp_set_script_translations( 'brik-builder', 'brik', BRIK_DIR . 'languages' );

		$type = get_post_type_object( $post->post_type );
		wp_localize_script(
			'brik-builder',
			'brikBuilder',
			array(
				'post'       => Rest::post_payload( $post ),
				'canvasUrl'  => self::canvas_url( $post->ID ),
				'exitUrl'    => ThemeBuilder::is_template( $post->ID ) ? admin_url( 'admin.php?page=brik-theme-builder' ) : get_edit_post_link( $post->ID, 'raw' ),
				'viewUrl'    => get_permalink( $post ),
				'adminUrl'   => admin_url(),
				'iconsUrl'   => BRIK_URL . 'resources/icons.json',
				'brandsUrl'  => BRIK_URL . 'resources/brands.json',
				'tagsUrl'    => BRIK_URL . 'resources/icon-tags.json',
				'canPublish' => current_user_can( $type->cap->publish_posts ),
				'canManage'  => current_user_can( 'edit_theme_options' ),
				'typeLabel'  => $type->labels->singular_name,
				'version'    => BRIK_VERSION,
			)
		);

		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( sprintf( /* translators: %s: post title */ __( 'Brik: %s', 'brik' ), $post->post_title ) ); ?></title>
	<?php
	wp_print_styles();
	wp_print_head_scripts();
	?>
</head>
<body class="brik-app-body">
	<div id="brik-app"><div class="brik-boot"><?php esc_html_e( 'Loading builder…', 'brik' ); ?></div></div>
	<?php
	wp_print_footer_scripts();
	wp_print_media_templates();
	?>
</body>
</html>
		<?php
		exit;
	}
}
