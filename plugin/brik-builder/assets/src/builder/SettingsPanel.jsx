import { useState, useMemo, config } from './wp.js';
import { useStore, setState, getState, select, setAttr, replaceNode, styleAttrs, toast, effectiveDevice } from './store.js';
import * as T from './tree.js';
import { saveSettings } from './api.js';
import { Icon } from './icons.js';
import { NodeField, visible } from './fields.jsx';
import { Slot } from './registry.js';
import { cn, Tabs, Collapsible, IconButton, Button, Input, Popover, Empty, Label, Select } from './ui.jsx';

const TAB_LIST = [
  { value: 'content', label: 'Content', icon: 'pencil' },
  { value: 'design', label: 'Design', icon: 'palette' },
  { value: 'advanced', label: 'Advanced', icon: 'settings-2' },
];

export function SettingsPanel() {
  const selected = useStore((s) => s.selected);
  const tree = useStore((s) => s.tree);
  const schema = useStore((s) => s.schema);
  const node = selected ? T.find(tree, selected) : null;

  if (!schema) return null;
  if (!node) {
    return (
      <div className="p-4">
        <Empty icon="mouse-pointer-click" title="Nothing selected">
          Click an element on the page to edit it. Double-click text to type right on the canvas.
        </Empty>
        <Shortcuts />
      </div>
    );
  }
  return <NodeSettings key={node.id} node={node} schema={schema} tree={tree} />;
}

function NodeSettings({ node, schema, tree }) {
  const [tab, setTab] = useState('content');
  const [query, setQuery] = useState('');
  const mode = useStore((s) => s.mode);
  const device = useStore((s) => effectiveDevice(s));
  const def = schema.byType[node.type];
  const path = T.pathTo(tree, node.id) || [];

  const groups = useMemo(() => {
    const out = [];
    const index = {};
    const q = query.trim().toLowerCase();
    if (!def) return out;
    for (const [key, field] of Object.entries(def.fields)) {
      if (q) {
        if (!field.label.toLowerCase().includes(q) && !key.includes(q)) continue;
      } else if ((field.tab || 'content') !== tab) {
        continue;
      }
      if (mode === 'hover' && !field.hover && !q) continue;
      const gk = field.group_label ? `${field.group}:${field.group_label}` : field.group || 'content';
      if (!index[gk]) {
        index[gk] = { key: gk, label: field.group_label || schema.groups[field.group] || humanize(field.group), fields: [] };
        out.push(index[gk]);
      }
      index[gk].fields.push([key, field]);
    }
    return out;
  }, [def, tab, mode, query]);

  if (!def) {
    return (
      <div className="p-4">
        <Empty icon="circle-help" title={`Unknown element "${node.type}"`}>
          The module that rendered this element is not available.
        </Empty>
      </div>
    );
  }

  const attrs = node.attrs || {};
  const groupHasValues = (g) => g.fields.some(([k]) => Object.keys(attrs).some((a) => a === k || a.startsWith(`${k}@`)));

  return (
    <div className="flex h-full flex-col">
      <div className="space-y-3 border-b border-border p-3">
        <div className="flex items-center gap-1 text-xs text-muted-foreground">
          {path.map((p) => (
            <span key={p.id} className="flex items-center gap-1">
              <button type="button" className="hover:text-foreground cursor-pointer" onClick={() => select(p.id)}>
                {T.label(p, schema)}
              </button>
              <Icon name="chevron-right" size={12} />
            </span>
          ))}
        </div>
        <div className="flex items-center justify-between gap-2">
          <AdminLabel node={node} def={def} />
          <Presets node={node} def={def} />
        </div>
        <Slot name="nodeHeader" node={node} def={def} />
        <Tabs tabs={TAB_LIST} value={tab} onChange={setTab} className="w-full" />
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <Icon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search settings" className="h-7 pl-8 text-xs" />
          </div>
          <StateSwitch />
        </div>
        {(device !== 'desktop' || mode === 'hover') && (
          <p className="flex items-center gap-1.5 rounded-md bg-brand/10 px-2 py-1.5 text-[11px] text-brand">
            <Icon name={mode === 'hover' ? 'mouse-pointer-2' : device === 'tablet' ? 'tablet' : 'smartphone'} size={12} />
            {mode === 'hover' ? 'Editing hover styles. Only fields with a hover state are shown.' : `Editing ${device} values. Fields without a ${device} variant change all devices.`}
          </p>
        )}
      </div>
      <div className="flex-1 overflow-y-auto">
        {groups.length === 0 && <p className="p-4 text-sm text-muted-foreground">No settings here.</p>}
        {groups.map((g, i) => {
          const fields = g.fields.filter(([, f]) => visible(f, attrs, def.fields));
          if (!fields.length) return null;
          return (
            <Collapsible key={`${node.id}-${tab}-${g.key}`} title={g.label} defaultOpen={i === 0 || !!query} count={groupHasValues(g)}>
              {fields.map(([key, field]) => (
                <NodeField key={key} node={node} fkey={key} field={field} def={def} />
              ))}
            </Collapsible>
          );
        })}
        <Slot name="nodeFooter" node={node} def={def} tab={tab} />
      </div>
    </div>
  );
}

