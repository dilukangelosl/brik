// Global CSS classes: picker chips on the selected element and the class style editor.
import { useState, useMemo, useEffect, useRef, createPortal, config } from '../../wp.js';
import { useStore, getState, setState, replaceNode, setAttr, styleAttrs, attrKey, effectiveDevice, toast } from '../../store.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { Control, visible } from '../../fields.jsx';
import { cn, Popover, Input, Button, IconButton, Collapsible, Label } from '../../ui.jsx';
import { useClasses, saveClass, rerender, useUsage, loadUsage, classCount } from './data.js';
import { VariablePicker, supportsVariables } from './VariablePicker.jsx';

export function slugify(s) {
  return String(s || '')
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9_-]+/g, '-')
    .replace(/-{2,}/g, '-')
    .replace(/^[-_]+|[-_]+$/g, '')
    .slice(0, 60);
}

export function editClass(id) {
  setState({ classEditing: id });
}

/* ------------------------------------------------------------------------
 * Chips + combobox (nodeHeader).
 * ---------------------------------------------------------------------- */

export function ClassChips({ node }) {
  const classes = useClasses();
  const editing = useStore((s) => s.classEditing);
  const applied = ((node.attrs && node.attrs.classes) || []).filter((id) => classes[id]);

  const remove = (id) => {
    const next = applied.filter((x) => x !== id);
    setAttr(node.id, 'classes', next.length ? next : undefined, null, true);
  };

  return (
    <div className="flex flex-wrap items-center gap-1">
      {applied.map((id) => (
        <span
          key={id}
          className={cn(
            'group inline-flex h-6 items-center gap-1 rounded-md border pl-1.5 pr-0.5 font-mono text-[11px] transition-colors',
            editing === id ? 'border-brand bg-brand text-white' : 'border-brand/30 bg-brand/10 text-brand hover:border-brand/60'
          )}
        >
          <button type="button" className="cursor-pointer" title="Edit class styles" onClick={() => editClass(editing === id ? null : id)}>
            .{classes[id].name}
          </button>
          <button type="button" title="Remove class" aria-label={`Remove ${classes[id].name}`} className={cn('inline-flex size-4 cursor-pointer items-center justify-center rounded', editing === id ? 'hover:bg-white/20' : 'hover:bg-brand/15')} onClick={() => remove(id)}>
            <Icon name="x" size={11} />
          </button>
        </span>
      ))}
      <AddClass node={node} applied={applied} />
    </div>
  );
}

function AddClass({ node, applied }) {
  const classes = useClasses();
  const schema = useStore((s) => s.schema);
  const [q, setQ] = useState('');
  const [busy, setBusy] = useState(false);
  const term = q.trim().toLowerCase();
  const slug = slugify(q);
  const list = Object.entries(classes).filter(([id, c]) => !applied.includes(id) && (!term || `${c.name} ${c.label}`.toLowerCase().includes(term)));
  const exists = Object.values(classes).some((c) => c.name === slug);
  const styles = styleAttrs(node);
  const styleCount = Object.keys(styles).length;

  const apply = (id) => {
    const next = [...applied, id];
    setAttr(node.id, 'classes', next, null, true);
  };

  const create = async (fromStyles, close) => {
    if (!slug) return;
    setBusy(true);
    const id = T.uid();
    try {
      await saveClass(id, { name: slug, label: q.trim() || slug, type: node.type, attrs: fromStyles ? styles : {} });
      const current = T.find(getState().tree, node.id) || node;
      const attrs = { ...current.attrs, classes: [...applied, id] };
      // The element's own styles moved into the class; drop them so the class shows through.
      if (fromStyles) for (const k of Object.keys(styles)) delete attrs[k];
      replaceNode(node.id, { ...current, attrs }, fromStyles ? 'Create class from styles' : 'Add class');
      toast(fromStyles ? `Moved ${styleCount} style${styleCount === 1 ? '' : 's'} into .${slug}` : `Class .${slug} created`, 'success');
      setQ('');
      close();
      loadUsage().catch(() => {});
    } finally {
      setBusy(false);
    }
  };

  return (
    <Popover
      width={264}
      trigger={
        <button type="button" className="inline-flex h-6 cursor-pointer items-center gap-1 rounded-md border border-dashed border-input px-1.5 text-[11px] text-muted-foreground hover:border-foreground/30 hover:text-foreground" title="Add a CSS class">
          <Icon name="plus" size={11} />
          {applied.length ? '' : 'Class'}
        </button>
      }
    >
      {(close) => (
        <div className="-m-1 space-y-2">
          <div className="relative">
            <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 font-mono text-xs text-muted-foreground">.</span>
            <Input
              autoFocus
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onKeyDown={(e) => {
                if (e.key !== 'Enter') return;
                if (list[0] && (!slug || list[0][1].name === slug || !config.canManage)) apply(list[0][0]), close();
                else if (config.canManage && slug && !exists) create(false, close);
              }}
              placeholder="Search or create a class"
              className="h-8 pl-[17px] font-mono text-xs"
            />
          </div>
          <div className="max-h-56 overflow-y-auto">
            {list.map(([id, c]) => (
              <button
                key={id}
                type="button"
                onClick={() => {
                  apply(id);
                  close();
                }}
                className="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left hover:bg-accent"
              >
                <Icon name="hash" size={13} className="text-brand" />
                <span className="flex-1 truncate font-mono text-xs">.{c.name}</span>
                {c.type && schema && schema.byType[c.type] && <span className="text-[10px] text-muted-foreground">{schema.byType[c.type].title}</span>}
              </button>
            ))}
            {!list.length && !slug && <p className="px-2 py-3 text-center text-xs text-muted-foreground">No classes yet. Type a name to create one.</p>}
          </div>
          {config.canManage && slug && !exists && (
            <button type="button" disabled={busy} onClick={() => create(false, close)} className="flex w-full cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs hover:bg-accent">
              <Icon name="plus" size={13} />
              Create <span className="font-mono font-medium">.{slug}</span>
            </button>
          )}
          {config.canManage && styleCount > 0 && (
            <div className="space-y-1.5 border-t border-border pt-2">
              <Button
                size="xs"
                variant="outline"
                icon="wand-sparkles"
                className="w-full"
                disabled={busy || !slug || exists}
                onClick={() => create(true, close)}
                title={!slug ? 'Type a class name first' : ''}
              >
                Create class from element styles
              </Button>
              <p className="px-0.5 text-[10px] leading-snug text-muted-foreground">
                Moves {styleCount} design setting{styleCount === 1 ? '' : 's'} into {slug ? <span className="font-mono">.{slug}</span> : 'the new class'} so other elements can share them.
              </p>
            </div>
          )}
        </div>
      )}
    </Popover>
  );
}

