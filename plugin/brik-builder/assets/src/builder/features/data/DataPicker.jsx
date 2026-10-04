// Searchable tree of dynamic data (post, author, terms, custom fields, ACF, Meta Box, Pods,
// WooCommerce, user, site, options, URL) with live preview values and output modifiers.
import { useState, useEffect, useMemo } from '../../wp.js';
import { closeModal, openModal, setAttr, useStore, attrKey } from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Dialog, Button, IconButton, Input, Select, Switch, Empty } from '../../ui.jsx';
import { loadSources, errorText } from './api.js';

export const MOD_LABELS = {
  url: 'URL',
  id: 'ID',
  alt: 'Alt text',
  label: 'Label',
  text: 'Plain text',
  date: 'Date',
  time: 'Time',
  datetime: 'Date & time',
  relative: 'Relative',
  iso: 'ISO 8601',
  year: 'Year',
  number: 'Formatted',
  int: 'Integer',
  raw: 'Raw',
  list: 'List',
  first: 'First',
  links: 'Links',
  count: 'Count',
  slug: 'Slugs',
};

const MODE_KINDS = {
  image: ['image', 'url'],
  link: ['url', 'image', 'terms', 'posts'],
};

/** Flatten groups → [{ item, group, path }]. */
function flatten(groups) {
  const out = [];
  const walk = (items, group, path) => {
    for (const item of items) {
      if (item.items) walk(item.items, group, [...path, item.label]);
      else out.push({ item, group, path });
    }
  };
  for (const g of groups) walk(g.items, g, []);
  return out;
}

function buildTag(item, mod, name) {
  if (!item) return '';
  let tag = item.tag;
  if (item.input) tag += (name || '').replace(/[^A-Za-z0-9_.\-]/g, '');
  if (tag.endsWith(':')) return '';
  return `{${tag}${mod ? `|${mod}` : ''}}`;
}

