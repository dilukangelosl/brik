<?php
namespace Brik\Perf;

use Brik\Data;
use Brik\Fonts;
use Brik\Modules;
use Brik\Renderer;
use Brik\Settings;
use Brik\ThemeBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Server-side performance report for a page: what it loads, how heavy it is, a 0–100 score
 * and plain-language suggestions with the savings they'd bring.
 */
final class Analyzer {

	/** Score weights (sum 100). */
	const WEIGHTS = array(
		'js'          => 20,
		'css'         => 20,
		'dom'         => 15,
		'images'      => 25,
		'fonts'       => 10,
		'third_party' => 10,
	);

	/**
	 * Render a post the way a visitor would get it: its own tree plus the header, body and
	 * footer templates that apply to it.
	 *
	 * @param int        $post_id Post.
	 * @param array|null $tree    Tree to use instead of the saved one (unsaved builder state).
	 * @return array Items in the shape Assets/PageCss use.
	 */
	public static function render( $post_id, $tree = null ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}
		$tree = null === $tree ? Data::get( $post->ID ) : Data::normalize( $tree );

		if ( ThemeBuilder::is_template( $post->ID ) ) {
			$area = ThemeBuilder::area( $post->ID );
			return array( self::item( $post->ID, $tree, $area, 'header' !== $area && 'footer' !== $area ) );
		}