function humanize(s) {
  return String(s || '')
    .replace(/_/g, ' ')
    .replace(/^\w/, (c) => c.toUpperCase());
}

function StateSwitch() {
  const mode = useStore((s) => s.mode);
  return (
    <div className="inline-flex rounded-md border border-input p-0.5">
      {[
        ['default', 'square-dashed-mouse-pointer', 'Normal'],
        ['hover', 'mouse-pointer-2', 'Hover'],
      ].map(([m, icon, label]) => (
        <button key={m} type="button" title={`${label} state`} onClick={() => setState({ mode: m })} className={cn('inline-flex h-6 items-center gap-1 rounded px-1.5 text-[11px] cursor-pointer', mode === m ? 'bg-accent text-foreground' : 'text-muted-foreground')}>
          <Icon name={icon} size={12} />
          {label}
        </button>
      ))}
    </div>
  );
}

function AdminLabel({ node, def }) {
  const [editing, setEditing] = useState(false);
  const label = (node.attrs && node.attrs.admin_label) || def.title;
  if (editing) {
    return (
      <Input
        autoFocus
        defaultValue={node.attrs && node.attrs.admin_label}
        placeholder={def.title}
        className="h-7"
        onBlur={(e) => {
          setAttr(node.id, 'admin_label', e.target.value.trim(), null, true);
          setEditing(false);
        }}
        onKeyDown={(e) => e.key === 'Enter' && e.target.blur()}
      />
    );
  }
  return (
    <button type="button" className="group flex min-w-0 items-center gap-1.5 text-sm font-semibold cursor-pointer" onClick={() => setEditing(true)} title="Rename">
      <span className="truncate">{label}</span>
      <Icon name="pencil" size={12} className="text-muted-foreground opacity-0 group-hover:opacity-100" />
    </button>
  );
}

/* ------------------------------------------------------------------------
 * Style presets.
 * ---------------------------------------------------------------------- */

