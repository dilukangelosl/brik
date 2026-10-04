<?php
namespace Brik\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Link checking for the audit. Internal links are resolved through WordPress itself (no HTTP
 * round trip, which often fails inside containers); external links get a short HEAD request.
 * Results are cached for an hour.
 */
final class Links {

	const CACHE = HOUR_IN_SECONDS;
	const MAX   = 80;

	/**
	 * Check a list of URLs.
	 *
	 * @param string[] $urls     URLs as written in the page.
	 * @param bool     $external Also check links to other sites.
	 * @return array url => { status: ok|broken|skipped|unknown, internal: bool, code?: int, reason?: string }
	 */
	public static function check( array $urls, $external = false ) {
		$out   = array();
		$count = 0;
		foreach ( array_unique( array_map( 'strval', $urls ) ) as $url ) {
			if ( $count >= self::MAX ) {
				$out[ $url ] = array(
					'status'   => 'skipped',
					'internal' => self::is_internal( $url ),
					'reason'   => 'limit',
				);
				continue;
			}
			$result = self::one( $url, $external );
			if ( 'skipped' !== $result['status'] ) {
				++$count;
			}
			$out[ $url ] = $result;
		}
		return $out;
	}

	public static function is_internal( $url ) {
		$url = trim( $url );
		if ( '' === $url || '#' === $url[0] ) {
			return true;
		}
		if ( '/' === $url[0] && ( ! isset( $url[1] ) || '/' !== $url[1] ) ) {
			return true;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host && $home && strtolower( $host ) === strtolower( $home );
	}

	private static function one( $url, $external ) {
		$url = trim( $url );
		if ( '' === $url || '#' === $url[0] || preg_match( '/^(mailto|tel|sms|javascript|data):/i', $url ) || false !== strpos( $url, '{' ) ) {
			return array(
				'status'   => 'skipped',
				'internal' => true,
			);
		}
		$internal = self::is_internal( $url );
		if ( ! $internal && ! $external ) {
			return array(
				'status'   => 'skipped',
				'internal' => false,
			);
		}

		$key    = 'brik_audit_link_' . md5( $url );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $internal ? self::internal( $url ) : self::remote( $url );
		$result = array_merge( array( 'internal' => $internal ), $result );
		set_transient( $key, $result, self::CACHE );
		return $result;
	}

	/**
	 * Resolve an internal URL the way WordPress would route it.
	 */
	private static function internal( $url ) {
		$abs  = '/' === $url[0] ? home_url( $url ) : $url;
		$path = (string) wp_parse_url( $abs, PHP_URL_PATH );

		// Static files (uploads, theme assets) are checked on disk.
		if ( preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) && ! preg_match( '/\.php$/i', $path ) ) {
			$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$relative  = ltrim( substr( $path, strlen( rtrim( $home_path, '/' ) ) ), '/' );
			$file      = ABSPATH . rawurldecode( $relative );
			return file_exists( $file ) ? array( 'status' => 'ok' ) : array(
				'status' => 'broken',
				'code'   => 404,
				'reason' => 'file',
			);
		}

		if ( url_to_postid( $abs ) ) {
			$post = get_post( url_to_postid( $abs ) );
			if ( $post && in_array( $post->post_status, array( 'publish', 'private', 'inherit' ), true ) ) {
				return array(
					'status'  => 'ok',
					'post_id' => $post->ID,
				);
			}
			return array(
				'status' => 'broken',
				'code'   => 404,
				'reason' => 'unpublished',
			);
		}

		$query = (string) wp_parse_url( $abs, PHP_URL_QUERY );
		if ( '' === trim( $path, '/' ) || preg_match( '#^/?wp-(admin|login|json)#', ltrim( $path, '/' ) ) || 0 === strpos( $path, '/wp-json' ) ) {
			return array( 'status' => 'ok' );
		}

		return self::route( $path, $query );
	}

	/**
	 * Run WordPress' request parsing for a path and see whether it finds anything.
	 */
	private static function route( $path, $query ) {
		global $wp_rewrite;

		$saved = array(
			'REQUEST_URI' => isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'PATH_INFO'   => isset( $_SERVER['PATH_INFO'] ) ? $_SERVER['PATH_INFO'] : null, // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'GET'         => $_GET, // phpcs:ignore WordPress.Security.NonceVerification
		);
		$_SERVER['REQUEST_URI'] = $path . ( $query ? '?' . $query : '' );
		unset( $_SERVER['PATH_INFO'] );
		$_GET = array();
		if ( $query ) {
			wp_parse_str( $query, $_GET );
		}

		$wp = new \WP();
		$wp->parse_request();
		$vars = $wp->query_vars;

		foreach ( array( 'REQUEST_URI', 'PATH_INFO' ) as $k ) {
			if ( null === $saved[ $k ] ) {
				unset( $_SERVER[ $k ] );
			} else {
				$_SERVER[ $k ] = $saved[ $k ];
			}
		}
		$_GET = $saved['GET'];

		if ( ! empty( $vars['error'] ) && '404' === (string) $vars['error'] ) {
			return array(
				'status' => 'broken',
				'code'   => 404,
			);
		}
		if ( ! $wp_rewrite || ! $wp_rewrite->using_permalinks() ) {
			if ( ! $vars ) {
				return array( 'status' => 'unknown' );
			}
		}

		$q = new \WP_Query( array_merge( $vars, array( 'posts_per_page' => 1, 'no_found_rows' => true, 'fields' => 'ids' ) ) );
		if ( $q->have_posts() ) {
			return array( 'status' => 'ok' );
		}
		// Empty archives (a category without posts, an author page) still resolve.
		if ( $q->is_archive() || $q->is_search() || $q->is_home() ) {
			if ( ( $q->is_category() || $q->is_tag() || $q->is_tax() || $q->is_author() ) && ! $q->get_queried_object() ) {
				return array(
					'status' => 'broken',
					'code'   => 404,
				);
			}
			return array( 'status' => 'ok' );
		}
		return array(
			'status' => 'broken',
			'code'   => 404,
		);
	}

	private static function remote( $url ) {
		$args = array(
			'timeout'     => 3,
			'redirection' => 3,
			'user-agent'  => 'Mozilla/5.0 (compatible; BrikLinkCheck/1.0; ' . home_url() . ')',
		);
		$res = wp_safe_remote_head( $url, $args );
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		// Some servers refuse HEAD; try a GET before calling the link broken.
		if ( in_array( $code, array( 403, 405, 501 ), true ) ) {
			$res  = wp_safe_remote_get( $url, array_merge( $args, array( 'limit_response_size' => 2048 ) ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		}
		if ( ! $code ) {
			return array(
				'status' => 'unknown',
				'reason' => is_wp_error( $res ) ? $res->get_error_message() : '',
			);
		}
		if ( $code >= 400 && 429 !== $code && 403 !== $code ) {
			return array(
				'status' => 'broken',
				'code'   => $code,
			);
		}
		return array(
			'status' => 'ok',
			'code'   => $code,
		);
	}
}
