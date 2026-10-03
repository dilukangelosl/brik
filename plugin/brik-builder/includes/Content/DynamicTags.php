<?php
namespace Brik\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Dynamic tags for fields: {field:name}, {field:name|raw}, {field:name|url},
 * {field:group.sub}, {term:name} and {option:name}.
 *
 * Modifiers: raw (stored value), url (attachment/link/post URL), label (choice labels),
 * value (choice values), count (items in a list or repeater).
 */
final class DynamicTags {

	public static function init() {
		add_filter( 'brik/dynamic_value', array( __CLASS__, 'value' ), 10, 3 );
		add_filter( 'brik/dynamic_tags', array( __CLASS__, 'tags' ) );
	}

	public static function tags( $tags ) {
		$tags['field:NAME']  = __( 'Content field', 'brik-builder' );
		$tags['term:NAME']   = __( 'Term field', 'brik-builder' );
		$tags['option:NAME'] = __( 'Site option', 'brik-builder' );
		return $tags;
	}

	public static function value( $value, $tag, $post_id = 0 ) {
		if ( null !== $value || ! preg_match( '/^(field|term|option):([A-Za-z0-9_.\-]+)(?:\|([a-z_]+))?$/', (string) $tag, $m ) ) {
			return $value;
		}
		$target = self::target( $m[1], $post_id );
		if ( null === $target ) {
			return '';
		}
		return self::resolve( $m[2], $target, isset( $m[3] ) ? $m[3] : '' );
	}

	/**
	 * The object a tag reads from. Field tags use the post handed in by the renderer (the loop
	 * item inside listings), falling back to the current post, then the queried term.
	 */
	private static function target( $kind, $post_id ) {
		if ( 'option' === $kind ) {
			return 'option';
		}
		if ( 'term' === $kind ) {
			$term = apply_filters( 'brik/content/current_term', get_queried_object() );
			return $term instanceof \WP_Term ? 'term_' . $term->term_id : null;
		}
		$id = $post_id ? (int) $post_id : (int) get_the_ID();
		if ( $id ) {
			return $id;
		}
		$object = get_queried_object();
		return $object instanceof \WP_Term ? 'term_' . $object->term_id : null;
	}

	/**
	 * Escaped text for a field on a target.
	 */
	public static function resolve( $name, $target, $modifier = '' ) {
		list( $field, $value ) = Values::resolve( $name, $target );
		if ( ! $field ) {
			return '';
		}

		if ( 'column' === $field['type'] ) {
			$leaf  = $field['leaf'];
			$parts = array();
			foreach ( (array) $value as $v ) {
				$parts[] = self::one( $leaf, $v, $modifier );
			}
			return 'count' === $modifier ? (string) count( (array) $value ) : implode( ', ', array_filter( $parts, 'strlen' ) );
		}
		return self::one( $field, $value, $modifier );
	}

	private static function one( array $field, $value, $modifier ) {
		switch ( $modifier ) {
			case 'raw':
				if ( is_bool( $value ) ) {
					return $value ? '1' : '0';
				}
				return is_scalar( $value ) ? esc_html( (string) $value ) : esc_html( (string) wp_json_encode( $value ) );
			case 'url':
				$urls = Fields::urls( $field, $value );
				return $urls ? esc_url( $urls[0] ) : '';
			case 'count':
				return (string) ( is_array( $value ) ? count( $value ) : ( Fields::is_empty( $field, $value ) ? 0 : 1 ) );
			case 'value':
				return esc_html( implode( ', ', array_map( 'strval', array_filter( (array) $value, 'is_scalar' ) ) ) );
			case 'label':
				return esc_html( Fields::text( $field, $value ) );
		}

		switch ( $field['type'] ) {
			case 'wysiwyg':
				// Rich text was filtered with wp_kses_post() on save; keep its markup.
				return wp_kses_post( Fields::format( $field, $value ) );
			case 'image':
			case 'file':
			case 'url':
			case 'oembed':
				// URL-like values land in src/href attributes as often as in text.
				$urls = Fields::urls( $field, $value );
				return $urls ? esc_url( $urls[0] ) : '';
			case 'textarea':
				return nl2br( esc_html( (string) $value ) );
		}
		return esc_html( Fields::text( $field, $value ) );
	}
}
