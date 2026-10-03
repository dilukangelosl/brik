import { useState, useEffect, useMemo } from './wp.js';
import { useStore, setState, getState, select, insertNodes, moveNode, toast } from './store.js';
import * as T from './tree.js';
import { library as loadLibrary, libraryItem, deleteLibraryItem } from './api.js';
import { setDragging, dragging, scrollTo } from './canvas.js';
import { Icon } from './icons.js';
import { cn, Input, IconButton, Tabs, Empty, Button } from './ui.jsx';

export function LeftPanel() {
  const left = useStore((s) => s.left);
  if (!left) return null;
  return (
    <aside className="flex w-72 shrink-0 flex-col border-r border-border bg-background">
      <div className="flex h-11 items-center justify-between border-b border-border px-3">
        <Tabs
          value={left}
          onChange={(v) => setState({ left: v })}
          tabs={[
            { value: 'modules', label: 'Add', icon: 'plus' },
            { value: 'layers', label: 'Layers', icon: 'layers' },
            { value: 'library', label: 'Library', icon: 'library' },
          ]}
        />
        <IconButton icon="panel-left-close" label="Close panel" size="icon-sm" onClick={() => setState({ left: null })} />
      </div>
      <div className="flex-1 overflow-y-auto">
        {left === 'modules' && <ModulesPanel />}
        {left === 'layers' && <LayersPanel />}
        {left === 'library' && <LibraryPanel />}
      </div>
    </aside>
  );
}

/* ------------------------------------------------------------------------
 * Modules.
 * ---------------------------------------------------------------------- */

/** Where a click-to-add should go, based on the selection. */
export function insertionPoint(type) {
  const { tree, selected } = getState();
  const node = selected ? T.find(tree, selected) : null;
  if (type === 'section') {
    const top = node ? (T.pathTo(tree, node.id) || [])[0] || node : null;
    return { parent: null, index: top ? tree.findIndex((n) => n.id === top.id) + 1 : tree.length };
  }
  if (!node) return { parent: null, index: tree.length };
  if (type === 'row') {
    const section = node.type === 'section' ? node : (T.pathTo(tree, node.id) || []).find((n) => n.type === 'section');
    if (section) return { parent: section.id, index: (section.children || []).length };
    return { parent: null, index: tree.length };
  }
  if (node.type === 'column') return { parent: node.id, index: (node.children || []).length };
  if (node.type === 'row' || node.type === 'section') {
    let column = null;
    T.walk([node], (n) => {
      if (n.type === 'column') column = n;
    });
    if (column) return { parent: column.id, index: (column.children || []).length };
    return { parent: null, index: tree.length };
  }
  const { parent, index } = T.locate(tree, node.id);
  return { parent: parent ? parent.id : null, index: index + 1 };
}

export function addModule(type, at) {
  const spot = at || insertionPoint(type);
  insertNodes(spot.parent, spot.index, [T.create(type)], 'Add element');
}

export function ModuleGrid({ onPick, draggable = true, filter = '' }) {
  const schema = useStore((s) => s.schema);
  const [q, setQ] = useState(filter);
  const groups = useMemo(() => {
    if (!schema) return [];
    const term = q.trim().toLowerCase();
    const out = {};
    for (const m of schema.modules) {
      if (m.type === 'column' || m.type === 'global') continue;
      if (term && !`${m.title} ${m.type} ${m.description}`.toLowerCase().includes(term)) continue;
      (out[m.category] = out[m.category] || []).push(m);
    }
    return Object.entries(schema.categories)
      .filter(([k]) => out[k])
      .map(([k, label]) => ({ key: k, label, modules: out[k] }));
  }, [schema, q]);

  return (
    <div className="space-y-4">
      <div className="relative">
        <Icon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
        <Input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search elements" className="pl-8" />
      </div>
      {groups.map((g) => (
        <div key={g.key}>
          <p className="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{g.label}</p>
          <div className="grid grid-cols-3 gap-1.5">
            {g.modules.map((m) => (
              <button
                key={m.type}
                type="button"
                draggable={draggable}
                onDragStart={(e) => {
                  e.dataTransfer.setData('text/plain', m.type);
                  e.dataTransfer.effectAllowed = 'copy';
                  setDragging({ type: m.type });
                }}
                onDragEnd={() => setDragging(null)}
                onClick={() => onPick(m.type)}
                title={m.description}
                className="flex aspect-[1.1] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-1 text-center text-[11px] leading-tight shadow-xs transition-colors hover:border-brand hover:bg-brand/5 active:cursor-grabbing"
              >
                <span className="text-foreground/80" dangerouslySetInnerHTML={{ __html: m.icon }} />
                <span className="line-clamp-2">{m.title}</span>
              </button>
            ))}
          </div>
        </div>
      ))}
      {!groups.length && <Empty icon="search-x" title="No elements found" />}
    </div>
  );
}

