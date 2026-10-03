<?php
namespace Brik\Content;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Reading and writing field values on posts, terms, users and the site options page.
 *
 * Targets are given the way brik_field() takes them: a post ID, "term_{id}", "user_{id}",
 * "option" (or "options"), a WP_Post / WP_Term / WP_User, or null for the current object.
 */
final class Values {

	const OPTION_PREFIX = 'brik_opt_';

	/**
	 * Resolve a target to [ type => post|term|user|option, id => int, sub => post type|taxonomy ].
	 */
	public static function target( $target = null ) {
		if ( is_array( $target ) && isset( $target['type'] ) ) {
			return $target;
		}
		if ( null === $target || false === $target || 0 === $target || '' === $target ) {
			$id = get_the_ID();
			if ( ! $id && ! is_admin() ) {
				$object = get_queried_object();
				if ( $object instanceof \WP_Term ) {
					return self::target( $object );
				}
				if ( $object instanceof \WP_User ) {
					return self::target( $object );
				}
			}
			$target = (int) $id;
		}
		if ( $target instanceof \WP_Post ) {
			$target = $target->ID;
		} elseif ( $target instanceof \WP_Term ) {
			return array(
				'type' => 'term',
				'id'   => (int) $target->term_id,
				'sub'  => $target->taxonomy,
			);
		} elseif ( $target instanceof \WP_User ) {
			return array(
				'type' => 'user',
				'id'   => (int) $target->ID,
				'sub'  => '',
			);
		}
		if ( is_string( $target ) && ! is_numeric( $target ) ) {
			if ( in_array( $target, array( 'option', 'options' ), true ) ) {
				return array(
					'type' => 'option',
					'id'   => 0,
					'sub'  => '',
				);
			}
			if ( preg_match( '/^term_(\d+)$/', $target, $m ) ) {
				$term = get_term( (int) $m[1] );
				return array(
					'type' => 'term',
					'id'   => (int) $m[1],
					'sub'  => $term && ! is_wp_error( $term ) ? $term->taxonomy : '',
				);
			}
			if ( preg_match( '/^user_(\d+)$/', $target, $m ) ) {
				return array(
					'type' => 'user',
					'id'   => (int) $m[1],
					'sub'  => '',
				);
			}
			return null;
		}
		$id = (int) $target;
		if ( $id <= 0 ) {
			return null;
		}
		return array(
			'type' => 'post',
			'id'   => $id,
			'sub'  => (string) get_post_type( $id ),
		);
	}

	/**
	 * Groups that can hold values for a target.
	 */
	public static function groups( array $t ) {
		return Registry::groups_for( $t['type'], $t['sub'] );
	}

	/**
	 * Field definition for a name, key or dotted path on a target. Falls back to any group
	 * when the target's own groups don't define it.
	 */
	public static function field( $name, $target = null ) {
		$t = self::target( $target );
		if ( 0 === strpos( (string) $name, 'field_' ) ) {
			$hit = Registry::find_by_key( (string) $name );
			if ( $hit ) {
				return $hit;
			}
		}
		if ( $t ) {
			$hit = Registry::find_in_groups( $name, self::groups( $t ) );
			if ( $hit ) {
				return $hit;
			}
		}
		return null;
	}

	/**
	 * The top-level field and remaining path parts for a name like "team.0.name".
	 */
	private static function split( $name, array $t ) {
		$name  = (string) $name;
		$top   = null;
		$parts = array();
		if ( 0 === strpos( $name, 'field_' ) ) {
			$top = Registry::find_by_key( $name );
			if ( $top && ! self::is_top_level( $top['key'], $t ) ) {
				// A sub field key can't be read without knowing the row.
				$top = null;
			}
		} else {
			$parts = explode( '.', $name );
			$first = array_shift( $parts );
			$top   = Registry::find_in_groups( $first, self::groups( $t ) );
		}
		return array( $top, $parts );
	}

	private static function is_top_level( $key, array $t ) {
		foreach ( self::groups( $t ) as $group ) {
			foreach ( $group['fields'] as $f ) {
				if ( $f['key'] === $key ) {
					return true;
				}
			}
		}
		return false;
	}

	public static function option_name( $name ) {
		return self::OPTION_PREFIX . $name;
	}

	private static function exists( array $t, $name ) {
		switch ( $t['type'] ) {
			case 'post':
				return metadata_exists( 'post', $t['id'], $name );
			case 'term':
				return metadata_exists( 'term', $t['id'], $name );
			case 'user':
				return metadata_exists( 'user', $t['id'], $name );
			case 'option':
				return false !== get_option( self::option_name( $name ), false );
		}
		return false;
	}

	private static function read( array $t, $name ) {
		switch ( $t['type'] ) {
			case 'post':
				return get_post_meta( $t['id'], $name, true );
			case 'term':
				return get_term_meta( $t['id'], $name, true );
			case 'user':
				return get_user_meta( $t['id'], $name, true );
			case 'option':
				return get_option( self::option_name( $name ), '' );
		}
		return null;
	}

