<?php
/**
 * Dynamic tags for database content:
 *
 *   {post:title}  {post:image|url}  {post:date|relative}  {author:name}  {author:avatar}
 *   {user:email}  {term:category|links}  {meta:price|number}  {option:blogname}
 *   {param:utm_source}  {now:Y-m-d}  {site:logo}  {acf:price}  {acf:team.name}
 *   {metabox:rating}  {pods:color}
 *
 * Values are resolved to a typed value first (get()) and escaped for output in format(), so
 * display conditions can compare the raw value with the same lookups.
 *
 * @package Brik
 */

namespace Brik\Data;

defined( 'ABSPATH' ) || exit;

final class Tags {

	const PREFIXES = array( 'post', 'author', 'user', 'term', 'meta', 'option', 'param', 'now', 'site', 'acf', 'metabox', 'pods' );

	/** Modifiers understood by format(). */
	const MODIFIERS = array( 'raw', 'url', 'id', 'alt', 'label', 'count', 'first', 'list', 'links', 'slug', 'date', 'time', 'datetime', 'iso', 'relative', 'timestamp', 'year', 'number', 'int', 'upper', 'lower', 'text' );

	public static function init() {
		// Before the content fields filter (priority 10), which also claims {term:…} and {option:…}.
		add_filter( 'brik/dynamic_value', array( __CLASS__, 'filter' ), 5, 3 );
		add_filter( 'brik/dynamic_tags', array( __CLASS__, 'labels' ) );
	}

	public static function labels( $tags ) {
		return (array) $tags + array(
			'post:title'       => __( 'Post: title', 'brik-builder' ),
			'post:image'       => __( 'Post: featured image URL', 'brik-builder' ),
			'author:name'      => __( 'Author: name', 'brik-builder' ),
			'author:avatar'    => __( 'Author: avatar URL', 'brik-builder' ),
			'user:name'        => __( 'Current user: name', 'brik-builder' ),
			'term:TAXONOMY'    => __( 'Post terms', 'brik-builder' ),
			'acf:NAME'         => __( 'ACF field', 'brik-builder' ),
			'param:NAME'       => __( 'URL parameter', 'brik-builder' ),
			'now:FORMAT'       => __( 'Current date/time', 'brik-builder' ),
		);
	}

	public static function filter( $value, $tag, $post_id ) {
		if ( null !== $value || ! is_string( $tag ) ) {
			return $value;
		}
		$p = self::parse( $tag );
		if ( ! $p ) {
			return $value;
		}
		$data = self::get( $p['prefix'], $p['name'], $post_id, $p['mod'] );
		return null === $data ? $value : self::format( $data, $p['mod'] );
	}

	/**
	 * Split "prefix:name|modifier". Null when the prefix isn't one of ours.
	 */
	public static function parse( $tag ) {
		if ( ! preg_match( '/^([a-z_]+):([A-Za-z0-9_.\-]+)(?:\|([a-z_]+))?$/', (string) $tag, $m ) || ! in_array( $m[1], self::PREFIXES, true ) ) {
			return null;
		}
		return array(
			'prefix' => $m[1],
			'name'   => $m[2],
			'mod'    => isset( $m[3] ) ? $m[3] : '',
		);
	}

	/**
	 * Unescaped value of any tag (ours, content fields, product tags or core tags), for
	 * comparisons. Lists come back as arrays of strings.
	 *
	 * @param string $tag Tag without braces, e.g. "meta:price" or "post_title".
	 */
	public static function raw( $tag, $post_id = 0 ) {
		$tag = trim( (string) $tag, "{} \t" );
		$p   = self::parse( $tag );
		if ( $p ) {
			$data = self::get( $p['prefix'], $p['name'], $post_id, $p['mod'] );
			return null === $data ? '' : self::plain( $data );
		}
		if ( preg_match( '/^field:([A-Za-z0-9_.\-]+)/', $tag, $m ) && function_exists( 'brik_raw_field' ) ) {
			$v = brik_raw_field( $m[1], Data::context_post( $post_id ) );
			if ( is_bool( $v ) ) {
				return $v ? '1' : '0';
			}
			return is_array( $v ) ? array_map( 'strval', array_filter( $v, 'is_scalar' ) ) : (string) $v;
		}
		if ( preg_match( '/^tax:([a-z0-9_\-]+)$/', $tag, $m ) ) {
			return self::raw( 'term:' . $m[1], $post_id );
		}
		$value = \Brik\Dynamic::value( $tag, $post_id );
		return null === $value ? '' : html_entity_decode( wp_strip_all_tags( (string) $value ), ENT_QUOTES, 'UTF-8' );
	}

