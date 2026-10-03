import { useSyncExternalStore, config } from './wp.js';
import * as T from './tree.js';

const HISTORY_LIMIT = 100;

const listeners = new Set();

let state = {
  post: config.post,
  tree: config.post.tree || [],
  page: config.post.page || {},
  title: config.post.title,
  status: config.post.status,
  conditions: config.post.conditions || [],
  area: config.post.area || null,
  schema: null,
  settings: null,
  selected: null,
  device: 'desktop',
  mode: 'default', // default | hover
  left: null, // modules | layers | library
  modal: null,
  menu: null, // context menu
  dirty: false,
  saving: false,
  past: [],
  future: [],
  clipboard: null,
  styleClipboard: null,
  toasts: [],
  // What the canvas needs to re-render: { ids: [] } or { full: true }.
  change: null,
};

export function getState() {
  return state;
}

export function setState(patch) {
  state = { ...state, ...(typeof patch === 'function' ? patch(state) : patch) };
  listeners.forEach((l) => l());
}

export function subscribe(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function useStore(selector = (s) => s) {
  return useSyncExternalStore(subscribe, () => selector(state));
}

/* ------------------------------------------------------------------------
 * Tree changes with undo history.
 * ---------------------------------------------------------------------- */

/**
 * Apply a tree change.
 * @param {string} label   History label.
 * @param {Array}  tree    New tree.
 * @param {Object} change  { ids: [...] } nodes whose markup changed, or { full: true }.
 * @param {Object} extra   Extra state (e.g. new selection).
 * @param {string} merge   Key: consecutive changes with the same key collapse into one history step.
 */
export function commit(label, tree, change = { full: true }, extra = {}, merge = null) {
  const prev = state;
  const last = prev.past[prev.past.length - 1];
  const past =
    merge && last && last.merge === merge && Date.now() - last.time < 1200
      ? prev.past
      : [...prev.past, { tree: prev.tree, label, merge, time: Date.now(), selected: prev.selected }].slice(-HISTORY_LIMIT);
  if (merge && past === prev.past) last.time = Date.now();
  setState({ tree, past, future: [], dirty: true, change: change ? { ...change, seq: Math.random() } : prev.change, ...extra });
}

export function undo() {
  const { past, future, tree, selected } = state;
  if (!past.length) return;
  const step = past[past.length - 1];
  setState({
    tree: step.tree,
    past: past.slice(0, -1),
    future: [{ tree, label: step.label, selected }, ...future],
    selected: T.find(step.tree, selected) ? selected : step.selected,
    dirty: true,
    change: { full: true, seq: Math.random() },
  });
}

export function redo() {
  const { past, future, tree, selected } = state;
  if (!future.length) return;
  const step = future[0];
  setState({
    tree: step.tree,
    past: [...past, { tree, label: step.label, selected }],
    future: future.slice(1),
    selected: T.find(step.tree, selected) ? selected : step.selected,
    dirty: true,
    change: { full: true, seq: Math.random() },
  });
}

/** Go back to the state before history step `index`. */
export function jumpTo(index) {
  const steps = state.past.length - index;
  for (let i = 0; i < steps; i++) undo();
}

/* ------------------------------------------------------------------------
 * Node actions.
 * ---------------------------------------------------------------------- */

function attrKey(key, field) {
  const { device, mode } = state;
  if (mode === 'hover' && field && field.hover) return `${key}@hover`;
  if (device !== 'desktop' && field && field.responsive) return `${key}@${device}`;
  return key;
}

export { attrKey };

/** Set (or with undefined, clear) an attribute on a node, honouring device/hover mode. */
export function setAttr(id, key, value, field, raw = false) {
  const k = raw ? key : attrKey(key, field);
  const tree = T.update(state.tree, id, (node) => {
    const attrs = { ...(node.attrs || {}) };
    if (value === undefined || value === null || value === '') delete attrs[k];
    else attrs[k] = value;
    return { ...node, attrs };
  });
  commit(`Edit ${key}`, tree, { ids: [id] }, {}, `${id}:${k}`);
}

export function setAttrs(id, patch, label = 'Edit') {
  const tree = T.update(state.tree, id, (node) => {
    const attrs = { ...(node.attrs || {}) };
    for (const [k, v] of Object.entries(patch)) {
      if (v === undefined || v === null || v === '') delete attrs[k];
      else attrs[k] = v;
    }
    return { ...node, attrs };
  });
  commit(label, tree, { ids: [id] });
}

export function replaceNode(id, node, label = 'Edit') {
  commit(label, T.update(state.tree, id, () => node), { ids: [id] });
}

/** Insert nodes; parentId null = root. Selects the first inserted node. */
export function insertNodes(parentId, index, nodes, label = 'Add') {
  if (!parentId) nodes = T.wrapForRoot(nodes);
  const tree = T.insert(state.tree, parentId, index, nodes);
  commit(label, tree, parentId ? { ids: [parentId] } : { full: true }, { selected: nodes[0] ? nodes[0].id : null });
}

export function removeNode(id) {
  const { parent } = T.locate(state.tree, id);
  let tree = T.remove(state.tree, id);
  let changed = parent ? { ids: [parent.id] } : { full: true };
  // A row always keeps at least one column.
  if (parent && parent.type === 'row' && !T.find(tree, parent.id).children.length) {
    tree = T.remove(tree, parent.id);
    changed = { full: true };
  }
  commit('Delete', tree, changed, { selected: parent ? parent.id : null });
}

export function duplicateNode(id) {
  const { parent, index } = T.locate(state.tree, id);
  const node = T.find(state.tree, id);
  if (!node) return;
  const copy = T.clone(node);
  let tree = T.insert(state.tree, parent ? parent.id : null, index + 1, [copy]);
  if (parent && parent.type === 'row') {
    // Keep column structure in step with the number of columns.
    const row = T.find(tree, parent.id);
    tree = T.update(tree, parent.id, () => ({ ...row, attrs: { ...row.attrs, columns: String(row.children.length) } }));
  }
  commit('Duplicate', tree, parent ? { ids: [parent.id] } : { full: true }, { selected: copy.id });
}

export function moveNode(id, parentId, index) {
  const from = T.parentOf(state.tree, id);
  const tree = T.move(state.tree, id, parentId, index);
  if (tree === state.tree) return;
  const ids = [from ? from.id : null, parentId].filter(Boolean);
  const full = !from || !parentId || ids.some((a) => ids.some((b) => a !== b && T.find([T.find(tree, a)], b)));
  commit('Move', tree, full ? { full: true } : { ids: [...new Set(ids)] }, { selected: id });
}

export function select(id) {
  if (state.selected !== id) setState({ selected: id, menu: null });
}

export function copyNode(id) {
  const node = T.find(state.tree, id);
  if (!node) return;
  setState({ clipboard: structuredClone(node) });
  try {
    localStorage.setItem('brik-clipboard', JSON.stringify(node));
  } catch (e) {}
  toast('Copied');
}

export function pasteAfter(id) {
  let node = state.clipboard;
  if (!node) {
    try {
      node = JSON.parse(localStorage.getItem('brik-clipboard') || 'null');
    } catch (e) {}
  }
  if (!node) return;
  const copy = T.clone(node);
  const target = id ? T.find(state.tree, id) : null;

  // Paste where the node type fits: after the target, or inside it, or up the tree.
  const placements = [];
  if (target) {
    const path = [...(T.pathTo(state.tree, id) || []), target];
    for (let i = path.length - 1; i >= 0; i--) {
      const n = path[i];
      if (T.canContain(n.type, copy.type)) {
        placements.push({ parent: n.id, index: (n.children || []).length });
        break;
      }
      const p = path[i - 1] || null;
      if (T.canContain(p ? p.type : null, copy.type)) {
        const list = p ? p.children : state.tree;
        placements.push({ parent: p ? p.id : null, index: list.findIndex((c) => c.id === n.id) + 1 });
        break;
      }
    }
  }
  const spot = placements[0] || { parent: null, index: -1 };
  insertNodes(spot.parent, spot.index, [copy], 'Paste');
}

/** Design-tab attributes of a node (for copy/paste styles and presets). */
export function styleAttrs(node) {
  const schema = state.schema;
  const def = schema && schema.byType[node.type];
  const out = {};
  for (const [k, v] of Object.entries(node.attrs || {})) {
    const base = k.split('@')[0];
    const field = def && def.fields[base];
    if (field && field.tab === 'design') out[k] = v;
  }
  return out;
}

export function copyStyles(id) {
  const node = T.find(state.tree, id);
  if (!node) return;
  setState({ styleClipboard: { type: node.type, attrs: styleAttrs(node) } });
  toast('Styles copied');
}

export function pasteStyles(id) {
  const clip = state.styleClipboard;
  const node = T.find(state.tree, id);
  if (!clip || !node) return;
  const attrs = { ...node.attrs };
  const def = state.schema.byType[node.type];
  for (const [k, v] of Object.entries(clip.attrs)) {
    if (def && def.fields[k.split('@')[0]]) attrs[k] = v;
  }
  replaceNode(id, { ...node, attrs }, 'Paste styles');
}

export function resetStyles(id) {
  const node = T.find(state.tree, id);
  if (!node) return;
  const styles = styleAttrs(node);
  const attrs = {};
  for (const [k, v] of Object.entries(node.attrs || {})) if (!(k in styles)) attrs[k] = v;
  replaceNode(id, { ...node, attrs }, 'Reset styles');
}

/* ------------------------------------------------------------------------
 * UI helpers.
 * ---------------------------------------------------------------------- */

export function toast(message, type = 'info') {
  const id = Math.random();
  setState((s) => ({ toasts: [...s.toasts, { id, message, type }] }));
  setTimeout(() => setState((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })), 2800);
}

export function openModal(type, props = {}) {
  setState({ modal: { type, props }, menu: null });
}

export function closeModal() {
  setState({ modal: null });
}