	/**
	 * Stored value of a top-level field: what was saved, or its default when nothing was.
	 */
	public static function stored( array $field, array $t ) {
		if ( 'taxonomy' === $field['type'] && 'post' === $t['type'] && ! empty( $field['options']['load_terms'] ) ) {
			$ids = wp_get_object_terms( $t['id'], $field['options']['taxonomy'], array( 'fields' => 'ids' ) );
			$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
			return Fields::is_multiple( $field ) ? $ids : ( $ids ? $ids[0] : '' );
		}
		if ( ! self::exists( $t, $field['name'] ) ) {
			return '' !== $field['default'] && null !== $field['default'] ? $field['default'] : Fields::empty_value( $field );
		}
		return self::from_storage( $field, self::read( $t, $field['name'] ) );
	}

	/**
	 * Meta stores booleans as "1"/"0" and everything else as given.
	 */
	public static function to_storage( array $field, $value ) {
		if ( 'toggle' === $field['type'] ) {
			return $value ? 1 : 0;
		}
		if ( 'repeater' === $field['type'] && is_array( $value ) ) {
			foreach ( $value as $i => $row ) {
				$value[ $i ] = self::row_to_storage( $field['options']['sub_fields'], (array) $row );
			}
		} elseif ( 'group' === $field['type'] && is_array( $value ) && $value ) {
			$value = self::row_to_storage( $field['options']['sub_fields'], $value );
		}
		return $value;
	}

	private static function row_to_storage( array $subs, array $row ) {
		foreach ( $subs as $sub ) {
			if ( isset( $row[ $sub['name'] ] ) ) {
				$row[ $sub['name'] ] = self::to_storage( $sub, $row[ $sub['name'] ] );
			}
		}
		return $row;
	}

	public static function from_storage( array $field, $value ) {
		if ( 'toggle' === $field['type'] ) {
			return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
		}
		if ( in_array( $field['type'], array( 'number', 'range' ), true ) ) {
			return is_numeric( $value ) ? 0 + $value : '';
		}
		if ( in_array( $field['type'], array( 'image', 'file' ), true ) || ( ! Fields::is_multiple( $field ) && in_array( $field['type'], array( 'post_object', 'user', 'taxonomy' ), true ) ) ) {
			return is_numeric( $value ) && (int) $value > 0 ? (int) $value : '';
		}
		if ( in_array( $field['type'], array( 'gallery', 'relationship' ), true ) || ( Fields::is_multiple( $field ) && in_array( $field['type'], array( 'post_object', 'user', 'taxonomy' ), true ) ) ) {
			return is_array( $value ) ? array_values( array_map( 'intval', $value ) ) : array();
		}
		if ( 'repeater' === $field['type'] ) {
			$rows = array();
			foreach ( is_array( $value ) ? $value : array() as $row ) {
				$rows[] = self::row_from_storage( $field['options']['sub_fields'], is_array( $row ) ? $row : array() );
			}
			return $rows;
		}
		if ( 'group' === $field['type'] ) {
			return self::row_from_storage( $field['options']['sub_fields'], is_array( $value ) ? $value : array() );
		}
		if ( Fields::is_multiple( $field ) || in_array( $field['type'], array( 'link', 'map' ), true ) ) {
			return is_array( $value ) ? $value : array();
		}
		return $value;
	}

	private static function row_from_storage( array $subs, array $row ) {
		$out = array();
		foreach ( $subs as $sub ) {
			if ( Fields::has_value( $sub['type'] ) ) {
				$out[ $sub['name'] ] = array_key_exists( $sub['name'], $row ) ? self::from_storage( $sub, $row[ $sub['name'] ] ) : ( '' !== $sub['default'] ? $sub['default'] : Fields::empty_value( $sub ) );
			}
		}
		return $out;
	}

	/**
	 * Follow a path ("0.name", "address.city") into a stored value. A non-numeric part on a
	 * repeater collects that sub field from every row.
	 */
	private static function walk( array $field, $value, array $parts ) {
		foreach ( $parts as $i => $part ) {
			if ( 'repeater' === $field['type'] ) {
				$rows = is_array( $value ) ? $value : array();
				if ( is_numeric( $part ) ) {
					$value = isset( $rows[ (int) $part ] ) ? $rows[ (int) $part ] : array();
					$field = array_merge( $field, array( 'type' => 'group' ) );
					continue;
				}
				$sub = Registry::descend( $field, array( $part ) );
				if ( ! $sub ) {
					return array( null, null );
				}
				$rest = array_slice( $parts, $i + 1 );
				$col  = array();
				foreach ( $rows as $row ) {
					list( , $v ) = self::walk( $sub, isset( $row[ $part ] ) ? $row[ $part ] : null, $rest );
					$col[]       = $v;
				}
				$leaf = $rest ? Registry::descend( $sub, $rest ) : $sub;
				if ( ! $leaf ) {
					return array( null, null );
				}
				return array( array_merge( $leaf, array( 'type' => 'column', 'leaf' => $leaf ) ), $col );
			}
			$sub = Registry::descend( $field, array( $part ) );
			if ( ! $sub ) {
				return array( null, null );
			}
			$value = is_array( $value ) && isset( $value[ $part ] ) ? $value[ $part ] : null;
			$field = $sub;
		}
		return array( $field, $value );
	}

