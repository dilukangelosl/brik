<?php
namespace Brik\Perf;

use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Images and embeds: modern formats for new uploads, loading priorities and dimensions in
 * rendered markup, optional <picture> sources and lazy iframes.
 */
final class Media {

	const MAX_PROBES = 30;

	public static function init() {
		add_filter( 'image_editor_output_format', array( __CLASS__, 'output_format' ) );
	}

	/** Formats this server can write. */
	public static function supported() {
		$out = array();
		foreach ( array( 'webp', 'avif' ) as $format ) {
			if ( wp_image_editor_supports( array( 'mime_type' => 'image/' . $format ) ) ) {
				$out[] = $format;
			}
		}
		return $out;
	}

	/**
	 * Convert JPEG/PNG uploads (and their generated sizes) to WebP or AVIF when the setting
	 * asks for it and the image editor can write that format.
	 */
	public static function output_format( $formats ) {
		$format = Settings::get( 'image_format' );
		if ( ! in_array( $format, array( 'webp', 'avif' ), true ) || ! in_array( $format, self::supported(), true ) ) {
			return $formats;
		}
		$formats['image/jpeg'] = 'image/' . $format;
		$formats['image/png']  = 'image/' . $format;
		return $formats;
	}

	/**
	 * Post-process rendered markup.
	 *
	 * @param string $html    Markup.
	 * @param string $context main (page or body template: its first image is the likely LCP),
	 *                        header (above the fold, never lazy) or other.
	 * @param array  $root    Top-level node ids, to find where the first section ends.
	 */
	public static function process( $html, $context = 'other', array $root = array() ) {
		if ( false === stripos( $html, '<img' ) && false === stripos( $html, '<iframe' ) ) {
			return $html;
		}

		// Third-party embeds load when they scroll into view.
		$html = preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			static function ( $m ) {
				return preg_match( '/\sloading\s*=/i', $m[0] ) ? $m[0] : preg_replace( '/^<iframe/i', '<iframe loading="lazy"', $m[0] );
			},
			$html
		);

		$probes = 0;
		$html   = preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $m ) use ( &$probes ) {
				return self::dimensions( $m[0], $probes );
			},
			$html
		);

		if ( 'header' === $context ) {
			$html = preg_replace_callback(
				'/<img\b[^>]*>/i',
				static function ( $m ) {
					return self::eager( $m[0], false );
				},
				$html
			);
		} elseif ( 'main' === $context ) {
			$first = PageCss::first_sections( $html, $root, 1 );
			if ( preg_match( '/<img\b[^>]*>/i', $first, $m, PREG_OFFSET_CAPTURE ) ) {
				$html = substr_replace( $html, self::eager( $m[0][0], true ), $m[0][1], strlen( $m[0][0] ) );
			}
		}

		if ( Settings::get( 'perf_picture' ) ) {
			$html = self::pictures( $html );
		}
		return $html;
	}

	/** Drop lazy loading; the hero image also gets fetch priority. */
	private static function eager( $tag, $priority ) {
		$tag = preg_replace( '/\sloading\s*=\s*(["\'])lazy\1/i', '', $tag );
		if ( $priority && ! preg_match( '/\sfetchpriority\s*=/i', $tag ) ) {
			$tag = preg_replace( '/^<img/i', '<img fetchpriority="high"', $tag );
		}
		return $tag;
	}

	/**
	 * Add width/height to images from the media folder that lack them, so the browser can
	 * reserve their space (no layout shift).
	 */
	private static function dimensions( $tag, &$probes ) {
		if ( preg_match( '/\swidth\s*=/i', $tag ) && preg_match( '/\sheight\s*=/i', $tag ) ) {
			return $tag;
		}
		if ( $probes >= self::MAX_PROBES || ! preg_match( '/\ssrc\s*=\s*(["\'])([^"\']+)\1/i', $tag, $m ) ) {
			return $tag;
		}
		$file = self::local_path( html_entity_decode( $m[2] ) );
		if ( ! $file ) {
			return $tag;
		}
		++$probes;
		$size = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- unreadable files are fine to skip.
		if ( ! $size || ! $size[0] ) {
			return $tag;
		}
		return preg_replace( '/^<img/i', sprintf( '<img width="%d" height="%d"', $size[0], $size[1] ), $tag );
	}

	/** Filesystem path of a URL inside the uploads folder, or ''. */
	public static function local_path( $url ) {
		static $uploads = null;
		if ( null === $uploads ) {
			$uploads = wp_upload_dir( null, false );
		}
		$url  = strtok( (string) $url, '?#' );
		$base = set_url_scheme( $uploads['baseurl'] );
		$url  = set_url_scheme( $url );
		if ( 0 !== strpos( $url, $base . '/' ) ) {
			return '';
		}
		$rel  = rawurldecode( substr( $url, strlen( $base ) ) );
		$path = $uploads['basedir'] . $rel;
		if ( false !== strpos( $rel, '..' ) || ! is_file( $path ) ) {
			return '';
		}
		return $path;
	}

	/**
	 * Wrap uploads that have AVIF/WebP siblings (photo.jpg → photo.avif / photo.webp, or
	 * photo.jpg.webp) in <picture>. display:contents keeps the layout identical.
	 */
	private static function pictures( $html ) {
		return preg_replace_callback(
			'/(<picture\b.*?<\/picture>)|<img\b[^>]*>/is',
			static function ( $m ) {
				if ( ! empty( $m[1] ) ) {
					return $m[0];
				}
				$tag = $m[0];
				if ( ! preg_match( '/\ssrc\s*=\s*(["\'])([^"\']+)\1/i', $tag, $src ) || ! preg_match( '/\.(jpe?g|png)(\?|$)/i', $src[2] ) ) {
					return $tag;
				}
				$candidates = array( $src[2] );
				if ( preg_match( '/\ssrcset\s*=\s*(["\'])([^"\']+)\1/i', $tag, $set ) ) {
					$candidates = array_map( 'trim', explode( ',', html_entity_decode( $set[2] ) ) );
				}
				$sizes   = preg_match( '/\ssizes\s*=\s*(["\'])([^"\']+)\1/i', $tag, $sz ) ? ' sizes="' . esc_attr( html_entity_decode( $sz[2] ) ) . '"' : '';
				$sources = '';
				foreach ( array( 'avif', 'webp' ) as $format ) {
					$alt = array();
					foreach ( $candidates as $candidate ) {
						$parts = preg_split( '/\s+/', $candidate, 2 );
						$url   = self::sibling( $parts[0], $format );
						if ( ! $url ) {
							$alt = array();
							break;
						}
						$alt[] = $url . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
					}
					if ( $alt ) {
						$sources .= '<source type="image/' . $format . '" srcset="' . esc_attr( implode( ', ', $alt ) ) . '"' . $sizes . '>';
					}
				}
				return $sources ? '<picture style="display:contents">' . $sources . $tag . '</picture>' : $tag;
			},
			$html
		);
	}

	private static function sibling( $url, $format ) {
		$path = self::local_path( $url );
		if ( ! $path ) {
			return '';
		}
		foreach ( array( preg_replace( '/\.(jpe?g|png)$/i', '.' . $format, $path ), $path . '.' . $format ) as $i => $candidate ) {
			if ( $candidate !== $path && is_file( $candidate ) ) {
				return 0 === $i ? preg_replace( '/\.(jpe?g|png)(\?.*)?$/i', '.' . $format, $url ) : $url . '.' . $format;
			}
		}
		return '';
	}
}
