<?php
/**
 * "No lock-in": convert a Brik page to core blocks or to static HTML + CSS, keeping a
 * backup of the Brik data so the conversion can be undone.
 *
 * @package Brik
 * @author  Diluk Angelo
 */

namespace Brik\Editor;

use Brik\Data;
use Brik\Fonts;
use Brik\Modules;
use Brik\Renderer;
use Brik\Settings;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class Export {

	const META_BACKUP = '_brik_export_backup';

	const MODES = array( 'blocks', 'static' );

	/** Static exports span the full width even inside a theme's narrow content column. */
	const BREAKOUT = '.brik-export.alignfull{width:100vw;max-width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw)}html:has(.brik-export){overflow-x:clip}';

	/** Brik module types with a core block equivalent. */
	const MAPPED = array( 'section', 'row', 'column', 'heading', 'text', 'button', 'button_group', 'image', 'gallery', 'video', 'divider', 'spacer', 'accordion', 'code' );

	private $post_id;

	private $mode;

	/** @var Renderer */
	private $renderer;

	/** Rendered markup of every node, keyed by id (data-brik-* attributes removed). */
	private $html = array();

	private $tokens = array();

	private $stats = array(
		'mapped'   => 0,
		'fallback' => 0,
		'types'    => array(),
		'blocks'   => 0,
	);

	/** Markup that keeps Brik classes (fallbacks, static) and needs Brik CSS. */
	private $fallback_html = '';

	private function __construct( $post_id, $mode ) {
		$this->post_id = (int) $post_id;
		$this->mode    = $mode;
	}

	/* ---------------------------------------------------------------------
	 * Public API.
	 * ------------------------------------------------------------------- */

	/**
	 * Build the converted content without saving anything.
	 *
	 * @return array|WP_Error { content, css, stats, preview }
	 */
	public static function build( $post_id, $mode = 'blocks' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'brik_not_found', __( 'Post not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		if ( ! in_array( $mode, self::MODES, true ) ) {
			return new WP_Error( 'brik_export_mode', __( 'Unknown export mode.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		$tree = Data::get( $post->ID );
		if ( ! $tree ) {
			return new WP_Error( 'brik_export_empty', __( 'This page has no Brik content to convert.', 'brik-builder' ), array( 'status' => 400 ) );
		}
		$self = new self( $post->ID, $mode );
		return $self->run( $tree, Data::page_settings( $post->ID ) );
	}

	/**
	 * Convert the page: back up Brik data, write the new content and switch Brik off.
	 */
	public static function convert( $post_id, $mode = 'blocks' ) {
		$built = self::build( $post_id, $mode );
		if ( is_wp_error( $built ) ) {
			return $built;
		}
		$post = get_post( $post_id );

		$backup = array(
			'time'    => time(),
			'mode'    => $mode,
			'user'    => get_current_user_id(),
			'tree'    => Data::get( $post_id ),
			'page'    => Data::page_settings( $post_id ),
			'content' => $post->post_content,
		);
		update_post_meta( $post_id, self::META_BACKUP, wp_slash( wp_json_encode( $backup ) ) );

		// Also a named entry in version history when that feature is available.
		if ( class_exists( '\\Brik\\Versions\\Versions' ) && method_exists( '\\Brik\\Versions\\Versions', 'record' ) ) {
			try {
				\Brik\Versions\Versions::record( $post_id, $backup['tree'], $backup['page'], 'builder', 'live', __( 'Before removing the builder', 'brik-builder' ) );
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				// History is a convenience; the meta backup above is what restore() uses.
			}
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( $built['content'] ),
			),
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}
		update_post_meta( $post_id, Data::META_ENABLED, 0 );
		do_action( 'brik/page_converted', $post_id, $mode );

		return array(
			'converted' => true,
			'mode'      => $mode,
			'stats'     => $built['stats'],
			'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
			'view_url'  => get_permalink( $post_id ),
		);
	}

	/**
	 * Bring Brik back from the backup taken by convert().
	 */
	public static function restore( $post_id ) {
		$backup = self::backup( $post_id );
		if ( ! $backup ) {
			return new WP_Error( 'brik_export_no_backup', __( 'There is no Brik backup for this page.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		// Data::save() re-enables Brik and rewrites the static copy in post_content.
		Data::save( $post_id, (array) $backup['tree'], (array) $backup['page'] );
		delete_post_meta( $post_id, self::META_BACKUP );
		do_action( 'brik/page_restored', $post_id );
		return array(
			'restored' => true,
			'enabled'  => Data::enabled( $post_id ),
		);
	}

	public static function backup( $post_id ) {
		$raw = get_post_meta( $post_id, self::META_BACKUP, true );
		$val = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return is_array( $val ) && isset( $val['tree'] ) ? $val : null;
	}

	public static function status( $post_id ) {
		$backup = self::backup( $post_id );
		return array(
			'enabled' => Data::enabled( $post_id ),
			'backup'  => $backup ? array(
				'time' => (int) $backup['time'],
				'mode' => (string) $backup['mode'],
			) : null,
		);
	}

	/* ---------------------------------------------------------------------
	 * Conversion.
	 * ------------------------------------------------------------------- */

	private function run( array $tree, array $page ) {
		$this->renderer          = new Renderer( $this->post_id );
		$this->renderer->capture = array_fill_keys( wp_list_pluck( Data::flatten( $tree ), 'id' ), true );
		$this->tokens            = self::token_map( ! empty( $page['dark'] ) );

		$post = get_post( $this->post_id );
		if ( $post ) {
			$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $post );
		}

		$root = self::clean( $this->renderer->render_root( $tree, 'brik-content' ) );
		foreach ( $this->renderer->captured as $id => $markup ) {
			$this->html[ $id ] = self::clean( $markup );
		}

		$element_css = $this->renderer->style->css() . ( isset( $page['custom_css'] ) ? (string) $page['custom_css'] : '' );
		$fonts       = Fonts::url( array_merge( Settings::fonts(), $this->renderer->style->fonts() ) );
		$head        = $fonts ? '<link rel="stylesheet" href="' . esc_url( $fonts ) . '">' : '';
		$dark_class  = ! empty( $page['dark'] ) ? ' dark' : '';

		if ( 'static' === $this->mode ) {
			$classes                 = Css::markup_classes( $root ) + array( 'brik' => true );
			$css                     = Settings::css() . $this->framework_css( $classes ) . $element_css . self::BREAKOUT;
			// has-brik-builder: the Brik theme leaves content styles off markup inside it.
			$markup                  = $head . '<style id="brik-export-css">' . self::style_text( $css ) . '</style>' . "\n" . '<div class="has-brik-builder brik-export alignfull' . $dark_class . '">' . $root . '</div>';
			$content                 = self::serialize( array( self::html_block( $markup ) ) );
			$this->stats['fallback'] = count( $this->html );
			$this->stats['blocks']   = 1;
		} else {
			$blocks = array();
			foreach ( $tree as $node ) {
				$blocks = array_merge( $blocks, $this->convert_node( $node ) );
			}
			// Element-level styles still apply through the brik-n-* classes on the blocks.
			$css = Settings::css() . preg_replace( '/\.brik \.brik-n-/', ':root .brik-n-', $element_css );
			if ( '' !== $this->fallback_html ) {
				$css .= $this->framework_css( Css::markup_classes( $this->fallback_html ) + array( 'brik' => true ) );
			}
			$style = self::html_block( $head . '<style id="brik-export-css">' . self::style_text( $css ) . '</style>' );
			array_unshift( $blocks, $style );
			$content = self::serialize( $blocks );
		}

		wp_reset_postdata();

		return array(
			'content' => $content,
			'css'     => $css,
			'stats'   => $this->stats,
			'preview' => $this->preview( $content ),
		);
	}

	/**
	 * Convert one node to a list of block arrays.
	 */
	private function convert_node( array $node ) {
		if ( empty( $node['type'] ) || empty( $node['id'] ) ) {
			return array();
		}
		// Not rendered (display conditions, unknown module): nothing to convert.
		if ( ! isset( $this->html[ $node['id'] ] ) ) {
			return array();
		}
		$def = Modules::get( $node['type'] );
		if ( ! $def ) {
			return array();
		}
		$a      = $this->renderer->resolve_attrs( wp_parse_args( $node, array( 'attrs' => array(), 'children' => array() ) ), $def );
		$method = 'map_' . $node['type'];
		$blocks = in_array( $node['type'], self::MAPPED, true ) && method_exists( $this, $method ) ? $this->$method( $node, $a ) : null;

		if ( null === $blocks ) {
			++$this->stats['fallback'];
			$this->stats['types'][ $node['type'] ] = isset( $this->stats['types'][ $node['type'] ] ) ? $this->stats['types'][ $node['type'] ] + 1 : 1;
			$this->fallback_html                  .= $this->html[ $node['id'] ];
			$blocks                                = array( self::html_block( '<div class="has-brik-builder"><div class="brik">' . $this->html[ $node['id'] ] . '</div></div>' ) );
		} else {
			++$this->stats['mapped'];
		}
		return $blocks;
	}

	private function children( array $node ) {
		$out = array();
		foreach ( isset( $node['children'] ) ? (array) $node['children'] : array() as $child ) {
			$out = array_merge( $out, $this->convert_node( $child ) );
		}
		return $out;
	}

	/* --- Structure ---------------------------------------------------- */

	private function map_section( array $node, array $a ) {
		$attrs = array(
			'tagName'   => in_array( $a['tag'], array( 'section', 'header', 'footer', 'main', 'aside', 'article', 'div' ), true ) ? $a['tag'] : 'section',
			'align'     => 'full',
			'className' => 'brik-n-' . $node['id'],
			'layout'    => array(
				'type'        => 'constrained',
				'contentSize' => Settings::get( 'container' ) ? (string) Settings::get( 'container' ) : '1200px',
			),
		);
		$style = $this->box_style( $node, $a, ! empty( $a['dark'] ) );
		if ( ! $this->has_responsive( $node, 'padding' ) ) {
			$style['spacing']['padding'] = self::sides( '' !== (string) $a['padding'] ? $a['padding'] : '5rem 1.5rem' );
		}
		if ( $style ) {
			$attrs['style'] = $style;
		}
		return array( $this->group( $attrs, $this->children( $node ) ) );
	}

	private function map_row( array $node, array $a ) {
		$kids = isset( $node['children'] ) ? array_values( (array) $node['children'] ) : array();
		if ( 1 === count( $kids ) ) {
			// One column: no need for a columns block.
			$inner = $this->children( $kids[0] );
			if ( ! $this->has_own_style( $node ) && ! $this->has_own_style( $kids[0] ) ) {
				return $inner;
			}
			return array( $this->group( array( 'className' => 'brik-n-' . $node['id'] . ' brik-n-' . $kids[0]['id'] ), $inner ) );
		}
		$widths  = self::column_widths( (string) $a['columns'], count( $kids ) );
		$columns = array();
		foreach ( $kids as $i => $col ) {
			if ( ! isset( $this->html[ $col['id'] ] ) ) {
				continue;
			}
			++$this->stats['mapped'];
			$attrs = array();
			$open  = '<div class="' . esc_attr( 'wp-block-column brik-n-' . $col['id'] ) . '"';
			if ( isset( $widths[ $i ] ) ) {
				$attrs['width'] = $widths[ $i ];
				$open          .= ' style="flex-basis:' . esc_attr( $widths[ $i ] ) . '"';
			}
			$attrs['className'] = 'brik-n-' . $col['id'];
			$columns[]          = self::block( 'core/column', $attrs, $open . '>', $this->children( $col ), '</div>' );
		}
		return array( self::block( 'core/columns', array( 'className' => 'brik-n-' . $node['id'] ), '<div class="' . esc_attr( 'wp-block-columns brik-n-' . $node['id'] ) . '">', $columns, '</div>' ) );
	}

	private function map_column( array $node, array $a ) {
		// A column outside a row (shouldn't happen after normalize): keep its content.
		return $this->children( $node );
	}

	/* --- Content ------------------------------------------------------ */

	private function map_heading( array $node, array $a ) {
		$text = (string) $a['text'];
		if ( ! empty( $a['link']['url'] ) ) {
			$text = '<a href="' . esc_url( $a['link']['url'] ) . '">' . $text . '</a>';
		}
		$cls = 'brik-n-' . $node['id'];
		if ( in_array( $a['level'], array( 'p', 'div' ), true ) ) {
			return array( self::paragraph( $text, $cls ) );
		}
		$level = (int) substr( (string) $a['level'], 1 );
		$level = $level >= 1 && $level <= 6 ? $level : 2;
		$attrs = array();
		if ( 2 !== $level ) {
			$attrs['level'] = $level;
		}
		$classes = array( 'wp-block-heading' );
		$align   = $this->plain( $node, 'text_align' );
		if ( in_array( $align, array( 'left', 'center', 'right' ), true ) ) {
			$attrs['textAlign'] = $align;
			$classes[]          = 'has-text-align-' . $align;
		}
		$attrs['className'] = $cls;
		$classes[]          = $cls;
		return array( self::block( 'core/heading', $attrs, '<h' . $level . ' class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $text . '</h' . $level . '>' ) );
	}

	private function map_text( array $node, array $a ) {
		$blocks = self::rich_blocks( brik_rich( (string) $a['content'] ) );
		return $this->wrap( $node, $blocks );
	}

	private function map_code( array $node, array $a ) {
		return array( self::html_block( (string) $a['code'] ) );
	}

	private function map_button( array $node, array $a ) {
		$btn = $this->button_block(
			array(
				'text'    => $a['text'],
				'link'    => $a['link'],
				'variant' => $a['variant'],
			)
		);
		return array( self::block( 'core/buttons', array( 'className' => 'brik-n-' . $node['id'] ), '<div class="' . esc_attr( 'wp-block-buttons brik-n-' . $node['id'] ) . '">', array( $btn ), '</div>' ) );
	}

	private function map_button_group( array $node, array $a ) {
		$buttons = array();
		foreach ( is_array( $a['buttons'] ) ? $a['buttons'] : array() as $item ) {
			if ( is_array( $item ) ) {
				$buttons[] = $this->button_block( $item );
			}
		}
		if ( ! $buttons ) {
			return array();
		}
		return array( self::block( 'core/buttons', array( 'className' => 'brik-n-' . $node['id'] ), '<div class="' . esc_attr( 'wp-block-buttons brik-n-' . $node['id'] ) . '">', $buttons, '</div>' ) );
	}

	private function button_block( array $b ) {
		$variant = isset( $b['variant'] ) ? (string) $b['variant'] : 'default';
		$link    = isset( $b['link'] ) && is_array( $b['link'] ) ? $b['link'] : array();
		$attrs   = array();
		$wrap    = array( 'wp-block-button' );
		$a_cls   = array( 'wp-block-button__link' );
		$style   = '';
		if ( in_array( $variant, array( 'outline', 'ghost', 'link' ), true ) ) {
			$attrs['className'] = 'is-style-outline';
			$wrap[]             = 'is-style-outline';
		} else {
			$map = array(
				'default'     => array( 'primary', 'primary-foreground' ),
				'secondary'   => array( 'secondary', 'secondary-foreground' ),
				'destructive' => array( 'destructive', 'primary-foreground' ),
			);
			$pair = isset( $map[ $variant ] ) ? $map[ $variant ] : $map['default'];
			$bg   = $this->color( 'var(--' . $pair[0] . ')' );
			$fg   = $this->color( 'var(--' . $pair[1] . ')' );
			if ( $bg && $fg ) {
				$attrs['style'] = array(
					'color' => array(
						'background' => $bg,
						'text'       => $fg,
					),
				);
				$a_cls[]        = 'has-text-color';
				$a_cls[]        = 'has-background';
				$style          = ' style="' . esc_attr( 'color:' . $fg . ';background-color:' . $bg ) . '"';
			}
		}
		$a_cls[] = 'wp-element-button';
		$extra   = '';
		if ( ! empty( $link['url'] ) ) {
			$extra .= ' href="' . esc_url( $link['url'] ) . '"';
		}
		$rel = array();
		if ( ! empty( $link['new_tab'] ) ) {
			$extra .= ' target="_blank"';
			$rel[]  = 'noreferrer noopener';
		}
		if ( ! empty( $link['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( $rel ) {
			$extra .= ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"';
		}
		$text = isset( $b['text'] ) ? (string) $b['text'] : '';
		$html = '<div class="' . esc_attr( implode( ' ', $wrap ) ) . '"><a class="' . esc_attr( implode( ' ', $a_cls ) ) . '"' . $extra . $style . '>' . $text . '</a></div>';
		return self::block( 'core/button', $attrs, $html );
	}

	/* --- Media -------------------------------------------------------- */

	private function map_image( array $node, array $a ) {
		$image = $a['image'];
		if ( is_string( $image ) ) {
			$image = array( 'url' => $image );
		}
		$url = brik_image_url( $image, 'large' );
		if ( ! $url ) {
			return array();
		}
		$alt     = '' !== (string) $a['alt'] ? (string) $a['alt'] : ( isset( $image['alt'] ) ? (string) $image['alt'] : '' );
		$id      = ! empty( $image['id'] ) ? (int) $image['id'] : 0;
		$link    = ! empty( $a['link']['url'] ) && 'link' === $a['action'] ? $a['link'] : null;
		$caption = (string) $a['caption'];
		return array( $this->image_block( $url, $alt, $id, $caption, $link, 'brik-n-' . $node['id'] ) );
	}

	private function image_block( $url, $alt, $id, $caption = '', $link = null, $class = '' ) {
		$attrs   = array();
		$classes = array( 'wp-block-image' );
		$img     = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"';
		if ( $id ) {
			$attrs['id']       = $id;
			$attrs['sizeSlug'] = 'large';
			$classes[]         = 'size-large';
			$img              .= ' class="wp-image-' . $id . '"';
		}
		$img .= '/>';
		if ( $link ) {
			$attrs['linkDestination'] = 'custom';
			$target                   = ! empty( $link['new_tab'] ) ? ' target="_blank" rel="noreferrer noopener"' : '';
			$img                      = '<a href="' . esc_url( $link['url'] ) . '"' . $target . '>' . $img . '</a>';
		} else {
			$attrs['linkDestination'] = 'none';
		}
		if ( $class ) {
			$attrs['className'] = $class;
			$classes[]          = $class;
		}
		$cap = '' !== trim( $caption ) ? '<figcaption class="wp-element-caption">' . $caption . '</figcaption>' : '';
		return self::block( 'core/image', $attrs, '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $img . $cap . '</figure>' );
	}

	private function map_gallery( array $node, array $a ) {
		$inner = array();
		foreach ( is_array( $a['images'] ) ? $a['images'] : array() as $image ) {
			$image = is_string( $image ) ? array( 'url' => $image ) : (array) $image;
			$url   = brik_image_url( $image, 'large' );
			if ( $url ) {
				$inner[] = $this->image_block( $url, isset( $image['alt'] ) ? (string) $image['alt'] : '', ! empty( $image['id'] ) ? (int) $image['id'] : 0 );
			}
		}
		if ( ! $inner ) {
			return array();
		}
		$attrs   = array();
		$cols    = (int) $a['columns'];
		$classes = array( 'wp-block-gallery', 'has-nested-images' );
		if ( $cols > 0 ) {
			$attrs['columns'] = $cols;
			$classes[]        = 'columns-' . $cols;
		} else {
			$classes[] = 'columns-default';
		}
		$classes[]          = 'is-cropped';
		$attrs['linkTo']    = 'none';
		$attrs['className'] = 'brik-n-' . $node['id'];
		$classes[]          = 'brik-n-' . $node['id'];
		return array( self::block( 'core/gallery', $attrs, '<figure class="' . esc_attr( implode( ' ', $classes ) ) . '">', $inner, '</figure>' ) );
	}

	private function map_video( array $node, array $a ) {
		$src = 'file' === $a['source'] ? $a['file'] : $a['url'];
		$url = is_array( $src ) ? ( isset( $src['url'] ) ? $src['url'] : '' ) : (string) $src;
		if ( '' === $url ) {
			return array();
		}
		$cls      = 'brik-n-' . $node['id'];
		$provider = preg_match( '#(youtube\.com|youtu\.be)#i', $url ) ? 'youtube' : ( preg_match( '#vimeo\.com#i', $url ) ? 'vimeo' : '' );
		if ( $provider ) {
			$extra   = 'wp-embed-aspect-16-9 wp-has-aspect-ratio';
			$attrs   = array(
				'url'              => $url,
				'type'             => 'video',
				'providerNameSlug' => $provider,
				'responsive'       => true,
				'className'        => $extra . ' ' . $cls,
			);
			$classes = 'wp-block-embed is-type-video is-provider-' . $provider . ' wp-block-embed-' . $provider . ' ' . $extra . ' ' . $cls;
			return array( self::block( 'core/embed', $attrs, '<figure class="' . esc_attr( $classes ) . '"><div class="wp-block-embed__wrapper">' . "\n" . esc_url( $url ) . "\n" . '</div></figure>' ) );
		}
		$attrs = array();
		if ( is_array( $src ) && ! empty( $src['id'] ) ) {
			$attrs['id'] = (int) $src['id'];
		}
		$attrs['className'] = $cls;
		$flags              = '';
		foreach ( array( 'autoplay', 'loop', 'muted' ) as $flag ) {
			if ( ! empty( $a[ $flag ] ) ) {
				$flags .= ' ' . $flag;
			}
		}
		if ( '' === (string) $a['controls'] || ! empty( $a['controls'] ) ) {
			$flags .= ' controls';
		}
		$poster = ! empty( $a['poster'] ) ? ' poster="' . esc_url( brik_image_url( $a['poster'], 'large' ) ) . '"' : '';
		if ( ! empty( $a['autoplay'] ) ) {
			$flags .= ' playsinline';
		}
		return array( self::block( 'core/video', $attrs, '<figure class="' . esc_attr( 'wp-block-video ' . $cls ) . '"><video' . $flags . $poster . ' src="' . esc_url( $url ) . '"></video></figure>' ) );
	}

	private function map_divider( array $node, array $a ) {
		if ( 'vertical' === $a['orientation'] ) {
			return null;
		}
		$cls = 'brik-n-' . $node['id'];
		return array( self::block( 'core/separator', array( 'className' => $cls ), '<hr class="' . esc_attr( 'wp-block-separator has-alpha-channel-opacity ' . $cls ) . '"/>' ) );
	}

	private function map_spacer( array $node, array $a ) {
		$h = trim( (string) $a['space'] );
		$h = '' === $h ? '48px' : ( is_numeric( $h ) ? $h . 'px' : $h );
		$h = preg_replace( '/[^a-z0-9.%()+\-*\/ ,]/i', '', $h );
		$cls = 'brik-n-' . $node['id'];
		return array(
			self::block(
				'core/spacer',
				array(
					'height'    => $h,
					'className' => $cls,
				),
				'<div style="height:' . esc_attr( $h ) . '" aria-hidden="true" class="' . esc_attr( 'wp-block-spacer ' . $cls ) . '"></div>'
			),
		);
	}

	private function map_accordion( array $node, array $a ) {
		$out   = array();
		$first = true;
		foreach ( is_array( $a['items'] ) ? $a['items'] : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$attrs = array();
			$open  = '';
			if ( $first && ! empty( $a['first_open'] ) ) {
				$attrs['showContent'] = true;
				$open                 = ' open';
			}
			$first = false;
			$title = isset( $item['title'] ) ? (string) $item['title'] : '';
			$inner = self::rich_blocks( brik_rich( isset( $item['content'] ) ? (string) $item['content'] : '' ) );
			if ( ! $inner ) {
				$inner = array( self::paragraph( '' ) );
			}
			$out[] = self::block( 'core/details', $attrs, '<details class="wp-block-details"' . $open . '><summary>' . $title . '</summary>', $inner, '</details>' );
		}
		return $this->wrap( $node, $out );
	}

	/* ---------------------------------------------------------------------
	 * Rich text → blocks.
	 * ------------------------------------------------------------------- */

	/**
	 * Split rich HTML into paragraph, heading, list and quote blocks; anything else becomes HTML.
	 */
	public static function rich_blocks( $html ) {
		$html = trim( (string) $html );
		if ( '' === $html ) {
			return array();
		}
		$doc = self::dom( $html );
		if ( ! $doc ) {
			return array( self::html_block( $html ) );
		}
		$root   = $doc->getElementById( 'brik-export-root' );
		$blocks = array();
		$inline = '';
		$flush  = static function () use ( &$inline, &$blocks ) {
			if ( '' !== trim( $inline ) ) {
				$blocks[] = self::paragraph( trim( $inline ) );
			}
			$inline = '';
		};
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$name = XML_ELEMENT_NODE === $child->nodeType ? strtolower( $child->nodeName ) : '#text';
			if ( '#text' === $name || in_array( $name, array( 'a', 'strong', 'em', 'b', 'i', 'span', 'code', 'br', 'mark', 'small', 'sub', 'sup', 's', 'u' ), true ) ) {
				$inline .= $doc->saveHTML( $child );
				continue;
			}
			$flush();
			if ( 'p' === $name ) {
				$blocks[] = self::paragraph( self::inner_html( $child ) );
			} elseif ( preg_match( '/^h([1-6])$/', $name, $m ) ) {
				$level    = (int) $m[1];
				$blocks[] = self::block( 'core/heading', 2 === $level ? array() : array( 'level' => $level ), '<h' . $level . ' class="wp-block-heading">' . self::inner_html( $child ) . '</h' . $level . '>' );
			} elseif ( 'ul' === $name || 'ol' === $name ) {
				$blocks[] = self::list_block( $child );
			} elseif ( 'blockquote' === $name ) {
				$blocks[] = self::quote_block( $child );
			} else {
				$blocks[] = self::html_block( $doc->saveHTML( $child ) );
			}
		}
		$flush();
		return $blocks;
	}

	private static function list_block( \DOMElement $list ) {
		$ordered = 'ol' === strtolower( $list->nodeName );
		$items   = array();
		foreach ( iterator_to_array( $list->childNodes ) as $li ) {
			if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
				continue;
			}
			$text   = '';
			$nested = array();
			foreach ( iterator_to_array( $li->childNodes ) as $c ) {
				if ( XML_ELEMENT_NODE === $c->nodeType && in_array( strtolower( $c->nodeName ), array( 'ul', 'ol' ), true ) ) {
					$nested[] = self::list_block( $c );
				} else {
					$text .= $c->ownerDocument->saveHTML( $c );
				}
			}
			$items[] = self::block( 'core/list-item', array(), '<li>' . trim( $text ), $nested, '</li>' );
		}
		$tag = $ordered ? 'ol' : 'ul';
		return self::block( 'core/list', $ordered ? array( 'ordered' => true ) : array(), '<' . $tag . ' class="wp-block-list">', $items, '</' . $tag . '>' );
	}

	private static function quote_block( \DOMElement $quote ) {
		$cite = '';
		$body = '';
		foreach ( iterator_to_array( $quote->childNodes ) as $c ) {
			if ( XML_ELEMENT_NODE === $c->nodeType && 'cite' === strtolower( $c->nodeName ) ) {
				$cite = self::inner_html( $c );
			} elseif ( XML_ELEMENT_NODE === $c->nodeType && 'p' === strtolower( $c->nodeName ) && 1 === $c->childNodes->length && 'cite' === strtolower( $c->firstChild->nodeName ) ) {
				// wpautop wraps a lone <cite> in a paragraph.
				$cite = self::inner_html( $c->firstChild );
			} else {
				$body .= $c->ownerDocument->saveHTML( $c );
			}
		}
		$inner = self::rich_blocks( $body );
		if ( ! $inner ) {
			$inner = array( self::paragraph( '' ) );
		}
		return self::block( 'core/quote', array(), '<blockquote class="wp-block-quote">', $inner, ( '' !== $cite ? '<cite>' . $cite . '</cite>' : '' ) . '</blockquote>' );
	}

	private static function dom( $html ) {
		if ( ! class_exists( '\\DOMDocument' ) ) {
			return null;
		}
		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$ok   = $doc->loadHTML( '<?xml encoding="utf-8"?><html><body><div id="brik-export-root">' . $html . '</div></body></html>', LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $ok ? $doc : null;
	}

	private static function inner_html( \DOMNode $node ) {
		$out = '';
		foreach ( iterator_to_array( $node->childNodes ) as $c ) {
			$out .= $node->ownerDocument->saveHTML( $c );
		}
		return trim( $out );
	}

	/* ---------------------------------------------------------------------
	 * Block helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * A parsed-block array, ready for serialize_block().
	 *
	 * @param string $name   Block name.
	 * @param array  $attrs  Comment attributes.
	 * @param string $open   Markup before the inner blocks (or the whole markup).
	 * @param array  $inner  Inner blocks.
	 * @param string $close  Markup after the inner blocks.
	 */
	public static function block( $name, array $attrs, $open, array $inner = array(), $close = '' ) {
		$content = array( "\n" . $open );
		foreach ( $inner as $i => $block ) {
			if ( $i > 0 ) {
				$content[] = "\n\n";
			}
			$content[] = null;
		}
		$content[] = $close . "\n";
		if ( ! $inner ) {
			$content = array( "\n" . $open . $close . "\n" );
		}
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $inner,
			'innerHTML'    => implode( '', array_filter( $content, 'is_string' ) ),
			'innerContent' => $content,
		);
	}

	public static function paragraph( $html, $class = '' ) {
		return self::block( 'core/paragraph', $class ? array( 'className' => $class ) : array(), '<p' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . '>' . $html . '</p>' );
	}

	public static function html_block( $html ) {
		return self::block( 'core/html', array(), $html );
	}

	private function group( array $attrs, array $inner ) {
		$tag     = isset( $attrs['tagName'] ) ? $attrs['tagName'] : 'div';
		$classes = array( 'wp-block-group' );
		if ( ! empty( $attrs['align'] ) ) {
			$classes[] = 'align' . $attrs['align'];
		}
		if ( ! empty( $attrs['className'] ) ) {
			$classes[] = $attrs['className'];
		}
		$css = array();
		if ( ! empty( $attrs['style']['color']['text'] ) ) {
			$classes[] = 'has-text-color';
			$css[]     = 'color:' . $attrs['style']['color']['text'];
		}
		if ( ! empty( $attrs['style']['color']['background'] ) ) {
			$classes[] = 'has-background';
			$css[]     = 'background-color:' . $attrs['style']['color']['background'];
		}
		if ( ! empty( $attrs['style']['spacing']['padding'] ) ) {
			foreach ( $attrs['style']['spacing']['padding'] as $side => $v ) {
				$css[] = 'padding-' . $side . ':' . $v;
			}
		}
		$open = '<' . $tag . ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $css ? ' style="' . esc_attr( implode( ';', $css ) ) . '"' : '' ) . '>';
		return self::block( 'core/group', $attrs, $open, $inner, '</' . $tag . '>' );
	}

	/**
	 * One block keeps the element class; several are wrapped in a group carrying it.
	 */
	private function wrap( array $node, array $blocks ) {
		$cls = 'brik-n-' . $node['id'];
		if ( 1 === count( $blocks ) && in_array( $blocks[0]['blockName'], array( 'core/paragraph', 'core/heading', 'core/list', 'core/quote', 'core/details' ), true ) ) {
			return array( self::add_class( $blocks[0], $cls ) );
		}
		if ( ! $blocks ) {
			return array();
		}
		return array( $this->group( array( 'className' => $cls ), $blocks ) );
	}

	/**
	 * Add a class to a block's className attribute and to its first tag.
	 */
	private static function add_class( array $block, $class ) {
		$block['attrs']['className'] = trim( ( isset( $block['attrs']['className'] ) ? $block['attrs']['className'] . ' ' : '' ) . $class );
		$first                       = $block['innerContent'][0];
		if ( preg_match( '/^(\s*<[a-z0-9]+)(\s+class="([^"]*)")?/i', $first, $m ) ) {
			$rest  = substr( $first, strlen( $m[0] ) );
			$first = isset( $m[2] ) && '' !== $m[2] ? $m[1] . ' class="' . esc_attr( trim( $m[3] . ' ' . $class ) ) . '"' . $rest : $m[1] . ' class="' . esc_attr( $class ) . '"' . $rest;
		}
		$block['innerContent'][0] = $first;
		$block['innerHTML']       = implode( '', array_filter( $block['innerContent'], 'is_string' ) );
		return $block;
	}

	public static function serialize( array $blocks ) {
		return trim( implode( "\n\n", array_map( 'serialize_block', $blocks ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Styles.
	 * ------------------------------------------------------------------- */

	/**
	 * Background and text colour of an element as block style attributes (tokens resolved).
	 */
	private function box_style( array $node, array $a, $dark = false ) {
		$style = array();
		$bg    = $this->has_responsive( $node, 'bg_color' ) ? '' : $this->color( (string) $a['bg_color'] );
		$fg    = $this->has_responsive( $node, 'text_color' ) ? '' : $this->color( (string) $a['text_color'] );
		if ( $dark ) {
			$tokens = self::token_map( true );
			$bg     = $bg ? $bg : ( isset( $tokens['background'] ) ? $tokens['background'] : '' );
			$fg     = $fg ? $fg : ( isset( $tokens['foreground'] ) ? $tokens['foreground'] : '' );
		}
		if ( $bg ) {
			$style['color']['background'] = $bg;
		}
		if ( $fg ) {
			$style['color']['text'] = $fg;
		}
		return $style;
	}

	/** Replace var(--token) references with their values. */
	private function color( $value ) {
		$value = trim( (string) $value );
		for ( $i = 0; $i < 4 && false !== strpos( $value, 'var(' ); $i++ ) {
			$tokens = $this->tokens;
			$value  = preg_replace_callback(
				'/var\(\s*--([a-z0-9-]+)\s*(?:,\s*([^()]*))?\)/i',
				static function ( $m ) use ( $tokens ) {
					if ( isset( $tokens[ $m[1] ] ) ) {
						return $tokens[ $m[1] ];
					}
					return isset( $m[2] ) ? trim( $m[2] ) : $m[0];
				},
				$value
			);
		}
		$value = preg_replace( '/[^a-z0-9#%.,()\/ \-]/i', '', $value );
		return false !== strpos( $value, 'var(' ) ? '' : $value;
	}

	private static function token_map( $dark = false ) {
		$map = Settings::tokens( $dark ? 'dark' : 'light' );
		foreach ( (array) Settings::get( 'colors' ) as $color ) {
			if ( isset( $color['id'], $color['value'] ) ) {
				$map[ 'brik-color-' . $color['id'] ] = $color['value'];
			}
		}
		return $map;
	}

	private function has_responsive( array $node, $key ) {
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		return isset( $attrs[ $key . '@tablet' ] ) || isset( $attrs[ $key . '@mobile' ] ) || isset( $attrs[ $key . '@hover' ] );
	}

	private function has_own_style( array $node ) {
		foreach ( isset( $node['attrs'] ) ? array_keys( (array) $node['attrs'] ) : array() as $key ) {
			if ( ! in_array( $key, array( 'columns', 'admin_label' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/** A desktop-only value (empty when the element changes it per device). */
	private function plain( array $node, $key ) {
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		if ( $this->has_responsive( $node, $key ) || ! isset( $attrs[ $key ] ) || ! is_string( $attrs[ $key ] ) ) {
			return '';
		}
		return $attrs[ $key ];
	}

	/**
	 * CSS shorthand ("96px 0") to { top, right, bottom, left }.
	 */
	public static function sides( $shorthand ) {
		$parts = preg_split( '/\s+/', trim( preg_replace( '/[^a-z0-9.%()\-+*\/ ,]/i', '', (string) $shorthand ) ) );
		$parts = array_map(
			static function ( $p ) {
				return is_numeric( $p ) && 0 != $p ? $p . 'px' : $p; // phpcs:ignore Universal.Operators.StrictComparisons
			},
			array_values( array_filter( $parts, 'strlen' ) )
		);
		if ( ! $parts ) {
			return array();
		}
		$t = $parts[0];
		$r = isset( $parts[1] ) ? $parts[1] : $t;
		$b = isset( $parts[2] ) ? $parts[2] : $t;
		$l = isset( $parts[3] ) ? $parts[3] : $r;
		return array(
			'top'    => $t,
			'right'  => $r,
			'bottom' => $b,
			'left'   => $l,
		);
	}

	/**
	 * Column structure ("1/3,2/3") to flex-basis percentages; null for equal columns.
	 */
	public static function column_widths( $structure, $count ) {
		$parts = array_map( 'trim', explode( ',', $structure ) );
		if ( count( $parts ) !== $count || ! preg_match( '#/#', $structure ) ) {
			return array();
		}
		$fractions = array();
		foreach ( $parts as $p ) {
			$xy          = explode( '/', $p );
			$fractions[] = 2 === count( $xy ) && (float) $xy[1] > 0 ? (float) $xy[0] / (float) $xy[1] : 0;
		}
		if ( count( array_unique( array_map( 'strval', $fractions ) ) ) <= 1 ) {
			return array();
		}
		$total = array_sum( $fractions );
		return array_map(
			static function ( $f ) use ( $total ) {
				return rtrim( rtrim( number_format( $f / $total * 100, 2, '.', '' ), '0' ), '.' ) . '%';
			},
			$fractions
		);
	}

	/**
	 * Brik's framework stylesheet reduced to the rules some markup uses.
	 */
	private function framework_css( array $classes ) {
		$css = '';
		foreach ( array( 'assets/build/frontend.css', 'assets/build/effects.css' ) as $i => $file ) {
			if ( $i > 0 && ! $this->renderer->effects ) {
				continue;
			}
			$path = BRIK_DIR . $file;
			if ( is_readable( $path ) ) {
				$css .= Css::purge( (string) file_get_contents( $path ), $classes ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		return $css;
	}

	private static function style_text( $css ) {
		return str_ireplace( '</style', '', (string) $css );
	}

	/** Builder-only attributes don't belong in exported markup. */
	private static function clean( $html ) {
		return preg_replace( '/\s+data-brik-(?:id|type|root|empty)="[^"]*"/', '', (string) $html );
	}

	/**
	 * Standalone HTML document for the preview iframe.
	 */
	private function preview( $content ) {
		$body = do_blocks( $content );
		$css  = array(
			includes_url( 'css/dist/block-library/style.min.css' ),
			includes_url( 'css/dist/block-library/theme.min.css' ),
		);
		$head = '';
		foreach ( $css as $href ) {
			$head .= '<link rel="stylesheet" href="' . esc_url( $href ) . '">';
		}
		$base = 'body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6;color:#111}'
			. '.is-layout-constrained>*{max-width:var(--wp--style--global--content-size,1200px);margin-left:auto!important;margin-right:auto!important}'
			. '.is-layout-constrained>.alignfull{max-width:none}.wp-block-columns{display:flex;gap:2em}.wp-block-column{flex:1}'
			. 'img{max-width:100%;height:auto}';
		return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $head . '<style>' . $base . '</style></head><body>' . $body . '</body></html>';
	}
}
