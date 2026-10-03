// Post type editor (#/post-types/{key} and #/post-types/new).
import { useState, useMemo, __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, Input, Textarea, Select } from '../builder/ui.jsx';
import { Card, Row, SwitchRow, SwitchBtn, Checkbox, Badge, Popover, MenuIcon, Segment } from './primitives.jsx';
import { useStore, getState, navigate, saveItem, toast, errorMessage, slugify, validateKey, uniqueKey, clone, cfg, loadWp, loadCounts } from './lib.js';
import { useDraft, useSaveShortcut, EditorHeader, SectionNav, SaveBar } from './EditorShell.jsx';
import { IconPicker } from './IconPicker.jsx';
import { LabelsEditor, useGeneratedLabels, cleanLabels } from './Labels.jsx';
import { removePostType } from './Overview.jsx';

const DEFAULTS = {
  key: '',
  active: true,
  version: 1,
  singular: '',
  plural: '',
  labels: {},
  description: '',
  icon: 'dashicons-admin-post',
  public: true,
  has_archive: true,
  archive_slug: '',
  rewrite_slug: '',
  hierarchical: false,
  show_in_rest: true,
  show_in_menu: true,
  menu_position: 25,
  supports: ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
  taxonomies: [],
  exclude_from_search: false,
  capability_type: 'post',
  brik: true,
};

export const SUPPORTS = [
  { value: 'title', label: __('Title', 'brik-builder'), icon: 'heading' },
  { value: 'editor', label: __('Content editor', 'brik-builder'), icon: 'pilcrow' },
  { value: 'thumbnail', label: __('Featured image', 'brik-builder'), icon: 'image' },
  { value: 'excerpt', label: __('Excerpt', 'brik-builder'), icon: 'text-quote' },
  { value: 'revisions', label: __('Revisions', 'brik-builder'), icon: 'rotate-ccw-clock' },
  { value: 'author', label: __('Author', 'brik-builder'), icon: 'user-round' },
  { value: 'page-attributes', label: __('Page attributes (order, parent)', 'brik-builder'), icon: 'list-ordered' },
  { value: 'comments', label: __('Comments', 'brik-builder'), icon: 'message-square' },
  { value: 'custom-fields', label: __('Custom fields panel', 'brik-builder'), icon: 'list-plus' },
];

const MENU_POSITIONS = [
  { value: 5, label: __('Below Posts', 'brik-builder') },
  { value: 10, label: __('Below Media', 'brik-builder') },
  { value: 20, label: __('Below Pages', 'brik-builder') },
  { value: 25, label: __('Below Comments', 'brik-builder') },
  { value: 60, label: __('Below first separator', 'brik-builder') },
  { value: 65, label: __('Below Plugins', 'brik-builder') },
  { value: 70, label: __('Below Users', 'brik-builder') },
  { value: 80, label: __('Below Settings', 'brik-builder') },
];

export function homeUrl() {
  return (cfg.homeUrl || cfg.siteUrl || window.location.origin + window.location.pathname.replace(/\/wp-admin\/.*$/, '')).replace(/\/$/, '');
}

function initialDraft(id, query) {
  const s = getState();
  if (id && id !== 'new') {
    const found = s.post_types.find((p) => p.key === id);
    return found ? { ...DEFAULTS, ...clone(found), labels: { ...(found.labels || {}) } } : null;
  }
  if (query.from) {
    const src = s.post_types.find((p) => p.key === query.from);
    if (src) {
      const d = { ...DEFAULTS, ...clone(src) };
      d.key = uniqueKey('post_types', `${src.key}_copy`);
      d.singular = sprintf(__('%s (copy)', 'brik-builder'), src.singular);
      d.plural = sprintf(__('%s (copy)', 'brik-builder'), src.plural);
      d.rewrite_slug = slugify(d.key, '-');
      return d;
    }
  }
  return { ...DEFAULTS };
}

export function PostTypeEditor({ id, query }) {
  const start = useMemo(() => initialDraft(id, query), [id]);
  if (!start) return <Missing />;
  return <Editor start={start} isNew={!id || id === 'new'} />;
}

