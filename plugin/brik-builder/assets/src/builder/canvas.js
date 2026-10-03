/**
 * Canvas controller. The canvas is an iframe showing the real page; this module patches
 * its DOM with server-rendered markup, draws selection overlays and handles drag & drop
 * and inline text editing. It runs in the parent window and works on the iframe's document.
 */
import * as store from './store.js';
import * as T from './tree.js';
import { render } from './api.js';
import { iconSvg } from './icons.js';
import { handleKey } from './keys.js';

let frame = null;
let doc = null;
let ui = null;
let inFlight = false;
let queued = null;
let lastChange = null;
let editing = null;

export let dragging = null;

export function setDragging(payload) {
  dragging = payload;
  if (!payload) hideDrop();
}

/* ------------------------------------------------------------------------
 * Setup.
 * ---------------------------------------------------------------------- */

export function attach(iframe) {
  frame = iframe;
  iframe.addEventListener('load', onLoad);
  store.subscribe(onStore);
}

function onLoad() {
  doc = frame.contentDocument;
  if (!doc || !doc.body) return;

  ui = doc.createElement('div');
  ui.id = 'brik-ui';
  ui.innerHTML =
    '<div class="bk-box bk-hover"><span class="bk-tag"></span></div>' +
    '<div class="bk-box bk-select"><div class="bk-bar"></div></div>' +
    '<div class="bk-drop"></div>' +
    '<div class="bk-textbar"></div>';
  doc.body.appendChild(ui);

  doc.addEventListener('mousemove', onMove, { passive: true });
  doc.addEventListener('mouseleave', () => setHover(null));
  doc.addEventListener('click', onClick, true);
  doc.addEventListener('dblclick', onDoubleClick, true);
  doc.addEventListener('contextmenu', onContextMenu);
  doc.addEventListener('dragover', onDragOver);
  doc.addEventListener('drop', onDrop);
  doc.addEventListener('dragleave', (e) => {
    if (!e.relatedTarget) hideDrop();
  });
  doc.addEventListener('submit', (e) => e.preventDefault(), true);
  doc.addEventListener('keydown', handleKey);
  doc.defaultView.addEventListener('scroll', position, { passive: true });
  doc.defaultView.addEventListener('resize', position);
  new doc.defaultView.ResizeObserver(position).observe(doc.body);

  // The canvas loads the saved version; bring it in line with the editor state.
  if (store.getState().dirty || !root()) {
    schedule({ full: true });
  } else {
    position();
  }
  store.setState({ canvasReady: true });
}

function root() {
  return doc && doc.querySelector('[data-brik-root]');
}

export function reload() {
  if (frame) frame.contentWindow.location.reload();
}

export function element(id) {
  return doc ? doc.querySelector(`[data-brik-id="${id}"]`) : null;
}

export function scrollTo(id) {
  const el = element(id);
  if (el) el.scrollIntoView({ block: 'center', behavior: 'smooth' });
}

/* ------------------------------------------------------------------------
 * Rendering.
 * ---------------------------------------------------------------------- */

function onStore() {
  const s = store.getState();
  if (s.change && s.change !== lastChange) {
    lastChange = s.change;
    schedule(s.change);
  }
  position();
}

function schedule(change) {
  if (!queued) queued = { ids: new Set(), full: false };
  if (change.full) queued.full = true;
  (change.ids || []).forEach((id) => queued.ids.add(id));
  if (!inFlight) setTimeout(flush, 60);
}

async function flush() {
  if (inFlight || !queued || !doc) return;
  const job = queued;
  queued = null;
  inFlight = true;
  const tree = store.getState().tree;

  // Re-rendering a node also covers its descendants.
  let ids = [...job.ids].filter((id) => T.find(tree, id));
  ids = ids.filter((id) => !ids.some((other) => other !== id && T.find([T.find(tree, other)], id)));
  const full = job.full || ids.some((id) => !element(id));

  try {
    const res = await render(tree, full ? [] : ids);
    apply(res, full ? null : ids);
  } catch (e) {
    store.toast(e.message || 'Preview failed', 'error');
  }
  inFlight = false;
  if (queued) flush();
}