	/**
	 * Resolve a name on a target to [ field, stored value ] (both null when unknown).
	 */
	public static function resolve( $name, $target = null ) {
		$t = self::target( $target );
		if ( ! $t ) {
			return array( null, null, null );
		}
		list( $top, $parts ) = self::split( $name, $t );
		if ( ! $top ) {
			return array( null, null, $t );
		}
		$value = self::stored( $top, $t );
		if ( ! $parts ) {
			return array( $top, $value, $t );
		}
		list( $field, $value ) = self::walk( $top, $value, $parts );
		return array( $field, $value, $t );
	}

	public static function get( $name, $target = null, $format = true ) {
		list( $field, $value, $t ) = self::resolve( $name, $target );
		if ( ! $field ) {
			// Unknown to Brik: behave like plain meta so templates keep working.
			if ( $t && 0 !== strpos( (string) $name, 'field_' ) && false === strpos( (string) $name, '.' ) ) {
				return self::read( $t, (string) $name );
			}
			return null;
		}
		if ( 'column' === $field['type'] ) {
			if ( ! $format ) {
				return $value;
			}
			$out = array();
			foreach ( (array) $value as $v ) {
				$out[] = Fields::format( $field['leaf'], $v );
			}
			return $out;
		}
		return $format ? Fields::format( $field, $value ) : $value;
	}

	/**
	 * Sanitize and save. Dotted names update one part of a repeater row or group.
	 *
	 * @return true|WP_Error
	 */
	public static function update( $name, $value, $target = null ) {
		$t = self::target( $target );
		if ( ! $t ) {
			return new WP_Error( 'brik_field_target', __( 'Unknown object to save the field on.', 'brik-builder' ) );
		}
		list( $top, $parts ) = self::split( $name, $t );
		if ( ! $top ) {
			/* translators: %s: field name */
			return new WP_Error( 'brik_field_unknown', sprintf( __( 'No field "%s" for this content.', 'brik-builder' ), $name ) );
		}
		if ( $parts ) {
			$whole = self::stored( $top, $t );
			$whole = self::set_path( $top, is_array( $whole ) ? $whole : array(), $parts, $value );
			$value = $whole;
		}
		return self::save( $top, Fields::sanitize( $top, $value ), $t );
	}

	private static function set_path( array $field, array $whole, array $parts, $value ) {
		$part = array_shift( $parts );
		if ( 'repeater' === $field['type'] && is_numeric( $part ) ) {
			$i   = (int) $part;
			$row = isset( $whole[ $i ] ) && is_array( $whole[ $i ] ) ? $whole[ $i ] : array();
			if ( ! $parts ) {
				$whole[ $i ] = is_array( $value ) ? $value : $row;
				return $whole;
			}
			$whole[ $i ] = self::set_path( array_merge( $field, array( 'type' => 'group' ) ), $row, $parts, $value );
			return $whole;
		}
		$sub = Registry::descend( $field, array( $part ) );
		if ( ! $sub ) {
			return $whole;
		}
		$whole[ $part ] = $parts ? self::set_path( $sub, isset( $whole[ $part ] ) && is_array( $whole[ $part ] ) ? $whole[ $part ] : array(), $parts, $value ) : $value;
		return $whole;
	}

	/**
	 * Write an already sanitized value.
	 */
	public static function save( array $field, $clean, array $t ) {
		$name   = $field['name'];
		$stored = wp_slash( self::to_storage( $field, $clean ) );
		switch ( $t['type'] ) {
			case 'post':
				update_post_meta( $t['id'], $name, $stored );
				if ( 'taxonomy' === $field['type'] && ! empty( $field['options']['save_terms'] ) && taxonomy_exists( $field['options']['taxonomy'] ) ) {
					wp_set_object_terms( $t['id'], array_map( 'intval', (array) $clean ), $field['options']['taxonomy'], false );
				}
				break;
			case 'term':
				update_term_meta( $t['id'], $name, $stored );
				break;
			case 'user':
				update_user_meta( $t['id'], $name, $stored );
				break;
			case 'option':
				update_option( self::option_name( $name ), self::to_storage( $field, $clean ), false );
				break;
		}
		do_action( 'brik/content/field_saved', $field, $clean, $t );
		return true;
	}

	/**
	 * Every top-level value on a target, keyed by name (used by REST/MCP output).
	 */
	public static function all( $target, $format = false ) {
		$t = self::target( $target );
		if ( ! $t ) {
			return array();
		}
		$out = array();
		foreach ( self::groups( $t ) as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( Fields::has_value( $field['type'] ) && ! isset( $out[ $field['name'] ] ) ) {
					$v                    = self::stored( $field, $t );
					$out[ $field['name'] ] = $format ? Fields::format( $field, $v ) : $v;
				}
			}
		}
		return $out;
	}
}