function Presets({ node, def }) {
  const settings = useStore((s) => s.settings);
  if (!settings || def.structural) return null;
  const presets = (settings.presets && settings.presets[node.type]) || {};
  const current = node.attrs && node.attrs.preset;
  const entries = Object.entries(presets);

  const savePresets = async (next) => {
    await saveSettings({ presets: { ...settings.presets, [node.type]: next } });
    setState({ change: { full: true, seq: Math.random() } });
  };

  const createPreset = async (close) => {
    const name = window.prompt('Preset name', `${def.title} style`);
    if (!name) return;
    const id = T.uid();
    await savePresets({ ...presets, [id]: { name, attrs: styleAttrs(node), default: false } });
    // Styles now live in the preset; drop them from the element.
    const attrs = { ...node.attrs, preset: id };
    for (const k of Object.keys(styleAttrs(node))) delete attrs[k];
    replaceNode(node.id, { ...node, attrs }, 'Create preset');
    toast('Preset saved');
    close();
  };

  return (
    <Popover
      align="end"
      width={260}
      trigger={
        <Button size="xs" variant="outline" icon="swatch-book">
          {current && presets[current] ? presets[current].name : 'Presets'}
        </Button>
      }
    >
      {(close) => (
        <div className="space-y-2">
          <p className="text-xs text-muted-foreground">Presets share design settings between {def.title.toLowerCase()} elements site-wide.</p>
          <div className="space-y-0.5">
            <PresetRow label="No preset" active={!current} onClick={() => setAttr(node.id, 'preset', undefined, null, true)} />
            {entries.map(([id, p]) => (
              <PresetRow
                key={id}
                label={p.name}
                badge={p.default ? 'default' : ''}
                active={current === id}
                onClick={() => setAttr(node.id, 'preset', id, null, true)}
                actions={
                  config.canManage && (
                    <span className="flex">
                      <IconButton
                        icon="refresh-cw"
                        size="icon-sm"
                        label="Update with this element's styles"
                        onClick={async (e) => {
                          e.stopPropagation();
                          await savePresets({ ...presets, [id]: { ...p, attrs: { ...p.attrs, ...styleAttrs(node) } } });
                          toast('Preset updated');
                        }}
                      />
                      <IconButton
                        icon={p.default ? 'star-off' : 'star'}
                        size="icon-sm"
                        label="Toggle default"
                        onClick={(e) => {
                          e.stopPropagation();
                          const next = {};
                          for (const [k, v] of entries) next[k] = { ...v, default: k === id ? !p.default : false };
                          savePresets(next);
                        }}
                      />
                      <IconButton
                        icon="trash-2"
                        size="icon-sm"
                        label="Delete preset"
                        onClick={(e) => {
                          e.stopPropagation();
                          if (!window.confirm(`Delete preset "${p.name}"?`)) return;
                          const next = { ...presets };
                          delete next[id];
                          savePresets(next);
                        }}
                      />
                    </span>
                  )
                }
              />
            ))}
          </div>
          {config.canManage && (
            <Button size="sm" variant="outline" icon="plus" className="w-full" onClick={() => createPreset(close)}>
              Save styles as preset
            </Button>
          )}
        </div>
      )}
    </Popover>
  );
}

function PresetRow({ label, badge, active, onClick, actions }) {
  return (
    <div role="button" tabIndex={0} onClick={onClick} className={cn('flex items-center justify-between rounded px-2 py-1 text-sm hover:bg-accent cursor-pointer', active && 'bg-accent')}>
      <span className="flex items-center gap-1.5 truncate">
        {active && <Icon name="check" size={12} />}
        {label}
        {badge && <span className="rounded bg-muted px-1 text-[10px] text-muted-foreground">{badge}</span>}
      </span>
      {actions}
    </div>
  );
}

function Shortcuts() {
  const mod = navigator.platform.includes('Mac') ? '⌘' : 'Ctrl';
  const rows = [
    [`${mod} S`, 'Save'],
    [`${mod} Z`, 'Undo'],
    [`${mod} ⇧ Z`, 'Redo'],
    [`${mod} C / V`, 'Copy / paste element'],
    [`${mod} D`, 'Duplicate'],
    ['Del', 'Delete element'],
    ['Esc', 'Select parent'],
    [`${mod} ⇧ L`, 'Layers'],
    [`${mod} ⇧ A`, 'Add element'],
  ];
  return (
    <div className="mt-6 space-y-1.5">
      <p className="text-xs font-medium text-muted-foreground">Keyboard shortcuts</p>
      {rows.map(([k, l]) => (
        <div key={l} className="flex items-center justify-between text-xs">
          <span className="text-muted-foreground">{l}</span>
          <kbd className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">{k}</kbd>
        </div>
      ))}
    </div>
  );
}
