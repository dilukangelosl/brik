<?php
namespace Brik\Perf;

use Brik\Data;
use Brik\Fields;
use Brik\Modules;
use Brik\Renderer;
use Brik\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * "Clean page": finds dead weight in a tree and removes it without changing what visitors see.
 *
 * Every fix is conservative: structure is only unwrapped or removed when it carries no settings
 * that could affect layout, and attributes are only dropped when they equal what the element
 * would get anyway. Running the cleaner twice gives the same tree (it repeats until stable).
 */
final class Cleaner {

	const KINDS = array( 'defaults', 'custom_css', 'hidden', 'empty_module', 'duplicate', 'wrapper', 'empty_structure', 'image_size' );

	/** Attributes that don't change how an empty container looks. */
	const INERT = array( 'admin_label', 'label', 'columns', 'gap', 'row_gap', 'justify', 'align_items', 'items', 'layout', 'sizing', 'wrap', 'reverse', 'direction', 'preset' );

	/** Keys whose responsive values modules read directly, so a copy of the desktop value still matters. */
	const RESPONSIVE_KEEP = array( 'columns', 'per_view', 'ratio', 'direction', 'sizing' );

	private $fixes = array();

	private $kinds;

	private $images = array();

	/**
	 * @param array $kinds Fix kinds to apply (default: all).
	 */
	public function __construct( array $kinds = array() ) {
		$this->kinds = $kinds ? array_values( array_intersect( self::KINDS, $kinds ) ) : self::KINDS;
	}

	/**
	 * Clean a tree.
	 *
	 * @return array [ tree, fixes ]
	 */
	public function clean( array $tree, $post_id = 0 ) {
		$this->fixes = array();
		if ( in_array( 'image_size', $this->kinds, true ) ) {
			foreach ( Images::inspect( $tree, $post_id ) as $img ) {
				if ( $img['resizable'] && $img['suggest'] && $img['suggest'] !== $img['size'] ) {
					$this->images[ $img['node_id'] ] = $img;
				}
			}
		}
		// Each pass can expose more work (an unwrapped row leaves an empty column…).
		for ( $pass = 0; $pass < 10; $pass++ ) {
			$before = wp_json_encode( $tree );
			$tree   = $this->nodes( $tree, null );
			if ( wp_json_encode( $tree ) === $before ) {
				break;
			}
		}
		return array( $tree, $this->fixes );
	}

	private function on( $kind ) {
		return in_array( $kind, $this->kinds, true );
	}

	private function fix( $kind, $node, $message, array $extra = array() ) {
		$this->fixes[] = array_merge(
			array(
				'kind'    => $kind,
				'node_id' => isset( $node['id'] ) ? $node['id'] : null,
				'type'    => isset( $node['type'] ) ? $node['type'] : null,
				'message' => $message,
			),
			$extra
		);
	}

