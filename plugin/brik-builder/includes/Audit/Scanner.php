<?php
namespace Brik\Audit;

use Brik\Data;
use Brik\Modules;
use Brik\Renderer;
use DOMDocument;
use DOMElement;
use DOMXPath;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side accessibility and SEO audit of a Brik page. Works from the tree and its rendered
 * HTML only, so it runs without a browser (MCP, REST). Checks that need layout (computed contrast,
 * focus styles, tap targets, overflow) run in the builder against the live canvas instead.
 *
 * Finding: { rule, category: a11y|seo, severity: error|warning|info, node, type, label, message,
 *            detail?, data?, fixes?: [ { id, label, patch, safe, prompt? } ] }
 * A fix patch is a set of node attributes to merge (null removes a key), so the builder can
 * apply it as one undoable change and the server can apply the very same patch.
 */
final class Scanner {

	const HEAVY_BYTES = 512000;

	/** Severity weights used for the 0-100 score (mirrored in the builder). */
	const WEIGHTS = array(
		'error'   => 12,
		'warning' => 5,
		'info'    => 0,
	);

	/** Link texts that never describe the destination. */
	const ALWAYS_GENERIC = array( 'click here', 'here', 'click', 'link', 'this', 'this link', 'click this' );

	/** Fine once in context, ambiguous when several point to different places. */
	const GENERIC_LINK_TEXT = array( 'read more', 'learn more', 'more', 'details', 'more info', 'continue', 'continue reading', 'see more', 'view more', 'go', 'find out more' );

	private $post;
	private $tree;
	private $opts;
	private $renderer;
	private $mode = 'light';

	/** @var array id => [ node, parent_id ] */
	private $nodes = array();
	private $findings = array();
	private $checks = array();
	private $outline = array();
	private $meta = array();
	private $hints = array( 'alt' => array() );

	/** @var DOMXPath|null */
	private $xpath;

	/**
	 * Run the audit.
	 *
	 * @param int        $post_id Post id.
	 * @param array|null $tree    Tree to audit (defaults to the saved tree).
	 * @param array      $opts    links: check link targets, external: also external links,
	 *                            categories: [ a11y, seo ].
	 */
	public static function run( $post_id, $tree = null, array $opts = array() ) {
		$scan = new self( $post_id, $tree, $opts );
		return $scan->report();
	}

	private function __construct( $post_id, $tree, array $opts ) {
		$this->post = get_post( $post_id );
		$this->tree = is_array( $tree ) ? Data::normalize( $tree ) : Data::get( $post_id );
		$this->opts = array_merge(
			array(
				'links'    => false,
				'external' => false,
			),
			$opts
		);
		$page       = Data::page_settings( $post_id );
		$this->mode = ! empty( $page['dark'] ) ? 'dark' : 'light';
		$this->index( $this->tree, null );
	}

	private function index( array $nodes, $parent ) {
		foreach ( $nodes as $node ) {
			if ( empty( $node['id'] ) ) {
				continue;
			}
			$this->nodes[ $node['id'] ] = array( $node, $parent );
			if ( ! empty( $node['children'] ) ) {
				$this->index( $node['children'], $node['id'] );
			}
		}
	}

	private function report() {
		$this->renderer = new Renderer( $this->post ? $this->post->ID : 0, true );
		$this->load_html();

		if ( $this->xpath ) {
			$this->headings();
			$this->html_images();
			$this->links();
			$this->controls();
			$this->forms();
			$this->aria();
			$this->media();
		}
		$this->image_modules();
		$this->contrast();
		$this->faq();
		$this->sizes();
		$this->seo_meta();

		$findings = array_values( $this->findings );
		return array(
			'post_id'  => $this->post ? $this->post->ID : 0,
			'scores'   => self::scores( $findings ),
			'findings' => $findings,
			'checks'   => $this->checks,
			'outline'  => $this->outline,
			'meta'     => $this->meta,
			'hints'    => $this->hints,
		);
	}

	/* ---------------------------------------------------------------------
	 * Scoring and helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * 0-100 per category. Each rule+severity group costs its weight once and repeats add a quarter
	 * each (up to four), so one bad pattern repeated twenty times doesn't zero the page.
	 */
	public static function scores( array $findings ) {
		$by = array(
			'a11y' => array(),
			'seo'  => array(),
		);
		foreach ( $findings as $f ) {
			$key = $f['rule'] . '|' . $f['severity'];
			if ( ! isset( $by[ $f['category'] ][ $key ] ) ) {
				$by[ $f['category'] ][ $key ] = array(
					'severity' => $f['severity'],
					'count'    => 0,
				);
			}
			++$by[ $f['category'] ][ $key ]['count'];
		}
		$out = array();
		foreach ( $by as $cat => $groups ) {
			$penalty = 0;
			foreach ( $groups as $g ) {
				$w        = self::WEIGHTS[ $g['severity'] ];
				$penalty += $w + $w * 0.25 * min( $g['count'] - 1, 4 );
			}
			$out[ $cat ] = (int) max( 0, round( 100 - $penalty ) );
		}
		$out['overall'] = (int) round( ( $out['a11y'] + $out['seo'] ) / 2 );
		return $out;
	}

	private function check( $rule ) {
		$this->checks[ $rule ] = true;
	}

	private function add( $rule, $category, $severity, $node_id, $message, array $extra = array() ) {
		$this->check( $rule );
		$key = $rule . '|' . $node_id . '|' . $message;
		if ( isset( $this->findings[ $key ] ) ) {
			return;
		}
		$node = $node_id && isset( $this->nodes[ $node_id ] ) ? $this->nodes[ $node_id ][0] : null;
		$this->findings[ $key ] = array_merge(
			array(
				'rule'     => $rule,
				'category' => $category,
				'severity' => $severity,
				'node'     => $node ? $node['id'] : null,
				'type'     => $node ? $node['type'] : null,
				'label'    => $node ? self::label( $node ) : __( 'Page', 'brik-builder' ),
				'message'  => $message,
			),
			$extra
		);
	}

	private function node( $id ) {
		return $id && isset( $this->nodes[ $id ] ) ? $this->nodes[ $id ][0] : null;
	}

	private function attrs( array $node ) {
		$def = Modules::get( $node['type'] );
		if ( ! $def ) {
			return isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		}
		return $this->renderer->resolve_attrs( array_merge( array( 'attrs' => array() ), $node ), $def );
	}

