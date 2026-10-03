// Nested menu editor for the "menu_tree" field type.
import { useState, useEffect } from './wp.js';
import { uid } from './tree.js';
import { library as loadLibrary } from './api.js';
import { Icon } from './icons.js';
import { Control } from './fields.jsx';
import { cn, Button, IconButton, Dialog, Label, Select, Input, Empty } from './ui.jsx';

const MAX_DEPTH = 3;

const TYPES = [
  { value: 'link', label: 'Link' },
  { value: 'dropdown', label: 'Dropdown' },
  { value: 'mega', label: 'Mega menu' },
  { value: 'button', label: 'Button' },
  { value: 'heading', label: 'Heading (no link)' },
  { value: 'divider', label: 'Divider' },
];

const opts = (map) => Object.entries(map).map(([value, label]) => ({ value, label }));

/* ------------------------------------------------------------------------
 * Tree helpers. A path is a list of indexes from the top level down.
 * ---------------------------------------------------------------------- */

function withIds(items) {
  return (items || []).map((it) => ({ ...it, id: it.id || uid(), children: it.children ? withIds(it.children) : it.children }));
}

function getAt(items, path) {
  let list = items;
  let item = null;
  for (const i of path) {
    item = list[i];
    list = (item && item.children) || [];
  }
  return item;
}

function listAt(items, parentPath) {
  return parentPath.length ? getAt(items, parentPath).children || [] : items;
}

function setList(items, parentPath, list) {
  if (!parentPath.length) return list;
  const [head, ...rest] = parentPath;
  return items.map((it, i) => (i === head ? { ...it, children: setList(it.children || [], rest, list) } : it));
}

function updateAt(items, path, fn) {
  const parent = path.slice(0, -1);
  const index = path[path.length - 1];
  const list = listAt(items, parent).map((it, i) => (i === index ? fn(it) : it));
  return setList(items, parent, list);
}

function removeAt(items, path) {
  const parent = path.slice(0, -1);
  const list = listAt(items, parent).filter((_, i) => i !== path[path.length - 1]);
  return setList(items, parent, list);
}

function insertAt(items, parentPath, index, item) {
  const list = listAt(items, parentPath).slice();
  list.splice(index, 0, item);
  return setList(items, parentPath, list);
}

function depthOf(item) {
  if (!item || !item.children || !item.children.length) return 1;
  return 1 + Math.max(...item.children.map(depthOf));
}

function flatten(items, path = [], out = []) {
  items.forEach((item, i) => {
    const p = [...path, i];
    out.push({ item, path: p });
    if (item.children && item.children.length && !item._collapsed) flatten(item.children, p, out);
  });
  return out;
}

function removeById(items, id) {
  return items.filter((it) => it.id !== id).map((it) => (it.children ? { ...it, children: removeById(it.children, id) } : it));
}

function placeById(items, targetId, item, mode) {
  const out = [];
  for (const it of items) {
    if (it.id === targetId) {
      if (mode === 'before') out.push(item);
      out.push(mode === 'inside' ? { ...it, _collapsed: false, children: [...(it.children || []), item] } : it);
      if (mode === 'after') out.push(item);
    } else {
      out.push(it.children ? { ...it, children: placeById(it.children, targetId, item, mode) } : it);
    }
  }
  return out;
}

function pathOf(items, id, path = []) {
  for (let i = 0; i < items.length; i++) {
    if (items[i].id === id) return [...path, i];
    if (items[i].children) {
      const found = pathOf(items[i].children, id, [...path, i]);
      if (found) return found;
    }
  }
  return null;
}

const samePath = (a, b) => a && b && a.length === b.length && a.every((v, i) => v === b[i]);
const isPrefix = (a, b) => a.length <= b.length && a.every((v, i) => v === b[i]);

function stripUi(items) {
  return items.map(({ _collapsed, ...it }) => (it.children ? { ...it, children: stripUi(it.children) } : it));
}

/* ------------------------------------------------------------------------
 * Field control.
 * ---------------------------------------------------------------------- */

