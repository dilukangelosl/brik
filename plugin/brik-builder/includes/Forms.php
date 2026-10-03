<?php
namespace Brik;

use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Form submissions: REST endpoint, validation, storage, email and webhooks.
 *
 * Everything that decides where a submission goes (recipients, subject, webhook, redirect)
 * is read from the form node stored on the server. The client only sends field values.
 */
final class Forms {

	const POST_TYPE = 'brik_submission';

	/** Set when a theme toggle renders, so the head can apply the stored mode before paint. */
	public static $theme_toggle = false;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'wp_head', array( __CLASS__, 'theme_head' ), 2 );

		if ( is_admin() ) {
			add_action( 'add_meta_boxes_' . self::POST_TYPE, array( __CLASS__, 'meta_boxes' ) );
			add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
			add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
			add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		}
	}

	/**
	 * Module types that submit through this endpoint. A module definition may provide a
	 * 'form_fields' callback returning its field schema for given attrs.
	 */
	public static function types() {
		return (array) apply_filters( 'brik/form_types', array( 'contact_form', 'signup' ) );
	}

	public static function register_post_type() {
		$admin = 'manage_options';
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'Submissions', 'brik-builder' ),
					'singular_name' => __( 'Submission', 'brik-builder' ),
					'edit_item'     => __( 'Submission', 'brik-builder' ),
					'search_items'  => __( 'Search submissions', 'brik-builder' ),
					'not_found'     => __( 'No submissions yet.', 'brik-builder' ),
					'all_items'     => __( 'Submissions', 'brik-builder' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => false,
				'map_meta_cap'    => true,
				// Submissions hold personal data, so only administrators see them.
				'capabilities'    => array(
					'create_posts'           => 'do_not_allow',
					'edit_posts'             => $admin,
					'edit_others_posts'      => $admin,
					'edit_published_posts'   => $admin,
					'edit_private_posts'     => $admin,
					'publish_posts'          => $admin,
					'read_private_posts'     => $admin,
					'delete_posts'           => $admin,
					'delete_others_posts'    => $admin,
					'delete_published_posts' => $admin,
					'delete_private_posts'   => $admin,
				),
			)
		);
	}

	public static function routes() {
		register_rest_route(
			Rest::NS,
			'/forms/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'node_id' => array(
						'required'          => true,
						'sanitize_callback' => static function ( $v ) {
							return preg_match( '/^[a-z0-9]{4,24}$/', (string) $v ) ? (string) $v : '';
						},
					),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Anti-spam token.
	 * ------------------------------------------------------------------- */

	public static function token( $time, $post_id, $node_id ) {
		return (int) $time . '.' . substr( wp_hash( (int) $time . '|' . (int) $post_id . '|' . $node_id, 'nonce' ), 0, 20 );
	}

	/**
	 * Seconds since the form was rendered, or false when the token was tampered with.
	 */
	private static function token_age( $token, $post_id, $node_id ) {
		$parts = explode( '.', (string) $token );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
			return false;
		}
		if ( ! hash_equals( self::token( (int) $parts[0], $post_id, $node_id ), (string) $token ) ) {
			return false;
		}
		return time() - (int) $parts[0];
	}

	/* ---------------------------------------------------------------------
	 * Submission.
	 * ------------------------------------------------------------------- */

	public static function submit( WP_REST_Request $r ) {
		$post_id = (int) $r['post_id'];
		$node_id = (string) $r['node_id'];
		$json    = '' !== (string) $r->get_header( 'x_brik_form' );

		$respond = static function ( $data, $status = 200 ) use ( $json, $r, $node_id ) {
			return $json ? new WP_REST_Response( $data, $status ) : self::fallback_redirect( $r, $node_id, $data );
		};
		$fail    = static function ( $message, $status, $errors = array() ) use ( $respond ) {
			$data = array(
				'success' => false,
				'message' => $message,
			);
			if ( $errors ) {
				$data['errors'] = $errors;
			}
			return $respond( $data, $status );
		};

		$found = $node_id ? self::locate( $post_id, $node_id ) : null;
		if ( ! $found ) {
			return $fail( __( 'This form is no longer available.', 'brik-builder' ), 404 );
		}
		list( $node, $def ) = $found;

		// {post_title} and {post_url} in the message mean the post the form saves, not the page
		// the form is on, so they are swapped before the page's dynamic tags are resolved.
		$raw_node = $node;
		foreach ( array( 'success_message', 'redirect' ) as $key ) {
			if ( isset( $raw_node['attrs'][ $key ] ) && is_string( $raw_node['attrs'][ $key ] ) ) {
				$raw_node['attrs'][ $key ] = str_replace( array( '{post_title}', '{post_url}' ), array( '%%brik_post_title%%', '%%brik_post_url%%' ), $raw_node['attrs'][ $key ] );
			}
		}
		$renderer = new Renderer( $post_id );
		$attrs    = $renderer->resolve_attrs( $raw_node, $def );
		$fields   = self::schema( $def, $attrs );
		$actions  = brik_form_actions( $attrs );

		// Bots that fill the honeypot get a normal-looking answer and nothing is stored.
		$hp = $r['brik_hp'];
		if ( is_string( $hp ) && '' !== trim( $hp ) ) {
			return $respond( self::success_payload( $attrs ) );
		}

		$age = self::token_age( $r['brik_ts'], $post_id, $node_id );
		$min = (int) apply_filters( 'brik/form_min_seconds', 3, $attrs );
		if ( false === $age ) {
			return $fail( __( 'Your session expired. Please reload the page and try again.', 'brik-builder' ), 400 );
		}
		if ( $age < $min ) {
			return $fail( __( 'That was quick! Please wait a moment and submit again.', 'brik-builder' ), 429 );
		}

		if ( ! self::rate_ok() ) {
			return $fail( __( 'Too many submissions. Please try again in a few minutes.', 'brik-builder' ), 429 );
		}

		// Who may do what is settled before anything is validated, uploaded or stored.
		$target = 0;
		$mode   = '';
		if ( in_array( 'update_post', $actions, true ) ) {
			$target = self::update_target( $r, $attrs, $post_id );
			$check  = $target ? brik_form_can_update( $attrs, $target ) : null;
			if ( $target && true === $check ) {
				$mode = 'update';
			} elseif ( ! in_array( 'create_post', $actions, true ) ) {
				$err = is_wp_error( $check ) ? $check : new \WP_Error( 'brik_form_forbidden', __( 'You can\'t edit this item.', 'brik-builder' ), array( 'status' => 403 ) );
				return $fail( $err->get_error_message(), 403 );
			}
		}
		if ( ! $mode && in_array( 'create_post', $actions, true ) ) {
			$check = brik_form_can_create( $attrs );
			if ( is_wp_error( $check ) ) {
				$data = $check->get_error_data();
				return $fail( $check->get_error_message(), isset( $data['status'] ) ? (int) $data['status'] : 403 );
			}
			$mode = 'create';
		}
		$register = in_array( 'register_user', $actions, true );
		if ( $register ) {
			if ( is_user_logged_in() ) {
				return $fail( __( 'You are already logged in.', 'brik-builder' ), 400 );
			}
			if ( ! get_option( 'users_can_register' ) && empty( $attrs['allow_registration'] ) ) {
				return $fail( __( 'Registration is closed.', 'brik-builder' ), 403 );
			}
		}

		$input = $r['fields'];
		$files = self::files( $r );
		list( $values, $errors ) = self::validate( $fields, is_array( $input ) ? $input : array(), $files, 'update' === $mode );
		if ( $register && ! $errors ) {
			$errors = self::register_errors( $attrs, $values );
		}
		$errors = (array) apply_filters( 'brik/form_errors', $errors, $values, $attrs, $node );
		if ( $errors ) {
			return $fail( __( 'Please fix the highlighted fields.', 'brik-builder' ), 422, $errors );
		}

		if ( $register ) {
			$user = self::register( $attrs, $values );
			if ( is_wp_error( $user ) ) {
				return $fail( $user->get_error_message(), 422, (array) $user->get_error_data( 'fields' ) );
			}
		}

		$result = 0;
		if ( $mode ) {
			$result = self::save_post( $attrs, $values, $fields, $files, 'update' === $mode ? $target : 0 );
			if ( is_wp_error( $result ) ) {
				return $fail( $result->get_error_message(), 500 );
			}
		} else {
			self::upload_all( $values, $fields, $files, 0 );
		}

		// Passwords never travel any further than the account they created.
		foreach ( $values as &$value ) {
			if ( 'password' === $value['type'] ) {
				$value['value'] = '' !== $value['value'] ? '••••••••' : '';
			}
			unset( $value['raw'], $value['attachment'], $value['upload'] );
		}
		unset( $value );

		$entry = array(
			'form'    => wp_strip_all_tags( (string) $attrs['form_name'] ),
			'type'    => $node['type'],
			'node_id' => $node_id,
			'post_id' => $post_id,
			'url'     => self::page_url( $r, $post_id ),
			'fields'  => $values,
			'date'    => current_time( 'mysql' ),
		);
		if ( '' === $entry['form'] ) {
			$entry['form'] = $def['title'];
		}
		if ( $result ) {
			$entry['created'] = $result;
		}

		$submission_id = in_array( 'save_entry', $actions, true ) ? self::store( $entry ) : 0;
		if ( in_array( 'email', $actions, true ) ) {
			self::mail( $entry, $attrs );
		}
		if ( in_array( 'webhook', $actions, true ) ) {
			self::webhook( $entry, $attrs );
		}

		do_action( 'brik/form_submitted', $entry, $attrs, $submission_id, $node );

		return $respond( self::success_payload( $attrs, $result, $mode, in_array( 'redirect', $actions, true ) ) );
	}

	/**
	 * Placeholders in the success message and redirect: {post_title}, {post_url}.
	 */
	private static function success_payload( array $attrs, $result_post = 0, $mode = '', $redirect_on = true ) {
		$post    = $result_post ? get_post( $result_post ) : null;
		$swap    = array(
			'%%brik_post_title%%' => $post ? wp_strip_all_tags( get_the_title( $post ) ) : '',
			'%%brik_post_url%%'   => $post ? (string) get_permalink( $post ) : '',
		);
		$message = '' !== trim( (string) $attrs['success_message'] ) ? wp_strip_all_tags( (string) $attrs['success_message'] ) : __( 'Thanks! Your message has been sent.', 'brik-builder' );
		$out     = array(
			'success' => true,
			'message' => strtr( $message, $swap ),
		);
		if ( $post ) {
			$out['post_id']   = $post->ID;
			$out['permalink'] = (string) get_permalink( $post );
			$out['mode']      = $mode;
		}
		$redirect = is_array( $attrs['redirect'] ) ? ( isset( $attrs['redirect']['url'] ) ? $attrs['redirect']['url'] : '' ) : (string) $attrs['redirect'];
		$redirect = $redirect_on ? esc_url_raw( trim( strtr( $redirect, $swap ) ) ) : '';
		if ( $redirect ) {
			$out['redirect'] = $redirect;
		}
		return $out;
	}

	/**
	 * The post an update form edits: the post the form lives on, or the id the page passed
	 * along (templates and ?post_id=). Permission is checked by the caller.
	 */
	private static function update_target( WP_REST_Request $r, array $attrs, $post_id ) {
		$s = brik_form_post_settings( $attrs );
		if ( 'current' === $s['source'] && ! brik_site_is_layout( $post_id ) ) {
			return (int) $post_id;
		}
		return absint( $r['brik_target'] );
	}

	/* ---------------------------------------------------------------------
	 * Uploads.
	 * ------------------------------------------------------------------- */

	/**
	 * Uploaded files of fields[name] inputs, one entry per field name.
	 */
	private static function files( WP_REST_Request $r ) {
		$raw = $r->get_file_params();
		$raw = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array();
		$out = array();
		if ( isset( $raw['name'] ) && is_array( $raw['name'] ) ) {
			foreach ( $raw['name'] as $name => $file_name ) {
				if ( is_string( $file_name ) ) {
					$out[ $name ] = array(
						'name'     => $file_name,
						'type'     => isset( $raw['type'][ $name ] ) ? (string) $raw['type'][ $name ] : '',
						'tmp_name' => isset( $raw['tmp_name'][ $name ] ) ? (string) $raw['tmp_name'][ $name ] : '',
						'error'    => isset( $raw['error'][ $name ] ) ? (int) $raw['error'][ $name ] : UPLOAD_ERR_NO_FILE,
						'size'     => isset( $raw['size'][ $name ] ) ? (int) $raw['size'][ $name ] : 0,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Check one upload against the field's limits.
	 *
	 * @return string Error message, or '' when the file is fine.
	 */
	public static function check_upload( array $f, array $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return in_array( $file['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
				? __( 'This file is too large.', 'brik-builder' )
				: __( 'The upload failed. Please try again.', 'brik-builder' );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return __( 'The upload failed. Please try again.', 'brik-builder' );
		}
		if ( $file['size'] > $f['max_size'] || filesize( $file['tmp_name'] ) > $f['max_size'] ) {
			/* translators: %s: size like "5 MB" */
			return sprintf( __( 'This file is too large (maximum %s).', 'brik-builder' ), size_format( $f['max_size'] ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $f['accept'] );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) || ( 'image' === $f['type'] && ! wp_getimagesize( $file['tmp_name'] ) ) ) {
			return 'image' === $f['type'] ? __( 'Please choose a JPG, PNG, GIF or WebP image.', 'brik-builder' ) : __( 'This file type is not allowed.', 'brik-builder' );
		}
		return '';
	}

	/**
	 * Move a checked upload into the media library.
	 *
	 * @return int Attachment id or 0.
	 */
	private static function upload( array $f, array $file, $parent ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$key            = 'brik_upload_' . $f['name'];
		$_FILES[ $key ] = $file; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$id             = media_handle_upload(
			$key,
			(int) $parent,
			array(),
			array(
				'test_form' => false,
				'mimes'     => $f['accept'],
			)
		);
		unset( $_FILES[ $key ] );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Upload every file field; values then show the file's URL (emails, entries, webhooks).
	 */
	private static function upload_all( array &$values, array $fields, array $files, $parent ) {
		foreach ( $fields as $f ) {
			$name = $f['name'];
			if ( ! in_array( $f['type'], array( 'file', 'image' ), true ) || empty( $values[ $name ]['upload'] ) || ! isset( $files[ $name ] ) ) {
				continue;
			}
			$id = self::upload( $f, $files[ $name ], $parent );
			unset( $values[ $name ]['upload'] );
			$values[ $name ]['attachment'] = $id;
			$values[ $name ]['value']      = $id ? (string) wp_get_attachment_url( $id ) : '';
		}
	}

	/* ---------------------------------------------------------------------
	 * Posts.
	 * ------------------------------------------------------------------- */

	/**
	 * Create or update a post from the mapped form values.
	 *
	 * @return int|\WP_Error Post id.
	 */
	public static function save_post( array $attrs, array &$values, array $fields, array $files, $update_id = 0 ) {
		$s       = brik_form_post_settings( $attrs );
		$mapping = brik_form_mapping( $attrs );
		$get     = static function ( $field ) use ( &$values ) {
			if ( ! isset( $values[ $field ] ) ) {
				return null;
			}
			return array_key_exists( 'raw', $values[ $field ] ) ? $values[ $field ]['raw'] : $values[ $field ]['value'];
		};

		$postarr = array();
		foreach ( $mapping as $map ) {
			list( $field, $kind ) = $map;
			$value = $get( $field );
			if ( null === $value || is_array( $value ) ) {
				continue;
			}
			if ( 'title' === $kind ) {
				$postarr['post_title'] = self::cut( sanitize_text_field( $value ), 200 );
			} elseif ( 'content' === $kind ) {
				$postarr['post_content'] = wp_kses_post( wpautop( $value ) );
			} elseif ( 'excerpt' === $kind ) {
				$postarr['post_excerpt'] = sanitize_textarea_field( $value );
			}
		}

		if ( $update_id ) {
			$postarr['ID'] = (int) $update_id;
			$post_id       = count( $postarr ) > 1 ? wp_update_post( wp_slash( $postarr ), true ) : (int) $update_id;
			$type          = get_post_type( $update_id );
		} else {
			$type   = $s['post_type'];
			$author = 0;
			if ( 'current' === $s['author'] && is_user_logged_in() ) {
				$author = get_current_user_id();
			} elseif ( $s['author_id'] && get_userdata( $s['author_id'] ) ) {
				$author = $s['author_id'];
			}
			if ( empty( $postarr['post_title'] ) ) {
				/* translators: 1: form name, 2: date */
				$postarr['post_title'] = sprintf( __( '%1$s – %2$s', 'brik-builder' ), wp_strip_all_tags( (string) $attrs['form_name'] ), wp_date( get_option( 'date_format' ) ) );
			}
			$postarr = array_merge(
				$postarr,
				array(
					'post_type'   => $type,
					'post_status' => $s['post_status'],
					'post_author' => $author,
				)
			);
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return new \WP_Error( 'brik_form_post', __( 'Your submission could not be saved. Please try again.', 'brik-builder' ) );
		}

		self::upload_all( $values, $fields, $files, $post_id );

		foreach ( $mapping as $map ) {
			list( $field, $kind, $name ) = $map;
			if ( ! isset( $values[ $field ] ) ) {
				continue;
			}
			$is_file = in_array( $values[ $field ]['type'], array( 'file', 'image' ), true );
			$value   = $is_file ? ( isset( $values[ $field ]['attachment'] ) ? (int) $values[ $field ]['attachment'] : 0 ) : $get( $field );
			// An empty upload on an edit form keeps what the post already has.
			if ( $is_file && ! $value ) {
				continue;
			}
			switch ( $kind ) {
				case 'featured_image':
					if ( $value && wp_attachment_is_image( $value ) ) {
						set_post_thumbnail( $post_id, $value );
					}
					break;
				case 'meta':
					self::save_meta( $post_id, $name, $value );
					break;
				case 'tax':
					self::save_terms( $post_id, $type, $name, $value, self::field_def( $fields, $field ) );
					break;
			}
		}
		return (int) $post_id;
	}

	private static function field_def( array $fields, $name ) {
		foreach ( $fields as $f ) {
			if ( $f['name'] === $name ) {
				return $f;
			}
		}
		return null;
	}

	/**
	 * Registered content fields are saved through the content API (it sanitizes per type);
	 * anything else becomes plain text post meta.
	 */
	public static function save_meta( $post_id, $name, $value ) {
		if ( function_exists( 'brik_field_object' ) && brik_field_object( $name, $post_id ) ) {
			return brik_update_field( $name, $value, $post_id );
		}
		$clean = is_array( $value ) ? array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) ) : sanitize_text_field( (string) $value );
		return (bool) update_post_meta( $post_id, $name, wp_slash( $clean ) );
	}

	/**
	 * Assign existing terms only; a form never creates terms. Ids come from sourced options,
	 * typed options are matched by slug or name.
	 */
	public static function save_terms( $post_id, $post_type, $taxonomy, $value, $field = null ) {
		if ( ! taxonomy_exists( $taxonomy ) || ! is_object_in_taxonomy( $post_type, $taxonomy ) ) {
			return false;
		}
		$ids = array();
		foreach ( (array) $value as $v ) {
			if ( ! is_scalar( $v ) || '' === (string) $v ) {
				continue;
			}
			$term = null;
			if ( $field && 0 === strpos( (string) $field['source'], 'taxonomy:' ) && ctype_digit( (string) $v ) ) {
				$term = get_term( (int) $v, $taxonomy );
			} else {
				$term = get_term_by( 'slug', sanitize_title( $v ), $taxonomy );
				$term = $term ? $term : get_term_by( 'name', (string) $v, $taxonomy );
			}
			if ( $term instanceof \WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( ! $ids ) {
			return false;
		}
		return ! is_wp_error( wp_set_object_terms( $post_id, $ids, $taxonomy ) );
	}

	/* ---------------------------------------------------------------------
	 * Users.
	 * ------------------------------------------------------------------- */

	private static function register_value( array $attrs, array $values, $key, $default = '' ) {
		$field = isset( $attrs[ $key ] ) && '' !== trim( (string) $attrs[ $key ] ) ? brik_form_name( $attrs[ $key ] ) : $default;
		return $field && isset( $values[ $field ] ) && is_string( $values[ $field ]['value'] ) ? array( $field, $values[ $field ]['value'] ) : array( $field, '' );
	}

	/**
	 * Field errors of a registration (taken email, username, weak password).
	 */
	private static function register_errors( array $attrs, array $values ) {
		$errors = array();
		list( $ef, $email ) = self::register_value( $attrs, $values, 'register_email', 'email' );
		list( $uf, $login ) = self::register_value( $attrs, $values, 'register_username' );
		list( $pf, $pass )  = self::register_value( $attrs, $values, 'register_password' );
		if ( ! is_email( $email ) ) {
			$errors[ $ef ? $ef : 'email' ] = __( 'Please enter a valid email address.', 'brik-builder' );
		} elseif ( email_exists( $email ) ) {
			$errors[ $ef ] = __( 'An account with this email already exists.', 'brik-builder' );
		}
		if ( $uf && '' !== $login ) {
			if ( sanitize_user( $login, true ) !== $login || strlen( $login ) > 60 ) {
				$errors[ $uf ] = __( 'Usernames can only contain letters, numbers and . _ - @', 'brik-builder' );
			} elseif ( username_exists( $login ) ) {
				$errors[ $uf ] = __( 'This username is taken.', 'brik-builder' );
			}
		}
		if ( $pf && '' !== $pass && strlen( $pass ) < 8 ) {
			$errors[ $pf ] = __( 'Use at least 8 characters.', 'brik-builder' );
		}
		return $errors;
	}

	/**
	 * @return int|\WP_Error New user id.
	 */
	private static function register( array $attrs, array $values ) {
		list( , $email ) = self::register_value( $attrs, $values, 'register_email', 'email' );
		list( , $login ) = self::register_value( $attrs, $values, 'register_username' );
		list( , $pass )  = self::register_value( $attrs, $values, 'register_password' );
		list( , $name )  = self::register_value( $attrs, $values, 'register_name', 'name' );

		if ( '' === $login ) {
			$base  = sanitize_user( strtok( $email, '@' ), true );
			$base  = '' !== $base ? $base : 'user';
			$login = $base;
			$n     = 2;
			while ( username_exists( $login ) ) {
				$login = $base . $n++;
			}
		}
		$generated = '' === $pass;
		$user_id   = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_email'   => $email,
				'user_pass'    => $generated ? wp_generate_password( 24 ) : $pass,
				'display_name' => '' !== $name ? sanitize_text_field( $name ) : $login,
				'first_name'   => '' !== $name ? sanitize_text_field( strtok( $name, ' ' ) ) : '',
				'role'         => brik_form_safe_role( isset( $attrs['register_role'] ) ? $attrs['register_role'] : 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'brik_form_register', $user_id->get_error_message() );
		}
		wp_new_user_notification( $user_id, null, $generated ? 'both' : 'admin' );

		if ( ! isset( $attrs['register_login'] ) || brik_form_bool( $attrs['register_login'] ) ) {
			wp_set_current_user( $user_id );
			wp_set_auth_cookie( $user_id, false, is_ssl() );
		}
		return (int) $user_id;
	}

	/**
	 * Without JavaScript the form posts here directly; send the visitor back to the page.
	 */
	private static function fallback_redirect( WP_REST_Request $r, $node_id, array $data ) {
		$back = wp_validate_redirect( esc_url_raw( (string) $r->get_header( 'referer' ) ), home_url( '/' ) );
		if ( ! empty( $data['success'] ) && ! empty( $data['redirect'] ) ) {
			$to = $data['redirect'];
		} else {
			$to = add_query_arg(
				array(
					'brik_form' => ! empty( $data['success'] ) ? 'sent' : 'error',
					'brik_node' => $node_id,
				),
				$back
			) . '#' . 'brik-' . $node_id . '-form';
		}
		$res = new WP_REST_Response( null, 303 );
		$res->header( 'Location', $to );
		return $res;
	}

	/**
	 * Find a form node in a post, following global elements into library items.
	 *
	 * @param array|null $types Module types to accept (form types by default). Listings use
	 *                          the same lookup for their filter endpoint.
	 * @return array|null [ node, module definition ]
	 */
	public static function locate( $post_id, $node_id, $depth = 0, $types = null ) {
		$types = null === $types ? self::types() : (array) $types;
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || $depth > 3 ) {
			return null;
		}
		$tree = Data::get( $post_id );
		if ( ! $tree ) {
			return null;
		}

		$node = Data::find( $tree, $node_id );
		if ( is_array( $node ) ) {
			$def = Modules::get( $node['type'] );
			return $def && in_array( $node['type'], $types, true ) ? array( $node, $def ) : null;
		}

		foreach ( Data::flatten( $tree ) as $item ) {
			if ( 'global' !== $item['type'] ) {
				continue;
			}
			$global = Data::find( $tree, $item['id'] );
			$ref    = isset( $global['attrs']['ref'] ) ? (int) $global['attrs']['ref'] : 0;
			if ( $ref && get_post_type( $ref ) === Library::POST_TYPE ) {
				$found = self::locate( $ref, $node_id, $depth + 1, $types );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	private static function schema( array $def, array $attrs ) {
		if ( isset( $def['form_fields'] ) && is_callable( $def['form_fields'] ) ) {
			return (array) call_user_func( $def['form_fields'], $attrs );
		}
		return brik_form_schema( isset( $attrs['fields'] ) ? $attrs['fields'] : array() );
	}

	/**
	 * Sanitize and validate submitted values against the field schema.
	 *
	 * Options from a taxonomy or post type are submitted as ids: "raw" keeps the id for
	 * mapping, "value" holds the label people read in emails and entries.
	 *
	 * @param array $files   Uploads keyed by field name (see files()).
	 * @param bool  $editing Edit forms may leave required uploads empty to keep the current file.
	 * @return array [ values: name => [label, value, type, raw?], errors: name => message ]
	 */
	public static function validate( array $fields, array $input, array $files = array(), $editing = false ) {
		$values = array();
		$errors = array();

		foreach ( $fields as $f ) {
			$name  = $f['name'];
			$raw   = isset( $input[ $name ] ) ? $input[ $name ] : '';
			$value = '';
			$error = '';
			$extra = array();
			$f     = wp_parse_args( $f, array( 'options' => array(), 'choices' => null, 'source' => 'manual', 'required' => false, 'label' => '' ) );

			switch ( $f['type'] ) {
				case 'file':
				case 'image':
					$file = isset( $files[ $name ] ) ? $files[ $name ] : null;
					if ( $file && UPLOAD_ERR_NO_FILE !== $file['error'] ) {
						$f     = wp_parse_args( $f, array( 'accept' => brik_form_accept( $f['type'], '' ), 'max_size' => brik_form_max_bytes( 0 ) ) );
						$error = self::check_upload( $f, $file );
						if ( ! $error ) {
							$value           = sanitize_file_name( $file['name'] );
							$extra['upload'] = true;
						}
					} elseif ( $editing ) {
						$f['required'] = false;
					}
					break;

				case 'password':
					$value = is_string( $raw ) ? substr( $raw, 0, 200 ) : '';
					break;

				case 'checkbox':
					if ( $f['options'] ) {
						// Only exact matches of the stored options are kept, so no sanitizing is needed.
						$picked = array_map( 'trim', array_filter( (array) $raw, 'is_string' ) );
						$value  = array_values( array_intersect( $f['options'], $picked ) );
						if ( 'manual' !== $f['source'] && is_array( $f['choices'] ) ) {
							$extra['raw'] = $value;
							$value        = array_values( array_intersect_key( $f['choices'], array_flip( $value ) ) );
						}
						break;
					}
					// Single checkbox works like consent.
				case 'consent':
					$value = is_string( $raw ) && '' !== $raw ? __( 'Yes', 'brik-builder' ) : '';
					break;

				case 'select':
				case 'radio':
					$raw   = is_string( $raw ) ? trim( $raw ) : '';
					$value = in_array( $raw, $f['options'], true ) ? $raw : '';
					if ( '' !== $raw && '' === $value ) {
						$error = __( 'Please choose one of the options.', 'brik-builder' );
					}
					if ( '' !== $value && 'manual' !== $f['source'] && isset( $f['choices'][ $value ] ) ) {
						$extra['raw'] = $value;
						$value        = $f['choices'][ $value ];
					}
					break;

				case 'textarea':
					$value = is_string( $raw ) ? self::cut( sanitize_textarea_field( $raw ), 5000 ) : '';
					break;

				case 'email':
					$raw   = is_string( $raw ) ? trim( $raw ) : '';
					$value = sanitize_email( $raw );
					if ( '' !== $raw && ! is_email( $value ) ) {
						$error = __( 'Please enter a valid email address.', 'brik-builder' );
						$value = '';
					}
					break;

				case 'url':
					$raw   = is_string( $raw ) ? trim( $raw ) : '';
					$value = '' !== $raw ? esc_url_raw( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $raw ) ? $raw : 'https://' . $raw, array( 'http', 'https' ) ) : '';
					if ( '' !== $raw && ( '' === $value || ! filter_var( $value, FILTER_VALIDATE_URL ) ) ) {
						$error = __( 'Please enter a valid URL.', 'brik-builder' );
						$value = '';
					}
					break;

				case 'number':
					$raw = is_string( $raw ) ? trim( str_replace( ',', '.', $raw ) ) : '';
					if ( '' !== $raw && ! is_numeric( $raw ) ) {
						$error = __( 'Please enter a number.', 'brik-builder' );
					} else {
						$value = $raw;
					}
					break;

				case 'tel':
					$raw   = is_string( $raw ) ? trim( sanitize_text_field( $raw ) ) : '';
					$value = self::cut( $raw, 40 );
					if ( '' !== $raw && ! preg_match( '/^[0-9+().\-\s\/x#*]{3,40}$/i', $raw ) ) {
						$error = __( 'Please enter a valid phone number.', 'brik-builder' );
					}
					break;

				case 'date':
					$raw = is_string( $raw ) ? trim( $raw ) : '';
					if ( '' !== $raw && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
						$error = __( 'Please enter a valid date.', 'brik-builder' );
					} else {
						$value = $raw;
					}
					break;

				default:
					$value = is_string( $raw ) ? self::cut( sanitize_text_field( $raw ), 500 ) : '';
			}

			$empty = is_array( $value ) ? ! $value : '' === $value;
			if ( ! $error && $f['required'] && $empty ) {
				if ( in_array( $f['type'], array( 'file', 'image' ), true ) ) {
					$error = __( 'Please choose a file.', 'brik-builder' );
				} elseif ( in_array( $f['type'], array( 'consent', 'checkbox' ), true ) && ! $f['options'] ) {
					$error = __( 'Please tick this box to continue.', 'brik-builder' );
				} else {
					$error = __( 'This field is required.', 'brik-builder' );
				}
			}
			if ( $error ) {
				$errors[ $name ] = $error;
			}
			$values[ $name ] = array_merge(
				array(
					'label' => '' !== trim( wp_strip_all_tags( $f['label'] ) ) ? wp_strip_all_tags( $f['label'] ) : $name,
					'value' => $value,
					'type'  => $f['type'],
				),
				$extra
			);
		}
		return array( $values, $errors );
	}

	private static function cut( $text, $max ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
	}

	/**
	 * Allow a handful of submissions per IP in a sliding window.
	 */
	private static function rate_ok() {
		$limit  = (int) apply_filters( 'brik/form_rate_limit', 8 );
		$window = (int) apply_filters( 'brik/form_rate_window', 10 * MINUTE_IN_SECONDS );
		if ( $limit <= 0 ) {
			return true;
		}
		$key   = 'brik_form_rl_' . md5( self::ip() );
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	/**
	 * The connecting address. Proxies can be handled with the filter; forwarded headers
	 * are not trusted by default because any client can send them.
	 */
	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'brik/form_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function page_url( WP_REST_Request $r, $post_id ) {
		$url = esc_url_raw( (string) $r['page_url'] );
		if ( $url && wp_validate_redirect( $url, false ) ) {
			return $url;
		}
		$type = get_post_type( $post_id );
		if ( in_array( $type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			$ref = wp_validate_redirect( esc_url_raw( (string) $r->get_header( 'referer' ) ), false );
			return $ref ? $ref : home_url( '/' );
		}
		return (string) get_permalink( $post_id );
	}

	/* ---------------------------------------------------------------------
	 * Delivery.
	 * ------------------------------------------------------------------- */

	private static function store( array $entry ) {
		$id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				/* translators: 1: form name, 2: date */
				'post_title'   => sprintf( __( '%1$s – %2$s', 'brik-builder' ), $entry['form'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
				'post_content' => wp_slash( self::table( $entry['fields'] ) ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_brik_form', wp_slash( $entry['form'] ) );
		update_post_meta( $id, '_brik_form_type', $entry['type'] );
		update_post_meta( $id, '_brik_form_node', $entry['node_id'] );
		update_post_meta( $id, '_brik_form_source', (int) $entry['post_id'] );
		update_post_meta( $id, '_brik_form_url', esc_url_raw( $entry['url'] ) );
		update_post_meta( $id, '_brik_form_ip', wp_hash( self::ip() ) );
		update_post_meta( $id, '_brik_form_fields', wp_slash( $entry['fields'] ) );
		return (int) $id;
	}

	private static function display_value( $value ) {
		return is_array( $value ) ? implode( ', ', $value ) : (string) $value;
	}

	/**
	 * Fields as an HTML table (stored as the submission content and shown in the admin).
	 */
	public static function table( array $fields ) {
		$rows = '';
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['label'], $field['value'] ) ) {
				continue;
			}
			$value = self::display_value( $field['value'] );
			$rows .= '<tr><th scope="row">' . esc_html( $field['label'] ) . '</th><td>' . ( '' === $value ? '<span aria-hidden="true">—</span>' : nl2br( esc_html( $value ) ) ) . '</td></tr>';
		}
		return '<table class="brik-submission widefat striped"><tbody>' . $rows . '</tbody></table>';
	}

	private static function header_safe( $text ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	}

	private static function mail( array $entry, array $attrs ) {
		$to = array();
		foreach ( preg_split( '/[,;\s]+/', (string) $attrs['email_to'] ) as $address ) {
			$address = sanitize_email( $address );
			if ( is_email( $address ) ) {
				$to[] = $address;
			}
		}
		$to = array_slice( array_unique( $to ), 0, 10 );
		if ( ! $to ) {
			$to = array( get_option( 'admin_email' ) );
		}

		$site    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject = '' !== trim( (string) $attrs['email_subject'] ) ? (string) $attrs['email_subject'] : '[{site_name}] {form_name}';
		$subject = str_replace( array( '{site_name}', '{form_name}' ), array( $site, $entry['form'] ), $subject );
		$subject = preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			static function ( $m ) use ( $entry ) {
				return isset( $entry['fields'][ $m[1] ] ) ? self::display_value( $entry['fields'][ $m[1] ]['value'] ) : $m[0];
			},
			$subject
		);
		$subject = self::cut( self::header_safe( $subject ), 200 );

		$lines = array();
		$reply = '';
		$from  = '';
		foreach ( $entry['fields'] as $name => $field ) {
			$value = self::display_value( $field['value'] );
			if ( 'email' === $field['type'] && ! $reply && is_email( $value ) ) {
				$reply = $value;
			}
			if ( ! $from && in_array( $name, array( 'name', 'full_name', 'your_name' ), true ) && '' !== $value ) {
				$from = $value;
			}
			$lines[] = ( false !== strpos( $value, "\n" ) ? $field['label'] . ":\n" . $value . "\n" : $field['label'] . ': ' . ( '' === $value ? '—' : $value ) );
		}

		/* translators: 1: form name, 2: site name */
		$body  = sprintf( __( 'New submission from "%1$s" on %2$s.', 'brik-builder' ), $entry['form'], $site ) . "\n\n";
		$body .= implode( "\n", $lines ) . "\n\n-- \n";
		/* translators: %s: page URL */
		$body .= sprintf( __( 'Page: %s', 'brik-builder' ), $entry['url'] ) . "\n";
		/* translators: %s: date and time */
		$body .= sprintf( __( 'Sent: %s', 'brik-builder' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . "\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		if ( $reply ) {
			$name      = str_replace( array( '"', '<', '>', '\\' ), '', self::header_safe( $from ) );
			$headers[] = 'Reply-To: ' . ( '' !== $name ? '"' . $name . '" ' : '' ) . '<' . $reply . '>';
		}

		$mail = apply_filters(
			'brik/form_mail',
			array(
				'to'      => $to,
				'subject' => $subject,
				'body'    => $body,
				'headers' => $headers,
			),
			$entry,
			$attrs
		);
		return wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
	}

	/**
	 * POST the submission as JSON. wp_safe_remote_post refuses internal addresses.
	 */
	private static function webhook( array $entry, array $attrs ) {
		$url = esc_url_raw( trim( (string) $attrs['webhook_url'] ), array( 'http', 'https' ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return;
		}
		$flat = array();
		foreach ( $entry['fields'] as $name => $field ) {
			$flat[ $name ] = $field['value'];
		}
		$payload = apply_filters(
			'brik/form_webhook_payload',
			array(
				'form'      => $entry['form'],
				'form_type' => $entry['type'],
				'form_id'   => $entry['node_id'],
				'post_id'   => $entry['post_id'],
				'page_url'  => $entry['url'],
				'submitted' => gmdate( 'c' ),
				'site'      => home_url( '/' ),
				'fields'    => $flat,
			),
			$entry,
			$attrs
		);
		wp_safe_remote_post(
			$url,
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $payload ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin screens.
	 * ------------------------------------------------------------------- */

	public static function meta_boxes( $post ) {
		remove_meta_box( 'submitdiv', self::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
		add_meta_box( 'brik-submission', __( 'Submission', 'brik-builder' ), array( __CLASS__, 'render_box' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'brik-submission-info', __( 'Details', 'brik-builder' ), array( __CLASS__, 'render_info' ), self::POST_TYPE, 'side', 'high' );
	}

	public static function render_box( $post ) {
		$fields = get_post_meta( $post->ID, '_brik_form_fields', true );
		echo '<h2 style="padding:0 0 12px;font-size:16px">' . esc_html( get_the_title( $post ) ) . '</h2>';
		if ( is_array( $fields ) && $fields ) {
			echo self::table( $fields ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in table().
		} else {
			echo wp_kses_post( $post->post_content );
		}
	}

	public static function render_info( $post ) {
		$source = (int) get_post_meta( $post->ID, '_brik_form_source', true );
		$url    = (string) get_post_meta( $post->ID, '_brik_form_url', true );
		$form   = (string) get_post_meta( $post->ID, '_brik_form', true );

		echo '<p><strong>' . esc_html__( 'Form', 'brik-builder' ) . ':</strong> ' . esc_html( $form ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Received', 'brik-builder' ) . ':</strong> ' . esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ) ) . '</p>';
		if ( $url ) {
			echo '<p><strong>' . esc_html__( 'Page', 'brik-builder' ) . ':</strong> <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $source && get_post( $source ) ? get_the_title( $source ) : $url ) . '</a></p>';
		}
		if ( current_user_can( 'delete_post', $post->ID ) ) {
			echo '<p><a class="submitdelete" style="color:#b32d2e" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">' . esc_html__( 'Move to Trash', 'brik-builder' ) . '</a></p>';
		}
	}

	public static function columns( $columns ) {
		return array(
			'cb'          => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'       => __( 'Submission', 'brik-builder' ),
			'brik_from'   => __( 'From', 'brik-builder' ),
			'brik_source' => __( 'Page', 'brik-builder' ),
			'date'        => __( 'Date', 'brik-builder' ),
		);
	}

	public static function column( $column, $post_id ) {
		if ( 'brik_from' === $column ) {
			$fields = get_post_meta( $post_id, '_brik_form_fields', true );
			$out    = array();
			foreach ( is_array( $fields ) ? $fields : array() as $name => $field ) {
				if ( isset( $field['type'], $field['value'] ) && is_string( $field['value'] ) && '' !== $field['value'] && ( 'email' === $field['type'] || in_array( $name, array( 'name', 'full_name', 'your_name' ), true ) ) ) {
					$out[] = $field['value'];
				}
			}
			echo esc_html( $out ? implode( ' · ', $out ) : '—' );
		} elseif ( 'brik_source' === $column ) {
			$source = (int) get_post_meta( $post_id, '_brik_form_source', true );
			$url    = (string) get_post_meta( $post_id, '_brik_form_url', true );
			if ( $url ) {
				echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $source && get_post( $source ) ? get_the_title( $source ) : $url ) . '</a>';
			} else {
				echo '—';
			}
		}
	}

	public static function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'] );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'View', 'brik-builder' ) . '</a>';
		}
		return $actions;
	}

	/* ---------------------------------------------------------------------
	 * Theme toggle.
	 * ------------------------------------------------------------------- */

	/**
	 * Apply the visitor's saved light/dark choice before the first paint. Printed only when a
	 * theme toggle is on the page; the front-end script covers every other page.
	 */
	public static function theme_head() {
		if ( ! self::$theme_toggle || Builder::is_canvas() ) {
			return;
		}
		echo "<script>(function(){try{var m=localStorage.getItem('brik-theme')||'system',d=m==='dark'||(m==='system'&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('dark',d)}catch(e){}})()</script>\n";
	}
}
