# Brik MCP server

Brik Builder ships a [Model Context Protocol](https://modelcontextprotocol.io) server, so AI clients
such as Claude Code, Claude Desktop, Cursor or VS Code can build and edit your site: pages, sections,
individual elements, header/footer templates, menus, media and the global design system.

Everything goes through the same code paths as the visual builder. Trees sent by a client are repaired
(lone modules get wrapped in section > row > column), sanitized for the connected user and saved exactly
like a builder save, so the result is fully editable in the builder afterwards.

## Endpoint

```
POST https://example.com/wp-json/brik/v1/mcp
```

* Transport: Streamable HTTP with plain JSON responses (no SSE stream). Single messages and batches.
* Protocol versions: `2025-06-18`, `2025-03-26`, `2024-11-05`.
* Stateless: the `Mcp-Session-Id` returned by `initialize` doesn't need to be sent back.
* Turn the server on or off under **Brik → Settings → MCP server** (403 when off).

## Authentication

The server uses normal WordPress REST authentication. The intended method is an
**Application Password** sent as HTTP Basic auth:

```
Authorization: Basic base64(username:application-password)
```

The client can do exactly what that user can do, and every tool re-checks capabilities for the object it
touches (`edit_post` for a page, `edit_theme_options` for templates, menus and design settings,
`upload_files` for media, `publish_*` for publishing, `manage_options` for site settings). Users without
`edit_posts` are refused. Revoke a password at any time from the user's profile.

Application passwords require HTTPS. On a local site (`WP_ENVIRONMENT_TYPE` set to `local`) WordPress also
allows them over plain HTTP.

### The quick way

Open **Brik → Connect AI** in wp-admin, click **Create password** and copy the snippet for your client.
The snippets already contain the encoded `Authorization` header.

### By hand

1. **Users → Profile → Application Passwords**: add a password named e.g. "Brik MCP".
2. Encode `username:password` (spaces in the password can stay):

   ```sh
   printf 'admin:abcd efgh ijkl mnop qrst uvwx' | base64
   ```

## Connecting clients

Replace `https://example.com` and `BASE64` with your site URL and the encoded credentials.

### Claude Code

```sh
claude mcp add --transport http brik https://example.com/wp-json/brik/v1/mcp \
  --header "Authorization: Basic BASE64"
```

### Claude Desktop and other stdio-only clients

Bridge with [`mcp-remote`](https://www.npmjs.com/package/mcp-remote) (needs Node.js). In
`claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "brik": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://example.com/wp-json/brik/v1/mcp", "--header", "Authorization:${BRIK_AUTH}"],
      "env": { "BRIK_AUTH": "Basic BASE64" }
    }
  }
}
```

Add `"--allow-http"` to `args` for a local `http://` site.

### Cursor

`~/.cursor/mcp.json` (or `.cursor/mcp.json` in a project):

```json
{
  "mcpServers": {
    "brik": {
      "url": "https://example.com/wp-json/brik/v1/mcp",
      "headers": { "Authorization": "Basic BASE64" }
    }
  }
}
```

### VS Code

`.vscode/mcp.json`:

```json
{
  "servers": {
    "brik": {
      "type": "http",
      "url": "https://example.com/wp-json/brik/v1/mcp",
      "headers": { "Authorization": "Basic BASE64" }
    }
  }
}
```

### curl

```sh
curl -s https://example.com/wp-json/brik/v1/mcp \
  -H "Authorization: Basic BASE64" -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

### MCP Inspector

```sh
npx -y @modelcontextprotocol/inspector --cli https://example.com/wp-json/brik/v1/mcp \
  --transport http --method tools/list --header "Authorization: Basic BASE64"
```

## Tools

| Tool | What it does |
| --- | --- |
| `get_guide` | The full building guide: tree format, attributes, tokens, theme builder, examples. |
| `list_modules` | Every module type with its content fields, defaults and options (`category` filter). |
| `get_module` | Full field schema of one module, including shared design/advanced fields. |
| `list_content` | Pages/posts the user can edit, with URLs and whether they use Brik. |
| `get_page` | A page/template/library item with its tree, or a compact outline (`outline: true`). |
| `create_page` | New page or post from a tree or a bundled `layout`; status, page template, front page. |
| `update_page` | Title, slug, status, page template, page settings or a full tree replace. |
| `insert_nodes` | Insert nodes under a parent at an index; wrapped only as much as the parent needs. |
| `update_node` | Merge or replace one node's attributes (`null` deletes a key). |
| `remove_node` | Delete a node and its children. |
| `move_node` | Move a node to another parent/position. |
| `duplicate_node` | Copy a node with fresh ids, right after the original. |
| `list_templates` | Theme builder templates with conditions in plain words. |
| `create_template` | New header/footer/body template with conditions, from a tree or a bundled `layout` (e.g. `header-default`, `footer-default`). |
| `update_template` | Change a template's title, tree, conditions or status. |
| `get_design_settings` | Palette, accent, resolved tokens, radius, fonts, container, global colors. |
| `update_design_settings` | Change the global design system. |
| `list_presets` / `save_preset` / `delete_preset` | Style presets per module type. |
| `list_library` / `save_to_library` | Saved layouts and global elements. |
| `list_layouts` / `insert_layout` | Bundled layouts (or a library item) inserted into a page. |
| `list_menus` / `create_menu` | Navigation menus, nested items, theme locations. |
| `upload_media` | Import an image, video or PDF from a URL into the media library. |
| `render_preview` | Render a saved page or an unsaved tree; HTML, CSS size and warnings. |
| `search_icons` | Find Lucide icon names and `brand:*` logos by keyword. |
| `update_site` | Site title, tagline, front page and posts page. |

Tool failures (missing permissions, unknown ids, invalid arguments) come back as results with
`isError: true` and a message the model can act on. Successful results include `warnings` when something
in a tree needs attention: unknown module types or attributes, invalid option values, unknown icons, row
structures that don't match their columns.

## Resources and prompts

* Resources: `brik://guide` (Markdown guide) and `brik://modules` (module list as JSON).
* Prompts: `build_landing_page` (topic, audience, sections, style), `build_header` (site_name, links, cta,
  style), `build_footer` (columns, social) and `restyle_site` (brand).

## The tree format in short

```json
[
  {"type": "section", "attrs": {"padding": "96px 24px", "padding@mobile": "56px 20px", "bg_color": "var(--muted)"}, "children": [
    {"type": "row", "attrs": {"columns": "1/2,1/2"}, "children": [
      {"type": "column", "children": [
        {"type": "heading", "attrs": {"text": "Hello", "level": "h1"}}
      ]},
      {"type": "column", "children": [
        {"type": "button", "attrs": {"text": "Get started", "link": {"url": "/signup"}}}
      ]}
    ]}
  ]}
]
```

* Structure: section > row > column > module. Columns may hold rows. Lone modules are wrapped automatically.
* Attributes are flat; `key@tablet`, `key@mobile` and `key@hover` set responsive and hover values.
* Colors should use design tokens (`var(--primary)`, `var(--muted-foreground)`, …) so dark sections and
  palette changes keep working. `"dark": true` on a section switches it to the dark palette.
* Text accepts dynamic tags such as `{site_name}` and `{year}`.

Ask the client to call `get_guide` for the complete reference.

## Example prompts

* "Build a landing page for an analytics product called Lumen: hero, six features, stats, pricing with
  three plans, FAQ and a dark call-to-action. Use the canvas template and keep it as a draft."
* "On the Lumen page, make the pricing section dark and move the FAQ above it."
* "Create a sticky header with the site name on the left, a menu with Features, Pricing and Blog and a
  Get started button, shown on the whole site except the landing page."
* "Create a footer with Product, Company and Resources link columns, GitHub and LinkedIn icons and the
  copyright line."
* "Switch the site to the slate palette with a blue accent, radius 0.75rem and Geist for headings."
* "Import this photo and use it as the hero background with a dark overlay: https://…"

## Security notes

* The endpoint accepts only authenticated requests (401 with `WWW-Authenticate: Basic` otherwise).
* Cookie-authenticated calls need a REST nonce, so browsers can't be tricked into calling it.
* Content from users without `unfiltered_html` is sanitized the same way as in the builder (scripts and
  unsafe attributes are stripped).
* `upload_media` uses WordPress' safe HTTP API (no private or local addresses) and accepts only images,
  videos and PDFs.
