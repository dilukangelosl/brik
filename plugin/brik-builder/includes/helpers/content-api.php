<?php
/**
 * Field API for templates and code: read, display and save Brik content fields.
 *
 * $post_id may be a post ID, "term_{id}", "user_{id}", "option", a WP_Post / WP_Term /
 * WP_User, or null for the current object (the loop post, or the queried term/author).
 *
 * @package Brik
 */

use Brik\Content\Fields;
use Brik\Content\Registry;
use Brik\Content\Values;

defined( 'ABSPATH' ) || exit;

/**
 * Formatted value of a field (images as arrays, dates formatted, posts as WP_Post…), or the
 * stored value with $format = false. Dotted names reach into groups and repeaters:
 * "address.city", "team.0.name", "team.name" (that sub field from every row).
 */
function brik_field( $name, $post_id = null, $format = true ) {
	return Values::get( $name, $post_id, $format );
}

/**
 * Stored value of a field.
 */
function brik_raw_field( $name, $post_id = null ) {
	return Values::get( $name, $post_id, false );
}

/**
 * Sanitize and save a field value.
 *
 * @return true|WP_Error
 */
function brik_update_field( $name, $value, $post_id = null ) {
	return Values::update( $name, $value, $post_id );
}

/**
 * Field definition array, or null when the object has no such field.
 */
function brik_field_object( $name, $post_id = null ) {
	$field = Values::field( $name, $post_id );
	if ( $field && 'column' === $field['type'] ) {
		return $field['leaf'];
	}
	return $field;
}

/**
 * Display markup for a field. See Brik\Content\Fields::html() for $args.
 */
function brik_field_html( $name, $post_id = null, array $args = array() ) {
	list( $field, $value ) = Values::resolve( $name, $post_id );
	if ( ! $field ) {
		return isset( $args['empty'] ) ? (string) $args['empty'] : '';
	}
	if ( 'column' === $field['type'] ) {
		$parts = array();
		foreach ( (array) $value as $v ) {
			$html = Fields::html( $field['leaf'], $v, array_merge( $args, array( 'empty' => '' ) ) );
			if ( '' !== $html ) {
				$parts[] = $html;
			}
		}
		return $parts ? '<div class="flex flex-col gap-3">' . implode( '', $parts ) . '</div>' : ( isset( $args['empty'] ) ? (string) $args['empty'] : '' );
	}
	return Fields::html( $field, $value, $args );
}

/**
 * Add a field group from code. Code groups are read-only in the admin.
 *
 * @return array|WP_Error Normalized group.
 */
function brik_register_field_group( array $group ) {
	$def = Registry::add_local( 'groups', $group );
	if ( ! is_wp_error( $def ) ) {
		Brik\Content\Register::late( 'groups', $def );
	}
	return $def;
}

/**
 * Add a post type definition from code (same shape as the admin stores).
 *
 * @return array|WP_Error Normalized definition.
 */
function brik_register_post_type( array $def ) {
	$def = Registry::add_local( 'post_types', $def );
	if ( ! is_wp_error( $def ) ) {
		Brik\Content\Register::late( 'post_types', $def );
	}
	return $def;
}

/**
 * Add a taxonomy definition from code.
 *
 * @return array|WP_Error Normalized definition.
 */
function brik_register_taxonomy( array $def ) {
	$def = Registry::add_local( 'taxonomies', $def );
	if ( ! is_wp_error( $def ) ) {
		Brik\Content\Register::late( 'taxonomies', $def );
	}
	return $def;
}
