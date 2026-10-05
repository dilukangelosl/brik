<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * wp-admin integration: the Brik menu, "Edit with Brik" entry points and the form actions
 * behind the admin screens (rendered by AdminPages).
 */
final class Admin {

	const SLUG = 'brik';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'classic_button' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'block_editor_button' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 80 );

		foreach ( array( 'new', 'template_new', 'template_duplicate', 'template_delete', 'library_delete', 'library_export', 'library_import', 'save_settings' ) as $action ) {
			add_action( 'admin_post_brik_' . $action, array( __CLASS__, 'action_' . $action ) );
		}
	}

	/**
	 * A simple brick-wall mark, drawn in the menu's own gray so it matches core icons.
	 */
	private static function icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M1.00 1.40h6.00v2.20h-6.00zM1.00 4.40h6.00v2.20h-6.00zM1.00 7.40h18.00v2.20h-18.00zM1.00 10.40h6.00v2.20h-6.00zM13.00 10.40h6.00v2.20h-6.00zM1.00 13.40h6.00v2.20h-6.00zM13.00 13.40h6.00v2.20h-6.00zM1.00 16.40h18.00v2.20h-18.00z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function new_url( $post_type = 'page' ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=brik_new&post_type=' . rawurlencode( $post_type ) ), 'brik_new' );
	}

	public static function menu() {
		add_menu_page( __( 'Brik', 'brik-builder' ), __( 'Brik', 'brik-builder' ), 'edit_posts', self::SLUG, array( AdminPages::class, 'dashboard' ), self::icon(), 58 );
		add_submenu_page( self::SLUG, __( 'Brik Dashboard', 'brik-builder' ), __( 'Dashboard', 'brik-builder' ), 'edit_posts', self::SLUG, array( AdminPages::class, 'dashboard' ) );
		add_submenu_page( self::SLUG, __( 'Theme Builder', 'brik-builder' ), __( 'Theme Builder', 'brik-builder' ), 'edit_theme_options', 'brik-theme-builder', array( AdminPages::class, 'theme_builder' ) );
		add_submenu_page( self::SLUG, __( 'Library', 'brik-builder' ), __( 'Library', 'brik-builder' ), 'edit_pages', 'brik-library', array( AdminPages::class, 'library' ) );
		add_submenu_page( self::SLUG, __( 'Content', 'brik-builder' ), __( 'Content', 'brik-builder' ), 'manage_options', 'brik-content', array( AdminPages::class, 'content' ) );
		if ( Content\MetaBoxes::has_options_groups() ) {
			add_submenu_page( self::SLUG, __( 'Site options', 'brik-builder' ), __( 'Site options', 'brik-builder' ), Content\MetaBoxes::options_capability(), Content\MetaBoxes::OPTIONS, array( Content\MetaBoxes::class, 'options_page' ) );
		}
		if ( post_type_exists( Forms::POST_TYPE ) ) {
			add_submenu_page( self::SLUG, __( 'Submissions', 'brik-builder' ), __( 'Submissions', 'brik-builder' ), 'manage_options', 'edit.php?post_type=' . Forms::POST_TYPE );
		}
		add_submenu_page( self::SLUG, __( 'Brik Settings', 'brik-builder' ), __( 'Settings', 'brik-builder' ), 'manage_options', 'brik-settings', array( AdminPages::class, 'settings' ) );
		add_submenu_page( self::SLUG, __( 'Connect AI', 'brik-builder' ), __( 'Connect AI', 'brik-builder' ), 'edit_posts', 'brik-mcp', array( AdminPages::class, 'mcp' ) );

		if ( in_array( 'page', Plugin::post_types(), true ) ) {
			$type = get_post_type_object( 'page' );
			if ( $type && current_user_can( $type->cap->create_posts ) ) {
				// A URL slug without a callback is linked as-is by core.
				add_submenu_page( 'edit.php?post_type=page', __( 'Add New with Brik', 'brik-builder' ), __( 'Add New with Brik', 'brik-builder' ), $type->cap->create_posts, 'admin-post.php?action=brik_new&post_type=page&_wpnonce=' . wp_create_nonce( 'brik_new' ) );
			}
		}
	}

	/**
	 * Keep the Brik menu open on the submissions list, which core treats as its own screen.
	 */
	public static function parent_file( $file ) {
		$screen = get_current_screen();
		if ( $screen && post_type_exists( Forms::POST_TYPE ) && Forms::POST_TYPE === $screen->post_type ) {
			return self::SLUG;
		}
		return $file;
	}

	public static function submenu_file( $file ) {
		$screen = get_current_screen();
		if ( $screen && post_type_exists( Forms::POST_TYPE ) && Forms::POST_TYPE === $screen->post_type ) {
			return 'edit.php?post_type=' . Forms::POST_TYPE;
		}
		return $file;
	}

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'brik' ) ) {
			// Classic editor button and list tables only need the stylesheet.
			if ( $screen && in_array( $screen->base, array( 'post', 'edit' ), true ) && in_array( $screen->post_type, Plugin::post_types(), true ) ) {
				wp_enqueue_style( 'brik-admin', BRIK_URL . 'assets/admin/admin.css', array(), Frontend::ver( 'assets/admin/admin.css' ) );
			}
			return;
		}
		wp_enqueue_style( 'brik-admin', BRIK_URL . 'assets/admin/admin.css', array(), Frontend::ver( 'assets/admin/admin.css' ) );
		if ( false !== strpos( $screen->id, 'brik-content' ) ) {
			self::content_assets();
		}
		wp_enqueue_script( 'brik-admin', BRIK_URL . 'assets/admin/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), Frontend::ver( 'assets/admin/admin.js' ), true );
		wp_set_script_translations( 'brik-admin', 'brik-builder', BRIK_DIR . 'languages' );

		$user = wp_get_current_user();
		wp_localize_script(
			'brik-admin',
			'brikAdmin',
			array(
				'mcpUrl'    => Mcp::url(),
				'userLogin' => $user->user_login,
				'siteName'  => get_bloginfo( 'name' ),
				'rules'     => ThemeBuilder::rule_types(),
				'postTypes' => self::labels( get_post_types( array( 'public' => true ), 'objects' ) ),
				'taxonomies' => self::labels( get_taxonomies( array( 'public' => true ), 'objects' ) ),
				'i18n'      => array(
					'include'      => __( 'Include', 'brik-builder' ),
					'exclude'      => __( 'Exclude', 'brik-builder' ),
					'anyType'      => __( 'Any post type', 'brik-builder' ),
					'chooseTax'    => __( 'Choose a taxonomy', 'brik-builder' ),
					'search'       => __( 'Search to add…', 'brik-builder' ),
					'remove'       => __( 'Remove', 'brik-builder' ),
					'addRule'      => __( 'Add condition', 'brik-builder' ),
					'save'         => __( 'Save conditions', 'brik-builder' ),
					'cancel'       => __( 'Cancel', 'brik-builder' ),
					'saving'       => __( 'Saving…', 'brik-builder' ),
					'saved'        => __( 'Saved', 'brik-builder' ),
					'error'        => __( 'Something went wrong:', 'brik-builder' ),
					'renamePrompt' => __( 'Template name', 'brik-builder' ),
					'deleteAsk'    => __( 'Move this item to the trash?', 'brik-builder' ),
					'copied'       => __( 'Copied', 'brik-builder' ),
					'copy'         => __( 'Copy', 'brik-builder' ),
					'creating'     => __( 'Creating…', 'brik-builder' ),
					'noRules'      => __( 'No conditions: the template is not shown anywhere.', 'brik-builder' ),
					'published'    => __( 'Active', 'brik-builder' ),
					'draft'        => __( 'Draft', 'brik-builder' ),
				),
			)
		);
	}

	/**
	 * The content modelling app (Brik → Content).
	 */
	private static function content_assets() {
		wp_enqueue_media();
		wp_enqueue_style( 'brik-content', BRIK_URL . 'assets/build/content.css', array(), Frontend::ver( 'assets/build/content.css' ) );
		wp_enqueue_script( 'brik-content', BRIK_URL . 'assets/build/content.js', array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'media-views' ), Frontend::ver( 'assets/build/content.js' ), true );
		wp_set_script_translations( 'brik-content', 'brik-builder', BRIK_DIR . 'languages' );
		wp_localize_script(
			'brik-content',
			'brikContent',
			array(
				'restBase'   => esc_url_raw( rest_url( Rest::NS . '/content' ) ),
				'adminUrl'   => esc_url_raw( admin_url() ),
				'dashicons'  => self::dashicons(),
				'iconsUrl'   => esc_url_raw( BRIK_URL . 'resources/icons.json' ),
				'optionsUrl' => esc_url_raw( admin_url( 'admin.php?page=' . Content\MetaBoxes::OPTIONS ) ),
				'version'    => BRIK_VERSION,
			)
		);
	}

	/**
	 * Dashicon names, read from core's stylesheet so the list matches the installed version.
	 */
	public static function dashicons() {
		$key   = 'brik_dashicons_' . get_bloginfo( 'version' );
		$names = get_transient( $key );
		if ( is_array( $names ) ) {
			return $names;
		}
		$names = array();
		$file  = ABSPATH . WPINC . '/css/dashicons.css';
		if ( is_readable( $file ) && preg_match_all( '/\.dashicons-([a-z0-9-]+):before/', (string) file_get_contents( $file ), $m ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$names = array_values( array_unique( array_diff( $m[1], array( 'before', 'adminmenu' ) ) ) );
		}
		set_transient( $key, $names, WEEK_IN_SECONDS );
		return $names;
	}

	private static function labels( array $objects ) {
		$out = array();
		foreach ( $objects as $object ) {
			if ( 'attachment' === $object->name ) {
				continue;
			}
			$out[ $object->name ] = $object->labels->singular_name;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * "Edit with Brik" entry points.
	 * ------------------------------------------------------------------- */

	private static function can_build( $post ) {
		$post = get_post( $post );
		return $post && Plugin::supports( $post ) && current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status;
	}

	public static function row_actions( $actions, $post ) {
		if ( ! self::can_build( $post ) || in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return $actions;
		}
		$link = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( Builder::url( $post->ID ) ),
			/* translators: %s: post title */
			esc_attr( sprintf( __( 'Edit “%s” with Brik', 'brik-builder' ), _draft_or_post_title( $post ) ) ),
			esc_html__( 'Edit with Brik', 'brik-builder' )
		);
		// Right after "Edit".
		$out = array();
		foreach ( $actions as $key => $html ) {
			$out[ $key ] = $html;
			if ( 'edit' === $key ) {
				$out['brik'] = $link;
			}
		}
		if ( ! isset( $out['brik'] ) ) {
			$out['brik'] = $link;
		}
		return $out;
	}

	public static function post_states( $states, $post ) {
		if ( Data::enabled( $post->ID ) && Plugin::supports( $post ) ) {
			$states['brik'] = __( 'Brik', 'brik-builder' );
		}
		return $states;
	}

	public static function classic_button( $post ) {
		if ( ! self::can_build( $post ) || in_array( $post->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return;
		}
		?>
		<div class="brik-classic-cta">
			<a class="button button-primary button-hero brik-classic-button" href="<?php echo esc_url( Builder::url( $post->ID ) ); ?>">
				<?php esc_html_e( 'Edit with Brik', 'brik-builder' ); ?>
			</a>
			<?php if ( Data::enabled( $post->ID ) ) : ?>
				<p class="description"><?php esc_html_e( 'This content is built with Brik. Changes made here are replaced the next time it is saved in the builder.', 'brik-builder' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function block_editor_button() {
		$screen = get_current_screen();
		$post   = get_post();
		if ( ! $screen || ! $post || ! in_array( $screen->post_type, Plugin::post_types(), true ) || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		if ( in_array( $screen->post_type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return;
		}
		wp_enqueue_style( 'brik-admin', BRIK_URL . 'assets/admin/admin.css', array(), Frontend::ver( 'assets/admin/admin.css' ) );
		wp_enqueue_script( 'brik-editor', BRIK_URL . 'assets/admin/editor.js', array( 'wp-data', 'wp-dom-ready', 'wp-i18n' ), Frontend::ver( 'assets/admin/editor.js' ), true );
		wp_localize_script(
			'brik-editor',
			'brikEditor',
			array(
				'url'   => Builder::url( $post->ID ),
				'label' => __( 'Edit with Brik', 'brik-builder' ),
				'built' => Data::enabled( $post->ID ),
			)
		);
	}

	public static function admin_bar( \WP_Admin_Bar $bar ) {
		if ( is_admin() || ! is_singular() || Builder::is_canvas() ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! self::can_build( $id ) ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'brik-edit',
				'title' => '<span class="ab-icon dashicons dashicons-layout" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Edit with Brik', 'brik-builder' ) . '</span>',
				'href'  => Builder::url( $id ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * admin-post actions.
	 * ------------------------------------------------------------------- */

	private static function back( $page, array $args = array() ) {
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php?page=' . $page ) ) );
		exit;
	}

	private static function request_id() {
		return isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller.
	}

	/**
	 * Create a draft and open it in the builder.
	 */
	public static function action_new() {
		check_admin_referer( 'brik_new' );
		$type   = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'page';
		$object = get_post_type_object( $type );
		if ( ! $object || ! in_array( $type, Plugin::post_types(), true ) || in_array( $type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			wp_die( esc_html__( 'Brik is not enabled for this post type.', 'brik-builder' ), 400 );
		}
		if ( ! current_user_can( $object->cap->create_posts ) ) {
			wp_die( esc_html__( 'You are not allowed to create this content.', 'brik-builder' ), 403 );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => 'draft',
				/* translators: %s: post type name */
				'post_title'  => sprintf( __( 'New %s', 'brik-builder' ), $object->labels->singular_name ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_post_meta( $id, Data::META_ENABLED, 1 );
		wp_safe_redirect( Builder::url( $id ) );
		exit;
	}

	public static function action_template_new() {
		check_admin_referer( 'brik_template_new' );
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage templates.', 'brik-builder' ), 403 );
		}
		$area = isset( $_POST['area'] ) ? sanitize_key( wp_unslash( $_POST['area'] ) ) : '';
		if ( ! isset( ThemeBuilder::areas()[ $area ] ) ) {
			$area = 'header';
		}
		// New templates start as drafts so an empty header doesn't replace the live one.
		$id = wp_insert_post(
			array(
				'post_type'   => ThemeBuilder::POST_TYPE,
				'post_status' => 'draft',
				/* translators: %s: area name, e.g. Header */
				'post_title'  => sprintf( __( 'New %s', 'brik-builder' ), ThemeBuilder::areas()[ $area ] ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_post_meta( $id, ThemeBuilder::META_AREA, $area );
		ThemeBuilder::save_conditions(
			$id,
			array(
				array(
					'type' => 'include',
					'rule' => 'entire_site',
				),
			)
		);
		Data::save( $id, array() );
		wp_safe_redirect( Builder::url( $id ) );
		exit;
	}

	public static function action_template_duplicate() {
		$id = self::request_id();
		check_admin_referer( 'brik_template_duplicate_' . $id );
		$post = get_post( $id );
		if ( ! $post || ! ThemeBuilder::is_template( $id ) || ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to duplicate this template.', 'brik-builder' ), 403 );
		}
		$copy = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => ThemeBuilder::POST_TYPE,
					'post_status' => 'draft',
					/* translators: %s: template title */
					'post_title'  => sprintf( __( '%s (copy)', 'brik-builder' ), $post->post_title ),
					'menu_order'  => $post->menu_order,
				)
			),
			true
		);
		if ( is_wp_error( $copy ) ) {
			wp_die( esc_html( $copy->get_error_message() ) );
		}
		update_post_meta( $copy, ThemeBuilder::META_AREA, ThemeBuilder::area( $id ) );
		ThemeBuilder::save_conditions( $copy, ThemeBuilder::conditions( $id ) );
		Data::save( $copy, self::strip_ids( Data::get( $id ) ), Data::page_settings( $id ) );
		self::back( 'brik-theme-builder', array( 'brik_notice' => 'duplicated' ) );
	}

	private static function strip_ids( array $nodes ) {
		foreach ( $nodes as &$node ) {
			unset( $node['id'] );
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = self::strip_ids( $node['children'] );
			}
		}
		return $nodes;
	}

	public static function action_template_delete() {
		$id = self::request_id();
		check_admin_referer( 'brik_template_delete_' . $id );
		if ( ! ThemeBuilder::is_template( $id ) || ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to delete this template.', 'brik-builder' ), 403 );
		}
		wp_trash_post( $id );
		self::back( 'brik-theme-builder', array( 'brik_notice' => 'deleted' ) );
	}

	public static function action_library_delete() {
		$id = self::request_id();
		check_admin_referer( 'brik_library_delete_' . $id );
		if ( ! Library::item( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'You are not allowed to delete this item.', 'brik-builder' ), 403 );
		}
		wp_trash_post( $id );
		self::back( 'brik-library', array( 'brik_notice' => 'deleted' ) );
	}

	/**
	 * Download one library item (?id=) or all of them as JSON.
	 */
	public static function action_library_export() {
		$id = self::request_id();
		check_admin_referer( 'brik_library_export_' . $id );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to export library items.', 'brik-builder' ), 403 );
		}
		$items = $id ? array_filter( array( Library::item( $id ) ) ) : Library::items();
		$out   = array();
		foreach ( $items as $item ) {
			if ( ! current_user_can( 'edit_post', $item['id'] ) ) {
				continue;
			}
			$out[] = array(
				'title'  => $item['title'],
				'kind'   => $item['kind'],
				'global' => $item['global'],
				'tree'   => Data::get( $item['id'] ),
			);
		}
		if ( ! $out ) {
			wp_die( esc_html__( 'Nothing to export.', 'brik-builder' ), 404 );
		}
		$name = $id ? sanitize_file_name( 'brik-' . sanitize_title( $out[0]['title'] ) . '.json' ) : 'brik-library-' . gmdate( 'Y-m-d' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode(
			array(
				'brik'  => BRIK_VERSION,
				'type'  => 'library',
				'items' => $out,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	/**
	 * Import a Brik library export, a single item, or a bundled layout file.
	 */
	public static function action_library_import() {
		check_admin_referer( 'brik_library_import' );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to import library items.', 'brik-builder' ), 403 );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- the file is only read and parsed as JSON.
		$file = isset( $_FILES['brik_file'] ) ? $_FILES['brik_file'] : null;
		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::back( 'brik-library', array( 'brik_notice' => 'import_failed' ) );
		}
		if ( $file['size'] > 5 * MB_IN_BYTES ) {
			self::back( 'brik-library', array( 'brik_notice' => 'import_failed' ) );
		}
		$data = json_decode( (string) file_get_contents( $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) ) {
			self::back( 'brik-library', array( 'brik_notice' => 'import_failed' ) );
		}
		$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array( $data );
		$count = 0;
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['tree'] ) || ! is_array( $item['tree'] ) ) {
				continue;
			}
			$id = Library::create(
				isset( $item['title'] ) ? (string) $item['title'] : __( 'Imported layout', 'brik-builder' ),
				isset( $item['kind'] ) ? sanitize_key( $item['kind'] ) : 'layout',
				self::strip_ids( $item['tree'] ),
				! empty( $item['global'] )
			);
			if ( ! is_wp_error( $id ) ) {
				++$count;
			}
		}
		self::back(
			'brik-library',
			array(
				'brik_notice' => $count ? 'imported' : 'import_failed',
				'count'       => $count,
			)
		);
	}

	public static function action_save_settings() {
		check_admin_referer( 'brik_save_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'brik-builder' ), 403 );
		}
		$types = isset( $_POST['post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['post_types'] ) ) : array();
		$types = array_values( array_intersect( $types, array_keys( AdminPages::buildable_types() ) ) );
		Settings::update(
			array(
				'post_types'   => $types,
				'google_fonts' => ! empty( $_POST['google_fonts'] ),
				'mcp_enabled'  => ! empty( $_POST['mcp_enabled'] ),
			)
		);
		self::back( 'brik-settings', array( 'brik_notice' => 'saved' ) );
	}
}