function apply(res, ids) {
  if (!doc) return;
  const scroll = doc.defaultView.scrollY;

  if (!ids) {
    const holder = doc.createElement('div');
    holder.innerHTML = res.root;
    const fresh = holder.firstElementChild;
    const current = root();
    if (current && fresh) {
      current.innerHTML = fresh.innerHTML;
    } else if (fresh) {
      (doc.querySelector('main, #content, .site-content, .entry-content') || doc.body).prepend(fresh);
    }
  } else {
    for (const id of ids) {
      const el = element(id);
      if (el && res.html[id] !== undefined) el.outerHTML = res.html[id];
    }
  }

  let style = doc.getElementById('brik-css');
  if (!style) {
    style = doc.createElement('style');
    style.id = 'brik-css';
    doc.head.appendChild(style);
  }
  style.textContent = res.css;

  if (res.fonts && !doc.querySelector(`link[href="${res.fonts}"]`)) {
    const link = doc.createElement('link');
    link.rel = 'stylesheet';
    link.href = res.fonts;
    doc.head.appendChild(link);
  }

  const win = doc.defaultView;
  // Effect scripts used by newly added elements (they mount themselves once loaded).
  for (const src of res.scripts || []) {
    if (!doc.querySelector(`script[src^="${src}"]`)) {
      const script = doc.createElement('script');
      script.src = src;
      doc.body.appendChild(script);
    }
  }
  if (win.brik && win.brik.mount) win.brik.mount(root() || doc);
  win.scrollTo(0, scroll);
  position();
}

/** Push fresh token/global CSS after design settings change. */
export function refreshTokens() {
  reload();
}

/* ------------------------------------------------------------------------
 * Overlays.
 * ---------------------------------------------------------------------- */

let hovered = null;

function setHover(el) {
  hovered = el;
  position();
}

function typeClass(type) {
  return T.STRUCTURAL.includes(type) ? `is-${type}` : 'is-module';
}

function place(box, el) {
  if (!el || !el.isConnected) {
    box.style.display = 'none';
    return null;
  }
  const r = el.getBoundingClientRect();
  box.style.display = 'block';
  box.style.top = `${r.top}px`;
  box.style.left = `${r.left}px`;
  box.style.width = `${r.width}px`;
  box.style.height = `${r.height}px`;
  return r;
}

export function position() {
  if (!ui || !doc) return;
  const s = store.getState();
  const hoverBox = ui.querySelector('.bk-hover');
  const selBox = ui.querySelector('.bk-select');

  const selEl = s.selected ? element(s.selected) : null;
  const hovEl = hovered && hovered !== selEl ? hovered : null;

  if (hovEl) {
    hoverBox.className = `bk-box bk-hover ${typeClass(hovEl.dataset.brikType)}`;
    const def = s.schema && s.schema.byType[hovEl.dataset.brikType];
    hoverBox.querySelector('.bk-tag').textContent = def ? def.title : hovEl.dataset.brikType;
  }
  place(hoverBox, hovEl);

  if (selEl) {
    selBox.className = `bk-box bk-select ${typeClass(selEl.dataset.brikType)}`;
    const node = T.find(s.tree, s.selected);
    if (node && selBox.dataset.for !== `${s.selected}:${s.tree.length}`) {
      selBox.dataset.for = `${s.selected}:${s.tree.length}`;
      selBox.querySelector('.bk-bar').innerHTML = toolbar(node, s);
      bindToolbar(selBox, node);
    }
    const r = place(selBox, selEl);
    // Keep the toolbar on screen when the element starts above the viewport.
    selBox.classList.toggle('bk-bar-inside', r && r.top < 30);
  } else {
    selBox.style.display = 'none';
    selBox.dataset.for = '';
  }

  if (editing) placeTextbar();
}

function toolbar(node, s) {
  const def = s.schema && s.schema.byType[node.type];
  const title = node.attrs && node.attrs.admin_label ? node.attrs.admin_label : def ? def.title : node.type;
  const btn = (action, icon, label) => `<button type="button" data-a="${action}" title="${label}">${iconSvg(icon)}</button>`;
  const parent = T.parentOf(s.tree, node.id);
  return (
    `<span class="bk-name" draggable="true" data-a="drag" title="Drag to move">${iconSvg('grip-vertical')}${escapeHtml(title)}</span>` +
    (parent ? btn('parent', 'arrow-up-left', 'Select parent') : '') +
    btn('add', 'plus', node.type === 'section' ? 'Add section below' : node.type === 'row' ? 'Add row below' : 'Add element below') +
    btn('duplicate', 'copy', 'Duplicate') +
    btn('save', 'library', 'Save to library') +
    btn('delete', 'trash-2', 'Delete')
  );
}

