// Taxonomy editor (#/taxonomies/{key} and #/taxonomies/new).
import { useState, useMemo, __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, Input, Textarea } from '../builder/ui.jsx';
import { Card, Row, SwitchRow, Badge, MultiSelect, MenuIcon } from './primitives.jsx';
import { useStore, getState, navigate, saveItem, toast, errorMessage, slugify, validateKey, uniqueKey, clone, loadWp, loadCounts } from './lib.js';
import { useDraft, useSaveShortcut, EditorHeader, SectionNav, SaveBar } from './EditorShell.jsx';
import { LabelsEditor, useGeneratedLabels, cleanLabels } from './Labels.jsx';
import { removeTaxonomy } from './Overview.jsx';
import { homeUrl } from './PostTypeEditor.jsx';

const DEFAULTS = { key: '', active: true, version: 1, singular: '', plural: '', labels: {}, description: '', hierarchical: true, public: true, show_in_rest: true, show_admin_column: true, rewrite_slug: '', post_types: [] };

function initialDraft(id, query) {
  const s = getState();
  if (id && id !== 'new') {
    const found = s.taxonomies.find((t) => t.key === id);
    return found ? { ...DEFAULTS, ...clone(found) } : null;
  }
  if (query.from) {
    const src = s.taxonomies.find((t) => t.key === query.from);
    if (src) {
      const d = { ...DEFAULTS, ...clone(src) };
      d.key = uniqueKey('taxonomies', `${src.key}_copy`);
      d.singular = sprintf(__('%s (copy)', 'brik-builder'), src.singular);
      d.plural = sprintf(__('%s (copy)', 'brik-builder'), src.plural);
      d.rewrite_slug = slugify(d.key, '-');
      return d;
    }
  }
  const d = { ...DEFAULTS };
  if (query.post_type) d.post_types = [query.post_type];
  return d;
}

export function TaxonomyEditor({ id, query }) {
  const start = useMemo(() => initialDraft(id, query), [id]);
  if (!start) {
    return (
      <div className="flex flex-col items-center gap-3 py-20 text-center">
        <Icon name="file-question-mark" size={28} className="text-muted-foreground" />
        <p className="text-sm font-medium">{__('This taxonomy doesn’t exist.', 'brik-builder')}</p>
        <Button variant="outline" size="sm" onClick={() => navigate('#/taxonomies')}>
          {__('Back to taxonomies', 'brik-builder')}
        </Button>
      </div>
    );
  }
  return <Editor start={start} isNew={!id || id === 'new'} />;
}

function plural(w) {
  if (!w) return '';
  if (/(s|x|z|ch|sh)$/i.test(w)) return `${w}es`;
  if (/[^aeiou]y$/i.test(w)) return `${w.slice(0, -1)}ies`;
  return `${w}s`;
}