function Missing() {
  return (
    <div className="flex flex-col items-center gap-3 py-20 text-center">
      <Icon name="file-question-mark" size={28} className="text-muted-foreground" />
      <p className="text-sm font-medium">{__('This post type doesn’t exist.', 'brik-builder')}</p>
      <Button variant="outline" size="sm" onClick={() => navigate('#/post-types')}>
        {__('Back to post types', 'brik-builder')}
      </Button>
    </div>
  );
}

function Editor({ start, isNew }) {
  const [d, update, dirty, markSaved] = useDraft(start);
  const [keyTouched, setKeyTouched] = useState(!isNew || !!start.key);
  const [slugTouched, setSlugTouched] = useState(!isNew || !!start.rewrite_slug);
  const [pluralTouched, setPluralTouched] = useState(!isNew || !!start.plural);
  const [newTax, setNewTax] = useState([]); // taxonomies created inline, saved with the post type
  const [saving, setSaving] = useState(false);
  const [showErrors, setShowErrors] = useState(false);
  const [serverError, setServerError] = useState('');
  const s = useStore();
  const generated = useGeneratedLabels(d.singular, d.plural, 'post_type');

  const keyError = validateKey('post_types', d.key, isNew ? null : start.key);
  const errors = {
    singular: !d.singular.trim() ? __('Give the post type a name.', 'brik-builder') : '',
    plural: !d.plural.trim() ? __('Add the plural name used in menus.', 'brik-builder') : '',
    key: keyError,
    rewrite_slug: d.rewrite_slug && !/^[a-z0-9-_/]+$/.test(d.rewrite_slug) ? __('Use lowercase letters, numbers and dashes.', 'brik-builder') : '',
  };
  const hasErrors = Object.values(errors).some(Boolean);

  const setSingular = (singular) => {
    const patch = { singular };
    if (!pluralTouched) patch.plural = pluralize(singular);
    if (!keyTouched) patch.key = slugify(singular, '_').slice(0, 20);
    if (!slugTouched) patch.rewrite_slug = slugify(patch.plural ?? d.plural, '-');
    update(patch);
  };

  const setPlural = (plural) => {
    setPluralTouched(true);
    update(slugTouched ? { plural } : { plural, rewrite_slug: slugify(plural, '-') });
  };

  const save = async () => {
    if (hasErrors) {
      setShowErrors(true);
      toast(__('Fix the highlighted fields first.', 'brik-builder'), 'error');
      return;
    }
    setSaving(true);
    setServerError('');
    try {
      for (const tx of newTax) {
        await saveItem('taxonomies', { ...tx, post_types: Array.from(new Set([...(tx.post_types || []), d.key])) });
      }
      const def = { ...d, labels: cleanLabels(d.labels), menu_position: d.menu_position === '' ? null : Number(d.menu_position) };
      const saved = await saveItem('post_types', def);
      setNewTax([]);
      markSaved({ ...DEFAULTS, ...saved });
      toast(isNew ? sprintf(__('%s created', 'brik-builder'), saved.plural || saved.key) : __('Post type saved', 'brik-builder'), 'success');
      loadWp().then(loadCounts);
      if (isNew) {
        setTimeout(() => navigate(`#/post-types/${saved.key}`), 0);
      }
    } catch (e) {
      const msg = errorMessage(e, __('Could not save the post type.', 'brik-builder'));
      setServerError(msg);
      toast(msg, 'error');
    }
    setSaving(false);
  };
  useSaveShortcut(save);

  const sections = [
    { id: 'identity', label: __('Name & menu', 'brik-builder'), icon: 'id-card', error: showErrors && (errors.singular || errors.plural || errors.key) },
    { id: 'general', label: __('General', 'brik-builder'), icon: 'settings-2', error: showErrors && errors.rewrite_slug },
    { id: 'editor', label: __('Editor', 'brik-builder'), icon: 'square-pen' },
    { id: 'taxonomies', label: __('Taxonomies', 'brik-builder'), icon: 'tags' },
    { id: 'advanced', label: __('Advanced', 'brik-builder'), icon: 'sliders-horizontal' },
  ];

  const err = (k) => (showErrors || (k === 'key' && d.key) ? errors[k] : '');
  const home = homeUrl();
  const slug = d.rewrite_slug || slugify(d.plural, '-') || d.key || 'items';
  const archive = d.archive_slug || slug;

  return (
    <div>
      <EditorHeader
        back="#/post-types"
        backLabel={__('Post types', 'brik-builder')}
        icon={<MenuIcon icon={d.icon} size={20} />}
        title={d.plural || d.singular || (isNew ? __('New post type', 'brik-builder') : d.key)}
        subtitle={isNew ? __('Name it, pick an icon, and it appears in the admin menu as soon as you save.', 'brik-builder') : sprintf(__('Key: %s', 'brik-builder'), d.key)}
        badges={!isNew && !d.active ? <Badge tone="outline">{__('Inactive', 'brik-builder')}</Badge> : null}
        actions={
          !isNew && (
            <>
              {s.wp.types[d.key] && (
                <Button variant="outline" size="sm" icon="list" onClick={() => (window.location.href = `edit.php?post_type=${d.key}`)}>
                  {__('View entries', 'brik-builder')}
                </Button>
              )}
              <Button variant="ghost" size="sm" icon="trash-2" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={async () => (await removePostType(start)) && navigate('#/post-types')}>
                {__('Delete', 'brik-builder')}
              </Button>
            </>
          )
        }
      />
      <div className="flex gap-8">
        <SectionNav sections={sections} />
        <div className="min-w-0 flex-1 space-y-6">
          <Card id="sec-identity" title={__('Name & menu', 'brik-builder')} description={__('How the post type is called in the admin and in code.', 'brik-builder')}>
            <div className="grid gap-6 md:grid-cols-[1fr_220px]">
              <div className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <Row label={__('Singular name', 'brik-builder')} error={err('singular')} htmlFor="pt-singular">
                    <Input id="pt-singular" autoFocus={isNew} value={d.singular} placeholder={__('e.g. Project', 'brik-builder')} onChange={(e) => setSingular(e.target.value)} />
                  </Row>
                  <Row label={__('Plural name', 'brik-builder')} error={err('plural')} htmlFor="pt-plural">
                    <Input id="pt-plural" value={d.plural} placeholder={__('e.g. Projects', 'brik-builder')} onChange={(e) => setPlural(e.target.value)} />
                  </Row>
                </div>
                <Row
                  label={__('Key', 'brik-builder')}
                  htmlFor="pt-key"
                  error={err('key')}
                  help={isNew ? __('Used in code, URLs and the database. Can’t be changed later without moving existing entries.', 'brik-builder') : __('The key is fixed once created, because existing entries are stored under it.', 'brik-builder')}
                  aside={
                    !isNew ? (
                      <Icon name="lock" size={12} className="text-muted-foreground" />
                    ) : !err('key') && d.key ? (
                      <span className="flex items-center gap-1 text-[11px] text-emerald-600">
                        <Icon name="check" size={12} />
                        {__('Available', 'brik-builder')}
                      </span>
                    ) : (
                      <span className="text-[11px] text-muted-foreground">{d.key.length}/20</span>
                    )
                  }
                >
                  <div className="relative">
                    <Input
                      id="pt-key"
                      className={cn('pr-16 font-mono disabled:bg-muted/50 disabled:text-muted-foreground', err('key') && 'border-destructive focus-visible:ring-destructive/30')}
                      value={d.key}
                      placeholder="project"
                      maxLength={24}
                      disabled={!isNew}
                      onChange={(e) => {
                        setKeyTouched(true);
                        update({ key: e.target.value.toLowerCase().replace(/\s+/g, '_') });
                      }}
                    />
                    {keyTouched && isNew && (
                      <button
                        type="button"
                        className="absolute top-1/2 right-1.5 -translate-y-1/2 rounded px-1.5 py-0.5 text-[11px] text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer"
                        onClick={() => {
                          setKeyTouched(false);
                          update({ key: slugify(d.singular, '_').slice(0, 20) });
                        }}
                      >
                        {__('Auto', 'brik-builder')}
                      </button>
                    )}
                  </div>
                </Row>
                <Row label={__('Menu icon', 'brik-builder')}>
                  <Popover
                    width={340}
                    trigger={
                      <button type="button" className="flex h-9 w-full items-center gap-2.5 rounded-md border border-input bg-background px-2.5 text-left text-sm shadow-xs hover:bg-accent/50 cursor-pointer" data-icon-trigger>
                        <MenuIcon icon={d.icon} size={18} />
                        <span className="flex-1 truncate font-mono text-xs text-muted-foreground">{d.icon}</span>
                        <Icon name="chevron-down" size={14} className="text-muted-foreground" />
                      </button>
                    }
                  >
                    {(close) => (
                      <IconPicker
                        value={d.icon}
                        onPick={(icon) => {
                          update({ icon });
                          close();
                        }}
                      />
                    )}
                  </Popover>
                </Row>
              </div>
              <MenuPreview d={d} generated={generated} pending={newTax} />
            </div>
          </Card>

          <Card id="sec-general" title={__('General', 'brik-builder')} description={__('Visibility, URLs and structure.', 'brik-builder')}>
            <Row label={__('Description', 'brik-builder')} help={__('Optional. Shown by some themes on the archive page.', 'brik-builder')}>
              <Textarea rows={2} className="min-h-16" value={d.description} onChange={(e) => update({ description: e.target.value })} />
            </Row>
            <SwitchRow icon="globe" label={__('Public', 'brik-builder')} help={__('Entries get their own pages on the site. Turn off for internal content such as testimonials shown in listings only.', 'brik-builder')} checked={d.public} onChange={(v) => update({ public: v })} />
            {d.public && (
              <Row label={__('URL slug', 'brik-builder')} error={err('rewrite_slug')}>
                <Input className="font-mono" value={d.rewrite_slug} placeholder={slugify(d.plural, '-') || 'projects'} onChange={(e) => (setSlugTouched(true), update({ rewrite_slug: e.target.value }))} />
                <UrlExample parts={[home, '/', <b key="s">{slug}</b>, '/', d.hierarchical ? 'parent/' : '', sprintf('%s/', slugify(sprintf(__('my first %s', 'brik-builder'), d.singular || __('entry', 'brik-builder')), '-'))]} />
              </Row>
            )}
            <SwitchRow icon="archive" label={__('Archive page', 'brik-builder')} help={__('A page that lists all entries. Design it with a Brik archive template.', 'brik-builder')} checked={d.has_archive} onChange={(v) => update({ has_archive: v })} />
            {d.has_archive && (
              <Row label={__('Archive slug', 'brik-builder')}>
                <Input className="font-mono" value={d.archive_slug} placeholder={slug} onChange={(e) => update({ archive_slug: e.target.value })} />
                <UrlExample parts={[home, '/', <b key="a">{archive}</b>, '/']} />
              </Row>
            )}
            <SwitchRow icon="network" label={__('Hierarchical', 'brik-builder')} help={__('Entries can have parents, like pages.', 'brik-builder')} checked={d.hierarchical} onChange={(v) => update({ hierarchical: v })} />
          </Card>

          <Card id="sec-editor" title={__('Editor', 'brik-builder')} description={__('What editors see when they write an entry.', 'brik-builder')}>
            <div className="flex items-start justify-between gap-4 rounded-lg border border-brand/20 bg-brand/5 p-3.5">
              <div className="flex items-start gap-3">
                <span className="flex size-8 items-center justify-center rounded-lg bg-brand text-white">
                  <Icon name="layout-template" size={16} />
                </span>
                <div>
                  <p className="text-[13px] font-medium">{__('Design with Brik', 'brik-builder')}</p>
                  <p className="text-xs text-muted-foreground">{__('Adds “Edit with Brik” to entries of this type.', 'brik-builder')}</p>
                </div>
              </div>
              <SwitchBtn checked={d.brik} onChange={(v) => update({ brik: v })} label={__('Design with Brik', 'brik-builder')} />
            </div>
            <Row label={__('Features', 'brik-builder')}>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {SUPPORTS.map((o) => {
                  const on = d.supports.includes(o.value);
                  return (
                    <label key={o.value} className={cn('flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 text-[13px] transition-colors', on ? 'border-foreground/20 bg-muted/40' : 'border-border hover:bg-muted/30')}>
                      <Checkbox checked={on} onChange={(v) => update({ supports: v ? [...d.supports, o.value] : d.supports.filter((x) => x !== o.value) })} label={o.label} />
                      <Icon name={o.icon} size={15} className={on ? 'text-foreground' : 'text-muted-foreground'} />
                      <span className="truncate">{o.label}</span>
                    </label>
                  );
                })}
              </div>
            </Row>
          </Card>

          <Card id="sec-taxonomies" title={__('Taxonomies', 'brik-builder')} description={__('Ways to group these entries.', 'brik-builder')}>
            <TaxonomyPicker d={d} update={update} newTax={newTax} setNewTax={setNewTax} />
          </Card>

          <Card id="sec-advanced" title={__('Advanced', 'brik-builder')} description={__('Sensible defaults — change only when you need to.', 'brik-builder')}>
            <SwitchRow icon="braces" label={__('Show in REST API', 'brik-builder')} help={__('Required for the block editor and for Brik listings.', 'brik-builder')} checked={d.show_in_rest} onChange={(v) => update({ show_in_rest: v })} />
            {!d.show_in_rest && d.supports.includes('editor') && (
              <p className="-mt-2 flex items-center gap-1.5 rounded-md bg-amber-500/10 px-2.5 py-1.5 text-xs text-amber-800">
                <Icon name="triangle-alert" size={13} />
                {__('Without the REST API, entries open in the classic editor.', 'brik-builder')}
              </p>
            )}
            <SwitchRow icon="panel-left" label={__('Show in admin menu', 'brik-builder')} checked={d.show_in_menu} onChange={(v) => update({ show_in_menu: v })} />
            <SwitchRow icon="search-x" label={__('Exclude from search', 'brik-builder')} help={__('Hide entries from the site’s search results.', 'brik-builder')} checked={d.exclude_from_search} onChange={(v) => update({ exclude_from_search: v })} />
            <div className="grid gap-4 sm:grid-cols-2">
              <Row label={__('Menu position', 'brik-builder')}>
                <Select value={String(d.menu_position ?? '')} onChange={(v) => update({ menu_position: v === '' ? '' : Number(v) })} options={[...MENU_POSITIONS.map((o) => ({ value: String(o.value), label: `${o.label} (${o.value})` })), ...(MENU_POSITIONS.some((o) => o.value === Number(d.menu_position)) || d.menu_position === '' || d.menu_position == null ? [] : [{ value: String(d.menu_position), label: String(d.menu_position) }])]} placeholder={__('Default (bottom)', 'brik-builder')} />
              </Row>
              <Row label={__('Capability type', 'brik-builder')} help={__('“post” uses the same permissions as posts.', 'brik-builder')}>
                <Input className="font-mono" value={d.capability_type} placeholder="post" onChange={(e) => update({ capability_type: e.target.value })} list="bk-cap-types" />
                <datalist id="bk-cap-types">
                  <option value="post" />
                  <option value="page" />
                </datalist>
              </Row>
            </div>
            <LabelsEditor value={d.labels} onChange={(labels) => update({ labels })} generated={generated} />
          </Card>
        </div>
      </div>
      <SaveBar dirty={dirty || newTax.length > 0} saving={saving} isNew={isNew} onSave={save} onCancel={() => navigate('#/post-types')} error={serverError} />
    </div>
  );
}