		$restore = self::query( $post );
		$items   = array();
		$body    = ThemeBuilder::resolve( 'body' );
		$items[] = self::item( $post->ID, $tree, '', ! $body );
		foreach ( array( 'header', 'body', 'footer' ) as $area ) {
			$id = ThemeBuilder::resolve( $area );
			if ( $id && $id !== $post->ID ) {
				$items[] = self::item( $id, Data::get( $id ), $area, 'body' === $area );
			}
		}
		$restore();
		return $items;
	}

	private static function item( $post_id, array $tree, $area, $main ) {
		$renderer = new Renderer( $post_id );
		$html     = $renderer->render_root( $tree, $area ? 'brik-template' : 'brik-content' );
		$page     = Data::page_settings( $post_id );
		$root     = array();
		foreach ( $renderer->root as $node ) {
			$root[] = isset( $node['id'] ) ? $node['id'] : '';
		}
		return array(
			'post_id' => (int) $post_id,
			'html'    => $html,
			'css'     => $renderer->style->css() . ( ! empty( $page['custom_css'] ) ? $page['custom_css'] : '' ),
			'fonts'   => $renderer->style->fonts(),
			'fx'      => array_keys( $renderer->scripts ),
			'effects' => $renderer->effects,
			'types'   => $renderer->types,
			'root'    => $root,
			'area'    => $area,
			'main'    => $main,
			'tree'    => $tree,
		);
	}

	/**
	 * Point the main query at a post so template conditions and dynamic tags resolve as they
	 * would on its page. Returns a function that puts everything back.
	 */
	private static function query( \WP_Post $post ) {
		global $wp_query, $wp_the_query;
		$saved = array( $wp_query, $wp_the_query, isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null );

		$args = 'page' === $post->post_type ? array( 'page_id' => $post->ID ) : array(
			'p'         => $post->ID,
			'post_type' => $post->post_type,
		);
		$args['post_status'] = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$query               = new \WP_Query( $args );
		$wp_query            = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$wp_the_query        = $query; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['post']     = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );
		self::forget_templates();

		return static function () use ( $saved ) {
			global $wp_query, $wp_the_query;
			list( $wp_query, $wp_the_query, $GLOBALS['post'] ) = $saved; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			if ( $GLOBALS['post'] ) {
				setup_postdata( $GLOBALS['post'] );
			}
			self::forget_templates();
		};
	}

	/** Template resolution is cached per request; clear it when the query changes. */
	private static function forget_templates() {
		$prop = new \ReflectionProperty( ThemeBuilder::class, 'resolved' );
		$prop->setAccessible( true );
		$prop->setValue( null, array() );
	}

	/**
	 * Full report.
	 *
	 * @param int        $post_id Post.
	 * @param array|null $tree    Optional unsaved tree.
	 */
	public static function report( $post_id, $tree = null ) {
		$items = self::render( $post_id, $tree );
		if ( ! $items ) {
			return new \WP_Error( 'brik_not_found', __( 'Post not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		$main_tree = $items[0]['tree'];

		$html     = '';
		$visible  = '';
		$node_css = '';
		$fonts    = Settings::fonts();
		$fx       = array();
		$effects  = false;
		$types    = array();
		$embedded = self::embeds_post_content( $items );
		foreach ( $items as $i => $item ) {
			$html     .= $item['html'];
			$node_css .= $item['css'];
			$fonts     = array_merge( $fonts, $item['fonts'] );
			$fx        = array_merge( $fx, $item['fx'] );
			$effects   = $effects || $item['effects'] || $item['fx'];
			// The page itself is printed inside the body template's post content module.
			if ( ! ( 0 === $i && $embedded ) ) {
				$visible .= $item['html'];
			}
			foreach ( $item['types'] as $type => $n ) {
				$types[ $type ] = ( isset( $types[ $type ] ) ? $types[ $type ] : 0 ) + $n;
			}
		}
		$fx = array_values( array_unique( $fx ) );

		$page    = self::page_css( $items, $node_css, $effects );
		$js      = self::js( $page['scripts'], $fx, $visible );
		$dom     = self::dom( $visible );
		$images  = Images::inspect( $main_tree, $post_id );
		foreach ( $items as $i => $item ) {
			if ( $i > 0 ) {
				$images = array_merge( $images, Images::inspect( $item['tree'], $item['post_id'] ) );
			}
		}
		$font_info   = self::fonts( $fonts );
		$third_party = self::third_party( $visible, $images );
		$modules     = self::modules( $types );

		$scores = array(
			'js'          => self::score_js( $js ),
			'css'         => self::score_css( $page ),
			'dom'         => self::score_dom( $dom ),
			'images'      => self::score_images( $images ),
			'fonts'       => self::score_fonts( $font_info ),
			'third_party' => max( 0, 100 - 20 * count( $third_party['hosts'] ) ),
		);
		$total = 0;
		foreach ( self::WEIGHTS as $k => $w ) {
			$total += $scores[ $k ] * $w;
		}

		$report = array(
			'post_id'     => (int) $post_id,
			'score'       => (int) round( $total / 100 ),
			'scores'      => $scores,
			'weights'     => self::WEIGHTS,
			'modules'     => $modules,
			'js'          => $js,
			'css'         => $page['summary'],
			'dom'         => $dom,
			'html'        => array(
				'bytes' => strlen( $visible ),
				'gzip'  => self::gzip( $visible ),
			),
			'images'      => self::image_summary( $images ),
			'fonts'       => $font_info,
			'third_party' => $third_party,
			'optimized'   => (bool) Settings::get( 'perf_assets' ),
		);
		$report['findings'] = self::findings( $report, $images );
		$report['summary']  = self::summary( $report );
		return $report;
	}

	private static function embeds_post_content( array $items ) {
		foreach ( $items as $i => $item ) {
			if ( $i > 0 && 'body' === $item['area'] && ! empty( $item['types']['post_content'] ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Measurements.
	 * ------------------------------------------------------------------- */

	private static function page_css( array $items, $node_css, $effects ) {
		$built   = PageCss::build(
			$items,
			array(
				'key'   => (string) $items[0]['post_id'],
				'store' => false,
			)
		);
		$library = PageCss::library_bytes( 'frontend' ) + ( $effects ? PageCss::library_bytes( 'effects' ) : 0 );
		$full    = $library + strlen( Settings::css() ) + strlen( $node_css );
		$opt     = strlen( $built['css'] );

		$full_css = (string) file_get_contents( BRIK_DIR . 'assets/build/frontend.css' ) . ( $effects ? (string) file_get_contents( BRIK_DIR . 'assets/build/effects.css' ) : '' ) . Settings::css() . $node_css; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local files.
		return array(
			'scripts' => $built['scripts'],
			'summary' => array(
				'full'          => $full,
				'full_gzip'     => self::gzip( $full_css ),
				'optimized'     => $opt,
				'optimized_gzip' => self::gzip( $built['css'] ),
				'critical'      => strlen( $built['critical'] ),
				'element'       => strlen( $node_css ),
				'effects'       => (bool) $effects,
				'saving'        => max( 0, $full - $opt ),
				'rules'         => substr_count( $built['css'], '}' ),
			),
		);
	}

	private static function js( array $scripts, array $fx, $html ) {
		$owners = self::script_owners( $scripts, $html );
		$list   = array();
		$total  = Scripts::bytes( 'core' );
		foreach ( $scripts as $name ) {
			$bytes   = Scripts::bytes( $name );
			$total  += $bytes;
			$list[]  = array(
				'name'   => $name,
				'kind'   => 'module',
				'bytes'  => $bytes,
				'nodes'  => isset( $owners[ $name ] ) ? $owners[ $name ] : array(),
			);
		}
		foreach ( $fx as $name ) {
			$bytes  = Scripts::fx_bytes( $name );
			$total += $bytes;
			$list[] = array(
				'name'  => $name,
				'kind'  => 'effect',
				'bytes' => $bytes,
				'nodes' => array(),
			);
		}
		$legacy = (int) @filesize( BRIK_DIR . 'assets/build/frontend.js' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		foreach ( $fx as $name ) {
			$legacy += Scripts::fx_bytes( $name );
		}
		return array(
			'core'    => Scripts::bytes( 'core' ),
			'scripts' => $list,
			'total'   => $total,
			'full'    => $legacy,
			'saving'  => max( 0, $legacy - $total ),
		);
	}

	/**
	 * Which elements make each script load: the nearest Brik element around each match.
	 *
	 * @return array script => [ [ id, type ], … ]
	 */
	private static function script_owners( array $scripts, $html ) {
		$doc = self::dom_document( $html );
		if ( ! $doc ) {
			return array();
		}
		$xpath    = new \DOMXPath( $doc );
		$manifest = Scripts::manifest();
		$out      = array();
		foreach ( $scripts as $name ) {
			$selectors = isset( $manifest['modules'][ $name ]['selectors'] ) ? $manifest['modules'][ $name ]['selectors'] : array();
			$seen      = array();
			foreach ( $selectors as $selector ) {
				$query = self::xpath( $selector );
				if ( ! $query ) {
					continue;
				}
				$nodes = @$xpath->query( $query ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				if ( ! $nodes ) {
					continue;
				}
				foreach ( $nodes as $el ) {
					for ( $n = $el; $n instanceof \DOMElement; $n = $n->parentNode ) {
						if ( preg_match( '/(?:^|\s)brik-n-([a-z0-9]+)(?:\s|$)/', $n->getAttribute( 'class' ), $m ) && preg_match( '/(?:^|\s)brik-el\s+brik-([a-z0-9_]+)(?:\s|$)/', $n->getAttribute( 'class' ), $t ) ) {
							if ( ! in_array( $t[1], array( 'section', 'row', 'column' ), true ) || $n === $el ) {
								$seen[ $m[1] ] = array(
									'id'   => $m[1],
									'type' => $t[1],
								);
								break;
							}
						}
					}
				}
			}
			$out[ $name ] = array_slice( array_values( $seen ), 0, 20 );
		}
		return $out;
	}

	/** XPath for the simple selectors scripts mount on: tag, .class, [attr] and descendants. */
	private static function xpath( $selector ) {
		$parts = preg_split( '/\s+/', trim( $selector ) );
		$path  = '';
		foreach ( $parts as $part ) {
			if ( ! preg_match( '/^([a-z][a-z0-9]*)?((?:\.[\w-]+|\[[\w-]+\])*)$/i', $part, $m ) || ( '' === $m[1] && '' === $m[2] ) ) {
				return '';
			}
			$cond = array();
			preg_match_all( '/\.([\w-]+)|\[([\w-]+)\]/', $m[2], $bits, PREG_SET_ORDER );
			foreach ( $bits as $bit ) {
				$cond[] = ! empty( $bit[1] ) ? "contains(concat(' ',normalize-space(@class),' '),' " . $bit[1] . " ')" : '@' . $bit[2];
			}
			$path .= '//' . ( $m[1] ? strtolower( $m[1] ) : '*' ) . ( $cond ? '[' . implode( ' and ', $cond ) . ']' : '' );
		}
		return $path;
	}

	private static function dom_document( $html ) {
		if ( '' === trim( $html ) || ! class_exists( '\DOMDocument' ) ) {
			return null;
		}
		$doc  = new \DOMDocument();
		$prev = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );
		return $doc;
	}

	/** Element count and nesting depth of the rendered markup. */
	public static function dom( $html ) {
		$count = preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)(?=[\s>\/])/', $html );
		$depth = 0;
		$doc   = self::dom_document( $html );
		if ( $doc ) {
			$body = $doc->getElementsByTagName( 'body' )->item( 0 );
			$depth = $body ? self::depth( $body ) - 1 : 0;
		}
		return array(
			'elements' => (int) $count,
			'depth'    => (int) $depth,
		);
	}

	private static function depth( \DOMNode $node ) {
		$max = 0;
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$max = max( $max, self::depth( $child ) );
			}
		}
		return $max + 1;
	}

	private static function fonts( array $families ) {
		$list = array();
		foreach ( LocalFonts::describe( $families ) as $font ) {
			$list[ $font['family'] ] = $font;
		}
		$mode = LocalFonts::mode();
		return array(
			'mode'     => $mode,
			'families' => array_values( $list ),
			'loading'  => 'google' === $mode ? __( 'Google Fonts stylesheet (third-party request)', 'brik-builder' ) : ( 'local' === $mode ? __( 'Self-hosted woff2 files, font-display: swap', 'brik-builder' ) : __( 'No web fonts (system font stack)', 'brik-builder' ) ),
		);
	}

	private static function third_party( $html, array $images ) {
		$site  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$hosts = array();
		$items = array();
		if ( preg_match_all( '/<(iframe|script|video|audio|source)\b[^>]*\ssrc\s*=\s*["\']([^"\']+)["\']/i', $html, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				$host = (string) wp_parse_url( html_entity_decode( $hit[2] ), PHP_URL_HOST );
				if ( $host && $host !== $site ) {
					$hosts[ $host ] = true;
					$items[]        = array(
						'kind' => strtolower( $hit[1] ),
						'host' => $host,
						'url'  => esc_url_raw( html_entity_decode( $hit[2] ) ),
					);
				}
			}
		}
		foreach ( $images as $img ) {
			if ( ! $img['local'] && $img['host'] && $img['host'] !== $site ) {
				$hosts[ $img['host'] ] = true;
				$items[]               = array(
					'kind' => 'image',
					'host' => $img['host'],
					'url'  => esc_url_raw( $img['url'] ),
				);
			}
		}
		if ( 'google' === LocalFonts::mode() ) {
			$hosts['fonts.googleapis.com'] = true;
			$hosts['fonts.gstatic.com']    = true;
		}
		return array(
			'hosts' => array_keys( $hosts ),
			'items' => array_slice( $items, 0, 40 ),
		);
	}

	private static function modules( array $types ) {
		$structural = 0;
		$list       = array();
		foreach ( $types as $type => $n ) {
			$def = Modules::get( $type );
			if ( in_array( $type, array( 'section', 'row', 'column' ), true ) ) {
				$structural += $n;
				continue;
			}
			$list[] = array(
				'type'  => $type,
				'title' => $def ? $def['title'] : $type,
				'count' => $n,
			);
		}
		usort(
			$list,
			static function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);
		return array(
			'total'      => array_sum( wp_list_pluck( $list, 'count' ) ),
			'types'      => count( $list ),
			'structural' => $structural,
			'list'       => $list,
		);
	}

	private static function image_summary( array $images ) {
		$bytes = 0;
		$after = 0;
		foreach ( $images as $img ) {
			$bytes += $img['bytes'];
			$after += $img['estimate'];
		}
		return array(
			'count'     => count( $images ),
			'bytes'     => $bytes,
			'estimate'  => $after,
			'heavy'     => count( array_filter( wp_list_pluck( $images, 'heavy' ) ) ),
			'oversized' => count( array_filter( wp_list_pluck( $images, 'oversized' ) ) ),
			'list'      => array_slice( $images, 0, 60 ),
		);
	}

	public static function gzip( $text ) {
		return function_exists( 'gzencode' ) ? strlen( (string) gzencode( (string) $text, 6 ) ) : 0;
	}

	/* ---------------------------------------------------------------------
	 * Scores (each 0–100).
	 * ------------------------------------------------------------------- */

	/** 100 up to 15 KB of script, then −1.2 per KB. */
	private static function score_js( array $js ) {
		$kb = $js['total'] / 1024;
		return self::clamp( 100 - max( 0, $kb - 15 ) * 1.2 );
	}

	/** 100 up to 20 KB of CSS for the page, then −1 per KB. */
	private static function score_css( array $page ) {
		$kb = $page['summary']['optimized'] / 1024;
		if ( ! Settings::get( 'perf_assets' ) ) {
			$kb = $page['summary']['full'] / 1024;
		}
		return self::clamp( 100 - max( 0, $kb - 20 ) );
	}

	/** 100 up to 800 elements (Lighthouse's warning level), 0 at 2,000. */
	private static function score_dom( array $dom ) {
		return self::clamp( 100 - max( 0, $dom['elements'] - 800 ) / 12 );
	}

	/** −15 per image over 300 KB, −10 per oversized image, −1 per 50 KB above 1.5 MB in total. */
	private static function score_images( array $images ) {
		$score = 100;
		$total = 0;
		foreach ( $images as $img ) {
			$total += $img['bytes'];
			if ( $img['heavy'] ) {
				$score -= 15;
			}
			if ( $img['oversized'] && $img['local'] ) {
				$score -= 10;
			}
		}
		$score -= max( 0, ( $total - 1572864 ) / 51200 );
		return self::clamp( $score );
	}

	/** Self-hosted or system fonts score best; −10 per family beyond two; Google costs 30. */
	private static function score_fonts( array $fonts ) {
		$n     = count( $fonts['families'] );
		$score = 100 - 10 * max( 0, $n - 2 );
		if ( 'google' === $fonts['mode'] ) {
			$score -= 30;
		}
		return self::clamp( $score );
	}

	private static function clamp( $v ) {
		return (int) max( 0, min( 100, round( $v ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Findings.
	 * ------------------------------------------------------------------- */

	private static function findings( array $r, array $images ) {
		$out = array();

		// Scripts.
		$module_scripts = array_values(
			array_filter(
				$r['js']['scripts'],
				static function ( $s ) {
					return 'module' === $s['kind'];
				}
			)
		);
		foreach ( $r['js']['scripts'] as $script ) {
			if ( $script['bytes'] < 2048 && 'module' === $script['kind'] ) {
				continue;
			}
			$node  = ! empty( $script['nodes'] ) ? $script['nodes'][0] : null;
			$out[] = array(
				'id'       => 'js-' . $script['name'],
				'category' => 'js',
				'severity' => $script['bytes'] > 10240 ? 'medium' : 'low',
				/* translators: 1: script name, 2: size */
				'title'    => sprintf( __( '%1$s loads %2$s of JavaScript', 'brik-builder' ), $script['name'], self::kb( $script['bytes'] ) ),
				'detail'   => $node
					/* translators: 1: element type, 2: size */
					? sprintf( __( 'Needed by the %1$s element. Removing it (and any other element using the same script) saves %2$s.', 'brik-builder' ), $node['type'], self::kb( $script['bytes'] ) )
					/* translators: %s: size */
					: sprintf( __( 'Only loads because an element on the page uses it. Removing those elements saves %s.', 'brik-builder' ), self::kb( $script['bytes'] ) ),
				'savings'  => array( 'js' => $script['bytes'] ),
				'node_id'  => $node ? $node['id'] : null,
			);
		}

		// Images.
		foreach ( $images as $img ) {
			if ( ! $img['heavy'] && ! ( $img['oversized'] && $img['local'] ) ) {
				continue;
			}
			$saving = max( 0, $img['bytes'] - $img['estimate'] );
			$tips   = array();
			if ( in_array( $img['format'], array( 'jpg', 'jpeg', 'png' ), true ) ) {
				$tips[] = __( 'convert to WebP', 'brik-builder' );
			}
			if ( $img['oversized'] ) {
				$tips[] = $img['suggest']
					/* translators: %s: image size name */
					? sprintf( __( 'use the "%s" size', 'brik-builder' ), $img['suggest'] )
					/* translators: %d: pixels */
					: sprintf( __( 'resize to about %dpx wide', 'brik-builder' ), 2 * $img['display'] );
			}
			$out[] = array(
				'id'       => 'img-' . $img['node_id'] . '-' . $img['field'] . '-' . md5( $img['url'] ),
				'category' => 'images',
				'severity' => $img['bytes'] > 1048576 ? 'high' : 'medium',
				'title'    => $img['oversized'] && $img['width']
					/* translators: 1: file name, 2: size, 3: width, 4: displayed width */
					? sprintf( __( '%1$s is %2$s and %3$dpx wide, shown at most %4$dpx', 'brik-builder' ), $img['name'], self::kb( $img['bytes'] ), $img['width'], $img['display'] )
					/* translators: 1: file name, 2: size */
					: sprintf( __( '%1$s is %2$s', 'brik-builder' ), $img['name'], self::kb( $img['bytes'] ) ),
				'detail'   => $tips
					/* translators: 1: suggested actions, 2: size saved */
					? sprintf( __( '%1$s: est. −%2$s.', 'brik-builder' ), ucfirst( implode( __( ' and ', 'brik-builder' ), $tips ) ), self::kb( $saving ) )
					: __( 'Compress this image before uploading it.', 'brik-builder' ),
				'savings'  => array( 'images' => $saving ),
				'node_id'  => $img['node_id'],
				'fix'      => $img['suggest'] && $img['resizable'] ? 'clean' : null,
			);
		}

		// CSS.
		$css = $r['css'];
		if ( ! $r['optimized'] ) {
			$out[] = array(
				'id'       => 'css-optimize',
				'category' => 'css',
				'severity' => 'high',
				/* translators: %s: size */
				'title'    => sprintf( __( 'The page loads the full %s stylesheet', 'brik-builder' ), self::kb( $css['full'] ) ),
				/* translators: %s: size */
				'detail'   => sprintf( __( 'Turn on optimized assets (Brik → Settings → Performance) to serve only the rules this page uses: −%s.', 'brik-builder' ), self::kb( $css['saving'] ) ),
				'savings'  => array( 'css' => $css['saving'] ),
				'node_id'  => null,
			);
		}

		// DOM.
		if ( $r['dom']['elements'] > 800 ) {
			$out[] = array(
				'id'       => 'dom-size',
				'category' => 'dom',
				'severity' => $r['dom']['elements'] > 1400 ? 'high' : 'medium',
				/* translators: %d: element count */
				'title'    => sprintf( __( 'The page has %d HTML elements', 'brik-builder' ), $r['dom']['elements'] ),
				'detail'   => __( 'Large pages take longer to lay out and use more memory. "Clean page" removes empty and redundant wrappers.', 'brik-builder' ),
				'savings'  => array(),
				'node_id'  => null,
				'fix'      => 'clean',
			);
		}

		// Fonts.
		if ( 'google' === $r['fonts']['mode'] ) {
			$out[] = array(
				'id'       => 'fonts-google',
				'category' => 'fonts',
				'severity' => 'medium',
				'title'    => __( 'Fonts load from Google Fonts', 'brik-builder' ),
				'detail'   => __( 'Switch font loading to "Self-hosted" to avoid two third-party connections and send no visitor data to Google.', 'brik-builder' ),
				'savings'  => array(),
				'node_id'  => null,
			);
		}
		if ( count( $r['fonts']['families'] ) > 2 ) {
			$out[] = array(
				'id'       => 'fonts-count',
				'category' => 'fonts',
				'severity' => 'low',
				/* translators: %d: number of font families */
				'title'    => sprintf( __( '%d font families are used', 'brik-builder' ), count( $r['fonts']['families'] ) ),
				'detail'   => implode( ', ', wp_list_pluck( $r['fonts']['families'], 'family' ) ) . '. ' . __( 'Each family adds font files to download; two are usually enough.', 'brik-builder' ),
				'savings'  => array(),
				'node_id'  => null,
			);
		}

		// Third parties.
		foreach ( $r['third_party']['items'] as $i => $item ) {
			if ( 'image' === $item['kind'] || $i > 10 ) {
				continue;
			}
			$out[] = array(
				'id'       => 'tp-' . md5( $item['url'] ),
				'category' => 'third_party',
				'severity' => 'low',
				/* translators: 1: element kind, 2: host */
				'title'    => sprintf( __( 'Embedded %1$s from %2$s', 'brik-builder' ), $item['kind'], $item['host'] ),
				'detail'   => __( 'Third-party embeds load their own scripts and styles. Brik loads iframes lazily; a click-to-load facade (video module) avoids them until needed.', 'brik-builder' ),
				'savings'  => array(),
				'node_id'  => null,
			);
		}

		$rank = array(
			'high'   => 0,
			'medium' => 1,
			'low'    => 2,
		);
		usort(
			$out,
			static function ( $a, $b ) use ( $rank ) {
				return $rank[ $a['severity'] ] - $rank[ $b['severity'] ];
			}
		);
		return $out;
	}

	/** One-paragraph plain-language summary. */
	private static function summary( array $r ) {
		$scripts = array();
		foreach ( $r['js']['scripts'] as $s ) {
			$scripts[] = $s['name'] . ' ' . self::kb( $s['bytes'] );
		}
		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: module count, 2: types, 3: scripts count, 4: script list */
			_n( 'Your page uses %1$d element (%2$d type); %3$d script loads: %4$s.', 'Your page uses %1$d elements (%2$d types); %3$d scripts load: %4$s.', $r['modules']['total'], 'brik-builder' ),
			$r['modules']['total'],
			$r['modules']['types'],
			count( $scripts ),
			$scripts ? implode( ', ', $scripts ) : __( 'none', 'brik-builder' )
		);
		$lines[] = sprintf(
			/* translators: 1: optimized CSS, 2: full CSS, 3: element count */
			__( 'CSS: %1$s for this page (the full library would be %2$s). %3$d HTML elements.', 'brik-builder' ),
			self::kb( $r['css']['optimized'] ),
			self::kb( $r['css']['full'] ),
			$r['dom']['elements']
		);
		if ( $r['images']['count'] && $r['images']['bytes'] ) {
			$lines[] = sprintf(
				/* translators: 1: image count, 2: total size, 3: flagged count */
				__( '%1$d images, %2$s from the media library, %3$d flagged.', 'brik-builder' ),
				$r['images']['count'],
				self::kb( $r['images']['bytes'] ),
				$r['images']['heavy'] + $r['images']['oversized']
			);
		} elseif ( $r['images']['count'] ) {
			$lines[] = sprintf(
				/* translators: %d: image count */
				_n( '%d image, hosted elsewhere (size not measured).', '%d images, hosted elsewhere (sizes not measured).', $r['images']['count'], 'brik-builder' ),
				$r['images']['count']
			);
		}
		return implode( ' ', $lines );
	}

	public static function kb( $bytes ) {
		if ( $bytes <= 0 ) {
			return '0 KB';
		}
		if ( $bytes >= 1048576 ) {
			return number_format_i18n( $bytes / 1048576, 1 ) . ' MB';
		}
		return number_format_i18n( max( 0.1, $bytes / 1024 ), $bytes < 10240 ? 1 : 0 ) . ' KB';
	}
}
