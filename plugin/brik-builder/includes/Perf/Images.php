<?php
namespace Brik\Perf;

use Brik\Modules;
use Brik\Renderer;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Image audit of a Brik tree: file weight, intrinsic vs displayed width, and a smaller
 * registered size when one would do.
 */
final class Images {

	/** Flag files heavier than this. */
	const HEAVY = 307200;

	/** Viewport width assumed for full-bleed backgrounds. */
	const VIEWPORT = 1920;

	/**
	 * Every image a tree displays.
	 *
	 * @return array[] node_id, type, field, id, url, file, bytes, width, height, size, display,
	 *                 oversized, heavy, suggest (size name), estimate (bytes after fixing).
	 */
	public static function inspect( array $tree, $post_id = 0 ) {
		$renderer  = new Renderer( $post_id );
		$container = (int) Settings::get( 'container' );
		$container = $container > 0 ? $container : 1200;
		$out       = array();
		self::walk( $tree, $renderer, $container, $out );
		return $out;
	}

	private static function walk( array $nodes, Renderer $renderer, $width, array &$out, $parent = null ) {
		foreach ( $nodes as $index => $node ) {
			if ( empty( $node['type'] ) ) {
				continue;
			}
			$def = Modules::get( $node['type'] );
			if ( ! $def ) {
				continue;
			}
			$node  = wp_parse_args(
				$node,
				array(
					'id'       => '',
					'attrs'    => array(),
					'children' => array(),
				)
			);
			$attrs = $renderer->resolve_attrs( $node, $def );
			$box   = self::box( $node, $attrs, $width, $parent, $index );

			foreach ( $def['fields'] as $key => $field ) {
				if ( ! in_array( $field['type'], array( 'image', 'gallery' ), true ) || empty( $attrs[ $key ] ) ) {
					continue;
				}
				$background = 'bg_image' === $key;
				$display    = $background ? ( 'section' === $node['type'] ? self::VIEWPORT : $box ) : $box;
				$size       = $background ? 'full' : ( isset( $def['fields']['size'] ) && ! empty( $attrs['size'] ) ? $attrs['size'] : 'large' );
				$values     = 'gallery' === $field['type'] ? brik_media_items( $attrs[ $key ], $size ) : array( $attrs[ $key ] );
				foreach ( $values as $value ) {
					$info = self::describe( $value, $size, $display );
					if ( $info ) {
						$out[] = array_merge(
							array(
								'node_id'   => $node['id'],
								'type'      => $node['type'],
								'field'     => $key,
								'resizable' => ! $background && 'image' === $field['type'] && isset( $def['fields']['size'] ),
							),
							$info
						);
					}
				}
			}

			if ( ! empty( $node['children'] ) ) {
				self::walk( $node['children'], $renderer, $box, $out, $node );
			}
		}
	}

	/** Approximate maximum width (px) a node is displayed at on desktop. */
	private static function box( array $node, array $attrs, $width, $parent, $index ) {
		if ( 'section' === $node['type'] ) {
			return ! empty( $attrs['full'] ) ? self::VIEWPORT : $width;
		}
		if ( 'row' === $node['type'] && ! empty( $attrs['full'] ) ) {
			return self::VIEWPORT;
		}
		if ( 'column' === $node['type'] && $parent && 'row' === $parent['type'] ) {
			$count = max( 1, count( (array) $parent['children'] ) );
			$parts = self::fractions( isset( $parent['attrs']['columns'] ) ? (string) $parent['attrs']['columns'] : '', $count );
			$frac  = isset( $parts[ $index ] ) ? $parts[ $index ] : 1 / $count;
			return (int) round( $width * $frac );
		}
		return $width;
	}