	private function nodes( array $nodes, $parent ) {
		$out = array();
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['type'] ) ) {
				continue;
			}
			$node['attrs'] = isset( $node['attrs'] ) && is_array( $node['attrs'] ) ? $node['attrs'] : array();
			$def           = Modules::get( $node['type'] );

			if ( $def ) {
				$node['attrs'] = $this->attrs( $node, $def );
			}

			// Hidden columns still hold their grid track, so they stay.
			if ( $this->on( 'hidden' ) && 'column' !== $node['type'] && self::hidden_everywhere( $node['attrs'] ) ) {
				$this->fix( 'hidden', $node, __( 'Removed an element hidden on desktop, tablet and phone.', 'brik-builder' ) );
				continue;
			}
			if ( $this->on( 'empty_module' ) && self::empty_module( $node ) ) {
				$this->fix( 'empty_module', $node, __( 'Removed an empty heading or text element.', 'brik-builder' ) );
				continue;
			}

			if ( ! empty( $node['children'] ) ) {
				$node['children'] = $this->nodes( $node['children'], $node );
			}

			// A one-column row nested in a column, with no settings of its own, is just two
			// extra wrappers: lift its content into the outer column.
			if ( $this->on( 'wrapper' ) && $parent && 'column' === $parent['type'] && 'row' === $node['type'] && self::bare_row( $node ) ) {
				$this->fix( 'wrapper', $node, __( 'Unwrapped a nested one-column row without settings.', 'brik-builder' ), array( 'nodes' => 2 ) );
				foreach ( $node['children'][0]['children'] as $child ) {
					$out[] = $child;
				}
				continue;
			}

			if ( $this->on( 'empty_structure' ) && self::empty_structure( $node, $parent ) ) {
				$this->fix( 'empty_structure', $node, sprintf( /* translators: %s: element type */ __( 'Removed an empty %s.', 'brik-builder' ), $node['type'] ) );
				continue;
			}

			if ( $this->on( 'duplicate' ) && $out && self::same( end( $out ), $node ) && ! in_array( $node['type'], array( 'section', 'row', 'column' ), true ) ) {
				$this->fix( 'duplicate', $node, __( 'Removed an exact copy of the element before it.', 'brik-builder' ) );
				continue;
			}

			if ( $this->on( 'image_size' ) && isset( $this->images[ $node['id'] ] ) ) {
				$img                   = $this->images[ $node['id'] ];
				$node['attrs']['size'] = $img['suggest'];
				unset( $this->images[ $node['id'] ] );
				$this->fix(
					'image_size',
					$node,
					/* translators: 1: file, 2: old size, 3: new size */
					sprintf( __( 'Switched %1$s from "%2$s" to the smaller "%3$s" size.', 'brik-builder' ), $img['name'], $img['size'], $img['suggest'] ),
					array( 'bytes' => max( 0, $img['bytes'] - self::size_bytes( $img['id'], $img['suggest'] ) ) )
				);
			}

			$out[] = $node;
		}

		// Rows must keep at least one column.
		if ( $parent && 'row' === $parent['type'] && ! $out && $nodes ) {
			return array( $nodes[0] );
		}
		return $out;
	}

	/** Drop attributes that equal what the element gets anyway, and empty custom CSS. */
	private function attrs( array $node, array $def ) {
		$attrs = $node['attrs'];
		if ( $this->on( 'custom_css' ) && isset( $attrs['custom_css'] ) && '' === trim( Css::strip_comments( (string) $attrs['custom_css'] ) ) ) {
			unset( $attrs['custom_css'] );
			$this->fix( 'custom_css', $node, __( 'Removed empty custom CSS.', 'brik-builder' ) );
		}
		if ( ! $this->on( 'defaults' ) ) {
			return $attrs;
		}

		$base = Modules::defaults( $node['type'] );
		if ( ! empty( $attrs['preset'] ) ) {
			$preset = Settings::preset( $node['type'], $attrs['preset'] );
			$base   = $preset ? array_merge( $base, $preset ) : $base;
		} elseif ( $preset = Settings::default_preset( $node['type'] ) ) {
			$base = array_merge( $base, $preset );
		}

		$removed = array();
		foreach ( $attrs as $key => $value ) {
			if ( 'preset' === $key ) {
				continue;
			}
			$parts = explode( '@', $key, 2 );
			$field = isset( $def['fields'][ $parts[0] ] ) ? $def['fields'][ $parts[0] ] : null;
			if ( ! $field ) {
				continue;
			}
			if ( 1 === count( $parts ) ) {
				// Same as the default (or empty where the default is empty).
				$default = isset( $base[ $key ] ) ? $base[ $key ] : '';
				if ( self::equal( $value, $default ) ) {
					$removed[] = $key;
				}
				continue;
			}
			// key@tablet equal to desktop, key@mobile equal to what tablet resolves to.
			if ( empty( $field['css'] ) || in_array( $parts[0], self::RESPONSIVE_KEEP, true ) || ! in_array( $parts[1], array( 'tablet', 'mobile' ), true ) ) {
				continue;
			}
			$inherit = 'tablet' === $parts[1]
				? self::get( $attrs, $base, $parts[0] )
				: ( isset( $attrs[ $parts[0] . '@tablet' ] ) && ! in_array( $parts[0] . '@tablet', $removed, true ) ? $attrs[ $parts[0] . '@tablet' ] : self::get( $attrs, $base, $parts[0] ) );
			if ( null !== $inherit && self::equal( $value, $inherit ) ) {
				$removed[] = $key;
			}
		}
		if ( $removed ) {
			foreach ( $removed as $key ) {
				unset( $attrs[ $key ] );
			}
			$this->fix(
				'defaults',
				$node,
				/* translators: %s: attribute names */
				sprintf( __( 'Removed settings that repeat the default: %s.', 'brik-builder' ), implode( ', ', $removed ) ),
				array( 'attrs' => $removed )
			);
		}
		return $attrs;
	}

	private static function get( array $attrs, array $base, $key ) {
		if ( isset( $attrs[ $key ] ) ) {
			return $attrs[ $key ];
		}
		return isset( $base[ $key ] ) ? $base[ $key ] : null;
	}

	/** Strict equality, the way the renderer would see it. */
	private static function equal( $a, $b ) {
		if ( is_array( $a ) || is_array( $b ) ) {
			return is_array( $a ) && is_array( $b ) && wp_json_encode( $a ) === wp_json_encode( $b );
		}
		return gettype( $a ) === gettype( $b ) && $a === $b;
	}

	private static function hidden_everywhere( array $attrs ) {
		$hide = isset( $attrs['hide_on'] ) ? (array) $attrs['hide_on'] : array();
		return ! array_diff( array( 'desktop', 'tablet', 'mobile' ), $hide );
	}

	private static function empty_module( array $node ) {
		$a = $node['attrs'];
		if ( 'heading' === $node['type'] && array_key_exists( 'text', $a ) ) {
			return self::blank( $a['text'] );
		}
		if ( 'text' === $node['type'] && array_key_exists( 'content', $a ) ) {
			return self::blank( $a['content'] );
		}
		return false;
	}

	private static function blank( $html ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' );
		return '' === trim( str_replace( "\xc2\xa0", ' ', $text ) ) && false === stripos( (string) $html, '<img' );
	}

	/** Only settings from the inert list (or none at all). */
	private static function bare( array $attrs ) {
		foreach ( $attrs as $key => $value ) {
			if ( ! in_array( strtok( $key, '@' ), self::INERT, true ) ) {
				return false;
			}
		}
		return true;
	}

	private static function bare_row( array $row ) {
		if ( empty( $row['children'] ) || 1 !== count( $row['children'] ) ) {
			return false;
		}
		$column = $row['children'][0];
		foreach ( $row['attrs'] as $key => $value ) {
			if ( ! ( 'columns' === strtok( $key, '@' ) && in_array( (string) $value, array( '', '1' ), true ) ) ) {
				return false;
			}
		}
		return 'column' === $column['type'] && empty( $column['attrs'] ) && ! empty( $column['children'] );
	}

	/**
	 * Sections and rows with nothing in them, and columns that are a row's only column.
	 * An empty column next to others keeps its place (it shapes the grid).
	 */
	private static function empty_structure( array $node, $parent ) {
		if ( ! in_array( $node['type'], array( 'section', 'row', 'column' ), true ) || ! self::bare( $node['attrs'] ) ) {
			return false;
		}
		if ( 'column' === $node['type'] ) {
			return false; // Handled with its row: a row whose columns are all empty and bare goes.
		}
		if ( 'row' === $node['type'] ) {
			foreach ( (array) $node['children'] as $column ) {
				if ( ! empty( $column['children'] ) || ! self::bare( $column['attrs'] ) ) {
					return false;
				}
			}
			return true;
		}
		return empty( $node['children'] );
	}

	private static function same( $a, $b ) {
		return wp_json_encode( self::strip_ids( $a ) ) === wp_json_encode( self::strip_ids( $b ) );
	}

	private static function strip_ids( $node ) {
		unset( $node['id'] );
		if ( ! empty( $node['children'] ) ) {
			$node['children'] = array_map( array( __CLASS__, 'strip_ids' ), $node['children'] );
		}
		return $node;
	}

	private static function size_bytes( $id, $size ) {
		$src  = wp_get_attachment_image_src( $id, $size );
		$file = $src ? Media::local_path( $src[0] ) : '';
		return $file ? (int) filesize( $file ) : 0;
	}

	/* ---------------------------------------------------------------------
	 * Page-level run with savings.
	 * ------------------------------------------------------------------- */

	/**
	 * Clean a post's tree (or the given one) and measure what it saves.
	 *
	 * @param int        $post_id Post.
	 * @param array|null $tree    Tree to clean instead of the saved one.
	 * @param bool       $apply   Save the result.
	 * @param array      $kinds   Fix kinds (default all).
	 */
	public static function run( $post_id, $tree = null, $apply = false, array $kinds = array() ) {
		$tree    = Data::normalize( null === $tree ? Data::get( $post_id ) : $tree );
		$cleaner = new self( $kinds );
		list( $clean, $fixes ) = $cleaner->clean( $tree, $post_id );

		$page        = Data::page_settings( $post_id );
		$page_change = isset( $page['custom_css'] ) && '' !== $page['custom_css'] && '' === trim( Css::strip_comments( $page['custom_css'] ) ) && $cleaner->on( 'custom_css' );
		if ( $page_change ) {
			$fixes[]            = array(
				'kind'    => 'custom_css',
				'node_id' => null,
				'type'    => 'page',
				'message' => __( 'Removed empty page custom CSS.', 'brik-builder' ),
			);
			$page['custom_css'] = '';
		}

		$before  = self::measure( $post_id, $tree );
		$after   = self::measure( $post_id, $clean );
		$image   = 0;
		foreach ( $fixes as $fix ) {
			$image += isset( $fix['bytes'] ) ? $fix['bytes'] : 0;
		}
		$savings = array(
			'css'    => max( 0, $before['css'] - $after['css'] ),
			'js'     => max( 0, $before['js'] - $after['js'] ),
			'dom'    => max( 0, $before['dom'] - $after['dom'] ),
			'images' => $image,
			'html'   => max( 0, $before['html'] - $after['html'] ),
		);

		$saved = false;
		if ( $apply && $fixes ) {
			$clean = Data::save( $post_id, $clean, $page_change ? $page : null );
			$saved = true;
		}

		return array(
			'post_id'  => (int) $post_id,
			'applied'  => $saved,
			'changed'  => (bool) $fixes,
			'fixes'    => $fixes,
			'counts'   => array_count_values( wp_list_pluck( $fixes, 'kind' ) ),
			'savings'  => $savings,
			'presets'  => self::unused_presets(),
			'tree'     => $clean,
			'page'     => (object) $page,
		);
	}

	/** Bytes of element CSS and module JS, element count and markup size for a tree. */
	private static function measure( $post_id, array $tree ) {
		$renderer = new Renderer( $post_id );
		$html     = $renderer->render_root( $tree );
		$js       = 0;
		foreach ( Scripts::needed( $html ) as $name ) {
			$js += Scripts::bytes( $name );
		}
		foreach ( array_keys( $renderer->scripts ) as $name ) {
			$js += Scripts::fx_bytes( $name );
		}
		// Library rules the page needs also shrink when elements go.
		$usage = ( new Usage() )->add_html( $html );
		$lib   = strlen( Css::serialize( Css::shake( PageCss::library( 'frontend' ), $usage ) ) );
		return array(
			'css'  => strlen( $renderer->style->css() ) + $lib,
			'js'   => $js,
			'dom'  => (int) preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)(?=[\s>\/])/', $html ),
			'html' => strlen( $html ),
		);
	}

	/**
	 * Saved presets no element uses anywhere (report only: deleting presets is a design decision).
	 */
	public static function unused_presets() {
		$presets = (array) Settings::get( 'presets' );
		if ( ! $presets ) {
			return array();
		}
		global $wpdb;
		$used  = array();
		$types = array();
		$rows  = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", Data::META ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $rows as $raw ) {
			$tree = json_decode( (string) $raw, true );
			if ( ! is_array( $tree ) ) {
				$tree = maybe_unserialize( $raw );
			}
			if ( is_array( $tree ) ) {
				self::collect_presets( $tree, $used, $types );
			}
		}
		$out = array();
		foreach ( $presets as $type => $list ) {
			foreach ( (array) $list as $id => $preset ) {
				// A default preset applies to every element of its type.
				$in_use = isset( $used[ $type . ':' . $id ] ) || ( ! empty( $preset['default'] ) && isset( $types[ $type ] ) );
				if ( ! $in_use ) {
					$out[] = array(
						'type' => $type,
						'id'   => $id,
						'name' => isset( $preset['name'] ) ? $preset['name'] : $id,
					);
				}
			}
		}
		return $out;
	}

	private static function collect_presets( array $nodes, array &$used, array &$types ) {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) || empty( $node['type'] ) ) {
				continue;
			}
			$types[ $node['type'] ] = true;
			if ( ! empty( $node['attrs']['preset'] ) ) {
				$used[ $node['type'] . ':' . $node['attrs']['preset'] ] = true;
			}
			if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
				self::collect_presets( $node['children'], $used, $types );
			}
		}
	}
}
