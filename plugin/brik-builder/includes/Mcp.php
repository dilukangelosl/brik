<?php
namespace Brik;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * MCP server over the "Streamable HTTP" transport, answering with plain JSON (no SSE).
 *
 * Endpoint: POST /wp-json/brik/v1/mcp. Authentication is regular WordPress REST auth,
 * normally an Application Password sent as HTTP Basic. The server is stateless: the session
 * id handed out on initialize is informational only.
 */
final class Mcp {

	const ROUTE = '/mcp';

	const VERSIONS = array( '2025-06-18', '2025-03-26', '2024-11-05' );

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		// After core's rest_send_allow_header(), which would list every registered method.
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 20, 3 );
	}

	public static function url() {
		return rest_url( Rest::NS . self::ROUTE );
	}

	public static function routes() {
		register_rest_route(
			Rest::NS,
			self::ROUTE,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'handle' ),
					'permission_callback' => array( __CLASS__, 'permission' ),
				),
				array(
					// SSE streams and session deletion are not offered.
					'methods'             => 'GET, DELETE',
					'callback'            => static function () {
						$response = new WP_REST_Response(
							array(
								'jsonrpc' => '2.0',
								'id'      => null,
								'error'   => array(
									'code'    => -32600,
									'message' => 'Method not allowed. Send JSON-RPC messages with POST.',
								),
							),
							405
						);
						$response->header( 'Allow', 'POST' );
						return $response;
					},
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	public static function permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'brik_mcp_unauthorized', __( 'Authentication required. Use an Application Password (HTTP Basic auth).', 'brik-builder' ), array( 'status' => 401 ) );
		}
		if ( ! Settings::get( 'mcp_enabled' ) ) {
			return new WP_Error( 'brik_mcp_disabled', __( 'The Brik MCP server is turned off in Brik → Settings.', 'brik-builder' ), array( 'status' => 403 ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error( 'brik_mcp_forbidden', __( 'Your account is not allowed to edit content.', 'brik-builder' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Adds the auth challenge to 401s and turns WordPress' own invalid-JSON error into a
	 * JSON-RPC parse error, for this route only.
	 */
	public static function post_dispatch( $response, $server, $request ) {
		if ( ! $request instanceof WP_REST_Request || '/' . Rest::NS . self::ROUTE !== untrailingslashit( $request->get_route() ) ) {
			return $response;
		}
		if ( ! $response instanceof WP_REST_Response ) {
			return $response;
		}
		$data = $response->get_data();
		if ( 400 === $response->get_status() && is_array( $data ) && isset( $data['code'] ) && 'rest_invalid_json' === $data['code'] ) {
			if ( ! is_user_logged_in() ) {
				$response = new WP_REST_Response( array( 'code' => 'brik_mcp_unauthorized', 'message' => __( 'Authentication required.', 'brik-builder' ) ), 401 );
			} else {
				$response = new WP_REST_Response( self::error( null, -32700, 'Parse error: the body is not valid JSON.' ), 400 );
			}
		}
		if ( 401 === $response->get_status() ) {
			$response->header( 'WWW-Authenticate', 'Basic realm="Brik"' );
		}
		if ( 405 === $response->get_status() ) {
			$response->header( 'Allow', 'POST' );
		}
		return $response;
	}

	public static function handle( WP_REST_Request $request ) {
		$body = trim( (string) $request->get_body() );
		$data = '' === $body ? null : json_decode( $body, true );
		if ( '' === $body || JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_REST_Response( self::error( null, -32700, 'Parse error: the body is not valid JSON.' ), 400 );
		}
		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( self::error( null, -32600, 'Invalid Request: expected a JSON-RPC object or batch.' ), 400 );
		}

		nocache_headers();
		$session = false;

		// A list is a batch; an object with string keys is a single message.
		$batch = '[' === $body[0];
		if ( $batch ) {
			if ( ! $data ) {
				return new WP_REST_Response( self::error( null, -32600, 'Invalid Request: empty batch.' ), 400 );
			}
			$replies = array();
			foreach ( $data as $message ) {
				$reply = self::process( $message, $session );
				if ( null !== $reply ) {
					$replies[] = $reply;
				}
			}
			$response = $replies ? new WP_REST_Response( $replies, 200 ) : new WP_REST_Response( null, 202 );
		} else {
			$reply    = self::process( $data, $session );
			$response = null === $reply ? new WP_REST_Response( null, 202 ) : new WP_REST_Response( $reply, 200 );
		}

		if ( $session ) {
			$response->header( 'Mcp-Session-Id', bin2hex( random_bytes( 16 ) ) );
		}
		return $response;
	}

	/**
	 * Handle one JSON-RPC message. Returns the reply, or null for notifications and responses.
	 */
	private static function process( $message, &$session ) {
		if ( ! is_array( $message ) || array_keys( $message ) === range( 0, count( $message ) - 1 ) ) {
			return self::error( null, -32600, 'Invalid Request: expected a JSON-RPC object.' );
		}
		$has_id = array_key_exists( 'id', $message );
		$id     = $has_id ? $message['id'] : null;
		if ( $has_id && ! is_null( $id ) && ! is_string( $id ) && ! is_int( $id ) && ! is_float( $id ) ) {
			return self::error( null, -32600, 'Invalid Request: id must be a string or number.' );
		}
		if ( ! isset( $message['jsonrpc'] ) || '2.0' !== $message['jsonrpc'] ) {
			return self::error( $id, -32600, 'Invalid Request: jsonrpc must be "2.0".' );
		}
		if ( ! isset( $message['method'] ) ) {
			// A client answering a server request; nothing to do in a stateless server.
			if ( $has_id && ( array_key_exists( 'result', $message ) || array_key_exists( 'error', $message ) ) ) {
				return null;
			}
			return self::error( $id, -32600, 'Invalid Request: missing method.' );
		}
		if ( ! is_string( $message['method'] ) ) {
			return self::error( $id, -32600, 'Invalid Request: method must be a string.' );
		}
		$method = $message['method'];
		$params = isset( $message['params'] ) ? $message['params'] : array();
		if ( ! is_array( $params ) ) {
			return $has_id ? self::error( $id, -32602, 'Invalid params: params must be an object.' ) : null;
		}

		if ( ! $has_id ) {
			// Notifications (notifications/initialized, notifications/cancelled, …) need no reply.
			return null;
		}

		try {
			$result = self::dispatch( $method, $params, $session );
		} catch ( \Throwable $e ) {
			return self::error( $id, -32603, 'Internal error: ' . $e->getMessage() );
		}
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_data();
			return self::error( $id, is_int( $code ) ? $code : -32603, $result->get_error_message() );
		}
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	private static function dispatch( $method, array $params, &$session ) {
		switch ( $method ) {
			case 'initialize':
				$session = true;
				return self::initialize( $params );
			case 'ping':
				return new \stdClass();
			case 'tools/list':
				return array( 'tools' => McpTools::listing() );
			case 'tools/call':
				return self::call_tool( $params );
			case 'resources/list':
				return array( 'resources' => self::resources() );
			case 'resources/templates/list':
				return array( 'resourceTemplates' => array() );
			case 'resources/read':
				return self::read_resource( $params );
			case 'prompts/list':
				return array( 'prompts' => self::prompts() );
			case 'prompts/get':
				return self::get_prompt( $params );
			case 'logging/setLevel':
				return new \stdClass();
		}
		return new WP_Error( 'method', 'Method not found: ' . $method, -32601 );
	}

	private static function error( $id, $code, $message ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
		);
	}

	private static function initialize( array $params ) {
		$asked   = isset( $params['protocolVersion'] ) ? (string) $params['protocolVersion'] : '';
		$version = in_array( $asked, self::VERSIONS, true ) ? $asked : self::VERSIONS[0];
		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools'     => array( 'listChanged' => false ),
				'resources' => new \stdClass(),
				'prompts'   => new \stdClass(),
			),
			'serverInfo'      => array(
				'name'    => 'brik',
				'title'   => 'Brik Builder',
				'version' => BRIK_VERSION,
			),
			'instructions'    => self::instructions(),
		);
	}

	private static function instructions() {
		$user = wp_get_current_user();
		return sprintf(
			'You are connected to the WordPress site "%1$s" (%2$s) as %3$s through Brik, a visual site builder. '
			. 'Pages are JSON trees: section > row > column > module, each node { "type", "attrs", "children" }; lone modules are wrapped automatically. '
			. 'Attributes are flat keys with optional @tablet, @mobile and @hover suffixes; use design tokens like var(--primary) and var(--muted-foreground) for colors. '
			. 'Before building, call get_guide once (full format and examples) and list_modules (available modules and fields). '
			. 'Create pages with create_page (draft unless the user asks to publish), edit them with get_page (outline: true) plus update_node / insert_nodes / move_node / remove_node, '
			. 'and build site headers/footers with create_template. Check the warnings in every result and share url / builder_url with the user so they can review or keep editing visually.',
			get_bloginfo( 'name' ),
			home_url( '/' ),
			$user->exists() ? $user->user_login : 'guest'
		);
	}

	private static function call_tool( array $params ) {
		$name = isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : '';
		if ( '' === $name || ! McpTools::exists( $name ) ) {
			return new WP_Error( 'params', 'Unknown tool: ' . $name, -32602 );
		}
		$args = isset( $params['arguments'] ) ? $params['arguments'] : array();
		if ( ! is_array( $args ) || ( $args && array_keys( $args ) === range( 0, count( $args ) - 1 ) ) ) {
			return new WP_Error( 'params', 'Invalid params: arguments must be an object.', -32602 );
		}

		list( $result, $warnings ) = McpTools::call( $name, $args );

		if ( is_wp_error( $result ) ) {
			$text = $result->get_error_message();
			if ( $warnings ) {
				$text .= "\n\nWarnings:\n- " . implode( "\n- ", $warnings );
			}
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => $text,
					),
				),
				'isError' => true,
			);
		}

		if ( is_string( $result ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => $result,
					),
				),
				'isError' => false,
			);
		}

		$result = (array) $result;
		if ( $warnings ) {
			$result['warnings'] = $warnings;
		}
		return array(
			'content'           => array(
				array(
					'type' => 'text',
					'text' => self::encode( $result ),
				),
			),
			'structuredContent' => $result ? $result : new \stdClass(),
			'isError'           => false,
		);
	}

	/**
	 * Pretty JSON for small payloads; big ones stay compact so they don't eat the client's context.
	 */
	private static function encode( $data ) {
		$flags   = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$compact = wp_json_encode( $data, $flags );
		return strlen( $compact ) < 8000 ? wp_json_encode( $data, $flags | JSON_PRETTY_PRINT ) : $compact;
	}

	/* ---------------------------------------------------------------------
	 * Resources.
	 * ------------------------------------------------------------------- */

	private static function resources() {
		return array(
			array(
				'uri'         => 'brik://guide',
				'name'        => 'guide',
				'title'       => 'Brik building guide',
				'description' => 'Tree format, attributes, design tokens, theme builder and examples.',
				'mimeType'    => 'text/markdown',
			),
			array(
				'uri'         => 'brik://modules',
				'name'        => 'modules',
				'title'       => 'Brik modules',
				'description' => 'Every module type with its content fields.',
				'mimeType'    => 'application/json',
			),
		);
	}

	private static function read_resource( array $params ) {
		$uri = isset( $params['uri'] ) ? (string) $params['uri'] : '';
		if ( 'brik://guide' === $uri ) {
			$text = McpTools::guide();
			$mime = 'text/markdown';
		} elseif ( 'brik://modules' === $uri ) {
			$text = self::encode( McpTools::list_modules( array() ) );
			$mime = 'application/json';
		} else {
			return new WP_Error( 'resource', 'Resource not found: ' . $uri, -32002 );
		}
		return array(
			'contents' => array(
				array(
					'uri'      => $uri,
					'mimeType' => $mime,
					'text'     => $text,
				),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Prompts.
	 * ------------------------------------------------------------------- */

	private static function prompt_defs() {
		return array(
			'build_landing_page' => array(
				'title'       => 'Build a landing page',
				'description' => 'Plan and build a complete landing page with Brik.',
				'arguments'   => array(
					'topic'    => array( 'Product, service or event the page is about.', true ),
					'audience' => array( 'Who the page is for.', false ),
					'sections' => array( 'Sections to include, comma separated (default: hero, features, pricing, FAQ, call to action).', false ),
					'style'    => array( 'Visual direction, e.g. "minimal", "bold dark", "playful".', false ),
				),
			),
			'build_header'       => array(
				'title'       => 'Build a site header',
				'description' => 'Create a header template with logo, navigation menu and call to action.',
				'arguments'   => array(
					'site_name' => array( 'Name or logo text (default: the site title).', false ),
					'links'     => array( 'Navigation links, comma separated, e.g. "Features, Pricing, Blog".', false ),
					'cta'       => array( 'Call-to-action button text.', false ),
					'style'     => array( 'light, dark or transparent.', false ),
				),
			),
			'build_footer'       => array(
				'title'       => 'Build a site footer',
				'description' => 'Create a footer template with link columns, social icons and copyright.',
				'arguments'   => array(
					'columns' => array( 'Link column titles, comma separated.', false ),
					'social'  => array( 'Social networks to link, comma separated (e.g. github, x, linkedin).', false ),
				),
			),
			'restyle_site'       => array(
				'title'       => 'Restyle the site',
				'description' => 'Change the global design system (palette, accent, radius, fonts).',
				'arguments'   => array(
					'brand' => array( 'Brand description or colors, e.g. "calm fintech, teal".', true ),
				),
			),
		);
	}

	private static function prompts() {
		$out = array();
		foreach ( self::prompt_defs() as $name => $def ) {
			$args = array();
			foreach ( $def['arguments'] as $arg => $info ) {
				$args[] = array(
					'name'        => $arg,
					'description' => $info[0],
					'required'    => $info[1],
				);
			}
			$out[] = array(
				'name'        => $name,
				'title'       => $def['title'],
				'description' => $def['description'],
				'arguments'   => $args,
			);
		}
		return $out;
	}

	private static function get_prompt( array $params ) {
		$name = isset( $params['name'] ) ? (string) $params['name'] : '';
		$defs = self::prompt_defs();
		if ( ! isset( $defs[ $name ] ) ) {
			return new WP_Error( 'params', 'Unknown prompt: ' . $name, -32602 );
		}
		$args = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();
		foreach ( $defs[ $name ]['arguments'] as $arg => $info ) {
			if ( $info[1] && ( ! isset( $args[ $arg ] ) || '' === trim( (string) $args[ $arg ] ) ) ) {
				return new WP_Error( 'params', 'Missing required prompt argument: ' . $arg, -32602 );
			}
		}
		$get = static function ( $key, $fallback = '' ) use ( $args ) {
			return isset( $args[ $key ] ) && '' !== trim( (string) $args[ $key ] ) ? sanitize_text_field( (string) $args[ $key ] ) : $fallback;
		};

		switch ( $name ) {
			case 'build_landing_page':
				$text = sprintf(
					"Build a landing page with Brik about: %1\$s.\nAudience: %2\$s.\nSections: %3\$s.\nVisual style: %4\$s.\n\n"
					. "Steps:\n1. Call get_guide and list_modules to learn the tree format and the modules available on this site.\n"
					. "2. Write real, specific copy (no lorem ipsum). Alternate section backgrounds (var(--background), var(--muted), a dark section) for rhythm, and use generous padding with @mobile variants.\n"
					. "3. Create the page with create_page as a draft, page_template \"brik-canvas.php\" if it should stand alone without the theme header and footer.\n"
					. "4. Read the warnings, fix any with update_node, then check it with render_preview.\n"
					. '5. Reply with the page url and builder_url.',
					$get( 'topic' ),
					$get( 'audience', 'general visitors' ),
					$get( 'sections', 'hero, features, social proof, pricing, FAQ, call to action' ),
					$get( 'style', 'clean and modern, shadcn/ui look' )
				);
				break;
			case 'build_header':
				$text = sprintf(
					"Create a header template with Brik.\nLogo text: %1\$s.\nNavigation: %2\$s.\nCall to action: %3\$s.\nStyle: %4\$s.\n\n"
					. "Steps:\n1. Call get_guide and list_modules (look for menu, logo and button modules).\n"
					. "2. If the site has no suitable menu, create one with create_menu.\n"
					. "3. Call create_template with area \"header\": one section with tag \"header\", sticky true, compact padding, a bottom border (border_width \"0 0 1px\"), and a row \"1/4,3/4\" with the logo left and the menu plus button right (column layout inline, justify flex-end, items center). On mobile the menu should collapse if the menu module supports it.\n"
					. '4. Report the template builder_url and which pages it applies to.',
					$get( 'site_name', get_bloginfo( 'name' ) ),
					$get( 'links', 'the main pages of the site' ),
					$get( 'cta', 'Get started' ),
					$get( 'style', 'light' )
				);
				break;
			case 'build_footer':
				$text = sprintf(
					"Create a footer template with Brik.\nLink columns: %1\$s.\nSocial links: %2\$s.\n\n"
					. "Steps:\n1. Call get_guide and list_modules.\n"
					. "2. Call create_template with area \"footer\": a dark section with tag \"footer\", a row with a brand column (site name + short tagline) and link columns, then a bottom row with \"© {year} {site_name}\" and social icons (brand:* icons, see search_icons).\n"
					. '3. Report the template builder_url.',
					$get( 'columns', 'Product, Company, Resources' ),
					$get( 'social', 'none' )
				);
				break;
			default:
				$text = sprintf(
					"Restyle this site's design system for: %s.\n\n"
					. "1. Call get_design_settings to see the current base palette, accent and tokens.\n"
					. "2. Pick a base (neutral|zinc|slate|stone) and either an accent or explicit oklch() token overrides for light and dark (primary, primary-foreground, ring, optionally accent and muted), a radius and fonts from the list.\n"
					. "3. Apply with update_design_settings and summarize the changes.",
					$get( 'brand' )
				);
		}

		return array(
			'description' => $defs[ $name ]['description'],
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => $text,
					),
				),
			),
		);
	}
}
