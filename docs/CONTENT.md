# Brik Content — post types, taxonomies, custom fields, listings

Brik ships a content modelling layer: custom post types, taxonomies and field groups are defined
in the admin (Brik → Content), stored as JSON options, registered on `init`, and used from the
builder through dynamic tags, the Field module, Listings and Forms.

## Storage

| option | contents |
| --- | --- |
| `brik_post_types` | list of post type definitions |
| `brik_taxonomies` | list of taxonomy definitions |
| `brik_field_groups` | list of field group definitions |

Each list item has a stable `key` (slug for post types/taxonomies, `group_xxxxxx` for groups) and
an `active` flag. Definitions are versioned with `"version": 1`.

### Post type

```json
{
  "key": "project",              // post type slug, [a-z0-9_-], max 20 chars, not reserved
  "active": true,
  "singular": "Project",
  "plural": "Projects",
  "labels": {},                  // optional overrides of generated WP labels
  "description": "",
  "icon": "dashicons-portfolio", // dashicon class or "lucide:briefcase" (rendered as SVG data URI)
  "public": true,
  "has_archive": true,
  "archive_slug": "",            // defaults to key/plural slug
  "rewrite_slug": "projects",
  "hierarchical": false,
  "show_in_rest": true,
  "show_in_menu": true,
  "menu_position": 25,
  "supports": ["title", "editor", "thumbnail", "excerpt", "revisions", "custom-fields", "author", "page-attributes", "comments"],
  "taxonomies": ["category", "project_type"],
  "exclude_from_search": false,
  "capability_type": "post",
  "brik": true                   // enable the Brik builder for this type
}
```

### Taxonomy

```json
{
  "key": "project_type", "active": true, "singular": "Project type", "plural": "Project types",
  "labels": {}, "description": "", "hierarchical": true, "public": true, "show_in_rest": true,
  "show_admin_column": true, "rewrite_slug": "project-type", "post_types": ["project"]
}
```

### Field group

```json
{
  "key": "group_ab12cd", "active": true, "title": "Project details",
  "location": [ [ { "param": "post_type", "operator": "==", "value": "project" } ] ],
  "position": "normal",          // normal | side | after_title
  "style": "card",               // card | seamless
  "order": 0,
  "fields": [ field, ... ]
}
```

`location` is OR of AND-groups. params: `post_type`, `taxonomy` (term edit screens),
`post_template`, `page_type` (front_page|posts_page|top_level|child), `post_status`, `user_role`,
`options_page` (global site options page `brik-options`).

### Field

```json
{
  "key": "field_9f3a1c",         // stable id
  "name": "price",               // meta key (also used in {field:price}), [a-z0-9_]
  "label": "Price",
  "type": "number",
  "instructions": "",
  "required": false,
  "default": "",
  "placeholder": "",
  "width": 100,                  // 25 | 33 | 50 | 66 | 75 | 100 (% of the row in the meta box)
  "conditions": [ [ { "field": "field_xxx", "operator": "==|!=|empty|!empty|contains", "value": "" } ] ],
  "options": {}                  // type-specific, see below
}
```

Types and their `options`:

| type | options | stored value |
| --- | --- | --- |
| text, textarea, email, url, password | `maxlength`, `rows` (textarea), `prepend`, `append` | string |
| wysiwyg | `toolbar` basic\|full, `media` bool | HTML string |
| number, range | `min`, `max`, `step`, `prepend`, `append` | number |
| select, radio, checkbox, button_group | `choices` `[{value,label}]`, `multiple` (select), `allow_null` | string or list |
| toggle | `on_text`, `off_text` | bool |
| date, datetime, time | `display_format`, `return_format` (PHP date format) | `Y-m-d`, `Y-m-d H:i:s`, `H:i:s` |
| color | `alpha` | CSS color |
| image, file | `mime_types`, `preview_size` | attachment id |
| gallery | `min`, `max` | list of attachment ids |
| oembed | | URL |
| link | | `{url,title,target}` |
| post_object, relationship | `post_types`, `taxonomy`, `multiple` (post_object), `min`, `max` | post id or list of ids |
| taxonomy | `taxonomy`, `field_type` select\|checkbox, `save_terms` bool | term id(s) |
| user | `role`, `multiple` | user id(s) |
| map | `center_lat`, `center_lng`, `zoom` | `{address,lat,lng}` |
| repeater | `sub_fields` [field], `min`, `max`, `button_label`, `layout` table\|block\|row | list of objects |
| group | `sub_fields` [field] | object |
| message | `message` (no value) | — |
| tab | `placement` (UI only, no value) | — |