	/** Column fractions from a structure like "1/3,2/3" or "3". */
	public static function fractions( $structure, $count ) {
		$structure = trim( $structure );
		if ( preg_match( '/^\d+$/', $structure ) ) {
			$n = max( 1, (int) $structure );
			return array_fill( 0, $n, 1 / $n );
		}
		$out = array();
		foreach ( explode( ',', $structure ) as $part ) {
			if ( preg_match( '/^\s*(\d+)\s*\/\s*(\d+)\s*$/', $part, $m ) && (int) $m[2] > 0 ) {
				$out[] = (int) $m[1] / (int) $m[2];
			}
		}
		return $out ? $out : array_fill( 0, $count, 1 / $count );
	}

	/**
	 * Weight and dimensions of one image value (attachment, media URL or remote URL).
	 */
	public static function describe( $value, $size, $display ) {
		$id  = 0;
		$url = '';
		if ( is_numeric( $value ) ) {
			$id = (int) $value;
		} elseif ( is_array( $value ) ) {
			$id  = ! empty( $value['id'] ) ? (int) $value['id'] : 0;
			$url = isset( $value['url'] ) ? (string) $value['url'] : ( isset( $value['src'] ) ? (string) $value['src'] : '' );
		} else {
			$url = (string) $value;
		}

		$width  = 0;
		$height = 0;
		if ( $id && wp_attachment_is_image( $id ) ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( $src ) {
				list( $url, $width, $height ) = $src;
			}
		}
		if ( '' === $url ) {
			return null;
		}
		$file  = Media::local_path( $url );
		$bytes = $file ? (int) filesize( $file ) : 0;
		if ( $file && ! $width ) {
			$dims = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( $dims ) {
				list( $width, $height ) = $dims;
			}
		}

		$display   = max( 1, (int) $display );
		$oversized = $width > 2 * $display;
		$suggest   = $id && $oversized ? self::smaller_size( $id, $display ) : '';
		$ext       = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

		// Rough estimate: area scales with the square of the width, WebP saves ~30% on JPEG
		// and ~55% on PNG photos.
		$estimate = $bytes;
		if ( $bytes && $oversized && $width ) {
			$target   = $suggest ? self::size_width( $id, $suggest ) : 2 * $display;
			$estimate = (int) ( $bytes * min( 1, pow( max( 1, $target ) / $width, 2 ) ) );
		}
		if ( in_array( $ext, array( 'jpg', 'jpeg' ), true ) ) {
			$estimate = (int) ( $estimate * 0.7 );
		} elseif ( 'png' === $ext ) {
			$estimate = (int) ( $estimate * 0.45 );
		}

		return array(
			'id'        => $id,
			'url'       => $url,
			'name'      => wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			'local'     => (bool) $file,
			'host'      => $file ? '' : (string) wp_parse_url( $url, PHP_URL_HOST ),
			'bytes'     => $bytes,
			'width'     => (int) $width,
			'height'    => (int) $height,
			'size'      => $size,
			'display'   => $display,
			'format'    => $ext,
			'heavy'     => $bytes > self::HEAVY,
			'oversized' => $oversized,
			'suggest'   => $suggest,
			'estimate'  => $estimate,
		);
	}

	/** Smallest generated size of an attachment that still covers the displayed width. */
	public static function smaller_size( $id, $display ) {
		$meta = wp_get_attachment_metadata( $id );
		if ( empty( $meta['sizes'] ) ) {
			return '';
		}
		$best  = '';
		$best_w = PHP_INT_MAX;
		foreach ( $meta['sizes'] as $name => $info ) {
			$w = isset( $info['width'] ) ? (int) $info['width'] : 0;
			// Cropped thumbnails change the picture; only offer proportional sizes.
			$proportional = ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && ! empty( $info['height'] ) && abs( $info['width'] / max( 1, $info['height'] ) - $meta['width'] / $meta['height'] ) < 0.02;
			if ( $w >= $display && $w < $best_w && $proportional ) {
				$best   = $name;
				$best_w = $w;
			}
		}
		return $best;
	}

	private static function size_width( $id, $size ) {
		$src = wp_get_attachment_image_src( $id, $size );
		return $src ? (int) $src[1] : 0;
	}
}
