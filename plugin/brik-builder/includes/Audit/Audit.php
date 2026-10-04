<?php
namespace Brik\Audit;

use Brik\Builder;
use Brik\Data;
use Brik\McpTools;
use Brik\Plugin;
use Brik\Rest;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Quality audits: accessibility, SEO structure and responsive problems.
 *
 * The server half (Scanner) works from the tree and rendered HTML and powers the REST endpoint
 * and the MCP tools. The builder half (assets/src/builder/features/audit) adds the checks that
 * need a real layout and merges both.
 */
final class Audit {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'brik/mcp_tools', array( __CLASS__, 'mcp_tools' ) );
		add_filter( 'brik/mcp_guide', array( __CLASS__, 'mcp_guide' ) );
	}

	/* ---------------------------------------------------------------------
	 * REST.
	 * ------------------------------------------------------------------- */

	public static function routes() {
		$can_edit = static function ( WP_REST_Request $r ) {
			$id = (int) $r['post_id'];
			return $id ? current_user_can( 'edit_post', $id ) : current_user_can( 'edit_posts' );
		};

		register_rest_route(
			Rest::NS,
			'/audit/(?P<post_id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_audit' ),
					'permission_callback' => $can_edit,
					'args'                => self::audit_args(),
				),
				// The builder posts its unsaved tree so findings match what is on screen.
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_audit' ),
					'permission_callback' => $can_edit,
					'args'                => self::audit_args(),
				),
			)
		);

		register_rest_route(
			Rest::NS,
			'/audit/links',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_links' ),
				'permission_callback' => $can_edit,
				'args'                => array(
					'urls'     => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
					'external' => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'post_id'  => array( 'type' => 'integer' ),
				),
			)
		);

		register_rest_route(
			Rest::NS,
			'/audit/media',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'rest_media' ),
				'permission_callback' => $can_edit,
				'args'                => array(
					'urls'    => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
					'post_id' => array( 'type' => 'integer' ),
				),
			)
		);
	}

	private static function audit_args() {
		return array(
			'links'    => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'external' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		);
	}

	public static function rest_audit( WP_REST_Request $r ) {
		$post = get_post( (int) $r['post_id'] );
		if ( ! $post ) {
			return new WP_Error( 'brik_not_found', __( 'Post not found.', 'brik-builder' ), array( 'status' => 404 ) );
		}
		$tree = null;
		$body = $r->get_json_params();
		if ( is_array( $body ) && isset( $body['tree'] ) && is_array( $body['tree'] ) ) {
			$tree = Data::sanitize( Data::normalize( $body['tree'] ) );
		}
		return rest_ensure_response(
			Scanner::run(
				$post->ID,
				$tree,
				array(
					'links'    => (bool) $r['links'],
					'external' => (bool) $r['external'],
				)
			)
		);
	}

	public static function rest_links( WP_REST_Request $r ) {
		$urls = array_slice( array_filter( array_map( 'strval', (array) $r['urls'] ) ), 0, 200 );
		$urls = array_map( 'esc_url_raw', $urls );
		return rest_ensure_response( array( 'results' => Links::check( array_filter( $urls ), (bool) $r['external'] ) ) );
	}

	/**
	 * File sizes for uploaded images referenced by URL (including generated sizes).
	 */
	public static function rest_media( WP_REST_Request $r ) {
		$uploads = wp_get_upload_dir();
		$base    = set_url_scheme( $uploads['baseurl'], 'http' );
		$out     = array();
		foreach ( array_slice( (array) $r['urls'], 0, 300 ) as $url ) {
			$url   = (string) $url;
			$plain = strtok( set_url_scheme( $url, 'http' ), '?' );
			if ( 0 !== strpos( $plain, $base . '/' ) ) {
				continue;
			}
			$relative = rawurldecode( substr( $plain, strlen( $base ) + 1 ) );
			if ( false !== strpos( $relative, '..' ) ) {
				continue;
			}
			$file = trailingslashit( $uploads['basedir'] ) . $relative;
			if ( file_exists( $file ) ) {
				$out[ $url ] = (int) filesize( $file );
			}
		}
		return rest_ensure_response( array( 'sizes' => (object) $out ) );
	}

	/* ---------------------------------------------------------------------
	 * MCP.
	 * ------------------------------------------------------------------- */

	public static function mcp_tools( $defs ) {
		$defs['audit_page'] = array(
			'title'       => 'Audit page',
			'description' => 'Accessibility + SEO audit of a Brik page from its tree and rendered HTML: heading hierarchy, image alt text, empty links/buttons, generic link text, form labels, ARIA misuse, autoplay, contrast of explicit colours, target=_blank without noopener, affiliate links without rel=sponsored, broken links, FAQ-like accordions without FAQ schema, image weight and dimensions, title/meta description length, noindex/canonical. Returns 0-100 scores and findings, each with the node id and fix ids for apply_audit_fixes. Layout checks (computed contrast, focus styles, tap targets, overflow at each width) need the builder\'s Audit panel.',
			'read_only'   => true,
			'open_world'  => true,
			'props'       => array(
				'post_id'        => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'check_links'    => array(
					'type'        => 'boolean',
					'description' => 'Resolve internal links (default true).',
				),
				'external_links' => array(
					'type'        => 'boolean',
					'description' => 'Also HEAD-request external links, 3 s timeout each (default false).',
				),
				'category'       => array(
					'type' => 'string',
					'enum' => array( 'all', 'a11y', 'seo' ),
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'mcp_audit' ),
		);

		$defs['apply_audit_fixes'] = array(
			'title'       => 'Apply audit fixes',
			'description' => 'Applies fixes from audit_page and saves the page. Without "fixes", applies every fix marked safe (heading levels, FAQ schema, rel="noopener", alt text from the media library). Pass fixes to choose: [{ "rule": "img-alt-missing", "node_id": "abc123", "fix": "alt", "value": "Team at the summit" }]. "fix" picks one of the finding\'s fix ids (e.g. decorative, alt, text, level, contrast); "value" fills fixes that need text (alt, button text).',
			'props'       => array(
				'post_id' => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'fixes'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'rule'    => array( 'type' => 'string' ),
							'node_id' => array( 'type' => 'string' ),
							'fix'     => array( 'type' => 'string' ),
							'value'   => array( 'type' => 'string' ),
						),
						'required'   => array( 'rule', 'node_id' ),
					),
				),
			),
			'required'    => array( 'post_id' ),
			'callback'    => array( __CLASS__, 'mcp_apply' ),
		);

		return $defs;
	}

	private static function editable( $post_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || 'trash' === $post->post_status ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No post with id %d.', 'brik-builder' ), (int) $post_id ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_forbidden', sprintf( __( 'You are not allowed to edit post %d.', 'brik-builder' ), $post->ID ) );
		}
		if ( ! Plugin::supports( $post ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_unsupported', sprintf( __( 'Brik is not enabled for the post type "%s".', 'brik-builder' ), $post->post_type ) );
		}
		return $post;
	}

	public static function mcp_audit( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$report = Scanner::run(
			$post->ID,
			null,
			array(
				'links'    => ! isset( $a['check_links'] ) || $a['check_links'],
				'external' => ! empty( $a['external_links'] ),
			)
		);
		$cat = isset( $a['category'] ) ? $a['category'] : 'all';

		$findings = array();
		foreach ( $report['findings'] as $f ) {
			if ( 'all' !== $cat && $cat !== $f['category'] ) {
				continue;
			}
			$item = array(
				'rule'     => $f['rule'],
				'category' => $f['category'],
				'severity' => $f['severity'],
				'node_id'  => $f['node'],
				'element'  => $f['label'],
				'message'  => $f['message'],
			);
			if ( ! empty( $f['data'] ) ) {
				$item['data'] = $f['data'];
			}
			if ( ! empty( $f['fixes'] ) ) {
				$item['fixes'] = array_map(
					static function ( $fix ) {
						$out = array(
							'fix'   => $fix['id'],
							'label' => $fix['label'],
							'safe'  => $fix['safe'],
						);
						if ( ! empty( $fix['prompt'] ) ) {
							$out['needs_value'] = true;
							$out['suggestion']  = $fix['prompt']['value'];
						}
						return $out;
					},
					$f['fixes']
				);
			}
			$findings[] = $item;
		}

		if ( ! $findings ) {
			McpTools::warn( __( 'No server-side issues found. Open the page in the builder and use the Audit panel for layout checks (contrast, focus, tap targets, responsive overflow).', 'brik-builder' ) );
		}

		return array(
			'post_id'     => $post->ID,
			'scores'      => $report['scores'],
			'counts'      => self::counts( $report['findings'] ),
			'findings'    => $findings,
			'outline'     => $report['outline'],
			'meta'        => $report['meta'],
			'builder_url' => Builder::url( $post->ID ),
		);
	}

	private static function counts( array $findings ) {
		$out = array(
			'error'   => 0,
			'warning' => 0,
			'info'    => 0,
		);
		foreach ( $findings as $f ) {
			++$out[ $f['severity'] ];
		}
		return $out;
	}

	public static function mcp_apply( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! Data::enabled( $post->ID ) ) {
			return new WP_Error( 'brik_not_built', __( 'This post is not built with Brik.', 'brik-builder' ) );
		}
		$selection = null;
		if ( ! empty( $a['fixes'] ) && is_array( $a['fixes'] ) ) {
			$selection = array();
			foreach ( $a['fixes'] as $f ) {
				if ( ! is_array( $f ) || empty( $f['rule'] ) || empty( $f['node_id'] ) ) {
					continue;
				}
				$item = array(
					'rule' => sanitize_key( $f['rule'] ),
					'node' => sanitize_key( $f['node_id'] ),
				);
				if ( ! empty( $f['fix'] ) ) {
					$item['fix'] = sanitize_key( $f['fix'] );
				}
				if ( isset( $f['value'] ) ) {
					$item['value'] = sanitize_text_field( (string) $f['value'] );
				}
				$selection[] = $item;
			}
		}
		$result = Fixer::apply( $post->ID, $selection );
		foreach ( $result['skipped'] as $s ) {
			/* translators: 1: rule, 2: node id, 3: reason */
			McpTools::warn( sprintf( __( 'Skipped %1$s on %2$s: %3$s.', 'brik-builder' ), $s['rule'], (string) $s['node'], $s['reason'] ) );
		}
		$result['builder_url'] = Builder::url( $post->ID );
		return $result;
	}

	public static function mcp_guide( $guide ) {
		return $guide . "\n\n## Quality audit\n\n"
			. "Run `audit_page` after building or editing a page. Fix errors first: give every image alt text (or mark it decorative with `decorative: true` on image/gallery), keep one H1 and don't skip heading levels, give icon-only buttons text, label form fields. "
			. "`apply_audit_fixes` without arguments applies the safe fixes; pass `fixes` with `value` for alt text or button text. Re-run `audit_page` to confirm. "
			. "Accordions whose titles are questions should have `faq_schema: true`.\n";
	}
}
