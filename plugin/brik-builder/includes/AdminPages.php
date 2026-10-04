<?php
namespace Brik;

defined( 'ABSPATH' ) || exit;

/**
 * Screens under the Brik admin menu.
 */
final class AdminPages {

	const DOCS = 'https://github.com/dilukangelosl/brik';

	/**
	 * Post types that can be offered in settings: anything with an edit screen.
	 */
	public static function buildable_types() {
		$out = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation', ThemeBuilder::POST_TYPE, Library::POST_TYPE, Forms::POST_TYPE ), true ) ) {
				continue;
			}
			if ( ! post_type_supports( $type->name, 'editor' ) && ! $type->public ) {
				continue;
			}
			$out[ $type->name ] = $type->labels->name;
		}
		return $out;
	}

	private static function header( $title, $subtitle = '', $actions = '' ) {
		?>
		<div class="brik-admin-header">
			<div>
				<h1 class="brik-admin-title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="brik-admin-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $actions ) : ?>
				<div class="brik-admin-actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts by the caller. ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification -- display only.
		if ( empty( $_GET['brik_notice'] ) ) {
			return;
		}
		$key   = sanitize_key( wp_unslash( $_GET['brik_notice'] ) );
		$count = isset( $_GET['count'] ) ? absint( $_GET['count'] ) : 0;
		// phpcs:enable
		$messages = array(
			'saved'         => array( 'success', __( 'Settings saved.', 'brik-builder' ) ),
			'duplicated'    => array( 'success', __( 'Template duplicated as a draft.', 'brik-builder' ) ),
			'deleted'       => array( 'success', __( 'Moved to the trash.', 'brik-builder' ) ),
			/* translators: %d: number of imported items */
			'imported'      => array( 'success', sprintf( _n( '%d item imported.', '%d items imported.', $count, 'brik-builder' ), $count ) ),
			'import_failed' => array( 'error', __( 'That file could not be imported. Choose a Brik JSON export.', 'brik-builder' ) ),
		);
		if ( isset( $messages[ $key ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $key ][0] ), esc_html( $messages[ $key ][1] ) );
		}
	}

	private static function count_posts( array $args ) {
		$query = new \WP_Query(
			array_merge(
				array(
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				),
				$args
			)
		);
		return (int) $query->found_posts;
	}

	/* ---------------------------------------------------------------------
	 * Dashboard.
	 * ------------------------------------------------------------------- */

	public static function dashboard() {
		$types = array_values( array_diff( Plugin::post_types(), array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ) ) );
		$built = self::count_posts(
			array(
				'post_type'  => $types ? $types : 'page',
				'meta_key'   => Data::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$templates = self::count_posts(
			array(
				'post_type'   => ThemeBuilder::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$library = self::count_posts(
			array(
				'post_type'   => Library::POST_TYPE,
				'post_status' => 'publish',
			)
		);
		$submissions = post_type_exists( Forms::POST_TYPE ) && current_user_can( 'manage_options' ) ? self::count_posts( array( 'post_type' => Forms::POST_TYPE, 'post_status' => 'any' ) ) : null;
		$recent      = get_posts(
			array(
				'post_type'      => $types ? $types : 'page',
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'meta_key'       => Data::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
				'perm'           => 'editable',
			)
		);
		$page_type = get_post_type_object( 'page' );
		$can_page  = in_array( 'page', Plugin::post_types(), true ) && $page_type && current_user_can( $page_type->cap->create_posts );
		?>
		<div class="wrap brik-admin">
			<?php self::header( __( 'Welcome to Brik', 'brik-builder' ), __( 'Build pages, headers and footers visually, with components that follow the shadcn/ui design language.', 'brik-builder' ) ); ?>

			<div class="brik-grid brik-grid-4">
				<?php if ( $can_page ) : ?>
					<a class="brik-card brik-action" href="<?php echo esc_url( Admin::new_url( 'page' ) ); ?>">
						<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'New page with Brik', 'brik-builder' ); ?></strong>
						<span><?php esc_html_e( 'Start from a blank canvas in the visual builder.', 'brik-builder' ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( current_user_can( 'edit_theme_options' ) ) : ?>
					<a class="brik-card brik-action" href="<?php echo esc_url( admin_url( 'admin.php?page=brik-theme-builder' ) ); ?>">
						<span class="dashicons dashicons-layout" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Open theme builder', 'brik-builder' ); ?></strong>
						<span><?php esc_html_e( 'Design the header, footer and post templates of your site.', 'brik-builder' ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( current_user_can( 'edit_pages' ) ) : ?>
					<a class="brik-card brik-action" href="<?php echo esc_url( admin_url( 'admin.php?page=brik-library' ) ); ?>">
						<span class="dashicons dashicons-portfolio" aria-hidden="true"></span>
						<strong><?php esc_html_e( 'Library', 'brik-builder' ); ?></strong>
						<span><?php esc_html_e( 'Saved layouts, sections and global elements.', 'brik-builder' ); ?></span>
					</a>
				<?php endif; ?>
				<a class="brik-card brik-action" href="<?php echo esc_url( admin_url( 'admin.php?page=brik-mcp' ) ); ?>">
					<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
					<strong><?php esc_html_e( 'Connect AI', 'brik-builder' ); ?></strong>
					<span><?php esc_html_e( 'Let Claude, Cursor or any MCP client build with Brik.', 'brik-builder' ); ?></span>
				</a>
			</div>

			<div class="brik-grid brik-grid-main">
				<div class="brik-card">
					<h2 class="brik-card-title"><?php esc_html_e( 'Recently edited with Brik', 'brik-builder' ); ?></h2>
					<?php if ( $recent ) : ?>
						<ul class="brik-list">
							<?php foreach ( $recent as $post ) : ?>
								<li>
									<span>
										<a href="<?php echo esc_url( Builder::url( $post->ID ) ); ?>"><?php echo esc_html( _draft_or_post_title( $post ) ); ?></a>
										<span class="brik-muted"><?php echo esc_html( get_post_type_object( $post->post_type )->labels->singular_name ); ?> · <?php echo esc_html( 'publish' === $post->post_status ? __( 'Published', 'brik-builder' ) : get_post_status_object( $post->post_status )->label ); ?></span>
									</span>
									<span class="brik-muted">
										<?php
										/* translators: %s: human time difference */
										echo esc_html( sprintf( __( '%s ago', 'brik-builder' ), human_time_diff( get_post_modified_time( 'U', true, $post ) ) ) );
										?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="brik-muted"><?php esc_html_e( 'Nothing yet. Create your first page to get started.', 'brik-builder' ); ?></p>
					<?php endif; ?>
				</div>

				<div class="brik-stack">
					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( 'At a glance', 'brik-builder' ); ?></h2>
						<dl class="brik-stats">
							<div><dt><?php esc_html_e( 'Pages and posts built with Brik', 'brik-builder' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $built ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Active templates', 'brik-builder' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $templates ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Library items', 'brik-builder' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $library ) ); ?></dd></div>
							<?php if ( null !== $submissions ) : ?>
								<div><dt><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Forms::POST_TYPE ) ); ?>"><?php esc_html_e( 'Form submissions', 'brik-builder' ); ?></a></dt><dd><?php echo esc_html( number_format_i18n( $submissions ) ); ?></dd></div>
							<?php endif; ?>
						</dl>
					</div>
					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( 'Learn', 'brik-builder' ); ?></h2>
						<ul class="brik-links">
							<li><a href="<?php echo esc_url( self::DOCS . '#readme' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Getting started', 'brik-builder' ); ?></a></li>
							<li><a href="<?php echo esc_url( self::DOCS . '/blob/main/docs/MODULES.md' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Writing modules', 'brik-builder' ); ?></a></li>
							<li><a href="<?php echo esc_url( self::DOCS . '/blob/main/docs/MCP.md' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'MCP server and AI clients', 'brik-builder' ); ?></a></li>
							<li><a href="<?php echo esc_url( self::DOCS . '/issues' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Report an issue', 'brik-builder' ); ?></a></li>
						</ul>
						<p class="brik-muted brik-version">
							<?php
							/* translators: %s: version number */
							echo esc_html( sprintf( __( 'Brik %s', 'brik-builder' ), BRIK_VERSION ) );
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Theme builder.
	 * ------------------------------------------------------------------- */

	/**
	 * Conditions with the titles of referenced items, for the condition editor.
	 */
	private static function conditions_for_js( $id ) {
		$rules = ThemeBuilder::conditions( $id );
		foreach ( $rules as &$rule ) {
			$items = array();
			foreach ( isset( $rule['ids'] ) ? (array) $rule['ids'] : array() as $ref ) {
				if ( in_array( $rule['rule'], array( 'term', 'in_term' ), true ) ) {
					$term  = get_term( $ref );
					$title = $term && ! is_wp_error( $term ) ? $term->name : '#' . $ref;
				} elseif ( 'author' === $rule['rule'] ) {
					$user  = get_userdata( $ref );
					$title = $user ? $user->display_name : '#' . $ref;
				} else {
					$title = get_the_title( $ref );
					$title = '' !== $title ? $title : '#' . $ref;
				}
				$items[] = array(
					'id'    => (int) $ref,
					'title' => $title,
				);
			}
			$rule['items'] = $items;
		}
		return $rules;
	}

	public static function theme_builder() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage templates.', 'brik-builder' ), 403 );
		}
		$templates = get_posts(
			array(
				'post_type'      => ThemeBuilder::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'orderby'        => 'menu_order date',
				'order'          => 'ASC',
			)
		);
		$by_area = array_fill_keys( array_keys( ThemeBuilder::areas() ), array() );
		foreach ( $templates as $template ) {
			$by_area[ ThemeBuilder::area( $template->ID ) ][] = $template;
		}
		$hints = array(
			'header' => __( 'Replaces the theme header where its conditions match.', 'brik-builder' ),
			'body'   => __( 'Replaces the page content area, e.g. a single post or archive layout.', 'brik-builder' ),
			'footer' => __( 'Replaces the theme footer where its conditions match.', 'brik-builder' ),
		);
		?>
		<div class="wrap brik-admin">
			<?php
			self::header( __( 'Theme Builder', 'brik-builder' ), __( 'Templates apply wherever their conditions match. Exclusions win, and the most specific template wins over general ones.', 'brik-builder' ) );
			self::notice();
			?>
			<div class="brik-grid brik-grid-3 brik-areas">
				<?php foreach ( ThemeBuilder::areas() as $area => $label ) : ?>
					<section class="brik-card brik-area" aria-labelledby="brik-area-<?php echo esc_attr( $area ); ?>">
						<div class="brik-area-head">
							<div>
								<h2 class="brik-card-title" id="brik-area-<?php echo esc_attr( $area ); ?>"><?php echo esc_html( $label ); ?></h2>
								<p class="brik-muted"><?php echo esc_html( $hints[ $area ] ); ?></p>
							</div>
						</div>

						<?php if ( ! $by_area[ $area ] ) : ?>
							<div class="brik-empty"><?php esc_html_e( 'No templates yet.', 'brik-builder' ); ?></div>
						<?php endif; ?>

						<?php foreach ( $by_area[ $area ] as $template ) : ?>
							<?php self::template_item( $template ); ?>
						<?php endforeach; ?>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="brik-area-add">
							<?php wp_nonce_field( 'brik_template_new' ); ?>
							<input type="hidden" name="action" value="brik_template_new">
							<input type="hidden" name="area" value="<?php echo esc_attr( $area ); ?>">
							<button type="submit" class="button brik-button-outline">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<?php
								/* translators: %s: area name, lowercase (header, body, footer) */
								echo esc_html( sprintf( __( 'Add %s', 'brik-builder' ), strtolower( $label ) ) );
								?>
							</button>
						</form>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	private static function template_item( \WP_Post $template ) {
		$id      = $template->ID;
		$active  = 'publish' === $template->post_status;
		$rules   = self::conditions_for_js( $id );
		$summary = McpTools::describe_conditions( ThemeBuilder::conditions( $id ) );
		?>
		<div class="brik-template" data-id="<?php echo esc_attr( $id ); ?>" data-conditions="<?php echo esc_attr( wp_json_encode( $rules ) ); ?>">
			<div class="brik-template-row">
				<div class="brik-template-main">
					<a class="brik-template-title" href="<?php echo esc_url( Builder::url( $id ) ); ?>"><?php echo esc_html( _draft_or_post_title( $template ) ); ?></a>
					<p class="brik-template-summary"><?php echo esc_html( $summary ); ?></p>
				</div>
				<label class="brik-switch" title="<?php esc_attr_e( 'Active (published) or draft', 'brik-builder' ); ?>">
					<input type="checkbox" class="brik-status-toggle" <?php checked( $active ); ?>>
					<span class="brik-switch-track" aria-hidden="true"></span>
					<span class="brik-switch-label"><?php echo $active ? esc_html__( 'Active', 'brik-builder' ) : esc_html__( 'Draft', 'brik-builder' ); ?></span>
				</label>
			</div>
			<div class="brik-template-actions">
				<a class="button button-small button-primary" href="<?php echo esc_url( Builder::url( $id ) ); ?>"><?php esc_html_e( 'Edit with Brik', 'brik-builder' ); ?></a>
				<button type="button" class="button button-small brik-edit-conditions"><?php esc_html_e( 'Conditions', 'brik-builder' ); ?></button>
				<button type="button" class="button button-small brik-rename" data-title="<?php echo esc_attr( $template->post_title ); ?>"><?php esc_html_e( 'Rename', 'brik-builder' ); ?></button>
				<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brik_template_duplicate&id=' . $id ), 'brik_template_duplicate_' . $id ) ); ?>"><?php esc_html_e( 'Duplicate', 'brik-builder' ); ?></a>
				<?php if ( current_user_can( 'delete_post', $id ) ) : ?>
					<a class="button button-small brik-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brik_template_delete&id=' . $id ), 'brik_template_delete_' . $id ) ); ?>"><?php esc_html_e( 'Delete', 'brik-builder' ); ?></a>
				<?php endif; ?>
			</div>
			<div class="brik-conditions-editor" hidden></div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Library.
	 * ------------------------------------------------------------------- */

	public static function library() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage the library.', 'brik-builder' ), 403 );
		}
		$items = Library::items();
		$kinds = Library::kinds();
		$all   = $items ? '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brik_library_export&id=0' ), 'brik_library_export_0' ) ) . '">' . esc_html__( 'Export all', 'brik-builder' ) . '</a>' : '';
		?>
		<div class="wrap brik-admin">
			<?php
			self::header( __( 'Library', 'brik-builder' ), __( 'Layouts, sections, rows and modules saved from the builder. Global items update everywhere they are used.', 'brik-builder' ), $all );
			self::notice();
			?>
			<div class="brik-card brik-card-flush">
				<?php if ( $items ) : ?>
					<table class="brik-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Name', 'brik-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Type', 'brik-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Modified', 'brik-builder' ); ?></th>
								<th scope="col" class="brik-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'brik-builder' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td>
										<a class="brik-strong" href="<?php echo esc_url( Builder::url( $item['id'] ) ); ?>"><?php echo esc_html( '' !== $item['title'] ? $item['title'] : __( '(no title)', 'brik-builder' ) ); ?></a>
										<?php if ( $item['global'] ) : ?>
											<span class="brik-badge brik-badge-primary"><?php esc_html_e( 'Global', 'brik-builder' ); ?></span>
										<?php endif; ?>
									</td>
									<td><span class="brik-badge"><?php echo esc_html( isset( $kinds[ $item['kind'] ] ) ? $kinds[ $item['kind'] ] : $item['kind'] ); ?></span></td>
									<td class="brik-muted">
										<?php
										/* translators: %s: human time difference */
										echo esc_html( sprintf( __( '%s ago', 'brik-builder' ), human_time_diff( strtotime( $item['modified'] ) ) ) );
										?>
									</td>
									<td class="brik-col-actions">
										<?php if ( current_user_can( 'edit_post', $item['id'] ) ) : ?>
											<a class="button button-small button-primary" href="<?php echo esc_url( Builder::url( $item['id'] ) ); ?>"><?php esc_html_e( 'Edit with Brik', 'brik-builder' ); ?></a>
											<a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brik_library_export&id=' . $item['id'] ), 'brik_library_export_' . $item['id'] ) ); ?>"><?php esc_html_e( 'Export', 'brik-builder' ); ?></a>
										<?php endif; ?>
										<?php if ( current_user_can( 'delete_post', $item['id'] ) ) : ?>
											<a class="button button-small brik-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=brik_library_delete&id=' . $item['id'] ), 'brik_library_delete_' . $item['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'brik-builder' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="brik-empty brik-empty-lg">
						<strong><?php esc_html_e( 'Your library is empty', 'brik-builder' ); ?></strong>
						<span><?php esc_html_e( 'In the builder, choose “Save to library” on any section, row or module, or import a JSON file below.', 'brik-builder' ); ?></span>
					</div>
				<?php endif; ?>
			</div>

			<div class="brik-card brik-import">
				<h2 class="brik-card-title"><?php esc_html_e( 'Import', 'brik-builder' ); ?></h2>
				<p class="brik-muted"><?php esc_html_e( 'Upload a JSON file exported from a Brik library.', 'brik-builder' ); ?></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'brik_library_import' ); ?>
					<input type="hidden" name="action" value="brik_library_import">
					<input type="file" name="brik_file" accept=".json,application/json" required>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'brik-builder' ); ?></button>
				</form>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Settings.
	 * ------------------------------------------------------------------- */

	public static function settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'brik-builder' ), 403 );
		}
		$enabled = (array) Settings::get( 'post_types' );
		?>
		<div class="wrap brik-admin">
			<?php
			self::header( __( 'Settings', 'brik-builder' ), __( 'Colors, fonts and other design settings live in the builder under Global settings.', 'brik-builder' ) );
			self::notice();
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="brik-card brik-form">
				<?php wp_nonce_field( 'brik_save_settings' ); ?>
				<input type="hidden" name="action" value="brik_save_settings">

				<fieldset class="brik-field">
					<legend class="brik-field-label"><?php esc_html_e( 'Post types', 'brik-builder' ); ?></legend>
					<p class="brik-muted"><?php esc_html_e( 'Content types that can be edited with Brik.', 'brik-builder' ); ?></p>
					<div class="brik-checks">
						<?php foreach ( self::buildable_types() as $name => $label ) : ?>
							<label class="brik-check">
								<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $name ); ?>" <?php checked( in_array( $name, $enabled, true ) ); ?>>
								<span><?php echo esc_html( $label ); ?></span>
								<code><?php echo esc_html( $name ); ?></code>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<div class="brik-field">
					<label class="brik-toggle-row">
						<span>
							<span class="brik-field-label"><?php esc_html_e( 'Google Fonts', 'brik-builder' ); ?></span>
							<span class="brik-muted"><?php esc_html_e( 'Load the fonts chosen in the builder from Google Fonts. Turn off if you self-host fonts or need to avoid third-party requests.', 'brik-builder' ); ?></span>
						</span>
						<span class="brik-switch">
							<input type="checkbox" name="google_fonts" value="1" <?php checked( Settings::get( 'google_fonts' ) ); ?>>
							<span class="brik-switch-track" aria-hidden="true"></span>
						</span>
					</label>
				</div>

				<div class="brik-field">
					<label class="brik-toggle-row">
						<span>
							<span class="brik-field-label"><?php esc_html_e( 'MCP server', 'brik-builder' ); ?></span>
							<span class="brik-muted">
								<?php esc_html_e( 'Allow AI clients to build and edit content through the Model Context Protocol endpoint. Each request still needs an application password and is limited to what that user may do.', 'brik-builder' ); ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=brik-mcp' ) ); ?>"><?php esc_html_e( 'Connect a client', 'brik-builder' ); ?></a>
							</span>
						</span>
						<span class="brik-switch">
							<input type="checkbox" name="mcp_enabled" value="1" <?php checked( Settings::get( 'mcp_enabled' ) ); ?>>
							<span class="brik-switch-track" aria-hidden="true"></span>
						</span>
					</label>
				</div>

				<?php do_action( 'brik/settings_form' ); ?>

				<p class="brik-form-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'brik-builder' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Connect AI (MCP).
	 * ------------------------------------------------------------------- */

	public static function mcp() {
		$user      = wp_get_current_user();
		$url       = Mcp::url();
		$available = wp_is_application_passwords_available_for_user( $user );
		$https     = is_ssl() || 0 === strpos( $url, 'https://' );
		$local     = 'local' === wp_get_environment_type();
		$enabled   = (bool) Settings::get( 'mcp_enabled' );
		?>
		<div class="wrap brik-admin">
			<?php self::header( __( 'Connect AI', 'brik-builder' ), __( 'Brik includes an MCP server, so AI assistants such as Claude, Cursor or VS Code can create pages, templates and menus for you.', 'brik-builder' ) ); ?>

			<?php if ( ! $enabled ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php esc_html_e( 'The MCP server is turned off.', 'brik-builder' ); ?>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=brik-settings' ) ); ?>"><?php esc_html_e( 'Turn it on in Settings', 'brik-builder' ); ?></a>
					<?php endif; ?>
				</p></div>
			<?php endif; ?>

			<div class="brik-grid brik-grid-main">
				<div class="brik-stack">
					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( '1. Endpoint', 'brik-builder' ); ?></h2>
						<p class="brik-muted"><?php esc_html_e( 'Streamable HTTP transport, JSON responses.', 'brik-builder' ); ?></p>
						<div class="brik-copy">
							<code id="brik-mcp-url"><?php echo esc_html( $url ); ?></code>
							<button type="button" class="button button-small brik-copy-button" data-copy="#brik-mcp-url"><?php esc_html_e( 'Copy', 'brik-builder' ); ?></button>
						</div>
					</div>

					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( '2. Create an application password', 'brik-builder' ); ?></h2>
						<p class="brik-muted">
							<?php
							printf(
								/* translators: %s: user login */
								esc_html__( 'Clients sign in as you (%s) with a WordPress application password, sent as HTTP Basic authentication. The client can do exactly what your account can do; revoke the password any time from your profile.', 'brik-builder' ),
								'<strong>' . esc_html( $user->user_login ) . '</strong>'
							);
							?>
						</p>
						<?php if ( ! $https && ! $local ) : ?>
							<div class="notice notice-warning inline"><p><?php esc_html_e( 'Application passwords require HTTPS. This site is not using HTTPS, so WordPress will refuse them unless the environment type is "local" (WP_ENVIRONMENT_TYPE).', 'brik-builder' ); ?></p></div>
						<?php elseif ( ! $https && $local ) : ?>
							<div class="notice notice-info inline"><p><?php esc_html_e( 'This is a local environment, so application passwords work over plain HTTP. Production sites need HTTPS.', 'brik-builder' ); ?></p></div>
						<?php endif; ?>

						<?php if ( $available ) : ?>
							<div class="brik-inline-form">
								<label for="brik-app-name" class="screen-reader-text"><?php esc_html_e( 'Password name', 'brik-builder' ); ?></label>
								<input type="text" id="brik-app-name" class="regular-text" value="<?php echo esc_attr( sprintf( /* translators: %s: date */ __( 'Brik MCP (%s)', 'brik-builder' ), wp_date( get_option( 'date_format' ) ) ) ); ?>">
								<button type="button" class="button button-primary" id="brik-create-password"><?php esc_html_e( 'Create password', 'brik-builder' ); ?></button>
							</div>
							<p class="brik-error" id="brik-password-error" hidden></p>
						<?php else : ?>
							<div class="notice notice-error inline"><p><?php esc_html_e( 'Application passwords are not available for your account on this site (they may be disabled by a plugin or require HTTPS).', 'brik-builder' ); ?></p></div>
						<?php endif; ?>
						<p><a href="<?php echo esc_url( admin_url( 'profile.php#application-passwords-section' ) ); ?>"><?php esc_html_e( 'Manage application passwords', 'brik-builder' ); ?></a></p>
					</div>

					<div class="brik-card" id="brik-snippets" hidden>
						<h2 class="brik-card-title"><?php esc_html_e( '3. Add Brik to your client', 'brik-builder' ); ?></h2>
						<div class="notice notice-warning inline"><p><?php esc_html_e( 'The password is shown only once and is included in the snippets below. Treat them like a password.', 'brik-builder' ); ?></p></div>
						<div class="brik-tabs" role="tablist">
							<button type="button" role="tab" class="brik-tab" aria-selected="true" data-tab="claude-code"><?php esc_html_e( 'Claude Code', 'brik-builder' ); ?></button>
							<button type="button" role="tab" class="brik-tab" aria-selected="false" data-tab="desktop"><?php esc_html_e( 'Claude Desktop', 'brik-builder' ); ?></button>
							<button type="button" role="tab" class="brik-tab" aria-selected="false" data-tab="cursor"><?php esc_html_e( 'Cursor / VS Code', 'brik-builder' ); ?></button>
							<button type="button" role="tab" class="brik-tab" aria-selected="false" data-tab="curl"><?php esc_html_e( 'Test with curl', 'brik-builder' ); ?></button>
						</div>
						<div class="brik-tab-panel" data-panel="claude-code">
							<p class="brik-muted"><?php esc_html_e( 'Run in a terminal:', 'brik-builder' ); ?></p>
							<pre class="brik-code" id="brik-snippet-claude-code"></pre>
						</div>
						<div class="brik-tab-panel" data-panel="desktop" hidden>
							<p class="brik-muted"><?php esc_html_e( 'Add to claude_desktop_config.json (or any client that only speaks stdio). mcp-remote bridges to the HTTP endpoint and needs Node.js.', 'brik-builder' ); ?></p>
							<pre class="brik-code" id="brik-snippet-desktop"></pre>
						</div>
						<div class="brik-tab-panel" data-panel="cursor" hidden>
							<p class="brik-muted"><?php esc_html_e( 'Cursor: ~/.cursor/mcp.json. VS Code: .vscode/mcp.json (use "servers" instead of "mcpServers" and add "type": "http").', 'brik-builder' ); ?></p>
							<pre class="brik-code" id="brik-snippet-cursor"></pre>
						</div>
						<div class="brik-tab-panel" data-panel="curl" hidden>
							<p class="brik-muted"><?php esc_html_e( 'Lists the available tools:', 'brik-builder' ); ?></p>
							<pre class="brik-code" id="brik-snippet-curl"></pre>
						</div>
						<button type="button" class="button brik-copy-button" data-copy-active="1"><?php esc_html_e( 'Copy', 'brik-builder' ); ?></button>
					</div>
				</div>

				<div class="brik-stack">
					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( 'What clients can do', 'brik-builder' ); ?></h2>
						<ul class="brik-checklist">
							<li><?php esc_html_e( 'Create and edit pages from a description', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( 'Insert, move and restyle individual elements', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( 'Build headers, footers and templates', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( 'Create menus and import images', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( 'Change global colors, fonts and presets', 'brik-builder' ); ?></li>
						</ul>
					</div>
					<div class="brik-card">
						<h2 class="brik-card-title"><?php esc_html_e( 'Try asking', 'brik-builder' ); ?></h2>
						<ul class="brik-prompts">
							<li><?php esc_html_e( '“Build a landing page for my bakery with a hero, menu highlights, opening hours and a contact form. Keep it as a draft.”', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( '“Create a sticky header with our logo text, the main menu and a Book now button.”', 'brik-builder' ); ?></li>
							<li><?php esc_html_e( '“Switch the site to the zinc palette with a violet accent and rounder corners.”', 'brik-builder' ); ?></li>
						</ul>
						<p><a href="<?php echo esc_url( self::DOCS . '/blob/main/docs/MCP.md' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'MCP documentation', 'brik-builder' ); ?></a></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Content (post types, taxonomies, field groups). The screen is a React app.
	 * ------------------------------------------------------------------- */

	public static function content() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage content types.', 'brik-builder' ), 403 );
		}
		?>
		<div class="wrap brik-admin brik-content-wrap">
			<div id="brik-content-app" class="brik-content-app">
				<noscript><?php esc_html_e( 'The content editor needs JavaScript.', 'brik-builder' ); ?></noscript>
			</div>
		</div>
		<?php
	}
}