function bindToolbar(box, node) {
  box.querySelectorAll('[data-a]').forEach((b) => {
    const action = b.dataset.a;
    if (action === 'drag') {
      b.addEventListener('dragstart', (e) => {
        e.dataTransfer.setData('text/plain', node.id);
        e.dataTransfer.effectAllowed = 'move';
        setDragging({ move: node.id, type: node.type });
      });
      b.addEventListener('dragend', () => setDragging(null));
      return;
    }
    b.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const s = store.getState();
      const id = node.id;
      if (action === 'parent') {
        const p = T.parentOf(s.tree, id);
        if (p) store.select(p.id);
      } else if (action === 'add') {
        addAfter(id);
      } else if (action === 'duplicate') {
        store.duplicateNode(id);
      } else if (action === 'delete') {
        store.removeNode(id);
      } else if (action === 'save') {
        store.openModal('save-library', { id });
      }
    });
  });
}

/** "+" on an element: open the right picker for what can go after it. */
export function addAfter(id) {
  const s = store.getState();
  const node = T.find(s.tree, id);
  const { parent, index } = T.locate(s.tree, id);
  if (node.type === 'section') {
    store.openModal('add-section', { parent: null, index: index + 1 });
  } else if (node.type === 'row') {
    store.openModal('structure', { parent: parent ? parent.id : null, index: index + 1 });
  } else if (node.type === 'column') {
    store.openModal('modules', { parent: node.id, index: (node.children || []).length });
  } else {
    store.openModal('modules', { parent: parent.id, index: index + 1 });
  }
}

/* ------------------------------------------------------------------------
 * Pointer interaction.
 * ---------------------------------------------------------------------- */

function onMove(e) {
  if (dragging) return;
  if (ui.contains(e.target)) return;
  const el = e.target.closest && e.target.closest('[data-brik-id]');
  if (el !== hovered) setHover(el);
}

function onClick(e) {
  if (ui.contains(e.target)) return;
  const empty = e.target.closest('[data-brik-empty]');
  if (empty) {
    e.preventDefault();
    const target = empty.dataset.brikEmpty;
    if (target === 'root') store.openModal('add-section', { parent: null, index: 0 });
    else store.openModal('modules', { parent: target, index: 0 });
    return;
  }

  // Module controls (tabs, lightbox, modal triggers…) handle their own clicks.
  const interactive = e.target.closest('summary, [role="tab"], [data-brik-modal], [data-brik-lightbox], .brik-carousel-arrow, .brik-carousel-dot, .brik-video-facade, .brik-compare, [data-brik-menu-toggle], .brik-menu-trigger');
  if (!interactive && e.target.closest('a[href], [type="submit"]')) e.preventDefault();
  if (editing && editing.el.contains(e.target)) return;

  const el = e.target.closest('[data-brik-id]');
  stopEditing();
  if (el) store.select(el.dataset.brikId);
  else store.select(null);
}

function onContextMenu(e) {
  const el = e.target.closest('[data-brik-id]');
  if (!el || (editing && editing.el.contains(e.target))) return;
  e.preventDefault();
  const rect = frame.getBoundingClientRect();
  store.select(el.dataset.brikId);
  store.setState({ menu: { x: rect.left + e.clientX, y: rect.top + e.clientY, id: el.dataset.brikId } });
}

/* ------------------------------------------------------------------------
 * Inline text editing.
 * ---------------------------------------------------------------------- */

function onDoubleClick(e) {
  const target = e.target.closest('[data-brik-inline]');
  if (!target) return;
  const owner = target.closest('[data-brik-id]');
  if (!owner) return;
  e.preventDefault();
  startEditing(owner.dataset.brikId, target);
}

function startEditing(id, el) {
  stopEditing();
  const s = store.getState();
  const node = T.find(s.tree, id);
  const def = s.schema.byType[node.type];
  const key = el.dataset.brikInline;
  const field = def && def.fields[key];
  if (!field) return;

  store.select(id);
  el.contentEditable = 'true';
  el.classList.add('bk-editing');
  el.focus();
  const range = doc.createRange();
  range.selectNodeContents(el);
  range.collapse(false);
  const sel = doc.getSelection();
  sel.removeAllRanges();
  sel.addRange(range);

  editing = { id, el, key, field, rich: field.type === 'richtext' };
  el.addEventListener('input', onInput);
  el.addEventListener('blur', onBlur);
  el.addEventListener('keydown', onEditKey);
  showTextbar();
}

let inputTimer = null;

function onInput() {
  clearTimeout(inputTimer);
  inputTimer = setTimeout(syncEditing, 250);
}