function Editor({ start, isNew }) {
  const [d, update, dirty, markSaved] = useDraft(start);
  const [touched, setTouched] = useState({ key: !isNew || !!start.key, plural: !isNew || !!start.plural, slug: !isNew || !!start.rewrite_slug });
  const [saving, setSaving] = useState(false);
  const [showErrors, setShowErrors] = useState(false);
  const [serverError, setServerError] = useState('');
  const s = useStore();
  const generated = useGeneratedLabels(d.singular, d.plural, 'taxonomy');

  const errors = {
    singular: !d.singular.trim() ? __('Give the taxonomy a name.', 'brik-builder') : '',
    plural: !d.plural.trim() ? __('Add the plural name.', 'brik-builder') : '',
    key: validateKey('taxonomies', d.key, isNew ? null : start.key),
  };
  const err = (k) => (showErrors || (k === 'key' && d.key) ? errors[k] : '');

  const setSingular = (singular) => {
    const patch = { singular };
    if (!touched.plural) patch.plural = plural(singular);
    if (!touched.key) patch.key = slugify(singular, '_').slice(0, 32);
    if (!touched.slug) patch.rewrite_slug = slugify(singular, '-');
    update(patch);
  };

  const typeOptions = [
    ...s.post_types.map((p) => ({ value: p.key, label: p.plural || p.key })),
    ...Object.values(s.wp.types || {})
      .filter((t) => ['post', 'page'].includes(t.slug))
      .map((t) => ({ value: t.slug, label: t.name })),
  ];

  const save = async () => {
    if (Object.values(errors).some(Boolean)) {
      setShowErrors(true);
      toast(__('Fix the highlighted fields first.', 'brik-builder'), 'error');
      return;
    }
    setSaving(true);
    setServerError('');
    try {
      const saved = await saveItem('taxonomies', { ...d, labels: cleanLabels(d.labels) });
      markSaved({ ...DEFAULTS, ...saved });
      toast(isNew ? sprintf(__('%s created', 'brik-builder'), saved.plural || saved.key) : __('Taxonomy saved', 'brik-builder'), 'success');
      loadWp().then(loadCounts);
      if (isNew) setTimeout(() => navigate(`#/taxonomies/${saved.key}`), 0);
    } catch (e) {
      const msg = errorMessage(e, __('Could not save the taxonomy.', 'brik-builder'));
      setServerError(msg);
      toast(msg, 'error');
    }
    setSaving(false);
  };
  useSaveShortcut(save);

  const sections = [
    { id: 'identity', label: __('Name', 'brik-builder'), icon: 'id-card', error: showErrors && (errors.singular || errors.plural || errors.key) },
    { id: 'structure', label: __('Structure', 'brik-builder'), icon: 'folder-tree' },
    { id: 'attach', label: __('Post types', 'brik-builder'), icon: 'file-stack' },
    { id: 'advanced', label: __('Advanced', 'brik-builder'), icon: 'sliders-horizontal' },
  ];
  const slug = d.rewrite_slug || slugify(d.singular, '-') || d.key || 'term';

  return (
    <div>
      <EditorHeader
        back="#/taxonomies"
        backLabel={__('Taxonomies', 'brik-builder')}
        icon={<Icon name={d.hierarchical ? 'folder-tree' : 'tag'} size={20} />}
        title={d.plural || d.singular || (isNew ? __('New taxonomy', 'brik-builder') : d.key)}
        subtitle={isNew ? __('Group entries into categories or tags.', 'brik-builder') : sprintf(__('Key: %s', 'brik-builder'), d.key)}
        badges={!isNew && !d.active ? <Badge tone="outline">{__('Inactive', 'brik-builder')}</Badge> : null}
        actions={
          !isNew && (
            <>
              {s.wp.taxonomies[d.key] && (
                <Button variant="outline" size="sm" icon="list" onClick={() => (window.location.href = `edit-tags.php?taxonomy=${d.key}${d.post_types[0] ? `&post_type=${d.post_types[0]}` : ''}`)}>
                  {__('Manage terms', 'brik-builder')}
                </Button>
              )}
              <Button variant="ghost" size="sm" icon="trash-2" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={async () => (await removeTaxonomy(start)) && navigate('#/taxonomies')}>
                {__('Delete', 'brik-builder')}
              </Button>
            </>
          )
        }
      />
      <div className="flex gap-8">
        <SectionNav sections={sections} />
        <div className="min-w-0 flex-1 space-y-6">
          <Card id="sec-identity" title={__('Name', 'brik-builder')} description={__('How the taxonomy is called in the admin and in code.', 'brik-builder')}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Row label={__('Singular name', 'brik-builder')} error={err('singular')} htmlFor="tx-singular">
                <Input id="tx-singular" autoFocus={isNew} value={d.singular} placeholder={__('e.g. Project type', 'brik-builder')} onChange={(e) => setSingular(e.target.value)} />
              </Row>
              <Row label={__('Plural name', 'brik-builder')} error={err('plural')} htmlFor="tx-plural">
                <Input id="tx-plural" value={d.plural} placeholder={__('e.g. Project types', 'brik-builder')} onChange={(e) => (setTouched({ ...touched, plural: true }), update({ plural: e.target.value }))} />
              </Row>
            </div>
            <Row
              label={__('Key', 'brik-builder')}
              htmlFor="tx-key"
              error={err('key')}
              help={isNew ? __('Used in code and URLs. Can’t be changed later.', 'brik-builder') : __('The key is fixed once created, because existing terms are stored under it.', 'brik-builder')}
              aside={!isNew ? <Icon name="lock" size={12} className="text-muted-foreground" /> : !err('key') && d.key ? <span className="flex items-center gap-1 text-[11px] text-emerald-600"><Icon name="check" size={12} />{__('Available', 'brik-builder')}</span> : <span className="text-[11px] text-muted-foreground">{d.key.length}/32</span>}
            >
              <Input id="tx-key" disabled={!isNew} className={cn('font-mono disabled:bg-muted/50 disabled:text-muted-foreground', err('key') && 'border-destructive')} value={d.key} placeholder="project_type" onChange={(e) => (setTouched({ ...touched, key: true }), update({ key: e.target.value.toLowerCase().replace(/\s+/g, '_') }))} />
            </Row>
            <Row label={__('Description', 'brik-builder')}>
              <Textarea rows={2} className="min-h-16" value={d.description} onChange={(e) => update({ description: e.target.value })} />
            </Row>
          </Card>

          <Card id="sec-structure" title={__('Structure', 'brik-builder')} description={__('Choose how editors pick terms.', 'brik-builder')}>
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup">
              {[
                { v: true, icon: 'folder-tree', title: __('Like categories', 'brik-builder'), text: __('Nested terms, picked from a checklist.', 'brik-builder') },
                { v: false, icon: 'tag', title: __('Like tags', 'brik-builder'), text: __('Flat terms, typed freely.', 'brik-builder') },
              ].map((o) => (
                <button key={String(o.v)} type="button" role="radio" aria-checked={d.hierarchical === o.v} onClick={() => update({ hierarchical: o.v })} className={cn('flex items-start gap-3 rounded-lg border p-3.5 text-left transition-colors cursor-pointer', d.hierarchical === o.v ? 'border-foreground/30 bg-muted/40 ring-1 ring-foreground/10' : 'border-border hover:bg-muted/30')}>
                  <span className={cn('flex size-8 items-center justify-center rounded-lg border', d.hierarchical === o.v ? 'border-foreground/20 bg-background' : 'border-border')}>
                    <Icon name={o.icon} size={16} />
                  </span>
                  <span>
                    <span className="block text-[13px] font-medium">{o.title}</span>
                    <span className="block text-xs text-muted-foreground">{o.text}</span>
                  </span>
                  <span className={cn('ml-auto mt-0.5 flex size-4 items-center justify-center rounded-full border', d.hierarchical === o.v ? 'border-primary bg-primary' : 'border-input')}>{d.hierarchical === o.v && <span className="size-1.5 rounded-full bg-primary-foreground" />}</span>
                </button>
              ))}
            </div>
          </Card>

          <Card id="sec-attach" title={__('Post types', 'brik-builder')} description={__('Where this taxonomy can be used.', 'brik-builder')}>
            <MultiSelect options={typeOptions} value={d.post_types} onChange={(post_types) => update({ post_types })} placeholder={__('Choose post types', 'brik-builder')} />
            {!d.post_types.length && <p className="text-xs text-muted-foreground">{__('Not attached yet — it won’t show up in any editor until you pick a post type.', 'brik-builder')}</p>}
            {s.post_types.length > 0 && (
              <div className="flex flex-wrap gap-1.5">
                {s.post_types
                  .filter((p) => !d.post_types.includes(p.key))
                  .slice(0, 6)
                  .map((p) => (
                    <button key={p.key} type="button" onClick={() => update({ post_types: [...d.post_types, p.key] })} className="inline-flex h-7 items-center gap-1.5 rounded-full border border-dashed border-border px-2.5 text-xs text-muted-foreground hover:border-foreground/30 hover:text-foreground cursor-pointer">
                      <MenuIcon icon={p.icon} size={13} />
                      {p.plural || p.key}
                      <Icon name="plus" size={12} />
                    </button>
                  ))}
              </div>
            )}
          </Card>

          <Card id="sec-advanced" title={__('Advanced', 'brik-builder')}>
            <SwitchRow icon="globe" label={__('Public', 'brik-builder')} help={__('Terms get archive pages on the site.', 'brik-builder')} checked={d.public} onChange={(v) => update({ public: v })} />
            {d.public && (
              <Row label={__('URL slug', 'brik-builder')}>
                <Input className="font-mono" value={d.rewrite_slug} placeholder={slugify(d.singular, '-') || 'project-type'} onChange={(e) => (setTouched({ ...touched, slug: true }), update({ rewrite_slug: e.target.value }))} />
                <p className="flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground">
                  <Icon name="link" size={12} />
                  <span className="truncate font-mono">
                    {homeUrl()}/<b className="font-semibold text-foreground">{slug}</b>/{slugify(__('example term', 'brik-builder'), '-')}/
                  </span>
                </p>
              </Row>
            )}
            <SwitchRow icon="braces" label={__('Show in REST API', 'brik-builder')} help={__('Needed for the block editor panel and Brik filters.', 'brik-builder')} checked={d.show_in_rest} onChange={(v) => update({ show_in_rest: v })} />
            <SwitchRow icon="columns-3" label={__('Show admin column', 'brik-builder')} help={__('Adds a column with the terms to the entries list.', 'brik-builder')} checked={d.show_admin_column} onChange={(v) => update({ show_admin_column: v })} />
            <LabelsEditor value={d.labels} onChange={(labels) => update({ labels })} generated={generated} />
          </Card>
        </div>
      </div>
      <SaveBar dirty={dirty} saving={saving} isNew={isNew} onSave={save} onCancel={() => navigate('#/taxonomies')} error={serverError} />
    </div>
  );
}
