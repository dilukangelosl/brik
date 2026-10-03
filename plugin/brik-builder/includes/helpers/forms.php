<?php
/**
 * Form helpers shared by the contact form, signup, search and login modules.
 *
 * The field schema built here is used twice: to render the form and, on submission,
 * to validate it against the attributes stored on the server.
 *
 * @package Brik
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field types a form can contain.
 */
function brik_form_field_types() {
	return array(
		'text'     => __( 'Text', 'brik-builder' ),
		'email'    => __( 'Email', 'brik-builder' ),
		'tel'      => __( 'Phone', 'brik-builder' ),
		'url'      => __( 'URL', 'brik-builder' ),
		'number'   => __( 'Number', 'brik-builder' ),
		'textarea' => __( 'Paragraph', 'brik-builder' ),
		'select'   => __( 'Dropdown', 'brik-builder' ),
		'checkbox' => __( 'Checkboxes', 'brik-builder' ),
		'radio'    => __( 'Radio buttons', 'brik-builder' ),
		'date'     => __( 'Date', 'brik-builder' ),
		'password' => __( 'Password', 'brik-builder' ),
		'file'     => __( 'File upload', 'brik-builder' ),
		'image'    => __( 'Image upload', 'brik-builder' ),
		'hidden'   => __( 'Hidden', 'brik-builder' ),
		'consent'  => __( 'Consent checkbox', 'brik-builder' ),
	);
}

/**
 * Where the options of a select, radio or checkbox field come from: typed in, the terms of a
 * taxonomy (value = term id) or the published posts of a post type (value = post id).
 */
function brik_form_options_sources() {
	$out = array( 'manual' => __( 'Typed in below', 'brik-builder' ) );
	if ( did_action( 'init' ) ) {
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tax ) {
			if ( 'post_format' !== $tax->name ) {
				/* translators: %s: taxonomy name */
				$out[ 'taxonomy:' . $tax->name ] = sprintf( __( 'Terms: %s', 'brik-builder' ), $tax->labels->name );
			}
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' !== $type->name ) {
				/* translators: %s: post type name */
				$out[ 'post_type:' . $type->name ] = sprintf( __( 'Posts: %s', 'brik-builder' ), $type->labels->name );
			}
		}
	}
	return $out;
}

/**
 * Choices (value => label) for an options source, or null for manual options.
 */
function brik_form_source_choices( $source ) {
	$source = (string) $source;
	if ( 0 === strpos( $source, 'taxonomy:' ) ) {
		$tax = sanitize_key( substr( $source, 9 ) );
		if ( ! taxonomy_exists( $tax ) || ! is_taxonomy_viewable( $tax ) ) {
			return array();
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $tax,
				'hide_empty' => false,
				'number'     => 200,
				'orderby'    => 'name',
			)
		);
		$out = array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$out[ (string) $term->term_id ] = $term->name;
		}
		return $out;
	}
	if ( 0 === strpos( $source, 'post_type:' ) ) {
		$type = sanitize_key( substr( $source, 10 ) );
		if ( ! post_type_exists( $type ) || ! is_post_type_viewable( $type ) ) {
			return array();
		}
		$out = array();
		foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
			$out[ (string) $p->ID ] = wp_strip_all_tags( get_the_title( $p ) );
		}
		return $out;
	}
	return null;
}

/**
 * Field name from a label: "Your email" becomes "your_email".
 */
function brik_form_name( $label ) {
	$name = strtolower( remove_accents( wp_strip_all_tags( (string) $label ) ) );
	$name = trim( preg_replace( '/[^a-z0-9]+/', '_', $name ), '_' );
	return substr( $name, 0, 40 );
}

/**
 * Options of a select/radio/checkbox field, one per line.
 */
function brik_form_options( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( wp_strip_all_tags( $line ) );
		if ( '' !== $line && ! in_array( $line, $out, true ) ) {
			$out[] = $line;
		}
	}
	return $out;
}

/**
 * Normalize a fields repeater into a list of field definitions with unique names.
 */
function brik_form_schema( $items ) {
	$types  = brik_form_field_types();
	$fields = array();
	$used   = array();
	if ( ! is_array( $items ) ) {
		return $fields;
	}
	foreach ( $items as $i => $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item = wp_parse_args(
			$item,
			array(
				'label'       => '',
				'name'        => '',
				'type'        => 'text',
				'placeholder' => '',
				'options'     => '',
				'required'       => false,
				'width'          => 'full',
				'value'          => '',
				'options_source' => 'manual',
				'accept'         => '',
				'max_size'       => '',
			)
		);

		$type = isset( $types[ $item['type'] ] ) ? $item['type'] : 'text';
		$name = brik_form_name( '' !== trim( (string) $item['name'] ) ? $item['name'] : $item['label'] );
		if ( '' === $name ) {
			$name = 'field_' . ( $i + 1 );
		}
		$base = $name;
		$n    = 2;
		while ( isset( $used[ $name ] ) ) {
			$name = $base . '_' . $n++;
		}
		$used[ $name ] = true;

		$choices = null;
		$source  = 'manual';
		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
			$choices = brik_form_source_choices( $item['options_source'] );
			$source  = null === $choices ? 'manual' : (string) $item['options_source'];
		}
		if ( null === $choices ) {
			$options = brik_form_options( $item['options'] );
			$choices = array_combine( $options, $options );
		}

		$field = array(
			'name'        => $name,
			'label'       => (string) $item['label'],
			'type'        => $type,
			'placeholder' => (string) $item['placeholder'],
			'options'     => array_map( 'strval', array_keys( $choices ) ),
			'choices'     => $choices,
			'source'      => $source,
			'required'    => brik_form_bool( $item['required'] ) || 'consent' === $type,
			'width'       => 'half' === $item['width'] ? 'half' : 'full',
			'value'       => (string) $item['value'],
		);
		if ( in_array( $type, array( 'file', 'image' ), true ) ) {
			$field['accept']   = brik_form_accept( $type, $item['accept'] );
			$field['max_size'] = brik_form_max_bytes( $item['max_size'] );
		}
		$fields[] = $field;
	}
	return $fields;
}