export function DataPickerModal({ mode = 'text', onPick, title }) {
  const [postType, setPostType] = useState('');
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [query, setQuery] = useState('');
  const [active, setActive] = useState(null);
  const [selected, setSelected] = useState(null);
  const [mod, setMod] = useState('');
  const [name, setName] = useState('');
  const [all, setAll] = useState(mode === 'text');

  useEffect(() => {
    setData(null);
    setError('');
    loadSources(postType)
      .then((d) => {
        setData(d);
        setActive((a) => a || (d.groups[0] && d.groups[0].key));
      })
      .catch((e) => setError(errorText(e)));
  }, [postType]);

  const kinds = all ? null : MODE_KINDS[mode];
  const flat = useMemo(() => (data ? flatten(data.groups) : []), [data]);
  const visible = useMemo(() => {
    const q = query.trim().toLowerCase();
    return flat.filter(({ item, group, path }) => {
      if (kinds && !kinds.includes(item.kind) && !item.input) return false;
      if (!q) return group.key === active;
      return `${group.label} ${path.join(' ')} ${item.label} ${item.tag}`.toLowerCase().includes(q);
    });
  }, [flat, query, active, kinds]);

  const counts = useMemo(() => {
    const c = {};
    for (const { item, group } of flat) {
      if (kinds && !kinds.includes(item.kind) && !item.input) continue;
      c[group.key] = (c[group.key] || 0) + 1;
    }
    return c;
  }, [flat, kinds]);

  // Links and images want a URL out of lists and posts.
  const modeMod = (item) => {
    const mods = (data && data.modifiers[item.kind]) || [];
    return mode !== 'text' && mods.includes('url') && mods[0] !== 'url' ? 'url' : '';
  };
  const choose = (entry) => {
    setSelected(entry);
    setName('');
    setMod(modeMod(entry.item));
  };

  const tag = selected ? buildTag(selected.item, mod, name) : '';
  const insert = (t = tag) => {
    if (!t) return;
    onPick(t);
    closeModal();
  };

  const mods = selected && data ? data.modifiers[selected.item.kind] || [] : [];
  const types = data ? Object.entries(data.post_types).map(([value, label]) => ({ value, label })) : [];

  // Sub-group headings while browsing one group.
  let lastPath = '';

  return (
    <Dialog
      size="lg"
      title={title || 'Insert dynamic data'}
      description="Pick a value from the database. It updates for every post the element is shown with."
      onClose={closeModal}
      footer={
        <div className="flex w-full flex-wrap items-center gap-2">
          {selected ? (
            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
              <code className="truncate rounded-md border border-border bg-muted px-2 py-1 font-mono text-xs">{tag || `{${selected.item.tag}…}`}</code>
              {mods.length > 0 && (
                <div className="inline-flex flex-wrap rounded-md border border-input p-0.5 shadow-xs" role="radiogroup" aria-label="Output">
                  {mods.map((m, i) => {
                    const value = i === 0 && m !== 'raw' ? '' : m;
                    return (
                      <button
                        key={m}
                        type="button"
                        role="radio"
                        aria-checked={mod === value}
                        onClick={() => setMod(value)}
                        className={cn('h-6 rounded px-2 text-xs cursor-pointer', mod === value ? 'bg-accent font-medium text-foreground' : 'text-muted-foreground hover:text-foreground')}
                      >
                        {MOD_LABELS[m] || m}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          ) : (
            <span className="flex-1 text-xs text-muted-foreground">Select a value to insert. Double-click inserts right away.</span>
          )}
          <Button variant="outline" size="sm" onClick={closeModal}>
            Cancel
          </Button>
          <Button size="sm" icon="plus" disabled={!tag} onClick={() => insert()}>
            Insert
          </Button>
        </div>
      }
    >
      <div className="mb-3 flex flex-wrap items-center gap-2">
        <div className="relative min-w-48 flex-1">
          <Icon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input autoFocus value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search fields, e.g. price, author, image…" className="pl-8" />
        </div>
        {data && (
          <div className="flex items-center gap-2 text-xs text-muted-foreground">
            <span className="whitespace-nowrap">Preview with</span>
            <Select className="h-8 w-40" value={postType || data.context.post_type} options={types} onChange={(v) => setPostType(v)} />
          </div>
        )}
      </div>
      {data && data.context.title && (
        <p className="mb-3 flex items-center gap-1.5 text-xs text-muted-foreground">
          <Icon name="eye" size={12} />
          Values from <span className="font-medium text-foreground">{data.context.title}</span>
          {MODE_KINDS[mode] && (
            <span className="ml-auto flex items-center gap-2">
              Show all fields <Switch checked={all} onChange={setAll} label="Show all fields" />
            </span>
          )}
        </p>
      )}

      {error && <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}
      {!data && !error && (
        <div className="flex h-80 items-center justify-center">
          <span className="bk-spinner" />
        </div>
      )}

      {data && (
        <div className="grid h-[420px] grid-cols-[170px_1fr] overflow-hidden rounded-lg border border-border">
          <nav className="space-y-0.5 overflow-y-auto border-r border-border bg-muted/40 p-1.5" aria-label="Sources">
            {data.groups.map((g) =>
              counts[g.key] ? (
                <button
                  key={g.key}
                  type="button"
                  onClick={() => {
                    setActive(g.key);
                    setQuery('');
                  }}
                  className={cn(
                    'flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm cursor-pointer',
                    active === g.key && !query ? 'bg-background font-medium text-foreground shadow-xs' : 'text-muted-foreground hover:bg-background/60 hover:text-foreground'
                  )}
                >
                  <Icon name={g.icon} size={14} />
                  <span className="flex-1 truncate">{g.label}</span>
                  <span className="text-[10px] tabular-nums opacity-60">{counts[g.key]}</span>
                </button>
              ) : null
            )}
          </nav>
          <div className="overflow-y-auto p-1.5" role="listbox" aria-label="Values">
            {visible.length === 0 && (
              <div className="p-6">
                <Empty icon="search-x" title="Nothing matches">
                  Try another word, or switch “Show all fields” on.
                </Empty>
              </div>
            )}
            {visible.map((entry, i) => {
              const { item, group, path } = entry;
              const heading = query ? '' : path.join(' › ');
              const showHeading = heading && heading !== lastPath;
              if (!query) lastPath = heading;
              const isSel = selected && selected.item === item;
              return (
                <div key={`${group.key}-${item.tag}-${item.label}-${i}`}>
                  {showHeading && <p className="px-2 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{heading}</p>}
                  <button
                    type="button"
                    role="option"
                    aria-selected={!!isSel}
                    onClick={() => choose(entry)}
                    onDoubleClick={() => !item.input && insert(buildTag(item, modeMod(item), ''))}
                    className={cn('group flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left cursor-pointer', isSel ? 'bg-accent ring-1 ring-ring/40' : 'hover:bg-accent/60')}
                  >
                    <KindIcon kind={item.kind} />
                    <span className="min-w-0 flex-1">
                      <span className="flex items-center gap-1.5 text-sm">
                        {query && (
                          <span className="text-muted-foreground">
                            {group.label}
                            {path.length ? ` › ${path.join(' › ')}` : ''} ›
                          </span>
                        )}
                        <span className="truncate font-medium">{item.label}</span>
                        {item.private && (
                          <span className="rounded border border-amber-500/40 bg-amber-500/10 px-1 text-[10px] text-amber-700" title="Protected meta: only shown to people who can edit the post">
                            private
                          </span>
                        )}
                      </span>
                      <code className="block truncate font-mono text-[11px] text-muted-foreground">{item.input ? `{${item.tag}${item.placeholder}}` : `{${item.tag}}`}</code>
                    </span>
                    {item.kind === 'image' && item.preview && /^https?:/.test(item.preview) ? (
                      <img src={item.preview} alt="" className="size-8 shrink-0 rounded border border-border object-cover" />
                    ) : (
                      <span className="max-w-[45%] truncate text-xs text-muted-foreground" title={item.preview || ''}>
                        {item.preview || (item.input ? '' : <span className="opacity-50">empty</span>)}
                      </span>
                    )}
                  </button>
                  {isSel && item.input && (
                    <div className="flex items-center gap-2 px-2 pb-2 pt-1">
                      <Input autoFocus value={name} placeholder={item.placeholder} onChange={(e) => setName(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && insert()} className="h-7 text-xs" />
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>
      )}
    </Dialog>
  );
}

const KIND_ICONS = { image: 'image', url: 'link', date: 'calendar', number: 'hash', terms: 'tags', posts: 'files', list: 'list', html: 'file-code', bool: 'toggle-left', text: 'type' };

function KindIcon({ kind }) {
  return (
    <span className="flex size-7 shrink-0 items-center justify-center rounded-md border border-border bg-background text-muted-foreground">
      <Icon name={KIND_ICONS[kind] || 'type'} size={13} />
    </span>
  );
}

export function openPicker(props) {
  openModal('brik-data-picker', props);
}

/** Button next to every text input. */
export function TextAddon({ value, onChange, field }) {
  if (field && field.type && field.type !== 'text') return null;
  return (
    <IconButton
      icon="database"
      label="Insert dynamic data"
      size="icon-sm"
      variant="outline"
      className="size-8 shrink-0"
      onClick={() => openPicker({ mode: 'text', onPick: (t) => onChange(`${value || ''}${t}`) })}
    />
  );
}

const MEDIA = { image: 'image', link: 'link', video: 'link' };

/** "Dynamic" button in the label row of image, link and video fields. */
export function FieldLabelButton({ node, fkey, field }) {
  const mode = MEDIA[field && field.type];
  useStore((s) => s.device);
  if (!mode || !node) return null;
  const current = (node.attrs || {})[attrKey(fkey, field)];
  const url = current && typeof current === 'object' ? current.url : current;
  const dynamic = typeof url === 'string' && url.startsWith('{');
  return (
    <button
      type="button"
      title={dynamic ? `Dynamic: ${url}` : 'Use dynamic data'}
      className={cn('inline-flex h-5 items-center gap-1 rounded px-1 text-[11px] cursor-pointer', dynamic ? 'bg-brand/10 text-brand' : 'text-muted-foreground hover:text-foreground')}
      onClick={() =>
        openPicker({
          mode,
          title: mode === 'image' ? 'Dynamic image' : 'Dynamic link',
          onPick: (tag) => {
            const base = current && typeof current === 'object' ? current : {};
            const next = mode === 'image' ? { url: tag } : { ...base, url: tag };
            delete next.id;
            setAttr(node.id, fkey, next, field);
          },
        })
      }
    >
      <Icon name="database" size={11} />
      {dynamic ? 'Dynamic' : ''}
    </button>
  );
}