export function MenuTreeControl({ value, onChange, field, placeholder, node }) {
  // Older menus stored a flat "links" list; show those instead of the sample tree.
  const legacy = node && node.attrs && Array.isArray(node.attrs.links) && node.attrs.links.length ? node.attrs.links.map((l) => ({ id: uid(), label: l.text || '', link: l.link, type: 'link' })) : null;
  const items = Array.isArray(value) ? value : Array.isArray(placeholder) ? placeholder : legacy || (Array.isArray(field.default) ? field.default : []);
  const [open, setOpen] = useState(false);
  const count = flatten(items.map((i) => ({ ...i, _collapsed: false }))).length;

  return (
    <div className="space-y-2">
      <div className="rounded-md border border-border">
        {items.length ? (
          items.map((it, i) => (
            <div key={it.id || i} className="flex items-center gap-2 border-b border-border px-2.5 py-1.5 text-sm last:border-0">
              {it.icon ? <Icon name={it.icon} size={14} className="text-muted-foreground" /> : <span className="w-3.5" />}
              <span className="flex-1 truncate">{stripTags(it.label) || 'Untitled'}</span>
              {it.type && it.type !== 'link' && <span className="rounded bg-muted px-1.5 text-[10px] text-muted-foreground">{it.type}</span>}
              {it.children && it.children.length > 0 && <span className="text-[10px] text-muted-foreground">{it.children.length}</span>}
            </div>
          ))
        ) : (
          <p className="p-3 text-center text-xs text-muted-foreground">No items yet</p>
        )}
      </div>
      <Button size="sm" variant="outline" icon="list-tree" className="w-full" onClick={() => setOpen(true)}>
        Edit menu ({count} {count === 1 ? 'item' : 'items'})
      </Button>
      {open && <MenuEditor initial={withIds(items)} onChange={(next) => onChange(stripUi(next))} onClose={() => setOpen(false)} />}
    </div>
  );
}

function stripTags(s) {
  return String(s || '').replace(/<[^>]+>/g, '');
}

/* ------------------------------------------------------------------------
 * Editor dialog.
 * ---------------------------------------------------------------------- */