function syncEditing() {
  if (!editing) return;
  const s = store.getState();
  const value = cleanInline(editing.el.innerHTML, editing.rich);
  const k = store.attrKey(editing.key, editing.field);
  const tree = T.update(s.tree, editing.id, (node) => ({ ...node, attrs: { ...node.attrs, [k]: value } }));
  // No canvas change: the text is already on screen.
  store.commit('Edit text', tree, null, {}, `${editing.id}:${k}`);
}

function onBlur(e) {
  if (ui.contains(e.relatedTarget)) return;
  stopEditing();
}

function onEditKey(e) {
  if (e.key === 'Escape') {
    e.preventDefault();
    stopEditing();
  } else if (e.key === 'Enter' && editing && !editing.rich && !e.shiftKey) {
    e.preventDefault();
    stopEditing();
  }
  e.stopPropagation();
}

export function stopEditing() {
  if (!editing) return;
  clearTimeout(inputTimer);
  syncEditing();
  const { el, id } = editing;
  el.removeAttribute('contenteditable');
  el.classList.remove('bk-editing');
  el.removeEventListener('input', onInput);
  el.removeEventListener('blur', onBlur);
  el.removeEventListener('keydown', onEditKey);
  editing = null;
  ui.querySelector('.bk-textbar').style.display = 'none';
  // Re-render so the stored value and the markup agree.
  store.setState({ change: { ids: [id], seq: Math.random() } });
}

export function isEditing() {
  return !!editing;
}

function cleanInline(html, rich) {
  let out = html.replace(/\s*contenteditable="[^"]*"/g, '').replace(/<(\/?)b>/g, '<$1strong>').replace(/<(\/?)i>/g, '<$1em>');
  if (!rich) out = out.replace(/<\/?(div|p)[^>]*>/g, '').replace(/(<br>)+$/, '');
  return out.trim();
}

function showTextbar() {
  const bar = ui.querySelector('.bk-textbar');
  const cmds = [
    ['bold', 'bold'],
    ['italic', 'italic'],
    ['underline', 'underline'],
    ['strikeThrough', 'strikethrough'],
    ['link', 'link'],
  ];
  if (editing.rich) {
    cmds.push(['h2', 'heading-2'], ['h3', 'heading-3'], ['p', 'pilcrow'], ['insertUnorderedList', 'list'], ['insertOrderedList', 'list-ordered'], ['blockquote', 'quote']);
  }
  cmds.push(['removeFormat', 'remove-formatting']);
  bar.innerHTML = cmds.map(([c, icon]) => `<button type="button" data-cmd="${c}" title="${c}">${iconSvg(icon)}</button>`).join('');
  bar.querySelectorAll('button').forEach((b) =>
    b.addEventListener('mousedown', (e) => {
      e.preventDefault();
      const cmd = b.dataset.cmd;
      if (cmd === 'link') {
        const url = doc.defaultView.prompt('Link URL', 'https://');
        if (url) doc.execCommand('createLink', false, url);
      } else if (['h2', 'h3', 'p', 'blockquote'].includes(cmd)) {
        doc.execCommand('formatBlock', false, cmd);
      } else {
        doc.execCommand(cmd, false, null);
      }
      onInput();
    })
  );
  bar.style.display = 'flex';
  placeTextbar();
}

function placeTextbar() {
  const bar = ui.querySelector('.bk-textbar');
  const r = editing.el.getBoundingClientRect();
  bar.style.top = `${Math.max(4, r.top - 44)}px`;
  bar.style.left = `${Math.max(4, r.left)}px`;
}

/* ------------------------------------------------------------------------
 * Drag & drop.
 * ---------------------------------------------------------------------- */

/**
 * Work out where a dragged node would land for a pointer position.
 * Returns { parent: id|null, index, rect, axis } or null.
 */
