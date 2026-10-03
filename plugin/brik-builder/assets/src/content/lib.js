// State, REST helpers and small utilities shared by the content screens.
import { api, useSyncExternalStore, __, sprintf } from '../builder/wp.js';
import { iconSvg } from '../builder/icons.js';

export const cfg = window.brikContent || {};

/* ------------------------------------------------------------------------
 * Store.
 * ---------------------------------------------------------------------- */

let state = {
  ready: false,
  error: null,
  post_types: [],
  taxonomies: [],
  groups: [],
  field_types: [],
  reserved: { post_types: [], taxonomies: [], names: [] },
  wp: { types: {}, taxonomies: {}, statuses: {} },
  counts: {},
  toasts: [],
  location_params: [],
  route: parseHash(),
  dirty: false,
};

const listeners = new Set();

export function getState() {
  return state;
}

export function setState(patch) {
  state = { ...state, ...(typeof patch === 'function' ? patch(state) : patch) };
  listeners.forEach((l) => l());
}

export function useStore(selector = (s) => s) {
  return useSyncExternalStore(
    (l) => {
      listeners.add(l);
      return () => listeners.delete(l);
    },
    () => selector(state)
  );
}

let toastSeq = 0;
export function toast(message, type = 'info', timeout = 3200) {
  const id = ++toastSeq;
  setState((s) => ({ toasts: [...s.toasts, { id, message, type }] }));
  setTimeout(() => setState((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })), timeout);
}

export function errorMessage(e, fallback) {
  if (!e) return fallback;
  if (e.message) return e.message;
  if (typeof e === 'string') return e;
  return fallback || __('Something went wrong.', 'brik-builder');
}

/* ------------------------------------------------------------------------
 * Routing (hash based: #/post-types/project, #/field-groups/new …).
 * ---------------------------------------------------------------------- */