function MenuEditor({ initial, onChange, onClose }) {
  const [items, setItems] = useState(initial);
  const [sel, setSel] = useState(initial.length ? [0] : null);
  const [drag, setDrag] = useState(null);
  const [drop, setDrop] = useState(null);

  const commit = (next, select = sel) => {
    setItems(next);
    setSel(select);
    onChange(next);
  };

  const rows = flatten(items);
  const selected = sel ? getAt(items, sel) : null;

  const add = (parentPath = []) => {
    const list = listAt(items, parentPath);
    const item = { id: uid(), label: parentPath.length ? 'New link' : 'New item', link: { url: '#' }, type: 'link' };
    commit(insertAt(items, parentPath, list.length, item), [...parentPath, list.length]);
  };

  const duplicate = (path) => {
    const copy = withIds([JSON.parse(JSON.stringify(getAt(items, path)))]).map(function fresh(it) {
      return { ...it, id: uid(), children: it.children ? it.children.map(fresh) : it.children };
    })[0];
    const parent = path.slice(0, -1);
    const index = path[path.length - 1] + 1;
    commit(insertAt(items, parent, index, copy), [...parent, index]);
  };

  const remove = (path) => commit(removeAt(items, path), null);

  const move = (path, delta) => {
    const parent = path.slice(0, -1);
    const index = path[path.length - 1];
    const list = listAt(items, parent).slice();
    const to = index + delta;
    if (to < 0 || to >= list.length) return;
    const [it] = list.splice(index, 1);
    list.splice(to, 0, it);
    commit(setList(items, parent, list), [...parent, to]);
  };

  // Indent: becomes the last child of the previous sibling.
  const indent = (path) => {
    const index = path[path.length - 1];
    if (index === 0 || path.length >= MAX_DEPTH) return;
    const item = getAt(items, path);
    if (path.length + depthOf(item) > MAX_DEPTH) return;
    const parent = path.slice(0, -1);
    const prevPath = [...parent, index - 1];
    let next = removeAt(items, path);
    const prev = getAt(next, prevPath);
    const kids = prev.children || [];
    next = updateAt(next, prevPath, (p) => ({ ...p, _collapsed: false, children: [...kids, item], type: p.type === 'link' || !p.type ? (prevPath.length === 1 ? 'dropdown' : p.type) : p.type }));
    commit(next, [...prevPath, kids.length]);
  };

  // Outdent: moves after its parent.
  const outdent = (path) => {
    if (path.length < 2) return;
    const item = getAt(items, path);
    const parentPath = path.slice(0, -1);
    const grand = parentPath.slice(0, -1);
    const parentIndex = parentPath[parentPath.length - 1];
    const next = insertAt(removeAt(items, path), grand, parentIndex + 1, item);
    commit(next, [...grand, parentIndex + 1]);
  };

  const update = (patch) => commit(updateAt(items, sel, (it) => ({ ...it, ...patch })));

  const onDrop = () => {
    if (!drag || !drop || isPrefix(drag, drop.path)) return finishDrag();
    const item = getAt(items, drag);
    const target = getAt(items, drop.path);
    const level = drop.inside ? drop.path.length + 1 : drop.path.length;
    if (level - 1 + depthOf(item) > MAX_DEPTH) return finishDrag();
    const next = placeById(removeById(items, item.id), target.id, item, drop.inside ? 'inside' : drop.after ? 'after' : 'before');
    setItems(next);
    onChange(next);
    setSel(pathOf(next, item.id));
    finishDrag();
  };

  const finishDrag = () => {
    setDrag(null);
    setDrop(null);
  };

  return (
    <Dialog title="Menu editor" description="Drag to reorder. Indent items to nest them under a dropdown or mega menu." onClose={onClose} size="xl" footer={<Button onClick={onClose}>Done</Button>}>
      <div className="grid min-h-[460px] grid-cols-[1fr_1.15fr] gap-5">
        <div className="flex flex-col gap-2">
          <div className="flex-1 space-y-0.5 rounded-lg border border-border p-1.5" onDragLeave={(e) => !e.currentTarget.contains(e.relatedTarget) && setDrop(null)}>
            {!rows.length && <Empty icon="list-tree" title="No menu items" />}
            {rows.map(({ item, path }) => {
              const active = samePath(path, sel);
              const hasKids = item.children && item.children.length > 0;
              return (
                <div
                  key={item.id}
                  draggable
                  onDragStart={(e) => {
                    e.dataTransfer.setData('text/plain', item.id);
                    setDrag(path);
                  }}
                  onDragEnd={finishDrag}
                  onDragOver={(e) => {
                    if (!drag) return;
                    e.preventDefault();
                    const r = e.currentTarget.getBoundingClientRect();
                    const y = (e.clientY - r.top) / r.height;
                    const inside = y > 0.3 && y < 0.7 && path.length < MAX_DEPTH && !['divider', 'heading'].includes(item.type);
                    setDrop({ path, inside, after: y >= 0.5 });
                  }}
                  onDrop={(e) => {
                    e.preventDefault();
                    onDrop();
                  }}
                  onClick={() => setSel(path)}
                  className={cn(
                    'group relative flex h-9 cursor-pointer items-center gap-1.5 rounded-md pr-1 text-sm hover:bg-accent',
                    active && 'bg-brand/10 ring-1 ring-brand/40',
                    drop && samePath(drop.path, path) && drop.inside && 'ring-2 ring-brand'
                  )}
                  style={{ paddingLeft: (path.length - 1) * 20 + 6 }}
                >
                  {drop && samePath(drop.path, path) && !drop.inside && <span className={cn('absolute left-2 right-2 h-0.5 rounded bg-brand', drop.after ? 'bottom-0' : 'top-0')} />}
                  <Icon name="grip-vertical" size={14} className="cursor-grab text-muted-foreground/60" />
                  {hasKids ? (
                    <button
                      type="button"
                      className="text-muted-foreground cursor-pointer"
                      onClick={(e) => {
                        e.stopPropagation();
                        setItems(updateAt(items, path, (it) => ({ ...it, _collapsed: !it._collapsed })));
                      }}
                    >
                      <Icon name="chevron-right" size={14} className={cn('transition-transform', !item._collapsed && 'rotate-90')} />
                    </button>
                  ) : (
                    <span className="w-3.5" />
                  )}
                  {item.icon && <Icon name={item.icon} size={14} className="text-muted-foreground" />}
                  <span className={cn('flex-1 truncate', item.type === 'heading' && 'text-xs font-semibold uppercase tracking-wide text-muted-foreground', item.type === 'divider' && 'text-muted-foreground')}>
                    {item.type === 'divider' ? '— divider —' : stripTags(item.label) || 'Untitled'}
                  </span>
                  {item.badge && <span className="rounded bg-brand/15 px-1 text-[10px] text-brand">{item.badge}</span>}
                  {item.type && !['link', 'heading', 'divider'].includes(item.type) && <span className="rounded bg-muted px-1.5 text-[10px] text-muted-foreground">{item.type}</span>}
                  <span className="hidden items-center group-hover:flex">
                    <IconButton icon="indent-increase" label="Indent" size="icon-sm" onClick={(e) => (e.stopPropagation(), indent(path))} />
                    <IconButton icon="indent-decrease" label="Outdent" size="icon-sm" onClick={(e) => (e.stopPropagation(), outdent(path))} />
                    {path.length < MAX_DEPTH && <IconButton icon="plus" label="Add child" size="icon-sm" onClick={(e) => (e.stopPropagation(), add(path))} />}
                  </span>
                </div>
              );
            })}
          </div>
          <Button size="sm" variant="outline" icon="plus" onClick={() => add([])}>
            Add top-level item
          </Button>
        </div>

        <div className="rounded-lg border border-border p-4">
          {selected ? (
            <ItemEditor
              key={selected.id}
              item={selected}
              depth={sel.length}
              onChange={update}
              actions={
                <div className="flex gap-1">
                  <IconButton icon="chevron-up" label="Move up" size="icon-sm" variant="outline" onClick={() => move(sel, -1)} />
                  <IconButton icon="chevron-down" label="Move down" size="icon-sm" variant="outline" onClick={() => move(sel, 1)} />
                  <IconButton icon="copy" label="Duplicate" size="icon-sm" variant="outline" onClick={() => duplicate(sel)} />
                  <IconButton icon="trash" label="Delete" size="icon-sm" variant="outline" onClick={() => remove(sel)} />
                </div>
              }
            />
          ) : (
            <Empty icon="mouse-pointer-click" title="Select an item to edit it" />
          )}
        </div>
      </div>
    </Dialog>
  );
}