	/* ---------------------------------------------------------------------
	 * Lookup.
	 * ------------------------------------------------------------------- */

	/**
	 * Typed value: array( 'kind' => text|html|url|image|date|number|bool|terms|posts|list, 'v' => … ).
	 * Null when the tag isn't handled here, so other filters can answer it.
	 */
	public static function get( $prefix, $name, $post_id = 0, $mod = '' ) {
		switch ( $prefix ) {
			case 'post':
				return self::post( $name, Data::context_post( $post_id ) );
			case 'author':
				$post = get_post( Data::context_post( $post_id ) );
				if ( ! $post || ! Data::can_read( $post ) ) {
					return self::text( '' );
				}
				return self::person( $name, (int) $post->post_author, false );
			case 'user':
				return is_user_logged_in() ? self::person( $name, get_current_user_id(), true ) : self::text( '' );
			case 'term':
				return self::terms( $name, Data::context_post( $post_id ) );
			case 'meta':
				return self::meta( $name, Data::context_post( $post_id ) );
			case 'option':
				return self::option( $name );
			case 'param':
				return self::param( $name );
			case 'now':
				return self::now( $name );
			case 'site':
				return self::site( $name );
			case 'acf':
				return Plugins::acf( $name, Data::context_post( $post_id ), $mod );
			case 'metabox':
				return Plugins::metabox( $name, Data::context_post( $post_id ) );
			case 'pods':
				return Plugins::pods( $name, Data::context_post( $post_id ), $mod );
		}
		return null;
	}

	public static function text( $v, $kind = 'text' ) {
		return array(
			'kind' => $kind,
			'v'    => $v,
		);
	}