export function parseHash() {
  const raw = (window.location.hash || '').replace(/^#\/?/, '');
  const [section = 'post-types', id = null] = raw.split('?')[0].split('/').map(decodeURIComponent);
  const query = Object.fromEntries(new URLSearchParams(raw.split('?')[1] || ''));
  const sections = ['post-types', 'taxonomies', 'field-groups'];
  return { section: sections.includes(section) ? section : 'post-types', id: id || null, query };
}

let guard = null; // () => Promise<boolean> — set by editors with unsaved changes

export function setGuard(fn) {
  guard = fn;
}

export async function navigate(to) {
  if (guard && getState().dirty) {
    const ok = await guard();
    if (!ok) return false;
  }
  setState({ dirty: false });
  guard = null;
  if (window.location.hash !== to) window.location.hash = to;
  else setState({ route: parseHash() });
  return true;
}

let lastHash = window.location.hash;
window.addEventListener('hashchange', () => {
  // Browser back/forward while an editor has unsaved work.
  if (getState().dirty && !window.confirm(__('You have unsaved changes. Leave without saving?', 'brik-builder'))) {
    history.replaceState(null, '', lastHash || '#/');
    return;
  }
  lastHash = window.location.hash;
  setState({ route: parseHash(), dirty: false });
  window.scrollTo(0, 0);
});

window.addEventListener('beforeunload', (e) => {
  if (getState().dirty) {
    e.preventDefault();
    e.returnValue = '';
  }
});

/* ------------------------------------------------------------------------
 * REST.
 * ---------------------------------------------------------------------- */

const BASE = '/brik/v1/content';

function asList(v) {
  if (Array.isArray(v)) return v;
  if (v && typeof v === 'object') return Object.values(v);
  return [];
}

export async function loadContent() {
  const data = await api({ path: BASE });
  setState({
    ready: true,
    error: null,
    post_types: asList(data.post_types),
    taxonomies: asList(data.taxonomies),
    groups: asList(data.groups),
    field_types: normalizeFieldTypes(data.field_types),
    reserved: normalizeReserved(data.reserved),
    location_params: Array.isArray(data.location_params) ? data.location_params : [],
  });
  return data;
}

export async function loadWp() {
  const opt = (p) => api({ path: p }).catch(() => ({}));
  const [types, taxonomies, statuses] = await Promise.all([
    opt('/wp/v2/types?context=edit'),
    opt('/wp/v2/taxonomies?context=edit'),
    opt('/wp/v2/statuses?context=edit'),
  ]);
  setState({ wp: { types, taxonomies, statuses } });
  return { types, taxonomies };
}

// Post and term counts come from the X-WP-Total header of a one-item collection request.
export async function loadCounts() {
  const s = getState();
  const counts = {};
  const total = async (path) => {
    try {
      const res = await api({ path, parse: false });
      return parseInt(res.headers.get('X-WP-Total') || '0', 10);
    } catch (e) {
      return null;
    }
  };
  await Promise.all([
    ...s.post_types.map(async (pt) => {
      const wp = s.wp.types[pt.key];
      if (!wp || !wp.rest_base) return;
      const ns = wp.rest_namespace || 'wp/v2';
      counts[`pt:${pt.key}`] = await total(`/${ns}/${wp.rest_base}?per_page=1&_fields=id&status=publish,future,draft,pending,private`);
    }),
    ...s.taxonomies.map(async (tx) => {
      const wp = s.wp.taxonomies[tx.key];
      if (!wp || !wp.rest_base) return;
      const ns = wp.rest_namespace || 'wp/v2';
      counts[`tx:${tx.key}`] = await total(`/${ns}/${wp.rest_base}?per_page=1&_fields=id&hide_empty=false`);
    }),
  ]);
  // Definitions carry server-side counts; use them where the REST collection wasn't available.
  for (const pt of s.post_types) if (counts[`pt:${pt.key}`] == null && typeof pt.count === 'number') counts[`pt:${pt.key}`] = pt.count;
  for (const tx of s.taxonomies) if (counts[`tx:${tx.key}`] == null && typeof tx.count === 'number') counts[`tx:${tx.key}`] = tx.count;
  setState({ counts });
}

export async function refreshAll() {
  await loadContent();
  await loadWp();
  loadCounts();
}

const KINDS = { post_types: 'post-types', taxonomies: 'taxonomies', groups: 'groups' };

export async function saveItem(kind, def) {
  const saved = await api({ path: `${BASE}/${KINDS[kind]}`, method: 'POST', data: def });
  const item = saved && saved.key ? saved : saved && saved.definition ? saved.definition : def;
  setState((s) => {
    const list = s[kind].slice();
    const i = list.findIndex((x) => x.key === item.key);
    if (i >= 0) list[i] = item;
    else list.push(item);
    return { [kind]: list };
  });
  return item;
}

export async function deleteItem(kind, key, params = {}) {
  const q = new URLSearchParams(params).toString();
  await api({ path: `${BASE}/${KINDS[kind]}/${encodeURIComponent(key)}${q ? `?${q}` : ''}`, method: 'DELETE' });
  setState((s) => ({ [kind]: s[kind].filter((x) => x.key !== key) }));
}

export async function importContent(payload) {
  const res = await api({ path: `${BASE}/import`, method: 'POST', data: payload });
  await refreshAll();
  return res;
}

/** @param {Object} only optional { post_types: [keys], taxonomies: [...], groups: [...] } */
export async function exportContent(format = 'json', only = null) {
  const q = new URLSearchParams({ format });
  if (only) for (const [k, v] of Object.entries(only)) q.set(k, v.length ? v.join(',') : '-');
  return api({ path: `${BASE}/export?${q}` });
}

const labelCache = new Map();
export async function previewLabels(singular, plural, kind = 'post_type') {
  const key = `${kind}|${singular}|${plural}`;
  if (!labelCache.has(key)) {
    const q = new URLSearchParams({ singular, plural, kind }).toString();
    labelCache.set(key, api({ path: `${BASE}/preview-labels?${q}` }).catch(() => ({})));
  }
  return labelCache.get(key);
}

/* ------------------------------------------------------------------------
 * Icons (Lucide set shared with the builder).
 * ---------------------------------------------------------------------- */

export async function loadIconSet() {
  if (!cfg.iconsUrl) return { icons: {}, aliases: {}, tags: {} };
  const base = cfg.iconsUrl.replace(/icons\.json.*$/, '');
  const json = (u) => fetch(u).then((r) => (r.ok ? r.json() : {})).catch(() => ({}));
  const [icons, aliases] = await Promise.all([json(cfg.iconsUrl), json(`${base}icon-aliases.json`)]);
  return { icons, aliases, tagsUrl: `${base}icon-tags.json` };
}

/* ------------------------------------------------------------------------
 * Keys, names and validation.
 * ---------------------------------------------------------------------- */

export function slugify(text, sep = '_') {
  return String(text || '')
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/['’]/g, '')
    .replace(/[^a-z0-9]+/g, sep)
    .replace(new RegExp(`^\\${sep}+|\\${sep}+$`, 'g'), '');
}

export function uid(prefix) {
  const r = Math.random().toString(16).slice(2, 8).padEnd(6, '0');
  return `${prefix}_${r}`;
}

// Fallbacks used until (or in case) the server sends its own list.
const WP_RESERVED_TYPES = ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'action', 'author', 'order', 'theme', 'brik_library', 'brik_template'];
const WP_RESERVED_TAX = ['category', 'post_tag', 'nav_menu', 'link_category', 'post_format', 'wp_theme', 'wp_template_part_area', 'wp_pattern_category', 'attachment', 'author', 'calendar', 'cat', 'day', 'feed', 'hour', 'm', 'minute', 'monthnum', 'name', 'order', 'orderby', 'p', 'page', 'paged', 'post', 'post_type', 'preview', 's', 'search', 'second', 'static', 'tag', 'taxonomy', 'term', 'terms', 'theme', 'type', 'w', 'withcomments', 'year'];

function normalizeReserved(r) {
  const out = { post_types: [], taxonomies: [], names: [] };
  if (Array.isArray(r)) {
    out.post_types = r;
    out.taxonomies = r;
  } else if (r && typeof r === 'object') {
    out.post_types = asList(r.post_types || r.post_type || r.types);
    out.taxonomies = asList(r.taxonomies || r.taxonomy);
    out.names = asList(r.names || r.fields || r.field_names);
  }
  if (!out.post_types.length) out.post_types = WP_RESERVED_TYPES;
  if (!out.taxonomies.length) out.taxonomies = WP_RESERVED_TAX;
  return out;
}

/**
 * Validates a post type or taxonomy key the way the server does.
 * @returns {string} message, or '' when valid
 */
export function validateKey(kind, key, originalKey) {
  const s = getState();
  const max = kind === 'post_types' ? 20 : 32;
  if (!key) return __('A key is required.', 'brik-builder');
  if (!/^[a-z0-9_-]+$/.test(key)) return __('Use lowercase letters, numbers, dashes and underscores only.', 'brik-builder');
  if (key.length > max) return sprintf(__('Keep it to %d characters or fewer.', 'brik-builder'), max);
  if (key === originalKey) return '';
  const reserved = kind === 'post_types' ? s.reserved.post_types : s.reserved.taxonomies;
  if (reserved.includes(key)) return sprintf(__('“%s” is reserved by WordPress.', 'brik-builder'), key);
  if (s[kind].some((x) => x.key === key)) return __('Another item already uses this key.', 'brik-builder');
  const wp = kind === 'post_types' ? s.wp.types : s.wp.taxonomies;
  if (wp && wp[key]) return sprintf(__('“%s” is already registered by WordPress or another plugin.', 'brik-builder'), key);
  return '';
}

export function uniqueKey(kind, base) {
  const s = getState();
  const max = kind === 'post_types' ? 20 : 32;
  const taken = (k) => s[kind].some((x) => x.key === k);
  let key = base.slice(0, max);
  for (let i = 2; taken(key); i++) key = `${base.slice(0, max - String(i).length - 1)}_${i}`;
  return key;
}

/* ------------------------------------------------------------------------
 * Field types.
 * ---------------------------------------------------------------------- */


export const CATEGORIES = [
  { id: 'basic', label: __('Basic', 'brik-builder') },
  { id: 'content', label: __('Content', 'brik-builder') },
  { id: 'choice', label: __('Choice', 'brik-builder') },
  { id: 'relational', label: __('Relational', 'brik-builder') },
  { id: 'advanced', label: __('Advanced', 'brik-builder') },
  { id: 'layout', label: __('Layout', 'brik-builder') },
];

const textOpts = [
  { key: 'prepend', type: 'text', label: __('Prepend', 'brik-builder') },
  { key: 'append', type: 'text', label: __('Append', 'brik-builder') },
  { key: 'maxlength', type: 'number', label: __('Character limit', 'brik-builder') },
];
const numOpts = [
  { key: 'min', type: 'number', label: __('Minimum', 'brik-builder') },
  { key: 'max', type: 'number', label: __('Maximum', 'brik-builder') },
  { key: 'step', type: 'number', label: __('Step', 'brik-builder') },
  { key: 'prepend', type: 'text', label: __('Prepend', 'brik-builder') },
  { key: 'append', type: 'text', label: __('Append', 'brik-builder') },
];
const choiceOpts = [{ key: 'choices', type: 'choices', label: __('Choices', 'brik-builder') }];
const dateFmt = (d) => [
  { key: 'display_format', type: 'text', label: __('Display format', 'brik-builder'), placeholder: d },
  { key: 'return_format', type: 'text', label: __('Return format', 'brik-builder'), placeholder: d },
];

// Local catalogue: icon, category, description and option schema for every documented type.
export const FIELD_TYPES = [
  { type: 'text', label: __('Text', 'brik-builder'), icon: 'type', category: 'basic', description: __('A single line of text.', 'brik-builder'), options: textOpts },
  { type: 'textarea', label: __('Text area', 'brik-builder'), icon: 'text-align-start', category: 'basic', description: __('Several lines of plain text.', 'brik-builder'), options: [{ key: 'rows', type: 'number', label: __('Rows', 'brik-builder') }, ...textOpts] },
  { type: 'number', label: __('Number', 'brik-builder'), icon: 'hash', category: 'basic', description: __('A number with optional limits.', 'brik-builder'), options: numOpts },
  { type: 'range', label: __('Range', 'brik-builder'), icon: 'sliders-horizontal', category: 'basic', description: __('A slider between a minimum and maximum.', 'brik-builder'), options: numOpts },
  { type: 'email', label: __('Email', 'brik-builder'), icon: 'mail', category: 'basic', description: __('A validated email address.', 'brik-builder'), options: textOpts },
  { type: 'url', label: __('URL', 'brik-builder'), icon: 'link-2', category: 'basic', description: __('A web address.', 'brik-builder'), options: textOpts },
  { type: 'password', label: __('Password', 'brik-builder'), icon: 'key-round', category: 'basic', description: __('Masked text input.', 'brik-builder'), options: textOpts },
  { type: 'wysiwyg', label: __('Rich text', 'brik-builder'), icon: 'pilcrow', category: 'content', description: __('The WordPress visual editor.', 'brik-builder'), options: [{ key: 'toolbar', type: 'select', label: __('Toolbar', 'brik-builder'), choices: [{ value: 'basic', label: __('Basic', 'brik-builder') }, { value: 'full', label: __('Full', 'brik-builder') }] }, { key: 'media', type: 'toggle', label: __('Media upload button', 'brik-builder') }] },
  { type: 'image', label: __('Image', 'brik-builder'), icon: 'image', category: 'content', description: __('Pick one image from the media library.', 'brik-builder'), options: [{ key: 'preview_size', type: 'select', label: __('Preview size', 'brik-builder'), choices: [{ value: 'thumbnail', label: __('Thumbnail', 'brik-builder') }, { value: 'medium', label: __('Medium', 'brik-builder') }, { value: 'large', label: __('Large', 'brik-builder') }] }, { key: 'mime_types', type: 'text', label: __('Allowed file types', 'brik-builder'), placeholder: 'jpg, png, webp' }] },
  { type: 'file', label: __('File', 'brik-builder'), icon: 'paperclip', category: 'content', description: __('Upload or pick any file.', 'brik-builder'), options: [{ key: 'mime_types', type: 'text', label: __('Allowed file types', 'brik-builder'), placeholder: 'pdf, zip' }] },
  { type: 'gallery', label: __('Gallery', 'brik-builder'), icon: 'images', category: 'content', description: __('An ordered set of images.', 'brik-builder'), options: [{ key: 'min', type: 'number', label: __('Minimum images', 'brik-builder') }, { key: 'max', type: 'number', label: __('Maximum images', 'brik-builder') }] },
  { type: 'oembed', label: __('Embed', 'brik-builder'), icon: 'clapperboard', category: 'content', description: __('YouTube, Vimeo, Spotify and other embeds by URL.', 'brik-builder'), options: [] },
  { type: 'select', label: __('Select', 'brik-builder'), icon: 'list', category: 'choice', description: __('A dropdown of choices.', 'brik-builder'), options: [...choiceOpts, { key: 'multiple', type: 'toggle', label: __('Allow multiple', 'brik-builder') }, { key: 'allow_null', type: 'toggle', label: __('Allow empty', 'brik-builder') }] },
  { type: 'checkbox', label: __('Checkboxes', 'brik-builder'), icon: 'square-check', category: 'choice', description: __('Tick any number of choices.', 'brik-builder'), options: choiceOpts },
  { type: 'radio', label: __('Radio buttons', 'brik-builder'), icon: 'circle-dot', category: 'choice', description: __('Pick exactly one choice.', 'brik-builder'), options: [...choiceOpts, { key: 'allow_null', type: 'toggle', label: __('Allow empty', 'brik-builder') }] },
  { type: 'button_group', label: __('Button group', 'brik-builder'), icon: 'rectangle-ellipsis', category: 'choice', description: __('One choice shown as segmented buttons.', 'brik-builder'), options: [...choiceOpts, { key: 'allow_null', type: 'toggle', label: __('Allow empty', 'brik-builder') }] },
  { type: 'toggle', label: __('Toggle', 'brik-builder'), icon: 'toggle-right', category: 'choice', description: __('An on/off switch.', 'brik-builder'), options: [{ key: 'on_text', type: 'text', label: __('On text', 'brik-builder'), placeholder: __('Yes', 'brik-builder') }, { key: 'off_text', type: 'text', label: __('Off text', 'brik-builder'), placeholder: __('No', 'brik-builder') }] },
  { type: 'link', label: __('Link', 'brik-builder'), icon: 'link', category: 'relational', description: __('URL, title and target.', 'brik-builder'), options: [] },
  { type: 'post_object', label: __('Post object', 'brik-builder'), icon: 'file-text', category: 'relational', description: __('Pick one or more posts from a dropdown.', 'brik-builder'), options: [{ key: 'post_types', type: 'post_types', label: __('Post types', 'brik-builder') }, { key: 'taxonomy', type: 'taxonomy', label: __('Filter by taxonomy', 'brik-builder') }, { key: 'multiple', type: 'toggle', label: __('Allow multiple', 'brik-builder') }] },
  { type: 'relationship', label: __('Relationship', 'brik-builder'), icon: 'git-merge', category: 'relational', description: __('Search and order related posts.', 'brik-builder'), options: [{ key: 'post_types', type: 'post_types', label: __('Post types', 'brik-builder') }, { key: 'taxonomy', type: 'taxonomy', label: __('Filter by taxonomy', 'brik-builder') }, { key: 'min', type: 'number', label: __('Minimum posts', 'brik-builder') }, { key: 'max', type: 'number', label: __('Maximum posts', 'brik-builder') }] },
  { type: 'taxonomy', label: __('Taxonomy', 'brik-builder'), icon: 'tags', category: 'relational', description: __('Pick terms from a taxonomy.', 'brik-builder'), options: [{ key: 'taxonomy', type: 'taxonomy', label: __('Taxonomy', 'brik-builder') }, { key: 'field_type', type: 'select', label: __('Appearance', 'brik-builder'), choices: [{ value: 'select', label: __('Select', 'brik-builder') }, { value: 'checkbox', label: __('Checkboxes', 'brik-builder') }] }, { key: 'save_terms', type: 'toggle', label: __('Assign terms to the post', 'brik-builder') }] },
  { type: 'user', label: __('User', 'brik-builder'), icon: 'user', category: 'relational', description: __('Pick one or more users.', 'brik-builder'), options: [{ key: 'role', type: 'role', label: __('Limit to role', 'brik-builder') }, { key: 'multiple', type: 'toggle', label: __('Allow multiple', 'brik-builder') }] },
  { type: 'date', label: __('Date', 'brik-builder'), icon: 'calendar', category: 'advanced', description: __('A calendar date picker.', 'brik-builder'), options: dateFmt('F j, Y') },
  { type: 'datetime', label: __('Date & time', 'brik-builder'), icon: 'calendar-clock', category: 'advanced', description: __('A date with a time.', 'brik-builder'), options: dateFmt('F j, Y g:i a') },
  { type: 'time', label: __('Time', 'brik-builder'), icon: 'clock', category: 'advanced', description: __('A time of day.', 'brik-builder'), options: dateFmt('g:i a') },
  { type: 'color', label: __('Color', 'brik-builder'), icon: 'palette', category: 'advanced', description: __('A color picker.', 'brik-builder'), options: [{ key: 'alpha', type: 'toggle', label: __('Allow transparency', 'brik-builder') }] },
  { type: 'map', label: __('Map', 'brik-builder'), icon: 'map-pin', category: 'advanced', description: __('An address with coordinates.', 'brik-builder'), options: [{ key: 'center_lat', type: 'number', label: __('Center latitude', 'brik-builder') }, { key: 'center_lng', type: 'number', label: __('Center longitude', 'brik-builder') }, { key: 'zoom', type: 'number', label: __('Zoom', 'brik-builder'), placeholder: '14' }] },
  { type: 'repeater', label: __('Repeater', 'brik-builder'), icon: 'rows-3', category: 'layout', description: __('Repeat a set of sub fields as rows.', 'brik-builder'), options: [{ key: 'layout', type: 'select', label: __('Layout', 'brik-builder'), choices: [{ value: 'table', label: __('Table', 'brik-builder') }, { value: 'block', label: __('Block', 'brik-builder') }, { value: 'row', label: __('Row', 'brik-builder') }] }, { key: 'button_label', type: 'text', label: __('Button label', 'brik-builder'), placeholder: __('Add row', 'brik-builder') }, { key: 'min', type: 'number', label: __('Minimum rows', 'brik-builder') }, { key: 'max', type: 'number', label: __('Maximum rows', 'brik-builder') }] },
  { type: 'group', label: __('Group', 'brik-builder'), icon: 'box', category: 'layout', description: __('Keep related sub fields together.', 'brik-builder'), options: [] },
  { type: 'tab', label: __('Tab', 'brik-builder'), icon: 'panels-top-left', category: 'layout', description: __('Split the meta box into tabs.', 'brik-builder'), options: [{ key: 'placement', type: 'select', label: __('Placement', 'brik-builder'), choices: [{ value: 'top', label: __('Top', 'brik-builder') }, { value: 'left', label: __('Left', 'brik-builder') }] }] },
  { type: 'message', label: __('Message', 'brik-builder'), icon: 'message-square-text', category: 'layout', description: __('Show instructions to editors. Stores nothing.', 'brik-builder'), options: [{ key: 'message', type: 'textarea', label: __('Message', 'brik-builder') }] },
];

export const NO_VALUE = ['tab', 'message'];
export const CHOICE_TYPES = ['select', 'checkbox', 'radio', 'button_group'];
export const PARENT_TYPES = ['repeater', 'group'];

// Merges what the server reports with the local catalogue so labels, icons and option schemas
// are always present, while types the server adds still show up.
// The server's registry is authoritative for labels and option schemas; the local catalogue
// adds descriptions and stands in for anything missing (or when the server sends nothing).
function normalizeFieldTypes(raw) {
  let list = [];
  if (Array.isArray(raw)) list = raw;
  else if (raw && typeof raw === 'object') list = Object.entries(raw).map(([type, v]) => (typeof v === 'string' ? { type, label: v } : { type, ...v }));
  const local = Object.fromEntries(FIELD_TYPES.map((f) => [f.type, f]));
  const merged = list
    .map((r) => {
      const type = r.name || r.type || r.key;
      if (!type) return null;
      const base = local[type] || { type, label: type, icon: 'square', category: 'advanced', description: '', options: [] };
      const remote = normalizeOptions(r.options);
      return {
        ...base,
        type,
        label: r.label || base.label,
        description: r.description || base.description,
        category: r.category && CATEGORIES.some((c) => c.id === r.category) ? r.category : base.category,
        icon: r.icon && iconSvg(r.icon) ? r.icon : base.icon,
        has_value: r.has_value !== undefined ? !!r.has_value : !NO_VALUE.includes(type),
        options: r.options !== undefined ? remote : base.options,
      };
    })
    .filter(Boolean);
  for (const f of FIELD_TYPES) if (!merged.some((m) => m.type === f.type)) merged.push(f);
  return merged;
}

const OPTION_TYPES = { boolean: 'toggle', bool: 'toggle', integer: 'number', int: 'number', float: 'number', fields: 'fields' };

function normalizeOptions(o) {
  if (!o) return [];
  const list = Array.isArray(o) ? o.map((x) => (typeof x === 'string' ? { key: x, type: 'text' } : { ...x, key: x.key || x.name })) : Object.entries(o).map(([key, v]) => (typeof v === 'string' ? { key, type: v } : { key, ...v }));
  return list
    .filter((x) => x.key && x.key !== 'sub_fields' && x.type !== 'fields')
    .map((x) => {
      let choices = x.choices || (x.enum ? x.enum.map((v) => ({ value: v, label: v })) : undefined);
      if (choices && !Array.isArray(choices)) choices = Object.entries(choices).map(([value, label]) => ({ value, label }));
      const type = OPTION_TYPES[x.type] || x.type || (choices ? 'select' : 'text');
      return { ...x, type: choices && type === 'text' ? 'select' : type, label: x.label || x.key.replace(/_/g, ' '), choices };
    });
}

export function fieldType(type) {
  return getState().field_types.find((f) => f.type === type) || FIELD_TYPES.find((f) => f.type === type) || { type, label: type, icon: 'square', options: [] };
}

/* ------------------------------------------------------------------------
 * Field tree helpers (sub fields live in options.sub_fields).
 * ---------------------------------------------------------------------- */

export function subFields(f) {
  return (f.options && f.options.sub_fields) || [];
}

export function withSub(f, sub) {
  return { ...f, options: { ...(f.options || {}), sub_fields: sub } };
}

export function findField(fields, key) {
  for (const f of fields) {
    if (f.key === key) return f;
    const hit = findField(subFields(f), key);
    if (hit) return hit;
  }
  return null;
}

/** Parent key of a field (null for top level, undefined when missing). */
export function parentOf(fields, key, parent = null) {
  for (const f of fields) {
    if (f.key === key) return parent;
    const hit = parentOf(subFields(f), key, f.key);
    if (hit !== undefined) return hit;
  }
  return undefined;
}

export function siblingsOf(fields, key) {
  const p = parentOf(fields, key);
  if (p === undefined) return [];
  return p === null ? fields : subFields(findField(fields, p));
}

export function mapFields(fields, fn) {
  return fields.map((f) => {
    const next = fn(f);
    if (!next) return next;
    return PARENT_TYPES.includes(next.type) ? withSub(next, mapFields(subFields(next), fn)) : next;
  });
}

export function updateField(fields, key, patch) {
  return mapFields(fields, (f) => (f.key === key ? { ...f, ...(typeof patch === 'function' ? patch(f) : patch) } : f));
}

export function removeField(fields, key) {
  let removed = null;
  const walk = (list) =>
    list
      .filter((f) => {
        if (f.key === key) {
          removed = f;
          return false;
        }
        return true;
      })
      .map((f) => (PARENT_TYPES.includes(f.type) || subFields(f).length ? withSub(f, walk(subFields(f))) : f));
  return [walk(fields), removed];
}

export function insertField(fields, parentKey, index, field) {
  if (parentKey === null) {
    const list = fields.slice();
    list.splice(Math.max(0, Math.min(index, list.length)), 0, field);
    return list;
  }
  return fields.map((f) => {
    if (f.key === parentKey) {
      const sub = subFields(f).slice();
      sub.splice(Math.max(0, Math.min(index, sub.length)), 0, field);
      return withSub(f, sub);
    }
    return PARENT_TYPES.includes(f.type) ? withSub(f, insertField(subFields(f), parentKey, index, field)) : f;
  });
}

export function contains(field, key) {
  return field.key === key || subFields(field).some((s) => contains(s, key));
}

export function countFields(fields) {
  return fields.reduce((n, f) => n + 1 + countFields(subFields(f)), 0);
}

/** Fresh keys for a field and everything below it, remapping condition references. */
export function cloneField(field) {
  const map = {};
  const assign = (f) => {
    map[f.key] = uid('field');
    subFields(f).forEach(assign);
  };
  assign(field);
  const rewrite = (f) => {
    const next = { ...f, key: map[f.key] };
    if (f.conditions) next.conditions = f.conditions.map((g) => g.map((r) => ({ ...r, field: map[r.field] || r.field })));
    if (PARENT_TYPES.includes(f.type)) return withSub(next, subFields(f).map(rewrite));
    return next;
  };
  return rewrite(field);
}

export function newField(type = 'text', label = '') {
  const f = { key: uid('field'), name: slugify(label), label, type, instructions: '', required: false, default: '', placeholder: '', width: 100, conditions: [], options: {} };
  if (PARENT_TYPES.includes(type)) f.options.sub_fields = [];
  if (type === 'repeater') f.options.layout = 'table';
  if (CHOICE_TYPES.includes(type)) f.options.choices = [];
  return f;
}

export function validateFields(fields) {
  const errors = {};
  const walk = (list) => {
    const seen = {};
    for (const f of list) {
      if (NO_VALUE.includes(f.type)) {
        if (!f.label) errors[f.key] = { label: __('Give it a label.', 'brik-builder') };
      } else if (!f.label) errors[f.key] = { label: __('A label is required.', 'brik-builder') };
      else if (!f.name) errors[f.key] = { name: __('A name is required.', 'brik-builder') };
      else if (!/^[a-z0-9_]+$/.test(f.name)) errors[f.key] = { name: __('Use lowercase letters, numbers and underscores.', 'brik-builder') };
      else if (!/^[a-z]/.test(f.name)) errors[f.key] = { name: __('Names must start with a letter.', 'brik-builder') };
      else if (f.name.length > 64) errors[f.key] = { name: __('Keep names to 64 characters or fewer.', 'brik-builder') };
      else if (seen[f.name]) errors[f.key] = { name: __('Another field at this level uses this name.', 'brik-builder') };
      if (f.name) seen[f.name] = true;
      walk(subFields(f));
    }
  };
  walk(fields);
  return errors;
}

export function clone(v) {
  return JSON.parse(JSON.stringify(v));
}

export function download(filename, text, type = 'application/json') {
  const blob = new Blob([text], { type });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => {
    URL.revokeObjectURL(a.href);
    a.remove();
  }, 100);
}

export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    const ok = document.execCommand('copy');
    ta.remove();
    return ok;
  }
}