function UrlExample({ parts }) {
  return (
    <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
      <Icon name="link" size={12} className="shrink-0" />
      <span className="truncate font-mono [&_b]:font-semibold [&_b]:text-foreground">{parts}</span>
    </p>
  );
}

// Rough English plural, only used to prefill the field.
function pluralize(w) {
  if (!w) return '';
  if (/(s|x|z|ch|sh)$/i.test(w)) return `${w}es`;
  if (/[^aeiou]y$/i.test(w)) return `${w.slice(0, -1)}ies`;
  return `${w}s`;
}

/** Mock of the wp-admin menu entry this post type will add. */
function MenuPreview({ d, generated, pending = [] }) {
  const name = (d.labels && d.labels.menu_name) || d.plural || __('Items', 'brik-builder');
  const all = (d.labels && d.labels.all_items) || generated.all_items || sprintf(__('All %s', 'brik-builder'), d.plural || __('Items', 'brik-builder'));
  const add = (d.labels && d.labels.add_new) || generated.add_new || __('Add New', 'brik-builder');
  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-[13px] font-medium">{__('Menu preview', 'brik-builder')}</span>
      <div className="overflow-hidden rounded-lg bg-[#1d2327] py-1.5 text-[13px] text-[#f0f0f1] shadow-sm" aria-hidden="true" data-menu-preview>
        <div className="flex items-center gap-2 px-3 py-1.5 text-[#f0f0f1]/60">
          <span className="dashicons dashicons-admin-post" style={{ fontSize: 18, width: 18, height: 18 }} />
          {__('Posts', 'brik-builder')}
        </div>
        <div className="flex items-center gap-2 bg-[#2271b1] px-3 py-1.5 text-white">
          <MenuIcon icon={d.icon} size={18} />
          <span className="truncate">{name}</span>
        </div>
        <div className="bg-[#2c3338] px-3 py-1.5 pl-[38px] text-[12px] leading-6">
          <div className="font-semibold text-white">{all}</div>
          <div className="text-[#f0f0f1]/80">{add}</div>
          {(d.taxonomies || []).slice(0, 2).map((t) => (
            <div key={t} className="truncate text-[#f0f0f1]/80">
              {(pending.find((x) => x.key === t) || {}).plural || taxLabel(t)}
            </div>
          ))}
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 text-[#f0f0f1]/60">
          <span className="dashicons dashicons-admin-media" style={{ fontSize: 18, width: 18, height: 18 }} />
          {__('Media', 'brik-builder')}
        </div>
      </div>
      {!d.show_in_menu && <p className="text-[11px] text-muted-foreground">{__('Hidden from the menu (see Advanced).', 'brik-builder')}</p>}
    </div>
  );
}