/* ------------------------------------------------------------------------
 * Class style editor: docked over the settings panel while a class is being edited.
 * ---------------------------------------------------------------------- */

export function ClassEditorHost() {
  const id = useStore((s) => s.classEditing);
  const classes = useClasses();
  useEffect(() => {
    if (id && !classes[id]) setState({ classEditing: null });
  }, [id, classes]);
  if (!id || !classes[id]) return null;
  return createPortal(<ClassEditor key={id} id={id} cls={classes[id]} />, document.getElementById('brik-app'));
}

function ClassEditor({ id, cls }) {
  const schema = useStore((s) => s.schema);
  const mode = useStore((s) => s.mode);
  const device = useStore((s) => effectiveDevice(s));
  const usage = useUsage();
  const [attrs, setAttrs] = useState(() => ({ ...(cls.attrs || {}) }));
  const [query, setQuery] = useState('');
  const [renaming, setRenaming] = useState(false);
  const [saving, setSaving] = useState(false);
  const timer = useRef(null);
  const pending = useRef(null);

  const fields = useMemo(() => {
    const src = (cls.type && schema.byType[cls.type] && schema.byType[cls.type].fields) || schema.common || {};
    return Object.entries(src).filter(([, f]) => f.tab === 'design');
  }, [schema, cls.type]);

  const groups = useMemo(() => {
    const out = [];
    const index = {};
    const q = query.trim().toLowerCase();
    for (const [key, field] of fields) {
      if (q && !field.label.toLowerCase().includes(q) && !key.includes(q)) continue;
      if (mode === 'hover' && !field.hover && !q) continue;
      const gk = field.group_label ? `${field.group}:${field.group_label}` : field.group || 'design';
      if (!index[gk]) {
        index[gk] = { key: gk, label: field.group_label || schema.groups[field.group] || field.group, fields: [] };
        out.push(index[gk]);
      }
      index[gk].fields.push([key, field]);
    }
    return out;
  }, [fields, query, mode]);

  const flush = async () => {
    if (!pending.current) return;
    const next = pending.current;
    pending.current = null;
    setSaving(true);
    try {
      await saveClass(id, { attrs: next });
    } finally {
      setSaving(false);
    }
  };

  useEffect(() => () => (clearTimeout(timer.current), flush()), []);

  const change = (key, field, value) => {
    const k = attrKey(key, field);
    const next = { ...attrs };
    if (value === undefined || value === null || value === '') delete next[k];
    else next[k] = value;
    setAttrs(next);
    pending.current = next;
    clearTimeout(timer.current);
    timer.current = setTimeout(flush, 280);
  };

  const rename = async (name) => {
    setRenaming(false);
    const slug = slugify(name);
    if (!slug || slug === cls.name) return;
    await saveClass(id, { name: slug });
    rerender();
    toast(`Renamed to .${slug}`, 'success');
  };

  const tree = useStore((s) => s.tree);
  const count = usage ? classCount(usage, id, tree) : null;
  const typeTitle = cls.type && schema.byType[cls.type] ? schema.byType[cls.type].title : null;
  const groupHasValues = (g) => g.fields.some(([k]) => Object.keys(attrs).some((a) => a === k || a.startsWith(`${k}@`)));

  return (
    <aside className="bk-fade fixed bottom-0 right-0 top-12 z-[60] flex w-80 flex-col border-l border-border bg-background shadow-[-8px_0_24px_-12px_rgb(0_0_0/0.18)]">
      <div className="space-y-3 border-b border-border bg-brand/[0.04] p-3">
        <div className="flex items-center justify-between gap-2">
          <span className="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-brand">
            <Icon name="hash" size={12} />
            Editing class
          </span>
          <span className="flex items-center gap-1">
            {saving && <span className="text-[10px] text-muted-foreground">Saving…</span>}
            <Button size="xs" variant="brand" icon="check" onClick={() => setState({ classEditing: null })}>
              Done
            </Button>
          </span>
        </div>
        {renaming ? (
          <Input autoFocus defaultValue={cls.name} className="h-8 font-mono text-sm" onBlur={(e) => rename(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && e.target.blur()} />
        ) : (
          <button type="button" className="group flex min-w-0 cursor-pointer items-center gap-1.5 font-mono text-sm font-semibold" onClick={() => config.canManage && setRenaming(true)} title="Rename class">
            <span className="truncate">.{cls.name}</span>
            <Icon name="pencil" size={12} className="text-muted-foreground opacity-0 group-hover:opacity-100" />
            {typeTitle && <span className="rounded bg-muted px-1.5 py-px font-sans text-[10px] font-normal text-muted-foreground">{typeTitle}</span>}
          </button>
        )}
        <p className="text-[11px] leading-snug text-muted-foreground">
          {count === null ? 'Counting usages…' : `Used by ${count} element${count === 1 ? '' : 's'} across the site.`} Changes apply everywhere right away; element settings still win over the class.
        </p>
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <Icon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search class styles" className="h-7 pl-8 text-xs" />
          </div>
          <div className="inline-flex rounded-md border border-input p-0.5">
            {[
              ['default', 'Normal'],
              ['hover', 'Hover'],
            ].map(([m, label]) => (
              <button key={m} type="button" onClick={() => setState({ mode: m })} className={cn('h-6 cursor-pointer rounded px-1.5 text-[11px]', mode === m ? 'bg-accent text-foreground' : 'text-muted-foreground')}>
                {label}
              </button>
            ))}
          </div>
        </div>
        {(device !== 'desktop' || mode === 'hover') && (
          <p className="flex items-center gap-1.5 rounded-md bg-brand/10 px-2 py-1.5 text-[11px] text-brand">
            <Icon name={mode === 'hover' ? 'mouse-pointer-2' : device === 'tablet' ? 'tablet' : 'smartphone'} size={12} />
            {mode === 'hover' ? 'Editing the class hover state.' : `Editing ${device} values of the class.`}
          </p>
        )}
      </div>
      <div className="flex-1 overflow-y-auto">
        {groups.map((g, i) => {
          const list = g.fields.filter(([, f]) => visible(f, attrs, Object.fromEntries(fields)));
          if (!list.length) return null;
          return (
            <Collapsible key={`${id}-${g.key}`} title={g.label} defaultOpen={i === 0 || !!query || groupHasValues(g)} count={groupHasValues(g)}>
              {list.map(([key, field]) => (
                <ClassField key={key} fkey={key} field={field} attrs={attrs} onChange={(v) => change(key, field, v)} />
              ))}
            </Collapsible>
          );
        })}
        {!config.canManage && <p className="p-4 text-xs text-muted-foreground">You can view class styles; changing them needs the "edit theme options" capability.</p>}
      </div>
    </aside>
  );
}

function ClassField({ fkey, field, attrs, onChange }) {
  const k = attrKey(fkey, field);
  const value = attrs[k];
  const state = k.split('@')[1];
  const placeholder = k === fkey ? undefined : state === 'mobile' ? attrs[`${fkey}@tablet`] ?? attrs[fkey] : attrs[fkey];
  const has = value !== undefined && value !== '';
  return (
    <div className="bk-field space-y-1.5" data-type={field.type}>
      {field.type !== 'toggle' && (
        <div className="flex min-h-5 items-center justify-between gap-2">
          <Label className="flex items-center gap-1.5">
            {field.label}
            {state && <Icon name={state === 'hover' ? 'mouse-pointer-2' : state === 'tablet' ? 'tablet' : 'smartphone'} size={12} className="text-brand" />}
          </Label>
          <span className="flex items-center gap-1.5">
            {supportsVariables(field) && <VariablePicker field={field} fkey={fkey} value={value} onPick={(v) => onChange(v || undefined)} />}
            {has && (
              <button type="button" className="cursor-pointer text-muted-foreground hover:text-foreground" title="Reset" onClick={() => onChange(undefined)}>
                <Icon name="rotate-ccw" size={12} />
              </button>
            )}
          </span>
        </div>
      )}
      <Control field={field} value={value} placeholder={placeholder} onChange={config.canManage ? onChange : () => {}} fkey={fkey} />
    </div>
  );
}
