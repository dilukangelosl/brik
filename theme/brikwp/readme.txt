=== BrikWP ===
Contributors: dilukangelo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: blog, one-column, two-columns, right-sidebar, grid-layout, custom-logo, custom-menu, featured-images, footer-widgets, threaded-comments, translation-ready, wide-blocks, block-styles, editor-style, accessibility-ready, sticky-post

A clean, neutral base theme in the shadcn/ui style, built to pair with the Brik Builder plugin.

== Description ==

Brik is a classic theme with a quiet, neutral look: Inter or the system font, generous whitespace, thin borders and soft shadows. It works on its own as a complete blog theme and becomes the native home for sites built with the Brik Builder plugin.

* Sticky header with a blurred background, logo or site title, keyboard-accessible dropdown menus, a dark mode toggle and a slide-in mobile menu.
* Blog, archive and search pages as a card grid with category badges, excerpts, reading time and numbered pagination.
* Single posts in a readable column with styled core blocks (headings, lists, quotes, code, tables, buttons, images, galleries, separators), tags, an author box, previous/next links and comments.
* Optional sidebar on single posts and up to three footer widget columns.
* Dark mode that follows the operating system by default and remembers the visitor's choice.
* Block editor styles that match the front end.

= Brik Builder integration =

With the plugin active, the theme:

* prints Brik theme-builder headers and footers in place of its own when a template applies;
* gives pages built with Brik the full width of the screen, with no title or theme padding;
* uses the plugin's design tokens (colors, radius, fonts, container width) on every page, and in the block editor;
* shares the plugin's color mode preference, so the theme toggle and the Brik theme toggle module stay in sync.

Without the plugin, the theme uses its own copy of the default neutral tokens.

= Customizer =

Appearance > Customize > Theme options:

* Show dark mode toggle.
* Footer copyright text. Use {year} for the current year and {site} for the site title.

The logo is set under Site Identity.

== Installation ==

1. In your admin, go to Appearance > Themes and click Add New Theme.
2. Upload the theme zip, install and activate it.
3. Assign menus to the Primary and Footer locations under Appearance > Menus.
4. Optionally install Brik Builder to design pages, headers and footers visually.

== Frequently Asked Questions ==

= Does the theme load fonts from Google? =

Not by itself. The theme uses Inter when it is installed on the visitor's device and falls back to the system font. When Brik Builder is active and its Google Fonts setting is on, the fonts chosen in the plugin are loaded.

= How do I change the colors? =

Install Brik Builder and edit the global design tokens. The theme picks them up automatically, in light and dark mode.

== Changelog ==

= 1.2.1 =
* Renamed to BrikWP (slug brikwp) for the WordPress.org directory.
* New screenshot without third-party images.

= 1.2.0 =
* Compatibility with Brik Builder 1.2.

= 1.1.0 =
* WooCommerce support: styled shop and product pages and a header cart icon.

= 1.0.0 =
* Initial release.

== Copyright ==

BrikWP WordPress Theme, (C) 2026 Diluk Angelo.
BrikWP is distributed under the terms of the GNU GPL v2 or later.

This theme bundles the following third-party resources:

Lucide icons, Copyright (c) Lucide Contributors
License: ISC
Source: https://lucide.dev

Screenshot
License: GPLv2 or later. Created by Diluk Angelo for this theme; contains no third-party images.
