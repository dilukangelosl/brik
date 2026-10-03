# Writing Brik modules

A module is one PHP file in `plugin/brik-builder/modules/` that returns its definition.
`modules/button.php` is the reference example.

```php
<?php
use Brik\Fields;

defined( 'ABSPATH' ) || exit;

return array(
	'type'        => 'badge',                // unique slug, [a-z0-9_]
	'title'       => __( 'Badge', 'brik-builder' ),
	'category'    => 'basic',                // structure|basic|content|media|interactive|forms|site|post
	'icon'        => 'tag',                  // Lucide icon name (resources/icons.json)
	'description' => 'Short label. variant: default|secondary|outline|destructive.', // shown to AI clients too
	'fields'      => array(
		'text'    => Fields::field( 'text', __( 'Text', 'brik-builder' ), 'content', array( 'default' => 'New' ) ),
		'variant' => Fields::field( 'select', __( 'Variant', 'brik-builder' ), 'content', array(
			'default' => 'default',
			'options' => Fields::opts( array( 'default' => 'Default', 'outline' => 'Outline' ) ),
		) ),
	),
	'render'      => static function ( array $a, Brik\Context $ctx ) {
		return '<span class="inline-flex rounded-md border px-2 py-0.5 text-xs font-medium">' . brik_inline( $a['text'] ) . '</span>';
	},
);
```

The renderer wraps the returned markup in the element wrapper
`<div class="brik-el brik-{type} brik-n-{id} …">`, so `render` returns only the inside.
Every module automatically gets the shared Design and Advanced fields (spacing, sizing,
background, typography, border, shadow, filters, transform, position, animation, CSS id/classes,
custom CSS, visibility). Don't redeclare them.

## Definition keys

| key | meaning |
| --- | --- |
| `type`, `title`, `category`, `icon`, `description` | identity; `description` should list the important attrs/values |
| `fields` | `key => Fields::field( type, label, group, extra )` |
| `render` | `fn( array $attrs, Brik\Context $ctx ): string` |
| `tag` | wrapper tag, string or `fn( $attrs )` (default `div`) |
| `class` | extra wrapper classes, string or `fn( $attrs )` |
| `css` | `fn( $attrs, $wrapSelector, $node ): string` extra raw CSS |
| `children` | structural modules only |
| `raw` | skip the wrapper (only the `global` module uses this) |

`$attrs` already contains defaults, presets and resolved dynamic tags (`{post_title}`, `{year}` …).

## Field types

`text` (inline HTML allowed, output with `brik_inline()`), `textarea`, `richtext` (output with
`brik_rich()`), `code`, `number`, `range` (`min`, `max`, `step`, `unit`), `unit` (CSS length,
bare numbers become px), `select` (`options`), `toggle`, `color`, `gradient`, `image`
(`['id'=>, 'url'=>, 'alt'=>]` or URL string), `gallery` (list of images), `video` (URL or
`['url'=>]`; add `'media_type' => 'audio'` to pick audio files), `link` (`['url'=>, 'new_tab'=>bool, 'nofollow'=>bool]`), `icon` (Lucide name or
`brand:github`), `align`, `spacing` (CSS shorthand like `10px 20px`), `font`, `shadow`,
`date`, `devices`, `columns`, `menu` (nav menu id), `post_type`, `taxonomy`, `library`,
`repeater` (`fields` => sub-fields, `title_field` => sub-field shown as item label,
`default` => list of items).

Common `extra` keys: `default`, `description`, `placeholder`, `tab` (`content|design|advanced`,
default `content`), `responsive` (has tablet/mobile values), `hover` (has a hover value),
`show_if` (`['other_field' => 'value' | ['a','b'] | '!']`, `'!'` = not empty),
`css` (see below), `inline` (text can be edited directly on the canvas).

## Styles from fields

A field with `css` writes CSS automatically for every state it supports:

```php
'css' => array( Fields::WRAP . ' .brik-badge', 'background-color' )      // selector, property
'css' => array( 'selector' => Fields::WRAP, 'prop' => 'gap', 'value' => 'calc({{v}} * 2)' )
'css' => array( 'selector' => Fields::WRAP, 'map' => array( 'left' => 'margin-right:auto' ) )
```

`Fields::WRAP` (`{{wrap}}`) is replaced with the element selector. Responsive values are written
as `key@tablet` / `key@mobile`, hover values as `key@hover`.

Helpers for element-level design groups:

* `Fields::typography( 'title', __( 'Title', 'brik-builder' ), Fields::WRAP . ' .brik-title' )` gives
  `title_font_family`, `title_font_size`, `title_text_color`, … with responsive/hover support.
* `Fields::box( 'card', __( 'Card', 'brik-builder' ), Fields::WRAP . ' .brik-card' )` gives
  `card_bg`, `card_color`, `card_border_width`, `card_border_color`, `card_radius`,
  `card_padding`, `card_shadow`.

## Markup and styling

* Use the shadcn/ui class recipes (see `resources/shadcn/*.tsx`) with Tailwind utilities.
  Colors must come from tokens: `bg-primary`, `text-muted-foreground`, `border-border`, …
* Write class names as complete literal strings — Tailwind scans the PHP files.
* The wrapper already carries `brik-{type}` (and `brik-el`, `brik-n-{id}`), so don't reuse
  `brik-{type}` for an inner element — its styles would also hit the wrapper.
* Give the important inner elements a stable `brik-*` class so `css` selectors and users can
  target them.
* Module-specific CSS that utilities can't express goes in `assets/src/css/modules/{name}.css`.
* Front-end behaviour goes in `assets/src/frontend/modules/{name}.js`:

  ```js
  import { on } from '../core.js';
  on('.brik-tabs', (el) => { /* runs once per element, also for markup inserted in the builder */ });
  ```

  Prefer native elements first: `<details>` for disclosure, `<dialog>` for modals, CSS scroll
  snap for carousels.
* Heavy effects (canvas, WebGL, scroll-driven animation) go in `assets/src/fx/{name}.js` and are
  loaded only on pages that use them: call `$ctx->script( '{name}' )` from `render`. Inside, import
  helpers from `./_api.js` (`on`, `loop` — an rAF loop that pauses off-screen, `fitCanvas`,
  `cssColor`, `reducedMotion`, `inCanvas`). Always respect reduced motion and pause off-screen work.
* `$ctx->canvas` is true inside the builder preview. Use `$ctx->placeholder( 'text' )` when a
  module has nothing to show yet (e.g. no image chosen).
* `$ctx->uid( 'suffix' )` gives unique DOM ids; `$ctx->css( $selector, $declarations )` adds
  computed CSS.

Helpers (`includes/helpers.php`): `brik_cls()`, `brik_attrs()`, `brik_inline()`, `brik_rich()`,
`brik_icon( $name, $class )`, `brik_link_attrs( $link, $extra )`, `brik_image( $image, $size, $attrs )`,
`brik_image_url()`, `brik_button_class( $variant, $size )`.
Shared helpers for a group of modules go in `includes/helpers/{group}.php` (loaded automatically).

## Third-party modules

```php
add_action( 'brik/register_modules', function () {
	Brik\Modules::add( require __DIR__ . '/my-module.php' );
} );
```
