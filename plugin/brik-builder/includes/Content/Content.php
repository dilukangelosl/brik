<?php
namespace Brik\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Content modelling: custom post types, taxonomies and field groups. See docs/CONTENT.md.
 */
final class Content {

	public static function init() {
		Register::init();
		DynamicTags::init();
		RestContent::init();

		if ( is_admin() ) {
			MetaBoxes::init();
		}
		// Term, user and options screens save through admin-post / core screens, so these
		// hooks have to exist for every request that might process them.
		add_action( 'admin_post_brik_save_options', array( MetaBoxes::class, 'save_options' ) );
	}
}