/**
 * Toggle values arrive as bool, "1", "on" or "true" depending on the client that wrote them.
 */
function brik_form_bool( $value ) {
	if ( is_string( $value ) ) {
		return in_array( strtolower( $value ), array( '1', 'on', 'true', 'yes' ), true );
	}
	return (bool) $value;
}

/**
 * Allowed upload types of a file/image field as extension => mime, limited to what
 * WordPress itself allows. "accept" takes extensions or mime types (".pdf, image/*").
 */
function brik_form_accept( $type, $accept ) {
	$allowed = get_allowed_mime_types();
	$images  = array_filter(
		$allowed,
		static function ( $mime ) {
			return in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ), true );
		}
	);
	$wanted = array_filter( array_map( 'trim', explode( ',', strtolower( (string) $accept ) ) ) );
	if ( ! $wanted ) {
		if ( 'image' === $type ) {
			return $images;
		}
		$safe = array( 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'csv', 'rtf', 'zip' );
		$out  = $images;
		foreach ( $allowed as $exts => $mime ) {
			if ( array_intersect( explode( '|', $exts ), $safe ) ) {
				$out[ $exts ] = $mime;
			}
		}
		return $out;
	}
	$out = array();
	foreach ( ( 'image' === $type ? $images : $allowed ) as $exts => $mime ) {
		foreach ( $wanted as $w ) {
			$hit = '.' === $w[0] ? in_array( substr( $w, 1 ), explode( '|', $exts ), true )
				: ( '/*' === substr( $w, -2 ) ? 0 === strpos( $mime, substr( $w, 0, -1 ) ) : $mime === $w );
			if ( $hit ) {
				$out[ $exts ] = $mime;
			}
		}
	}
	return $out;
}

/**
 * Upload size limit in bytes from a megabyte setting (default 5 MB), capped by the server.
 */
function brik_form_max_bytes( $mb ) {
	$mb = is_numeric( $mb ) && $mb > 0 ? (float) $mb : 5;
	return (int) min( $mb * MB_IN_BYTES, wp_max_upload_size() );
}

/**
 * The form actions in effect. Without an explicit "actions" list the older toggles decide
 * (send_email, save_entries, webhook_url, redirect), so existing forms keep working.
 */
function brik_form_actions( array $a ) {
	$known = array( 'email', 'save_entry', 'webhook', 'create_post', 'update_post', 'register_user', 'redirect' );
	$list  = isset( $a['actions'] ) ? $a['actions'] : '';
	$list  = is_array( $list ) ? $list : explode( ',', (string) $list );
	$list  = array_values( array_intersect( $known, array_map( 'trim', array_map( 'strval', $list ) ) ) );
	if ( $list ) {
		return $list;
	}
	$out = array();
	if ( ! isset( $a['send_email'] ) || brik_form_bool( $a['send_email'] ) ) {
		$out[] = 'email';
	}
	if ( ! isset( $a['save_entries'] ) || brik_form_bool( $a['save_entries'] ) ) {
		$out[] = 'save_entry';
	}
	if ( ! empty( $a['webhook_url'] ) ) {
		$out[] = 'webhook';
	}
	$redirect = isset( $a['redirect'] ) ? ( is_array( $a['redirect'] ) ? ( isset( $a['redirect']['url'] ) ? $a['redirect']['url'] : '' ) : (string) $a['redirect'] ) : '';
	if ( '' !== trim( $redirect ) ) {
		$out[] = 'redirect';
	}
	return $out;
}

/**
 * Field → post mapping rows, validated: [ field, kind (title|content|excerpt|featured_image|meta|tax), name ].
 */
function brik_form_mapping( array $a ) {
	$out = array();
	foreach ( isset( $a['mapping'] ) && is_array( $a['mapping'] ) ? $a['mapping'] : array() as $row ) {
		if ( ! is_array( $row ) || empty( $row['field'] ) || empty( $row['target'] ) ) {
			continue;
		}
		$field  = brik_form_name( $row['field'] );
		$target = trim( (string) $row['target'] );
		if ( in_array( $target, array( 'title', 'content', 'excerpt', 'featured_image' ), true ) ) {
			$out[] = array( $field, $target, '' );
		} elseif ( preg_match( '/^meta:([A-Za-z0-9][A-Za-z0-9_\-]{0,63})$/', $target, $m ) ) {
			// Protected (underscore) keys are never written from a form.
			$out[] = array( $field, 'meta', $m[1] );
		} elseif ( preg_match( '/^tax:([a-z0-9_\-]{1,32})$/', $target, $m ) ) {
			$out[] = array( $field, 'tax', $m[1] );
		}
	}
	return $out;
}