function ModulesPanel() {
  return (
    <div className="p-3">
      <ModuleGrid onPick={(type) => addModule(type)} />
      <p className="mt-4 text-[11px] text-muted-foreground">Drag elements onto the page, or click to add after the selected element.</p>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Layers.
 * ---------------------------------------------------------------------- */

function LayersPanel() {
  const tree = useStore((s) => s.tree);
  const schema = useStore((s) => s.schema);
  const [collapsed, setCollapsed] = useState({});
  const [drop, setDrop] = useState(null);

  if (!tree.length) {
    return (
      <div className="p-3">
        <Empty icon="layers" title="The page is empty">
          Add a section to get started.
        </Empty>
      </div>
    );
  }

  const onDragOver = (e, node) => {
    if (!dragging) return;
    const r = e.currentTarget.getBoundingClientRect();
    const y = (e.clientY - r.top) / r.height;
    const type = dragging.type;
    const { parent, index } = T.locate(tree, node.id);
    let target = null;
    if (T.canContain(node.type, type) && y > 0.25 && y < 0.75) target = { parent: node.id, index: (node.children || []).length, id: node.id, pos: 'inside' };
    else if (T.canContain(parent ? parent.type : null, type)) target = { parent: parent ? parent.id : null, index: index + (y >= 0.5 ? 1 : 0), id: node.id, pos: y >= 0.5 ? 'after' : 'before' };
    else if (T.canContain(node.type, type)) target = { parent: node.id, index: (node.children || []).length, id: node.id, pos: 'inside' };
    if (target && dragging.move && (dragging.move === node.id || T.find([T.find(tree, dragging.move)], node.id))) target = null;
    if (target) e.preventDefault();
    setDrop(target);
  };

  const onDrop = (e) => {
    e.preventDefault();
    if (!drop || !dragging) return;
    if (dragging.move) moveNode(dragging.move, drop.parent, drop.index);
    else if (dragging.type) insertNodes(drop.parent, drop.index, [T.create(dragging.type)], 'Add element');
    setDragging(null);
    setDrop(null);
  };

  return (
    <div className="p-2" onDragLeave={(e) => !e.currentTarget.contains(e.relatedTarget) && setDrop(null)}>
      {tree.map((n) => (
        <LayerRow key={n.id} node={n} depth={0} schema={schema} collapsed={collapsed} setCollapsed={setCollapsed} drop={drop} onDragOver={onDragOver} onDrop={onDrop} />
      ))}
    </div>
  );
}

const TYPE_COLORS = {
  section: 'text-sky-500',
  row: 'text-emerald-500',
  column: 'text-amber-500',
};

function LayerRow({ node, depth, schema, collapsed, setCollapsed, drop, onDragOver, onDrop }) {
  const selected = useStore((s) => s.selected);
  const def = schema.byType[node.type];
  const kids = node.children || [];
  const isCollapsed = collapsed[node.id];
  const hidden = node.attrs && Array.isArray(node.attrs.hide_on) && node.attrs.hide_on.length === 3;
  const preview = T.preview(node);

  return (
    <div>
      <div
        draggable
        onDragStart={(e) => {
          e.stopPropagation();
          e.dataTransfer.setData('text/plain', node.id);
          setDragging({ move: node.id, type: node.type });
        }}
        onDragEnd={() => setDragging(null)}
        onDragOver={(e) => onDragOver(e, node)}
        onDrop={onDrop}
        onClick={() => {
          select(node.id);
          scrollTo(node.id);
        }}
        onContextMenu={(e) => {
          e.preventDefault();
          select(node.id);
          setState({ menu: { x: e.clientX, y: e.clientY, id: node.id } });
        }}
        className={cn(
          'relative flex h-8 cursor-pointer items-center gap-1.5 rounded-md pr-2 text-sm hover:bg-accent',
          selected === node.id && 'bg-brand/10 text-foreground ring-1 ring-brand/40',
          hidden && 'opacity-50',
          drop && drop.id === node.id && drop.pos === 'inside' && 'ring-2 ring-brand'
        )}
        style={{ paddingLeft: depth * 14 + 4 }}
      >
        {drop && drop.id === node.id && drop.pos !== 'inside' && <span className={cn('absolute left-2 right-2 h-0.5 rounded bg-brand', drop.pos === 'before' ? 'top-0' : 'bottom-0')} />}
        {kids.length ? (
          <button
            type="button"
            className="text-muted-foreground cursor-pointer"
            onClick={(e) => {
              e.stopPropagation();
              setCollapsed({ ...collapsed, [node.id]: !isCollapsed });
            }}
          >
            <Icon name="chevron-right" size={14} className={cn('transition-transform', !isCollapsed && 'rotate-90')} />
          </button>
        ) : (
          <span className="w-3.5" />
        )}
        <span className={cn('shrink-0 [&_svg]:size-3.5', TYPE_COLORS[node.type] || 'text-violet-500')} dangerouslySetInnerHTML={{ __html: def ? def.icon : '' }} />
        <span className="truncate">{T.label(node, schema)}</span>
        {preview && <span className="truncate text-xs text-muted-foreground">{preview}</span>}
      </div>
      {!isCollapsed &&
        kids.map((c) => <LayerRow key={c.id} node={c} depth={depth + 1} schema={schema} collapsed={collapsed} setCollapsed={setCollapsed} drop={drop} onDragOver={onDragOver} onDrop={onDrop} />)}
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Library.
 * ---------------------------------------------------------------------- */

export function useLibrary() {
  const [data, setData] = useState(null);
  const refresh = () => loadLibrary().then(setData);
  useEffect(() => {
    refresh();
  }, []);
  return [data, refresh];
}

/** Insert a saved library item. Global items are inserted as references. */
export async function insertLibraryItem(item, at) {
  const spot = at || insertionPoint(item.kind === 'module' ? 'module' : item.kind === 'row' ? 'row' : 'section');
  if (item.global) {
    insertNodes(spot.parent, spot.index, [{ id: T.uid(), type: 'global', attrs: { ref: item.id } }], 'Insert global element');
    return;
  }
  const parent = spot.parent ? T.find(getState().tree, spot.parent) : null;
  const full = await libraryItem(item.id, parent && parent.type === 'column' ? 'column' : 'root');
  insertNodes(spot.parent, spot.index, T.cloneAll(full.tree || []), 'Insert from library');
}

export function insertBundled(layout, at) {
  const spot = at || insertionPoint('section');
  insertNodes(spot.parent, spot.index, T.cloneAll(withIds(layout.tree)), `Insert ${layout.title}`);
}

function withIds(nodes) {
  return nodes.map((n) => ({ ...n, id: n.id || T.uid(), attrs: n.attrs || {}, children: n.children ? withIds(n.children) : n.children }));
}

function LibraryPanel() {
  const [data, refresh] = useLibrary();
  const [tab, setTab] = useState('layouts');
  const [q, setQ] = useState('');
  if (!data) return <p className="p-4 text-sm text-muted-foreground">Loading…</p>;
  const term = q.toLowerCase();
  const bundled = data.bundled.filter((l) => !term || `${l.title} ${l.category}`.toLowerCase().includes(term));
  const saved = data.items.filter((i) => !term || i.title.toLowerCase().includes(term));
  const categories = [...new Set(bundled.map((l) => l.category))];

  return (
    <div className="space-y-3 p-3">
      <Tabs
        className="w-full"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'layouts', label: 'Layouts' },
          { value: 'saved', label: `Saved (${data.items.length})` },
        ]}
      />
      <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search" />
      {tab === 'layouts' &&
        categories.map((cat) => (
          <div key={cat}>
            <p className="mb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{cat}</p>
            <div className="space-y-1">
              {bundled
                .filter((l) => l.category === cat)
                .map((l) => (
                  <button
                    key={l.slug}
                    type="button"
                    draggable
                    onDragStart={(e) => {
                      e.dataTransfer.setData('text/plain', l.slug);
                      setDragging({ type: 'section', nodes: withIds(l.tree) });
                    }}
                    onDragEnd={() => setDragging(null)}
                    onClick={() => insertBundled(l)}
                    className="flex w-full items-center gap-2 rounded-md border border-border px-2.5 py-2 text-left text-sm hover:border-brand hover:bg-brand/5 cursor-pointer"
                  >
                    <Icon name="layout-template" size={14} className="text-muted-foreground" />
                    <span className="truncate">{l.title}</span>
                  </button>
                ))}
            </div>
          </div>
        ))}
      {tab === 'layouts' && !bundled.length && <Empty icon="layout-template" title="No layouts" />}
      {tab === 'saved' && (
        <div className="space-y-1">
          {saved.map((item) => (
            <div key={item.id} className="group flex items-center gap-2 rounded-md border border-border px-2.5 py-1.5 text-sm hover:border-brand">
              <button type="button" className="flex flex-1 items-center gap-2 truncate text-left cursor-pointer" onClick={() => insertLibraryItem(item)}>
                <Icon name={item.global ? 'globe' : 'bookmark'} size={14} className={item.global ? 'text-brand' : 'text-muted-foreground'} />
                <span className="truncate">{item.title}</span>
                <span className="rounded bg-muted px-1 text-[10px] text-muted-foreground">{item.kind}</span>
              </button>
              <a href={`${window.brikBuilder.adminUrl}post.php?post=${item.id}&action=brik`} target="_blank" rel="noreferrer" className="hidden text-muted-foreground hover:text-foreground group-hover:block" title="Edit">
                <Icon name="pencil" size={13} />
              </a>
              <button
                type="button"
                className="hidden text-muted-foreground hover:text-destructive group-hover:block cursor-pointer"
                title="Delete"
                onClick={async () => {
                  if (!window.confirm(`Delete "${item.title}" from the library?`)) return;
                  await deleteLibraryItem(item.id);
                  refresh();
                  toast('Deleted');
                }}
              >
                <Icon name="trash-2" size={13} />
              </button>
            </div>
          ))}
          {!saved.length && (
            <Empty icon="bookmark" title="Nothing saved yet">
              Use the library button on any element's toolbar to save it here.
            </Empty>
          )}
        </div>
      )}
    </div>
  );
}
