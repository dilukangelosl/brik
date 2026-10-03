<?php
namespace Brik\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Field types: settings schema, sanitizing, validation, formatting and display markup.
 *
 * Values move through three shapes: raw input (form posts, REST, MCP), the stored value
 * (sanitize()), and the formatted value handed to templates (format()).
 */
final class Fields {

	private static $types;

	public static function reset_cache() {
		self::$types = null;
	}

	/* ---------------------------------------------------------------------
	 * Type registry.
	 * ------------------------------------------------------------------- */

	public static function categories() {
		return array(
			'basic'      => __( 'Basic', 'brik-builder' ),
			'content'    => __( 'Content', 'brik-builder' ),
			'choice'     => __( 'Choice', 'brik-builder' ),
			'relational' => __( 'Relational', 'brik-builder' ),
			'advanced'   => __( 'Advanced', 'brik-builder' ),
			'layout'     => __( 'Layout', 'brik-builder' ),
		);
	}

	/**
	 * Option schema helpers.
	 */
	private static function opt( $type, $label, $default = '', array $extra = array() ) {
		return array_merge(
			array(
				'type'    => $type,
				'label'   => $label,
				'default' => $default,
			),
			$extra
		);
	}

	private static function select_opt( $label, array $choices, $default ) {
		$list = array();
		foreach ( $choices as $value => $text ) {
			$list[] = array(
				'value' => (string) $value,
				'label' => $text,
			);
		}
		return self::opt( 'select', $label, $default, array( 'choices' => $list ) );
	}

