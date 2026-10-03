<?php
namespace Brik;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Tools exposed by the MCP server.
 *
 * Every tool receives its arguments as an array and returns an array (sent as structured
 * content), a string (sent as text) or a WP_Error (reported as a tool error, so the client
 * can read the message and correct itself).
 */
final class McpTools {

	const MAX_HTML = 12000;

	/** Warnings collected while a tool runs; returned alongside its result. */
	private static $warnings = array();

	private static $defs;

	/* ---------------------------------------------------------------------
	 * Registry.
	 * ------------------------------------------------------------------- */

	public static function definitions() {
		if ( null !== self::$defs ) {
			return self::$defs;
		}

		$tree = array(
			'type'        => 'array',
			'description' => 'Brik tree: a list of nodes { "type", "attrs", "children" }. Structure is section > row > column > module; lone modules are wrapped automatically. "id" is optional (generated). Call get_guide for the full format.',
			'items'       => self::node_schema(),
		);
		$post_id = array(
			'type'        => 'integer',
			'description' => 'ID of the page, post, template or library item.',
			'minimum'     => 1,
		);
		$node_id = array(
			'type'        => 'string',
			'description' => 'Node id from get_page (use outline: true to list ids cheaply).',
		);
		$page_settings = array(
			'type'                 => 'object',
			'description'          => 'Page settings: dark (whole page in the dark palette), hide_title (Brik theme hides the post title), body_class, custom_css (plain CSS for this page).',
			'properties'           => array(
				'dark'       => array( 'type' => 'boolean' ),
				'hide_title' => array( 'type' => 'boolean' ),
				'body_class' => array( 'type' => 'string' ),
				'custom_css' => array( 'type' => 'string' ),
			),
			'additionalProperties' => false,
		);
		$conditions = array(
			'type'        => 'array',
			'description' => 'Display conditions. Includes add places, excludes remove them (excludes win, more specific rules win). Default: [{ "type": "include", "rule": "entire_site" }].',
			'items'       => array(
				'type'       => 'object',
				'properties' => array(
					'type'      => array(
						'type' => 'string',
						'enum' => array( 'include', 'exclude' ),
					),
					'rule'      => array(
						'type'        => 'string',
						'enum'        => array_keys( ThemeBuilder::rule_types() ),
						'description' => 'singular/archive use post_type; term/in_term use taxonomy (+ optional term ids); post uses ids; author can use ids.',
					),
					'post_type' => array( 'type' => 'string' ),
					'taxonomy'  => array( 'type' => 'string' ),
					'ids'       => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'required'   => array( 'rule' ),
			),
		);
		$status = array(
			'type' => 'string',
			'enum' => array( 'draft', 'publish', 'pending', 'private' ),
		);
		$layout = array(
			'type'        => 'string',
			'description' => 'Bundled layout slug (see list_layouts) used as the starting tree when "tree" is not given, e.g. "header-default", "footer-default", "hero-centered".',
		);
		$slugs = wp_list_pluck( Library::bundled(), 'slug' );
		if ( $slugs ) {
			$layout['enum'] = array_values( $slugs );
		}

		$d = array();

		$d['get_guide'] = array(
			'title'       => 'Get the Brik building guide',
			'description' => 'Returns the complete guide to building with Brik: tree format (section > row > column > module), attribute conventions (@tablet/@mobile/@hover suffixes), column structures, design tokens such as var(--primary), dark sections, dynamic tags, presets, global elements, theme builder templates and worked examples. Read it once before creating or editing content.',
			'read_only'   => true,
			'props'       => array(),
		);

		$d['list_modules'] = array(
			'title'       => 'List modules',
			'description' => 'Compact list of every module type with its content fields (type, default, options). Use the "type" values as node types in trees. Every module also accepts the shared design/advanced attributes (margin, padding, bg_color, radius, shadow, font_size, text_color, animation, css_class, …); see get_module for those and for module-specific style fields.',
			'read_only'   => true,
			'props'       => array(
				'category' => array(
					'type'        => 'string',
					'enum'        => array_keys( Modules::categories() ),
					'description' => 'Only modules from this category.',
				),
			),
		);

		$d['get_module'] = array(
			'title'       => 'Get a module schema',
			'description' => 'Full field schema for one module type, including module-specific style fields and the shared design/advanced fields every element supports. Fields marked responsive accept key@tablet / key@mobile, fields marked hover accept key@hover.',
			'read_only'   => true,
			'props'       => array(
				'type'          => array(
					'type'        => 'string',
					'description' => 'Module type, e.g. "button".',
				),
				'include_common' => array(
					'type'        => 'boolean',
					'description' => 'Include the shared design/advanced fields (default true).',
				),
			),
			'required'    => array( 'type' ),
		);

		$d['list_content'] = array(
			'title'       => 'List pages and posts',
			'description' => 'Lists content the user can edit, newest first, with ids, status, URLs and whether it is built with Brik.',
			'read_only'   => true,
			'props'       => array(
				'post_type' => array(
					'type'        => 'string',
					'description' => 'Post type (default "page"; "any" for all Brik-enabled types).',
				),
				'search'    => array( 'type' => 'string' ),
				'only_brik' => array(
					'type'        => 'boolean',
					'description' => 'Only items built with Brik.',
				),
				'per_page'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				),
				'page'      => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
		);

		$d['get_page'] = array(
			'title'       => 'Get a page',
			'description' => 'Returns a page, post, template or library item: title, status, URLs (url, builder_url), page settings and its Brik tree. With outline: true, returns a compact outline (id, type, label, children) instead of full attributes — use it to find node ids before update_node / insert_nodes / move_node.',
			'read_only'   => true,
			'props'       => array(
				'id'      => $post_id,
				'outline' => array(
					'type'        => 'boolean',
					'description' => 'Return an outline instead of the full tree.',
				),
			),
			'required'    => array( 'id' ),
		);

		$d['create_page'] = array(
			'title'       => 'Create a page',
			'description' => 'Creates a page (or post / other Brik-enabled type) built with Brik from a full tree (or from a bundled "layout"). Returns id, url, builder_url (open it to keep editing visually), edit_url, an outline of the saved tree and warnings about unknown modules/attributes. Tip: page_template "brik-canvas.php" removes the theme header/footer; "brik-full-width.php" keeps them but drops the content container.',
			'props'       => array(
				'title'             => array( 'type' => 'string' ),
				'slug'              => array(
					'type'        => 'string',
					'description' => 'URL slug (optional).',
				),
				'post_type'         => array(
					'type'        => 'string',
					'description' => 'Default "page". Must be a post type Brik is enabled for.',
				),
				'status'            => array(
					'type' => 'string',
					'enum' => array( 'draft', 'publish' ),
				),
				'tree'              => $tree,
				'layout'            => $layout,
				'page_settings'     => $page_settings,
				'page_template'     => array(
					'type' => 'string',
					'enum' => array( '', 'brik-canvas.php', 'brik-full-width.php' ),
				),
				'set_as_front_page' => array(
					'type'        => 'boolean',
					'description' => 'Make this page the site front page (needs manage_options).',
				),
			),
			'required'    => array( 'title' ),
		);

		$d['update_page'] = array(
			'title'       => 'Update a page',
			'description' => 'Updates title, slug, status, page template, page settings and/or replaces the whole tree. For small edits prefer update_node / insert_nodes / remove_node, which keep the rest of the page intact.',
			'props'       => array(
				'id'            => $post_id,
				'title'         => array( 'type' => 'string' ),
				'slug'          => array( 'type' => 'string' ),
				'status'        => $status,
				'tree'          => array_merge( $tree, array( 'description' => 'Replaces the entire tree. ' . $tree['description'] ) ),
				'page_settings' => array_merge( $page_settings, array( 'description' => 'Merged into the current page settings. ' . $page_settings['description'] ) ),
				'page_template' => array(
					'type' => 'string',
					'enum' => array( '', 'brik-canvas.php', 'brik-full-width.php' ),
				),
			),
			'required'    => array( 'id' ),
		);

		$d['insert_nodes'] = array(
			'title'       => 'Insert nodes',
			'description' => 'Inserts nodes into an existing tree under parent_id (null = page root) at index (-1 = end). Nodes are wrapped only as much as the parent needs: modules inserted into a column stay modules, modules inserted at the root get a section > row > column. Returns the ids of the inserted nodes.',
			'props'       => array(
				'post_id'   => $post_id,
				'parent_id' => array(
					'type'        => array( 'string', 'null' ),
					'description' => 'Parent node id; null or omitted for the root.',
				),
				'index'     => array(
					'type'        => 'integer',
					'description' => 'Position among the parent\'s children, -1 = end (default).',
				),
				'nodes'     => $tree,
			),
			'required'    => array( 'post_id', 'nodes' ),
		);

		$d['update_node'] = array(
			'title'       => 'Update a node',
			'description' => 'Changes the attributes of one node. mode "merge" (default) keeps other attributes; "replace" swaps the whole attrs object. In merge mode a null value deletes that key (e.g. { "bg_color": null }). Responsive/hover keys use suffixes: "padding@mobile", "bg_color@hover".',
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => $node_id,
				'attrs'   => array(
					'type'        => 'object',
					'description' => 'Flat attribute map.',
				),
				'mode'    => array(
					'type' => 'string',
					'enum' => array( 'merge', 'replace' ),
				),
			),
			'required'    => array( 'post_id', 'node_id', 'attrs' ),
		);

		$d['remove_node'] = array(
			'title'       => 'Remove a node',
			'description' => 'Deletes a node and everything inside it.',
			'destructive' => true,
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => $node_id,
			),
			'required'    => array( 'post_id', 'node_id' ),
		);

		$d['move_node'] = array(
			'title'       => 'Move a node',
			'description' => 'Moves a node to another parent (null = root) and position (-1 = end). The node is wrapped if the new parent needs it (e.g. a module moved to the root gets a section).',
			'props'       => array(
				'post_id'   => $post_id,
				'node_id'   => $node_id,
				'parent_id' => array( 'type' => array( 'string', 'null' ) ),
				'index'     => array( 'type' => 'integer' ),
			),
			'required'    => array( 'post_id', 'node_id' ),
		);

		$d['duplicate_node'] = array(
			'title'       => 'Duplicate a node',
			'description' => 'Copies a node (with fresh ids for it and all descendants) and inserts the copy right after the original.',
			'props'       => array(
				'post_id' => $post_id,
				'node_id' => $node_id,
			),
			'required'    => array( 'post_id', 'node_id' ),
		);

		$d['list_templates'] = array(
			'title'       => 'List theme builder templates',
			'description' => 'Header, body and footer templates with status, conditions (and a plain-language summary) and builder links.',
			'read_only'   => true,
			'props'       => array(
				'area' => array(
					'type' => 'string',
					'enum' => array_keys( ThemeBuilder::areas() ),
				),
			),
		);

		$d['create_template'] = array(
			'title'       => 'Create a theme builder template',
			'description' => 'Creates a header, footer or body template. Published templates replace the theme header/footer (or the page body) wherever their conditions match. Fastest start: layout "header-default" or "footer-default" (no tree needed), then adjust with update_node. A header is usually one section with tag "header", sticky true, padding "12px 24px" and a row "1/4,3/4" holding a logo (site_logo or heading module) and a menu module (sticky: true on the section makes the header stick); a body template typically uses post modules (post title, content) — check list_modules category "site" and "post".',
			'props'       => array(
				'area'       => array(
					'type' => 'string',
					'enum' => array_keys( ThemeBuilder::areas() ),
				),
				'title'      => array( 'type' => 'string' ),
				'conditions' => $conditions,
				'tree'       => $tree,
				'layout'     => $layout,
				'status'     => array(
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish' ),
					'description' => 'Default "publish" (active immediately).',
				),
			),
			'required'    => array( 'area' ),
		);

		$d['update_template'] = array(
			'title'       => 'Update a theme builder template',
			'description' => 'Updates a template\'s title, tree (full replace), conditions or status (draft disables it).',
			'props'       => array(
				'id'         => $post_id,
				'title'      => array( 'type' => 'string' ),
				'tree'       => $tree,
				'conditions' => $conditions,
				'status'     => array(
					'type' => 'string',
					'enum' => array( 'draft', 'publish' ),
				),
			),
			'required'    => array( 'id' ),
		);

		$d['get_design_settings'] = array(
			'title'       => 'Get design settings',
			'description' => 'Global design system: base palette, accent, resolved light/dark token values, radius, fonts, container width, global colors and custom CSS.',
			'read_only'   => true,
			'props'       => array(),
		);