function taxLabel(key) {
  const s = getState();
  const own = s.taxonomies.find((t) => t.key === key);
  if (own) return own.plural || key;
  const wp = s.wp.taxonomies[key];
  return (wp && wp.name) || key;
}

function TaxonomyPicker({ d, update, newTax, setNewTax }) {
  const s = useStore();
  const [adding, setAdding] = useState(false);
  const [form, setForm] = useState({ singular: '', plural: '', hierarchical: true });
  const builtins = Object.values(s.wp.taxonomies || {}).filter((t) => ['category', 'post_tag'].includes(t.slug));
  const all = [
    ...s.taxonomies.map((t) => ({ key: t.key, label: t.plural || t.key, hierarchical: t.hierarchical, own: true })),
    ...builtins.map((t) => ({ key: t.slug, label: t.name, hierarchical: t.hierarchical, own: false })),
    ...newTax.map((t) => ({ key: t.key, label: t.plural, hierarchical: t.hierarchical, own: true, pending: true })),
  ].filter((t, i, arr) => arr.findIndex((x) => x.key === t.key) === i);

  const key = slugify(form.singular, '_').slice(0, 32);
  const keyErr = form.singular ? validateKey('taxonomies', key) || (newTax.some((t) => t.key === key) ? __('Already added.', 'brik-builder') : '') : '';

  const addNew = () => {
    if (!form.singular || keyErr) return;
    const plural = form.plural || pluralize(form.singular);
    const tx = { key, active: true, version: 1, singular: form.singular, plural, labels: {}, description: '', hierarchical: form.hierarchical, public: true, show_in_rest: true, show_admin_column: true, rewrite_slug: slugify(form.singular, '-'), post_types: [] };
    setNewTax([...newTax, tx]);
    update({ taxonomies: [...d.taxonomies, key] });
    setForm({ singular: '', plural: '', hierarchical: true });
    setAdding(false);
  };

  return (
    <div className="space-y-3">
      {all.length > 0 ? (
        <div className="grid gap-2 sm:grid-cols-2">
          {all.map((t) => {
            const on = d.taxonomies.includes(t.key);
            return (
              <label key={t.key} className={cn('flex cursor-pointer items-center gap-2.5 rounded-lg border px-3 py-2.5 transition-colors', on ? 'border-foreground/20 bg-muted/40' : 'border-border hover:bg-muted/30')}>
                <Checkbox
                  checked={on}
                  label={t.label}
                  onChange={(v) => {
                    update({ taxonomies: v ? [...d.taxonomies, t.key] : d.taxonomies.filter((x) => x !== t.key) });
                    if (!v && t.pending) setNewTax(newTax.filter((x) => x.key !== t.key));
                  }}
                />
                <Icon name={t.hierarchical ? 'folder-tree' : 'tag'} size={15} className="text-muted-foreground" />
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-[13px] font-medium">{t.label}</span>
                  <span className="block font-mono text-[11px] text-muted-foreground">{t.key}</span>
                </span>
                {t.pending ? <Badge tone="brand">{__('New', 'brik-builder')}</Badge> : !t.own && <Badge>{__('Built-in', 'brik-builder')}</Badge>}
              </label>
            );
          })}
        </div>
      ) : (
        !adding && <p className="text-sm text-muted-foreground">{__('No taxonomies yet. Create one below — it’s saved together with this post type.', 'brik-builder')}</p>
      )}
      {adding ? (
        <div className="space-y-3 rounded-lg border border-dashed border-border bg-muted/20 p-3.5" data-new-taxonomy>
          <div className="grid gap-3 sm:grid-cols-2">
            <Row label={__('Singular name', 'brik-builder')} error={keyErr} help={key ? sprintf(__('Key: %s', 'brik-builder'), key) : ''}>
              <Input autoFocus value={form.singular} placeholder={__('e.g. Project type', 'brik-builder')} onChange={(e) => setForm({ ...form, singular: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addNew())} />
            </Row>
            <Row label={__('Plural name', 'brik-builder')}>
              <Input value={form.plural} placeholder={pluralize(form.singular) || __('e.g. Project types', 'brik-builder')} onChange={(e) => setForm({ ...form, plural: e.target.value })} onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), addNew())} />
            </Row>
          </div>
          <div className="flex flex-wrap items-center justify-between gap-3">
            <Segment
              value={form.hierarchical ? 'cat' : 'tag'}
              onChange={(v) => setForm({ ...form, hierarchical: v === 'cat' })}
              options={[
                { value: 'cat', label: __('Like categories', 'brik-builder'), icon: 'folder-tree' },
                { value: 'tag', label: __('Like tags', 'brik-builder'), icon: 'tag' },
              ]}
            />
            <div className="flex gap-2">
              <Button variant="ghost" size="sm" onClick={() => setAdding(false)}>
                {__('Cancel', 'brik-builder')}
              </Button>
              <Button size="sm" variant="secondary" icon="plus" disabled={!form.singular || !!keyErr} onClick={addNew}>
                {__('Add taxonomy', 'brik-builder')}
              </Button>
            </div>
          </div>
        </div>
      ) : (
        <Button variant="outline" size="sm" icon="plus" onClick={() => setAdding(true)}>
          {__('Create a new taxonomy', 'brik-builder')}
        </Button>
      )}
    </div>
  );
}