	/**
	 * All field types: label, category, Lucide icon, whether it stores a value, and the
	 * schema of its type-specific options (used by the admin app to render settings).
	 */
	public static function types() {
		if ( null !== self::$types ) {
			return self::$types;
		}

		$prepend   = self::opt( 'text', __( 'Prepend', 'brik-builder' ), '', array( 'help' => __( 'Shown before the input, e.g. "$".', 'brik-builder' ) ) );
		$append    = self::opt( 'text', __( 'Append', 'brik-builder' ), '', array( 'help' => __( 'Shown after the input, e.g. "kg".', 'brik-builder' ) ) );
		$maxlength = self::opt( 'number', __( 'Character limit', 'brik-builder' ), '', array( 'min' => 1 ) );
		$choices   = self::opt( 'choices', __( 'Choices', 'brik-builder' ), array(), array( 'help' => __( 'One choice per row: a stored value and a label.', 'brik-builder' ) ) );
		$return    = self::select_opt(
			__( 'Return', 'brik-builder' ),
			array(
				'value' => __( 'Value', 'brik-builder' ),
				'label' => __( 'Label', 'brik-builder' ),
				'array' => __( 'Value and label', 'brik-builder' ),
			),
			'value'
		);
		$layout    = self::select_opt(
			__( 'Layout', 'brik-builder' ),
			array(
				'vertical'   => __( 'Vertical', 'brik-builder' ),
				'horizontal' => __( 'Horizontal', 'brik-builder' ),
			),
			'vertical'
		);
		$allow_null = self::opt( 'boolean', __( 'Allow empty', 'brik-builder' ), false );
		$min        = self::opt( 'number', __( 'Minimum', 'brik-builder' ), '' );
		$max        = self::opt( 'number', __( 'Maximum', 'brik-builder' ), '' );
		$mimes      = self::opt( 'text', __( 'Allowed file types', 'brik-builder' ), '', array( 'help' => __( 'Comma separated extensions, e.g. "jpg, png, pdf". Empty allows all.', 'brik-builder' ) ) );
		$sizes      = array( 'thumbnail' => __( 'Thumbnail', 'brik-builder' ), 'medium' => __( 'Medium', 'brik-builder' ), 'large' => __( 'Large', 'brik-builder' ), 'full' => __( 'Full size', 'brik-builder' ) );
		$preview    = self::select_opt( __( 'Preview size', 'brik-builder' ), $sizes, 'medium' );
		$post_types = self::opt( 'post_types', __( 'Post types', 'brik-builder' ), array(), array( 'help' => __( 'Empty allows every post type.', 'brik-builder' ) ) );
		$tax_filter = self::opt( 'terms', __( 'Filter by terms', 'brik-builder' ), array(), array( 'help' => __( 'Only offer posts in these terms ("taxonomy:slug").', 'brik-builder' ) ) );
		$post_ret   = self::select_opt(
			__( 'Return', 'brik-builder' ),
			array(
				'object' => __( 'Post object', 'brik-builder' ),
				'id'     => __( 'Post ID', 'brik-builder' ),
			),
			'object'
		);
		$date_fmt   = static function ( $display, $return ) {
			return array(
				'display_format' => self::opt( 'text', __( 'Display format', 'brik-builder' ), $display, array( 'help' => __( 'PHP date format used when showing the value.', 'brik-builder' ) ) ),
				'return_format'  => self::opt( 'text', __( 'Return format', 'brik-builder' ), $return, array( 'help' => __( 'PHP date format returned by brik_field(). Empty uses the site setting.', 'brik-builder' ) ) ),
			);
		};
		$sub_fields = self::opt( 'fields', __( 'Sub fields', 'brik-builder' ), array() );

		$number_format = self::select_opt(
			__( 'Number format', 'brik-builder' ),
			array(
				'none'      => __( 'Plain (1999.5)', 'brik-builder' ),
				'thousands' => __( 'Thousands separator (1,999.5)', 'brik-builder' ),
				'currency'  => __( 'Currency (1,999.50)', 'brik-builder' ),
			),
			'none'
		);

		$types = array(
			'text'         => array(
				'label'    => __( 'Text', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'type',
				'options'  => array(
					'maxlength' => $maxlength,
					'prepend'   => $prepend,
					'append'    => $append,
				),
			),
			'textarea'     => array(
				'label'    => __( 'Text area', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'align-left',
				'options'  => array(
					'rows'      => self::opt( 'number', __( 'Rows', 'brik-builder' ), 4, array( 'min' => 1 ) ),
					'maxlength' => $maxlength,
					'new_lines' => self::select_opt(
						__( 'New lines', 'brik-builder' ),
						array(
							'wpautop' => __( 'Paragraphs', 'brik-builder' ),
							'br'      => __( 'Line breaks', 'brik-builder' ),
							''        => __( 'No formatting', 'brik-builder' ),
						),
						'wpautop'
					),
				),
			),
			'number'       => array(
				'label'    => __( 'Number', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'hash',
				'options'  => array(
					'min'     => $min,
					'max'     => $max,
					'step'    => self::opt( 'number', __( 'Step', 'brik-builder' ), '' ),
					'prepend' => $prepend,
					'append'  => $append,
					'format'  => $number_format,
				),
			),
			'range'        => array(
				'label'    => __( 'Range', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'sliders-horizontal',
				'options'  => array(
					'min'     => self::opt( 'number', __( 'Minimum', 'brik-builder' ), 0 ),
					'max'     => self::opt( 'number', __( 'Maximum', 'brik-builder' ), 100 ),
					'step'    => self::opt( 'number', __( 'Step', 'brik-builder' ), 1 ),
					'prepend' => $prepend,
					'append'  => $append,
					'format'  => $number_format,
				),
			),
			'email'        => array(
				'label'    => __( 'Email', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'mail',
				'options'  => array(
					'maxlength' => $maxlength,
					'prepend'   => $prepend,
					'append'    => $append,
				),
			),
			'url'          => array(
				'label'    => __( 'URL', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'link-2',
				'options'  => array(
					'maxlength' => $maxlength,
					'prepend'   => $prepend,
					'append'    => $append,
				),
			),
			'password'     => array(
				'label'    => __( 'Password', 'brik-builder' ),
				'category' => 'basic',
				'icon'     => 'key-round',
				'options'  => array(
					'maxlength' => $maxlength,
					'prepend'   => $prepend,
					'append'    => $append,
				),
			),
			'wysiwyg'      => array(
				'label'    => __( 'Rich text', 'brik-builder' ),
				'category' => 'content',
				'icon'     => 'pilcrow',
				'options'  => array(
					'toolbar' => self::select_opt(
						__( 'Toolbar', 'brik-builder' ),
						array(
							'full'  => __( 'Full', 'brik-builder' ),
							'basic' => __( 'Basic', 'brik-builder' ),
						),
						'full'
					),
					'media'   => self::opt( 'boolean', __( 'Media upload button', 'brik-builder' ), true ),
				),
			),
			'oembed'       => array(
				'label'    => __( 'Embed', 'brik-builder' ),
				'category' => 'content',
				'icon'     => 'square-play',
				'options'  => array(),
			),
			'image'        => array(
				'label'    => __( 'Image', 'brik-builder' ),
				'category' => 'content',
				'icon'     => 'image',
				'options'  => array(
					'mime_types'    => $mimes,
					'preview_size'  => $preview,
					'return_format' => self::select_opt(
						__( 'Return', 'brik-builder' ),
						array(
							'array' => __( 'Image array', 'brik-builder' ),
							'url'   => __( 'Image URL', 'brik-builder' ),
							'id'    => __( 'Image ID', 'brik-builder' ),
						),
						'array'
					),
				),
			),
			'file'         => array(
				'label'    => __( 'File', 'brik-builder' ),
				'category' => 'content',
				'icon'     => 'file',
				'options'  => array(
					'mime_types'    => $mimes,
					'return_format' => self::select_opt(
						__( 'Return', 'brik-builder' ),
						array(
							'array' => __( 'File array', 'brik-builder' ),
							'url'   => __( 'File URL', 'brik-builder' ),
							'id'    => __( 'File ID', 'brik-builder' ),
						),
						'array'
					),
				),
			),
			'gallery'      => array(
				'label'    => __( 'Gallery', 'brik-builder' ),
				'category' => 'content',
				'icon'     => 'images',
				'options'  => array(
					'min'          => self::opt( 'number', __( 'Minimum images', 'brik-builder' ), '' ),
					'max'          => self::opt( 'number', __( 'Maximum images', 'brik-builder' ), '' ),
					'mime_types'   => $mimes,
					'preview_size' => self::select_opt( __( 'Preview size', 'brik-builder' ), $sizes, 'thumbnail' ),
				),
			),
			'select'       => array(
				'label'    => __( 'Select', 'brik-builder' ),
				'category' => 'choice',
				'icon'     => 'chevrons-up-down',
				'options'  => array(
					'choices'       => $choices,
					'multiple'      => self::opt( 'boolean', __( 'Allow several', 'brik-builder' ), false ),
					'allow_null'    => $allow_null,
					'return_format' => $return,
				),
			),
			'checkbox'     => array(
				'label'    => __( 'Checkboxes', 'brik-builder' ),
				'category' => 'choice',
				'icon'     => 'square-check',
				'options'  => array(
					'choices'       => $choices,
					'layout'        => $layout,
					'return_format' => $return,
				),
			),
			'radio'        => array(
				'label'    => __( 'Radio buttons', 'brik-builder' ),
				'category' => 'choice',
				'icon'     => 'circle-dot',
				'options'  => array(
					'choices'       => $choices,
					'layout'        => $layout,
					'allow_null'    => $allow_null,
					'return_format' => $return,
				),
			),
			'button_group' => array(
				'label'    => __( 'Button group', 'brik-builder' ),
				'category' => 'choice',
				'icon'     => 'rectangle-ellipsis',
				'options'  => array(
					'choices'       => $choices,
					'allow_null'    => $allow_null,
					'return_format' => $return,
				),
			),
			'toggle'       => array(
				'label'    => __( 'Toggle', 'brik-builder' ),
				'category' => 'choice',
				'icon'     => 'toggle-right',
				'options'  => array(
					'message'  => self::opt( 'text', __( 'Message', 'brik-builder' ), '', array( 'help' => __( 'Text next to the switch.', 'brik-builder' ) ) ),
					'on_text'  => self::opt( 'text', __( 'On text', 'brik-builder' ), '' ),
					'off_text' => self::opt( 'text', __( 'Off text', 'brik-builder' ), '' ),
				),
			),
			'link'         => array(
				'label'    => __( 'Link', 'brik-builder' ),
				'category' => 'relational',
				'icon'     => 'link',
				'options'  => array(),
			),
			'post_object'  => array(
				'label'    => __( 'Post', 'brik-builder' ),
				'category' => 'relational',
				'icon'     => 'file-text',
				'options'  => array(
					'post_types'    => $post_types,
					'taxonomy'      => $tax_filter,
					'multiple'      => self::opt( 'boolean', __( 'Allow several', 'brik-builder' ), false ),
					'allow_null'    => $allow_null,
					'return_format' => $post_ret,
				),
			),
			'relationship' => array(
				'label'    => __( 'Relationship', 'brik-builder' ),
				'category' => 'relational',
				'icon'     => 'git-compare-arrows',
				'options'  => array(
					'post_types'    => $post_types,
					'taxonomy'      => $tax_filter,
					'min'           => self::opt( 'number', __( 'Minimum posts', 'brik-builder' ), '' ),
					'max'           => self::opt( 'number', __( 'Maximum posts', 'brik-builder' ), '' ),
					'return_format' => $post_ret,
				),
			),
			'taxonomy'     => array(
				'label'    => __( 'Taxonomy terms', 'brik-builder' ),
				'category' => 'relational',
				'icon'     => 'tags',
				'options'  => array(
					'taxonomy'      => self::opt( 'taxonomy', __( 'Taxonomy', 'brik-builder' ), 'category' ),
					'field_type'    => self::select_opt(
						__( 'Appearance', 'brik-builder' ),
						array(
							'select'       => __( 'Select', 'brik-builder' ),
							'multi_select' => __( 'Multi select', 'brik-builder' ),
							'checkbox'     => __( 'Checkboxes', 'brik-builder' ),
							'radio'        => __( 'Radio buttons', 'brik-builder' ),
						),
						'checkbox'
					),
					'save_terms'    => self::opt( 'boolean', __( 'Assign terms to the post', 'brik-builder' ), false ),
					'load_terms'    => self::opt( 'boolean', __( 'Load the post’s terms', 'brik-builder' ), false ),
					'allow_null'    => $allow_null,
					'return_format' => self::select_opt(
						__( 'Return', 'brik-builder' ),
						array(
							'object' => __( 'Term object', 'brik-builder' ),
							'id'     => __( 'Term ID', 'brik-builder' ),
						),
						'object'
					),
				),
			),
			'user'         => array(
				'label'    => __( 'User', 'brik-builder' ),
				'category' => 'relational',
				'icon'     => 'user',
				'options'  => array(
					'role'          => self::opt( 'roles', __( 'Roles', 'brik-builder' ), array(), array( 'help' => __( 'Empty allows every role.', 'brik-builder' ) ) ),
					'multiple'      => self::opt( 'boolean', __( 'Allow several', 'brik-builder' ), false ),
					'allow_null'    => $allow_null,
					'return_format' => self::select_opt(
						__( 'Return', 'brik-builder' ),
						array(
							'array'  => __( 'User array', 'brik-builder' ),
							'object' => __( 'User object', 'brik-builder' ),
							'id'     => __( 'User ID', 'brik-builder' ),
						),
						'array'
					),
				),
			),
			'date'         => array(
				'label'    => __( 'Date', 'brik-builder' ),
				'category' => 'advanced',
				'icon'     => 'calendar',
				'options'  => $date_fmt( 'F j, Y', '' ),
			),
			'datetime'     => array(
				'label'    => __( 'Date and time', 'brik-builder' ),
				'category' => 'advanced',
				'icon'     => 'calendar-clock',
				'options'  => $date_fmt( 'F j, Y g:i a', '' ),
			),
			'time'         => array(
				'label'    => __( 'Time', 'brik-builder' ),
				'category' => 'advanced',
				'icon'     => 'clock',
				'options'  => $date_fmt( 'g:i a', '' ),
			),
			'color'        => array(
				'label'    => __( 'Color', 'brik-builder' ),
				'category' => 'advanced',
				'icon'     => 'palette',
				'options'  => array(
					'alpha' => self::opt( 'boolean', __( 'Allow transparency', 'brik-builder' ), false ),
				),
			),
			'map'          => array(
				'label'    => __( 'Map', 'brik-builder' ),
				'category' => 'advanced',
				'icon'     => 'map-pin',
				'options'  => array(
					'center_lat' => self::opt( 'number', __( 'Center latitude', 'brik-builder' ), '' ),
					'center_lng' => self::opt( 'number', __( 'Center longitude', 'brik-builder' ), '' ),
					'zoom'       => self::opt( 'number', __( 'Zoom', 'brik-builder' ), 14, array( 'min' => 1, 'max' => 21 ) ),
					'height'     => self::opt( 'number', __( 'Height (px)', 'brik-builder' ), 320, array( 'min' => 120 ) ),
				),
			),
			'repeater'     => array(
				'label'    => __( 'Repeater', 'brik-builder' ),
				'category' => 'layout',
				'icon'     => 'list-plus',
				'options'  => array(
					'sub_fields'   => $sub_fields,
					'min'          => self::opt( 'number', __( 'Minimum rows', 'brik-builder' ), '' ),
					'max'          => self::opt( 'number', __( 'Maximum rows', 'brik-builder' ), '' ),
					'button_label' => self::opt( 'text', __( 'Button label', 'brik-builder' ), '' ),
					'layout'       => self::select_opt(
						__( 'Layout', 'brik-builder' ),
						array(
							'block' => __( 'Block', 'brik-builder' ),
							'table' => __( 'Table', 'brik-builder' ),
							'row'   => __( 'Row', 'brik-builder' ),
						),
						'block'
					),
				),
			),
			'group'        => array(
				'label'    => __( 'Group', 'brik-builder' ),
				'category' => 'layout',
				'icon'     => 'group',
				'options'  => array(
					'sub_fields' => $sub_fields,
					'layout'     => self::select_opt(
						__( 'Layout', 'brik-builder' ),
						array(
							'block' => __( 'Block', 'brik-builder' ),
							'row'   => __( 'Row', 'brik-builder' ),
						),
						'block'
					),
				),
			),
			'message'      => array(
				'label'     => __( 'Message', 'brik-builder' ),
				'category'  => 'layout',
				'icon'      => 'message-square-text',
				'has_value' => false,
				'options'   => array(
					'message' => self::opt( 'textarea', __( 'Message', 'brik-builder' ), '' ),
				),
			),
			'tab'          => array(
				'label'     => __( 'Tab', 'brik-builder' ),
				'category'  => 'layout',
				'icon'      => 'panel-top',
				'has_value' => false,
				'options'   => array(
					'placement' => self::select_opt(
						__( 'Placement', 'brik-builder' ),
						array(
							'top'  => __( 'Top', 'brik-builder' ),
							'left' => __( 'Left', 'brik-builder' ),
						),
						'top'
					),
				),
			),
		);

		foreach ( $types as $name => &$type ) {
			$type['name']      = $name;
			$type['has_value'] = isset( $type['has_value'] ) ? $type['has_value'] : true;
		}
		unset( $type );

		/**
		 * Field types offered by Brik content. Adding a type also requires handling it through
		 * the brik/content/sanitize_field, brik/content/format_field and brik/content/field_html filters.
		 */
		self::$types = apply_filters( 'brik/content/field_types', $types );
		return self::$types;
	}

	public static function type( $type ) {
		$types = self::types();
		return isset( $types[ $type ] ) ? $types[ $type ] : null;
	}

	public static function has_value( $type ) {
		$def = self::type( $type );
		return $def ? ! empty( $def['has_value'] ) : false;
	}

	/**
	 * Types for GET /content: everything but the PHP-only bits.
	 */
	public static function for_client() {
		$out = array();
		foreach ( self::types() as $name => $type ) {
			$out[ $name ] = array(
				'name'      => $name,
				'label'     => $type['label'],
				'category'  => $type['category'],
				'icon'      => $type['icon'],
				'has_value' => (bool) $type['has_value'],
				'options'   => $type['options'] ? $type['options'] : new \stdClass(),
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Options.
	 * ------------------------------------------------------------------- */

	/**
	 * Keep known options only, cast to the schema types, defaults filled in.
	 */
	public static function normalize_options( $type, array $opts ) {
		$def = self::type( $type );
		if ( ! $def ) {
			return array();
		}
		$out = array();
		foreach ( $def['options'] as $key => $schema ) {
			$v = array_key_exists( $key, $opts ) ? $opts[ $key ] : $schema['default'];
			switch ( $schema['type'] ) {
				case 'boolean':
					$v = filter_var( $v, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'number':
					$v = is_numeric( $v ) ? 0 + $v : '';
					break;
				case 'select':
					$allowed = wp_list_pluck( $schema['choices'], 'value' );
					$v       = is_scalar( $v ) && in_array( (string) $v, $allowed, true ) ? (string) $v : $schema['default'];
					break;
				case 'choices':
					$v = self::normalize_choices( $v );
					break;
				case 'fields':
					$v = Registry::normalize_fields( is_array( $v ) ? $v : array() );
					break;
				case 'post_types':
				case 'roles':
					$v = array_values( array_filter( array_map( 'sanitize_key', is_string( $v ) ? explode( ',', $v ) : (array) $v ) ) );
					break;
				case 'terms':
					$list = is_string( $v ) ? explode( ',', $v ) : (array) $v;
					$v    = array();
					foreach ( $list as $item ) {
						if ( is_scalar( $item ) && preg_match( '/^([a-z0-9_-]+):([a-z0-9_-]+)$/', trim( (string) $item ), $m ) ) {
							$v[] = $m[1] . ':' . $m[2];
						}
					}
					break;
				case 'taxonomy':
					$v = is_scalar( $v ) ? sanitize_key( (string) $v ) : '';
					break;
				case 'textarea':
					$v = is_scalar( $v ) ? wp_kses_post( (string) $v ) : '';
					break;
				default:
					$v = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
			}
			$out[ $key ] = $v;
		}
		return $out;
	}

	/**
	 * Choices as [{value, label}]. Accepts that shape, a value => label map, or
	 * "value : label" lines.
	 */
	public static function normalize_choices( $raw ) {
		if ( is_string( $raw ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $raw );
			$raw   = array();
			foreach ( $lines as $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$parts = array_map( 'trim', explode( ':', $line, 2 ) );
				$raw[] = array(
					'value' => $parts[0],
					'label' => isset( $parts[1] ) ? $parts[1] : $parts[0],
				);
			}
		}
		$out  = array();
		$seen = array();
		foreach ( (array) $raw as $k => $choice ) {
			if ( is_array( $choice ) ) {
				$value = isset( $choice['value'] ) && is_scalar( $choice['value'] ) ? (string) $choice['value'] : '';
				$label = isset( $choice['label'] ) && is_scalar( $choice['label'] ) ? (string) $choice['label'] : $value;
			} elseif ( is_scalar( $choice ) ) {
				$value = is_string( $k ) ? $k : (string) $choice;
				$label = (string) $choice;
			} else {
				continue;
			}
			$value = sanitize_text_field( $value );
			if ( '' === $value || isset( $seen[ $value ] ) ) {
				continue;
			}
			$seen[ $value ] = true;
			$out[]          = array(
				'value' => $value,
				'label' => sanitize_text_field( '' !== $label ? $label : $value ),
			);
		}
		return $out;
	}

	private static function o( array $field, $key, $default = '' ) {
		return isset( $field['options'][ $key ] ) && '' !== $field['options'][ $key ] && null !== $field['options'][ $key ] ? $field['options'][ $key ] : $default;
	}

	public static function choice_values( array $field ) {
		return wp_list_pluck( self::o( $field, 'choices', array() ), 'value' );
	}

	public static function choice_label( array $field, $value ) {
		foreach ( self::o( $field, 'choices', array() ) as $choice ) {
			if ( (string) $choice['value'] === (string) $value ) {
				return $choice['label'];
			}
		}
		return (string) $value;
	}

	/**
	 * Whether the stored value of a field is a list.
	 */
	public static function is_multiple( array $field ) {
		switch ( $field['type'] ) {
			case 'checkbox':
			case 'gallery':
			case 'relationship':
			case 'repeater':
				return true;
			case 'select':
			case 'post_object':
			case 'user':
				return (bool) self::o( $field, 'multiple', false );
			case 'taxonomy':
				return in_array( self::o( $field, 'field_type', 'checkbox' ), array( 'checkbox', 'multi_select' ), true );
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Sanitizing.
	 * ------------------------------------------------------------------- */

	/**
	 * Empty value for a field: '' for scalars, [] for lists and objects.
	 */
	public static function empty_value( array $field ) {
		if ( 'toggle' === $field['type'] ) {
			return false;
		}
		if ( self::is_multiple( $field ) || in_array( $field['type'], array( 'group', 'link', 'map' ), true ) ) {
			return array();
		}
		return '';
	}

	public static function is_empty( array $field, $value ) {
		if ( 'toggle' === $field['type'] ) {
			return false;
		}
		if ( null === $value || '' === $value || array() === $value || false === $value ) {
			return true;
		}
		if ( in_array( $field['type'], array( 'image', 'file', 'post_object', 'user', 'taxonomy' ), true ) && ! self::is_multiple( $field ) ) {
			return ! (int) $value;
		}
		if ( 'link' === $field['type'] ) {
			return ! is_array( $value ) || empty( $value['url'] );
		}
		if ( 'map' === $field['type'] ) {
			return ! is_array( $value ) || ( '' === (string) ( isset( $value['lat'] ) ? $value['lat'] : '' ) && '' === (string) ( isset( $value['address'] ) ? $value['address'] : '' ) );
		}
		if ( 'group' === $field['type'] && is_array( $value ) ) {
			foreach ( $field['options']['sub_fields'] as $sub ) {
				if ( Fields::has_value( $sub['type'] ) && isset( $value[ $sub['name'] ] ) && ! self::is_empty( $sub, $value[ $sub['name'] ] ) && 'toggle' !== $sub['type'] ) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	private static function maxlength( $text, array $field ) {
		$max = (int) self::o( $field, 'maxlength', 0 );
		if ( $max > 0 ) {
			$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
		}
		return $text;
	}

	private static function id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = '' === trim( $value ) ? array() : explode( ',', $value );
		}
		if ( is_array( $value ) && ( isset( $value['id'] ) || isset( $value['ID'] ) ) ) {
			$value = array( $value );
		}
		$out = array();
		foreach ( (array) $value as $item ) {
			if ( is_object( $item ) ) {
				$item = isset( $item->ID ) ? $item->ID : ( isset( $item->term_id ) ? $item->term_id : 0 );
			} elseif ( is_array( $item ) ) {
				$item = isset( $item['id'] ) ? $item['id'] : ( isset( $item['ID'] ) ? $item['ID'] : 0 );
			}
			if ( is_numeric( $item ) && (int) $item > 0 && (string) (int) $item === trim( (string) $item ) ) {
				$out[] = (int) $item;
			}
		}
		return array_values( array_unique( $out ) );
	}

	private static function mime_ok( array $field, $id ) {
		$allowed = array_filter( array_map( 'trim', explode( ',', strtolower( (string) self::o( $field, 'mime_types', '' ) ) ) ) );
		if ( ! $allowed ) {
			return true;
		}
		$file = get_attached_file( $id );
		$ext  = strtolower( pathinfo( $file ? $file : (string) wp_get_attachment_url( $id ), PATHINFO_EXTENSION ) );
		$allowed = array_map(
			static function ( $e ) {
				return ltrim( $e, '.' );
			},
			$allowed
		);
		return in_array( $ext, $allowed, true ) || ( 'jpeg' === $ext && in_array( 'jpg', $allowed, true ) ) || ( 'jpg' === $ext && in_array( 'jpeg', $allowed, true ) );
	}

	private static function attachment_ok( array $field, $id, $image ) {
		if ( 'attachment' !== get_post_type( $id ) ) {
			return false;
		}
		if ( $image && ! wp_attachment_is_image( $id ) ) {
			return false;
		}
		return self::mime_ok( $field, $id );
	}

	private static function post_ok( array $field, $id ) {
		$post = get_post( $id );
		if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) || 'revision' === $post->post_type ) {
			return false;
		}
		$types = self::o( $field, 'post_types', array() );
		return ! $types || in_array( $post->post_type, (array) $types, true );
	}

	private static function user_ok( array $field, $id ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return false;
		}
		$roles = (array) self::o( $field, 'role', array() );
		return ! $roles || (bool) array_intersect( $roles, (array) $user->roles );
	}

	private static function term_ok( array $field, $id ) {
		$term = get_term( $id, self::o( $field, 'taxonomy', 'category' ) );
		return $term && ! is_wp_error( $term );
	}

	public static function sanitize_color( $value, $alpha = true ) {
		$value = strtolower( trim( (string) $value ) );
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ) {
			return $value;
		}
		if ( $alpha && preg_match( '/^#([0-9a-f]{4}|[0-9a-f]{8})$/', $value ) ) {
			return $value;
		}
		$num = '\s*-?[\d.]+%?\s*';
		if ( preg_match( '/^(rgb|hsl)\(' . $num . ',' . $num . ',' . $num . '\)$/', $value )
			|| preg_match( '/^(rgb|hsl|oklch|oklab|lab|lch)\((\s*-?[\d.]+(%|deg)?){3}\s*\)$/', $value ) ) {
			return $value;
		}
		if ( $alpha && ( preg_match( '/^(rgba|hsla)\(' . $num . ',' . $num . ',' . $num . ',' . $num . '\)$/', $value )
			|| preg_match( '/^(rgb|hsl|oklch|oklab|lab|lch)\((\s*-?[\d.]+(%|deg)?){3}\s*\/\s*[\d.]+%?\s*\)$/', $value ) ) ) {
			return $value;
		}
		return '';
	}

	private static function sanitize_date( $value, $format ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$tz = wp_timezone();
		foreach ( array( $format, 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d', 'H:i', 'H:i:s', 'Ymd' ) as $try ) {
			$dt = \DateTime::createFromFormat( '!' . $try, $value, $tz );
			$errors = \DateTime::getLastErrors();
			if ( $dt && ( ! $errors || ( ! $errors['warning_count'] && ! $errors['error_count'] ) ) ) {
				return $dt->format( $format );
			}
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}/', $value ) || preg_match( '/[a-z]{3}/i', $value ) ) {
			$ts = strtotime( $value );
			if ( false !== $ts ) {
				return wp_date( $format, $ts, new \DateTimeZone( 'UTC' ) );
			}
		}
		return '';
	}

	public static function date_storage_format( $type ) {
		return 'date' === $type ? 'Y-m-d' : ( 'time' === $type ? 'H:i:s' : 'Y-m-d H:i:s' );
	}

	private static function sub_input( array $row, array $sub ) {
		if ( array_key_exists( $sub['key'], $row ) ) {
			return $row[ $sub['key'] ];
		}
		return array_key_exists( $sub['name'], $row ) ? $row[ $sub['name'] ] : null;
	}

	/**
	 * Sanitize raw input into the stored shape. Invalid parts are dropped, numbers are
	 * clamped to their range, lists are trimmed to their maximum.
	 */
	public static function sanitize( array $field, $value ) {
		$o = $field['options'];
		switch ( $field['type'] ) {
			case 'text':
			case 'password':
				$out = is_scalar( $value ) ? self::maxlength( sanitize_text_field( (string) $value ), $field ) : '';
				break;
			case 'textarea':
				// Browsers submit textarea newlines as CRLF.
				$out = is_scalar( $value ) ? self::maxlength( sanitize_textarea_field( str_replace( "\r\n", "\n", (string) $value ) ), $field ) : '';
				break;
			case 'email':
				$out = is_scalar( $value ) ? sanitize_email( (string) $value ) : '';
				$out = $out && is_email( $out ) ? self::maxlength( $out, $field ) : '';
				break;
			case 'url':
			case 'oembed':
				$out = is_scalar( $value ) ? esc_url_raw( trim( (string) $value ), 'url' === $field['type'] ? array( 'http', 'https', 'mailto', 'tel' ) : array( 'http', 'https' ) ) : '';
				$out = 'url' === $field['type'] ? self::maxlength( $out, $field ) : $out;
				break;
			case 'wysiwyg':
				$out = is_scalar( $value ) ? wp_kses_post( str_replace( "\r\n", "\n", (string) $value ) ) : '';
				break;
			case 'number':
			case 'range':
				if ( is_bool( $value ) || ! is_scalar( $value ) || ! is_numeric( trim( (string) $value ) ) ) {
					$out = '';
					break;
				}
				$out = 0 + trim( (string) $value );
				if ( is_float( $out ) && ( is_nan( $out ) || is_infinite( $out ) ) ) {
					$out = '';
					break;
				}
				if ( '' !== $o['min'] && $out < $o['min'] ) {
					$out = $o['min'];
				}
				if ( '' !== $o['max'] && $out > $o['max'] ) {
					$out = $o['max'];
				}
				break;
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$allowed = self::choice_values( $field );
				if ( self::is_multiple( $field ) ) {
					$list = is_array( $value ) ? $value : ( is_scalar( $value ) && '' !== (string) $value ? array( $value ) : array() );
					$out  = array();
					foreach ( $list as $item ) {
						if ( is_scalar( $item ) && in_array( (string) $item, $allowed, true ) && ! in_array( (string) $item, $out, true ) ) {
							$out[] = (string) $item;
						}
					}
				} else {
					if ( is_array( $value ) ) {
						$value = reset( $value );
					}
					$out = is_scalar( $value ) && in_array( (string) $value, $allowed, true ) ? (string) $value : '';
				}
				break;
			case 'toggle':
				$out = is_scalar( $value ) ? filter_var( $value, FILTER_VALIDATE_BOOLEAN ) : false;
				break;
			case 'date':
			case 'datetime':
			case 'time':
				$out = is_scalar( $value ) ? self::sanitize_date( $value, self::date_storage_format( $field['type'] ) ) : '';
				break;
			case 'color':
				$out = is_scalar( $value ) ? self::sanitize_color( $value, (bool) self::o( $field, 'alpha', false ) ) : '';
				break;
			case 'image':
			case 'file':
				$ids = self::id_list( $value );
				$id  = $ids ? $ids[0] : 0;
				$out = $id && self::attachment_ok( $field, $id, 'image' === $field['type'] ) ? $id : '';
				break;
			case 'gallery':
				$out = array();
				foreach ( self::id_list( $value ) as $id ) {
					if ( self::attachment_ok( $field, $id, true ) ) {
						$out[] = $id;
					}
				}
				$max = (int) self::o( $field, 'max', 0 );
				$out = $max > 0 ? array_slice( $out, 0, $max ) : $out;
				break;
			case 'link':
				if ( is_string( $value ) ) {
					$value = array( 'url' => $value );
				}
				$value = is_array( $value ) ? $value : array();
				// esc_url_raw() keeps relative links ("/contact", "#top") and drops unsafe schemes.
				$url = isset( $value['url'] ) && is_scalar( $value['url'] ) ? esc_url_raw( trim( (string) $value['url'] ), array( 'http', 'https', 'mailto', 'tel' ) ) : '';
				$target = ! empty( $value['target'] ) && '_blank' === $value['target'] || ! empty( $value['new_tab'] ) ? '_blank' : '';
				$out    = '' === $url ? array() : array(
					'url'    => $url,
					'title'  => isset( $value['title'] ) && is_scalar( $value['title'] ) ? sanitize_text_field( (string) $value['title'] ) : '',
					'target' => $target,
				);
				break;
			case 'post_object':
			case 'relationship':
				$out = array();
				foreach ( self::id_list( $value ) as $id ) {
					if ( self::post_ok( $field, $id ) ) {
						$out[] = $id;
					}
				}
				if ( 'relationship' === $field['type'] ) {
					$max = (int) self::o( $field, 'max', 0 );
					$out = $max > 0 ? array_slice( $out, 0, $max ) : $out;
				} elseif ( ! self::is_multiple( $field ) ) {
					$out = $out ? $out[0] : '';
				}
				break;
			case 'user':
				$out = array();
				foreach ( self::id_list( $value ) as $id ) {
					if ( self::user_ok( $field, $id ) ) {
						$out[] = $id;
					}
				}
				$out = self::is_multiple( $field ) ? $out : ( $out ? $out[0] : '' );
				break;
			case 'taxonomy':
				$out = array();
				foreach ( self::id_list( $value ) as $id ) {
					if ( self::term_ok( $field, $id ) ) {
						$out[] = $id;
					}
				}
				$out = self::is_multiple( $field ) ? $out : ( $out ? $out[0] : '' );
				break;
			case 'map':
				$value = is_array( $value ) ? $value : array();
				$lat   = isset( $value['lat'] ) && is_numeric( $value['lat'] ) && abs( (float) $value['lat'] ) <= 90 ? round( (float) $value['lat'], 7 ) : '';
				$lng   = isset( $value['lng'] ) && is_numeric( $value['lng'] ) && abs( (float) $value['lng'] ) <= 180 ? round( (float) $value['lng'], 7 ) : '';
				if ( '' === $lat || '' === $lng ) {
					$lat = '';
					$lng = '';
				}
				$address = isset( $value['address'] ) && is_scalar( $value['address'] ) ? sanitize_text_field( (string) $value['address'] ) : '';
				$out     = array();
				if ( '' !== $address || '' !== $lat ) {
					$out = array( 'address' => $address );
					// Coordinates are left out rather than stored empty, so the REST schema stays numeric.
					if ( '' !== $lat ) {
						$out['lat'] = $lat;
						$out['lng'] = $lng;
					}
				}
				if ( $out && isset( $value['zoom'] ) && is_numeric( $value['zoom'] ) ) {
					$out['zoom'] = max( 1, min( 21, (int) $value['zoom'] ) );
				}
				break;
			case 'repeater':
				$out = array();
				foreach ( is_array( $value ) ? $value : array() as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$out[] = self::sanitize_row( $field['options']['sub_fields'], $row );
				}
				$max = (int) self::o( $field, 'max', 0 );
				$out = $max > 0 ? array_slice( $out, 0, $max ) : $out;
				break;
			case 'group':
				$out = self::sanitize_row( $field['options']['sub_fields'], is_array( $value ) ? $value : array() );
				$out = self::is_empty( $field, $out ) ? array() : $out;
				break;
			default:
				$out = null;
		}

		/**
		 * Sanitized value of a field, for custom types or stricter rules.
		 *
		 * @param mixed $out   Sanitized value.
		 * @param mixed $value Raw value.
		 * @param array $field Field definition.
		 */
		return apply_filters( 'brik/content/sanitize_field', $out, $value, $field );
	}

	/**
	 * Sanitize one repeater row / group value. Input may be keyed by field key or name;
	 * the result is keyed by name.
	 */
	public static function sanitize_row( array $sub_fields, array $row ) {
		$out = array();
		foreach ( $sub_fields as $sub ) {
			if ( ! self::has_value( $sub['type'] ) ) {
				continue;
			}
			$raw                 = self::sub_input( $row, $sub );
			$out[ $sub['name'] ] = null === $raw ? self::empty_value( $sub ) : self::sanitize( $sub, $raw );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Validation.
	 * ------------------------------------------------------------------- */

	/**
	 * Validate raw input for one field. Returns a list of messages (empty when valid).
	 */
	public static function validate( array $field, $value, $label_prefix = '' ) {
		$errors = array();
		$label  = $label_prefix . ( '' !== $field['label'] ? $field['label'] : $field['name'] );
		$clean  = self::sanitize( $field, $value );
		$empty  = self::is_empty( $field, $clean );
		$given  = ! ( null === $value || '' === $value || array() === $value );
		$o      = $field['options'];

		if ( $empty ) {
			if ( ! empty( $field['required'] ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '%s is required.', 'brik-builder' ), $label );
			}
			if ( $given && ! in_array( $field['type'], array( 'repeater', 'group', 'map', 'link' ), true ) && self::invalid_when_dropped( $field, $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '%s has an invalid value.', 'brik-builder' ), $label );
			}
			if ( 'repeater' !== $field['type'] && 'gallery' !== $field['type'] && 'relationship' !== $field['type'] ) {
				return $errors;
			}
		}

		switch ( $field['type'] ) {
			case 'text':
			case 'textarea':
			case 'password':
			case 'email':
			case 'url':
				$max = (int) self::o( $field, 'maxlength', 0 );
				$len = is_scalar( $value ) ? ( function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value ) : strlen( (string) $value ) ) : 0;
				if ( $max > 0 && $len > $max ) {
					/* translators: 1: field label, 2: character limit */
					$errors[] = sprintf( __( '%1$s must be %2$d characters or fewer.', 'brik-builder' ), $label, $max );
				}
				break;
			case 'number':
			case 'range':
				if ( is_numeric( $value ) ) {
					if ( '' !== $o['min'] && $value < $o['min'] ) {
						/* translators: 1: field label, 2: minimum */
						$errors[] = sprintf( __( '%1$s must be at least %2$s.', 'brik-builder' ), $label, $o['min'] );
					}
					if ( '' !== $o['max'] && $value > $o['max'] ) {
						/* translators: 1: field label, 2: maximum */
						$errors[] = sprintf( __( '%1$s must be at most %2$s.', 'brik-builder' ), $label, $o['max'] );
					}
				}
				break;
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$given_list = is_array( $value ) ? $value : array( $value );
				$allowed    = self::choice_values( $field );
				foreach ( $given_list as $item ) {
					if ( ! is_scalar( $item ) || ( '' !== (string) $item && ! in_array( (string) $item, $allowed, true ) ) ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '%s has a choice that is not allowed.', 'brik-builder' ), $label );
						break;
					}
				}
				break;
			case 'image':
			case 'file':
			case 'post_object':
			case 'user':
			case 'taxonomy':
				$ids  = self::id_list( $value );
				$kept = (array) $clean;
				if ( count( $ids ) > count( array_filter( $kept ) ) && ( self::is_multiple( $field ) || ! $kept ) ) {
					/* translators: %s: field label */
					$errors[] = in_array( $field['type'], array( 'image', 'file' ), true ) && $ids && 'attachment' === get_post_type( $ids[0] )
						/* translators: %s: field label */
						? sprintf( __( '%s does not accept this file type.', 'brik-builder' ), $label )
						/* translators: %s: field label */
						: sprintf( __( '%s refers to something that does not exist.', 'brik-builder' ), $label );
				}
				break;
			case 'gallery':
			case 'relationship':
				$count = count( self::id_list( $value ) );
				$min   = (int) self::o( $field, 'min', 0 );
				$max   = (int) self::o( $field, 'max', 0 );
				if ( $count && $min > 0 && $count < $min ) {
					/* translators: 1: field label, 2: minimum count */
					$errors[] = sprintf( _n( '%1$s needs at least %2$d item.', '%1$s needs at least %2$d items.', $min, 'brik-builder' ), $label, $min );
				}
				if ( $max > 0 && $count > $max ) {
					/* translators: 1: field label, 2: maximum count */
					$errors[] = sprintf( _n( '%1$s allows at most %2$d item.', '%1$s allows at most %2$d items.', $max, 'brik-builder' ), $label, $max );
				}
				break;
			case 'repeater':
				$rows = is_array( $value ) ? array_values( array_filter( $value, 'is_array' ) ) : array();
				$min  = (int) self::o( $field, 'min', 0 );
				$max  = (int) self::o( $field, 'max', 0 );
				if ( $min > 0 && count( $rows ) < $min && ( $rows || ! empty( $field['required'] ) ) ) {
					/* translators: 1: field label, 2: minimum rows */
					$errors[] = sprintf( _n( '%1$s needs at least %2$d row.', '%1$s needs at least %2$d rows.', $min, 'brik-builder' ), $label, $min );
				}
				if ( $max > 0 && count( $rows ) > $max ) {
					/* translators: 1: field label, 2: maximum rows */
					$errors[] = sprintf( _n( '%1$s allows at most %2$d row.', '%1$s allows at most %2$d rows.', $max, 'brik-builder' ), $label, $max );
				}
				foreach ( $rows as $i => $row ) {
					/* translators: 1: repeater label, 2: row number */
					$prefix = sprintf( __( '%1$s row %2$d: ', 'brik-builder' ), $label, $i + 1 );
					$errors = array_merge( $errors, self::validate_set( $field['options']['sub_fields'], $row, $prefix ) );
				}
				break;
			case 'group':
				$errors = array_merge( $errors, self::validate_set( $field['options']['sub_fields'], is_array( $value ) ? $value : array(), $label . ': ' ) );
				break;
			case 'link':
				if ( is_array( $value ) && ! empty( $value['url'] ) && $empty ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '%s has an invalid URL.', 'brik-builder' ), $label );
				}
				break;
		}
		return array_values( array_unique( $errors ) );
	}

	/**
	 * Non-empty input that sanitized down to nothing was bad input, except where empty
	 * strings are a normal "nothing chosen" signal.
	 */
	private static function invalid_when_dropped( array $field, $value ) {
		if ( is_array( $value ) ) {
			return (bool) array_filter(
				$value,
				static function ( $v ) {
					return '' !== $v && null !== $v;
				}
			);
		}
		return '' !== trim( (string) ( is_scalar( $value ) ? $value : 'x' ) ) && '0' !== (string) $value;
	}

	/**
	 * Validate a set of sibling fields (a group's fields or one repeater row), skipping
	 * fields hidden by their conditions. $values may be keyed by field key or name.
	 */
	public static function validate_set( array $fields, array $values, $prefix = '' ) {
		$by_key = self::values_by_key( $fields, $values );
		$errors = array();
		foreach ( $fields as $field ) {
			if ( ! self::has_value( $field['type'] ) || ! self::conditions_met( $field, $by_key ) ) {
				continue;
			}
			$raw    = self::sub_input( $values, $field );
			$errors = array_merge( $errors, self::validate( $field, $raw, $prefix ) );
		}
		return $errors;
	}

	/**
	 * Sanitized sibling values keyed by field key, as conditional rules reference keys.
	 */
	public static function values_by_key( array $fields, array $values ) {
		$out = array();
		foreach ( $fields as $field ) {
			if ( self::has_value( $field['type'] ) ) {
				$raw                  = self::sub_input( $values, $field );
				$out[ $field['key'] ] = null === $raw ? self::empty_value( $field ) : self::sanitize( $field, $raw );
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Conditional logic.
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a field is shown, given sibling values keyed by field key. Conditions are an
	 * OR of AND groups; rules about fields that aren't present are ignored.
	 */
	public static function conditions_met( array $field, array $values ) {
		if ( empty( $field['conditions'] ) ) {
			return true;
		}
		foreach ( $field['conditions'] as $and ) {
			$ok = true;
			foreach ( $and as $rule ) {
				if ( ! array_key_exists( $rule['field'], $values ) ) {
					continue;
				}
				if ( ! self::rule_met( $rule, $values[ $rule['field'] ] ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				return true;
			}
		}
		return false;
	}

	public static function rule_met( array $rule, $actual ) {
		$expected = (string) $rule['value'];
		if ( is_bool( $actual ) ) {
			$actual = $actual ? '1' : '0';
		}
		switch ( $rule['operator'] ) {
			case 'empty':
				return null === $actual || '' === $actual || array() === $actual;
			case '!empty':
				return ! ( null === $actual || '' === $actual || array() === $actual );
			case 'contains':
				if ( is_array( $actual ) ) {
					return in_array( $expected, array_map( 'strval', array_filter( $actual, 'is_scalar' ) ), true );
				}
				return '' !== $expected && false !== stripos( (string) $actual, $expected );
			case '!=':
				return ! self::equals( $actual, $expected );
			default:
				return self::equals( $actual, $expected );
		}
	}

	private static function equals( $actual, $expected ) {
		if ( is_array( $actual ) ) {
			return in_array( $expected, array_map( 'strval', array_filter( $actual, 'is_scalar' ) ), true );
		}
		if ( in_array( $expected, array( 'true', 'false' ), true ) ) {
			$expected = 'true' === $expected ? '1' : '0';
		}
		return (string) $actual === $expected;
	}

	/* ---------------------------------------------------------------------
	 * REST schema.
	 * ------------------------------------------------------------------- */

	public static function rest_schema( array $field, $nested = false ) {
		$id_type = array( 'type' => 'integer' );
		// Only nested values may mix types: WordPress skips meta whose top-level type is a list.
		$num  = $nested ? array( 'number', 'string' ) : 'number';
		$int  = $nested ? array( 'integer', 'string' ) : 'integer';
		$bool = $nested ? array( 'boolean', 'string', 'integer' ) : 'boolean';
		switch ( $field['type'] ) {
			case 'number':
			case 'range':
				$schema = array( 'type' => $num );
				break;
			case 'toggle':
				$schema = array( 'type' => $bool );
				break;
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$schema = self::is_multiple( $field ) ? array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				) : array( 'type' => 'string' );
				break;
			case 'image':
			case 'file':
				$schema = array( 'type' => $int );
				break;
			case 'gallery':
			case 'relationship':
				$schema = array(
					'type'  => 'array',
					'items' => $id_type,
				);
				break;
			case 'post_object':
			case 'user':
			case 'taxonomy':
				$schema = self::is_multiple( $field ) ? array(
					'type'  => 'array',
					'items' => $id_type,
				) : array( 'type' => $int );
				break;
			case 'link':
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(
						'url'    => array( 'type' => 'string' ),
						'title'  => array( 'type' => 'string' ),
						'target' => array( 'type' => 'string' ),
					),
					'additionalProperties' => false,
				);
				break;
			case 'map':
				$schema = array(
					'type'                 => 'object',
					'properties'           => array(
						'address' => array( 'type' => 'string' ),
						'lat'     => array( 'type' => 'number' ),
						'lng'     => array( 'type' => 'number' ),
						'zoom'    => array( 'type' => 'integer' ),
					),
					'additionalProperties' => false,
				);
				break;
			case 'repeater':
				$schema = array(
					'type'  => 'array',
					'items' => self::object_schema( $field['options']['sub_fields'] ),
				);
				break;
			case 'group':
				$schema = self::object_schema( $field['options']['sub_fields'] );
				break;
			default:
				$schema = array( 'type' => 'string' );
		}
		if ( '' !== $field['label'] ) {
			$schema['description'] = $field['label'];
		}
		return $schema;
	}

	private static function object_schema( array $subs ) {
		$props = array();
		foreach ( $subs as $sub ) {
			if ( self::has_value( $sub['type'] ) ) {
				$props[ $sub['name'] ] = self::rest_schema( $sub, true );
			}
		}
		return array(
			'type'                 => 'object',
			'properties'           => $props ? $props : new \stdClass(),
			'additionalProperties' => false,
		);
	}

	/* ---------------------------------------------------------------------
	 * Formatting (brik_field).
	 * ------------------------------------------------------------------- */

	public static function image_data( $id ) {
		$id  = (int) $id;
		$src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;
		if ( ! $src ) {
			return null;
		}
		$sizes = array();
		foreach ( get_intermediate_image_sizes() as $size ) {
			$img = wp_get_attachment_image_src( $id, $size );
			if ( $img ) {
				$sizes[ $size ] = $img[0];
			}
		}
		$sizes['full'] = $src[0];
		return array(
			'id'      => $id,
			'url'     => $src[0],
			'alt'     => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'width'   => (int) $src[1],
			'height'  => (int) $src[2],
			'sizes'   => $sizes,
			'title'   => get_the_title( $id ),
			'caption' => (string) wp_get_attachment_caption( $id ),
		);
	}

	public static function file_data( $id ) {
		$id  = (int) $id;
		$url = $id ? wp_get_attachment_url( $id ) : false;
		if ( ! $url ) {
			return null;
		}
		$path = get_attached_file( $id );
		return array(
			'id'       => $id,
			'url'      => $url,
			'title'    => get_the_title( $id ),
			'filename' => wp_basename( $path ? $path : $url ),
			'filesize' => $path && file_exists( $path ) ? (int) filesize( $path ) : 0,
			'mime'     => (string) get_post_mime_type( $id ),
		);
	}

	/**
	 * Posts that may be shown to the current visitor.
	 */
	private static function visible_posts( array $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post && ( 'publish' === $post->post_status || current_user_can( 'read_post', $post->ID ) ) ) {
				$out[] = $post;
			}
		}
		return $out;
	}

	private static function choice_out( array $field, $value ) {
		switch ( self::o( $field, 'return_format', 'value' ) ) {
			case 'label':
				return self::choice_label( $field, $value );
			case 'array':
				return array(
					'value' => $value,
					'label' => self::choice_label( $field, $value ),
				);
		}
		return $value;
	}

	public static function user_data( $id ) {
		$user = get_userdata( (int) $id );
		if ( ! $user ) {
			return null;
		}
		return array(
			'id'           => $user->ID,
			'display_name' => $user->display_name,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'nicename'     => $user->user_nicename,
			'url'          => get_author_posts_url( $user->ID ),
			'avatar'       => get_avatar_url( $user->ID ),
			'description'  => $user->description,
		);
	}

	/**
	 * Stored value → template-friendly value.
	 */
	public static function format( array $field, $value ) {
		if ( null === $value || ( '' === $value && 'toggle' !== $field['type'] ) ) {
			$value = self::empty_value( $field );
		}
		switch ( $field['type'] ) {
			case 'textarea':
			case 'text':
			case 'email':
			case 'url':
			case 'password':
			case 'oembed':
			case 'color':
				$out = is_scalar( $value ) ? (string) $value : '';
				break;
			case 'wysiwyg':
				$out = '' === $value ? '' : wpautop( (string) $value );
				break;
			case 'number':
			case 'range':
				$out = is_numeric( $value ) ? 0 + $value : '';
				break;
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				if ( self::is_multiple( $field ) ) {
					$out = array();
					foreach ( (array) $value as $v ) {
						$out[] = self::choice_out( $field, $v );
					}
				} else {
					$out = '' === $value || is_array( $value ) ? '' : self::choice_out( $field, $value );
				}
				break;
			case 'toggle':
				$out = (bool) $value;
				break;
			case 'date':
			case 'datetime':
			case 'time':
				$out = self::format_date( $field, $value, self::o( $field, 'return_format', '' ) );
				break;
			case 'image':
				$data = self::image_data( $value );
				$fmt  = self::o( $field, 'return_format', 'array' );
				$out  = ! $data ? ( 'array' === $fmt ? null : '' ) : ( 'url' === $fmt ? $data['url'] : ( 'id' === $fmt ? $data['id'] : $data ) );
				break;
			case 'file':
				$data = self::file_data( $value );
				$fmt  = self::o( $field, 'return_format', 'array' );
				$out  = ! $data ? ( 'array' === $fmt ? null : '' ) : ( 'url' === $fmt ? $data['url'] : ( 'id' === $fmt ? $data['id'] : $data ) );
				break;
			case 'gallery':
				$out = array_values( array_filter( array_map( array( __CLASS__, 'image_data' ), (array) $value ) ) );
				break;
			case 'link':
				$out = is_array( $value ) && ! empty( $value['url'] ) ? array(
					'url'    => $value['url'],
					'title'  => isset( $value['title'] ) ? $value['title'] : '',
					'target' => isset( $value['target'] ) ? $value['target'] : '',
				) : null;
				break;
			case 'post_object':
			case 'relationship':
				$posts = self::visible_posts( self::id_list( $value ) );
				if ( 'id' === self::o( $field, 'return_format', 'object' ) ) {
					$posts = wp_list_pluck( $posts, 'ID' );
				}
				$out = self::is_multiple( $field ) ? $posts : ( $posts ? $posts[0] : null );
				break;
			case 'taxonomy':
				$terms = array();
				foreach ( self::id_list( $value ) as $id ) {
					$term = get_term( $id );
					if ( $term && ! is_wp_error( $term ) ) {
						$terms[] = 'id' === self::o( $field, 'return_format', 'object' ) ? $term->term_id : $term;
					}
				}
				$out = self::is_multiple( $field ) ? $terms : ( $terms ? $terms[0] : null );
				break;
			case 'user':
				$users = array();
				foreach ( self::id_list( $value ) as $id ) {
					$fmt = self::o( $field, 'return_format', 'array' );
					$u   = 'object' === $fmt ? get_userdata( $id ) : ( 'id' === $fmt ? ( get_userdata( $id ) ? $id : null ) : self::user_data( $id ) );
					if ( $u ) {
						$users[] = $u;
					}
				}
				$out = self::is_multiple( $field ) ? $users : ( $users ? $users[0] : null );
				break;
			case 'map':
				$out = is_array( $value ) && $value ? array(
					'address' => isset( $value['address'] ) ? (string) $value['address'] : '',
					'lat'     => isset( $value['lat'] ) && '' !== $value['lat'] ? (float) $value['lat'] : null,
					'lng'     => isset( $value['lng'] ) && '' !== $value['lng'] ? (float) $value['lng'] : null,
					'zoom'    => isset( $value['zoom'] ) ? (int) $value['zoom'] : (int) self::o( $field, 'zoom', 14 ),
				) : null;
				break;
			case 'repeater':
				$out = array();
				foreach ( is_array( $value ) ? $value : array() as $row ) {
					$out[] = self::format_row( $field['options']['sub_fields'], is_array( $row ) ? $row : array() );
				}
				break;
			case 'group':
				$out = self::format_row( $field['options']['sub_fields'], is_array( $value ) ? $value : array() );
				break;
			default:
				$out = null;
		}

		/**
		 * Formatted value of a field.
		 *
		 * @param mixed $out   Formatted value.
		 * @param mixed $value Stored value.
		 * @param array $field Field definition.
		 */
		return apply_filters( 'brik/content/format_field', $out, $value, $field );
	}

	public static function format_row( array $subs, array $row ) {
		$out = array();
		foreach ( $subs as $sub ) {
			if ( self::has_value( $sub['type'] ) ) {
				$out[ $sub['name'] ] = self::format( $sub, isset( $row[ $sub['name'] ] ) ? $row[ $sub['name'] ] : null );
			}
		}
		return $out;
	}

	public static function format_date( array $field, $value, $format ) {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return '';
		}
		if ( '' === $format ) {
			$format = 'time' === $field['type'] ? get_option( 'time_format' ) : get_option( 'date_format' );
			if ( 'datetime' === $field['type'] ) {
				$format .= ' ' . get_option( 'time_format' );
			}
		}
		$dt = \DateTime::createFromFormat( '!' . self::date_storage_format( $field['type'] ), (string) $value, wp_timezone() );
		if ( ! $dt ) {
			return (string) $value;
		}
		return wp_date( $format, $dt->getTimestamp() );
	}

	/* ---------------------------------------------------------------------
	 * Text (dynamic tags) and URLs.
	 * ------------------------------------------------------------------- */

	/**
	 * Plain text for a stored value, unescaped. Used by {field:…} tags.
	 */
	public static function text( array $field, $value ) {
		$f = self::format( $field, $value );
		switch ( $field['type'] ) {
			case 'number':
			case 'range':
				if ( '' === $f ) {
					return '';
				}
				return self::affix_text( $field, self::number( $f, $field ) );
			case 'text':
			case 'email':
			case 'url':
				return '' === $f ? '' : self::affix_text( $field, $f );
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				return implode( ', ', array_map( array( __CLASS__, 'label_of' ), array_fill( 0, count( (array) $value ), $field ), (array) $value ) );
			case 'toggle':
				return $f ? self::o( $field, 'on_text', __( 'Yes', 'brik-builder' ) ) : self::o( $field, 'off_text', __( 'No', 'brik-builder' ) );
			case 'image':
			case 'file':
			case 'gallery':
				return implode( ', ', self::urls( $field, $value ) );
			case 'link':
				return is_array( $value ) && ! empty( $value['url'] ) ? ( '' !== $value['title'] ? $value['title'] : $value['url'] ) : '';
			case 'post_object':
			case 'relationship':
				return implode( ', ', array_map( 'get_the_title', self::visible_posts( self::id_list( $value ) ) ) );
			case 'taxonomy':
				$names = array();
				foreach ( self::id_list( $value ) as $id ) {
					$term = get_term( $id );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				return implode( ', ', $names );
			case 'user':
				$names = array();
				foreach ( self::id_list( $value ) as $id ) {
					$u = get_userdata( $id );
					if ( $u ) {
						$names[] = $u->display_name;
					}
				}
				return implode( ', ', $names );
			case 'map':
				return $f ? ( '' !== $f['address'] ? $f['address'] : $f['lat'] . ', ' . $f['lng'] ) : '';
			case 'repeater':
			case 'group':
				return '';
			case 'textarea':
				return (string) $value;
		}
		return is_scalar( $f ) ? (string) $f : '';
	}

	/**
	 * Prefix and suffix around a value. Word-like affixes ("USD", "kg") get a space,
	 * symbols ("$", "%") sit right against the value.
	 */
	public static function affix_text( array $field, $text ) {
		$pre = (string) self::o( $field, 'prepend', '' );
		$app = (string) self::o( $field, 'append', '' );
		$out = $text;
		if ( '' !== $pre ) {
			$out = $pre . ( preg_match( '/[\p{L}\d]$/u', $pre ) ? ' ' : '' ) . $out;
		}
		if ( '' !== $app ) {
			$out .= ( preg_match( '/^[\p{L}\d]/u', $app ) ? ' ' : '' ) . $app;
		}
		return $out;
	}

	public static function label_of( array $field, $value ) {
		return self::choice_label( $field, $value );
	}

	/**
	 * URLs a value points at: attachments, links, posts, terms, users.
	 */
	public static function urls( array $field, $value, $size = 'full' ) {
		$out = array();
		switch ( $field['type'] ) {
			case 'image':
			case 'gallery':
				foreach ( self::id_list( $value ) as $id ) {
					$url = wp_get_attachment_image_url( $id, $size );
					if ( $url ) {
						$out[] = $url;
					}
				}
				break;
			case 'file':
				foreach ( self::id_list( $value ) as $id ) {
					$url = wp_get_attachment_url( $id );
					if ( $url ) {
						$out[] = $url;
					}
				}
				break;
			case 'link':
				if ( is_array( $value ) && ! empty( $value['url'] ) ) {
					$out[] = $value['url'];
				}
				break;
			case 'post_object':
			case 'relationship':
				foreach ( self::visible_posts( self::id_list( $value ) ) as $post ) {
					$out[] = get_permalink( $post );
				}
				break;
			case 'taxonomy':
				foreach ( self::id_list( $value ) as $id ) {
					$link = get_term_link( (int) $id );
					if ( ! is_wp_error( $link ) ) {
						$out[] = $link;
					}
				}
				break;
			case 'user':
				foreach ( self::id_list( $value ) as $id ) {
					if ( get_userdata( $id ) ) {
						$out[] = get_author_posts_url( $id );
					}
				}
				break;
			case 'email':
				if ( is_string( $value ) && '' !== $value ) {
					$out[] = 'mailto:' . $value;
				}
				break;
			case 'map':
				$f = self::format( $field, $value );
				if ( $f ) {
					$out[] = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( null !== $f['lat'] ? $f['lat'] . ',' . $f['lng'] : $f['address'] );
				}
				break;
			default:
				if ( is_string( $value ) && '' !== $value ) {
					$out[] = $value;
				}
		}
		return $out;
	}

	/**
	 * Display a number. Plain by default (years and IDs must not read "2,019"); the format
	 * option adds thousands separators or fixed currency decimals.
	 */
	public static function number( $value, array $field ) {
		global $wp_locale;
		$format = self::o( $field, 'format', 'none' );
		$step   = (string) self::o( $field, 'step', '' );
		if ( 'currency' === $format ) {
			$decimals = 2;
		} elseif ( false !== strpos( $step, '.' ) ) {
			$decimals = strlen( substr( $step, strpos( $step, '.' ) + 1 ) );
			// A 0.5 step still shows 3 as "3", not "3.0".
			if ( floor( (float) $value ) == (float) $value ) { // phpcs:ignore Universal.Operators.StrictComparisons
				$decimals = 0;
			}
		} else {
			$decimals = is_float( $value ) && floor( $value ) != $value ? min( 4, strlen( substr( strrchr( rtrim( sprintf( '%.4F', $value ), '0' ), '.' ), 1 ) ) ) : 0; // phpcs:ignore Universal.Operators.StrictComparisons
		}
		if ( 'none' === $format ) {
			$point = $wp_locale && isset( $wp_locale->number_format['decimal_point'] ) ? $wp_locale->number_format['decimal_point'] : '.';
			return number_format( (float) $value, $decimals, $point, '' );
		}
		return number_format_i18n( (float) $value, $decimals );
	}

	/* ---------------------------------------------------------------------
	 * Display markup (brik_field_html).
	 * ------------------------------------------------------------------- */

	private static function gallery_cols( $cols ) {
		$map = array(
			1 => 'grid-cols-1',
			2 => 'grid-cols-2',
			3 => 'grid-cols-2 sm:grid-cols-3',
			4 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
			5 => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
			6 => 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6',
		);
		$cols = max( 1, min( 6, (int) $cols ) );
		return $map[ $cols ];
	}

	/**
	 * Display markup for a stored value.
	 *
	 * @param array $args size (image size), class, columns (gallery), display (list|cards|table
	 *                    for relationships and repeaters), date_format, button (variant), empty
	 *                    (markup when there is no value), lightbox (gallery, default true).
	 */
	public static function html( array $field, $value, array $args = array() ) {
		$args = array_merge(
			array(
				'size'        => 'large',
				'class'       => '',
				'columns'     => 3,
				'display'     => '',
				'date_format' => '',
				'button'      => 'default',
				'empty'       => '',
				'lightbox'    => true,
				'id'          => '',
			),
			$args
		);

		if ( self::is_empty( $field, $value ) && ! in_array( $field['type'], array( 'toggle', 'message' ), true ) ) {
			return (string) $args['empty'];
		}

		$o    = $field['options'];
		$html = '';
		switch ( $field['type'] ) {
			case 'text':
			case 'email':
			case 'url':
			case 'password':
				$text = 'password' === $field['type'] ? str_repeat( '•', 8 ) : (string) $value;
				if ( 'email' === $field['type'] ) {
					$text = '<a class="text-primary underline-offset-4 hover:underline" href="' . esc_url( 'mailto:' . $value ) . '">' . esc_html( antispambot( $value ) ) . '</a>';
				} elseif ( 'url' === $field['type'] ) {
					$text = '<a class="text-primary underline-offset-4 hover:underline break-all" href="' . esc_url( $value ) . '">' . esc_html( preg_replace( '~^https?://~', '', $value ) ) . '</a>';
				} else {
					$text = esc_html( $text );
				}
				$html = '<span class="' . esc_attr( brik_cls( 'brik-field-text', $args['class'] ) ) . '">' . self::affix( $field, 'prepend' ) . $text . self::affix( $field, 'append' ) . '</span>';
				break;

			case 'textarea':
				$mode = self::o( $field, 'new_lines', '' );
				$body = esc_html( (string) $value );
				$body = 'wpautop' === $mode ? wpautop( $body ) : ( 'br' === $mode ? nl2br( $body ) : $body );
				$html = '<div class="' . esc_attr( brik_cls( 'brik-field-textarea leading-relaxed [&>p:not(:last-child)]:mb-4', $args['class'] ) ) . '">' . $body . '</div>';
				break;

			case 'wysiwyg':
				$html = '<div class="' . esc_attr( brik_cls( 'brik-field-wysiwyg brik-prose', $args['class'] ) ) . '">' . wp_kses_post( self::format( $field, $value ) ) . '</div>';
				break;

			case 'number':
			case 'range':
				$html = '<span class="' . esc_attr( brik_cls( 'brik-field-number tabular-nums', $args['class'] ) ) . '">' . self::affix( $field, 'prepend' ) . esc_html( self::number( $value, $field ) ) . self::affix( $field, 'append' ) . '</span>';
				break;

			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$items = array();
				foreach ( (array) $value as $v ) {
					$label = self::choice_label( $field, $v );
					$items[] = 'text' === $args['display'] ? esc_html( $label ) : '<span class="' . esc_attr( brik_badge_class( 'secondary' ) ) . '">' . esc_html( $label ) . '</span>';
				}
				$html = 'text' === $args['display']
					? '<span class="' . esc_attr( brik_cls( 'brik-field-choice', $args['class'] ) ) . '">' . implode( ', ', $items ) . '</span>'
					: '<div class="' . esc_attr( brik_cls( 'brik-field-choice flex flex-wrap gap-1.5', $args['class'] ) ) . '">' . implode( '', $items ) . '</div>';
				break;

			case 'toggle':
				$on    = (bool) $value;
				$label = $on ? self::o( $field, 'on_text', __( 'Yes', 'brik-builder' ) ) : self::o( $field, 'off_text', __( 'No', 'brik-builder' ) );
				$html  = '<span class="' . esc_attr( brik_cls( 'brik-field-toggle inline-flex items-center gap-1.5', $on ? 'text-foreground' : 'text-muted-foreground', $args['class'] ) ) . '">'
					. brik_icon( $on ? 'circle-check' : 'circle-x', $on ? 'size-4 text-primary' : 'size-4' ) . esc_html( $label ) . '</span>';
				break;

			case 'date':
			case 'datetime':
			case 'time':
				$format = '' !== $args['date_format'] ? $args['date_format'] : self::o( $field, 'display_format', '' );
				$text   = self::format_date( $field, $value, $format );
				$dt     = \DateTime::createFromFormat( '!' . self::date_storage_format( $field['type'] ), (string) $value, wp_timezone() );
				$attr   = $dt ? ( 'time' === $field['type'] ? $dt->format( 'H:i' ) : ( 'date' === $field['type'] ? $dt->format( 'Y-m-d' ) : $dt->format( DATE_W3C ) ) ) : '';
				$html   = '<time class="' . esc_attr( brik_cls( 'brik-field-date', $args['class'] ) ) . '" datetime="' . esc_attr( $attr ) . '">' . esc_html( $text ) . '</time>';
				break;

			case 'color':
				$html = '<span class="' . esc_attr( brik_cls( 'brik-field-color inline-flex items-center gap-2 font-mono text-sm', $args['class'] ) ) . '"><span class="inline-block size-5 rounded-md border border-border shadow-xs" style="background:' . esc_attr( self::sanitize_color( $value ) ) . '"></span>' . esc_html( $value ) . '</span>';
				break;

			case 'image':
				$img = wp_get_attachment_image(
					(int) $value,
					$args['size'],
					false,
					array(
						'class'    => brik_cls( 'brik-field-image h-auto max-w-full rounded-lg', $args['class'] ),
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				$html = $img ? $img : '';
				break;

			case 'file':
				$data = self::file_data( $value );
				if ( $data ) {
					$html = '<a class="' . esc_attr( brik_cls( 'brik-field-file group flex max-w-md items-center gap-3 rounded-lg border border-border bg-card p-3 text-card-foreground no-underline shadow-xs transition-colors hover:bg-accent', $args['class'] ) ) . '" href="' . esc_url( $data['url'] ) . '" download>'
						. '<span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">' . brik_icon( 'file-down', 'size-5' ) . '</span>'
						. '<span class="flex min-w-0 flex-col"><span class="truncate text-sm font-medium">' . esc_html( $data['title'] ? $data['title'] : $data['filename'] ) . '</span>'
						. '<span class="text-xs text-muted-foreground">' . esc_html( strtoupper( pathinfo( $data['filename'], PATHINFO_EXTENSION ) ) . ( $data['filesize'] ? ' · ' . size_format( $data['filesize'] ) : '' ) ) . '</span></span></a>';
				}
				break;

			case 'gallery':
				$group = 'brik-gallery-' . ( $args['id'] ? sanitize_key( $args['id'] ) : $field['key'] );
				$items = '';
				foreach ( (array) $value as $id ) {
					$img = wp_get_attachment_image(
						(int) $id,
						'large' === $args['size'] ? 'medium_large' : $args['size'],
						false,
						array(
							'class'    => 'size-full object-cover transition-transform duration-300 group-hover:scale-105',
							'loading'  => 'lazy',
							'decoding' => 'async',
						)
					);
					if ( ! $img ) {
						continue;
					}
					$full   = wp_get_attachment_image_url( (int) $id, 'full' );
					$inner  = '<span class="block aspect-square overflow-hidden rounded-lg bg-muted">' . $img . '</span>';
					$items .= $args['lightbox'] && $full
						? '<a class="group block" href="' . esc_url( $full ) . '" data-brik-lightbox="' . esc_attr( $group ) . '" data-caption="' . esc_attr( wp_get_attachment_caption( (int) $id ) ) . '">' . $inner . '</a>'
						: '<figure class="group m-0">' . $inner . '</figure>';
				}
				$html = '<div class="' . esc_attr( brik_cls( 'brik-field-gallery grid gap-3', self::gallery_cols( $args['columns'] ), $args['class'] ) ) . '">' . $items . '</div>';
				break;

			case 'oembed':
				$embed = function_exists( 'brik_oembed_html' ) ? brik_oembed_html( $value ) : wp_oembed_get( $value );
				$html  = $embed
					? '<div class="' . esc_attr( brik_cls( 'brik-field-oembed overflow-hidden rounded-lg [&_iframe]:aspect-video [&_iframe]:h-auto [&_iframe]:w-full', $args['class'] ) ) . '">' . $embed . '</div>'
					: '<a class="text-primary underline-offset-4 hover:underline" href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>';
				break;

			case 'link':
				$attrs = array(
					'class' => brik_button_class( isset( brik_button_variants_labels()[ $args['button'] ] ) ? $args['button'] : 'default', 'default', brik_cls( 'brik-field-link', $args['class'] ) ),
					'href'  => $value['url'],
				);
				if ( '_blank' === $value['target'] ) {
					$attrs['target'] = '_blank';
					$attrs['rel']    = 'noopener';
				}
				$text = '' !== $value['title'] ? $value['title'] : preg_replace( '~^https?://~', '', $value['url'] );
				$html = '<a' . brik_attrs( $attrs ) . '>' . esc_html( $text ) . ( '_blank' === $value['target'] ? brik_icon( 'arrow-up-right', 'size-4' ) : '' ) . '</a>';
				break;

			case 'post_object':
			case 'relationship':
				$posts = self::visible_posts( self::id_list( $value ) );
				$html  = 'cards' === $args['display'] ? self::post_cards( $posts, $args ) : self::post_list( $posts, $args );
				break;

			case 'taxonomy':
				$items = array();
				foreach ( self::id_list( $value ) as $id ) {
					$term = get_term( $id );
					if ( ! $term || is_wp_error( $term ) ) {
						continue;
					}
					$link    = get_term_link( $term );
					$items[] = is_wp_error( $link )
						? '<span class="' . esc_attr( brik_badge_class( 'secondary' ) ) . '">' . esc_html( $term->name ) . '</span>'
						: '<a class="' . esc_attr( brik_badge_class( 'secondary', 'default', 'no-underline hover:bg-secondary/80' ) ) . '" href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>';
				}
				$html = '<div class="' . esc_attr( brik_cls( 'brik-field-terms flex flex-wrap gap-1.5', $args['class'] ) ) . '">' . implode( '', $items ) . '</div>';
				break;

			case 'user':
				$items = '';
				foreach ( self::id_list( $value ) as $id ) {
					$u = get_userdata( $id );
					if ( ! $u ) {
						continue;
					}
					$items .= '<li class="flex items-center gap-3"><img class="size-9 rounded-full bg-muted" src="' . esc_url( get_avatar_url( $u->ID, array( 'size' => 72 ) ) ) . '" alt="" loading="lazy" width="36" height="36">'
						. '<a class="text-sm font-medium text-foreground no-underline hover:underline" href="' . esc_url( get_author_posts_url( $u->ID ) ) . '">' . esc_html( $u->display_name ) . '</a></li>';
				}
				$html = '<ul class="' . esc_attr( brik_cls( 'brik-field-users m-0 flex list-none flex-col gap-3 p-0', $args['class'] ) ) . '">' . $items . '</ul>';
				break;

			case 'map':
				$f     = self::format( $field, $value );
				$query = null !== $f['lat'] ? $f['lat'] . ',' . $f['lng'] : $f['address'];
				$h     = max( 120, (int) self::o( $field, 'height', 320 ) );
				$html  = '<div class="' . esc_attr( brik_cls( 'brik-field-map overflow-hidden rounded-lg border border-border', $args['class'] ) ) . '">'
					. '<iframe class="block w-full border-0" style="height:' . (int) $h . 'px" src="' . esc_url( brik_map_embed_url( $query, $f['zoom'] ) ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="' . esc_attr( '' !== $f['address'] ? $f['address'] : $field['label'] ) . '"></iframe>'
					. ( '' !== $f['address'] ? '<p class="m-0 flex items-center gap-2 border-t border-border px-4 py-2.5 text-sm text-muted-foreground">' . brik_icon( 'map-pin', 'size-4 shrink-0' ) . esc_html( $f['address'] ) . '</p>' : '' )
					. '</div>';
				break;

			case 'repeater':
				$html = self::repeater_html( $field, (array) $value, $args );
				break;

			case 'group':
				$html = self::rows_html( $field['options']['sub_fields'], array( (array) $value ), 'list', $args );
				break;

			case 'message':
				$html = '<div class="' . esc_attr( brik_cls( 'brik-field-message text-sm text-muted-foreground', $args['class'] ) ) . '">' . wpautop( wp_kses_post( self::o( $field, 'message', '' ) ) ) . '</div>';
				break;
		}

		/**
		 * Display markup of a field.
		 *
		 * @param string $html  Markup.
		 * @param array  $field Field definition.
		 * @param mixed  $value Stored value.
		 * @param array  $args  Display arguments.
		 */
		return apply_filters( 'brik/content/field_html', $html, $field, $value, $args );
	}

	private static function affix( array $field, $which ) {
		$text = (string) self::o( $field, $which, '' );
		if ( '' === $text ) {
			return '';
		}
		$space = 'prepend' === $which ? preg_match( '/[\p{L}\d]$/u', $text ) : preg_match( '/^[\p{L}\d]/u', $text );
		$html  = '<span class="brik-field-' . $which . ' text-muted-foreground">' . esc_html( $text ) . '</span>';
		return $space ? ( 'prepend' === $which ? $html . ' ' : ' ' . $html ) : $html;
	}

	private static function post_list( array $posts, array $args ) {
		$items = '';
		foreach ( $posts as $post ) {
			$thumb  = get_the_post_thumbnail( $post, 'thumbnail', array( 'class' => 'size-10 shrink-0 rounded-md object-cover' ) );
			$items .= '<li><a class="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm font-medium text-foreground no-underline transition-colors hover:bg-accent" href="' . esc_url( get_permalink( $post ) ) . '">'
				. ( $thumb ? $thumb : '<span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">' . brik_icon( 'file-text', 'size-4' ) . '</span>' )
				. '<span class="min-w-0 truncate">' . esc_html( get_the_title( $post ) ) . '</span>' . brik_icon( 'chevron-right', 'ms-auto size-4 text-muted-foreground' ) . '</a></li>';
		}
		return '<ul class="' . esc_attr( brik_cls( 'brik-field-posts m-0 flex list-none flex-col gap-1 p-0', $args['class'] ) ) . '">' . $items . '</ul>';
	}

	private static function post_cards( array $posts, array $args ) {
		$items = '';
		foreach ( $posts as $post ) {
			$thumb  = get_the_post_thumbnail( $post, 'medium_large', array( 'class' => 'aspect-video w-full object-cover' ) );
			$items .= '<a class="group flex flex-col overflow-hidden rounded-xl border border-border bg-card text-card-foreground no-underline shadow-xs transition-shadow hover:shadow-md" href="' . esc_url( get_permalink( $post ) ) . '">'
				. ( $thumb ? $thumb : '<span class="flex aspect-video items-center justify-center bg-muted text-muted-foreground">' . brik_icon( 'image', 'size-6' ) . '</span>' )
				. '<span class="flex flex-col gap-1.5 p-4"><span class="font-semibold leading-snug group-hover:underline">' . esc_html( get_the_title( $post ) ) . '</span>'
				. '<span class="line-clamp-2 text-sm text-muted-foreground">' . esc_html( wp_trim_words( get_the_excerpt( $post ), 20 ) ) . '</span></span></a>';
		}
		return '<div class="' . esc_attr( brik_cls( 'brik-field-posts grid gap-4', self::gallery_cols( $args['columns'] ), $args['class'] ) ) . '">' . $items . '</div>';
	}

	private static function repeater_html( array $field, array $rows, array $args ) {
		$display = '' !== $args['display'] ? $args['display'] : ( 'table' === self::o( $field, 'layout', 'block' ) ? 'table' : 'list' );
		return self::rows_html( $field['options']['sub_fields'], $rows, $display, $args );
	}

	private static function rows_html( array $subs, array $rows, $display, array $args ) {
		$subs = array_values(
			array_filter(
				$subs,
				static function ( $s ) {
					return Fields::has_value( $s['type'] );
				}
			)
		);
		$inner = array_merge( $args, array( 'class' => '', 'display' => '', 'empty' => '' ) );

		if ( 'table' === $display ) {
			$head = '';
			foreach ( $subs as $sub ) {
				$head .= '<th class="h-10 px-3 text-start align-middle font-medium text-muted-foreground">' . esc_html( $sub['label'] ) . '</th>';
			}
			$body = '';
			foreach ( $rows as $row ) {
				$body .= '<tr class="border-b border-border transition-colors last:border-0 hover:bg-muted/50">';
				foreach ( $subs as $sub ) {
					$body .= '<td class="p-3 align-middle">' . self::html( $sub, isset( $row[ $sub['name'] ] ) ? $row[ $sub['name'] ] : null, $inner ) . '</td>';
				}
				$body .= '</tr>';
			}
			return '<div class="' . esc_attr( brik_cls( 'brik-field-table relative w-full overflow-x-auto rounded-lg border border-border', $args['class'] ) ) . '"><table class="w-full caption-bottom text-sm"><thead class="border-b border-border bg-muted/50"><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
		}

		$items = '';
		foreach ( $rows as $row ) {
			$cells = '';
			foreach ( $subs as $sub ) {
				$v    = isset( $row[ $sub['name'] ] ) ? $row[ $sub['name'] ] : null;
				$cell = self::html( $sub, $v, $inner );
				if ( '' === $cell ) {
					continue;
				}
				$cells .= '<div class="flex flex-col gap-1"><dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">' . esc_html( $sub['label'] ) . '</dt><dd class="m-0">' . $cell . '</dd></div>';
			}
			$items .= '<li class="rounded-lg border border-border bg-card p-4 text-card-foreground shadow-xs"><dl class="m-0 grid gap-3">' . $cells . '</dl></li>';
		}
		return '<ul class="' . esc_attr( brik_cls( 'brik-field-rows m-0 grid list-none gap-3 p-0', $args['class'] ) ) . '">' . $items . '</ul>';
	}
}