	private static function post( $name, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) ) {
			return self::text( '' );
		}
		$locked = post_password_required( $post );

		switch ( $name ) {
			case 'title':
				return self::text( get_the_title( $post ) );
			case 'excerpt':
				return self::text( $locked ? '' : get_the_excerpt( $post ) );
			case 'content':
				// No the_content filters: a page built with Brik would render itself again.
				return self::text( $locked ? '' : wpautop( do_shortcode( $post->post_content ) ), 'html' );
			case 'date':
				return self::text( (int) get_post_timestamp( $post ), 'date' );
			case 'modified':
				return self::text( (int) get_post_timestamp( $post, 'modified' ), 'date' );
			case 'url':
			case 'permalink':
				return self::text( (string) get_permalink( $post ), 'url' );
			case 'id':
				return self::text( (int) $post->ID, 'number' );
			case 'slug':
				return self::text( $post->post_name );
			case 'status':
				$status = get_post_status_object( $post->post_status );
				return self::text( $status ? $status->label : $post->post_status );
			case 'type':
				return self::text( $post->post_type );
			case 'type_label':
				$type = get_post_type_object( $post->post_type );
				return self::text( $type ? $type->labels->singular_name : $post->post_type );
			case 'image':
			case 'image_id':
			case 'image_alt':
				$id = (int) get_post_thumbnail_id( $post );
				return self::image( $id );
			case 'comment_count':
				return self::text( (int) get_comments_number( $post ), 'number' );
			case 'menu_order':
				return self::text( (int) $post->menu_order, 'number' );
			case 'reading_time':
				$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
				return self::text( max( 1, (int) ceil( $words / 220 ) ), 'number' );
			case 'parent':
			case 'parent_title':
				return self::text( $post->post_parent && Data::can_read( $post->post_parent ) ? get_the_title( $post->post_parent ) : '' );
			case 'parent_url':
				return self::text( $post->post_parent && Data::can_read( $post->post_parent ) ? (string) get_permalink( $post->post_parent ) : '', 'url' );
			case 'parent_id':
				return self::text( (int) $post->post_parent, 'number' );
		}
		return self::text( '' );
	}

	/**
	 * Image value from an attachment id: url by default, |id and |alt on request.
	 */
	public static function image( $id, $size = 'full' ) {
		$id = (int) $id;
		return array(
			'kind' => 'image',
			'v'    => $id ? (string) wp_get_attachment_image_url( $id, $size ) : '',
			'id'   => $id,
			'alt'  => $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '',
		);
	}

	/**
	 * Fields of a user. The post author is public profile data only; the current user also
	 * gets email, login and roles (it's their own account).
	 */
	private static function person( $name, $user_id, $self ) {
		$user = $user_id ? get_userdata( $user_id ) : null;
		if ( ! $user ) {
			return self::text( '' );
		}
		if ( 0 === strpos( $name, 'meta.' ) ) {
			$key = substr( $name, 5 );
			if ( ! in_array( $key, self::user_meta_keys(), true ) ) {
				return self::text( '' );
			}
			$v = get_user_meta( $user->ID, $key, true );
			return is_scalar( $v ) ? self::text( (string) $v ) : self::text( '' );
		}
		switch ( $name ) {
			case 'name':
			case 'display_name':
				return self::text( $user->display_name );
			case 'first_name':
			case 'last_name':
			case 'nickname':
				return self::text( (string) get_user_meta( $user->ID, $name, true ) );
			case 'bio':
			case 'description':
				return self::text( (string) get_user_meta( $user->ID, 'description', true ) );
			case 'avatar':
				return array(
					'kind' => 'image',
					'v'    => (string) get_avatar_url( $user->ID, array( 'size' => 256 ) ),
					'id'   => 0,
					'alt'  => $user->display_name,
				);
			case 'url':
				return self::text( get_author_posts_url( $user->ID ), 'url' );
			case 'website':
				return self::text( (string) $user->user_url, 'url' );
			case 'id':
				return self::text( (int) $user->ID, 'number' );
			case 'posts':
				return self::text( (int) count_user_posts( $user->ID, 'post', true ), 'number' );
		}
		if ( ! $self ) {
			return self::text( '' );
		}
		switch ( $name ) {
			case 'email':
				return self::text( $user->user_email );
			case 'login':
				return self::text( $user->user_login );
			case 'role':
			case 'roles':
				$names = array();
				$all   = wp_roles()->get_names();
				foreach ( (array) $user->roles as $role ) {
					$names[] = isset( $all[ $role ] ) ? translate_user_role( $all[ $role ] ) : $role;
				}
				return self::text( $names, 'list' );
			case 'registered':
				return self::text( (int) strtotime( $user->user_registered . ' UTC' ), 'date' );
		}
		return self::text( '' );
	}

	/**
	 * User meta keys that may be shown. Everything else (capabilities, session tokens …) stays private.
	 */
	public static function user_meta_keys() {
		$keys = array( 'first_name', 'last_name', 'nickname', 'description', 'locale' );
		if ( class_exists( '\\Brik\\Content\\Registry' ) ) {
			$keys = array_merge( $keys, array_keys( \Brik\Content\Registry::fields_for( 'user' ) ) );
		}
		if ( class_exists( 'WooCommerce' ) ) {
			$keys = array_merge( $keys, array( 'billing_first_name', 'billing_last_name', 'billing_company', 'billing_city', 'billing_state', 'billing_country' ) );
		}
		return array_values( array_unique( (array) apply_filters( 'brik/data/user_meta_keys', $keys ) ) );
	}

	private static function terms( $taxonomy, $post_id ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			// Not a taxonomy: let {term:field} (term fields on archives) answer.
			return null;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) || ! is_taxonomy_viewable( $taxonomy ) ) {
			return self::text( array(), 'terms' );
		}
		$terms = get_the_terms( $post, $taxonomy );
		return self::text( $terms && ! is_wp_error( $terms ) ? array_values( $terms ) : array(), 'terms' );
	}

	/**
	 * Protected (underscore) meta is shown only when it's registered for REST, allow-listed, or
	 * the viewer can edit the post.
	 */
	public static function meta_allowed( $key, $post_id = 0, $post_type = '' ) {
		if ( ! is_protected_meta( $key, 'post' ) ) {
			return true;
		}
		$public = (array) apply_filters( 'brik/data/public_meta', array( '_price', '_regular_price', '_sale_price', '_sku', '_stock', '_stock_status', '_wc_average_rating', '_wc_review_count', '_thumbnail_id' ) );
		if ( in_array( $key, $public, true ) ) {
			return true;
		}
		$type = $post_id ? get_post_type( $post_id ) : $post_type;
		$reg  = get_registered_meta_keys( 'post', (string) $type ) + get_registered_meta_keys( 'post' );
		if ( isset( $reg[ $key ] ) && ! empty( $reg[ $key ]['show_in_rest'] ) ) {
			return true;
		}
		return $post_id && current_user_can( 'edit_post', $post_id );
	}

	private static function meta( $key, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || ! Data::can_read( $post ) || ! self::meta_allowed( $key, $post->ID ) ) {
			return self::text( '' );
		}
		return self::guess( get_post_meta( $post->ID, $key, true ) );
	}

	/**
	 * Typed value for something read from storage with no field definition.
	 */
	public static function guess( $v ) {
		if ( $v instanceof \WP_Post ) {
			return self::text( array( $v ), 'posts' );
		}
		if ( $v instanceof \WP_Term ) {
			return self::text( array( $v ), 'terms' );
		}
		if ( $v instanceof \WP_User ) {
			return self::text( $v->display_name );
		}
		if ( is_array( $v ) ) {
			if ( isset( $v['url'] ) && is_string( $v['url'] ) ) {
				return array(
					'kind'  => isset( $v['mime_type'] ) && 0 !== strpos( (string) $v['mime_type'], 'image/' ) ? 'url' : 'image',
					'v'     => $v['url'],
					'id'    => isset( $v['ID'] ) ? (int) $v['ID'] : ( isset( $v['id'] ) ? (int) $v['id'] : 0 ),
					'alt'   => isset( $v['alt'] ) ? (string) $v['alt'] : '',
					'label' => isset( $v['title'] ) && is_string( $v['title'] ) ? $v['title'] : '',
				);
			}
			$first = reset( $v );
			if ( $first instanceof \WP_Post ) {
				return self::text( array_values( $v ), 'posts' );
			}
			if ( $first instanceof \WP_Term ) {
				return self::text( array_values( $v ), 'terms' );
			}
			$flat = array();
			foreach ( $v as $item ) {
				if ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				} elseif ( is_array( $item ) && isset( $item['label'] ) ) {
					$flat[] = (string) $item['label'];
				}
			}
			return self::text( $flat, 'list' );
		}
		if ( is_bool( $v ) ) {
			return self::text( $v, 'bool' );
		}
		if ( is_int( $v ) || is_float( $v ) ) {
			return self::text( $v, 'number' );
		}
		return self::text( (string) $v );
	}

	/**
	 * WordPress options that are safe to print.
	 */
	public static function public_options() {
		return (array) apply_filters(
			'brik/data/public_options',
			array(
				'blogname'        => __( 'Site title', 'brik-builder' ),
				'blogdescription' => __( 'Tagline', 'brik-builder' ),
				'home'            => __( 'Home URL', 'brik-builder' ),
				'siteurl'         => __( 'WordPress URL', 'brik-builder' ),
				'date_format'     => __( 'Date format', 'brik-builder' ),
				'time_format'     => __( 'Time format', 'brik-builder' ),
				'timezone_string' => __( 'Timezone', 'brik-builder' ),
				'start_of_week'   => __( 'Week starts on', 'brik-builder' ),
				'posts_per_page'  => __( 'Posts per page', 'brik-builder' ),
				'WPLANG'          => __( 'Site language', 'brik-builder' ),
			)
		);
	}

	private static function option( $name ) {
		if ( ! array_key_exists( $name, self::public_options() ) ) {
			// Brik options-page fields are answered by the content fields filter.
			return null;
		}
		$v = get_option( $name );
		if ( in_array( $name, array( 'home', 'siteurl' ), true ) ) {
			return self::text( (string) $v, 'url' );
		}
		return is_scalar( $v ) ? self::text( (string) $v ) : self::text( '' );
	}

	private static function param( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display value.
		$v = isset( $_GET[ $name ] ) ? wp_unslash( $_GET[ $name ] ) : '';
		if ( ! is_string( $v ) ) {
			return self::text( '' );
		}
		$v = sanitize_text_field( $v );
		return self::text( function_exists( 'mb_substr' ) ? mb_substr( $v, 0, 200 ) : substr( $v, 0, 200 ) );
	}

	private static function now( $format ) {
		$named = array(
			'date'      => (string) get_option( 'date_format' ),
			'time'      => (string) get_option( 'time_format' ),
			'datetime'  => get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			'year'      => 'Y',
			'month'     => 'F',
			'day'       => 'j',
			'weekday'   => 'l',
			'iso'       => 'c',
			'timestamp' => 'U',
		);
		if ( isset( $named[ $format ] ) ) {
			return self::text( wp_date( $named[ $format ] ) );
		}
		if ( ! preg_match( '/^[A-Za-z\-_.]{1,20}$/', $format ) ) {
			return self::text( '' );
		}
		return self::text( wp_date( $format ) );
	}

	private static function site( $name ) {
		switch ( $name ) {
			case 'name':
			case 'title':
				return self::text( get_bloginfo( 'name' ) );
			case 'tagline':
			case 'description':
				return self::text( get_bloginfo( 'description' ) );
			case 'url':
				return self::text( home_url( '/' ), 'url' );
			case 'logo':
				return self::image( (int) get_theme_mod( 'custom_logo' ) );
			case 'icon':
				return self::text( (string) get_site_icon_url( 512 ), 'url' );
			case 'language':
				return self::text( get_bloginfo( 'language' ) );
			case 'year':
				return self::text( wp_date( 'Y' ) );
		}
		return self::text( '' );
	}

	/* ---------------------------------------------------------------------
	 * Output.
	 * ------------------------------------------------------------------- */

	/**
	 * Escaped markup for a typed value.
	 */
	public static function format( array $d, $mod = '' ) {
		$kind = $d['kind'];
		$v    = $d['v'];

		if ( 'raw' === $mod ) {
			if ( is_array( $v ) ) {
				return esc_html( implode( ', ', self::names( $d, 'id' ) ) );
			}
			return is_bool( $v ) ? ( $v ? '1' : '0' ) : esc_html( (string) $v );
		}
		if ( 'count' === $mod ) {
			return (string) ( is_array( $v ) ? count( $v ) : ( '' === (string) $v ? 0 : 1 ) );
		}

		switch ( $kind ) {
			case 'terms':
			case 'posts':
			case 'list':
				return self::format_list( $d, $mod );
			case 'image':
				if ( 'id' === $mod ) {
					return (string) (int) $d['id'];
				}
				if ( 'alt' === $mod ) {
					return esc_html( $d['alt'] );
				}
				if ( 'label' === $mod && isset( $d['label'] ) ) {
					return esc_html( $d['label'] );
				}
				return esc_url( (string) $v );
			case 'url':
				if ( 'label' === $mod && ! empty( $d['label'] ) ) {
					return esc_html( $d['label'] );
				}
				return esc_url( (string) $v );
			case 'html':
				return 'text' === $mod ? esc_html( wp_strip_all_tags( (string) $v ) ) : wp_kses_post( (string) $v );
			case 'date':
				return esc_html( self::format_date( (int) $v, $mod ) );
			case 'bool':
				return $v ? esc_html__( 'Yes', 'brik-builder' ) : esc_html__( 'No', 'brik-builder' );
			case 'number':
				if ( 'number' === $mod ) {
					$n = (float) $v;
					return esc_html( number_format_i18n( $n, floor( $n ) === $n ? 0 : 2 ) );
				}
				return 'int' === $mod ? (string) (int) $v : esc_html( (string) $v );
		}

		$text = (string) $v;
		switch ( $mod ) {
			case 'url':
				return esc_url( $text );
			case 'upper':
				return esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text ) : strtoupper( $text ) );
			case 'lower':
				return esc_html( function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text ) );
			case 'number':
				return is_numeric( $text ) ? esc_html( number_format_i18n( (float) $text, floor( (float) $text ) === (float) $text ? 0 : 2 ) ) : esc_html( $text );
			case 'int':
				return (string) (int) $text;
			case 'date':
			case 'time':
			case 'datetime':
			case 'iso':
			case 'relative':
			case 'timestamp':
			case 'year':
				$ts = self::timestamp( $text );
				return $ts ? esc_html( self::format_date( $ts, $mod ) ) : esc_html( $text );
		}
		return esc_html( $text );
	}

	private static function format_date( $ts, $mod ) {
		if ( ! $ts ) {
			return '';
		}
		switch ( $mod ) {
			case 'time':
				return wp_date( (string) get_option( 'time_format' ), $ts );
			case 'datetime':
				return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
			case 'iso':
				return wp_date( 'c', $ts );
			case 'timestamp':
				return (string) $ts;
			case 'year':
				return wp_date( 'Y', $ts );
			case 'relative':
				/* translators: %s: time span, e.g. "3 days" */
				return $ts > time() ? sprintf( __( 'in %s', 'brik-builder' ), human_time_diff( time(), $ts ) ) : sprintf( __( '%s ago', 'brik-builder' ), human_time_diff( $ts, time() ) );
		}
		return wp_date( (string) get_option( 'date_format' ), $ts );
	}

	/**
	 * A date-like string (Y-m-d, Ymd, datetime, timestamp) as a Unix timestamp, or 0.
	 */
	public static function timestamp( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return 0;
		}
		if ( preg_match( '/^\d{8}$/', $text ) ) {
			$text = substr( $text, 0, 4 ) . '-' . substr( $text, 4, 2 ) . '-' . substr( $text, 6, 2 );
		} elseif ( preg_match( '/^\d{9,11}$/', $text ) ) {
			return (int) $text;
		}
		$date = date_create_immutable( $text, wp_timezone() );
		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * Display names (or ids/slugs/urls) of the items in a list value.
	 */
	private static function names( array $d, $what = 'name' ) {
		$out = array();
		foreach ( (array) $d['v'] as $item ) {
			if ( $item instanceof \WP_Term ) {
				$out[] = 'id' === $what ? (string) $item->term_id : ( 'slug' === $what ? $item->slug : ( 'url' === $what ? (string) get_term_link( $item ) : $item->name ) );
			} elseif ( $item instanceof \WP_Post ) {
				if ( ! Data::can_read( $item ) ) {
					continue;
				}
				$out[] = 'id' === $what ? (string) $item->ID : ( 'slug' === $what ? $item->post_name : ( 'url' === $what ? (string) get_permalink( $item ) : get_the_title( $item ) ) );
			} elseif ( is_scalar( $item ) ) {
				$out[] = (string) $item;
			}
		}
		return $out;
	}

	private static function format_list( array $d, $mod ) {
		switch ( $mod ) {
			case 'first':
				$names = self::names( $d );
				return $names ? esc_html( $names[0] ) : '';
			case 'url':
				$urls = 'list' === $d['kind'] ? array() : self::names( $d, 'url' );
				return $urls ? esc_url( $urls[0] ) : '';
			case 'slug':
				return esc_html( implode( ', ', self::names( $d, 'slug' ) ) );
			case 'id':
				return esc_html( implode( ', ', self::names( $d, 'id' ) ) );
			case 'links':
				if ( 'list' === $d['kind'] ) {
					break;
				}
				$names = self::names( $d );
				$urls  = self::names( $d, 'url' );
				$links = array();
				foreach ( $names as $i => $name ) {
					$links[] = '<a href="' . esc_url( isset( $urls[ $i ] ) ? $urls[ $i ] : '' ) . '">' . esc_html( $name ) . '</a>';
				}
				return implode( ', ', $links );
		}
		return esc_html( implode( ', ', self::names( $d ) ) );
	}

	/**
	 * Comparable value: string, number, timestamp or list of strings (names, slugs and ids).
	 */
	public static function plain( array $d ) {
		switch ( $d['kind'] ) {
			case 'terms':
			case 'posts':
				return array_values( array_unique( array_merge( self::names( $d ), self::names( $d, 'slug' ), self::names( $d, 'id' ) ) ) );
			case 'list':
				return self::names( $d );
			case 'bool':
				return $d['v'] ? '1' : '0';
			case 'html':
				return trim( wp_strip_all_tags( (string) $d['v'] ) );
			case 'date':
				return (int) $d['v'] ? wp_date( 'Y-m-d H:i:s', (int) $d['v'] ) : '';
		}
		return is_scalar( $d['v'] ) ? (string) $d['v'] : '';
	}
}