	public static function label( array $node ) {
		$def   = Modules::get( $node['type'] );
		$title = $def ? $def['title'] : $node['type'];
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		if ( ! empty( $attrs['admin_label'] ) && is_string( $attrs['admin_label'] ) ) {
			return $title . ' · ' . $attrs['admin_label'];
		}
		foreach ( array( 'text', 'title', 'heading', 'label', 'content' ) as $k ) {
			if ( ! empty( $attrs[ $k ] ) && is_string( $attrs[ $k ] ) ) {
				$text = trim( wp_strip_all_tags( $attrs[ $k ] ) );
				if ( '' !== $text ) {
					return $title . ' · ' . ( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $text, 0, 40, '…' ) : substr( $text, 0, 40 ) );
				}
			}
		}
		return $title;
	}

	/* ---------------------------------------------------------------------
	 * Rendered HTML.
	 * ------------------------------------------------------------------- */

	private function load_html() {
		if ( ! $this->post ) {
			return;
		}
		$previous        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $this->post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $this->post );
		try {
			$html = $this->tree ? $this->renderer->render_root( $this->tree ) : apply_filters( 'the_content', $this->post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		} catch ( \Throwable $e ) {
			$html = '';
		}
		$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( $previous ) {
			setup_postdata( $previous );
		}
		if ( '' === trim( (string) $html ) ) {
			return;
		}
		$dom      = new DOMDocument();
		$internal = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="utf-8"?><!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $internal );
		$this->xpath = new DOMXPath( $dom );
	}

	/** Id of the Brik node an element belongs to. */
	private function owner( \DOMNode $el ) {
		for ( $n = $el; $n && $n instanceof DOMElement; $n = $n->parentNode ) {
			if ( $n->hasAttribute( 'data-brik-id' ) ) {
				return $n->getAttribute( 'data-brik-id' );
			}
		}
		return null;
	}

	private function owner_type( \DOMNode $el ) {
		$id   = $this->owner( $el );
		$node = $this->node( $id );
		return $node ? $node['type'] : null;
	}

	private static function text( \DOMNode $el ) {
		return trim( preg_replace( '/\s+/u', ' ', $el->textContent ) );
	}

	/**
	 * Approximate accessible name: aria-label, aria-labelledby, visible text, image alt, title.
	 */
	private function name( DOMElement $el ) {
		if ( '' !== trim( $el->getAttribute( 'aria-label' ) ) ) {
			return trim( $el->getAttribute( 'aria-label' ) );
		}
		if ( $el->hasAttribute( 'aria-labelledby' ) ) {
			$text = '';
			foreach ( preg_split( '/\s+/', $el->getAttribute( 'aria-labelledby' ) ) as $id ) {
				$ref = $this->xpath->query( '//*[@id="' . esc_attr( $id ) . '"]' )->item( 0 );
				if ( $ref ) {
					$text .= ' ' . self::text( $ref );
				}
			}
			if ( '' !== trim( $text ) ) {
				return trim( $text );
			}
		}
		$text = '';
		$walk = function ( \DOMNode $n ) use ( &$walk, &$text ) {
			foreach ( $n->childNodes as $c ) {
				if ( XML_TEXT_NODE === $c->nodeType ) {
					$text .= $c->nodeValue;
				} elseif ( $c instanceof DOMElement ) {
					if ( 'true' === $c->getAttribute( 'aria-hidden' ) || in_array( strtolower( $c->tagName ), array( 'script', 'style' ), true ) ) {
						continue;
					}
					if ( 'img' === strtolower( $c->tagName ) ) {
						$text .= ' ' . $c->getAttribute( 'alt' );
					} elseif ( 'svg' === strtolower( $c->tagName ) ) {
						$title = $c->getElementsByTagName( 'title' )->item( 0 );
						$text .= $title ? ' ' . $title->textContent : ' ' . $c->getAttribute( 'aria-label' );
					} else {
						$walk( $c );
					}
				}
			}
		};
		$walk( $el );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text ) {
			$text = trim( $el->getAttribute( 'title' ) );
		}
		return $text;
	}

	private static function focusable( DOMElement $el ) {
		$tag = strtolower( $el->tagName );
		if ( $el->hasAttribute( 'disabled' ) ) {
			return false;
		}
		if ( $el->hasAttribute( 'tabindex' ) ) {
			return (int) $el->getAttribute( 'tabindex' ) >= 0;
		}
		if ( 'a' === $tag ) {
			return $el->hasAttribute( 'href' );
		}
		if ( 'input' === $tag ) {
			return 'hidden' !== strtolower( $el->getAttribute( 'type' ) );
		}
		return in_array( $tag, array( 'button', 'select', 'textarea', 'summary', 'iframe' ), true );
	}

	/* ---------------------------------------------------------------------
	 * Headings.
	 * ------------------------------------------------------------------- */

	private function headings() {
		$this->check( 'heading-multiple-h1' );
		$this->check( 'heading-skipped' );
		$this->check( 'heading-empty' );
		$this->check( 'h1-count' );

		$h1   = 0;
		$prev = 1; // The page title is usually the H1 above the content.
		foreach ( $this->xpath->query( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $el ) {
			$level = (int) substr( strtolower( $el->tagName ), 1 );
			$text  = $this->name( $el );
			$id    = $this->owner( $el );
			$node  = $this->node( $id );
			$own   = $node && 'heading' === $node['type'];

			$this->outline[] = array(
				'level' => $level,
				'text'  => $text,
				'node'  => $id,
			);

			if ( '' === $text ) {
				$this->add( 'heading-empty', 'a11y', 'error', $id, __( 'Empty heading. Screen reader users navigate by headings and will hear nothing here.', 'brik-builder' ) );
				continue;
			}

			if ( 1 === $level ) {
				++$h1;
				if ( $h1 > 1 ) {
					$this->add(
						'heading-multiple-h1',
						'a11y',
						'warning',
						$id,
						__( 'More than one H1. Use a single H1 for the page topic and H2 for sections.', 'brik-builder' ),
						array( 'fixes' => $own ? array( self::fix( 'level', __( 'Change to H2', 'brik-builder' ), array( 'level' => 'h2' ), true ) ) : array() )
					);
				}
			} elseif ( $level > $prev + 1 ) {
				$want = 'h' . ( $prev + 1 );
				$this->add(
					'heading-skipped',
					'a11y',
					'warning',
					$id,
					/* translators: 1: heading level, 2: previous level */
					sprintf( __( 'Heading level skipped: H%1$d follows H%2$d.', 'brik-builder' ), $level, $prev ),
					array(
						'data'  => array(
							'level' => $level,
							'prev'  => $prev,
						),
						'fixes' => $own ? array(
							/* translators: %s: heading tag */
							self::fix( 'level', sprintf( __( 'Change to %s', 'brik-builder' ), strtoupper( $want ) ), array( 'level' => $want ), true ),
						) : array(),
					)
				);
			}
			$prev = $level;
		}

		if ( 0 === $h1 ) {
			$this->add( 'h1-count', 'seo', 'warning', null, __( 'No H1 in the page content. Unless your theme prints the page title as an H1, add one that states the page topic.', 'brik-builder' ) );
		} elseif ( $h1 > 1 ) {
			/* translators: %d: number of H1 headings */
			$this->add( 'h1-count', 'seo', 'error', null, sprintf( __( '%d H1 headings. Search engines expect one main heading per page.', 'brik-builder' ), $h1 ), array( 'data' => array( 'count' => $h1 ) ) );
		}
		$this->meta['h1'] = $h1;
	}

	private static function fix( $id, $label, array $patch, $safe = false, $prompt = null ) {
		$fix = array(
			'id'    => $id,
			'label' => $label,
			'patch' => $patch,
			'safe'  => (bool) $safe,
		);
		if ( $prompt ) {
			$fix['prompt'] = $prompt;
		}
		return $fix;
	}

	/* ---------------------------------------------------------------------
	 * Images.
	 * ------------------------------------------------------------------- */

	public static function filename_like( $alt ) {
		$alt = trim( (string) $alt );
		return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i', $alt )
			|| (bool) preg_match( '/^(img|image|dsc|dscn|pxl|photo|screenshot|screen shot|unnamed|untitled)[\s_-]*\d/i', $alt )
			|| (bool) preg_match( '/^[a-z0-9]+([_-][a-z0-9]+){2,}$/i', $alt ) && ! preg_match( '/\s/', $alt );
	}

	/**
	 * Alt text suggestion for an attachment or image URL: media alt, then title, then file name.
	 *
	 * @return array [ text, source ] source: alt|title|caption|file|''
	 */
	public static function suggest_alt( $image ) {
		$id  = is_array( $image ) && ! empty( $image['id'] ) ? (int) $image['id'] : 0;
		$url = is_array( $image ) ? ( isset( $image['url'] ) ? $image['url'] : '' ) : (string) $image;
		if ( ! $id && $url ) {
			$id = attachment_url_to_postid( $url );
		}
		if ( $id ) {
			$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
			if ( '' !== $alt && ! self::filename_like( $alt ) ) {
				return array( $alt, 'alt' );
			}
			$title = trim( get_the_title( $id ) );
			if ( '' !== $title && ! self::filename_like( $title ) ) {
				return array( $title, 'title' );
			}
			$caption = trim( wp_strip_all_tags( (string) wp_get_attachment_caption( $id ) ) );
			if ( '' !== $caption ) {
				return array( $caption, 'caption' );
			}
		}
		$file = $url ? (string) wp_parse_url( $url, PHP_URL_PATH ) : '';
		$file = preg_replace( '/\.[a-z0-9]+$/i', '', basename( $file ) );
		$file = preg_replace( '/-\d+x\d+$/', '', $file );
		$file = trim( preg_replace( '/[\s_-]+/', ' ', $file ) );
		if ( '' !== $file && ! preg_match( '/^(img|image|dsc|photo)?\s*\d+$/i', $file ) && ! preg_match( '/\d{6,}/', $file ) ) {
			return array( ucfirst( $file ), 'file' );
		}
		return array( '', '' );
	}

	private function image_modules() {
		$this->check( 'img-alt-missing' );
		$this->check( 'img-alt-filename' );
		$this->check( 'img-alt-long' );

		foreach ( $this->nodes as $id => $entry ) {
			$node = $entry[0];
			if ( ! in_array( $node['type'], array( 'image', 'gallery' ), true ) ) {
				continue;
			}
			$a = $this->attrs( $node );
			if ( ! empty( $a['decorative'] ) ) {
				continue;
			}

			if ( 'image' === $node['type'] ) {
				$items = function_exists( 'brik_media_items' ) ? brik_media_items( $a['image'], 'large' ) : array();
				if ( ! $items ) {
					continue;
				}
				$alt = isset( $a['alt'] ) && '' !== trim( (string) $a['alt'] ) ? trim( (string) $a['alt'] ) : trim( $items[0]['alt'] );
				list( $suggest, $source ) = self::suggest_alt( $a['image'] );
				if ( '' !== $suggest ) {
					$this->hints['alt'][ $id ] = $suggest;
				}
				$prompt = array(
					'key'   => 'alt',
					'label' => __( 'Alt text', 'brik-builder' ),
					'value' => $suggest,
				);
				$alt_fix = self::fix( 'alt', __( 'Write alt text', 'brik-builder' ), array( 'alt' => $suggest ), in_array( $source, array( 'alt', 'title', 'caption' ), true ), $prompt );
				$dec_fix = self::fix( 'decorative', __( 'Mark decorative', 'brik-builder' ), array( 'decorative' => true ) );

				if ( '' === $alt ) {
					$this->add(
						'img-alt-missing',
						'a11y',
						'error',
						$id,
						__( 'Image has no alt text. Describe it, or mark it decorative if it adds no information.', 'brik-builder' ),
						array( 'fixes' => array( $alt_fix, $dec_fix ) )
					);
				} elseif ( self::filename_like( $alt ) ) {
					/* translators: %s: alt text */
					$this->add( 'img-alt-filename', 'a11y', 'warning', $id, sprintf( __( 'Alt text looks like a file name: "%s".', 'brik-builder' ), $alt ), array( 'fixes' => array( $alt_fix, $dec_fix ) ) );
				} elseif ( self::strlen( $alt ) > 150 ) {
					$this->add( 'img-alt-long', 'a11y', 'warning', $id, __( 'Alt text is very long (over 150 characters). Keep it short and put details in a caption.', 'brik-builder' ), array( 'data' => array( 'length' => self::strlen( $alt ) ) ) );
				}
				continue;
			}

			// Gallery.
			$items   = function_exists( 'brik_media_items' ) ? brik_media_items( $a['images'], 'large' ) : array();
			$missing = 0;
			$names   = 0;
			foreach ( $items as $item ) {
				if ( '' === trim( $item['alt'] ) ) {
					++$missing;
				} elseif ( self::filename_like( $item['alt'] ) ) {
					++$names;
				}
			}
			$dec_fix = self::fix( 'decorative', __( 'Mark all decorative', 'brik-builder' ), array( 'decorative' => true ) );
			if ( $missing ) {
				$this->add(
					'img-alt-missing',
					'a11y',
					'error',
					$id,
					/* translators: 1: images without alt, 2: total images */
					sprintf( __( '%1$d of %2$d gallery images have no alt text.', 'brik-builder' ), $missing, count( $items ) ),
					array(
						'data'  => array(
							'missing' => $missing,
							'total'   => count( $items ),
						),
						'fixes' => array( $dec_fix ),
					)
				);
			} elseif ( $names ) {
				/* translators: %d: number of images */
				$this->add( 'img-alt-filename', 'a11y', 'warning', $id, sprintf( _n( '%d gallery image uses a file name as alt text.', '%d gallery images use file names as alt text.', $names, 'brik-builder' ), $names ) );
			}
		}
	}

	private static function strlen( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s );
	}

	/**
	 * Images rendered by other modules (cards, blurbs, rich text): only the markup is known.
	 */
	private function html_images() {
		$this->check( 'img-dimensions' );
		$dims = array();
		foreach ( $this->xpath->query( '//img' ) as $img ) {
			$id   = $this->owner( $img );
			$type = $this->owner_type( $img );
			if ( ! $img->hasAttribute( 'width' ) || ! $img->hasAttribute( 'height' ) ) {
				$src = $img->getAttribute( 'src' );
				if ( $src && 0 !== strpos( $src, 'data:' ) && ! preg_match( '/\.svg(\?|$)/i', $src ) ) {
					$dims[ (string) $id ] = isset( $dims[ (string) $id ] ) ? $dims[ (string) $id ] + 1 : 1;
				}
			}
			if ( in_array( $type, array( 'image', 'gallery' ), true ) ) {
				continue;
			}
			if ( 'presentation' === $img->getAttribute( 'role' ) || 'none' === $img->getAttribute( 'role' ) || 'true' === $img->getAttribute( 'aria-hidden' ) ) {
				continue;
			}
			if ( ! $img->hasAttribute( 'alt' ) ) {
				$this->add( 'img-alt-missing', 'a11y', 'error', $id, __( 'Image without an alt attribute. Add alt text (or alt="" if it is decorative).', 'brik-builder' ) );
			} elseif ( self::filename_like( $img->getAttribute( 'alt' ) ) ) {
				/* translators: %s: alt text */
				$this->add( 'img-alt-filename', 'a11y', 'warning', $id, sprintf( __( 'Alt text looks like a file name: "%s".', 'brik-builder' ), $img->getAttribute( 'alt' ) ) );
			} elseif ( self::strlen( $img->getAttribute( 'alt' ) ) > 150 ) {
				$this->add( 'img-alt-long', 'a11y', 'warning', $id, __( 'Alt text is very long (over 150 characters). Keep it short and put details in a caption.', 'brik-builder' ) );
			}
		}
		foreach ( $dims as $id => $count ) {
			$this->add(
				'img-dimensions',
				'seo',
				'warning',
				$id ? $id : null,
				/* translators: %d: number of images */
				sprintf( _n( '%d image has no width/height attributes, which causes layout shift while it loads.', '%d images have no width/height attributes, which causes layout shift while they load.', $count, 'brik-builder' ), $count ),
				array( 'data' => array( 'count' => $count ) )
			);
		}
	}

	/* ---------------------------------------------------------------------
	 * Links and buttons.
	 * ------------------------------------------------------------------- */

	private static function rels( $rel ) {
		return array_filter( preg_split( '/\s+/', strtolower( trim( (string) $rel ) ) ) );
	}

	public static function affiliate( $href ) {
		return (bool) preg_match( '~(amzn\.to/|amazon\.[a-z.]+/.*[?&]tag=|[?&](ref|aff|affiliate|aff_id|affid|partner)=|/go/|/recommends/|/refer/|shareasale\.com|awin1\.com|clickbank\.net|impact\.com|partnerize|rstyle\.me|go\.skimresources|anrdoezrs\.net|dpbolvw\.net|jdoqocy\.com|tkqlhce\.com|utm_medium=(affiliate|sponsored|paid))~i', $href );
	}

	private function links() {
		foreach ( array( 'name-missing', 'link-generic', 'link-blank-noopener', 'link-sponsored' ) as $r ) {
			$this->check( $r );
		}
		$hrefs   = array();
		$generic = array();
		foreach ( $this->xpath->query( '//a[@href]' ) as $a ) {
			if ( 'true' === $a->getAttribute( 'aria-hidden' ) && '-1' === $a->getAttribute( 'tabindex' ) ) {
				continue;
			}
			$id   = $this->owner( $a );
			$node = $this->node( $id );
			$href = trim( $a->getAttribute( 'href' ) );
			$name = $this->name( $a );
			$rels = self::rels( $a->getAttribute( 'rel' ) );

			if ( '' === $name ) {
				$fixes = array();
				if ( $node && 'button' === $node['type'] ) {
					list( $suggest ) = self::suggest_button_text( $node, $href );
					$fixes[]         = self::fix(
						'text',
						__( 'Add button text', 'brik-builder' ),
						array( 'text' => $suggest ),
						false,
						array(
							'key'   => 'text',
							'label' => __( 'Button text', 'brik-builder' ),
							'value' => $suggest,
						)
					);
				}
				$this->add( 'name-missing', 'a11y', 'error', $id, __( 'Link has no accessible name (icon only or empty). Screen readers announce just "link".', 'brik-builder' ), array( 'fixes' => $fixes ) );
			} else {
				$text = strtolower( trim( preg_replace( '/[\s.!:»›→]+$/u', '', $name ) ) );
				if ( in_array( $text, self::ALWAYS_GENERIC, true ) || in_array( $text, self::GENERIC_LINK_TEXT, true ) ) {
					$generic[ $text ][] = array( $id, $name, $href );
				}
			}

			if ( '_blank' === strtolower( $a->getAttribute( 'target' ) ) && ! array_intersect( $rels, array( 'noopener', 'noreferrer' ) ) ) {
				$this->add(
					'link-blank-noopener',
					'seo',
					'warning',
					$id,
					__( 'Link opens a new tab without rel="noopener". The opened page can control this one (tab-nabbing).', 'brik-builder' ),
					array(
						'data'  => array( 'url' => $href ),
						'fixes' => $node ? $this->rel_fixes( $node, 'noopener' ) : array(),
					)
				);
			}

			if ( self::affiliate( $href ) && ! array_intersect( $rels, array( 'sponsored', 'nofollow' ) ) ) {
				$this->add(
					'link-sponsored',
					'seo',
					'info',
					$id,
					__( 'Looks like an affiliate or paid link. Google asks for rel="sponsored" (or nofollow) on these.', 'brik-builder' ),
					array(
						'data'  => array( 'url' => $href ),
						'fixes' => $node ? $this->rel_fixes( $node, 'sponsored' ) : array(),
					)
				);
			}

			if ( '' !== $href && '#' !== $href[0] ) {
				$hrefs[ $href ][] = $id;
			}
		}

		// "Read more" is fine once in context; several pointing to different places are ambiguous.
		foreach ( $generic as $text => $list ) {
			$targets = array_unique( array_column( $list, 2 ) );
			if ( ! in_array( $text, self::ALWAYS_GENERIC, true ) && count( $targets ) < 2 ) {
				continue;
			}
			foreach ( $list as $g ) {
				/* translators: %s: link text */
				$this->add( 'link-generic', 'a11y', 'warning', $g[0], sprintf( __( 'Link text "%s" doesn\'t say where it goes. Describe the destination, e.g. "Read the pricing guide".', 'brik-builder' ), $g[1] ) );
			}
		}

		$this->meta['links'] = count( $hrefs );
		if ( ! empty( $this->opts['links'] ) && $hrefs ) {
			$this->check( 'link-broken' );
			$results = Links::check( array_keys( $hrefs ), ! empty( $this->opts['external'] ) );
			foreach ( $results as $url => $r ) {
				if ( 'broken' !== $r['status'] ) {
					continue;
				}
				foreach ( array_unique( $hrefs[ $url ] ) as $id ) {
					$this->add(
						'link-broken',
						'seo',
						'error',
						$id,
						/* translators: %s: URL */
						sprintf( __( 'Broken link: %s', 'brik-builder' ), $url ),
						array( 'data' => array_merge( array( 'url' => $url ), $r ) )
					);
				}
			}
		}
	}

	/**
	 * Fallback text for an icon-only button, taken from where it links.
	 */
	private static function suggest_button_text( array $node, $href ) {
		$path = trim( (string) wp_parse_url( $href, PHP_URL_PATH ), '/' );
		if ( $path ) {
			$last = basename( $path );
			return array( ucfirst( trim( preg_replace( '/[-_]+/', ' ', $last ) ) ) );
		}
		return array( '' );
	}

	/**
	 * Patches that add a rel token to the links a node renders: the button "rel" field, link field
	 * flags, or anchors written inside text attributes.
	 */
	private function rel_fixes( array $node, $token ) {
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		$patch = array();

		if ( 'button' === $node['type'] && ( 'sponsored' === $token || ! empty( $attrs['rel'] ) ) ) {
			$rels          = self::rels( isset( $attrs['rel'] ) ? $attrs['rel'] : '' );
			$link          = isset( $attrs['link'] ) && is_array( $attrs['link'] ) ? $attrs['link'] : array();
			$base          = ! empty( $link['new_tab'] ) ? array( 'noopener' ) : array();
			$patch['rel']  = implode( ' ', array_unique( array_merge( $base, $rels, array( $token ) ) ) );
		} elseif ( 'sponsored' === $token ) {
			foreach ( $attrs as $key => $value ) {
				if ( is_array( $value ) && isset( $value['url'] ) && self::affiliate( (string) $value['url'] ) && empty( $value['nofollow'] ) ) {
					$patch[ $key ] = array_merge( $value, array( 'nofollow' => true ) );
				}
			}
		}

		foreach ( $attrs as $key => $value ) {
			if ( isset( $patch[ $key ] ) ) {
				continue;
			}
			$new = self::rewrite_rel( $value, $token );
			if ( $new !== $value ) {
				$patch[ $key ] = $new;
			}
		}
		if ( ! $patch ) {
			return array();
		}
		/* translators: %s: rel value */
		return array( self::fix( 'rel', sprintf( __( 'Add rel="%s"', 'brik-builder' ), $token ), $patch, 'noopener' === $token ) );
	}

	/**
	 * Add a rel token to anchors inside an attribute value (strings and repeater items).
	 */
	public static function rewrite_rel( $value, $token ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = self::rewrite_rel( $v, $token );
			}
			return $value;
		}
		if ( ! is_string( $value ) || false === stripos( $value, '<a' ) ) {
			return $value;
		}
		return preg_replace_callback(
			'/<a\b[^>]*>/i',
			static function ( $m ) use ( $token ) {
				$tag = $m[0];
				if ( 'noopener' === $token && ! preg_match( '/\btarget\s*=\s*["\']?_blank/i', $tag ) ) {
					return $tag;
				}
				if ( 'sponsored' === $token && ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $tag, $h ) || ! self::affiliate( $h[2] ) ) ) {
					return $tag;
				}
				if ( preg_match( '/\brel\s*=\s*(["\'])(.*?)\1/i', $tag, $r ) ) {
					$rels = self::rels( $r[2] );
					if ( in_array( $token, $rels, true ) || ( 'noopener' === $token && in_array( 'noreferrer', $rels, true ) ) ) {
						return $tag;
					}
					return str_replace( $r[0], 'rel=' . $r[1] . trim( $r[2] . ' ' . $token ) . $r[1], $tag );
				}
				return preg_replace( '/^<a\b/i', '<a rel="' . $token . '"', $tag );
			},
			$value
		);
	}

	private function controls() {
		$this->check( 'div-button' );
		$this->check( 'tabindex-positive' );
		foreach ( $this->xpath->query( '//button|//*[@role="button"]' ) as $el ) {
			if ( ! self::focusable( $el ) && 'button' !== strtolower( $el->tagName ) ) {
				$this->add( 'div-button', 'a11y', 'warning', $this->owner( $el ), __( 'Element acts as a button but can\'t be reached with the keyboard. Use a real <button> or add tabindex="0" and key handlers.', 'brik-builder' ) );
				continue;
			}
			if ( '' === $this->name( $el ) ) {
				$this->add( 'name-missing', 'a11y', 'error', $this->owner( $el ), __( 'Button has no accessible name (icon only or empty).', 'brik-builder' ) );
			}
		}
		foreach ( $this->xpath->query( '//*[@onclick]' ) as $el ) {
			$tag = strtolower( $el->tagName );
			if ( ! in_array( $tag, array( 'a', 'button', 'input', 'select', 'textarea', 'summary', 'label' ), true ) && ! $el->hasAttribute( 'role' ) ) {
				$this->add( 'div-button', 'a11y', 'warning', $this->owner( $el ), sprintf( /* translators: %s: tag name */ __( 'Clickable <%s> isn\'t a button: keyboard and screen reader users can\'t use it.', 'brik-builder' ), $tag ) );
			}
		}
		foreach ( $this->xpath->query( '//*[@tabindex]' ) as $el ) {
			if ( (int) $el->getAttribute( 'tabindex' ) > 0 ) {
				$this->add( 'tabindex-positive', 'a11y', 'warning', $this->owner( $el ), __( 'tabindex greater than 0 changes the natural tab order and usually confuses keyboard users.', 'brik-builder' ) );
			}
		}
	}

	private function forms() {
		$this->check( 'input-label' );
		foreach ( $this->xpath->query( '//input|//select|//textarea' ) as $el ) {
			$type = strtolower( $el->getAttribute( 'type' ) );
			if ( in_array( $type, array( 'hidden', 'submit', 'button', 'reset', 'image' ), true ) ) {
				continue;
			}
			if ( '' !== trim( $el->getAttribute( 'aria-label' ) ) || '' !== trim( $el->getAttribute( 'aria-labelledby' ) ) || '' !== trim( $el->getAttribute( 'title' ) ) ) {
				continue;
			}
			$id = $el->getAttribute( 'id' );
			if ( $id && $this->xpath->query( '//label[@for="' . esc_attr( $id ) . '"]' )->length ) {
				continue;
			}
			$labelled = false;
			for ( $p = $el->parentNode; $p instanceof DOMElement; $p = $p->parentNode ) {
				if ( 'label' === strtolower( $p->tagName ) ) {
					$labelled = true;
					break;
				}
			}
			if ( $labelled ) {
				continue;
			}
			$msg = $el->getAttribute( 'placeholder' )
				? __( 'Form field is only labelled by its placeholder, which disappears while typing and isn\'t reliably announced. Add a visible label.', 'brik-builder' )
				: __( 'Form field has no label.', 'brik-builder' );
			$this->add( 'input-label', 'a11y', 'error', $this->owner( $el ), $msg );
		}
	}

	private function aria() {
		$this->check( 'aria-hidden-focusable' );
		foreach ( $this->xpath->query( '//*[@aria-hidden="true"]' ) as $el ) {
			$bad = self::focusable( $el ) ? $el : null;
			if ( ! $bad ) {
				foreach ( $el->getElementsByTagName( '*' ) as $child ) {
					if ( self::focusable( $child ) ) {
						$bad = $child;
						break;
					}
				}
			}
			if ( $bad ) {
				$this->add( 'aria-hidden-focusable', 'a11y', 'error', $this->owner( $el ), __( 'aria-hidden="true" on (or around) a focusable element: keyboard users land on something screen readers can\'t see.', 'brik-builder' ) );
			}
		}
	}

	private function media() {
		$this->check( 'autoplay-sound' );
		foreach ( $this->xpath->query( '//video[@autoplay]|//audio[@autoplay]' ) as $el ) {
			if ( 'video' === strtolower( $el->tagName ) && $el->hasAttribute( 'muted' ) ) {
				continue;
			}
			$this->add( 'autoplay-sound', 'a11y', 'error', $this->owner( $el ), __( 'Media plays sound automatically. Autoplaying audio interferes with screen readers; mute it or let visitors start it.', 'brik-builder' ) );
		}
		foreach ( $this->xpath->query( '//iframe[@src]' ) as $el ) {
			$src = $el->getAttribute( 'src' );
			if ( preg_match( '/[?&](autoplay|auto_play)=(1|true)/i', $src ) && ! preg_match( '/[?&](mute|muted)=(1|true)/i', $src ) ) {
				$this->add( 'autoplay-sound', 'a11y', 'error', $this->owner( $el ), __( 'Embedded player autoplays with sound. Add mute=1 or turn autoplay off.', 'brik-builder' ) );
			}
		}
		foreach ( $this->nodes as $id => $entry ) {
			$node = $entry[0];
			if ( 'audio' === $node['type'] && ! empty( $node['attrs']['autoplay'] ) ) {
				$this->add( 'autoplay-sound', 'a11y', 'error', $id, __( 'Audio set to autoplay. Let visitors start it themselves.', 'brik-builder' ), array( 'fixes' => array( self::fix( 'autoplay', __( 'Turn off autoplay', 'brik-builder' ), array( 'autoplay' => null ), true ) ) ) );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Contrast from explicit colour attributes.
	 * ------------------------------------------------------------------- */

	/**
	 * Explicit background of a node, as a list of colours (several for gradients).
	 * Returns null when the background is unknown (image) and [] when there is none.
	 */
	private function background( array $attrs ) {
		if ( ! empty( $attrs['bg_image'] ) && ( ! empty( $attrs['bg_image']['url'] ) || ! empty( $attrs['bg_image']['id'] ) || is_string( $attrs['bg_image'] ) ) ) {
			if ( ! empty( $attrs['bg_overlay'] ) ) {
				$c = Color::parse( $attrs['bg_overlay'], $this->mode );
				if ( $c && $c[3] >= 0.85 ) {
					return array( $c );
				}
			}
			return null;
		}
		if ( ! empty( $attrs['bg_gradient'] ) && is_string( $attrs['bg_gradient'] ) ) {
			preg_match_all( '/#[0-9a-f]{3,8}\b|(?:rgba?|hsla?|oklch)\([^)]*\)|var\(--[a-z0-9-]+\)/i', $attrs['bg_gradient'], $m );
			$out = array();
			foreach ( $m[0] as $raw ) {
				$c = Color::parse( $raw, $this->mode );
				if ( $c ) {
					$out[] = $c;
				}
			}
			return $out ? $out : null;
		}
		if ( ! empty( $attrs['bg_color'] ) && is_string( $attrs['bg_color'] ) ) {
			$c = Color::parse( $attrs['bg_color'], $this->mode );
			return $c ? array( $c ) : null;
		}
		return array();
	}

	private function contrast() {
		$this->check( 'contrast' );
		$page_bg = Color::parse( 'var(--background)', $this->mode );
		$page_bg = $page_bg ? $page_bg : array( 255, 255, 255, 1 );

		foreach ( $this->nodes as $id => $entry ) {
			$node  = $entry[0];
			$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();

			// Text colours set on this node, plus any inherited text_color from ancestors.
			$colors = array();
			foreach ( $attrs as $key => $value ) {
				if ( is_string( $value ) && '' !== $value && false === strpos( $key, '@' ) && ( 'text_color' === $key || preg_match( '/_text_color$/', $key ) || preg_match( '/^[a-z]+_color$/', $key ) && ! preg_match( '/^(bg|border|icon|overlay|accent|line|track|bar|dot|star|fill|stroke|glow|beam|particle|grid|ring)_/', $key ) ) ) {
					$colors[ $key ] = $value;
				}
			}

			// Walk up for the first explicit background and an inherited text colour.
			$bg      = array();
			$known   = true;
			$inherit = null;
			for ( $cur = $id; $cur; $cur = $this->nodes[ $cur ][1] ) {
				$a = isset( $this->nodes[ $cur ][0]['attrs'] ) ? (array) $this->nodes[ $cur ][0]['attrs'] : array();
				if ( $cur !== $id && null === $inherit && ! empty( $a['text_color'] ) && is_string( $a['text_color'] ) ) {
					$inherit = $a['text_color'];
				}
				$b = $this->background( $a );
				if ( null === $b ) {
					$known = false;
					break;
				}
				if ( $b ) {
					$bg = $b;
					break;
				}
			}
			if ( ! $known ) {
				$bg = array();
			}
			if ( ! $colors && $inherit && ! $this->has_children( $node ) ) {
				$colors['text_color'] = $inherit;
			}
			if ( ! $colors ) {
				continue;
			}

			$large  = $this->is_large( $node, $attrs );
			$need   = $large ? 3 : 4.5;
			$def    = Modules::get( $node['type'] );
			$fields = $def ? $def['fields'] : array();
			foreach ( $colors as $key => $raw ) {
				$fg = Color::parse( $raw, $this->mode );
				if ( ! $fg ) {
					continue;
				}
				// Text inside a styled box (button, card, item) sits on the box background.
				$own    = $bg;
				$prefix = preg_replace( '/_(text_)?color$/', '', $key );
				if ( 'text' !== $prefix && isset( $fields[ $prefix . '_bg' ] ) ) {
					$box = isset( $attrs[ $prefix . '_bg' ] ) && is_string( $attrs[ $prefix . '_bg' ] ) ? Color::parse( $attrs[ $prefix . '_bg' ], $this->mode ) : null;
					if ( ! $box ) {
						continue;
					}
					$own = array( $box );
				}
				if ( ! $own ) {
					continue;
				}
				$worst = null;
				$wbg   = null;
				foreach ( $own as $b ) {
					$solid = $b[3] < 1 ? Color::over( $b, $page_bg ) : $b;
					$r     = Color::ratio( $fg, $solid );
					if ( null === $worst || $r < $worst ) {
						$worst = $r;
						$wbg   = $solid;
					}
				}
				if ( $worst >= $need ) {
					continue;
				}
				$fix_color = Color::ratio( array( 10, 10, 10, 1 ), $wbg ) >= Color::ratio( array( 255, 255, 255, 1 ), $wbg ) ? '#0a0a0a' : '#ffffff';
				// Far below the threshold is an error; just under it (e.g. 4.3:1) a warning.
				$this->add(
					'contrast',
					'a11y',
					$worst < $need * 0.75 ? 'error' : 'warning',
					$id,
					/* translators: 1: contrast ratio, 2: required ratio */
					sprintf( __( 'Low text contrast %1$s:1 (needs %2$s:1).', 'brik-builder' ), $worst, $need ),
					array(
						'data'  => array(
							'ratio'    => $worst,
							'required' => $need,
							'fg'       => Color::hex( Color::over( $fg, $wbg ) ),
							'bg'       => Color::hex( $wbg ),
							'attr'     => $key,
							'large'    => $large,
						),
						'fixes' => array(
							/* translators: %s: colour */
							self::fix( 'contrast', sprintf( __( 'Use %s text', 'brik-builder' ), '#ffffff' === $fix_color ? __( 'white', 'brik-builder' ) : __( 'near-black', 'brik-builder' ) ), array( $key => $fix_color ) ),
						),
					)
				);
			}
		}
	}

	private function has_children( array $node ) {
		return ! empty( $node['children'] );
	}

	private function is_large( array $node, array $attrs ) {
		foreach ( $attrs as $key => $value ) {
			if ( ( 'font_size' === $key || preg_match( '/_font_size$/', $key ) ) && is_string( $value ) && preg_match( '/^([\d.]+)(px|rem|em)?$/', trim( $value ), $m ) ) {
				$unit = isset( $m[2] ) && '' !== $m[2] ? $m[2] : 'px';
				$px   = 'px' === $unit ? (float) $m[1] : (float) $m[1] * 16;
				$bold = false;
				foreach ( $attrs as $k => $v ) {
					if ( preg_match( '/font_weight$/', $k ) && (int) $v >= 700 ) {
						$bold = true;
					}
				}
				return $px >= 24 || ( $bold && $px >= 18.66 );
			}
		}
		if ( 'heading' === $node['type'] ) {
			$style = isset( $attrs['style'] ) ? $attrs['style'] : '';
			$level = isset( $attrs['level'] ) ? $attrs['level'] : 'h2';
			return in_array( $style ? $style : $level, array( 'display', 'h1', 'h2', 'h3', 'h4' ), true );
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * SEO.
	 * ------------------------------------------------------------------- */

	private function faq() {
		$this->check( 'faq-schema' );
		$schemas = 0;
		foreach ( $this->nodes as $id => $entry ) {
			$node  = $entry[0];
			$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
			if ( 'accordion' === $node['type'] ) {
				$a     = $this->attrs( $node );
				$items = is_array( $a['items'] ) ? $a['items'] : array();
				$q     = 0;
				foreach ( $items as $item ) {
					if ( is_array( $item ) && isset( $item['title'] ) && '?' === substr( trim( wp_strip_all_tags( (string) $item['title'] ) ), -1 ) ) {
						++$q;
					}
				}
				if ( ! empty( $a['faq_schema'] ) ) {
					++$schemas;
					continue;
				}
				if ( $q >= 2 && $q >= count( $items ) / 2 ) {
					$this->add(
						'faq-schema',
						'seo',
						'warning',
						$id,
						/* translators: %d: number of questions */
						sprintf( __( 'This accordion looks like an FAQ (%d questions) but has no FAQ structured data.', 'brik-builder' ), $q ),
						array(
							'data'  => array( 'questions' => $q ),
							'fixes' => array( self::fix( 'faq', __( 'Enable FAQ schema', 'brik-builder' ), array( 'faq_schema' => true ), true ) ),
						)
					);
				}
			} elseif ( 'tabs' === $node['type'] && ! empty( $attrs['items'] ) && is_array( $attrs['items'] ) ) {
				$q = 0;
				foreach ( $attrs['items'] as $item ) {
					if ( is_array( $item ) && isset( $item['title'] ) && '?' === substr( trim( wp_strip_all_tags( (string) $item['title'] ) ), -1 ) ) {
						++$q;
					}
				}
				if ( $q >= 2 ) {
					$this->add( 'faq-schema', 'seo', 'info', $id, __( 'These tabs read like FAQ questions. An Accordion with "FAQ structured data" turned on can show them as rich results.', 'brik-builder' ) );
				}
			} elseif ( 'toggle' === $node['type'] && ! empty( $attrs['title'] ) && '?' === substr( trim( wp_strip_all_tags( (string) $attrs['title'] ) ), -1 ) ) {
				$this->add( 'faq-schema', 'seo', 'info', $id, __( 'Toggle with a question title. Group FAQ toggles into an Accordion and enable "FAQ structured data".', 'brik-builder' ) );
			}
		}
		if ( $schemas > 1 ) {
			$this->add( 'faq-schema', 'seo', 'info', null, __( 'Several accordions output FAQPage data. Google reads one FAQ block per page; consider merging them.', 'brik-builder' ) );
		}
	}

	/**
	 * Attachment ids used by the tree (image fields, galleries, background images) with the size
	 * they render at.
	 */
	private function attachments() {
		$out  = array();
		$walk = static function ( $value, $size, $id ) use ( &$out, &$walk ) {
			if ( is_array( $value ) ) {
				if ( ! empty( $value['id'] ) && is_numeric( $value['id'] ) ) {
					$out[] = array( (int) $value['id'], $size, $id );
					return;
				}
				foreach ( $value as $v ) {
					$walk( $v, $size, $id );
				}
			}
		};
		foreach ( $this->nodes as $id => $entry ) {
			$attrs = isset( $entry[0]['attrs'] ) ? (array) $entry[0]['attrs'] : array();
			$size  = ! empty( $attrs['size'] ) && is_string( $attrs['size'] ) ? $attrs['size'] : 'large';
			foreach ( $attrs as $key => $value ) {
				$walk( $value, 0 === strpos( $key, 'bg_image' ) ? 'full' : $size, $id );
			}
		}
		return $out;
	}

	/**
	 * Bytes of the file WordPress serves for an attachment at a size.
	 */
	public static function file_size( $attachment_id, $size = 'large' ) {
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return 0;
		}
		if ( 'full' !== $size ) {
			$meta = image_get_intermediate_size( $attachment_id, $size );
			if ( $meta && ! empty( $meta['path'] ) ) {
				$uploads = wp_get_upload_dir();
				$sized   = trailingslashit( $uploads['basedir'] ) . $meta['path'];
				if ( file_exists( $sized ) ) {
					$file = $sized;
				}
			}
		}
		return file_exists( $file ) ? (int) filesize( $file ) : 0;
	}

	private function sizes() {
		$this->check( 'img-heavy' );
		$seen = array();
		foreach ( $this->attachments() as $item ) {
			list( $att, $size, $id ) = $item;
			if ( isset( $seen[ $att . $size ] ) || ! wp_attachment_is_image( $att ) ) {
				continue;
			}
			$seen[ $att . $size ] = true;
			$bytes                = self::file_size( $att, $size );
			if ( $bytes > self::HEAVY_BYTES ) {
				$this->add(
					'img-heavy',
					'seo',
					'warning',
					$id,
					/* translators: 1: file size, 2: file name */
					sprintf( __( 'Heavy image: %1$s (%2$s). Compress it or use a smaller size; aim for under 500 KB.', 'brik-builder' ), size_format( $bytes, 1 ), basename( (string) get_attached_file( $att ) ) ),
					array(
						'data' => array(
							'bytes'         => $bytes,
							'attachment_id' => $att,
							'url'           => wp_get_attachment_image_url( $att, $size ),
						),
					)
				);
			}
		}
	}

	private function seo_meta() {
		if ( ! $this->post ) {
			return;
		}
		foreach ( array( 'title-length', 'meta-description', 'noindex', 'canonical', 'structured-data' ) as $r ) {
			$this->check( $r );
		}
		$id   = $this->post->ID;
		$sep  = apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		$site = get_bloginfo( 'name' );

		$yoast = defined( 'WPSEO_VERSION' );
		$rank  = defined( 'RANK_MATH_VERSION' );

		// Title.
		$title  = '';
		$source = 'post';
		if ( $yoast && get_post_meta( $id, '_yoast_wpseo_title', true ) ) {
			$title  = (string) get_post_meta( $id, '_yoast_wpseo_title', true );
			$title  = function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $title, $this->post ) : $title;
			$source = 'yoast';
		} elseif ( $rank && get_post_meta( $id, 'rank_math_title', true ) ) {
			$title  = (string) get_post_meta( $id, 'rank_math_title', true );
			$source = 'rankmath';
		}
		if ( '' === $title ) {
			$title = trim( get_the_title( $id ) . ' ' . $sep . ' ' . $site );
		}
		$title = trim( wp_strip_all_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) ) );
		$len   = self::strlen( $title );

		if ( '' === trim( get_the_title( $id ) ) ) {
			$this->add( 'title-length', 'seo', 'error', null, __( 'The page has no title. It is used as the search result headline.', 'brik-builder' ) );
		} elseif ( $len > 60 ) {
			/* translators: %d: characters */
			$this->add( 'title-length', 'seo', 'warning', null, sprintf( __( 'Title is %d characters; search results cut it off around 60.', 'brik-builder' ), $len ), array( 'data' => array( 'title' => $title ) ) );
		} elseif ( $len < 20 ) {
			/* translators: %d: characters */
			$this->add( 'title-length', 'seo', 'warning', null, sprintf( __( 'Title is only %d characters. A descriptive 30–60 character title earns more clicks.', 'brik-builder' ), $len ), array( 'data' => array( 'title' => $title ) ) );
		}

		// Description.
		$desc        = '';
		$desc_source = '';
		if ( $yoast && get_post_meta( $id, '_yoast_wpseo_metadesc', true ) ) {
			$desc        = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
			$desc        = function_exists( 'wpseo_replace_vars' ) ? wpseo_replace_vars( $desc, $this->post ) : $desc;
			$desc_source = 'yoast';
		} elseif ( $rank && get_post_meta( $id, 'rank_math_description', true ) ) {
			$desc        = (string) get_post_meta( $id, 'rank_math_description', true );
			$desc_source = 'rankmath';
		} elseif ( '' !== trim( $this->post->post_excerpt ) ) {
			$desc        = $this->post->post_excerpt;
			$desc_source = 'excerpt';
		}
		$desc  = trim( wp_strip_all_tags( $desc ) );
		$dlen  = self::strlen( $desc );
		$where = $yoast ? __( 'in Yoast SEO', 'brik-builder' ) : ( $rank ? __( 'in Rank Math', 'brik-builder' ) : __( 'as the page excerpt (or install an SEO plugin)', 'brik-builder' ) );
		if ( '' === $desc ) {
			/* translators: %s: where to set it */
			$this->add( 'meta-description', 'seo', 'warning', null, sprintf( __( 'No meta description. Write a 120–160 character summary %s; otherwise search engines pick a random snippet.', 'brik-builder' ), $where ) );
		} elseif ( $dlen > 160 ) {
			/* translators: %d: characters */
			$this->add( 'meta-description', 'seo', 'info', null, sprintf( __( 'Meta description is %d characters; it will be truncated after about 160.', 'brik-builder' ), $dlen ) );
		} elseif ( $dlen < 70 ) {
			/* translators: %d: characters */
			$this->add( 'meta-description', 'seo', 'info', null, sprintf( __( 'Meta description is short (%d characters). Aim for 120–160.', 'brik-builder' ), $dlen ) );
		}

		// Indexing.
		$noindex = false;
		if ( $yoast && '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			$noindex = true;
		}
		if ( $rank ) {
			$robots = get_post_meta( $id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) {
				$noindex = true;
			}
		}
		if ( $noindex ) {
			$this->add( 'noindex', 'seo', 'error', null, __( 'This page is set to noindex: search engines will drop it from results.', 'brik-builder' ) );
		}
		if ( ! get_option( 'blog_public' ) ) {
			$this->add( 'noindex', 'seo', 'warning', null, __( 'The whole site discourages search engines (Settings → Reading).', 'brik-builder' ) );
		}

		$canonical = '';
		if ( $yoast ) {
			$canonical = (string) get_post_meta( $id, '_yoast_wpseo_canonical', true );
		} elseif ( $rank ) {
			$canonical = (string) get_post_meta( $id, 'rank_math_canonical_url', true );
		}
		$permalink = get_permalink( $id );
		if ( $canonical && untrailingslashit( $canonical ) !== untrailingslashit( (string) $permalink ) ) {
			/* translators: %s: URL */
			$this->add( 'canonical', 'seo', 'info', null, sprintf( __( 'Canonical URL points elsewhere (%s), so this page won\'t rank on its own.', 'brik-builder' ), $canonical ) );
		}

		// Structured data hints: informational only.
		if ( 'product' === $this->post->post_type && class_exists( 'WooCommerce' ) ) {
			$this->add( 'structured-data', 'seo', 'info', null, __( 'WooCommerce adds Product structured data to this page.', 'brik-builder' ) );
		} elseif ( 'post' === $this->post->post_type && ! $yoast && ! $rank ) {
			$this->add( 'structured-data', 'seo', 'info', null, __( 'No SEO plugin detected, so no Article structured data is output for this post.', 'brik-builder' ) );
		}

		$this->meta = array_merge(
			$this->meta,
			array(
				'title'              => $title,
				'title_length'       => $len,
				'title_source'       => $source,
				'description'        => $desc,
				'description_length' => $dlen,
				'description_source' => $desc_source,
				'canonical'          => $canonical ? $canonical : $permalink,
				'noindex'            => $noindex,
				'seo_plugin'         => $yoast ? 'yoast' : ( $rank ? 'rankmath' : '' ),
				'url'                => $permalink,
			)
		);
	}
}