		$d['update_design_settings'] = array(
			'title'       => 'Update design settings',
			'description' => 'Changes the global design system for the whole site. base: neutral|zinc|slate|stone. accent: "" or blue|green|rose|orange|violet|red|yellow (sets --primary). tokens: per-mode overrides of shadcn variables, e.g. { "light": { "primary": "oklch(0.55 0.2 260)" }, "dark": {…} }. colors: global swatches [{ id, name, value }] usable as var(--brik-color-{id}). Only the keys you pass change.',
			'props'       => array(
				'base'         => array(
					'type' => 'string',
					'enum' => array_keys( Settings::bases() ),
				),
				'accent'       => array(
					'type' => 'string',
					'enum' => array_merge( array( '' ), array_keys( Settings::accents() ) ),
				),
				'tokens'       => array(
					'type'       => 'object',
					'properties' => array(
						'light' => array( 'type' => 'object' ),
						'dark'  => array( 'type' => 'object' ),
					),
				),
				'radius'       => array(
					'type'        => 'string',
					'description' => 'Base corner radius, e.g. "0.625rem".',
				),
				'font_body'    => array( 'type' => 'string' ),
				'font_heading' => array( 'type' => 'string' ),
				'container'    => array(
					'type'        => 'string',
					'description' => 'Max content width, e.g. "1200px".',
				),
				'colors'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'string' ),
							'name'  => array( 'type' => 'string' ),
							'value' => array( 'type' => 'string' ),
						),
						'required'   => array( 'value' ),
					),
				),
				'custom_css'   => array( 'type' => 'string' ),
			),
		);

		$d['list_presets'] = array(
			'title'       => 'List style presets',
			'description' => 'Saved style presets per module type. Apply one with attrs.preset = preset id; node attrs still win over the preset.',
			'read_only'   => true,
			'props'       => array(
				'type' => array(
					'type'        => 'string',
					'description' => 'Only presets for this module type.',
				),
			),
		);

		$d['save_preset'] = array(
			'title'       => 'Save a style preset',
			'description' => 'Creates or replaces a style preset for a module type. default: true applies it to every element of that type that has no preset of its own.',
			'props'       => array(
				'type'    => array( 'type' => 'string' ),
				'id'      => array(
					'type'        => 'string',
					'description' => 'Preset id (lowercase, e.g. "pill").',
				),
				'name'    => array( 'type' => 'string' ),
				'attrs'   => array( 'type' => 'object' ),
				'default' => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'type', 'id', 'attrs' ),
		);

		$d['delete_preset'] = array(
			'title'       => 'Delete a style preset',
			'description' => 'Deletes a style preset.',
			'destructive' => true,
			'props'       => array(
				'type' => array( 'type' => 'string' ),
				'id'   => array( 'type' => 'string' ),
			),
			'required'    => array( 'type', 'id' ),
		);

		$d['list_library'] = array(
			'title'       => 'List library items',
			'description' => 'Saved layouts, sections, rows and modules. Global items can be embedded with { "type": "global", "attrs": { "ref": ID } } so edits sync everywhere.',
			'read_only'   => true,
			'props'       => array(
				'kind' => array(
					'type' => 'string',
					'enum' => array_keys( Library::kinds() ),
				),
			),
		);

		$d['save_to_library'] = array(
			'title'       => 'Save to library',
			'description' => 'Saves a tree to the library. With global: true it becomes a global element: embed it with { "type": "global", "attrs": { "ref": <id> } } and edit it once to update every page.',
			'props'       => array(
				'title'  => array( 'type' => 'string' ),
				'kind'   => array(
					'type' => 'string',
					'enum' => array_keys( Library::kinds() ),
				),
				'tree'   => $tree,
				'global' => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'title', 'tree' ),
		);

		$d['list_layouts'] = array(
			'title'       => 'List bundled layouts',
			'description' => 'Ready-made layouts shipped with Brik (slug, title, category, kind). Pass slug to also get that layout\'s tree.',
			'read_only'   => true,
			'props'       => array(
				'slug'     => array( 'type' => 'string' ),
				'category' => array( 'type' => 'string' ),
			),
		);

		$d['insert_layout'] = array(
			'title'       => 'Insert a layout',
			'description' => 'Inserts a bundled layout (slug) or a library item (library_id) into a page at the root, at index (-1 = end).',
			'props'       => array(
				'post_id'    => $post_id,
				'slug'       => array( 'type' => 'string' ),
				'library_id' => array( 'type' => 'integer' ),
				'index'      => array( 'type' => 'integer' ),
			),
			'required'    => array( 'post_id' ),
		);

		$d['list_menus'] = array(
			'title'       => 'List navigation menus',
			'description' => 'Navigation menus with their items and the theme menu locations. Menu modules take a menu id.',
			'read_only'   => true,
			'props'       => array(),
		);

		$d['create_menu'] = array(
			'title'       => 'Create a navigation menu',
			'description' => 'Creates a navigation menu. Items link to a URL or to a post/page by post_id, and can nest via children. Optionally assigns it to a theme location. With replace: true an existing menu of the same name is emptied and rebuilt.',
			'props'       => array(
				'name'     => array( 'type' => 'string' ),
				'items'    => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'title'    => array( 'type' => 'string' ),
							'url'      => array( 'type' => 'string' ),
							'post_id'  => array( 'type' => 'integer' ),
							'children' => array(
								'type'  => 'array',
								'items' => array( 'type' => 'object' ),
							),
						),
					),
				),
				'location' => array(
					'type'        => 'string',
					'description' => 'Theme menu location slug (see list_menus).',
				),
				'replace'  => array( 'type' => 'boolean' ),
			),
			'required'    => array( 'name', 'items' ),
		);

		$d['upload_media'] = array(
			'title'       => 'Upload media from a URL',
			'description' => 'Downloads an image, video or PDF from a public URL into the media library. Returns id and url; use { "id": <id>, "url": <url>, "alt": "…" } in image attributes.',
			'open_world'  => true,
			'props'       => array(
				'url'     => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'title'   => array( 'type' => 'string' ),
				'alt'     => array( 'type' => 'string' ),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'Attach to this post (optional).',
				),
			),
			'required'    => array( 'url' ),
		);

		$d['render_preview'] = array(
			'title'       => 'Render a preview',
			'description' => 'Renders a saved page (post_id) or an unsaved tree to HTML exactly as the front end would, and reports CSS size and warnings (unknown module types, unknown attributes, invalid option values, column count mismatches). Use it to check a tree before saving.',
			'read_only'   => true,
			'props'       => array(
				'post_id'     => array(
					'type'        => 'integer',
					'description' => 'Saved post to render, or the context for a tree (dynamic tags).',
				),
				'tree'        => $tree,
				'max_chars'   => array(
					'type'        => 'integer',
					'description' => 'Trim HTML to this many characters (default 12000).',
				),
				'include_css' => array( 'type' => 'boolean' ),
			),
		);

		$d['search_icons'] = array(
			'title'       => 'Search icons',
			'description' => 'Finds Lucide icon names (and brand icons as "brand:name") by keyword, for icon attributes such as button.icon or icon.icon.',
			'read_only'   => true,
			'props'       => array(
				'query'  => array( 'type' => 'string' ),
				'limit'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				),
				'brands' => array(
					'type'        => 'boolean',
					'description' => 'Include brand logos (default true).',
				),
			),
			'required'    => array( 'query' ),
		);

		$d['update_site'] = array(
			'title'       => 'Update site settings',
			'description' => 'Sets the site title, tagline, front page and posts page. front_page_id 0 shows latest posts on the front page.',
			'props'       => array(
				'title'         => array( 'type' => 'string' ),
				'tagline'       => array( 'type' => 'string' ),
				'front_page_id' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
				'posts_page_id' => array(
					'type'    => 'integer',
					'minimum' => 0,
				),
			),
		);

		$field_schema = array(
			'type'        => 'object',
			'description' => 'Field: { "name" (meta key, a-z0-9_), "label", "type", "required", "default", "instructions", "placeholder", "width" (25|33|50|66|75|100), "conditions" ([[{ "field": "<field key>", "operator": "==|!=|empty|!empty|contains", "value" }]]), "options" }. "key" is generated when missing; keep existing keys when editing. Types and their options: see list_content_types → field_types. repeater/group take options.sub_fields (a list of fields).',
		);

		$d['list_content_types'] = array(
			'title'       => 'List content types and fields',
			'description' => 'Custom post types, taxonomies and field groups defined in Brik → Content (plus the field types and their options). Use the post type keys with create_entries / query_entries and field names in {field:name} dynamic tags.',
			'read_only'   => true,
			'props'       => array(
				'include_field_types' => array(
					'type'        => 'boolean',
					'description' => 'Include the field type catalogue with option schemas (default true).',
				),
			),
		);

		$d['save_post_type'] = array(
			'title'       => 'Create or update a post type',
			'description' => 'Saves a custom post type by key (creates it or updates the existing one). Requires manage_options. Example: { "key": "project", "singular": "Project", "plural": "Projects", "icon": "lucide:briefcase", "supports": ["title","editor","thumbnail","excerpt"], "taxonomies": ["project_type"], "has_archive": true, "rewrite_slug": "projects", "brik": true }. Keys: lowercase a-z0-9_-, at most 20 characters, not a WordPress reserved name.',
			'props'       => array(
				'definition'   => array(
					'type'        => 'object',
					'description' => 'Post type definition (see docs: key, singular, plural, labels, description, icon (dashicons-* or lucide:name), public, has_archive, archive_slug, rewrite_slug, hierarchical, show_in_rest, show_in_menu, menu_position, supports, taxonomies, exclude_from_search, capability_type, brik, active).',
				),
				'previous_key' => array(
					'type'        => 'string',
					'description' => 'Rename: the current key of the post type (existing posts keep the old type).',
				),
			),
			'required'    => array( 'definition' ),
		);

		$d['save_taxonomy'] = array(
			'title'       => 'Create or update a taxonomy',
			'description' => 'Saves a custom taxonomy by key. Requires manage_options. Example: { "key": "project_type", "singular": "Project type", "plural": "Project types", "hierarchical": true, "post_types": ["project"], "rewrite_slug": "project-type" }. Keys: at most 32 characters.',
			'props'       => array(
				'definition'   => array(
					'type'        => 'object',
					'description' => 'Taxonomy definition: key, singular, plural, labels, description, hierarchical, public, show_in_rest, show_admin_column, rewrite_slug, post_types, active.',
				),
				'previous_key' => array( 'type' => 'string' ),
			),
			'required'    => array( 'definition' ),
		);

		$d['save_field_group'] = array(
			'title'       => 'Create or update a field group',
			'description' => 'Saves a group of custom fields shown in the editor of the matching content. Requires manage_options. location is an OR-list of AND-lists of rules { "param": "post_type|taxonomy|post_template|page_type|post_status|user_role|options_page", "operator": "==|!=", "value" } — e.g. [[{"param":"post_type","operator":"==","value":"project"}]]. Omit "key" to create a new group (the result returns it); pass it to update. Field names must be unique across groups for the same content.',
			'props'       => array(
				'definition' => array(
					'type'        => 'object',
					'description' => 'Group: key (group_…), title, location, position (normal|side|after_title), style (card|seamless), order, active, fields (list of fields).',
					'properties'  => array(
						'fields' => array(
							'type'  => 'array',
							'items' => $field_schema,
						),
					),
				),
			),
			'required'    => array( 'definition' ),
		);

		$d['delete_post_type'] = array(
			'title'       => 'Delete a post type definition',
			'description' => 'Removes a custom post type definition. Posts stay in the database (hidden) unless delete_posts is true, which permanently deletes every post of the type.',
			'destructive' => true,
			'props'       => array(
				'key'          => array( 'type' => 'string' ),
				'delete_posts' => array(
					'type'        => 'boolean',
					'description' => 'Also permanently delete all posts of this type.',
				),
			),
			'required'    => array( 'key' ),
		);

		$d['create_entries'] = array(
			'title'       => 'Create entries of a post type',
			'description' => 'Bulk-creates posts of any type (up to 50) with title, content, status and custom field values by name. Values are sanitized like the editor does; image/file/gallery fields and featured_image accept public image URLs (downloaded into the media library) or attachment ids. terms maps a taxonomy to term names (created when missing) or ids. Returns ids, URLs and per-entry warnings.',
			'open_world'  => true,
			'props'       => array(
				'post_type' => array( 'type' => 'string' ),
				'entries'   => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'title'          => array( 'type' => 'string' ),
							'content'        => array(
								'type'        => 'string',
								'description' => 'Post content (HTML).',
							),
							'excerpt'        => array( 'type' => 'string' ),
							'status'         => $status,
							'slug'           => array( 'type' => 'string' ),
							'date'           => array( 'type' => 'string' ),
							'menu_order'     => array( 'type' => 'integer' ),
							'fields'         => array(
								'type'        => 'object',
								'description' => 'Field values by field name, e.g. { "price": 120, "gallery": ["https://…jpg"], "team": [{ "name": "Ada" }] }.',
							),
							'terms'          => array(
								'type'        => 'object',
								'description' => '{ "<taxonomy>": ["Term name", 12] }',
							),
							'featured_image' => array(
								'type'        => array( 'string', 'integer' ),
								'description' => 'Image URL or attachment id.',
							),
						),
					),
				),
			),
			'required'    => array( 'post_type', 'entries' ),
		);

		$d['query_entries'] = array(
			'title'       => 'Query entries',
			'description' => 'Finds posts of a type with their custom field values and terms. meta filters: [{ "field": "price", "compare": "=|!=|>|>=|<|<=|LIKE|IN|BETWEEN|EXISTS", "value": 100 }] (numeric/date types are inferred from the field).',
			'read_only'   => true,
			'props'       => array(
				'post_type' => array( 'type' => 'string' ),
				'search'    => array( 'type' => 'string' ),
				'meta'      => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'field'   => array( 'type' => 'string' ),
							'compare' => array( 'type' => 'string' ),
							'value'   => array( 'description' => 'Value, or a list for IN / BETWEEN.' ),
							'type'    => array( 'type' => 'string' ),
						),
					),
				),
				'terms'     => array(
					'type'        => 'object',
					'description' => '{ "<taxonomy>": ["slug"] }',
				),
				'status'    => array(
					'type' => 'string',
					'enum' => array( 'any', 'publish', 'draft', 'pending', 'private', 'future' ),
				),
				'orderby'   => array(
					'type'        => 'string',
					'description' => 'date | title | menu_order | modified | rand | a field name.',
				),
				'order'     => array(
					'type' => 'string',
					'enum' => array( 'ASC', 'DESC' ),
				),
				'per_page'  => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
				),
				'page'      => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'required'    => array( 'post_type' ),
		);

		if ( class_exists( 'WooCommerce' ) ) {
			$d = array_merge( $d, Woo\Mcp::definitions() );
		}

		self::$defs = apply_filters( 'brik/mcp_tools', $d );
		return self::$defs;
	}

	private static function node_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'     => array(
					'type'        => 'string',
					'description' => 'section | row | column | any module type from list_modules.',
				),
				'id'       => array(
					'type'        => 'string',
					'description' => 'Optional, 4-24 lowercase letters/digits.',
				),
				'attrs'    => array(
					'type'        => 'object',
					'description' => 'Flat attributes; responsive/hover variants as "key@tablet", "key@mobile", "key@hover".',
				),
				'children' => array(
					'type'        => 'array',
					'description' => 'Child nodes (sections hold rows, rows hold columns, columns hold modules or rows).',
					'items'       => array( 'type' => 'object' ),
				),
			),
			'required'   => array( 'type' ),
		);
	}

	/**
	 * Tool list for tools/list.
	 */
	public static function listing() {
		$out = array();
		foreach ( self::definitions() as $name => $def ) {
			$schema = array(
				'type'       => 'object',
				'properties' => $def['props'] ? $def['props'] : new \stdClass(),
			);
			if ( ! empty( $def['required'] ) ) {
				$schema['required'] = $def['required'];
			}
			$read_only = ! empty( $def['read_only'] );
			$out[]     = array(
				'name'        => $name,
				'title'       => $def['title'],
				'description' => $def['description'],
				'inputSchema' => $schema,
				'annotations' => array(
					'title'           => $def['title'],
					'readOnlyHint'    => $read_only,
					'destructiveHint' => ! empty( $def['destructive'] ),
					'idempotentHint'  => $read_only,
					'openWorldHint'   => ! empty( $def['open_world'] ),
				),
			);
		}
		return $out;
	}

	public static function exists( $name ) {
		return isset( self::definitions()[ $name ] );
	}

	/**
	 * Run a tool. Returns [ result, warnings ].
	 */
	public static function call( $name, array $args ) {
		self::$warnings = array();
		$def            = self::definitions()[ $name ];

		$args = self::check_args( $def, $args );
		if ( is_wp_error( $args ) ) {
			return array( $args, array() );
		}

		$callback = isset( $def['callback'] ) ? $def['callback'] : array( __CLASS__, $name );
		$result   = call_user_func( $callback, $args );
		return array( $result, array_values( array_unique( self::$warnings ) ) );
	}

	/**
	 * Minimal argument validation against the declared schema: required keys, top-level types
	 * and enums. Numeric strings and "true"/"false" are coerced, since some clients send them.
	 */
	private static function check_args( array $def, array $args ) {
		$props = $def['props'];
		foreach ( isset( $def['required'] ) ? $def['required'] : array() as $key ) {
			if ( ! array_key_exists( $key, $args ) || null === $args[ $key ] || '' === $args[ $key ] ) {
				/* translators: %s: argument name */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'Missing required argument "%s".', 'brik-builder' ), $key ) );
			}
		}
		foreach ( $args as $key => $value ) {
			if ( ! isset( $props[ $key ] ) ) {
				/* translators: %s: argument name */
				self::warn( sprintf( __( 'Ignored unknown argument "%s".', 'brik-builder' ), $key ) );
				unset( $args[ $key ] );
				continue;
			}
			$types = (array) ( isset( $props[ $key ]['type'] ) ? $props[ $key ]['type'] : array() );
			if ( null === $value && in_array( 'null', $types, true ) ) {
				continue;
			}
			if ( null === $value ) {
				unset( $args[ $key ] );
				continue;
			}
			$ok = false;
			foreach ( $types as $type ) {
				switch ( $type ) {
					case 'integer':
						if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^-?\d+$/', $value ) ) || ( is_float( $value ) && floor( $value ) === $value ) ) {
							$value = (int) $value;
							$ok    = true;
						}
						break;
					case 'number':
						if ( is_numeric( $value ) ) {
							$value = 0 + $value;
							$ok    = true;
						}
						break;
					case 'boolean':
						if ( is_bool( $value ) || in_array( $value, array( 'true', 'false', 0, 1, '0', '1' ), true ) ) {
							$value = is_bool( $value ) ? $value : in_array( $value, array( 'true', 1, '1' ), true );
							$ok    = true;
						}
						break;
					case 'string':
						if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
							$value = (string) $value;
							$ok    = true;
						}
						break;
					case 'array':
						// Some clients send nested JSON as a string.
						if ( is_string( $value ) && ( $decoded = json_decode( $value, true ) ) !== null && is_array( $decoded ) ) {
							$value = $decoded;
						}
						$ok = is_array( $value ) && ( ! $value || array_keys( $value ) === range( 0, count( $value ) - 1 ) || isset( $value['type'] ) );
						if ( $ok && isset( $value['type'] ) ) {
							$value = array( $value );
						}
						break;
					case 'object':
						if ( is_string( $value ) && ( $decoded = json_decode( $value, true ) ) !== null && is_array( $decoded ) ) {
							$value = $decoded;
						}
						$ok = is_array( $value ) && ( ! $value || array_keys( $value ) !== range( 0, count( $value ) - 1 ) );
						break;
					default:
						$ok = true;
				}
				if ( $ok ) {
					break;
				}
			}
			if ( ! $ok ) {
				/* translators: 1: argument name, 2: expected type */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'Argument "%1$s" must be of type %2$s.', 'brik-builder' ), $key, implode( ' or ', $types ) ) );
			}
			if ( isset( $props[ $key ]['enum'] ) && ! in_array( $value, $props[ $key ]['enum'], true ) ) {
				/* translators: 1: argument name, 2: allowed values */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'Argument "%1$s" must be one of: %2$s.', 'brik-builder' ), $key, implode( ', ', array_map( 'wp_json_encode', $props[ $key ]['enum'] ) ) ) );
			}
			$args[ $key ] = $value;
		}
		return $args;
	}

	private static function warn( $message ) {
		if ( count( self::$warnings ) < 60 ) {
			self::$warnings[] = $message;
		}
	}

	/* ---------------------------------------------------------------------
	 * Shared helpers.
	 * ------------------------------------------------------------------- */

	/**
	 * A post the current user may edit with Brik, or an error.
	 */
	private static function editable( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || 'trash' === $post->post_status ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No post with id %d.', 'brik-builder' ), (int) $id ) );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			/* translators: %d: post id */
			return new WP_Error( 'brik_forbidden', sprintf( __( 'You are not allowed to edit post %d.', 'brik-builder' ), $post->ID ) );
		}
		if ( ThemeBuilder::is_template( $post->ID ) && ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Editing theme builder templates requires the edit_theme_options capability.', 'brik-builder' ) );
		}
		if ( ! Plugin::supports( $post ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_unsupported', sprintf( __( 'Brik is not enabled for the post type "%s". Enable it under Brik → Settings.', 'brik-builder' ), $post->post_type ) );
		}
		return $post;
	}

	private static function urls( $post ) {
		$post = get_post( $post );
		$out  = array( 'builder_url' => Builder::url( $post->ID ) );
		if ( ThemeBuilder::is_template( $post->ID ) || Library::POST_TYPE === $post->post_type ) {
			$out['admin_url'] = ThemeBuilder::is_template( $post->ID ) ? admin_url( 'admin.php?page=brik-theme-builder' ) : admin_url( 'admin.php?page=brik-library' );
			return $out;
		}
		$out['url']      = 'publish' === $post->post_status ? get_permalink( $post ) : get_preview_post_link( $post );
		$out['edit_url'] = get_edit_post_link( $post->ID, 'raw' );
		return $out;
	}

	private static function json_tree( $value ) {
		if ( is_object( $value ) ) {
			$value = json_decode( wp_json_encode( $value ), true );
		}
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Every id in a tree, as a set for Data::normalize().
	 */
	private static function ids( array $nodes, array &$out = array() ) {
		foreach ( $nodes as $node ) {
			if ( isset( $node['id'] ) ) {
				$out[ $node['id'] ] = true;
			}
			if ( ! empty( $node['children'] ) ) {
				self::ids( $node['children'], $out );
			}
		}
		return $out;
	}

	/**
	 * Where a node sits: [ parent node or null, index ].
	 */
	private static function locate( array $nodes, $id, $parent = null ) {
		foreach ( array_values( $nodes ) as $i => $node ) {
			if ( isset( $node['id'] ) && $node['id'] === $id ) {
				return array( $parent, $i );
			}
			if ( ! empty( $node['children'] ) ) {
				$found = self::locate( $node['children'], $id, $node );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}

	private static function strip_ids( array $nodes ) {
		foreach ( $nodes as &$node ) {
			unset( $node['id'] );
			if ( ! empty( $node['children'] ) ) {
				$node['children'] = self::strip_ids( $node['children'] );
			}
		}
		return $nodes;
	}

	/**
	 * Normalize nodes for a given parent type (null = root) without clashing with existing ids.
	 */
	private static function prepare( array $nodes, $parent_type, array $existing ) {
		$nodes = self::json_tree( $nodes );
		if ( isset( $nodes['type'] ) ) {
			$nodes = array( $nodes );
		}
		if ( null !== $parent_type ) {
			foreach ( $nodes as $node ) {
				if ( isset( $node['type'] ) && 'section' === $node['type'] ) {
					self::warn( __( 'Sections can only be placed at the page root; the section was dropped. Insert it with parent_id null.', 'brik-builder' ) );
				}
			}
		}
		$seen = $existing;
		return Data::normalize( $nodes, $parent_type, $seen );
	}

	private static function container_ok( $parent ) {
		if ( null === $parent ) {
			return true;
		}
		$def = Modules::get( $parent['type'] );
		return $def && $def['children'];
	}

	/**
	 * Save a tree back to its post and report what was saved.
	 */
	private static function store( $post, array $tree, $page = null ) {
		$saved = Data::save( $post->ID, $tree, $page );
		self::validate( $saved );
		return $saved;
	}

	/**
	 * Short human label for a node, for outlines.
	 */
	private static function label( array $node ) {
		$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		foreach ( array( 'text', 'title', 'heading', 'label', 'name', 'content', 'code', 'shortcode' ) as $key ) {
			if ( ! empty( $attrs[ $key ] ) && is_string( $attrs[ $key ] ) ) {
				$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $attrs[ $key ] ) ) );
				if ( '' !== $text ) {
					return mb_strlen( $text ) > 60 ? mb_substr( $text, 0, 57 ) . '…' : $text;
				}
			}
		}
		if ( 'row' === $node['type'] ) {
			return isset( $attrs['columns'] ) ? (string) $attrs['columns'] : '1';
		}
		if ( 'global' === $node['type'] && ! empty( $attrs['ref'] ) ) {
			return 'ref ' . (int) $attrs['ref'];
		}
		return isset( $attrs['css_id'] ) ? '#' . $attrs['css_id'] : '';
	}

	private static function outline( array $nodes ) {
		$out = array();
		foreach ( $nodes as $node ) {
			$item  = array(
				'id'   => $node['id'],
				'type' => $node['type'],
			);
			$label = self::label( $node );
			if ( '' !== $label ) {
				$item['label'] = $label;
			}
			if ( ! empty( $node['children'] ) ) {
				$item['children'] = self::outline( $node['children'] );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Report problems an author would want to fix: unknown types and attributes, invalid
	 * option values, row structures that don't match their columns.
	 */
	private static function validate( array $nodes ) {
		foreach ( $nodes as $node ) {
			$type = $node['type'];
			$def  = Modules::get( $type );
			$at   = $type . ( isset( $node['id'] ) ? ' ' . $node['id'] : '' );
			if ( ! $def ) {
				/* translators: %s: module type */
				self::warn( sprintf( __( 'Unknown module type "%s"; it renders nothing until a module with that type exists. See list_modules.', 'brik-builder' ), $type ) );
				continue;
			}
			$attrs = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
			foreach ( $attrs as $key => $value ) {
				$parts = explode( '@', (string) $key, 2 );
				$base  = $parts[0];
				$state = isset( $parts[1] ) ? $parts[1] : '';
				if ( 'preset' === $base ) {
					if ( is_string( $value ) && '' !== $value && null === Settings::preset( $type, $value ) ) {
						/* translators: 1: preset id, 2: module type */
						self::warn( sprintf( __( 'Preset "%1$s" does not exist for %2$s (see list_presets).', 'brik-builder' ), $value, $type ) );
					}
					continue;
				}
				if ( ! isset( $def['fields'][ $base ] ) ) {
					/* translators: 1: attribute, 2: node */
					self::warn( sprintf( __( 'Unknown attribute "%1$s" on %2$s (ignored when rendering). See get_module.', 'brik-builder' ), $key, $at ) );
					continue;
				}
				$field = $def['fields'][ $base ];
				if ( '' !== $state && ! in_array( $state, array( 'tablet', 'mobile', 'hover' ), true ) ) {
					/* translators: 1: attribute, 2: node */
					self::warn( sprintf( __( 'Unknown state suffix in "%1$s" on %2$s; use @tablet, @mobile or @hover.', 'brik-builder' ), $key, $at ) );
					continue;
				}
				$styled = ! empty( $field['css'] ) || ! empty( $field['composite'] );
				if ( in_array( $state, array( 'tablet', 'mobile' ), true ) && empty( $field['responsive'] ) && ! $styled ) {
					/* translators: 1: attribute, 2: node */
					self::warn( sprintf( __( '"%1$s" on %2$s: this field has no responsive variants, the suffix has no effect.', 'brik-builder' ), $key, $at ) );
				}
				if ( 'hover' === $state && empty( $field['hover'] ) && ! $styled ) {
					/* translators: 1: attribute, 2: node */
					self::warn( sprintf( __( '"%1$s" on %2$s: this field has no hover variant, the suffix has no effect.', 'brik-builder' ), $key, $at ) );
				}
				if ( 'select' === $field['type'] && ! empty( $field['options'] ) && is_scalar( $value ) && '' !== (string) $value ) {
					$allowed = wp_list_pluck( $field['options'], 'value' );
					if ( ! in_array( (string) $value, $allowed, true ) ) {
						/* translators: 1: value, 2: attribute, 3: node, 4: allowed values */
						self::warn( sprintf( __( 'Invalid value "%1$s" for %2$s on %3$s. Allowed: %4$s.', 'brik-builder' ), $value, $key, $at, implode( '|', array_filter( $allowed, 'strlen' ) ) ) );
					}
				}
				if ( 'icon' === $field['type'] && is_string( $value ) && '' !== $value && ! Icons::exists( $value ) ) {
					/* translators: 1: icon name, 2: node */
					self::warn( sprintf( __( 'Unknown icon "%1$s" on %2$s. Use search_icons to find a valid name.', 'brik-builder' ), $value, $at ) );
				}
			}
			if ( 'row' === $type && ! empty( $node['children'] ) ) {
				$columns = isset( $attrs['columns'] ) ? (string) $attrs['columns'] : '1';
				$count   = count( array_filter( explode( ',', $columns ), 'strlen' ) );
				// More columns than fractions is fine when they fill whole lines: the grid wraps.
				$children = count( $node['children'] );
				if ( $children < $count || 0 !== $children % $count ) {
					/* translators: 1: row id, 2: structure, 3: number of columns */
					self::warn( sprintf( __( 'Row %1$s has columns "%2$s" but %3$d column children; give one fraction per column (e.g. "1/3,1/3,1/3"). Extra columns wrap onto new lines.', 'brik-builder' ), $node['id'], $columns, $children ) );
				}
			}
			if ( 'global' === $type && ( empty( $attrs['ref'] ) || ! Library::item( (int) $attrs['ref'] ) ) ) {
				/* translators: %s: node id */
				self::warn( sprintf( __( 'Global element %s references a missing library item.', 'brik-builder' ), $node['id'] ) );
			}
			if ( ! empty( $node['children'] ) ) {
				self::validate( $node['children'] );
			}
		}
	}

	private static function compact_field( array $field, $full = false ) {
		$out = array( 'type' => $field['type'] );
		if ( $full && ! empty( $field['label'] ) ) {
			$out['label'] = $field['label'];
		}
		if ( isset( $field['default'] ) && '' !== $field['default'] && array() !== $field['default'] ) {
			$default = $field['default'];
			// Sample content in defaults is long and not useful for choosing values.
			if ( ! $full && 'repeater' === $field['type'] ) {
				$default = null;
			} elseif ( ! $full && is_string( $default ) && mb_strlen( $default ) > 60 ) {
				$default = mb_substr( wp_strip_all_tags( $default ), 0, 40 ) . '…';
			}
			if ( null !== $default ) {
				$out['default'] = $default;
			}
		}
		if ( ! empty( $field['options'] ) ) {
			$out['options'] = array_values( array_filter( wp_list_pluck( $field['options'], 'value' ), 'strlen' ) );
		}
		if ( ! empty( $field['responsive'] ) ) {
			$out['responsive'] = true;
		}
		if ( ! empty( $field['hover'] ) ) {
			$out['hover'] = true;
		}
		foreach ( array( 'min', 'max', 'step', 'unit', 'language' ) as $key ) {
			if ( isset( $field[ $key ] ) ) {
				$out[ $key ] = $field[ $key ];
			}
		}
		if ( $full ) {
			foreach ( array( 'description', 'show_if', 'group', 'tab', 'title_field' ) as $key ) {
				if ( ! empty( $field[ $key ] ) ) {
					$out[ $key ] = $field[ $key ];
				}
			}
		}
		if ( 'repeater' === $field['type'] && ! empty( $field['fields'] ) ) {
			$out['fields'] = array();
			foreach ( $field['fields'] as $key => $sub ) {
				$out['fields'][ $key ] = self::compact_field( array_merge( array( 'type' => 'text' ), $sub ), $full );
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Tools: reference.
	 * ------------------------------------------------------------------- */

	public static function get_guide() {
		return self::guide();
	}

	public static function list_modules( array $a ) {
		$out = array();
		foreach ( Modules::all() as $type => $def ) {
			if ( ! empty( $a['category'] ) && $def['category'] !== $a['category'] ) {
				continue;
			}
			$fields = array();
			$style  = 0;
			foreach ( $def['fields'] as $key => $field ) {
				if ( isset( Fields::common()[ $key ] ) ) {
					continue;
				}
				if ( 'content' !== $field['tab'] ) {
					++$style;
					continue;
				}
				$fields[ $key ] = self::compact_field( $field );
			}
			$item = array(
				'type'        => $type,
				'title'       => wp_strip_all_tags( $def['title'] ),
				'category'    => $def['category'],
				'description' => $def['description'],
				'fields'      => $fields ? $fields : new \stdClass(),
			);
			if ( $def['children'] ) {
				$item['children'] = true === $def['children'] ? 'modules or rows' : $def['children'];
			}
			if ( $style ) {
				$item['style_fields'] = $style;
			}
			$out[] = $item;
		}
		return array(
			'count'   => count( $out ),
			'modules' => $out,
			'note'    => 'All modules also accept the shared design/advanced attributes (get_module with include_common). style_fields = number of module-specific design fields, listed by get_module.',
		);
	}

	public static function get_module( array $a ) {
		$def = Modules::get( sanitize_key( $a['type'] ) );
		if ( ! $def ) {
			/* translators: %s: module type */
			return new WP_Error( 'brik_unknown_module', sprintf( __( 'Unknown module type "%s". Call list_modules for valid types.', 'brik-builder' ), $a['type'] ) );
		}
		$own    = array();
		$common = array();
		foreach ( $def['fields'] as $key => $field ) {
			if ( isset( Fields::common()[ $key ] ) ) {
				$common[ $key ] = self::compact_field( $field, true );
			} else {
				$own[ $key ] = self::compact_field( $field, true );
			}
		}
		$out = array(
			'type'        => $def['type'],
			'title'       => wp_strip_all_tags( $def['title'] ),
			'category'    => $def['category'],
			'description' => $def['description'],
			'structural'  => (bool) $def['structural'],
			'children'    => $def['children'],
			'fields'      => $own ? $own : new \stdClass(),
		);
		if ( ! isset( $a['include_common'] ) || $a['include_common'] ) {
			$out['common_fields'] = $common;
		}
		$presets = Settings::get( 'presets' );
		if ( ! empty( $presets[ $def['type'] ] ) ) {
			$out['presets'] = array_keys( $presets[ $def['type'] ] );
		}
		return $out;
	}

	public static function search_icons( array $a ) {
		$query = strtolower( trim( $a['query'] ) );
		$limit = isset( $a['limit'] ) ? max( 1, min( 100, $a['limit'] ) ) : 30;
		$words = array_filter( preg_split( '/[\s,]+/', $query ) );
		if ( ! $words ) {
			return new WP_Error( 'brik_mcp_args', __( 'Give a search word.', 'brik-builder' ) );
		}
		$tags   = json_decode( (string) file_get_contents( BRIK_DIR . 'resources/icon-tags.json' ), true );
		$tags   = is_array( $tags ) ? $tags : array();
		$scores = array();

		$score = static function ( $name, array $keywords ) use ( $words, $query ) {
			$s = 0;
			if ( $name === $query || $name === str_replace( ' ', '-', $query ) ) {
				$s += 100;
			}
			foreach ( $words as $w ) {
				if ( 0 === strpos( $name, $w ) ) {
					$s += 30;
				} elseif ( false !== strpos( $name, $w ) ) {
					$s += 20;
				}
				foreach ( $keywords as $k ) {
					if ( $k === $w ) {
						$s += 12;
						break;
					}
					if ( false !== strpos( $k, $w ) ) {
						$s += 4;
						break;
					}
				}
			}
			return $s;
		};

		foreach ( array_keys( Icons::icons() ) as $name ) {
			$s = $score( $name, isset( $tags[ $name ] ) ? array_map( 'strtolower', (array) $tags[ $name ] ) : array() );
			if ( $s ) {
				$scores[ $name ] = $s;
			}
		}
		if ( ! isset( $a['brands'] ) || $a['brands'] ) {
			foreach ( Icons::brands() as $slug => $brand ) {
				$s = $score( $slug, array( strtolower( isset( $brand['title'] ) ? $brand['title'] : '' ) ) );
				if ( $s ) {
					$scores[ 'brand:' . $slug ] = $s + 1;
				}
			}
		}
		arsort( $scores );
		$names = array_slice( array_keys( $scores ), 0, $limit );
		return array(
			'query' => $a['query'],
			'icons' => $names,
			'total' => count( $scores ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Tools: content.
	 * ------------------------------------------------------------------- */

	public static function list_content( array $a ) {
		$type = isset( $a['post_type'] ) ? sanitize_key( $a['post_type'] ) : 'page';
		if ( 'any' === $type ) {
			$type = array_values( array_diff( Plugin::post_types(), array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ) ) );
		} elseif ( ! post_type_exists( $type ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_mcp_args', sprintf( __( 'Unknown post type "%s".', 'brik-builder' ), $type ) );
		}
		$per_page = isset( $a['per_page'] ) ? max( 1, min( 100, $a['per_page'] ) ) : 20;
		$args     = array(
			'post_type'      => $type,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $per_page,
			'paged'          => isset( $a['page'] ) ? max( 1, $a['page'] ) : 1,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'perm'           => 'editable',
		);
		if ( ! empty( $a['search'] ) ) {
			$args['s'] = sanitize_text_field( $a['search'] );
		}
		if ( ! empty( $a['only_brik'] ) ) {
			$args['meta_key']   = Data::META_ENABLED; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = '1'; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		$query = new \WP_Query( $args );
		$items = array();
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'edit_post', $post->ID ) ) {
				continue;
			}
			$items[] = array_merge(
				array(
					'id'       => $post->ID,
					'title'    => $post->post_title,
					'type'     => $post->post_type,
					'status'   => $post->post_status,
					'slug'     => $post->post_name,
					'brik'     => Data::enabled( $post->ID ),
					'modified' => get_post_modified_time( 'c', true, $post ),
				),
				Plugin::supports( $post ) ? self::urls( $post ) : array( 'url' => get_permalink( $post ) )
			);
		}
		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'total_pages' => (int) $query->max_num_pages,
		);
	}

	public static function get_page( array $a ) {
		$post = self::editable( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree = Data::get( $post->ID );
		$out  = array_merge(
			array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'type'   => $post->post_type,
				'status' => $post->post_status,
				'slug'   => $post->post_name,
				'brik'   => Data::enabled( $post->ID ),
			),
			self::urls( $post )
		);
		if ( 'page' === $post->post_type ) {
			$out['page_template'] = (string) get_page_template_slug( $post );
		}
		$out['page_settings'] = (object) Data::page_settings( $post->ID );
		if ( ThemeBuilder::is_template( $post->ID ) ) {
			$out['area']       = ThemeBuilder::area( $post->ID );
			$out['conditions'] = ThemeBuilder::conditions( $post->ID );
		}
		if ( ! empty( $a['outline'] ) ) {
			$out['outline'] = self::outline( $tree );
		} else {
			$out['tree'] = $tree;
		}
		if ( ! $tree && ! Data::enabled( $post->ID ) && '' !== trim( $post->post_content ) ) {
			$out['note'] = __( 'This post is not built with Brik yet; its existing content is not part of the tree. Saving a tree replaces the visible content.', 'brik-builder' );
		}
		return $out;
	}

	/**
	 * The tree to start from: the given tree, else a bundled layout, else empty.
	 */
	private static function initial_tree( array $a ) {
		if ( isset( $a['tree'] ) ) {
			if ( ! empty( $a['layout'] ) ) {
				self::warn( __( 'Both tree and layout were given; the tree was used.', 'brik-builder' ) );
			}
			return $a['tree'];
		}
		if ( empty( $a['layout'] ) ) {
			return array();
		}
		$layout = Library::bundled_one( $a['layout'] );
		if ( ! $layout ) {
			/* translators: %s: layout slug */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No bundled layout "%s". See list_layouts.', 'brik-builder' ), $a['layout'] ) );
		}
		return self::strip_ids( self::json_tree( $layout['tree'] ) );
	}

	private static function page_template_ok( $template, $post_type ) {
		return in_array( $template, array( '', 'brik-canvas.php', 'brik-full-width.php' ), true ) && in_array( $post_type, Plugin::post_types(), true );
	}

	public static function create_page( array $a ) {
		$type = isset( $a['post_type'] ) ? sanitize_key( $a['post_type'] ) : 'page';
		if ( in_array( $type, array( ThemeBuilder::POST_TYPE, Library::POST_TYPE ), true ) ) {
			return new WP_Error( 'brik_mcp_args', __( 'Use create_template or save_to_library for templates and library items.', 'brik-builder' ) );
		}
		$object = get_post_type_object( $type );
		if ( ! $object || ! in_array( $type, Plugin::post_types(), true ) ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_unsupported', sprintf( __( 'Brik is not enabled for the post type "%s".', 'brik-builder' ), $type ) );
		}
		if ( ! current_user_can( $object->cap->create_posts ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to create content of this type.', 'brik-builder' ) );
		}
		$status = isset( $a['status'] ) ? $a['status'] : 'draft';
		if ( 'publish' === $status && ! current_user_can( $object->cap->publish_posts ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to publish this content; create it as a draft.', 'brik-builder' ) );
		}
		if ( ! empty( $a['set_as_front_page'] ) && ( 'page' !== $type || ! current_user_can( 'manage_options' ) ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Only pages can be the front page, and setting it requires the manage_options capability.', 'brik-builder' ) );
		}

		$tree = self::initial_tree( $a );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$postarr = array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( $a['title'] ),
			'post_content' => '',
		);
		if ( ! empty( $a['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $a['slug'] );
		}
		if ( ! empty( $a['page_template'] ) && 'page' === $type ) {
			$postarr['page_template'] = $a['page_template'];
		}
		$id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! empty( $a['page_template'] ) && 'page' !== $type ) {
			update_post_meta( $id, '_wp_page_template', $a['page_template'] );
		}

		$post  = get_post( $id );
		$saved = self::store( $post, $tree, isset( $a['page_settings'] ) ? $a['page_settings'] : null );

		if ( ! empty( $a['set_as_front_page'] ) ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $id );
			if ( 'publish' !== $status ) {
				self::warn( __( 'The page is the front page but still a draft; publish it so visitors can see it.', 'brik-builder' ) );
			}
		}

		return array_merge(
			array(
				'id'     => $id,
				'title'  => get_the_title( $id ),
				'status' => get_post_status( $id ),
			),
			self::urls( $id ),
			array( 'outline' => self::outline( $saved ) )
		);
	}

	public static function update_page( array $a ) {
		$post = self::editable( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$update = array( 'ID' => $post->ID );
		if ( isset( $a['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $a['title'] );
		}
		if ( isset( $a['slug'] ) ) {
			$update['post_name'] = sanitize_title( $a['slug'] );
		}
		if ( isset( $a['status'] ) && $a['status'] !== $post->post_status ) {
			$object = get_post_type_object( $post->post_type );
			if ( in_array( $a['status'], array( 'publish', 'private' ), true ) && ! current_user_can( $object->cap->publish_posts ) ) {
				return new WP_Error( 'brik_forbidden', __( 'You are not allowed to publish this content.', 'brik-builder' ) );
			}
			$update['post_status'] = $a['status'];
		}
		if ( isset( $a['page_template'] ) ) {
			update_post_meta( $post->ID, '_wp_page_template', $a['page_template'] ? $a['page_template'] : 'default' );
		}
		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$page = null;
		if ( isset( $a['page_settings'] ) ) {
			$page = array_merge( Data::page_settings( $post->ID ), $a['page_settings'] );
		}
		if ( isset( $a['tree'] ) || null !== $page ) {
			$tree = isset( $a['tree'] ) ? $a['tree'] : Data::get( $post->ID );
			self::store( $post, $tree, $page );
		}

		$post = get_post( $post->ID );
		return array_merge(
			array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'status' => $post->post_status,
				'slug'   => $post->post_name,
			),
			self::urls( $post ),
			array(
				'page_settings' => (object) Data::page_settings( $post->ID ),
				'outline'       => self::outline( Data::get( $post->ID ) ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Tools: tree operations.
	 * ------------------------------------------------------------------- */

	public static function insert_nodes( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree      = Data::get( $post->ID );
		$parent_id = isset( $a['parent_id'] ) && '' !== $a['parent_id'] ? (string) $a['parent_id'] : null;
		$parent    = null;
		if ( null !== $parent_id ) {
			$parent = Data::find( $tree, $parent_id );
			if ( null === $parent ) {
				/* translators: %s: node id */
				return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree. Use get_page with outline: true.', 'brik-builder' ), $parent_id ) );
			}
			if ( ! self::container_ok( $parent ) ) {
				/* translators: %s: module type */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'A %s cannot hold children. Insert into its column instead.', 'brik-builder' ), $parent['type'] ) );
			}
		}
		$new = self::prepare( $a['nodes'], $parent ? $parent['type'] : null, self::ids( $tree ) );
		if ( ! $new ) {
			return new WP_Error( 'brik_mcp_args', __( 'Nothing to insert: no valid nodes (each node needs a "type").', 'brik-builder' ) );
		}
		$index = isset( $a['index'] ) ? (int) $a['index'] : -1;
		$tree  = Data::insert( $tree, $parent_id, $new, $index );
		$saved = self::store( $post, $tree );

		return array_merge(
			array(
				'post_id'      => $post->ID,
				'inserted_ids' => wp_list_pluck( $new, 'id' ),
				'inserted'     => self::outline( $new ),
			),
			self::urls( $post ),
			array( 'nodes_total' => count( Data::flatten( $saved ) ) )
		);
	}

	public static function update_node( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree = Data::get( $post->ID );
		$node = &Data::find( $tree, (string) $a['node_id'] );
		if ( null === $node ) {
			/* translators: %s: node id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree. Use get_page with outline: true.', 'brik-builder' ), $a['node_id'] ) );
		}
		$attrs   = (array) $a['attrs'];
		$current = isset( $node['attrs'] ) ? (array) $node['attrs'] : array();
		$merged  = ( isset( $a['mode'] ) && 'replace' === $a['mode'] ) ? $attrs : array_merge( $current, $attrs );
		foreach ( $merged as $key => $value ) {
			if ( null === $value ) {
				unset( $merged[ $key ] );
			}
		}
		$node['attrs'] = $merged;
		unset( $node );

		$saved = self::store( $post, $tree );
		$after = Data::find( $saved, (string) $a['node_id'] );
		return array_merge(
			array(
				'post_id' => $post->ID,
				'node'    => array(
					'id'    => $after['id'],
					'type'  => $after['type'],
					'attrs' => $after['attrs'] ? $after['attrs'] : new \stdClass(),
				),
			),
			self::urls( $post )
		);
	}

	public static function remove_node( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$removed = null;
		$tree    = Data::remove( Data::get( $post->ID ), (string) $a['node_id'], $removed );
		if ( null === $removed ) {
			/* translators: %s: node id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree.', 'brik-builder' ), $a['node_id'] ) );
		}
		self::store( $post, $tree );
		return array_merge(
			array(
				'post_id' => $post->ID,
				'removed' => array(
					'id'          => $removed['id'],
					'type'        => $removed['type'],
					'descendants' => ! empty( $removed['children'] ) ? count( Data::flatten( $removed['children'] ) ) : 0,
				),
			),
			self::urls( $post )
		);
	}

	public static function move_node( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree    = Data::get( $post->ID );
		$node_id = (string) $a['node_id'];
		$target  = isset( $a['parent_id'] ) && '' !== $a['parent_id'] ? (string) $a['parent_id'] : null;

		$node = Data::find( $tree, $node_id );
		if ( null === $node ) {
			/* translators: %s: node id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree.', 'brik-builder' ), $node_id ) );
		}
		if ( null !== $target ) {
			if ( $target === $node_id || ( ! empty( $node['children'] ) && null !== Data::find( $node['children'], $target ) ) ) {
				return new WP_Error( 'brik_mcp_args', __( 'A node cannot be moved inside itself.', 'brik-builder' ) );
			}
			$parent = Data::find( $tree, $target );
			if ( null === $parent ) {
				/* translators: %s: node id */
				return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree.', 'brik-builder' ), $target ) );
			}
			if ( ! self::container_ok( $parent ) ) {
				/* translators: %s: module type */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'A %s cannot hold children.', 'brik-builder' ), $parent['type'] ) );
			}
		}

		$index = isset( $a['index'] ) ? (int) $a['index'] : -1;

		// Moving within the same parent: account for the gap left by the removal.
		$where = self::locate( $tree, $node_id );
		$from  = $where[0] ? $where[0]['id'] : null;
		if ( $from === $target && $index > $where[1] ) {
			--$index;
		}

		$tree   = Data::remove( $tree, $node_id );
		$parent = null === $target ? null : Data::find( $tree, $target );
		$moved  = self::prepare( array( $node ), $parent ? $parent['type'] : null, self::ids( $tree ) );
		if ( ! $moved ) {
			return new WP_Error( 'brik_mcp_args', __( 'That node cannot be placed there.', 'brik-builder' ) );
		}
		$tree = Data::insert( $tree, $target, $moved, $index );
		self::store( $post, $tree );

		$where = self::locate( Data::get( $post->ID ), $moved[0]['id'] );
		return array_merge(
			array(
				'post_id'   => $post->ID,
				'node_id'   => $moved[0]['id'],
				'wrapped'   => $moved[0]['id'] !== $node_id,
				'parent_id' => $where && $where[0] ? $where[0]['id'] : null,
				'index'     => $where ? $where[1] : null,
			),
			self::urls( $post )
		);
	}

	public static function duplicate_node( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$tree  = Data::get( $post->ID );
		$where = self::locate( $tree, (string) $a['node_id'] );
		if ( ! $where ) {
			/* translators: %s: node id */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No node with id "%s" in this tree.', 'brik-builder' ), $a['node_id'] ) );
		}
		$original = Data::find( $tree, (string) $a['node_id'] );
		$copy     = self::prepare( self::strip_ids( array( $original ) ), $where[0] ? $where[0]['type'] : null, self::ids( $tree ) );
		$tree     = Data::insert( $tree, $where[0] ? $where[0]['id'] : null, $copy, $where[1] + 1 );
		self::store( $post, $tree );

		return array_merge(
			array(
				'post_id' => $post->ID,
				'copy_id' => $copy[0]['id'],
				'copy'    => self::outline( $copy ),
			),
			self::urls( $post )
		);
	}

	public static function render_preview( array $a ) {
		$post_id = isset( $a['post_id'] ) ? (int) $a['post_id'] : 0;
		if ( $post_id ) {
			$post = self::editable( $post_id );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
		}
		if ( isset( $a['tree'] ) ) {
			$tree = Data::sanitize( Data::normalize( $a['tree'] ) );
		} elseif ( $post_id ) {
			$tree = Data::get( $post_id );
		} else {
			return new WP_Error( 'brik_mcp_args', __( 'Pass post_id, tree or both.', 'brik-builder' ) );
		}
		self::validate( $tree );

		if ( $post_id ) {
			$GLOBALS['post'] = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			setup_postdata( $GLOBALS['post'] );
		}
		$renderer = new Renderer( $post_id );
		$html     = $renderer->render_root( $tree, 'brik-content' );
		$css      = $renderer->style->css();
		if ( $post_id ) {
			wp_reset_postdata();
		}

		$html  = trim( preg_replace( '/>\s+</', '><', $html ) );
		$max   = isset( $a['max_chars'] ) ? max( 500, min( 200000, (int) $a['max_chars'] ) ) : self::MAX_HTML;
		$total = strlen( $html );
		$out   = array(
			'html'      => $total > $max ? substr( $html, 0, $max ) . '<!-- trimmed -->' : $html,
			'html_size' => $total,
			'trimmed'   => $total > $max,
			'css_size'  => strlen( $css ),
			'fonts'     => array_values( array_unique( $renderer->style->fonts() ) ),
			'nodes'     => count( Data::flatten( $tree ) ),
			'text'      => mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_replace( '><', '> <', $html ) ) ) ), 0, 1500 ),
		);
		if ( ! empty( $a['include_css'] ) ) {
			$out['css'] = strlen( $css ) > $max ? substr( $css, 0, $max ) : $css;
		}
		if ( $post_id ) {
			$out = array_merge( $out, self::urls( $post_id ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Tools: theme builder.
	 * ------------------------------------------------------------------- */

	private static function can_theme() {
		return current_user_can( 'edit_theme_options' ) ? true : new WP_Error( 'brik_forbidden', __( 'This requires the edit_theme_options capability.', 'brik-builder' ) );
	}

	/**
	 * Condition list in plain words, e.g. "Entire site, except: Front page".
	 */
	public static function describe_conditions( array $rules ) {
		$types = ThemeBuilder::rule_types();
		$in    = array();
		$out   = array();
		foreach ( $rules as $rule ) {
			$label = isset( $types[ $rule['rule'] ] ) ? $types[ $rule['rule'] ] : $rule['rule'];
			$extra = array();
			if ( ! empty( $rule['post_type'] ) && ( $object = get_post_type_object( $rule['post_type'] ) ) ) {
				$extra[] = $object->labels->name;
			}
			if ( ! empty( $rule['taxonomy'] ) && ( $tax = get_taxonomy( $rule['taxonomy'] ) ) ) {
				$extra[] = $tax->labels->name;
			}
			if ( ! empty( $rule['ids'] ) ) {
				$names = array();
				foreach ( array_slice( $rule['ids'], 0, 5 ) as $id ) {
					if ( in_array( $rule['rule'], array( 'term', 'in_term' ), true ) ) {
						$term    = get_term( $id );
						$names[] = $term && ! is_wp_error( $term ) ? $term->name : '#' . $id;
					} elseif ( 'author' === $rule['rule'] ) {
						$user    = get_userdata( $id );
						$names[] = $user ? $user->display_name : '#' . $id;
					} else {
						$names[] = get_the_title( $id ) ? get_the_title( $id ) : '#' . $id;
					}
				}
				if ( count( $rule['ids'] ) > 5 ) {
					/* translators: %d: number of more items */
					$names[] = sprintf( __( '%d more', 'brik-builder' ), count( $rule['ids'] ) - 5 );
				}
				$extra[] = implode( ', ', $names );
			}
			$text = $label . ( $extra ? ' (' . implode( ': ', $extra ) . ')' : '' );
			if ( 'exclude' === $rule['type'] ) {
				$out[] = $text;
			} else {
				$in[] = $text;
			}
		}
		if ( ! $in ) {
			return __( 'Not shown anywhere (no include conditions)', 'brik-builder' );
		}
		$summary = implode( '; ', $in );
		if ( $out ) {
			/* translators: %s: excluded places */
			$summary .= ' — ' . sprintf( __( 'except %s', 'brik-builder' ), implode( '; ', $out ) );
		}
		return $summary;
	}

	private static function template_payload( $post ) {
		$post = get_post( $post );
		return array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'area'        => ThemeBuilder::area( $post->ID ),
			'status'      => $post->post_status,
			'conditions'  => ThemeBuilder::conditions( $post->ID ),
			'shown_on'    => self::describe_conditions( ThemeBuilder::conditions( $post->ID ) ),
			'builder_url' => Builder::url( $post->ID ),
			'admin_url'   => admin_url( 'admin.php?page=brik-theme-builder' ),
			'modified'    => get_post_modified_time( 'c', true, $post ),
		);
	}

	public static function list_templates( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$args = array(
			'post_type'      => ThemeBuilder::POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 200,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
		);
		if ( ! empty( $a['area'] ) ) {
			$args['meta_key']   = ThemeBuilder::META_AREA; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['meta_value'] = $a['area']; // phpcs:ignore WordPress.DB.SlowDBQuery
		}
		return array( 'templates' => array_map( array( __CLASS__, 'template_payload' ), get_posts( $args ) ) );
	}

	private static function clean_conditions( $rules ) {
		$valid = ThemeBuilder::rule_types();
		$out   = array();
		foreach ( (array) $rules as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['rule'] ) || ! isset( $valid[ $rule['rule'] ] ) ) {
				/* translators: %s: rule */
				self::warn( sprintf( __( 'Skipped condition with unknown rule %s.', 'brik-builder' ), wp_json_encode( isset( $rule['rule'] ) ? $rule['rule'] : null ) ) );
				continue;
			}
			if ( ! empty( $rule['post_type'] ) && ! post_type_exists( $rule['post_type'] ) ) {
				/* translators: %s: post type */
				self::warn( sprintf( __( 'Condition post type "%s" does not exist.', 'brik-builder' ), $rule['post_type'] ) );
			}
			if ( ! empty( $rule['taxonomy'] ) && ! taxonomy_exists( $rule['taxonomy'] ) ) {
				/* translators: %s: taxonomy */
				self::warn( sprintf( __( 'Condition taxonomy "%s" does not exist.', 'brik-builder' ), $rule['taxonomy'] ) );
			}
			$out[] = $rule;
		}
		return $out;
	}

	public static function create_template( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$area   = $a['area'];
		$status = isset( $a['status'] ) ? $a['status'] : 'publish';
		$tree   = self::initial_tree( $a );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}
		$id     = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => ThemeBuilder::POST_TYPE,
					'post_status' => $status,
					'post_title'  => ! empty( $a['title'] ) ? sanitize_text_field( $a['title'] ) : ThemeBuilder::areas()[ $area ],
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, ThemeBuilder::META_AREA, $area );
		$rules = isset( $a['conditions'] ) ? self::clean_conditions( $a['conditions'] ) : array(
			array(
				'type' => 'include',
				'rule' => 'entire_site',
			),
		);
		ThemeBuilder::save_conditions( $id, $rules );
		$saved = self::store( get_post( $id ), $tree );

		return array_merge( self::template_payload( $id ), array( 'outline' => self::outline( $saved ) ) );
	}

	public static function update_template( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$post = self::editable( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! ThemeBuilder::is_template( $post->ID ) ) {
			return new WP_Error( 'brik_mcp_args', __( 'That id is not a theme builder template. Use update_page for pages.', 'brik-builder' ) );
		}
		$update = array( 'ID' => $post->ID );
		if ( isset( $a['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $a['title'] );
		}
		if ( isset( $a['status'] ) ) {
			$update['post_status'] = $a['status'];
		}
		if ( count( $update ) > 1 ) {
			wp_update_post( wp_slash( $update ) );
		}
		if ( isset( $a['conditions'] ) ) {
			ThemeBuilder::save_conditions( $post->ID, self::clean_conditions( $a['conditions'] ) );
		}
		$out = self::template_payload( $post->ID );
		if ( isset( $a['tree'] ) ) {
			$out['outline'] = self::outline( self::store( $post, $a['tree'] ) );
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Tools: design settings and presets.
	 * ------------------------------------------------------------------- */

	public static function get_design_settings() {
		$all = Settings::all();
		return array(
			'base'         => $all['base'],
			'bases'        => array_keys( Settings::bases() ),
			'accent'       => $all['accent'],
			'accents'      => array_keys( Settings::accents() ),
			'radius'       => $all['radius'],
			'font_body'    => $all['font_body'],
			'font_heading' => $all['font_heading'],
			'container'    => $all['container'],
			'colors'       => $all['colors'],
			'tokens'       => array(
				'overrides' => $all['tokens'],
				'light'     => Settings::tokens( 'light' ),
				'dark'      => Settings::tokens( 'dark' ),
			),
			'custom_css'   => $all['custom_css'],
			'fonts'        => array_keys( Fonts::google() ),
			'usage'        => 'Tokens are CSS variables: var(--primary), var(--muted-foreground), var(--radius), var(--brik-color-{id}) …',
		);
	}

	public static function update_design_settings( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$changes = array();
		foreach ( array( 'base', 'accent', 'radius', 'font_body', 'font_heading', 'container', 'custom_css', 'colors', 'tokens' ) as $key ) {
			if ( array_key_exists( $key, $a ) ) {
				$changes[ $key ] = $a[ $key ];
			}
		}
		if ( ! $changes ) {
			return new WP_Error( 'brik_mcp_args', __( 'Nothing to change.', 'brik-builder' ) );
		}
		if ( isset( $changes['tokens'] ) ) {
			foreach ( (array) $changes['tokens'] as $mode => $values ) {
				foreach ( (array) $values as $name => $value ) {
					if ( ! in_array( $name, Settings::token_names(), true ) ) {
						/* translators: 1: token, 2: valid tokens */
						self::warn( sprintf( __( 'Unknown token "%1$s" ignored. Valid: %2$s.', 'brik-builder' ), $name, implode( ', ', Settings::token_names() ) ) );
					}
				}
			}
			// Keep the other mode's overrides when only one is given.
			$changes['tokens'] = array_merge( (array) Settings::get( 'tokens' ), (array) $changes['tokens'] );
		}
		foreach ( array( 'font_body', 'font_heading' ) as $key ) {
			if ( ! empty( $changes[ $key ] ) && ! isset( Fonts::google()[ $changes[ $key ] ] ) && false === strpos( $changes[ $key ], ',' ) ) {
				/* translators: %s: font */
				self::warn( sprintf( __( '"%s" is not in the bundled Google Fonts list; it must be available some other way.', 'brik-builder' ), $changes[ $key ] ) );
			}
		}
		Settings::update( $changes );
		$out            = self::get_design_settings();
		$out['changed'] = array_keys( $changes );
		unset( $out['fonts'] );
		return $out;
	}

	public static function list_presets( array $a ) {
		$presets = (array) Settings::get( 'presets' );
		if ( ! empty( $a['type'] ) ) {
			$presets = isset( $presets[ $a['type'] ] ) ? array( $a['type'] => $presets[ $a['type'] ] ) : array();
		}
		return array( 'presets' => $presets ? $presets : new \stdClass() );
	}

	public static function save_preset( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$type = sanitize_key( $a['type'] );
		$def  = Modules::get( $type );
		if ( ! $def ) {
			/* translators: %s: module type */
			return new WP_Error( 'brik_unknown_module', sprintf( __( 'Unknown module type "%s".', 'brik-builder' ), $a['type'] ) );
		}
		$id = sanitize_key( $a['id'] );
		if ( '' === $id ) {
			return new WP_Error( 'brik_mcp_args', __( 'Preset id must contain letters or digits.', 'brik-builder' ) );
		}
		self::validate(
			array(
				array(
					'id'    => 'preset',
					'type'  => $type,
					'attrs' => (array) $a['attrs'],
				),
			)
		);
		$presets = (array) Settings::get( 'presets' );
		if ( ! empty( $a['default'] ) && ! empty( $presets[ $type ] ) ) {
			foreach ( $presets[ $type ] as &$preset ) {
				$preset['default'] = false;
			}
			unset( $preset );
		}
		$presets[ $type ][ $id ] = array(
			'name'    => ! empty( $a['name'] ) ? $a['name'] : $id,
			'default' => ! empty( $a['default'] ),
			'attrs'   => array_filter(
				(array) $a['attrs'],
				static function ( $v ) {
					return null !== $v;
				}
			),
		);
		Settings::update( array( 'presets' => $presets ) );
		$saved = Settings::get( 'presets' );
		return array(
			'type'   => $type,
			'id'     => $id,
			'preset' => $saved[ $type ][ $id ],
			'usage'  => array(
				'type'  => $type,
				'attrs' => array( 'preset' => $id ),
			),
		);
	}

	public static function delete_preset( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$presets = (array) Settings::get( 'presets' );
		if ( ! isset( $presets[ $a['type'] ][ $a['id'] ] ) ) {
			return new WP_Error( 'brik_not_found', __( 'No such preset.', 'brik-builder' ) );
		}
		unset( $presets[ $a['type'] ][ $a['id'] ] );
		if ( ! $presets[ $a['type'] ] ) {
			unset( $presets[ $a['type'] ] );
		}
		Settings::update( array( 'presets' => $presets ) );
		return array(
			'deleted' => true,
			'type'    => $a['type'],
			'id'      => $a['id'],
		);
	}

	/* ---------------------------------------------------------------------
	 * Tools: library and layouts.
	 * ------------------------------------------------------------------- */

	public static function list_library( array $a ) {
		$items = Library::items( isset( $a['kind'] ) ? $a['kind'] : '' );
		foreach ( $items as &$item ) {
			$item['builder_url'] = Builder::url( $item['id'] );
			if ( $item['global'] ) {
				$item['embed'] = array(
					'type'  => 'global',
					'attrs' => array( 'ref' => $item['id'] ),
				);
			}
		}
		return array( 'items' => $items );
	}

	public static function save_to_library( array $a ) {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Saving to the library requires the edit_pages capability.', 'brik-builder' ) );
		}
		$id = Library::create( $a['title'], isset( $a['kind'] ) ? $a['kind'] : 'section', self::json_tree( $a['tree'] ), ! empty( $a['global'] ) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$saved = Data::get( $id );
		self::validate( $saved );
		$out                = Library::item( $id );
		$out['builder_url'] = Builder::url( $id );
		$out['outline']     = self::outline( $saved );
		if ( $out['global'] ) {
			$out['embed'] = array(
				'type'  => 'global',
				'attrs' => array( 'ref' => $id ),
			);
		}
		return $out;
	}

	public static function list_layouts( array $a ) {
		$out = array();
		foreach ( Library::bundled() as $layout ) {
			if ( ! empty( $a['slug'] ) && $layout['slug'] !== $a['slug'] ) {
				continue;
			}
			if ( ! empty( $a['category'] ) && $layout['category'] !== $a['category'] ) {
				continue;
			}
			if ( empty( $a['slug'] ) ) {
				unset( $layout['tree'] );
			}
			$out[] = $layout;
		}
		if ( ! empty( $a['slug'] ) && ! $out ) {
			/* translators: %s: layout slug */
			return new WP_Error( 'brik_not_found', sprintf( __( 'No bundled layout "%s".', 'brik-builder' ), $a['slug'] ) );
		}
		return array( 'layouts' => $out );
	}

	public static function insert_layout( array $a ) {
		$post = self::editable( $a['post_id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! empty( $a['slug'] ) ) {
			$layout = Library::bundled_one( $a['slug'] );
			if ( ! $layout ) {
				/* translators: %s: layout slug */
				return new WP_Error( 'brik_not_found', sprintf( __( 'No bundled layout "%s". See list_layouts.', 'brik-builder' ), $a['slug'] ) );
			}
			$nodes = $layout['tree'];
		} elseif ( ! empty( $a['library_id'] ) ) {
			$item = Library::item( (int) $a['library_id'] );
			if ( ! $item || ! current_user_can( 'edit_post', $item['id'] ) ) {
				return new WP_Error( 'brik_not_found', __( 'No such library item.', 'brik-builder' ) );
			}
			$nodes = Library::nodes_for( $item['id'] );
		} else {
			return new WP_Error( 'brik_mcp_args', __( 'Pass slug or library_id.', 'brik-builder' ) );
		}
		$tree = Data::get( $post->ID );
		$new  = self::prepare( self::strip_ids( self::json_tree( $nodes ) ), null, self::ids( $tree ) );
		$tree = Data::insert( $tree, null, $new, isset( $a['index'] ) ? (int) $a['index'] : -1 );
		self::store( $post, $tree );
		return array_merge(
			array(
				'post_id'      => $post->ID,
				'inserted_ids' => wp_list_pluck( $new, 'id' ),
				'inserted'     => self::outline( $new ),
			),
			self::urls( $post )
		);
	}

	/* ---------------------------------------------------------------------
	 * Tools: menus, media, site.
	 * ------------------------------------------------------------------- */

	private static function menu_tree( array $items, $parent = 0 ) {
		$out = array();
		foreach ( $items as $item ) {
			if ( (int) $item->menu_item_parent !== (int) $parent ) {
				continue;
			}
			$node     = array(
				'id'    => (int) $item->ID,
				'title' => $item->title,
				'url'   => $item->url,
			);
			$children = self::menu_tree( $items, $item->ID );
			if ( $children ) {
				$node['children'] = $children;
			}
			$out[] = $node;
		}
		return $out;
	}

	public static function list_menus() {
		$locations = get_nav_menu_locations();
		$menus     = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$items   = wp_get_nav_menu_items( $menu->term_id );
			$menus[] = array(
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'count'     => (int) $menu->count,
				'locations' => array_keys( $locations, $menu->term_id, true ),
				'items'     => self::menu_tree( $items ? $items : array() ),
			);
		}
		return array(
			'menus'     => $menus,
			'locations' => get_registered_nav_menus() ? get_registered_nav_menus() : new \stdClass(),
		);
	}

	private static function add_menu_items( $menu_id, array $items, $parent = 0, &$count = 0 ) {
		foreach ( array_values( $items ) as $i => $item ) {
			if ( ! is_array( $item ) || $count >= 200 ) {
				continue;
			}
			$args = array(
				'menu-item-title'     => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
				'menu-item-status'    => 'publish',
				'menu-item-parent-id' => $parent,
				'menu-item-position'  => $i + 1,
			);
			if ( ! empty( $item['post_id'] ) ) {
				$target = get_post( (int) $item['post_id'] );
				if ( ! $target || ! current_user_can( 'read_post', $target->ID ) ) {
					/* translators: %d: post id */
					self::warn( sprintf( __( 'Menu item skipped: post %d not found.', 'brik-builder' ), (int) $item['post_id'] ) );
					continue;
				}
				$args['menu-item-type']      = 'post_type';
				$args['menu-item-object']    = $target->post_type;
				$args['menu-item-object-id'] = $target->ID;
			} else {
				$url = isset( $item['url'] ) ? (string) $item['url'] : '#';
				// Root-relative and fragment links are fine in menus.
				$args['menu-item-type'] = 'custom';
				$args['menu-item-url']  = 0 === strpos( $url, '#' ) || 0 === strpos( $url, '/' ) ? esc_url_raw( home_url( $url ) ) : esc_url_raw( $url );
				if ( 0 === strpos( $url, '#' ) ) {
					$args['menu-item-url'] = $url;
				}
				if ( '' === $args['menu-item-title'] ) {
					$args['menu-item-title'] = $url;
				}
			}
			$id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( is_wp_error( $id ) ) {
				self::warn( $id->get_error_message() );
				continue;
			}
			++$count;
			if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
				self::add_menu_items( $menu_id, $item['children'], $id, $count );
			}
		}
		return $count;
	}

	public static function create_menu( array $a ) {
		$ok = self::can_theme();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$name     = sanitize_text_field( $a['name'] );
		$existing = wp_get_nav_menu_object( $name );
		if ( $existing ) {
			if ( empty( $a['replace'] ) ) {
				/* translators: 1: menu name, 2: menu id */
				return new WP_Error( 'brik_exists', sprintf( __( 'A menu named "%1$s" already exists (id %2$d). Pass replace: true to rebuild it or choose another name.', 'brik-builder' ), $name, $existing->term_id ) );
			}
			$menu_id = $existing->term_id;
			foreach ( (array) wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) ) as $old ) {
				wp_delete_post( $old->ID, true );
			}
		} else {
			$menu_id = wp_create_nav_menu( $name );
			if ( is_wp_error( $menu_id ) ) {
				return $menu_id;
			}
		}
		$count = self::add_menu_items( $menu_id, (array) $a['items'] );

		if ( ! empty( $a['location'] ) ) {
			$registered = get_registered_nav_menus();
			if ( isset( $registered[ $a['location'] ] ) ) {
				$locations                   = get_nav_menu_locations();
				$locations[ $a['location'] ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			} else {
				/* translators: 1: location, 2: valid locations */
				self::warn( sprintf( __( 'Unknown menu location "%1$s". Registered: %2$s.', 'brik-builder' ), $a['location'], $registered ? implode( ', ', array_keys( $registered ) ) : 'none' ) );
			}
		}
		$items = wp_get_nav_menu_items( $menu_id );
		return array(
			'id'    => (int) $menu_id,
			'name'  => $name,
			'count' => $count,
			'items' => self::menu_tree( $items ? $items : array() ),
			'usage' => 'Pass this id to the "menu" attribute of a menu module.',
		);
	}

	public static function upload_media( array $a ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to upload files.', 'brik-builder' ) );
		}
		$url = esc_url_raw( trim( $a['url'] ), array( 'http', 'https' ) );
		if ( ! $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'brik_mcp_args', __( 'Give a public http(s) URL.', 'brik-builder' ) );
		}
		$attach_to = isset( $a['post_id'] ) ? (int) $a['post_id'] : 0;
		if ( $attach_to && ! current_user_can( 'edit_post', $attach_to ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to attach media to that post.', 'brik-builder' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$name = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		$mime = wp_get_image_mime( $tmp );
		if ( ! pathinfo( $name, PATHINFO_EXTENSION ) || ! wp_check_filetype( $name )['type'] ) {
			$ext  = $mime ? array_search( $mime, wp_get_mime_types(), true ) : false;
			$ext  = $ext ? explode( '|', $ext )[0] : '';
			$name = ( pathinfo( $name, PATHINFO_FILENAME ) ? pathinfo( $name, PATHINFO_FILENAME ) : 'brik-media' ) . ( $ext ? '.' . $ext : '' );
		}
		$check = wp_check_filetype_and_ext( $tmp, $name );
		$type  = $check['type'] ? $check['type'] : '';
		if ( ! $type || ! ( 0 === strpos( $type, 'image/' ) || 0 === strpos( $type, 'video/' ) || 'application/pdf' === $type ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'brik_mcp_type', __( 'Only images, videos and PDF files can be imported.', 'brik-builder' ) );
		}
		if ( $check['proper_filename'] ) {
			$name = $check['proper_filename'];
		}

		$file = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);
		$post_data = array();
		if ( ! empty( $a['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $a['title'] );
		}
		$id = media_handle_sideload( $file, $attach_to, null, $post_data );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return $id;
		}
		if ( ! empty( $a['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $a['alt'] ) );
		}
		$src = wp_get_attachment_url( $id );
		$out = array(
			'id'    => $id,
			'url'   => $src,
			'mime'  => get_post_mime_type( $id ),
			'title' => get_the_title( $id ),
			'image' => array(
				'id'  => $id,
				'url' => $src,
				'alt' => isset( $a['alt'] ) ? sanitize_text_field( $a['alt'] ) : '',
			),
		);
		$meta = wp_get_attachment_metadata( $id );
		if ( ! empty( $meta['width'] ) ) {
			$out['width']  = (int) $meta['width'];
			$out['height'] = (int) $meta['height'];
		}
		return $out;
	}

	public static function update_site( array $a ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'brik_forbidden', __( 'Changing site settings requires the manage_options capability.', 'brik-builder' ) );
		}
		$changed = array();
		if ( isset( $a['title'] ) ) {
			update_option( 'blogname', sanitize_text_field( $a['title'] ) );
			$changed[] = 'title';
		}
		if ( isset( $a['tagline'] ) ) {
			update_option( 'blogdescription', sanitize_text_field( $a['tagline'] ) );
			$changed[] = 'tagline';
		}
		foreach ( array(
			'front_page_id' => 'page_on_front',
			'posts_page_id' => 'page_for_posts',
		) as $key => $option ) {
			if ( ! isset( $a[ $key ] ) ) {
				continue;
			}
			$id = (int) $a[ $key ];
			if ( $id && ( 'page' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) ) {
				/* translators: %d: page id */
				return new WP_Error( 'brik_mcp_args', sprintf( __( 'Post %d is not a published page.', 'brik-builder' ), $id ) );
			}
			update_option( $option, $id );
			$changed[] = $key;
		}
		if ( isset( $a['front_page_id'] ) ) {
			update_option( 'show_on_front', $a['front_page_id'] ? 'page' : 'posts' );
		}
		if ( ! $changed ) {
			return new WP_Error( 'brik_mcp_args', __( 'Nothing to change.', 'brik-builder' ) );
		}
		return array(
			'changed'       => $changed,
			'title'         => get_option( 'blogname' ),
			'tagline'       => get_option( 'blogdescription' ),
			'show_on_front' => get_option( 'show_on_front' ),
			'front_page_id' => (int) get_option( 'page_on_front' ),
			'posts_page_id' => (int) get_option( 'page_for_posts' ),
			'home_url'      => home_url( '/' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Content types and entries.
	 * ------------------------------------------------------------------- */

	private static function can_model() {
		return current_user_can( 'manage_options' ) ? true : new WP_Error( 'brik_forbidden', __( 'Managing content types requires the manage_options capability.', 'brik-builder' ) );
	}

	/**
	 * Fields without empty settings, which keeps responses small for clients.
	 */
	private static function compact_content_field( array $field ) {
		$out = array(
			'key'   => $field['key'],
			'name'  => $field['name'],
			'label' => $field['label'],
			'type'  => $field['type'],
		);
		foreach ( array( 'required', 'instructions', 'default', 'placeholder', 'conditions' ) as $k ) {
			if ( ! empty( $field[ $k ] ) ) {
				$out[ $k ] = $field[ $k ];
			}
		}
		if ( 100 !== (int) $field['width'] ) {
			$out['width'] = $field['width'];
		}
		$options = array();
		foreach ( $field['options'] as $k => $v ) {
			if ( 'sub_fields' === $k ) {
				$options[ $k ] = array_map( array( __CLASS__, 'compact_content_field' ), $v );
			} elseif ( '' !== $v && array() !== $v && false !== $v ) {
				$options[ $k ] = $v;
			}
		}
		if ( $options ) {
			$out['options'] = $options;
		}
		return $out;
	}

	public static function list_content_types( array $a ) {
		$groups = array();
		foreach ( Content\Registry::groups() as $group ) {
			$groups[] = array(
				'key'      => $group['key'],
				'title'    => $group['title'],
				'active'   => $group['active'],
				'local'    => $group['local'],
				'location' => $group['location'],
				'position' => $group['position'],
				'fields'   => array_map( array( __CLASS__, 'compact_content_field' ), $group['fields'] ),
			);
		}
		$out = array(
			'post_types' => Content\RestContent::with_stats( 'post_types', Content\Registry::post_types() ),
			'taxonomies' => Content\RestContent::with_stats( 'taxonomies', Content\Registry::taxonomies() ),
			'groups'     => $groups,
			'builtin'    => array(
				'post_types' => array_values( array_diff( get_post_types( array( 'public' => true ) ), wp_list_pluck( Content\Registry::post_types(), 'key' ), array( 'attachment' ) ) ),
				'taxonomies' => array_values( array_diff( get_taxonomies( array( 'public' => true ) ), wp_list_pluck( Content\Registry::taxonomies(), 'key' ), array( 'post_format' ) ) ),
			),
			'usage'      => 'Show values in builder text with {field:name}, {field:name|url} (image/file/link URL), {field:group.sub}, {term:name} and {option:name}.',
		);
		if ( ! isset( $a['include_field_types'] ) || $a['include_field_types'] ) {
			$types = array();
			foreach ( Content\Fields::types() as $name => $type ) {
				$types[ $name ] = array(
					'label'    => $type['label'],
					'category' => $type['category'],
					'options'  => array_map(
						static function ( $o ) {
							$short = array( 'type' => $o['type'] );
							if ( isset( $o['choices'] ) ) {
								$short['choices'] = wp_list_pluck( $o['choices'], 'value' );
							}
							if ( '' !== $o['default'] && array() !== $o['default'] ) {
								$short['default'] = $o['default'];
							}
							return $short;
						},
						$type['options']
					),
				);
			}
			$out['field_types'] = $types;
		}
		return $out;
	}

	private static function saved_definition( $kind, $def ) {
		if ( is_wp_error( $def ) ) {
			return $def;
		}
		$stats = Content\RestContent::with_stats( $kind, array( $def ) );
		return $stats[0];
	}

	public static function save_post_type( array $a ) {
		$ok = self::can_model();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return self::saved_definition( 'post_types', Content\Registry::save_post_type( (array) $a['definition'], isset( $a['previous_key'] ) ? sanitize_key( $a['previous_key'] ) : '' ) );
	}

	public static function save_taxonomy( array $a ) {
		$ok = self::can_model();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return self::saved_definition( 'taxonomies', Content\Registry::save_taxonomy( (array) $a['definition'], isset( $a['previous_key'] ) ? sanitize_key( $a['previous_key'] ) : '' ) );
	}

	public static function save_field_group( array $a ) {
		$ok = self::can_model();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		$def = (array) $a['definition'];
		if ( empty( $def['location'] ) ) {
			self::warn( __( 'The group has no location rules, so it is not shown anywhere yet.', 'brik-builder' ) );
		}
		$saved = Content\Registry::save_group( $def );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$saved['fields'] = array_map( array( __CLASS__, 'compact_content_field' ), $saved['fields'] );
		return $saved;
	}

	public static function delete_post_type( array $a ) {
		$ok = self::can_model();
		if ( is_wp_error( $ok ) ) {
			return $ok;
		}
		return Content\Registry::delete_post_type( sanitize_key( $a['key'] ), ! empty( $a['delete_posts'] ) );
	}

	public static function create_entries( array $a ) {
		$type   = sanitize_key( $a['post_type'] );
		$object = get_post_type_object( $type );
		if ( ! $object ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_mcp_args', sprintf( __( 'Unknown post type "%s". Create it with save_post_type first.', 'brik-builder' ), $type ) );
		}
		if ( ! current_user_can( $object->cap->create_posts ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to create this content.', 'brik-builder' ) );
		}
		$entries = array_slice( array_values( array_filter( (array) $a['entries'], 'is_array' ) ), 0, 50 );
		if ( count( (array) $a['entries'] ) > 50 ) {
			self::warn( __( 'Only the first 50 entries were created.', 'brik-builder' ) );
		}
		$created = array();
		foreach ( $entries as $i => $entry ) {
			$result = Content\Entries::create( $type, $entry );
			if ( is_wp_error( $result ) ) {
				$created[] = array(
					'index' => $i,
					'error' => $result->get_error_message(),
				);
				continue;
			}
			$result['index'] = $i;
			if ( ! $result['warnings'] ) {
				unset( $result['warnings'] );
			}
			$created[] = $result;
		}
		return array(
			'post_type' => $type,
			'created'   => count(
				array_filter(
					$created,
					static function ( $c ) {
						return isset( $c['id'] );
					}
				)
			),
			'entries'   => $created,
		);
	}

	public static function query_entries( array $a ) {
		$type   = sanitize_key( $a['post_type'] );
		$object = get_post_type_object( $type );
		if ( ! $object ) {
			/* translators: %s: post type */
			return new WP_Error( 'brik_mcp_args', sprintf( __( 'Unknown post type "%s".', 'brik-builder' ), $type ) );
		}
		if ( ! current_user_can( $object->cap->edit_posts ) ) {
			return new WP_Error( 'brik_forbidden', __( 'You are not allowed to read this content.', 'brik-builder' ) );
		}
		return Content\Entries::query( $a );
	}

	/* ---------------------------------------------------------------------
	 * Guide.
	 * ------------------------------------------------------------------- */

	/**
	 * Tree as JSON with one node per line, which reads far better than full pretty printing.
	 */
	private static function format_tree( array $nodes, $indent = '' ) {
		$flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
		$lines = array();
		foreach ( $nodes as $node ) {
			$line = $indent . '  {"type":' . wp_json_encode( $node['type'], $flags );
			if ( ! empty( $node['attrs'] ) ) {
				$line .= ',"attrs":' . wp_json_encode( $node['attrs'], $flags );
			}
			if ( ! empty( $node['children'] ) ) {
				$line .= ',"children":[' . "\n" . self::format_tree( $node['children'], $indent . '  ' ) . "\n" . $indent . '  ]';
			}
			$lines[] = $line . '}';
		}
		$body = implode( ",\n", $lines );
		return '' === $indent ? "[\n" . $body . "\n]" : $body;
	}

	/**
	 * The building guide, served by get_guide and the brik://guide resource.
	 * Lists that change with the site (modules, rules, tags) are generated.
	 */
	public static function guide() {
		$modules = array();
		foreach ( Modules::all() as $type => $def ) {
			$modules[ $def['category'] ][] = $type;
		}
		$module_lines = '';
		foreach ( Modules::categories() as $cat => $label ) {
			if ( ! empty( $modules[ $cat ] ) ) {
				$module_lines .= '- ' . wp_strip_all_tags( $label ) . ': ' . implode( ', ', $modules[ $cat ] ) . "\n";
			}
		}
		$rows = Modules::get( 'row' );
		$structures = $rows && ! empty( $rows['fields']['columns']['options'] ) ? implode( ' · ', wp_list_pluck( $rows['fields']['columns']['options'], 'value' ) ) : '1 · 1/2,1/2 · 1/3,1/3,1/3';
		$tags       = implode( ', ', array_map( static function ( $t ) { return '{' . $t . '}'; }, array_keys( Dynamic::tags() ) ) );
		$rules      = array();
		foreach ( ThemeBuilder::rule_types() as $key => $label ) {
			$rules[] = '`' . $key . '` (' . $label . ')';
		}
		$tokens = implode( ', ', array_map( static function ( $t ) { return 'var(--' . $t . ')'; }, Settings::token_names() ) );
		$fonts  = implode( ', ', array_slice( array_keys( Fonts::google() ), 0, 24 ) );
		$layouts = array();
		foreach ( Library::bundled() as $layout ) {
			$layouts[] = '`' . $layout['slug'] . '` (' . $layout['category'] . ')';
		}

		$hero = array(
			array(
				'type'     => 'section',
				'attrs'    => array(
					'padding'        => '128px 24px 112px',
					'padding@mobile' => '72px 20px',
					'bg_gradient'    => 'radial-gradient(60% 60% at 50% 0%, color-mix(in oklch, var(--primary) 16%, transparent), transparent)',
				),
				'children' => array(
					array(
						'type'     => 'row',
						'attrs'    => array(
							'columns'   => '1',
							'max_width' => '820px',
						),
						'children' => array(
							array(
								'type'     => 'column',
								'attrs'    => array(
									'items'      => 'center',
									'text_align' => 'center',
									'gap'        => '20px',
								),
								'children' => array(
									array(
										'type'  => 'heading',
										'attrs' => array(
											'text'  => 'New · Version 2.0',
											'level' => 'p',
											'style' => 'eyebrow',
										),
									),
									array(
										'type'  => 'heading',
										'attrs' => array(
											'text'  => 'Ship your website <span class="text-primary">this week</span>',
											'level' => 'h1',
											'style' => 'display',
										),
									),
									array(
										'type'  => 'text',
										'attrs' => array(
											'content'   => '<p>Everything you need to launch, from landing pages to docs, in one tool.</p>',
											'size'      => 'lead',
											'max_width' => '640px',
										),
									),
									array(
										'type'     => 'row',
										'children' => array(
											array(
												'type'     => 'column',
												'attrs'    => array(
													'layout'  => 'inline',
													'justify' => 'center',
													'gap'     => '12px',
												),
												'children' => array(
													array(
														'type'  => 'button',
														'attrs' => array(
															'text' => 'Start free',
															'size' => 'lg',
															'icon' => 'arrow-right',
															'link' => array( 'url' => '/signup' ),
														),
													),
													array(
														'type'  => 'button',
														'attrs' => array(
															'text'    => 'See pricing',
															'size'    => 'lg',
															'variant' => 'outline',
															'link'    => array( 'url' => '#pricing' ),
														),
													),
												),
											),
										),
									),
								),
							),
						),
					),
				),
			),
		);

		$card = static function ( $icon, $title, $text ) {
			return array(
				'type'     => 'column',
				'attrs'    => array(
					'bg_color'     => 'var(--card)',
					'border_width' => '1px',
					'border_color' => 'var(--border)',
					'radius'       => 'calc(var(--radius) + 4px)',
					'padding'      => '28px',
					'shadow'       => 'xs',
					'gap'          => '12px',
					'shadow@hover' => 'md',
					'translate_y@hover' => '-2px',
				),
				'children' => array(
					array(
						'type'  => 'icon',
						'attrs' => array(
							'icon'      => $icon,
							'icon_size' => '20px',
							'shape'     => 'rounded',
							'tone'      => 'soft',
						),
					),
					array(
						'type'  => 'heading',
						'attrs' => array(
							'text'  => $title,
							'level' => 'h3',
							'style' => 'h4',
						),
					),
					array(
						'type'  => 'text',
						'attrs' => array(
							'content' => '<p>' . $text . '</p>',
							'size'    => 'muted',
						),
					),
				),
			);
		};

		$features = array(
			array(
				'type'     => 'section',
				'attrs'    => array(
					'css_id'  => 'features',
					'padding' => '96px 24px',
					'bg_color' => 'var(--muted)',
				),
				'children' => array(
					array(
						'type'     => 'row',
						'attrs'    => array( 'max_width' => '720px' ),
						'children' => array(
							array(
								'type'     => 'column',
								'attrs'    => array( 'text_align' => 'center' ),
								'children' => array(
									array(
										'type'  => 'heading',
										'attrs' => array(
											'text'  => 'Features',
											'level' => 'p',
											'style' => 'eyebrow',
										),
									),
									array(
										'type'  => 'heading',
										'attrs' => array(
											'text'  => 'Everything in one place',
											'level' => 'h2',
										),
									),
								),
							),
						),
					),
					array(
						'type'     => 'row',
						'attrs'    => array(
							'columns'        => '1/3,1/3,1/3',
							'columns@tablet' => '1/2,1/2',
							'columns@mobile' => '1',
							'gap'            => '24px',
						),
						'children' => array(
							$card( 'zap', 'Fast', 'Pages render on the server and ship almost no JavaScript.' ),
							$card( 'palette', 'On brand', 'Design tokens keep colors, radius and fonts consistent.' ),
							$card( 'shield-check', 'Secure', 'Everything is sanitized and permission checked.' ),
						),
					),
				),
			),
		);

		$json = static function ( array $nodes ) {
			return self::format_tree( $nodes );
		};

		$guide = <<<'MD'
# Building with Brik

Brik pages are JSON trees rendered by PHP into clean HTML styled with shadcn/ui design tokens.
Everything you create here is editable later in the visual builder (open `builder_url`).

## Workflow

1. `list_modules` once to see the available module types and their content fields.
2. `create_page` with a complete tree (all sections at once is fine). Use `status: "draft"` until the user is happy.
3. Check the result: `render_preview` (HTML + warnings) or give the user `url` / `builder_url`.
4. Refine with `get_page` (`outline: true` lists node ids), then `update_node`, `insert_nodes`, `move_node`, `duplicate_node`, `remove_node`.
5. Site chrome: `create_menu`, then `create_template` for a header and footer; global look: `update_design_settings`.

Always read the `warnings` in tool results: they name unknown modules, unknown attributes and invalid option values.

## Tree format

A tree is a list of nodes. A node is `{ "type": "...", "attrs": { ... }, "children": [ ... ] }`.
`id` is optional (4–24 lowercase letters/digits, generated when missing; keep existing ids when editing).

Structure: **section > row > column > module**. Columns may also hold rows (nested rows); sections only live at the root.

Auto-wrapping repairs incomplete trees: a module at the root becomes section > row > column > module,
a module directly in a section gets a row and column, a module directly in a row gets a column.
So `[{"type":"heading","attrs":{"text":"Hello"}}]` is a valid page. Write the full structure when layout matters.

Repeating content (accordion items, pricing plans, slides, list items, form fields) lives in repeater
attributes (a list of objects), not in child nodes.

## Attributes

- Keys are flat: `{"padding": "96px 24px", "bg_color": "var(--muted)"}`.
- State suffixes: `key@tablet` (≤980px), `key@mobile` (≤767px), `key@hover`. Tablet falls back to desktop, mobile to tablet.
  Example: `"padding": "96px 24px", "padding@mobile": "56px 20px", "bg_color@hover": "var(--accent)"`.
- Lengths: any CSS length (`24px`, `2rem`, `60vh`, `clamp(2rem,5vw,4rem)`); bare numbers mean px.
  Spacing (margin, padding, border_width, radius) uses CSS shorthand: `"12px 24px"`.
- Text fields accept inline HTML (`<strong>`, `<em>`, `<br>`, `<a>`, `<span class="text-primary">`).
  Rich text (`text.content`) accepts `<p>`, `<ul>`, `<ol>`, `<blockquote>`, `<h2>`–`<h4>`, `<code>`.
- Links: `{"url": "/pricing", "new_tab": false, "nofollow": false}`. `#id` jumps to an element with `css_id`.
- Images: `{"id": 123, "url": "https://…", "alt": "…"}` or a URL string. Import remote images with `upload_media`.
- Icons: Lucide names (`arrow-right`, `check`, `sparkles`) or brand logos (`brand:github`). Use `search_icons`.
- Select fields only accept the listed option values; toggles are booleans.
- `preset: "<id>"` applies a saved style preset; node attrs win over it.
- In `update_node` (merge mode) a `null` value removes the key.

Shared attributes every element accepts (design tab): margin, padding, width, max_width, min_height, height,
self_align (left|center|right), overflow, bg_color, bg_gradient (CSS gradient), bg_image, bg_overlay (color over the image),
bg_size, bg_position, bg_attachment (fixed = parallax), font_family, font_size, font_weight, text_color, line_height,
letter_spacing, text_align, text_transform, border_width, border_style, border_color, radius,
shadow (none|xs|sm|md|lg|xl|2xl|inner or CSS), opacity, blur, brightness, grayscale, backdrop_blur, rotate, scale,
translate_x, translate_y, transition (ms), position, top/right/bottom/left, z_index,
animation (fade|slide-up|slide-down|slide-left|slide-right|zoom|zoom-out|flip|bounce|blur), animation_duration, animation_delay.
Advanced: css_id, css_class, el_link (whole element clickable), custom_css (`selector { … }` targets the element),
hide_on (["desktop","tablet","mobile"]), display (logged_in|logged_out), show_from / show_until (dates).

## Sections, rows and columns

**section** — full-width band. Default padding is `80px 24px`. Useful attrs: padding, bg_color, bg_gradient, bg_image + bg_overlay,
`dark: true` (dark palette inside), `fullwidth: true` (rows edge to edge), `sticky: true` (headers), `tag`
(section|header|footer|main|nav|aside|div), min_height + v_align (center|flex-end), divider_top / divider_bottom
(wave|waves|curve|tilt|triangle|arrow|mountains|zigzag) with divider_*_color.
Rows inside a section are centered and limited to the site container width; give a row `max_width` to make it narrower.

**row** — a grid. `columns` lists one fraction per child column, e.g. `"1/3,2/3"` for 2 columns. The number of
fractions must equal the number of columns. Columns stack on phones unless `columns@mobile` is set;
use `columns@tablet: "1/2,1/2"` to turn 3–4 columns into 2 on tablets (phones inherit the tablet
structure, so add `columns@mobile: "1"` to stack them again). More columns than fractions wrap onto new lines,
e.g. six columns in `"1/3,1/3,1/3"` make two lines of three. `gap` (column gap), `row_gap`,
`align_items` (start|center|end), `reverse` (reverse order when stacked), `full` (ignore container).
Flex options (all responsive): `direction` ""(auto: stacks on phones)|horizontal|vertical|horizontal-reverse|vertical-reverse, `sizing` ""|equal|auto
("auto" = columns fit their content or their own `width`), `justify` start|center|end|space-between|space-around|space-evenly,
`wrap` (bool, with equal/auto sizing). Example navbar row: `{"sizing":"auto","justify":"space-between","align_items":"center"}`.
Structures in the picker: STRUCTURES

**column** — stacks its children vertically with a 20px gap. `gap`, `items` (alignment of children: flex-start|center|flex-end|stretch),
`justify`, `layout: "inline"` (children side by side and wrapping, e.g. a button group), `sticky`.
Columns take all shared design attrs, so a column with bg_color, border_width, border_color, radius, padding and shadow is a card.

## Modules on this site

MODULES
Call `list_modules` for fields and `get_module` for style fields. Modules not listed don't exist on this site.

## Design tokens and colors

Use token variables instead of hard-coded colors so pages follow the site palette and dark sections work:
TOKENS. Corner radius: `var(--radius)` (e.g. `calc(var(--radius) + 4px)` for cards).
Global colors from design settings: `var(--brik-color-{id})`. Container width: `var(--brik-container)`.
Mix tokens for tints: `color-mix(in oklch, var(--primary) 12%, transparent)`.
Any CSS color also works (`#0f172a`, `oklch(0.6 0.2 260)`).
Utility classes in text: `text-primary`, `text-muted-foreground`, `font-semibold`.

Change the site-wide look with `update_design_settings`: `base` (neutral|zinc|slate|stone), `accent`
(blue|green|rose|orange|violet|red|yellow sets --primary), token overrides per mode, `radius`, `font_body`,
`font_heading`, `container`. Fonts (Google): FONTS, …

## Dark sections

`"dark": true` on a section switches every token inside it to the dark palette and paints the section with
`var(--background)`; text, cards, borders and buttons adapt automatically. Great for heroes, CTAs and footers.
Whole page: `page_settings: { "dark": true }`.

## Dynamic tags

Text attributes may contain: TAGS — e.g. `"© {year} {site_name}"`. In header/footer/body templates they
resolve against the page being viewed.

## Content types, custom fields and listings

Brik → Content defines custom post types, taxonomies and field groups (ACF-style custom fields).
`list_content_types` shows what exists. Create a type with `save_post_type`, its taxonomies with
`save_taxonomy`, and its fields with `save_field_group` (location `[[{"param":"post_type","operator":"==","value":"project"}]]`).
Field types: text, textarea, number, range, email, url, password, wysiwyg, oembed, image, file, gallery,
select, checkbox, radio, button_group, toggle, link, post_object, relationship, taxonomy, user, date,
datetime, time, color, map, repeater (options.sub_fields), group (options.sub_fields), message, tab.
Then fill it with `create_entries` (field values by name; image fields take URLs) and read it back with `query_entries`.
Types saved with `"brik": true` can be built visually, and theme builder body templates with `singular` /
`archive` rules for the post type design every entry at once.

Field tags resolve against the current post (the loop item inside listings):
`{field:price}` (formatted text: prefixes, choice labels, dates), `{field:price|raw}` (stored value),
`{field:photo|url}` (image/file/link/post URL; use it in image and link attributes, e.g.
`"image": "{field:photo|url}"`, `"link": {"url": "{field:website|url}"}`), `{field:address.city}` (group sub field),
`{field:team.name}` (a sub field from every repeater row), `|label`, `|count`; `{term:name}` reads term fields on
term archives and `{option:name}` reads the site options page.

## Presets

`save_preset` stores attrs for a module type under an id; apply with `attrs.preset`. A preset saved with
`default: true` styles every element of that type that has no preset. Edit a preset once to restyle all uses.

## Global elements and the library

`save_to_library` stores a tree. With `global: true`, embed it anywhere with
`{"type": "global", "attrs": {"ref": <library id>}}`; editing the library item updates every page.
`list_layouts` / `insert_layout` insert ready-made bundled layouts; `create_page` and `create_template` also take
`layout: "<slug>"` to start from one. Bundled layouts on this site: LAYOUTS.

## Theme builder

`create_template` with `area` header|footer|body and `conditions`
(`[{"type":"include","rule":"entire_site"}]` by default; excludes win; more specific rules win). Rules: RULES.
Quickest start: `create_template` with `area: "header", layout: "header-default"` or
`area: "footer", layout: "footer-default"` (no tree needed), then refine with `update_node`.
Headers: one section with `tag: "header"` and `sticky: true` (the section attr that makes the header stick), small padding (`14px 24px`), a row like `"1/4,3/4"`
with the logo on the left and a menu (plus a button) on the right (column `layout: "inline"`, `justify: "flex-end"`),
or a row `{"sizing":"auto","justify":"space-between","align_items":"center","direction@mobile":"horizontal"}`.
Header behaviour on the section: `overlay: true` floats a transparent header over the hero; `scroll_effect`
"shrink" | "hide" (hides on scroll down, returns on scroll up) | "shrink hide"; `scrolled_bg`, `scrolled_color`,
`scrolled_shadow`, `scrolled_padding`, `scrolled_blur` style the header once the page is scrolled
(e.g. transparent over the hero, solid after scrolling).
Menus: the `menu` module with `source: "custom"` takes a nested `items` tree (dropdowns, mega menus with
columns/grid/featured card/library design, buttons, badges, icons) — see `get_module` for the item shape.
Footers: a `dark` section with `tag: "footer"` and a few link columns plus `© {year} {site_name}`.
Set `status: "draft"` to disable a template without deleting it.

## Page options

`page_template`: `brik-canvas.php` (no theme header/footer, for landing pages), `brik-full-width.php`
(theme header/footer, no content container). `page_settings`: dark, hide_title, body_class, custom_css.

## Example: hero

```json
HERO
```

## Example: features grid

```json
FEATURES
```
MD;

		return strtr(
			$guide,
			array(
				'STRUCTURES' => $structures,
				'MODULES'    => $module_lines,
				'TOKENS'     => $tokens,
				'FONTS'      => $fonts,
				'TAGS'       => $tags,
				'RULES'      => implode( ', ', $rules ),
				'LAYOUTS'    => $layouts ? implode( ', ', $layouts ) : 'none',
				'HERO'       => $json( $hero ),
				'FEATURES'   => $json( $features ),
			)
		) . ( class_exists( 'WooCommerce' ) ? Woo\Mcp::guide() : '' );
	}
}
