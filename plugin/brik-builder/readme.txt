=== Brik Builder ===
Contributors: dilukangelo
Tags: page builder, visual editor, drag and drop, theme builder, mcp
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual drag & drop site builder with shadcn/ui style components, a theme builder and a built-in MCP server for AI assistants.

== Description ==

Brik is a visual site builder for WordPress. Design pages on a live canvas of your real site, build
headers and footers with the theme builder, and use a library of components that follow the
shadcn/ui design language.

= Visual builder =

* Drag & drop sections, rows, columns and modules on a live preview of the page
* Inline text editing, layers panel, undo and redo, copy and paste of elements and styles
* Responsive editing for desktop, tablet and mobile, and hover styles for any setting
* Spacing, backgrounds (gradients, images, video, parallax), typography, borders, shadows, filters,
  transforms, sticky positioning and entrance animations
* Custom CSS per element, visibility per device and display conditions
* Global colors, fonts and light/dark design tokens, style presets and global elements
* Library of saved layouts with JSON import and export, plus bundled section layouts
* Dynamic tags such as {post_title}, {site_name} and {year}

= Theme builder =

Create header, footer and body templates and choose where they appear: the entire site, the front
page, the blog, post types, specific pages, archives, categories and tags, authors, search results or
the 404 page. Exclusions and more specific rules win.

= Components =

Headings, text, buttons, badges, alerts, avatars, icons, cards, call to action boxes, pricing tables,
stats and counters, testimonials, team members, timelines, tables, images, galleries, carousels, video,
maps, accordions, tabs, modals, tooltips, countdowns, contact forms with stored submissions, newsletter
signup, menus with a mobile sheet, post modules for templates, and more than 1,800 Lucide icons and
brand logos.

= Clean output =

Pages are rendered by PHP into lean HTML with scoped CSS. A static copy of every page is kept in the
post content, so your content stays readable if you ever deactivate the plugin.

= Build with AI (MCP) =

Brik includes a Model Context Protocol server. Connect Claude Code, Claude Desktop, Cursor, VS Code or
any MCP client, and ask it to build landing pages, headers and footers, menus or a new color scheme.
Clients sign in with a WordPress application password and can only do what that user is allowed to do.
Open Brik → Connect AI to create a password and copy a ready-made configuration.

== Installation ==

1. Upload the `brik-builder` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New → Upload Plugin.
2. Activate Brik Builder.
3. Under Brik → Settings, choose the post types that can be edited with Brik.
4. Click "Edit with Brik" on any page, or use Brik → Dashboard → New page with Brik.

The companion Brik theme is optional. Brik works with any theme; with block themes, theme builder
headers and footers are shown through a full-page template.

== Frequently Asked Questions ==

= Does Brik work with my theme? =

Yes. Pages built with Brik render inside any theme. Use the "Brik Canvas" page template for pages
without the theme header and footer, or "Brik Full Width" for edge-to-edge layouts. The Brik theme adds
native support for theme builder locations and shares the design tokens.

= What happens if I deactivate the plugin? =

Brik stores a static HTML copy of each page in the post content, so the text and images remain
visible. Layout styles and interactive components need the plugin.

= How do I connect an AI assistant? =

Go to Brik → Connect AI, create an application password and copy the snippet for your client.
Application passwords require HTTPS, except on local development sites.

= Is the MCP server safe to leave on? =

Every request must be authenticated with an application password, and each action checks the
permissions of that user. Content from users without the unfiltered_html capability is sanitized.
You can turn the server off under Brik → Settings.

= Can I add my own modules? =

Yes. A module is a single PHP file that returns its definition. Register it on the
`brik/register_modules` hook. See docs/MODULES.md in the source repository.

= Where are form submissions stored? =

Under Brik → Submissions, visible to administrators.

== Changelog ==

= 1.2.1 =
* Critical CSS includes the classes scripts add above the fold, fixing layout shift while the page stylesheet loads on slow connections.
* Off-screen sections and animated backgrounds pause their CSS animations.
* The aurora background animates on the GPU instead of repainting every frame.
* Device mockup videos load and play only while visible.
* Generated page stylesheets are served with a one-year cache header on Apache and LiteSpeed.

= 1.2.0 =
* Performance: per-page optimized CSS with critical CSS inlined, per-element JavaScript, self-hosted fonts, WebP/AVIF uploads, a performance score with savings suggestions and a one-click page cleaner.
* Design system: global CSS classes, spacing/radius/type/shadow variables, a design system panel and components with overridable content and safe detach.
* Accessibility, SEO and responsive audits with click-to-element issues and one-click fixes.
* Version history with section-level compare and restore, staging with shareable preview links, deploy and scheduled publish or rollback.
* Dynamic data from ACF, Meta Box, Pods, WooCommerce, users, terms and meta; a visual query builder for listings; nested display conditions.
* Responsive timeline, fluid values, a custom-width breakpoint simulator, "Why is this broken?" diagnostics, a CSS inspector and Designer/Developer modes.
* Convert a page to Gutenberg blocks or static HTML and back.
* Fixes: MCP validation of rows without columns, anchor links no longer marked as the current menu item.

= 1.1.0 =
* WooCommerce: product page elements, product grids with quick view, product filters, mini cart drawer, styled cart, checkout, account and order pages.
* Theme builder conditions for products, shop, product categories, cart, checkout and account.
* Product and cart dynamic tags, and MCP tools for products and orders.

= 1.0.0 =
* Initial release.