function dropTarget(e) {
  const s = store.getState();
  const type = dragging.type;
  const movingId = dragging.move;

  const empty = e.target.closest && e.target.closest('[data-brik-empty]');
  if (empty) {
    const pid = empty.dataset.brikEmpty === 'root' ? null : empty.dataset.brikEmpty;
    const ptype = pid ? (T.find(s.tree, pid) || {}).type : null;
    if (T.canContain(ptype || null, type) && pid !== movingId) {
      return { parent: pid, index: 0, rect: empty.getBoundingClientRect(), axis: 'fill' };
    }
  }

  let el = e.target.closest && e.target.closest('[data-brik-id]');
  while (el) {
    const id = el.dataset.brikId;
    const node = T.find(s.tree, id);
    if (!node) break;
    if (movingId && (id === movingId || T.find([T.find(s.tree, movingId)], id))) {
      el = el.parentElement && el.parentElement.closest('[data-brik-id]');
      continue;
    }

    if (T.canContain(node.type, type)) {
      const kids = [...el.querySelectorAll(':scope > [data-brik-id]')];
      if (!kids.length) return { parent: id, index: 0, rect: el.getBoundingClientRect(), axis: 'fill' };
      return between(kids, node, e, id);
    }

    const parent = T.parentOf(s.tree, id);
    if (T.canContain(parent ? parent.type : null, type)) {
      const r = el.getBoundingClientRect();
      const horizontal = isHorizontal(el.parentElement, node);
      const after = horizontal ? e.clientX > r.left + r.width / 2 : e.clientY > r.top + r.height / 2;
      const { index } = T.locate(s.tree, id);
      return { parent: parent ? parent.id : null, index: index + (after ? 1 : 0), rect: r, axis: horizontal ? 'x' : 'y', after };
    }
    el = el.parentElement && el.parentElement.closest('[data-brik-id]');
  }

  // Below all content: append sections to the end.
  if (type === 'section' || type === 'global' || !dragging.move) {
    const rootEl = root();
    if (rootEl) {
      const r = rootEl.getBoundingClientRect();
      if (e.clientY > r.bottom - 40) return { parent: null, index: s.tree.length, rect: r, axis: 'y', after: true, wrap: true };
    }
  }
  return null;
}

function isHorizontal(container, node) {
  if (node.type === 'column') return true;
  if (!container) return false;
  const style = container.ownerDocument.defaultView.getComputedStyle(container);
  return style.display.includes('flex') && style.flexDirection.startsWith('row');
}

function between(kids, parentNode, e, parentId) {
  const horizontal = isHorizontal(kids[0].parentElement, (parentNode.children || [])[0] || {});
  let index = kids.length;
  let rect = kids[kids.length - 1].getBoundingClientRect();
  let after = true;
  for (let i = 0; i < kids.length; i++) {
    const r = kids[i].getBoundingClientRect();
    const mid = horizontal ? r.left + r.width / 2 : r.top + r.height / 2;
    if ((horizontal ? e.clientX : e.clientY) < mid) {
      index = i;
      rect = r;
      after = false;
      break;
    }
  }
  return { parent: parentId, index, rect, axis: horizontal ? 'x' : 'y', after };
}

let currentDrop = null;

function onDragOver(e) {
  if (!dragging) return;
  // Non-module payloads (layouts) are wrapped for the spot they land in.
  const target = dropTarget(e);
  currentDrop = target;
  if (!target) {
    hideDrop();
    return;
  }
  e.preventDefault();
  e.dataTransfer.dropEffect = dragging.move ? 'move' : 'copy';
  showDrop(target);
}

function onDrop(e) {
  if (!dragging) return;
  e.preventDefault();
  const target = currentDrop || dropTarget(e);
  const payload = dragging;
  setDragging(null);
  if (!target) return;
  performDrop(payload, target.parent, target.index);
}

export function performDrop(payload, parent, index) {
  if (payload.move) {
    store.moveNode(payload.move, parent, index);
  } else if (payload.nodes) {
    store.insertNodes(parent, index, T.cloneAll(payload.nodes), 'Insert layout');
  } else if (payload.type) {
    store.insertNodes(parent, index, [T.create(payload.type)], 'Add element');
  }
}

function showDrop(t) {
  const line = ui.querySelector('.bk-drop');
  const r = t.rect;
  line.style.display = 'block';
  line.className = `bk-drop is-${t.axis}`;
  if (t.axis === 'fill') {
    Object.assign(line.style, { top: `${r.top}px`, left: `${r.left}px`, width: `${r.width}px`, height: `${r.height}px` });
  } else if (t.axis === 'x') {
    const x = t.after ? r.right : r.left;
    Object.assign(line.style, { top: `${r.top}px`, left: `${x - 2}px`, width: '4px', height: `${r.height}px` });
  } else {
    const y = t.after ? r.bottom : r.top;
    Object.assign(line.style, { top: `${y - 2}px`, left: `${r.left}px`, width: `${r.width}px`, height: '4px' });
  }
}

function hideDrop() {
  currentDrop = null;
  if (ui) ui.querySelector('.bk-drop').style.display = 'none';
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
}