/* ------------------------------------------------------------------------
 * Item settings.
 * ---------------------------------------------------------------------- */

function Row({ label, children }) {
  return (
    <div className="space-y-1.5">
      <Label>{label}</Label>
      {children}
    </div>
  );
}

function ItemEditor({ item, depth, onChange, actions }) {
  const type = item.type || 'link';
  const types = depth === 1 ? TYPES : TYPES.filter((t) => !['mega', 'dropdown', 'button'].includes(t.value));
  const set = (k) => (v) => onChange({ [k]: v === '' ? undefined : v });
  const [lib, setLib] = useState([]);
  useEffect(() => {
    if (type === 'mega' && item.mega_layout === 'layout') loadLibrary().then((r) => setLib(r.items || []));
  }, [type, item.mega_layout]);

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <p className="text-sm font-semibold">{depth === 1 ? 'Top-level item' : depth === 2 ? 'Sub item' : 'Third-level item'}</p>
        {actions}
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Row label="Type">
          <Select value={type} onChange={set('type')} options={types} />
        </Row>
        {type !== 'divider' && (
          <Row label="Label">
            <Input value={item.label || ''} onChange={(e) => onChange({ label: e.target.value })} />
          </Row>
        )}
      </div>

      {!['divider', 'heading'].includes(type) && (
        <Row label="Link">
          <Control field={{ type: 'link', label: 'Link' }} value={item.link} onChange={set('link')} />
        </Row>
      )}

      {type !== 'divider' && (
        <div className="grid grid-cols-2 gap-3">
          <Row label="Icon">
            <Control field={{ type: 'icon', label: 'Icon' }} value={item.icon} onChange={set('icon')} />
          </Row>
          {type === 'button' ? (
            <Row label="Button style">
              <Select value={item.button_variant || 'default'} onChange={set('button_variant')} options={opts({ default: 'Primary', secondary: 'Secondary', outline: 'Outline', ghost: 'Ghost' })} />
            </Row>
          ) : (
            <Row label="Badge">
              <div className="flex gap-1.5">
                <Input value={item.badge || ''} placeholder="New" onChange={(e) => onChange({ badge: e.target.value || undefined })} />
                <Select className="w-28" value={item.badge_variant || 'default'} onChange={set('badge_variant')} options={opts({ default: 'Default', secondary: 'Secondary', outline: 'Outline', success: 'Success', warning: 'Warning', info: 'Info', destructive: 'Red' })} />
              </div>
            </Row>
          )}
        </div>
      )}

      {!['divider', 'button'].includes(type) && (
        <Row label="Description">
          <Input value={item.description || ''} placeholder="Shown under the label in dropdowns and mega menus" onChange={(e) => onChange({ description: e.target.value || undefined })} />
        </Row>
      )}

      {type === 'mega' && (
        <div className="space-y-3 rounded-md bg-muted/60 p-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Mega menu</p>
          <div className="grid grid-cols-3 gap-3">
            <Row label="Layout">
              <Select
                value={item.mega_layout || 'columns'}
                onChange={set('mega_layout')}
                options={opts({ columns: 'Columns of links', grid: 'Icon grid', featured: 'Links + featured card', layout: 'Library design' })}
              />
            </Row>
            <Row label="Columns">
              <Select value={String(item.mega_columns || 3)} onChange={(v) => onChange({ mega_columns: Number(v) })} options={opts({ 2: '2', 3: '3', 4: '4', 5: '5' })} />
            </Row>
            <Row label="Panel width">
              <Select value={item.mega_width || 'auto'} onChange={set('mega_width')} options={opts({ auto: 'Fit content', container: 'Site container', full: 'Full width' })} />
            </Row>
          </div>
          {(item.mega_layout || 'columns') === 'columns' && <p className="text-[11px] text-muted-foreground">Each sub item is a column heading; its children are the links in that column.</p>}
          {item.mega_layout === 'grid' && <p className="text-[11px] text-muted-foreground">Sub items show as cards with icon, label and description.</p>}
          {item.mega_layout === 'layout' && (
            <Row label="Library item">
              <Select value={item.layout_id ? String(item.layout_id) : ''} onChange={(v) => onChange({ layout_id: v ? Number(v) : undefined })} placeholder="Choose a saved design" options={lib.map((l) => ({ value: String(l.id), label: `${l.title} (${l.kind})` }))} />
              <p className="text-[11px] text-muted-foreground">Design the panel with the builder: save a section to the library, then pick it here.</p>
            </Row>
          )}
          {item.mega_layout === 'featured' && <Featured value={item.featured || {}} onChange={(featured) => onChange({ featured })} />}
        </div>
      )}
    </div>
  );
}

function Featured({ value, onChange }) {
  const set = (k) => (v) => onChange({ ...value, [k]: v });
  return (
    <div className="space-y-3 border-t border-border pt-3">
      <p className="text-xs font-medium">Featured card</p>
      <Control field={{ type: 'image', label: 'Image' }} value={value.image} onChange={set('image')} />
      <div className="grid grid-cols-2 gap-3">
        <Row label="Eyebrow">
          <Input value={value.eyebrow || ''} onChange={(e) => set('eyebrow')(e.target.value)} />
        </Row>
        <Row label="Title">
          <Input value={value.title || ''} onChange={(e) => set('title')(e.target.value)} />
        </Row>
      </div>
      <Row label="Text">
        <Input value={value.text || ''} onChange={(e) => set('text')(e.target.value)} />
      </Row>
      <div className="grid grid-cols-2 gap-3">
        <Row label="Button text">
          <Input value={value.button_text || ''} onChange={(e) => set('button_text')(e.target.value)} />
        </Row>
        <Row label="Link">
          <Control field={{ type: 'link', label: 'Link' }} value={value.link} onChange={set('link')} />
        </Row>
      </div>
    </div>
  );
}
