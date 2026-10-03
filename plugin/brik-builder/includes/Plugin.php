<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot() {
		load_plugin_textdomain( 'brik-builder', false, dirname( plugin_basename( BRIK_FILE ) ) . '/languages' );

		add_action( 'init', array( __CLASS__, 'register_meta' ) );

		Content\Content::init();
		ThemeBuilder::init();
		Library::init();
		Frontend::init();
		Builder::init();
		Rest::init();
		Forms::init();
		Mcp::init();
		Admin::init();

		do_action( 'brik/loaded' );
	}

	public static function activate() {
		ThemeBuilder::register_post_type();
		Library::register_post_type();
		flush_rewrite_rules();

		if ( false === get_option( Settings::OPTION ) ) {
			add_option( Settings::OPTION, Settings::defaults() );
		}
	}

	public static function register_meta() {
		foreach ( self::post_types() as $type ) {
			register_post_meta(
				$type,
				Data::META_ENABLED,
				array(
					'type'          => 'boolean',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => static function ( $allowed, $key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Post types the builder can be used on.
	 */
	public static function post_types() {
		$enabled = Settings::get( 'post_types' );
		$types   = array_merge( (array) $enabled, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ) );
		return array_values( array_unique( apply_filters( 'brik/post_types', $types ) ) );
	}

	public static function supports( $post ) {
		$post = get_post( $post );
		return $post && in_array( $post->post_type, self::post_types(), true );
	}
}
