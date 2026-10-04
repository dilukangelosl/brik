# Brik — Architecture & Roadmap

Brik is an open-source visual site builder for WordPress (plugin **Brik Builder**) with a
companion theme (**Brik**). Components follow the shadcn/ui design language and tokens.

Author: Diluk Angelo · License: GPLv2 or later

## Repository layout

```
plugin/brik-builder/     WordPress plugin
  brik-builder.php       bootstrap
  includes/              PHP core (namespace Brik)
  modules/               one file per module (definition + render)
  layouts/               bundled section/page layouts (JSON)
  assets/src/            builder app (React via wp.element), canvas + frontend JS, Tailwind input
  assets/build/          compiled assets (committed, so the plugin runs without a build step)
  resources/shadcn/      shadcn registry snapshot used as the styling reference
theme/brikwp/            base theme (BrikWP)
tools/                   build helpers (shadcn fetch, packaging)
dev/                     docker-compose for a local WordPress
```

## Data model

A page is a JSON tree stored in post meta `_brik_data`; `_brik_enabled` marks the post as built
with Brik. On save the rendered HTML is also written to `post_content`, so content survives if
the plugin is deactivated (no shortcode lock-in).

```json
{ "id": "k3j9a", "type": "section", "attrs": { "padding": "96px 0", "padding@mobile": "48px 0" },
  "children": [
    { "id": "p2x8d", "type": "row", "attrs": { "columns": "1/2,1/2" }, "children": [
      { "id": "q81mz", "type": "column", "children": [
        { "id": "z0c4e", "type": "heading", "attrs": { "text": "Hello", "level": "h1" } }
      ]}
    ]}
  ]}
```

* Structure: `section > row > column > module`. Columns may also contain rows (nesting).
* Attribute keys are flat. Suffixes select a state: `key@tablet`, `key@mobile`, `key@hover`.
* Repeating content (accordion items, slides, pricing plans, form fields) lives in repeater
  attributes, not child nodes — easier to edit and easier for AI clients to produce.
* `preset` attribute applies a saved style preset for that module type (node attrs win).
* `global` module type references a library item, so edits sync everywhere it's used.
* Text attributes accept dynamic tags: `{post_title}`, `{site_name}`, `{year}`, `{meta:key}`…

## Rendering

PHP is the single renderer (front end, builder canvas, MCP previews, theme builder).
`Renderer` walks the tree, each module's `render()` returns markup, and `Style` compiles
design attributes into scoped CSS (`.brik-n-{id}`) with tablet (≤980px) / mobile (≤767px)
media queries and `:hover` rules. CSS for the current request is printed in `<head>`.

Styling: Tailwind v4 compiled from the module templates using shadcn class recipes and
CSS variables (`--primary`, `--radius`, …). No global preflight — a scoped reset under `.brik`
keeps host themes untouched. Global settings edit the tokens (light + dark).

Front-end JS is vanilla and only covers what the platform doesn't: tabs, carousel, counters,
countdown, lightbox, mobile menu, AJAX forms, scroll animations. Accordions use `<details>`,
modals use `<dialog>`.

## Performance

Optimized assets are on by default (Brik → Settings → Performance) and never apply to the
builder canvas, which always loads the complete stylesheet and bundle.

* **CSS** — each page gets one minified file in `uploads/brik/css/{post}-{hash}.css`: the
  front-end library shaken down to rules whose classes the page's markup (header/footer/body
  templates included) can use, plus classes its scripts add at runtime and markup some modules
  fetch over AJAX, followed by the tokens, self-hosted `@font-face` rules and element CSS. The
  hash covers every input, so a changed page or setting gets a new file. The CSS for the first
  screen (header + first two sections) is inlined and the file loads without blocking.
* **JS** — `frontend/core.js` plus one script per `frontend/modules/*.js`, loaded when the
  selector passed to `on()` matches the rendered markup (`tools/frontend-split.mjs` writes the
  manifest). Scripts start once the page stylesheet has loaded.
* **Fonts** — `fonts_mode`: self-hosted (default; downloaded once to `uploads/brik/fonts`),
  Google, or system fonts.
* **Media** — the first image of the page loads eagerly with `fetchpriority="high"`, the rest
  and all iframes lazily; optional WebP/AVIF conversion of uploads and `<picture>` sources.
* `brik/v1/perf/{id}` (and the MCP tool `performance_report`) scores a page; `…/clean` (and
  `clean_page`) removes dead weight from its tree.

## Builder

`post.php?post=ID&action=brik` opens a full-screen app. The canvas is an iframe of the real
page (`?brik_canvas=1`), so the preview is exact. Changing a setting re-renders only that node
through `POST /brik/v1/render`.

Features (Divi parity targets):
- Drag & drop from module panel, reorder in canvas and layers tree, `+` insert buttons
- Column structures picker, nested rows
- Content / Design / Advanced settings tabs
- Responsive editing (desktop/tablet/mobile) and hover state editing per field
- Design: spacing, sizing, background (color, gradient, image, video, parallax), typography,
  borders, radius, shadows, filters, transforms, position, z-index, entrance animations, sticky
- Advanced: CSS ID/classes, custom CSS, visibility per device, display conditions
- Undo/redo history, copy/paste element, copy/paste styles, duplicate, keyboard shortcuts
- Layers (wireframe) panel, page settings, global colors & fonts, style presets
- Library: saved layouts, global modules, bundled layouts, import/export JSON
- Theme builder: header, footer and body templates with include/exclude conditions
- Inline text editing in the canvas

## Theme builder

CPT `brik_template` with meta `_brik_area` (`header|footer|body`) and `_brik_conditions`.
Conditions: entire site, front page, blog, 404, search, singular by post type / specific IDs,
archives by post type / taxonomy / author / date. Exclusions win; more specific rules win.
The Brik theme renders locations natively; other themes are supported through a full-page
takeover template when a template applies.

## MCP server

`/wp-json/brik/v1/mcp` speaks MCP (JSON-RPC 2.0, Streamable HTTP, JSON responses).
Authentication: WordPress Application Passwords. Clients without HTTP support can bridge with
`npx mcp-remote`. Tools expose the same schema the builder uses: list modules/fields,
create/update pages, insert/update/move/remove nodes, theme-builder templates, menus,
global settings, presets, library layouts, media sideload and render previews.

## Roadmap after 1.0

- WooCommerce modules (product grid, cart, checkout parts)
- Revisions UI for builder data
- Role editor / per-role module restrictions
- A/B testing
