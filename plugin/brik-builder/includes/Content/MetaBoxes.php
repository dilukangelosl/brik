<?php
namespace Brik\Content;

use Brik\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Field group UI in wp-admin: post editor meta boxes (block and classic editor), term
 * screens, user profiles and the "Site options" page, plus saving and validation.
 *
 * Inputs are named brik_fields[{field key}] (sub fields: [{row}][{sub key}]) so groups that
 * reuse a field name for different objects never collide on one screen.
 */
final class MetaBoxes {

	const INPUT       = 'brik_fields';
	const NONCE       = 'brik_cf_nonce';
	const OPTIONS     = 'brik-options';
	const ERRORS_TTL  = 120;

	private static $nonce_printed = false;

	private static $enqueued = false;

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ), 10, 2 );
		add_action( 'edit_form_after_title', array( __CLASS__, 'after_title' ) );
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		add_action( 'admin_init', array( __CLASS__, 'term_hooks' ) );
		add_action( 'created_term', array( __CLASS__, 'save_term' ), 10, 3 );
		add_action( 'edited_term', array( __CLASS__, 'save_term' ), 10, 3 );

		add_action( 'show_user_profile', array( __CLASS__, 'user_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'user_fields' ) );
		add_action( 'user_new_form', array( __CLASS__, 'user_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user' ) );
		add_action( 'user_register', array( __CLASS__, 'save_user' ) );
	}

	/* ---------------------------------------------------------------------
	 * Location contexts.
	 * ------------------------------------------------------------------- */

	public static function post_context( \WP_Post $post ) {
		$types = array();
		if ( 'page' === $post->post_type ) {
			if ( (int) get_option( 'page_on_front' ) === $post->ID ) {
				$types[] = 'front_page';
			}
			if ( (int) get_option( 'page_for_posts' ) === $post->ID ) {
				$types[] = 'posts_page';
			}
		}
		$types[] = $post->post_parent ? 'child' : 'top_level';
		if ( is_post_type_hierarchical( $post->post_type ) && get_children(
			array(
				'post_parent' => $post->ID,
				'post_type'   => $post->post_type,
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		) ) {
			$types[] = 'parent';
		}
		$template = get_page_template_slug( $post );
		return array(
			'post_type'     => $post->post_type,
			'post_status'   => $post->post_status,
			'post_template' => $template ? $template : 'default',
			'page_type'     => $types,
		);
	}

	public static function user_context( $user ) {
		$roles = $user instanceof \WP_User && $user->exists() ? (array) $user->roles : array( get_option( 'default_role', 'subscriber' ) );
		return array( 'user_role' => array_values( $roles ) );
	}

	/**
	 * Active groups matching a context.
	 */
	public static function matching( array $ctx ) {
		$out = array();
		foreach ( Registry::groups( true ) as $group ) {
			if ( $group['fields'] && Registry::matches( $group, $ctx ) ) {
				$out[] = $group;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Post editor.
	 * ------------------------------------------------------------------- */

	private static function block_editor( $post ) {
		return function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post );
	}

	public static function add_meta_boxes( $post_type, $post = null ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		$block = self::block_editor( $post );
		foreach ( self::matching( self::post_context( $post ) ) as $group ) {
			if ( 'after_title' === $group['position'] && ! $block ) {
				continue;
			}
			$context  = 'side' === $group['position'] ? 'side' : 'normal';
			$priority = 'after_title' === $group['position'] ? 'high' : 'default';
			add_meta_box(
				'brik-group-' . $group['key'],
				esc_html( $group['title'] ),
				array( __CLASS__, 'meta_box' ),
				$post_type,
				$context,
				$priority,
				array(
					'group'                            => $group,
					'__back_compat_meta_box'           => false,
					'__block_editor_compatible_meta_box' => true,
				)
			);
			add_filter(
				'postbox_classes_' . $post_type . '_brik-group-' . $group['key'],
				static function ( $classes ) use ( $group ) {
					$classes[] = 'brik-cf-postbox';
					$classes[] = 'brik-cf-style-' . $group['style'];
					return $classes;
				}
			);
		}
	}

	/**
	 * Classic editor: "after title" groups render between the title and the content.
	 */
	public static function after_title( $post ) {
		if ( self::block_editor( $post ) ) {
			return;
		}
		foreach ( self::matching( self::post_context( $post ) ) as $group ) {
			if ( 'after_title' !== $group['position'] ) {
				continue;
			}
			echo '<div class="brik-cf-after-title postbox brik-cf-postbox brik-cf-style-' . esc_attr( $group['style'] ) . '">';
			echo '<div class="postbox-header"><h2 class="hndle">' . esc_html( $group['title'] ) . '</h2></div><div class="inside">';
			self::meta_box( $post, array( 'args' => array( 'group' => $group ) ) );
			echo '</div></div>';
		}
	}

	public static function meta_box( $post, $box ) {
		$group = $box['args']['group'];
		if ( ! self::$nonce_printed && self::block_editor( $post ) ) {
			// The block editor hides admin notices; the script lifts these into editor notices.
			self::print_errors( 'post_' . $post->ID );
		}
		self::nonce( 'post', $post->ID );
		self::render_group(
			$group,
			array(
				'type' => 'post',
				'id'   => $post->ID,
				'sub'  => $post->post_type,
			),
			'auto-draft' === $post->post_status
		);
	}

	private static function nonce( $object, $id = 0 ) {
		if ( self::$nonce_printed ) {
			return;
		}
		self::$nonce_printed = true;
		wp_nonce_field( 'brik_cf_' . $object . '_' . (int) $id, self::NONCE );
	}

	/* ---------------------------------------------------------------------
	 * Terms.
	 * ------------------------------------------------------------------- */

	public static function term_hooks() {
		foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $tax ) {
			add_action( $tax . '_add_form_fields', array( __CLASS__, 'term_add_fields' ) );
			add_action( $tax . '_edit_form', array( __CLASS__, 'term_edit_fields' ), 10, 2 );
		}
	}

	public static function term_add_fields( $taxonomy ) {
		$groups = self::matching( array( 'taxonomy' => $taxonomy ) );
		if ( ! $groups ) {
			return;
		}
		self::nonce( 'term', 0 );
		echo '<div class="brik-cf-screen brik-cf-term-add">';
		foreach ( $groups as $group ) {
			self::render_card( $group, null, true );
		}
		echo '</div>';
	}

	public static function term_edit_fields( $term, $taxonomy ) {
		$groups = self::matching( array( 'taxonomy' => $taxonomy ) );
		if ( ! $groups ) {
			return;
		}
		self::nonce( 'term', $term->term_id );
		echo '<div class="brik-cf-screen brik-cf-term-edit">';
		foreach ( $groups as $group ) {
			self::render_card(
				$group,
				array(
					'type' => 'term',
					'id'   => (int) $term->term_id,
					'sub'  => $taxonomy,
				)
			);
		}
		echo '</div>';
	}

	public static function save_term( $term_id, $tt_id, $taxonomy ) {
		if ( ! self::verify( 'term', isset( $_POST['tag_ID'] ) ? (int) $_POST['tag_ID'] : 0 ) || ! current_user_can( 'edit_term', $term_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- verified in verify().
			return;
		}
		$errors = self::process(
			self::matching( array( 'taxonomy' => $taxonomy ) ),
			array(
				'type' => 'term',
				'id'   => (int) $term_id,
				'sub'  => $taxonomy,
			)
		);
		self::remember_errors( 'term_' . $term_id, $errors );
	}

	/* ---------------------------------------------------------------------
	 * Users.
	 * ------------------------------------------------------------------- */

	public static function user_fields( $user ) {
		$groups = self::matching( self::user_context( $user ) );
		if ( ! $groups ) {
			return;
		}
		$id = $user instanceof \WP_User ? (int) $user->ID : 0;
		self::nonce( 'user', $id );
		echo '<div class="brik-cf-screen brik-cf-user">';
		foreach ( $groups as $group ) {
			self::render_card(
				$group,
				$id ? array(
					'type' => 'user',
					'id'   => $id,
					'sub'  => '',
				) : null,
				! $id
			);
		}
		echo '</div>';
	}

	public static function save_user( $user_id ) {
		$form_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in verify().
		if ( ! self::verify( 'user', $form_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		$errors = self::process(
			self::matching( self::user_context( get_userdata( $user_id ) ) ),
			array(
				'type' => 'user',
				'id'   => (int) $user_id,
				'sub'  => '',
			)
		);
		self::remember_errors( 'user_' . $user_id, $errors );
	}

	/* ---------------------------------------------------------------------
	 * Site options page.
	 * ------------------------------------------------------------------- */

	public static function options_capability() {
		return apply_filters( 'brik/content/options_capability', 'manage_options' );
	}

	public static function has_options_groups() {
		return (bool) self::matching( array( 'options_page' => self::OPTIONS ) );
	}

	public static function options_page() {
		if ( ! current_user_can( self::options_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to edit site options.', 'brik-builder' ), 403 );
		}
		$groups = self::matching( array( 'options_page' => self::OPTIONS ) );
		$target = array(
			'type' => 'option',
			'id'   => 0,
			'sub'  => '',
		);
		?>
		<div class="wrap brik-admin brik-cf-options-wrap">
			<div class="brik-admin-header">
				<div>
					<h1 class="brik-admin-title"><?php esc_html_e( 'Site options', 'brik-builder' ); ?></h1>
					<p class="brik-admin-subtitle"><?php esc_html_e( 'Global content used across the site. Show it anywhere with {option:name}.', 'brik-builder' ); ?></p>
				</div>
			</div>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification -- display only.
			if ( isset( $_GET['brik_notice'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['brik_notice'] ) ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Options saved.', 'brik-builder' ) . '</p></div>';
			}
			self::print_errors( 'option' );
			?>
			<?php if ( ! $groups ) : ?>
				<div class="brik-card"><p><?php esc_html_e( 'No field groups are assigned to the site options page yet. Create one under Brik → Content with the location “Options page”.', 'brik-builder' ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="brik-cf-screen brik-cf-options" novalidate>
					<input type="hidden" name="action" value="brik_save_options">
					<?php
					self::nonce( 'option', 0 );
					foreach ( $groups as $group ) {
						self::render_card( $group, $target );
					}
					?>
					<p class="submit"><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save options', 'brik-builder' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function save_options() {
		if ( ! current_user_can( self::options_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to edit site options.', 'brik-builder' ), 403 );
		}
		if ( ! self::verify( 'option', 0 ) ) {
			wp_die( esc_html__( 'The link you followed has expired. Reload the page and try again.', 'brik-builder' ), 403 );
		}
		$errors = self::process(
			self::matching( array( 'options_page' => self::OPTIONS ) ),
			array(
				'type' => 'option',
				'id'   => 0,
				'sub'  => '',
			)
		);
		self::remember_errors( 'option', $errors );
		wp_safe_redirect( add_query_arg( 'brik_notice', 'saved', admin_url( 'admin.php?page=' . self::OPTIONS ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Saving.
	 * ------------------------------------------------------------------- */

	private static function verify( $object, $id ) {
		if ( empty( $_POST[ self::NONCE ] ) ) {
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) );
		// New terms and users are created by the same request that carries the nonce for id 0.
		return wp_verify_nonce( $nonce, 'brik_cf_' . $object . '_' . (int) $id ) || ( in_array( $object, array( 'term', 'user' ), true ) && wp_verify_nonce( $nonce, 'brik_cf_' . $object . '_0' ) );
	}

	public static function save_post( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! self::verify( 'post', $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// Saving terms or meta below must not run this again.
		remove_action( 'save_post', array( __CLASS__, 'save_post' ), 10 );
		$errors = self::process(
			self::matching( self::post_context( $post ) ),
			array(
				'type' => 'post',
				'id'   => (int) $post_id,
				'sub'  => $post->post_type,
			)
		);
		add_action( 'save_post', array( __CLASS__, 'save_post' ), 10, 2 );
		self::remember_errors( 'post_' . $post_id, $errors );
	}

	/**
	 * Validate and save the submitted fields of the given groups. Only fields that were on
	 * the form are touched; fields that fail validation keep their previous value.
	 *
	 * @return string[] Error messages.
	 */
	public static function process( array $groups, array $t ) {
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- nonce checked by callers; every value is sanitized per field type.
		$input = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : array();
		// phpcs:ignore WordPress.Security.NonceVerification -- nonce checked by callers.
		$shown  = isset( $_POST['brik_cf_groups'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['brik_cf_groups'] ) ) : array();
		$errors = array();
		foreach ( $groups as $group ) {
			if ( ! in_array( $group['key'], $shown, true ) ) {
				continue;
			}
			$by_key = Fields::values_by_key( $group['fields'], $input );
			foreach ( $group['fields'] as $field ) {
				if ( ! Fields::has_value( $field['type'] ) || ! array_key_exists( $field['key'], $input ) ) {
					continue;
				}
				$raw = self::from_form( $field, $input[ $field['key'] ] );
				if ( Fields::conditions_met( $field, $by_key ) ) {
					$messages = Fields::validate( $field, $raw );
					if ( $messages ) {
						$errors = array_merge( $errors, $messages );
						continue;
					}
				}
				Values::save( $field, Fields::sanitize( $field, $raw ), $t );
			}
		}
		return $errors;
	}

	/**
	 * Undo the form encoding: the "" placeholder that makes empty lists submit, and the
	 * date-local separator.
	 */
	private static function from_form( array $field, $raw ) {
		if ( is_array( $raw ) && in_array( $field['type'], array( 'repeater' ), true ) ) {
			$rows = array();
			foreach ( $raw as $row ) {
				if ( is_array( $row ) ) {
					$clean = array();
					foreach ( $field['options']['sub_fields'] as $sub ) {
						if ( array_key_exists( $sub['key'], $row ) ) {
							$clean[ $sub['key'] ] = self::from_form( $sub, $row[ $sub['key'] ] );
						}
					}
					$rows[] = $clean;
				}
			}
			return $rows;
		}
		if ( is_array( $raw ) && 'group' === $field['type'] ) {
			$clean = array();
			foreach ( $field['options']['sub_fields'] as $sub ) {
				if ( array_key_exists( $sub['key'], $raw ) ) {
					$clean[ $sub['key'] ] = self::from_form( $sub, $raw[ $sub['key'] ] );
				}
			}
			return $clean;
		}
		if ( is_array( $raw ) && ( Fields::is_multiple( $field ) || 'gallery' === $field['type'] ) ) {
			return array_values(
				array_filter(
					$raw,
					static function ( $v ) {
						return '' !== $v;
					}
				)
			);
		}
		if ( 'toggle' === $field['type'] && is_array( $raw ) ) {
			return end( $raw );
		}
		return $raw;
	}

	private static function errors_key( $what ) {
		return 'brik_cf_err_' . get_current_user_id() . '_' . $what;
	}

	private static function remember_errors( $what, array $errors ) {
		if ( $errors ) {
			set_transient( self::errors_key( $what ), array_slice( array_values( array_unique( $errors ) ), 0, 30 ), self::ERRORS_TTL );
		} else {
			delete_transient( self::errors_key( $what ) );
		}
	}

	private static function print_errors( $what ) {
		$errors = get_transient( self::errors_key( $what ) );
		if ( ! $errors ) {
			return;
		}
		delete_transient( self::errors_key( $what ) );
		echo '<div class="notice notice-error brik-cf-notice"><p><strong>' . esc_html__( 'Some fields were not saved:', 'brik-builder' ) . '</strong></p><ul>';
		foreach ( (array) $errors as $error ) {
			echo '<li>' . esc_html( $error ) . '</li>';
		}
		echo '</ul></div>';
	}

	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || ( method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) ) {
			return;
		}
		if ( 'post' === $screen->base && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- display only.
			self::print_errors( 'post_' . absint( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( 'term' === $screen->base && isset( $_GET['tag_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::print_errors( 'term_' . absint( $_GET['tag_ID'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( in_array( $screen->base, array( 'profile', 'user-edit' ), true ) ) {
			$id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification
			self::print_errors( 'user_' . $id );
		}
	}

	/* ---------------------------------------------------------------------
	 * Assets.
	 * ------------------------------------------------------------------- */

	public static function assets( $hook ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$load = false;
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$load = (bool) Registry::groups_for( 'post', $screen->post_type );
		} elseif ( in_array( $hook, array( 'term.php', 'edit-tags.php' ), true ) ) {
			$load = (bool) Registry::groups_for( 'term', $screen->taxonomy );
		} elseif ( in_array( $hook, array( 'profile.php', 'user-edit.php', 'user-new.php' ), true ) ) {
			$load = (bool) Registry::groups_for( 'user' );
		} elseif ( false !== strpos( $screen->id, self::OPTIONS ) ) {
			$load = true;
		}
		if ( $load ) {
			self::enqueue( $screen );
		}
	}

	public static function enqueue( $screen = null ) {
		if ( self::$enqueued ) {
			return;
		}
		self::$enqueued = true;
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'brik-content-fields', BRIK_URL . 'assets/admin/content-fields.css', array(), Frontend::ver( 'assets/admin/content-fields.css' ) );
		wp_enqueue_script( 'brik-content-fields', BRIK_URL . 'assets/admin/content-fields.js', array( 'wp-api-fetch', 'wp-i18n', 'wp-dom-ready' ), Frontend::ver( 'assets/admin/content-fields.js' ), true );
		wp_set_script_translations( 'brik-content-fields', 'brik-builder', BRIK_DIR . 'languages' );
		wp_localize_script(
			'brik-content-fields',
			'brikFields',
			array(
				'rest'        => esc_url_raw( rest_url( 'brik/v1/content/' ) ),
				'blockEditor' => $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor(),
				'i18n'        => array(
					'required'      => __( 'This field is required.', 'brik-builder' ),
					/* translators: %s: comma separated field labels */
					'fillRequired'  => __( 'Fill in the required fields before saving: %s', 'brik-builder' ),
					'chooseImage'   => __( 'Choose image', 'brik-builder' ),
					'chooseImages'  => __( 'Add images', 'brik-builder' ),
					'chooseFile'    => __( 'Choose file', 'brik-builder' ),
					'use'           => __( 'Use this', 'brik-builder' ),
					'remove'        => __( 'Remove', 'brik-builder' ),
					'search'        => __( 'Search…', 'brik-builder' ),
					'noResults'     => __( 'Nothing found', 'brik-builder' ),
					'loading'       => __( 'Loading…', 'brik-builder' ),
					/* translators: %d: maximum number of items */
					'max'           => __( 'You can choose up to %d.', 'brik-builder' ),
					/* translators: %d: minimum number of items */
					'min'           => __( 'Choose at least %d.', 'brik-builder' ),
					/* translators: %d: row number */
					'row'           => __( 'Row %d', 'brik-builder' ),
					'confirmRemove' => __( 'Remove this row?', 'brik-builder' ),
					'notFound'      => __( 'That address could not be found.', 'brik-builder' ),
					'badType'       => __( 'This file type is not allowed here.', 'brik-builder' ),
					'invalidEmail'  => __( 'Enter a valid email address.', 'brik-builder' ),
					'invalidUrl'    => __( 'Enter a valid URL.', 'brik-builder' ),
					'noPreview'     => __( 'No preview available for this URL.', 'brik-builder' ),
					'loadMore'      => __( 'Load more', 'brik-builder' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering.
	 * ------------------------------------------------------------------- */

	/**
	 * Group outside the post editor (terms, users, options) as a standalone card.
	 */
	private static function render_card( array $group, $t, $fresh = false ) {
		echo '<section class="brik-cf-card brik-cf-style-' . esc_attr( $group['style'] ) . '">';
		echo '<header class="brik-cf-card-header"><h2 class="brik-cf-card-title">' . esc_html( $group['title'] ) . '</h2>';
		if ( '' !== $group['description'] ) {
			echo '<p class="brik-cf-card-description">' . esc_html( $group['description'] ) . '</p>';
		}
		echo '</header><div class="brik-cf-card-body">';
		self::render_group( $group, $t, $fresh );
		echo '</div></section>';
	}

	/**
	 * @param array|null $t     Target to read values from; null for a new object (defaults).
	 * @param bool       $fresh New object: show defaults even if meta is empty.
	 */
	public static function render_group( array $group, $t, $fresh = false ) {
		$values = array();
		foreach ( $group['fields'] as $field ) {
			if ( ! Fields::has_value( $field['type'] ) ) {
				continue;
			}
			$values[ $field['key'] ] = $t && ! $fresh ? Values::stored( $field, $t ) : ( '' !== $field['default'] ? $field['default'] : Fields::empty_value( $field ) );
		}
		printf( '<div class="brik-cf" data-group="%s">', esc_attr( $group['key'] ) );
		printf( '<input type="hidden" name="brik_cf_groups[]" value="%s">', esc_attr( $group['key'] ) );
		self::render_fields( $group['fields'], $values, self::INPUT, 'brik-cf' );
		echo '</div>';
	}

	/**
	 * A list of sibling fields, split into tabs when it holds tab fields.
	 *
	 * @param array $values Values keyed by field key.
	 */
	public static function render_fields( array $fields, array $values, $base, $id_base ) {
		$tabs    = array();
		$current = null;
		$loose   = array();
		foreach ( $fields as $field ) {
			if ( 'tab' === $field['type'] ) {
				$tabs[ $field['key'] ] = array(
					'field'  => $field,
					'fields' => array(),
				);
				$current               = $field['key'];
				continue;
			}
			if ( null === $current ) {
				$loose[] = $field;
			} else {
				$tabs[ $current ]['fields'][] = $field;
			}
		}

		if ( $loose ) {
			echo '<div class="brik-cf-fields">';
			foreach ( $loose as $field ) {
				self::render_field( $field, $values, $base, $id_base );
			}
			echo '</div>';
		}
		if ( ! $tabs ) {
			return;
		}

		$first     = reset( $tabs );
		$placement = isset( $first['field']['options']['placement'] ) && 'left' === $first['field']['options']['placement'] ? 'left' : 'top';
		echo '<div class="brik-cf-tabs brik-cf-tabs-' . esc_attr( $placement ) . '"><div class="brik-cf-tablist" role="tablist">';
		$i = 0;
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<button type="button" role="tab" class="brik-cf-tab" id="%1$s-tab" aria-controls="%1$s-panel" aria-selected="%2$s" tabindex="%3$s">%4$s</button>',
				esc_attr( $id_base . '-' . $key ),
				0 === $i ? 'true' : 'false',
				0 === $i ? '0' : '-1',
				esc_html( $tab['field']['label'] )
			);
			++$i;
		}
		echo '</div><div class="brik-cf-panels">';
		$i = 0;
		foreach ( $tabs as $key => $tab ) {
			printf( '<div class="brik-cf-panel brik-cf-fields" role="tabpanel" id="%1$s-panel" aria-labelledby="%1$s-tab"%2$s>', esc_attr( $id_base . '-' . $key ), 0 === $i ? '' : ' hidden' );
			foreach ( $tab['fields'] as $field ) {
				self::render_field( $field, $values, $base, $id_base );
			}
			echo '</div>';
			++$i;
		}
		echo '</div></div>';
	}

	private static function dom_id( $name ) {
		return trim( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', $name ), '-' );
	}

	private static function opt( array $field, $key, $default = '' ) {
		return isset( $field['options'][ $key ] ) && '' !== $field['options'][ $key ] && null !== $field['options'][ $key ] ? $field['options'][ $key ] : $default;
	}

	public static function render_field( array $field, array $values, $base, $id_base ) {
		$name  = $base . '[' . $field['key'] . ']';
		$id    = self::dom_id( $name );
		$value = isset( $values[ $field['key'] ] ) ? $values[ $field['key'] ] : Fields::empty_value( $field );
		$attrs = array(
			'class'           => 'brik-cf-field brik-cf-type-' . $field['type'] . ( 'message' === $field['type'] ? ' brik-cf-is-message' : '' ),
			'data-key'        => $field['key'],
			'data-name'       => $field['name'],
			'data-type'       => $field['type'],
			'data-required'   => $field['required'] ? '1' : null,
			'data-label'      => $field['label'],
			'data-conditions' => $field['conditions'] ? wp_json_encode( $field['conditions'] ) : null,
			// Each field gives up its share of the 16px gap so widths add up to a full row.
			'style'           => '--brik-cf-w:' . (int) $field['width'] . '%;--brik-cf-g:' . round( 16 * ( 1 - (int) $field['width'] / 100 ), 2 ) . 'px',
		);
		if ( in_array( $field['type'], array( 'gallery', 'relationship', 'repeater', 'post_object', 'user', 'taxonomy' ), true ) ) {
			foreach ( array( 'min', 'max' ) as $limit ) {
				$n = (int) self::opt( $field, $limit, 0 );
				if ( $n > 0 ) {
					$attrs[ 'data-' . $limit ] = (string) $n;
				}
			}
		}
		echo '<div' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput -- brik_attrs escapes.

		$label_for = in_array( $field['type'], array( 'text', 'textarea', 'number', 'email', 'url', 'password', 'select', 'date', 'datetime', 'time', 'oembed', 'range' ), true ) ? $id : '';
		if ( 'message' !== $field['type'] || '' !== $field['label'] ) {
			echo '<div class="brik-cf-label-row">';
			if ( $label_for ) {
				echo '<label class="brik-cf-label" for="' . esc_attr( $label_for ) . '">';
			} else {
				echo '<span class="brik-cf-label" id="' . esc_attr( $id ) . '-label">';
			}
			echo esc_html( '' !== $field['label'] ? $field['label'] : $field['name'] );
			if ( $field['required'] ) {
				echo '<span class="brik-cf-required" aria-hidden="true">*</span><span class="screen-reader-text">' . esc_html__( '(required)', 'brik-builder' ) . '</span>';
			}
			echo $label_for ? '</label>' : '</span>';
			echo '</div>';
		}
		if ( '' !== $field['instructions'] ) {
			echo '<p class="brik-cf-help" id="' . esc_attr( $id ) . '-help">' . wp_kses( $field['instructions'], array( 'a' => array( 'href' => true, 'target' => true ), 'strong' => array(), 'em' => array(), 'code' => array(), 'br' => array() ) ) . '</p>';
		}
		echo '<div class="brik-cf-control">';
		self::control( $field, $value, $name, $id );
		echo '</div><p class="brik-cf-error" role="alert" hidden></p></div>';
	}

	private static function input_attrs( array $field, $id, array $extra = array() ) {
		return array_merge(
			array(
				'id'               => $id,
				'placeholder'      => '' !== $field['placeholder'] ? $field['placeholder'] : null,
				'aria-describedby' => '' !== $field['instructions'] ? $id . '-help' : null,
				'aria-required'    => $field['required'] ? 'true' : null,
			),
			$extra
		);
	}

	private static function affixed( array $field, $input ) {
		$pre = (string) self::opt( $field, 'prepend', '' );
		$app = (string) self::opt( $field, 'append', '' );
		if ( '' === $pre && '' === $app ) {
			return $input;
		}
		return '<div class="brik-cf-affix">' . ( '' !== $pre ? '<span class="brik-cf-affix-text">' . esc_html( $pre ) . '</span>' : '' ) . $input . ( '' !== $app ? '<span class="brik-cf-affix-text">' . esc_html( $app ) . '</span>' : '' ) . '</div>';
	}

	/**
	 * The input for one field.
	 */
	public static function control( array $field, $value, $name, $id ) {
		$o = $field['options'];
		switch ( $field['type'] ) {
			case 'text':
			case 'email':
			case 'url':
			case 'password':
				$types = array(
					'text'     => 'text',
					'email'    => 'email',
					'url'      => 'url',
					'password' => 'password',
				);
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'type'         => $types[ $field['type'] ],
						'name'         => $name,
						'value'        => is_scalar( $value ) ? (string) $value : '',
						'class'        => 'brik-cf-input',
						'maxlength'    => (int) self::opt( $field, 'maxlength', 0 ) ? (string) (int) self::opt( $field, 'maxlength', 0 ) : null,
						'autocomplete' => 'password' === $field['type'] ? 'new-password' : null,
					)
				);
				echo self::affixed( $field, '<input' . brik_attrs( $attrs ) . '>' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
				break;

			case 'textarea':
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'name'      => $name,
						'class'     => 'brik-cf-input brik-cf-textarea',
						'rows'      => (string) max( 1, (int) self::opt( $field, 'rows', 4 ) ),
						'maxlength' => (int) self::opt( $field, 'maxlength', 0 ) ? (string) (int) self::opt( $field, 'maxlength', 0 ) : null,
					)
				);
				echo '<textarea' . brik_attrs( $attrs ) . '>' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'number':
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'type'      => 'number',
						'name'      => $name,
						'value'     => is_scalar( $value ) ? (string) $value : '',
						'class'     => 'brik-cf-input',
						'min'       => '' !== $o['min'] ? (string) $o['min'] : null,
						'max'       => '' !== $o['max'] ? (string) $o['max'] : null,
						'step'      => '' !== $o['step'] ? (string) $o['step'] : 'any',
						'inputmode' => 'decimal',
					)
				);
				echo self::affixed( $field, '<input' . brik_attrs( $attrs ) . '>' ); // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'range':
				$min   = '' !== $o['min'] ? $o['min'] : 0;
				$max   = '' !== $o['max'] ? $o['max'] : 100;
				$v     = is_numeric( $value ) ? $value : $min;
				$attrs = array(
					'type'  => 'range',
					'id'    => $id,
					'name'  => $name,
					'value' => (string) $v,
					'min'   => (string) $min,
					'max'   => (string) $max,
					'step'  => '' !== $o['step'] ? (string) $o['step'] : '1',
					'class' => 'brik-cf-range',
				);
				echo '<div class="brik-cf-range-wrap"><input' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<output class="brik-cf-range-value" for="' . esc_attr( $id ) . '">' . esc_html( self::opt( $field, 'prepend', '' ) ) . '<span>' . esc_html( (string) $v ) . '</span>' . esc_html( self::opt( $field, 'append', '' ) ) . '</output></div>';
				break;

			case 'wysiwyg':
				$settings = array(
					'toolbar' => self::opt( $field, 'toolbar', 'full' ),
					'media'   => (bool) self::opt( $field, 'media', true ),
				);
				echo '<div class="brik-cf-wysiwyg" data-settings="' . esc_attr( wp_json_encode( $settings ) ) . '">';
				echo '<textarea class="brik-cf-wysiwyg-input wp-editor-area" rows="10" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( is_scalar( $value ) ? (string) $value : '' ) . '</textarea></div>';
				break;

			case 'select':
				$multiple = Fields::is_multiple( $field );
				$selected = array_map( 'strval', (array) $value );
				if ( $multiple ) {
					echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
				}
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'name'     => $multiple ? $name . '[]' : $name,
						'class'    => 'brik-cf-input brik-cf-select' . ( $multiple ? ' is-multiple' : '' ),
						'multiple' => $multiple,
						'size'     => $multiple ? (string) min( 8, max( 3, count( $o['choices'] ) ) ) : null,
					)
				);
				unset( $attrs['placeholder'] );
				echo '<select' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				if ( ! $multiple ) {
					echo '<option value="">' . esc_html( '' !== $field['placeholder'] ? $field['placeholder'] : __( '— Select —', 'brik-builder' ) ) . '</option>';
				}
				foreach ( $o['choices'] as $choice ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $choice['value'] ), selected( in_array( $choice['value'], $selected, true ), true, false ), esc_html( $choice['label'] ) );
				}
				echo '</select>';
				break;

			case 'checkbox':
			case 'radio':
				$is_check = 'checkbox' === $field['type'];
				$selected = array_map( 'strval', (array) $value );
				$layout   = 'horizontal' === self::opt( $field, 'layout', 'vertical' ) ? 'is-horizontal' : 'is-vertical';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
				echo '<div class="brik-cf-choices ' . esc_attr( $layout ) . '" role="' . ( $is_check ? 'group' : 'radiogroup' ) . '" aria-labelledby="' . esc_attr( $id ) . '-label">';
				foreach ( $o['choices'] as $i => $choice ) {
					printf(
						'<label class="brik-cf-choice"><input type="%1$s" name="%2$s" value="%3$s"%4$s><span>%5$s</span></label>',
						$is_check ? 'checkbox' : 'radio',
						esc_attr( $is_check ? $name . '[]' : $name ),
						esc_attr( $choice['value'] ),
						checked( in_array( $choice['value'], $selected, true ), true, false ),
						esc_html( $choice['label'] )
					);
				}
				echo '</div>';
				break;

			case 'button_group':
				$current = is_scalar( $value ) ? (string) $value : '';
				echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
				echo '<div class="brik-cf-button-group" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-label"' . ( self::opt( $field, 'allow_null', false ) ? ' data-allow-null="1"' : '' ) . '>';
				foreach ( $o['choices'] as $choice ) {
					printf(
						'<label class="brik-cf-segment"><input type="radio" name="%1$s" value="%2$s"%3$s><span>%4$s</span></label>',
						esc_attr( $name ),
						esc_attr( $choice['value'] ),
						checked( $current, $choice['value'], false ),
						esc_html( $choice['label'] )
					);
				}
				echo '</div>';
				break;

			case 'toggle':
				$on = (bool) $value;
				echo '<label class="brik-cf-switch-row"><input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
				echo '<input type="checkbox" role="switch" class="brik-cf-switch" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $on, true, false ) . ' aria-labelledby="' . esc_attr( $id ) . '-label">';
				$message = (string) self::opt( $field, 'message', '' );
				$on_t    = (string) self::opt( $field, 'on_text', '' );
				$off_t   = (string) self::opt( $field, 'off_text', '' );
				if ( '' !== $message || '' !== $on_t || '' !== $off_t ) {
					echo '<span class="brik-cf-switch-text" data-on="' . esc_attr( '' !== $on_t ? $on_t : $message ) . '" data-off="' . esc_attr( '' !== $off_t ? $off_t : $message ) . '">' . esc_html( $on ? ( '' !== $on_t ? $on_t : $message ) : ( '' !== $off_t ? $off_t : $message ) ) . '</span>';
				}
				echo '</label>';
				break;

			case 'date':
			case 'datetime':
			case 'time':
				$v = is_scalar( $value ) ? (string) $value : '';
				if ( 'datetime' === $field['type'] && '' !== $v ) {
					$v = str_replace( ' ', 'T', substr( $v, 0, 16 ) );
				} elseif ( 'time' === $field['type'] && '' !== $v ) {
					$v = substr( $v, 0, 5 );
				}
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'type'  => 'datetime' === $field['type'] ? 'datetime-local' : $field['type'],
						'name'  => $name,
						'value' => $v,
						'class' => 'brik-cf-input brik-cf-date',
					)
				);
				echo '<input' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'color':
				$v     = is_scalar( $value ) ? (string) $value : '';
				$hex   = preg_match( '/^#[0-9a-f]{6}$/i', $v ) ? $v : ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $v, $m ) ? '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3] : '#000000' );
				echo '<div class="brik-cf-color' . ( '' === $v ? ' is-empty' : '' ) . '"><span class="brik-cf-color-swatch" style="--swatch:' . esc_attr( '' !== $v ? $v : 'transparent' ) . '"><input type="color" class="brik-cf-color-picker" value="' . esc_attr( $hex ) . '" aria-label="' . esc_attr__( 'Pick a color', 'brik-builder' ) . '" tabindex="-1"></span>';
				echo '<input type="text" class="brik-cf-input brik-cf-color-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $v ) . '" placeholder="' . esc_attr( self::opt( $field, 'alpha', false ) ? 'rgba(0, 0, 0, 0.5)' : '#000000' ) . '" spellcheck="false" autocomplete="off"></div>';
				break;

			case 'image':
			case 'file':
				self::media_control( $field, $value, $name, $id );
				break;

			case 'gallery':
				self::gallery_control( $field, (array) $value, $name, $id );
				break;

			case 'oembed':
				$attrs = self::input_attrs(
					$field,
					$id,
					array(
						'type'        => 'url',
						'name'        => $name,
						'value'       => is_scalar( $value ) ? (string) $value : '',
						'class'       => 'brik-cf-input brik-cf-oembed-url',
						'placeholder' => '' !== $field['placeholder'] ? $field['placeholder'] : 'https://www.youtube.com/watch?v=…',
					)
				);
				echo '<div class="brik-cf-oembed"><div class="brik-cf-input-icon">' . brik_icon( 'square-play', 'brik-cf-icon' ) . '<input' . brik_attrs( $attrs ) . '></div><div class="brik-cf-oembed-preview" hidden></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				break;

			case 'link':
				$v = is_array( $value ) ? $value : array();
				echo '<div class="brik-cf-link">';
				echo '<div class="brik-cf-input-icon">' . brik_icon( 'link', 'brik-cf-icon' ) . '<input type="text" class="brik-cf-input" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[url]" value="' . esc_attr( isset( $v['url'] ) ? $v['url'] : '' ) . '" placeholder="' . esc_attr__( 'https://… or /page', 'brik-builder' ) . '" aria-label="' . esc_attr__( 'URL', 'brik-builder' ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
				echo '<input type="text" class="brik-cf-input" name="' . esc_attr( $name ) . '[title]" value="' . esc_attr( isset( $v['title'] ) ? $v['title'] : '' ) . '" placeholder="' . esc_attr__( 'Link text', 'brik-builder' ) . '" aria-label="' . esc_attr__( 'Link text', 'brik-builder' ) . '">';
				echo '<label class="brik-cf-choice brik-cf-link-target"><input type="checkbox" name="' . esc_attr( $name ) . '[target]" value="_blank"' . checked( isset( $v['target'] ) && '_blank' === $v['target'], true, false ) . '><span>' . esc_html__( 'Open in a new tab', 'brik-builder' ) . '</span></label>';
				echo '</div>';
				break;

			case 'post_object':
			case 'user':
				self::picker_control( $field, $value, $name, $id );
				break;

			case 'relationship':
				self::relationship_control( $field, (array) $value, $name, $id );
				break;

			case 'taxonomy':
				self::taxonomy_control( $field, $value, $name, $id );
				break;

			case 'map':
				self::map_control( $field, is_array( $value ) ? $value : array(), $name, $id );
				break;

			case 'repeater':
				self::repeater_control( $field, is_array( $value ) ? $value : array(), $name, $id );
				break;

			case 'group':
				$v      = is_array( $value ) ? $value : array();
				$values = array();
				foreach ( $o['sub_fields'] as $sub ) {
					if ( Fields::has_value( $sub['type'] ) ) {
						$values[ $sub['key'] ] = array_key_exists( $sub['name'], $v ) ? $v[ $sub['name'] ] : ( '' !== $sub['default'] ? $sub['default'] : Fields::empty_value( $sub ) );
					}
				}
				echo '<input type="hidden" name="' . esc_attr( $name ) . '[__group]" value="1">';
				echo '<div class="brik-cf-group brik-cf-layout-' . esc_attr( self::opt( $field, 'layout', 'block' ) ) . '">';
				self::render_fields( $o['sub_fields'], $values, $name, $id );
				echo '</div>';
				break;

			case 'message':
				echo '<div class="brik-cf-message">' . wp_kses_post( wpautop( (string) self::opt( $field, 'message', '' ) ) ) . '</div>';
				break;
		}
	}

	private static function media_control( array $field, $value, $name, $id ) {
		$is_image = 'image' === $field['type'];
		$aid      = (int) $value;
		$size     = self::opt( $field, 'preview_size', 'medium' );
		$preview  = '';
		$title    = '';
		$meta     = '';
		if ( $aid && 'attachment' === get_post_type( $aid ) ) {
			if ( $is_image ) {
				$src     = wp_get_attachment_image_url( $aid, 'thumbnail' === $size ? 'thumbnail' : 'medium' );
				$preview = $src ? '<img src="' . esc_url( $src ) . '" alt="">' : '';
			}
			$file  = get_attached_file( $aid );
			$title = get_the_title( $aid );
			$meta  = wp_basename( $file ? $file : (string) wp_get_attachment_url( $aid ) );
		} else {
			$aid = 0;
		}
		$attrs = array(
			'class'        => 'brik-cf-media' . ( $aid ? ' has-value' : '' ) . ' is-' . $field['type'] . ' size-' . $size,
			'data-library' => $is_image ? 'image' : '',
			'data-mimes'   => (string) self::opt( $field, 'mime_types', '' ),
			'data-size'    => $size,
		);
		echo '<div' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" class="brik-cf-media-id" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $aid ? (string) $aid : '' ) . '">';
		echo '<div class="brik-cf-media-preview">';
		if ( $is_image ) {
			echo '<div class="brik-cf-media-thumb">' . $preview . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
		} else {
			echo '<div class="brik-cf-file-card"><span class="brik-cf-file-icon">' . brik_icon( 'file', 'brik-cf-icon' ) . '</span><span class="brik-cf-file-meta"><strong class="brik-cf-file-title">' . esc_html( $title ) . '</strong><span class="brik-cf-file-name">' . esc_html( $meta ) . '</span></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="brik-cf-media-actions">';
		echo '<button type="button" class="brik-cf-btn brik-cf-btn-sm brik-cf-media-edit">' . esc_html__( 'Replace', 'brik-builder' ) . '</button>';
		echo '<button type="button" class="brik-cf-btn brik-cf-btn-sm brik-cf-btn-ghost brik-cf-media-remove">' . esc_html__( 'Remove', 'brik-builder' ) . '</button>';
		echo '</div></div>';
		echo '<button type="button" class="brik-cf-dropzone brik-cf-media-add">' . brik_icon( $is_image ? 'image-plus' : 'file-plus', 'brik-cf-icon' ) . '<span>' . esc_html( $is_image ? __( 'Add image', 'brik-builder' ) : __( 'Add file', 'brik-builder' ) ) . '</span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	private static function gallery_control( array $field, array $ids, $name, $id ) {
		$size = self::opt( $field, 'preview_size', 'thumbnail' );
		echo '<div class="brik-cf-gallery" data-mimes="' . esc_attr( (string) self::opt( $field, 'mime_types', '' ) ) . '" data-size="' . esc_attr( $size ) . '" id="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		echo '<ul class="brik-cf-gallery-grid brik-cf-sortable">';
		foreach ( $ids as $aid ) {
			$src = wp_get_attachment_image_url( (int) $aid, 'thumbnail' );
			if ( ! $src ) {
				continue;
			}
			self::gallery_item( (int) $aid, $src, $name );
		}
		echo '</ul>';
		echo '<div class="brik-cf-gallery-bar"><button type="button" class="brik-cf-btn brik-cf-btn-outline brik-cf-gallery-add">' . brik_icon( 'images', 'brik-cf-icon' ) . esc_html__( 'Add images', 'brik-builder' ) . '</button><span class="brik-cf-count"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<template class="brik-cf-gallery-template">';
		self::gallery_item( 0, '', $name );
		echo '</template></div>';
	}

	private static function gallery_item( $aid, $src, $name ) {
		echo '<li class="brik-cf-gallery-item" draggable="true" data-id="' . esc_attr( $aid ? (string) $aid : '' ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $aid ? (string) $aid : '' ) . '">';
		echo '<img src="' . esc_url( $src ) . '" alt="" draggable="false">';
		echo '<button type="button" class="brik-cf-gallery-remove" aria-label="' . esc_attr__( 'Remove image', 'brik-builder' ) . '">' . brik_icon( 'x', 'brik-cf-icon' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</li>';
	}

	/**
	 * Labelled items for selected ids (posts, users or terms).
	 */
	public static function describe( $kind, array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( 'user' === $kind ) {
				$u = get_userdata( $id );
				if ( $u ) {
					$out[] = array(
						'id'    => $id,
						'title' => $u->display_name,
						'meta'  => implode( ', ', array_map( 'translate_user_role', array_map( 'ucfirst', (array) $u->roles ) ) ),
						'thumb' => get_avatar_url( $id, array( 'size' => 64 ) ),
					);
				}
			} elseif ( 'term' === $kind ) {
				$t = get_term( $id );
				if ( $t && ! is_wp_error( $t ) ) {
					$out[] = array(
						'id'    => $id,
						'title' => $t->name,
						'meta'  => '',
						'thumb' => '',
					);
				}
			} else {
				$p = get_post( $id );
				if ( $p ) {
					$type  = get_post_type_object( $p->post_type );
					$out[] = array(
						'id'    => $id,
						'title' => '' !== $p->post_title ? $p->post_title : __( '(no title)', 'brik-builder' ),
						'meta'  => ( $type ? $type->labels->singular_name : $p->post_type ) . ( 'publish' !== $p->post_status ? ' · ' . get_post_status_object( $p->post_status )->label : '' ),
						'thumb' => (string) get_the_post_thumbnail_url( $p, 'thumbnail' ),
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Searchable picker for posts, users and (select-style) terms.
	 */
	private static function picker_control( array $field, $value, $name, $id, $kind = null ) {
		$kind     = $kind ? $kind : ( 'user' === $field['type'] ? 'user' : 'post' );
		$multiple = Fields::is_multiple( $field );
		$items    = self::describe( $kind, array_filter( array_map( 'intval', (array) $value ) ) );
		$query    = array();
		if ( 'post' === $kind ) {
			$query = array(
				'post_type' => implode( ',', (array) self::opt( $field, 'post_types', array() ) ),
				'terms'     => implode( ',', (array) self::opt( $field, 'taxonomy', array() ) ),
			);
		} elseif ( 'user' === $kind ) {
			$query = array( 'role' => implode( ',', (array) self::opt( $field, 'role', array() ) ) );
		} else {
			$query = array( 'taxonomy' => self::opt( $field, 'taxonomy', 'category' ) );
		}
		$attrs = array(
			'class'         => 'brik-cf-picker' . ( $multiple ? ' is-multiple' : ' is-single' ),
			'data-kind'     => $kind,
			'data-multiple' => $multiple ? '1' : null,
			'data-name'     => $multiple ? $name . '[]' : $name,
			'data-query'    => wp_json_encode( $query ),
		);
		echo '<div' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		echo '<ul class="brik-cf-chips' . ( $multiple ? ' brik-cf-sortable' : '' ) . '">';
		foreach ( $items as $item ) {
			self::chip( $item, $multiple ? $name . '[]' : $name, $multiple );
		}
		echo '</ul>';
		echo '<div class="brik-cf-combobox"><div class="brik-cf-input-icon">' . brik_icon( 'search', 'brik-cf-icon' ) . '<input type="search" class="brik-cf-input brik-cf-picker-search" id="' . esc_attr( $id ) . '" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="' . esc_attr( $id ) . '-list" autocomplete="off" placeholder="' . esc_attr( '' !== $field['placeholder'] ? $field['placeholder'] : __( 'Search…', 'brik-builder' ) ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<ul class="brik-cf-listbox" id="' . esc_attr( $id ) . '-list" role="listbox" hidden></ul></div>';
		echo '</div>';
	}

	private static function chip( array $item, $name, $sortable ) {
		echo '<li class="brik-cf-chip"' . ( $sortable ? ' draggable="true"' : '' ) . ' data-id="' . esc_attr( (string) $item['id'] ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $item['id'] ) . '">';
		if ( ! empty( $item['thumb'] ) ) {
			echo '<img class="brik-cf-chip-thumb" src="' . esc_url( $item['thumb'] ) . '" alt="">';
		}
		echo '<span class="brik-cf-chip-text"><span class="brik-cf-chip-title">' . esc_html( $item['title'] ) . '</span>';
		if ( ! empty( $item['meta'] ) ) {
			echo '<span class="brik-cf-chip-meta">' . esc_html( $item['meta'] ) . '</span>';
		}
		echo '</span><button type="button" class="brik-cf-chip-remove" aria-label="' . esc_attr__( 'Remove', 'brik-builder' ) . '">' . brik_icon( 'x', 'brik-cf-icon' ) . '</button></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	private static function relationship_control( array $field, array $ids, $name, $id ) {
		$items = self::describe( 'post', array_filter( array_map( 'intval', $ids ) ) );
		$query = array(
			'post_type' => implode( ',', (array) self::opt( $field, 'post_types', array() ) ),
			'terms'     => implode( ',', (array) self::opt( $field, 'taxonomy', array() ) ),
		);
		echo '<div class="brik-cf-relationship" data-name="' . esc_attr( $name ) . '[]" data-query="' . esc_attr( wp_json_encode( $query ) ) . '">';
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		echo '<div class="brik-cf-rel-pane brik-cf-rel-choices"><div class="brik-cf-rel-head"><div class="brik-cf-input-icon">' . brik_icon( 'search', 'brik-cf-icon' ) . '<input type="search" class="brik-cf-input brik-cf-rel-search" id="' . esc_attr( $id ) . '" placeholder="' . esc_attr__( 'Search posts…', 'brik-builder' ) . '" autocomplete="off"></div></div><ul class="brik-cf-rel-list brik-cf-rel-results" role="listbox" aria-label="' . esc_attr__( 'Available', 'brik-builder' ) . '"></ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="brik-cf-rel-pane brik-cf-rel-selected-pane"><div class="brik-cf-rel-head"><span class="brik-cf-rel-heading">' . esc_html__( 'Selected', 'brik-builder' ) . '</span><span class="brik-cf-count"></span></div><ul class="brik-cf-rel-list brik-cf-rel-selected brik-cf-sortable" data-empty="' . esc_attr__( 'Nothing selected yet. Pick posts from the list.', 'brik-builder' ) . '">';
		foreach ( $items as $item ) {
			self::chip( $item, $name . '[]', true );
		}
		echo '</ul></div></div>';
	}

	private static function taxonomy_control( array $field, $value, $name, $id ) {
		$tax  = self::opt( $field, 'taxonomy', 'category' );
		$mode = self::opt( $field, 'field_type', 'checkbox' );
		if ( ! taxonomy_exists( $tax ) ) {
			/* translators: %s: taxonomy key */
			echo '<p class="brik-cf-muted">' . esc_html( sprintf( __( 'The taxonomy “%s” does not exist.', 'brik-builder' ), $tax ) ) . '</p>';
			return;
		}
		if ( in_array( $mode, array( 'select', 'multi_select' ), true ) ) {
			self::picker_control( $field, $value, $name, $id, 'term' );
			return;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'number'     => 500,
				'orderby'    => 'name',
			)
		);
		$terms    = is_wp_error( $terms ) ? array() : $terms;
		$selected = array_map( 'intval', (array) $value );
		$is_check = 'checkbox' === $mode;
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		echo '<div class="brik-cf-terms">';
		if ( count( $terms ) > 8 ) {
			echo '<div class="brik-cf-input-icon brik-cf-terms-filter">' . brik_icon( 'search', 'brik-cf-icon' ) . '<input type="search" class="brik-cf-input" placeholder="' . esc_attr__( 'Filter…', 'brik-builder' ) . '" aria-label="' . esc_attr__( 'Filter terms', 'brik-builder' ) . '"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '<div class="brik-cf-terms-list" role="' . ( $is_check ? 'group' : 'radiogroup' ) . '" aria-labelledby="' . esc_attr( $id ) . '-label">';
		if ( ! $terms ) {
			echo '<p class="brik-cf-muted">' . esc_html__( 'No terms yet.', 'brik-builder' ) . '</p>';
		}
		foreach ( self::term_tree( $terms ) as $row ) {
			list( $term, $depth ) = $row;
			printf(
				'<label class="brik-cf-choice" style="--depth:%1$d"><input type="%2$s" name="%3$s" value="%4$d"%5$s><span>%6$s</span></label>',
				(int) $depth,
				$is_check ? 'checkbox' : 'radio',
				esc_attr( $is_check ? $name . '[]' : $name ),
				(int) $term->term_id,
				checked( in_array( (int) $term->term_id, $selected, true ), true, false ),
				esc_html( $term->name )
			);
		}
		echo '</div></div>';
	}

	/**
	 * Terms in parent-first order with their depth.
	 */
	private static function term_tree( array $terms, $parent = 0, $depth = 0 ) {
		$out = array();
		$ids = wp_list_pluck( $terms, 'term_id' );
		foreach ( $terms as $term ) {
			$p = (int) $term->parent;
			// Orphans (parent not in the list) are shown at the top level.
			if ( $p === $parent || ( 0 === $parent && $p && ! in_array( $p, $ids, true ) ) ) {
				$out[] = array( $term, $depth );
				if ( $depth < 8 ) {
					$out = array_merge( $out, self::term_tree( $terms, (int) $term->term_id, $depth + 1 ) );
				}
			}
		}
		return $out;
	}

	private static function map_control( array $field, array $v, $name, $id ) {
		$lat  = isset( $v['lat'] ) ? (string) $v['lat'] : '';
		$lng  = isset( $v['lng'] ) ? (string) $v['lng'] : '';
		$addr = isset( $v['address'] ) ? (string) $v['address'] : '';
		$zoom = isset( $v['zoom'] ) ? (int) $v['zoom'] : (int) self::opt( $field, 'zoom', 14 );
		$attrs = array(
			'class'       => 'brik-cf-map',
			'data-lat'    => (string) self::opt( $field, 'center_lat', '' ),
			'data-lng'    => (string) self::opt( $field, 'center_lng', '' ),
			'data-zoom'   => (string) $zoom,
			'data-height' => (string) max( 160, (int) self::opt( $field, 'height', 320 ) ),
		);
		echo '<div' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="brik-cf-map-search"><div class="brik-cf-input-icon">' . brik_icon( 'map-pin', 'brik-cf-icon' ) . '<input type="text" class="brik-cf-input brik-cf-map-address" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '[address]" value="' . esc_attr( $addr ) . '" placeholder="' . esc_attr__( 'Search for an address', 'brik-builder' ) . '" autocomplete="off"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="brik-cf-btn brik-cf-btn-outline brik-cf-map-find">' . esc_html__( 'Find', 'brik-builder' ) . '</button></div>';
		echo '<div class="brik-cf-map-coords"><label><span>' . esc_html__( 'Latitude', 'brik-builder' ) . '</span><input type="number" step="any" min="-90" max="90" class="brik-cf-input brik-cf-map-lat" name="' . esc_attr( $name ) . '[lat]" value="' . esc_attr( $lat ) . '"></label>';
		echo '<label><span>' . esc_html__( 'Longitude', 'brik-builder' ) . '</span><input type="number" step="any" min="-180" max="180" class="brik-cf-input brik-cf-map-lng" name="' . esc_attr( $name ) . '[lng]" value="' . esc_attr( $lng ) . '"></label>';
		echo '<input type="hidden" class="brik-cf-map-zoom" name="' . esc_attr( $name ) . '[zoom]" value="' . esc_attr( (string) $zoom ) . '"></div>';
		echo '<div class="brik-cf-map-preview" hidden><iframe title="' . esc_attr__( 'Map preview', 'brik-builder' ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
		echo '</div>';
	}

	private static function repeater_control( array $field, array $rows, $name, $id ) {
		$subs   = $field['options']['sub_fields'];
		$layout = self::opt( $field, 'layout', 'block' );
		$label  = (string) self::opt( $field, 'button_label', '' );
		$token  = '__i_' . $field['key'] . '__';
		$attrs  = array(
			'class'      => 'brik-cf-repeater brik-cf-layout-' . $layout,
			'data-token' => $token,
			'data-next'  => (string) count( $rows ),
			'id'         => $id,
		);
		echo '<div' . brik_attrs( $attrs ) . '>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="">';
		if ( 'table' === $layout ) {
			echo '<div class="brik-cf-table-head" aria-hidden="true"><span class="brik-cf-table-spacer"></span>';
			foreach ( $subs as $sub ) {
				if ( 'tab' !== $sub['type'] ) {
					echo '<span class="brik-cf-table-th" style="--brik-cf-w:' . (int) $sub['width'] . '%">' . esc_html( $sub['label'] ) . ( $sub['required'] ? '<span class="brik-cf-required">*</span>' : '' ) . '</span>';
				}
			}
			echo '<span class="brik-cf-table-spacer"></span></div>';
		}
		echo '<ol class="brik-cf-rows brik-cf-sortable">';
		foreach ( array_values( $rows ) as $i => $row ) {
			self::row( $field, is_array( $row ) ? $row : array(), $name . '[' . $i . ']', $id . '-' . $i, $i );
		}
		echo '</ol>';
		echo '<div class="brik-cf-empty"' . ( $rows ? ' hidden' : '' ) . '>' . esc_html__( 'No rows yet.', 'brik-builder' ) . '</div>';
		echo '<div class="brik-cf-repeater-bar"><button type="button" class="brik-cf-btn brik-cf-btn-outline brik-cf-row-add">' . brik_icon( 'plus', 'brik-cf-icon' ) . esc_html( '' !== $label ? $label : __( 'Add row', 'brik-builder' ) ) . '</button><span class="brik-cf-count"></span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<template class="brik-cf-row-template">';
		$defaults = array();
		foreach ( $subs as $sub ) {
			if ( Fields::has_value( $sub['type'] ) && '' !== $sub['default'] ) {
				$defaults[ $sub['name'] ] = $sub['default'];
			}
		}
		self::row( $field, $defaults, $name . '[' . $token . ']', $id . '-' . $token, -1 );
		echo '</template></div>';
	}

	private static function row( array $field, array $row, $name, $id, $index ) {
		$values = array();
		foreach ( $field['options']['sub_fields'] as $sub ) {
			if ( Fields::has_value( $sub['type'] ) ) {
				$values[ $sub['key'] ] = array_key_exists( $sub['name'], $row ) ? $row[ $sub['name'] ] : ( '' !== $sub['default'] ? $sub['default'] : Fields::empty_value( $sub ) );
			}
		}
		echo '<li class="brik-cf-row" data-prefix="' . esc_attr( $name ) . '">';
		echo '<div class="brik-cf-row-head">';
		echo '<span class="brik-cf-row-handle" draggable="true" title="' . esc_attr__( 'Drag to reorder', 'brik-builder' ) . '">' . brik_icon( 'grip-vertical', 'brik-cf-icon' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<span class="brik-cf-row-number">' . ( $index >= 0 ? (int) $index + 1 : '' ) . '</span>';
		echo '<span class="brik-cf-row-title"></span>';
		echo '<span class="brik-cf-row-actions">';
		echo '<button type="button" class="brik-cf-icon-btn brik-cf-row-toggle" aria-expanded="true" aria-label="' . esc_attr__( 'Collapse row', 'brik-builder' ) . '">' . brik_icon( 'chevron-down', 'brik-cf-icon' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="brik-cf-icon-btn brik-cf-row-duplicate" aria-label="' . esc_attr__( 'Duplicate row', 'brik-builder' ) . '">' . brik_icon( 'copy', 'brik-cf-icon' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<button type="button" class="brik-cf-icon-btn brik-cf-row-remove" aria-label="' . esc_attr__( 'Remove row', 'brik-builder' ) . '">' . brik_icon( 'trash-2', 'brik-cf-icon' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</span></div><div class="brik-cf-row-body">';
		self::render_fields( $field['options']['sub_fields'], $values, $name, $id );
		echo '</div></li>';
	}
}