/**
 * Post settings of a create/update form with defaults applied.
 */
function brik_form_post_settings( array $a ) {
	$type   = isset( $a['post_type'] ) && is_string( $a['post_type'] ) && '' !== $a['post_type'] ? sanitize_key( $a['post_type'] ) : 'post';
	$status = isset( $a['post_status'] ) && in_array( $a['post_status'], array( 'draft', 'pending', 'publish' ), true ) ? $a['post_status'] : 'pending';
	return array(
		'post_type'    => $type,
		'post_status'  => $status,
		'author'       => isset( $a['post_author'] ) && 'fixed' === $a['post_author'] ? 'fixed' : 'current',
		'author_id'    => isset( $a['post_author_id'] ) ? absint( $a['post_author_id'] ) : 0,
		'allow_guests' => isset( $a['allow_guests'] ) && brik_form_bool( $a['allow_guests'] ),
		'source'       => isset( $a['update_source'] ) && 'query' === $a['update_source'] ? 'query' : 'current',
	);
}

/**
 * Whether the current visitor may create a post through this form.
 *
 * Guests only when the form allows them; logged-in users need the post type's create
 * capability unless the form is open to everyone.
 *
 * @return true|WP_Error
 */
function brik_form_can_create( array $a ) {
	$s    = brik_form_post_settings( $a );
	$type = get_post_type_object( $s['post_type'] );
	if ( ! $type || in_array( $s['post_type'], array( 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'brik_submission', 'brik_layout', 'brik_template' ), true ) ) {
		return new WP_Error( 'brik_form_post_type', __( 'This form is not set up correctly.', 'brik-builder' ), array( 'status' => 500 ) );
	}
	if ( $s['allow_guests'] ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'brik_form_login', __( 'Please log in to submit this form.', 'brik-builder' ), array( 'status' => 403 ) );
	}
	if ( ! current_user_can( $type->cap->create_posts ) ) {
		return new WP_Error( 'brik_form_forbidden', __( 'Your account can\'t submit this form.', 'brik-builder' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Whether the current user may edit a post through this form.
 *
 * @return true|WP_Error
 */
function brik_form_can_update( array $a, $post_id ) {
	$s    = brik_form_post_settings( $a );
	$post = $post_id ? get_post( (int) $post_id ) : null;
	if ( ! $post || ! is_user_logged_in() || ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'brik_form_forbidden', __( 'You can\'t edit this item.', 'brik-builder' ), array( 'status' => 403 ) );
	}
	if ( ! empty( $a['post_type'] ) && $post->post_type !== $s['post_type'] ) {
		return new WP_Error( 'brik_form_forbidden', __( 'You can\'t edit this item.', 'brik-builder' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * A role new users may get from a form: never one that can manage the site or other
 * people's content. Anything else falls back to subscriber.
 */
function brik_form_safe_role( $role ) {
	$role   = sanitize_key( (string) $role );
	$object = $role ? get_role( $role ) : null;
	$risky  = array( 'manage_options', 'edit_others_posts', 'edit_others_pages', 'edit_users', 'create_users', 'promote_users', 'delete_users', 'unfiltered_html', 'install_plugins', 'activate_plugins', 'edit_plugins', 'edit_theme_options', 'switch_themes', 'manage_categories', 'moderate_comments', 'publish_pages' );
	if ( ! $object || in_array( $role, array( 'administrator', 'editor' ), true ) ) {
		return 'subscriber';
	}
	foreach ( $risky as $cap ) {
		if ( ! empty( $object->capabilities[ $cap ] ) ) {
			return 'subscriber';
		}
	}
	return $role;
}

/**
 * The post an update form edits while rendering: the current post or ?post_id=, and only
 * when the visitor may edit it.
 */
function brik_form_render_target( array $a, $ctx ) {
	if ( ! in_array( 'update_post', brik_form_actions( $a ), true ) || ! is_user_logged_in() ) {
		return 0;
	}
	$s  = brik_form_post_settings( $a );
	$id = 0;
	if ( 'query' === $s['source'] ) {
		$id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	} else {
		$post = brik_site_post( $ctx );
		$id   = $post ? $post->ID : 0;
	}
	return $id && true === brik_form_can_update( $a, $id ) ? $id : 0;
}

/**
 * Current values of the mapped targets of a post, keyed by form field name.
 */
function brik_form_prefill( array $a, $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$out = array();
	foreach ( brik_form_mapping( $a ) as $map ) {
		list( $field, $kind, $name ) = $map;
		switch ( $kind ) {
			case 'title':
				$out[ $field ] = $post->post_title;
				break;
			case 'content':
				$out[ $field ] = wp_strip_all_tags( $post->post_content );
				break;
			case 'excerpt':
				$out[ $field ] = $post->post_excerpt;
				break;
			case 'meta':
				$value         = function_exists( 'brik_raw_field' ) && brik_field_object( $name, $post->ID ) ? brik_raw_field( $name, $post->ID ) : get_post_meta( $post->ID, $name, true );
				$out[ $field ] = is_array( $value ) ? array_map( 'strval', array_filter( $value, 'is_scalar' ) ) : ( is_scalar( $value ) ? (string) $value : '' );
				break;
			case 'tax':
				$terms = taxonomy_exists( $name ) ? wp_get_object_terms( $post->ID, $name ) : array();
				$list  = array();
				foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
					$list[] = array( (string) $term->term_id, $term->name );
				}
				$out[ $field ] = $list;
				break;
		}
	}
	return $out;
}

/**
 * Apply prefill values to a schema: sourced options match by id, typed ones by label.
 */
function brik_form_apply_prefill( array $fields, array $prefill ) {
	foreach ( $fields as &$f ) {
		if ( ! array_key_exists( $f['name'], $prefill ) || in_array( $f['type'], array( 'file', 'image', 'password', 'hidden' ), true ) ) {
			continue;
		}
		$value = $prefill[ $f['name'] ];
		if ( is_array( $value ) && $value && is_array( reset( $value ) ) ) {
			// Terms: [ id, name ] pairs.
			$value = array_map(
				static function ( $pair ) use ( $f ) {
					return 'manual' === $f['source'] ? $pair[1] : $pair[0];
				},
				$value
			);
		}
		$f['prefill'] = $value;
	}
	unset( $f );
	return $fields;
}

/**
 * Field schema of the signup module.
 */
function brik_signup_schema( array $a ) {
	$fields = array();
	if ( brik_form_bool( $a['show_name'] ) ) {
		$fields[] = array(
			'name'        => 'name',
			'label'       => __( 'Name', 'brik-builder' ),
			'type'        => 'text',
			'placeholder' => (string) $a['name_placeholder'],
			'options'     => array(),
			'required'    => brik_form_bool( $a['name_required'] ),
			'width'       => 'full',
			'value'       => '',
		);
	}
	$fields[] = array(
		'name'        => 'email',
		'label'       => __( 'Email', 'brik-builder' ),
		'type'        => 'email',
		'placeholder' => (string) $a['email_placeholder'],
		'options'     => array(),
		'required'    => true,
		'width'       => 'full',
		'value'       => '',
	);
	return $fields;
}

/* -------------------------------------------------------------------------
 * Markup.
 * ----------------------------------------------------------------------- */

function brik_input_class( $extra = '' ) {
	return brik_cls(
		'brik-input h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
		$extra
	);
}

function brik_textarea_class() {
	return 'brik-input brik-textarea flex min-h-28 w-full rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40';
}

function brik_select_class() {
	return 'brik-input brik-select h-9 w-full min-w-0 appearance-none rounded-md border border-input bg-transparent px-3 py-1 pr-9 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm dark:bg-input/30 dark:hover:bg-input/50 focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 [&>option]:bg-popover [&>option]:text-popover-foreground';
}

function brik_label_class( $extra = '' ) {
	return brik_cls( 'brik-form-label flex items-center gap-1 text-sm leading-none font-medium select-none', $extra );
}

/**
 * Native checkbox/radio styled like shadcn. The indicator is a sibling so it works everywhere.
 */
function brik_choice( $type, array $attrs, $text ) {
	$radio = 'radio' === $type;
	$input = $radio
		? 'brik-choice peer size-4 shrink-0 appearance-none rounded-full border border-input bg-transparent shadow-xs transition-[color,box-shadow] outline-none cursor-pointer focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 checked:border-primary aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30'
		: 'brik-choice peer size-4 shrink-0 appearance-none rounded-[4px] border border-input bg-transparent shadow-xs transition-shadow outline-none cursor-pointer focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 checked:border-primary checked:bg-primary aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:checked:bg-primary';
	$mark  = $radio
		? '<span class="pointer-events-none absolute size-2 rounded-full bg-primary opacity-0 peer-checked:opacity-100"></span>'
		: brik_icon( 'check', 'pointer-events-none absolute size-3.5 text-primary-foreground opacity-0 peer-checked:opacity-100', array( 'stroke-width' => '3' ) );

	$attrs['type']  = $radio ? 'radio' : 'checkbox';
	$attrs['class'] = $input;

	return '<label class="brik-choice-label flex items-start gap-2.5 text-sm leading-snug cursor-pointer">'
		. '<span class="relative mt-px inline-grid shrink-0 place-items-center"><input' . brik_attrs( $attrs ) . '>' . $mark . '</span>'
		. '<span>' . $text . '</span></label>';
}

/**
 * One form control with its label and error slot.
 *
 * @param array        $f      Field from brik_form_schema().
 * @param string       $layout stacked|floating|placeholder.
 * @param Brik\Context $ctx    Render context.
 */
function brik_form_field( array $f, $layout, $ctx ) {
	$id       = $ctx->uid( 'f-' . $f['name'] );
	$err_id   = $id . '-error';
	$name     = 'fields[' . $f['name'] . ']';
	$required = $f['required'];
	$label    = brik_inline( $f['label'] );
	$star     = $required ? '<span class="text-destructive" aria-hidden="true">*</span>' : '';

	if ( 'hidden' === $f['type'] ) {
		return '<input' . brik_attrs( array( 'type' => 'hidden', 'name' => $name, 'value' => $f['value'] ) ) . '>';
	}

	$wrap  = brik_cls( 'brik-form-field grid gap-2 content-start', 'half' === $f['width'] ? '@md:col-span-1' : '@md:col-span-2' );
	$error = '<p class="brik-form-error text-sm text-destructive" id="' . esc_attr( $err_id ) . '" hidden></p>';
	$base  = array(
		'id'               => $id,
		'name'             => $name,
		'required'         => $required,
		'aria-describedby' => $err_id,
	);

	$prefill  = isset( $f['prefill'] ) ? $f['prefill'] : null;
	$picked   = null === $prefill ? array() : array_map( 'strval', (array) $prefill );
	$choices  = isset( $f['choices'] ) && is_array( $f['choices'] ) ? $f['choices'] : array_combine( $f['options'], $f['options'] );

	// Uploads: the native input covers a drop zone, so clicking and dropping both work.
	if ( in_array( $f['type'], array( 'file', 'image' ), true ) ) {
		$image = 'image' === $f['type'];
		$exts  = array();
		foreach ( array_keys( isset( $f['accept'] ) ? $f['accept'] : array() ) as $group ) {
			$exts = array_merge( $exts, explode( '|', $group ) );
		}
		$accept = $image ? 'image/*' : implode( ',', array_map( static function ( $e ) { return '.' . $e; }, $exts ) );
		$max    = isset( $f['max_size'] ) ? (int) $f['max_size'] : 0;
		$hint   = strtoupper( implode( ', ', array_slice( array_unique( array_diff( $exts, array( 'jpe', 'jpeg' ) ) ), 0, 6 ) ) );
		/* translators: 1: file types, 2: size like "5 MB" */
		$hint   = sprintf( __( '%1$s up to %2$s', 'brik-builder' ), $hint, size_format( $max ) );
		$input  = '<input' . brik_attrs(
			array(
				'id'               => $id,
				'name'             => $name,
				'type'             => 'file',
				'accept'           => $accept,
				'required'         => $required,
				'aria-describedby' => $err_id . ' ' . $id . '-hint',
				'data-max-bytes'   => $max ? $max : null,
				'class'            => 'brik-upload-input peer absolute inset-0 z-10 size-full cursor-pointer opacity-0',
			)
		) . '>';
		$zone = '<div class="brik-upload relative">'
			. $input
			. '<div class="brik-upload-zone flex items-center gap-4 rounded-lg border border-dashed border-input bg-transparent p-4 text-sm transition-colors peer-hover:border-ring/60 peer-hover:bg-accent/40 peer-focus-visible:border-ring peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50 peer-aria-invalid:border-destructive dark:bg-input/30">'
			. '<span class="brik-upload-preview grid size-12 shrink-0 place-items-center overflow-hidden rounded-md bg-muted text-muted-foreground">' . brik_icon( $image ? 'image-up' : 'file-up', 'size-5' ) . '</span>'
			. '<span class="grid min-w-0 gap-0.5"><span class="brik-upload-name truncate font-medium" data-empty="' . esc_attr( $image ? __( 'Click to upload an image or drag it here', 'brik-builder' ) : __( 'Click to upload a file or drag it here', 'brik-builder' ) ) . '">' . esc_html( $image ? __( 'Click to upload an image or drag it here', 'brik-builder' ) : __( 'Click to upload a file or drag it here', 'brik-builder' ) ) . '</span>'
			. '<span class="text-xs text-muted-foreground" id="' . esc_attr( $id . '-hint' ) . '">' . esc_html( $hint ) . '</span></span>'
			. '</div></div>';
		return '<div class="' . esc_attr( brik_cls( 'brik-form-field grid gap-2 content-start', 'half' === $f['width'] ? '@md:col-span-1' : '@md:col-span-2' ) ) . '" data-field="' . esc_attr( $f['name'] ) . '" data-type="' . esc_attr( $f['type'] ) . '">'
			. '<label for="' . esc_attr( $id ) . '" class="' . esc_attr( brik_label_class() ) . '">' . $label . $star . '</label>'
			. $zone . $error . '</div>';
	}

	// Choice groups.
	if ( in_array( $f['type'], array( 'checkbox', 'radio', 'consent' ), true ) ) {
		$options = $f['options'];
		if ( 'consent' === $f['type'] || ( 'checkbox' === $f['type'] && ! $options ) ) {
			$attrs = array_merge( $base, array( 'value' => '1' ) );
			return '<div class="' . esc_attr( $wrap ) . '" data-field="' . esc_attr( $f['name'] ) . '">'
				. brik_choice( 'checkbox', $attrs, '<span class="brik-form-label-text">' . $label . '</span> ' . $star )
				. $error . '</div>';
		}
		$items = '';
		$i     = 0;
		foreach ( $choices as $option => $text ) {
			$attrs = array(
				'id'      => $id . '-' . $i++,
				'name'    => 'checkbox' === $f['type'] ? $name . '[]' : $name,
				'value'   => (string) $option,
				'checked' => in_array( (string) $option, $picked, true ),
			);
			if ( 'radio' === $f['type'] && $required ) {
				$attrs['required'] = true;
			}
			$items .= brik_choice( $f['type'], $attrs, esc_html( $text ) );
		}
		return '<fieldset class="' . esc_attr( $wrap ) . ' m-0 min-w-0 border-0 p-0" data-field="' . esc_attr( $f['name'] ) . '"' . ( $required ? ' data-required' : '' ) . ' aria-describedby="' . esc_attr( $err_id ) . '">'
			. '<legend class="' . esc_attr( brik_label_class( 'mb-3' ) ) . '">' . $label . $star . '</legend>'
			. '<div class="brik-choices grid gap-2.5">' . $items . '</div>' . $error . '</fieldset>';
	}

	$floating    = 'floating' === $layout;
	$placeholder = $f['placeholder'];
	if ( 'placeholder' === $layout && '' === $placeholder ) {
		$placeholder = wp_strip_all_tags( $f['label'] ) . ( $required ? ' *' : '' );
	}
	if ( $floating ) {
		// :placeholder-shown drives the floating label, so the placeholder must not be empty.
		$placeholder = ' ';
	}

	switch ( $f['type'] ) {
		case 'textarea':
			$control = '<textarea' . brik_attrs( array_merge( $base, array( 'class' => brik_textarea_class(), 'rows' => 5, 'placeholder' => $placeholder, 'maxlength' => 5000 ) ) ) . '>' . ( is_string( $prefill ) ? esc_textarea( $prefill ) : '' ) . '</textarea>';
			break;

		case 'select':
			$opts = '<option value="">' . esc_html( '' !== $f['placeholder'] ? $f['placeholder'] : ( $floating ? '' : __( 'Select an option', 'brik-builder' ) ) ) . '</option>';
			foreach ( $choices as $option => $text ) {
				$opts .= '<option value="' . esc_attr( $option ) . '"' . selected( in_array( (string) $option, $picked, true ), true, false ) . '>' . esc_html( $text ) . '</option>';
			}
			$control = '<div class="relative">'
				. '<select' . brik_attrs( array_merge( $base, array( 'class' => brik_select_class() ) ) ) . '>' . $opts . '</select>'
				. brik_icon( 'chevron-down', 'pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground opacity-60' )
				. '</div>';
			break;

		default:
			$attrs = array_merge(
				$base,
				array(
					'type'        => $f['type'],
					'class'       => brik_input_class(),
					'placeholder' => $placeholder,
					'maxlength'   => in_array( $f['type'], array( 'number', 'date' ), true ) ? null : 500,
					'value'       => is_string( $prefill ) && 'password' !== $f['type'] ? $prefill : null,
				)
			);
			if ( 'password' === $f['type'] ) {
				$attrs['autocomplete'] = 'new-password';
				$attrs['minlength']    = 8;
			}
			$auto = array(
				'email' => 'email',
				'tel'   => 'tel',
				'url'   => 'url',
			);
			if ( isset( $auto[ $f['type'] ] ) ) {
				$attrs['autocomplete'] = $auto[ $f['type'] ];
			} elseif ( 'text' === $f['type'] && preg_match( '/(^|_)name$/', $f['name'] ) ) {
				$attrs['autocomplete'] = 'name';
			}
			if ( 'number' === $f['type'] ) {
				$attrs['inputmode'] = 'decimal';
				$attrs['step']      = 'any';
			}
			$control = '<input' . brik_attrs( $attrs ) . '>';
	}

	$label_html = '<label for="' . esc_attr( $id ) . '" class="' . esc_attr( brik_label_class( 'placeholder' === $layout ? 'sr-only' : '' ) ) . '">' . $label . $star . '</label>';

	if ( $floating ) {
		return '<div class="' . esc_attr( $wrap . ' brik-float' ) . '" data-field="' . esc_attr( $f['name'] ) . '" data-type="' . esc_attr( $f['type'] ) . '">'
			. '<div class="brik-float-box relative">' . $control . $label_html . '</div>' . $error . '</div>';
	}

	return '<div class="' . esc_attr( $wrap ) . '" data-field="' . esc_attr( $f['name'] ) . '">' . $label_html . $control . $error . '</div>';
}

/**
 * Hidden inputs every Brik form posts: where it lives, a signed render time and a honeypot.
 */
function brik_form_hidden( $ctx ) {
	$time = time();
	$html = '<input type="hidden" name="post_id" value="' . (int) $ctx->post_id . '">'
		. '<input type="hidden" name="node_id" value="' . esc_attr( $ctx->id ) . '">'
		. '<input type="hidden" name="brik_ts" value="' . esc_attr( Brik\Forms::token( $time, $ctx->post_id, $ctx->id ) ) . '">';

	// The REST API treats cookie requests without a nonce as logged out; post and account
	// actions need to know who is submitting.
	if ( is_user_logged_in() && ! $ctx->canvas ) {
		$html .= '<input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">';
	}

	// Off-screen rather than display:none, which some bots skip.
	$html .= '<div class="brik-hp" aria-hidden="true"><label>' . esc_html__( 'Leave this field empty', 'brik-builder' )
		. '<input type="text" name="brik_hp" value="" tabindex="-1" autocomplete="off"></label></div>';
	return $html;
}

/**
 * Status messages shown after submitting. Both are rendered so the script only swaps text.
 */
function brik_form_alerts( $success, $ctx ) {
	$state = isset( $_GET['brik_form'], $_GET['brik_node'] ) && $ctx->id === sanitize_key( wp_unslash( $_GET['brik_node'] ) ) ? sanitize_key( wp_unslash( $_GET['brik_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$base  = 'brik-form-alert relative grid w-full grid-cols-[1rem_1fr] items-start gap-x-3 rounded-lg border px-4 py-3 text-sm [&>svg]:size-4 [&>svg]:translate-y-0.5';

	return '<div class="brik-form-status" aria-live="polite">'
		. '<div class="' . esc_attr( $base . ' brik-form-alert-success border-border bg-card text-card-foreground [&>svg]:text-primary' ) . '" role="status" data-alert="success"' . ( 'sent' === $state ? '' : ' hidden' ) . '>'
		. brik_icon( 'circle-check' ) . '<p class="brik-form-alert-text leading-relaxed">' . esc_html( $success ) . '</p></div>'
		. '<div class="' . esc_attr( $base . ' brik-form-alert-error border-destructive/40 bg-card text-destructive' ) . '" role="alert" data-alert="error"' . ( 'error' === $state ? '' : ' hidden' ) . '>'
		. brik_icon( 'circle-alert' ) . '<p class="brik-form-alert-text leading-relaxed">' . esc_html__( 'Something went wrong. Please check the form and try again.', 'brik-builder' ) . '</p></div>'
		. '</div>';
}

/**
 * Submit button with a loading spinner.
 */
function brik_form_submit( $text, $variant, $size, $extra = '', $icon = '', $inline = '' ) {
	$class = brik_button_class( $variant ? $variant : 'default', $size ? $size : 'default', brik_cls( 'brik-form-submit group/submit', $extra ) );
	return '<button type="submit" class="' . esc_attr( $class ) . '">'
		. brik_icon( 'loader-circle', 'brik-spinner hidden size-4 animate-spin group-data-[loading]/submit:block' )
		. ( $icon ? brik_icon( $icon, 'size-4 group-data-[loading]/submit:hidden' ) : '' )
		. '<span' . $inline . '>' . brik_inline( $text ) . '</span></button>';
}

/**
 * Opening <form> tag of an AJAX form.
 */
function brik_form_open( $ctx, $class, array $extra = array() ) {
	return '<form' . brik_attrs(
		array_merge(
			array(
				'class'          => $class,
				'action'         => rest_url( Brik\Rest::NS . '/forms/submit' ),
				'method'         => 'post',
				'data-brik-form' => true,
				// Client-side messages match the server's wording.
				'data-msg-required' => __( 'This field is required.', 'brik-builder' ),
				'data-msg-consent'  => __( 'Please tick this box to continue.', 'brik-builder' ),
				'data-msg-email'    => __( 'Please enter a valid email address.', 'brik-builder' ),
			),
			$extra
		)
	) . '>';
}

/**
 * Shared delivery settings fields (email, storage, webhook, redirect) for form modules.
 */
function brik_form_settings_fields( $name, $subject, $send_email = true ) {
	return array(
		'form_name'       => Brik\Fields::field( 'text', __( 'Form name', 'brik-builder' ), 'delivery', array( 'default' => $name, 'description' => __( 'Used in the admin and email subject.', 'brik-builder' ) ) ),
		'success_message' => Brik\Fields::field( 'textarea', __( 'Success message', 'brik-builder' ), 'delivery', array( 'default' => __( 'Thanks! Your message has been sent. We\'ll get back to you shortly.', 'brik-builder' ), 'description' => __( '{post_title} and {post_url} refer to a post the form created or updated.', 'brik-builder' ) ) ),
		'redirect'        => Brik\Fields::field( 'text', __( 'Redirect URL', 'brik-builder' ), 'delivery', array( 'placeholder' => 'https://', 'description' => __( 'Optional. Send visitors here after a successful submission.', 'brik-builder' ) ) ),
		'send_email'      => Brik\Fields::field( 'toggle', __( 'Email notification', 'brik-builder' ), 'delivery', array( 'default' => $send_email ) ),
		'email_to'        => Brik\Fields::field( 'text', __( 'Send to', 'brik-builder' ), 'delivery', array( 'placeholder' => get_option( 'admin_email' ), 'description' => __( 'Comma separated. Defaults to the site admin email.', 'brik-builder' ), 'show_if' => array( 'send_email' => '!' ) ) ),
		'email_subject'   => Brik\Fields::field( 'text', __( 'Email subject', 'brik-builder' ), 'delivery', array( 'default' => $subject, 'description' => __( 'Field values can be used as {field_name}.', 'brik-builder' ), 'show_if' => array( 'send_email' => '!' ) ) ),
		'save_entries'    => Brik\Fields::field( 'toggle', __( 'Save submissions', 'brik-builder' ), 'delivery', array( 'default' => true, 'description' => __( 'Listed under Brik → Submissions.', 'brik-builder' ) ) ),
		'webhook_url'     => Brik\Fields::field( 'text', __( 'Webhook URL', 'brik-builder' ), 'delivery', array( 'placeholder' => 'https://hooks.zapier.com/…', 'description' => __( 'Optional. Receives every submission as JSON (Zapier, Make, Mailchimp via automation).', 'brik-builder' ) ) ),
	);
}

/**
 * Settings of the post and user actions (create_post, update_post, register_user).
 */
function brik_form_action_fields() {
	$post = array( 'group_label' => __( 'Create / update post', 'brik-builder' ) );
	$user = array( 'group_label' => __( 'Register user', 'brik-builder' ) );
	$roles = array();
	foreach ( function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array() as $role => $label ) {
		if ( brik_form_safe_role( $role ) === $role ) {
			$roles[ $role ] = translate_user_role( $label );
		}
	}
	return array(
		'actions'          => Brik\Fields::field( 'multiselect', __( 'Actions', 'brik-builder' ), 'delivery', array(
			'placeholder' => 'email, save_entry',
			'options'     => Brik\Fields::opts(
				array(
					'email'         => __( 'Email notification', 'brik-builder' ),
					'save_entry'    => __( 'Save submission', 'brik-builder' ),
					'webhook'       => __( 'Webhook', 'brik-builder' ),
					'create_post'   => __( 'Create a post', 'brik-builder' ),
					'update_post'   => __( 'Update a post', 'brik-builder' ),
					'register_user' => __( 'Register a user', 'brik-builder' ),
					'redirect'      => __( 'Redirect', 'brik-builder' ),
				)
			),
			'multiple'    => true,
			'description' => __( 'Comma separated: email, save_entry, webhook, create_post, update_post, register_user, redirect. Empty uses the toggles below.', 'brik-builder' ),
		) ),
		'post_type'        => Brik\Fields::field( 'post_type', __( 'Post type', 'brik-builder' ), 'post_action', array_merge( $post, array( 'default' => 'post' ) ) ),
		'post_status'      => Brik\Fields::field( 'select', __( 'Status of new posts', 'brik-builder' ), 'post_action', array_merge( $post, array( 'default' => 'pending', 'options' => Brik\Fields::opts( array( 'pending' => __( 'Pending review', 'brik-builder' ), 'draft' => __( 'Draft', 'brik-builder' ), 'publish' => __( 'Published', 'brik-builder' ) ) ) ) ) ),
		'post_author'      => Brik\Fields::field( 'select', __( 'Author', 'brik-builder' ), 'post_action', array_merge( $post, array( 'default' => 'current', 'options' => Brik\Fields::opts( array( 'current' => __( 'The logged-in user', 'brik-builder' ), 'fixed' => __( 'A specific user', 'brik-builder' ) ) ) ) ) ),
		'post_author_id'   => Brik\Fields::field( 'number', __( 'Author user ID', 'brik-builder' ), 'post_action', array_merge( $post, array( 'min' => 1, 'description' => __( 'Also used for posts sent by visitors who are not logged in.', 'brik-builder' ) ) ) ),
		'allow_guests'     => Brik\Fields::field( 'toggle', __( 'Allow anyone to submit', 'brik-builder' ), 'post_action', array_merge( $post, array( 'description' => __( 'Off: only logged-in users who can create this post type.', 'brik-builder' ) ) ) ),
		'update_source'    => Brik\Fields::field( 'select', __( 'Post to update', 'brik-builder' ), 'post_action', array_merge( $post, array( 'default' => 'current', 'options' => Brik\Fields::opts( array( 'current' => __( 'The current post', 'brik-builder' ), 'query' => __( '?post_id= in the URL', 'brik-builder' ) ) ) ) ) ),
		'mapping'          => Brik\Fields::field(
			'repeater',
			__( 'Field mapping', 'brik-builder' ),
			'post_action',
			array_merge(
				$post,
				array(
					'title_field' => 'field',
					'item_label'  => __( 'mapping', 'brik-builder' ),
					'fields'      => array(
						'field'  => Brik\Fields::field( 'text', __( 'Form field name', 'brik-builder' ), 'content', array( 'placeholder' => 'title' ) ),
						'target' => Brik\Fields::field( 'text', __( 'Saved as', 'brik-builder' ), 'content', array( 'placeholder' => 'title', 'description' => __( 'title, content, excerpt, featured_image, meta:field_name or tax:taxonomy', 'brik-builder' ) ) ),
					),
				)
			)
		),
		'register_email'    => Brik\Fields::field( 'text', __( 'Email field', 'brik-builder' ), 'user_action', array_merge( $user, array( 'default' => 'email' ) ) ),
		'register_username' => Brik\Fields::field( 'text', __( 'Username field', 'brik-builder' ), 'user_action', array_merge( $user, array( 'description' => __( 'Optional. Generated from the email when empty.', 'brik-builder' ) ) ) ),
		'register_password' => Brik\Fields::field( 'text', __( 'Password field', 'brik-builder' ), 'user_action', array_merge( $user, array( 'description' => __( 'Optional. Without one the user gets an email to set a password.', 'brik-builder' ) ) ) ),
		'register_name'     => Brik\Fields::field( 'text', __( 'Display name field', 'brik-builder' ), 'user_action', array_merge( $user, array( 'default' => 'name' ) ) ),
		'register_role'     => Brik\Fields::field( 'select', __( 'Role', 'brik-builder' ), 'user_action', array_merge( $user, array( 'default' => 'subscriber', 'options' => Brik\Fields::opts( $roles ? $roles : array( 'subscriber' => __( 'Subscriber', 'brik-builder' ) ) ) ) ) ),
		'register_login'    => Brik\Fields::field( 'toggle', __( 'Log the new user in', 'brik-builder' ), 'user_action', array_merge( $user, array( 'default' => true ) ) ),
		'allow_registration' => Brik\Fields::field( 'toggle', __( 'Allow even when site registration is off', 'brik-builder' ), 'user_action', $user ),
	);
}

/**
 * Number formatting shared by the counters (PHP side mirrors counters.js).
 */
function brik_format_number( $value, $decimals = 0, $separator = ',' ) {
	$decimals = max( 0, min( 4, (int) $decimals ) );
	$point    = '.' === $separator ? ',' : '.';
	return number_format( (float) $value, $decimals, $point, $separator );
}
