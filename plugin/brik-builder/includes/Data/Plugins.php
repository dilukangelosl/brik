<?php
/**
 * Field plugins: ACF, Meta Box and Pods. Values and field lists, when the plugin is active.
 *
 * @package Brik
 */

namespace Brik\Data;

defined( 'ABSPATH' ) || exit;

final class Plugins {

	/* ---------------------------------------------------------------------
	 * ACF.
	 * ------------------------------------------------------------------- */

	public static function has_acf() {
		return function_exists( 'get_field_object' ) && function_exists( 'acf_get_field_groups' );
	}

	/**
	 * {acf:name}, {acf:group.sub}, {acf:repeater.sub} (first row), {acf:repeater.2.sub}.
	 */
	public static function acf( $name, $post_id, $mod = '' ) {
		if ( ! self::has_acf() ) {
			return Tags::text( '' );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) ) {
			return Tags::text( '' );
		}
		$parts = explode( '.', $name );
		$top   = array_shift( $parts );
		$field = get_field_object( $top, $post->ID, false, true );
		if ( ! $field || ! is_array( $field ) ) {
			return Tags::text( '' );
		}
		$value = $field['value'];

		// Walk into repeaters (first row unless an index is given) and groups.
		while ( $parts && in_array( $field['type'], array( 'repeater', 'group', 'flexible_content' ), true ) ) {
			if ( 'group' !== $field['type'] ) {
				$index = ctype_digit( $parts[0] ) ? (int) array_shift( $parts ) : 0;
				$rows  = is_array( $value ) ? array_values( $value ) : array();
				$value = isset( $rows[ $index ] ) ? $rows[ $index ] : array();
			}
			if ( ! $parts ) {
				break;
			}
			$sub_name = array_shift( $parts );
			$sub      = null;
			$subs     = isset( $field['sub_fields'] ) ? (array) $field['sub_fields'] : array();
			if ( 'flexible_content' === $field['type'] && ! empty( $field['layouts'] ) ) {
				foreach ( $field['layouts'] as $layout ) {
					$subs = array_merge( $subs, isset( $layout['sub_fields'] ) ? (array) $layout['sub_fields'] : array() );
				}
			}
			foreach ( $subs as $candidate ) {
				if ( $candidate['name'] === $sub_name ) {
					$sub = $candidate;
					break;
				}
			}
			if ( ! $sub ) {
				return Tags::text( '' );
			}
			// Unformatted rows are keyed by field key.
			$value = is_array( $value ) ? ( isset( $value[ $sub['key'] ] ) ? $value[ $sub['key'] ] : ( isset( $value[ $sub_name ] ) ? $value[ $sub_name ] : null ) ) : null;
			$field = $sub;
		}

