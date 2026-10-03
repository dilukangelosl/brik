// Pure helpers for Brik trees. Nodes: { id, type, attrs, children? }.

export const STRUCTURAL = ['section', 'row', 'column'];

export function uid() {
  return Math.random().toString(36).slice(2, 10).padEnd(8, '0');
}

export function walk(nodes, fn, parent = null) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (fn(node, parent, i) === false) return false;
    if (node.children && walk(node.children, fn, node) === false) return false;
  }
  return true;
}

export function find(nodes, id) {
  let found = null;
  walk(nodes, (node) => {
    if (node.id === id) {
      found = node;
      return false;
    }
  });
  return found;
}

/** Ancestors from the root down to (not including) the node. */
export function pathTo(nodes, id, trail = []) {
  for (const node of nodes) {
    if (node.id === id) return trail;
    if (node.children) {
      const found = pathTo(node.children, id, [...trail, node]);
      if (found) return found;
    }
  }
  return null;
}

export function parentOf(nodes, id) {
  const path = pathTo(nodes, id);
  return path && path.length ? path[path.length - 1] : null;
}

/** Siblings list and index of a node. */
export function locate(nodes, id) {
  const parent = parentOf(nodes, id);
  const list = parent ? parent.children : nodes;
  return { parent, list, index: list.findIndex((n) => n.id === id) };
}

/** Immutable update of one node. */
export function update(nodes, id, fn) {
  return nodes.map((node) => {
    if (node.id === id) return fn(node);
    if (node.children) {
      const children = update(node.children, id, fn);
      if (children !== node.children) return { ...node, children };
    }
    return node;
  });
}

export function remove(nodes, id) {
  const out = [];
  for (const node of nodes) {
    if (node.id === id) continue;
    out.push(node.children ? { ...node, children: remove(node.children, id) } : node);
  }
  return out;
}

export function insert(nodes, parentId, index, newNodes) {
  const add = (list) => {
    const copy = list.slice();
    copy.splice(index < 0 || index > copy.length ? copy.length : index, 0, ...newNodes);
    return copy;
  };
  if (!parentId) return add(nodes);
  return update(nodes, parentId, (node) => ({ ...node, children: add(node.children || []) }));
}

export function move(nodes, id, parentId, index) {
  const node = find(nodes, id);
  if (!node) return nodes;
  // Can't move a node into itself.
  if (parentId && (parentId === id || find([node], parentId))) return nodes;

  const from = locate(nodes, id);
  let target = index;
  if ((from.parent ? from.parent.id : null) === (parentId || null) && from.index < index) target--;
  return insert(remove(nodes, id), parentId, target, [node]);
}

/** Deep copy with fresh ids. */
export function clone(node) {
  const copy = { ...node, id: uid(), attrs: structuredClone(node.attrs || {}) };
  if (node.children) copy.children = node.children.map(clone);
  return copy;
}

export function cloneAll(nodes) {
  return nodes.map(clone);
}

/** Which parent types can hold a node type. null = the root list. */
export function allowedParents(type) {
  if (type === 'section') return [null];
  if (type === 'row') return ['section', 'column'];
  if (type === 'column') return ['row'];
  return ['column'];
}

export function canContain(parentType, childType) {
  return allowedParents(childType).includes(parentType);
}

/** Build a new node with sensible structure. */
export function create(type, attrs = {}) {
  const node = { id: uid(), type, attrs: { ...attrs } };
  if (type === 'section') {
    node.children = [create('row')];
  } else if (type === 'row') {
    const count = columnCount(attrs.columns || '1');
    node.children = Array.from({ length: count }, () => create('column'));
  } else if (type === 'column') {
    node.children = [];
  }
  return node;
}

export function columnCount(structure) {
  const s = String(structure || '1').trim();
  if (/^\d+$/.test(s)) return Math.max(1, parseInt(s, 10));
  return s.split(',').filter(Boolean).length || 1;
}

/**
 * Change a row's structure, adding or merging columns so content is kept.
 */
export function restructure(row, structure) {
  const count = columnCount(structure);
  let children = (row.children || []).slice();
  if (children.length < count) {
    children = children.concat(Array.from({ length: count - children.length }, () => create('column')));
  } else if (children.length > count) {
    const extra = children.slice(count);
    const last = { ...children[count - 1] };
    last.children = (last.children || []).concat(...extra.map((c) => c.children || []));
    children = children.slice(0, count - 1).concat(last);
  }
  return { ...row, attrs: { ...row.attrs, columns: structure }, children };
}

/** Wrap loose nodes so they can live at the root. */
export function wrapForRoot(nodes) {
  return nodes.map((n) => {
    if (n.type === 'section' || n.type === 'global') return n;
    if (n.type === 'row') return { ...create('section'), children: [n] };
    if (n.type === 'column') return { ...create('section'), children: [{ ...create('row'), children: [n] }] };
    const section = create('section');
    section.children[0].children[0].children = [n];
    return section;
  });
}

export function label(node, schema) {
  if (node.attrs && node.attrs.admin_label) return node.attrs.admin_label;
  const def = schema && schema.byType[node.type];
  return def ? def.title : node.type;
}

/** Short text preview of a node for the layers panel. */
export function preview(node) {
  const a = node.attrs || {};
  const text = a.text || a.title || a.content || a.name || '';
  return String(text)
    .replace(/<[^>]+>/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 40);
}