Values are stored as post meta under `name` (term meta / user meta / option `brik_opt_{name}` for
other locations). Repeater and group values are stored as a single serialized array under `name`.
Every field is registered with `register_post_meta()` (`show_in_rest` with a schema) for the
post types its group targets.

## PHP API (`includes/Content/` — namespace `Brik\Content`)

```php
brik_field( $name, $post_id = null, $format = true );   // formatted value (images → array, dates formatted…)
brik_raw_field( $name, $post_id = null );                // stored value
brik_update_field( $name, $value, $post_id = null );     // sanitized save
brik_field_object( $name, $post_id = null );             // field definition array or null
brik_field_html( $name, $post_id = null, array $args = array() ); // display markup (used by the Field module)
```

`Brik\Content\Registry`: `post_types()`, `taxonomies()`, `groups()`, `save_post_type( $def )`,
`delete_post_type( $key )`, … `fields_for_post_type( $type )` (flattened name → field),
`find_field( $name_or_key, $post_type = null )`.

## REST (`brik/v1`, capability `manage_options`)

```
GET    /content                     → { post_types, taxonomies, groups, field_types, reserved }
POST   /content/post-types          → save one (create/update by key) → definition
DELETE /content/post-types/{key}    → { deleted }  (?delete_posts=1 optional)
POST   /content/taxonomies          … same
DELETE /content/taxonomies/{key}
POST   /content/groups              … same
DELETE /content/groups/{key}
POST   /content/import              { post_types?, taxonomies?, groups? } → merged result
GET    /content/export?format=json|php
GET    /content/preview-labels?singular=&plural=   → generated WP labels
```

Saving validates keys (format, length, reserved WordPress names, uniqueness), flushes rewrite
rules on the next request when slugs change, and never deletes posts unless asked.

## Builder integration

* Dynamic tags: `{field:name}` (formatted text), `{field:name|raw}`, `{field:name|url}` (image/file
  URL), `{field:group.sub}`, `{term:name}` in term context, `{option:name}` for the options page.
  Image and link attributes accept tags too (`"image": "{field:photo|url}"`).
* `field` module: shows one field with formatting per type (text, image, gallery grid, link button,
  date, number with prefix/suffix, choice labels as badges, relationship as linked cards/list,
  repeater as list/table, map embed, oEmbed). Falls back to a placeholder in the canvas.
* `listing` module: queries posts (any type) and renders each with a **loop item** — a library item
  of kind `loop` designed in the builder (dynamic tags resolve to the current item), or a built-in
  card when none is chosen. Query: post type, taxonomy terms, meta conditions
  (`[{field, compare, value, type}]`), search, author, order by date/title/menu_order/rand/field,
  per page, offset, exclude current, related to current (shared terms or relationship field).
  Layout: grid/list/masonry/carousel, responsive columns, gap. Pagination: numbered, load more,
  infinite scroll. Empty state text.
* `listing_filter` module: targets a listing by CSS id (`target`) and offers filters — search,
  taxonomy (select/checkboxes/radio/pills), field (select/checkboxes/range slider/date range),
  sort, reset. Filtering is AJAX (`POST brik/v1/listing`) with URL query parameters
  (`?bf_{listing}_{filter}=…`) so results are shareable and work without JavaScript.
* Theme builder body templates with `singular` / `archive` conditions per post type are already
  supported; post modules and `{field:…}` resolve against the viewed post.

## Forms integration

`contact_form` gains `actions` (list): `email` (existing behaviour), `save_entry`, `webhook`,
`create_post`, `update_post` (edit an existing post: current post or `?post_id=` the user can edit),
`register_user`, `redirect`. `create_post` options: `post_type`, `post_status`
(draft|pending|publish), `author` (current user or a fixed user), and `mapping`
`[{field: "<form field name>", target: "title|content|excerpt|featured_image|meta:<field name>|tax:<taxonomy>"}]`.
Form field types gain `file` / `image` (uploads go to the media library, size/mime limits) and
`select` options can come from a taxonomy or post type (`options_source`). Permissions: guests may
only create posts when the form explicitly allows it (`allow_guests`), and the status can never be
`publish` for users without `publish_posts` unless the form owner chose it.

## MCP tools

`list_content_types`, `save_post_type`, `save_taxonomy`, `save_field_group`, `create_entries`
(bulk create posts of a type with title/content/fields/terms), `query_entries`, `delete_post_type`.