		return self::acf_value( $field, $value, $post->ID, $mod );
	}

	/**
	 * Typed value from an ACF field and its stored (unformatted) value.
	 */
	private static function acf_value( array $field, $value, $post_id, $mod ) {
		switch ( $field['type'] ) {
			case 'image':
				return Tags::image( is_array( $value ) ? (int) $value['ID'] : (int) $value );
			case 'file':
				$id = is_array( $value ) ? (int) $value['ID'] : (int) $value;
				return array(
					'kind'  => 'url',
					'v'     => $id ? (string) wp_get_attachment_url( $id ) : '',
					'label' => $id ? get_the_title( $id ) : '',
				);
			case 'gallery':
				$ids = array_map( 'intval', (array) $value );
				return 'url' === $mod || 'id' === $mod || 'alt' === $mod ? Tags::image( $ids ? $ids[0] : 0 ) : Tags::text( array_map( 'strval', $ids ), 'list' );
			case 'link':
				if ( is_array( $value ) ) {
					return array(
						'kind'  => 'url',
						'v'     => isset( $value['url'] ) ? (string) $value['url'] : '',
						'label' => isset( $value['title'] ) ? (string) $value['title'] : '',
					);
				}
				return Tags::text( (string) $value, 'url' );
			case 'url':
			case 'page_link':
				return Tags::text( is_array( $value ) ? (string) reset( $value ) : ( is_numeric( $value ) ? (string) get_permalink( (int) $value ) : (string) $value ), 'url' );
			case 'oembed':
				return Tags::text( (string) $value, 'url' );
			case 'post_object':
			case 'relationship':
				$posts = array();
				foreach ( (array) $value as $id ) {
					$p = get_post( is_object( $id ) ? $id->ID : (int) $id );
					if ( $p ) {
						$posts[] = $p;
					}
				}
				return Tags::text( $posts, 'posts' );
			case 'taxonomy':
				$terms = array();
				foreach ( (array) $value as $id ) {
					$t = get_term( is_object( $id ) ? $id->term_id : (int) $id );
					if ( $t && ! is_wp_error( $t ) ) {
						$terms[] = $t;
					}
				}
				return Tags::text( $terms, 'terms' );
			case 'user':
				$names = array();
				foreach ( (array) $value as $id ) {
					$u = get_userdata( is_array( $id ) ? (int) $id['ID'] : (int) $id );
					if ( $u ) {
						$names[] = $u->display_name;
					}
				}
				return Tags::text( $names, 'list' );
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$values = array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) );
				if ( 'raw' !== $mod && 'value' !== $mod ) {
					$choices = isset( $field['choices'] ) ? (array) $field['choices'] : array();
					$values  = array_map(
						static function ( $v ) use ( $choices ) {
							return isset( $choices[ $v ] ) ? (string) $choices[ $v ] : $v;
						},
						$values
					);
				}
				return count( $values ) > 1 || 'checkbox' === $field['type'] ? Tags::text( $values, 'list' ) : Tags::text( $values ? $values[0] : '' );
			case 'true_false':
				return Tags::text( (bool) $value, 'bool' );
			case 'number':
			case 'range':
				return '' === (string) $value ? Tags::text( '' ) : Tags::text( 0 + $value, 'number' );
			case 'date_picker':
			case 'date_time_picker':
				$ts = Tags::timestamp( (string) $value );
				if ( ! $ts || in_array( $mod, array( 'date', 'time', 'datetime', 'iso', 'relative', 'timestamp', 'year' ), true ) ) {
					return Tags::text( $ts, 'date' );
				}
				$format = ! empty( $field['display_format'] ) ? $field['display_format'] : get_option( 'date_format' );
				return Tags::text( wp_date( $format, $ts ) );
			case 'wysiwyg':
				return Tags::text( wpautop( (string) $value ), 'html' );
			case 'textarea':
				return Tags::text( (string) $value );
			case 'google_map':
				return Tags::text( is_array( $value ) && isset( $value['address'] ) ? (string) $value['address'] : '' );
			case 'repeater':
			case 'flexible_content':
				return Tags::text( is_array( $value ) ? count( $value ) : 0, 'number' );
			case 'group':
				return Tags::text( '' );
		}
		return Tags::guess( is_array( $value ) || is_object( $value ) ? $value : (string) $value );
	}

	/**
	 * ACF fields for a post type: name => [label, type, choices, multiple, sub fields].
	 */
	public static function acf_fields( $post_type ) {
		if ( ! self::has_acf() ) {
			return array();
		}
		$out = array();
		foreach ( acf_get_field_groups( array( 'post_type' => $post_type ) ) as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $f ) {
				if ( empty( $f['name'] ) || in_array( $f['type'], array( 'tab', 'message', 'accordion' ), true ) ) {
					continue;
				}
				$f['group_title'] = $group['title'];
				$out[ $f['name'] ] = $f;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Meta Box.
	 * ------------------------------------------------------------------- */

	public static function has_metabox() {
		return function_exists( 'rwmb_get_value' ) && function_exists( 'rwmb_get_registry' );
	}

	public static function metabox( $name, $post_id ) {
		if ( ! self::has_metabox() ) {
			return Tags::text( '' );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) ) {
			return Tags::text( '' );
		}
		$field = function_exists( 'rwmb_get_field_settings' ) ? rwmb_get_field_settings( $name, array(), $post->ID ) : array();
		$value = rwmb_get_value( $name, array(), $post->ID );
		$type  = is_array( $field ) && isset( $field['type'] ) ? $field['type'] : '';

		if ( in_array( $type, array( 'image', 'image_advanced', 'image_upload', 'single_image', 'plupload_image', 'file_advanced', 'file_upload', 'file', 'video' ), true ) ) {
			$first = is_array( $value ) && isset( $value['ID'] ) ? $value : ( is_array( $value ) ? reset( $value ) : null );
			if ( is_array( $first ) ) {
				$url = isset( $first['full_url'] ) ? $first['full_url'] : ( isset( $first['url'] ) ? $first['url'] : '' );
				return array(
					'kind' => 0 === strpos( $type, 'file' ) ? 'url' : 'image',
					'v'    => (string) $url,
					'id'   => isset( $first['ID'] ) ? (int) $first['ID'] : 0,
					'alt'  => isset( $first['alt'] ) ? (string) $first['alt'] : '',
				);
			}
			return Tags::text( '', 'image' );
		}
		if ( in_array( $type, array( 'select', 'select_advanced', 'radio', 'checkbox_list', 'button_group', 'image_select' ), true ) && ! empty( $field['options'] ) ) {
			$labels = array();
			foreach ( (array) $value as $v ) {
				if ( is_scalar( $v ) ) {
					$labels[] = isset( $field['options'][ $v ] ) ? (string) $field['options'][ $v ] : (string) $v;
				}
			}
			return count( $labels ) > 1 ? Tags::text( $labels, 'list' ) : Tags::text( $labels ? $labels[0] : '' );
		}
		if ( in_array( $type, array( 'post' ), true ) ) {
			return Tags::text( array_filter( array_map( 'get_post', array_map( 'intval', (array) $value ) ) ), 'posts' );
		}
		if ( 'checkbox' === $type || 'switch' === $type ) {
			return Tags::text( (bool) $value, 'bool' );
		}
		if ( 'wysiwyg' === $type ) {
			return Tags::text( wpautop( (string) $value ), 'html' );
		}
		return Tags::guess( $value );
	}

	public static function metabox_fields( $post_type ) {
		if ( ! self::has_metabox() ) {
			return array();
		}
		$out = array();
		try {
			foreach ( rwmb_get_registry( 'meta_box' )->all() as $box ) {
				$mb    = is_object( $box ) && isset( $box->meta_box ) ? $box->meta_box : array();
				$types = isset( $mb['post_types'] ) ? (array) $mb['post_types'] : array();
				if ( ! in_array( $post_type, $types, true ) ) {
					continue;
				}
				foreach ( isset( $mb['fields'] ) ? (array) $mb['fields'] : array() as $f ) {
					if ( empty( $f['id'] ) || in_array( isset( $f['type'] ) ? $f['type'] : '', array( 'heading', 'divider', 'custom_html', 'button', 'tab' ), true ) ) {
						continue;
					}
					$out[ $f['id'] ] = array(
						'name'        => $f['id'],
						'label'       => ! empty( $f['name'] ) ? $f['name'] : $f['id'],
						'type'        => isset( $f['type'] ) ? $f['type'] : 'text',
						'choices'     => isset( $f['options'] ) ? (array) $f['options'] : array(),
						'multiple'    => ! empty( $f['multiple'] ) || ( isset( $f['type'] ) && 'checkbox_list' === $f['type'] ),
						'group_title' => isset( $mb['title'] ) ? $mb['title'] : '',
					);
				}
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Pods.
	 * ------------------------------------------------------------------- */

	public static function has_pods() {
		return function_exists( 'pods' ) && function_exists( 'pods_api' );
	}

	public static function pods( $name, $post_id, $mod = '' ) {
		if ( ! self::has_pods() ) {
			return Tags::text( '' );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) ) {
			return Tags::text( '' );
		}
		try {
			$pod = pods( $post->post_type, $post->ID );
			if ( ! $pod || ( method_exists( $pod, 'exists' ) && ! $pod->exists() ) ) {
				return Tags::text( '' );
			}
			if ( 'raw' === $mod || 'url' === $mod || 'id' === $mod ) {
				return Tags::guess( $pod->field( $name ) );
			}
			return Tags::text( (string) $pod->display( $name ), 'html' );
		} catch ( \Throwable $e ) {
			return Tags::text( '' );
		}
	}

	public static function pods_fields( $post_type ) {
		if ( ! self::has_pods() ) {
			return array();
		}
		$out = array();
		try {
			$pod = pods_api()->load_pod( array( 'name' => $post_type ), false );
			if ( ! $pod ) {
				return array();
			}
			$fields = is_object( $pod ) && method_exists( $pod, 'get_fields' ) ? $pod->get_fields() : ( isset( $pod['fields'] ) ? $pod['fields'] : array() );
			foreach ( (array) $fields as $key => $f ) {
				$fname = is_object( $f ) && method_exists( $f, 'get_name' ) ? $f->get_name() : ( isset( $f['name'] ) ? $f['name'] : $key );
				$label = is_object( $f ) && method_exists( $f, 'get_label' ) ? $f->get_label() : ( isset( $f['label'] ) ? $f['label'] : $fname );
				$type  = is_object( $f ) && method_exists( $f, 'get_type' ) ? $f->get_type() : ( isset( $f['type'] ) ? $f['type'] : 'text' );
				$out[ $fname ] = array(
					'name'  => $fname,
					'label' => $label,
					'type'  => $type,
				);
			}
		} catch ( \Throwable $e ) {
			return array();
		}
		return $out;
	}
}
