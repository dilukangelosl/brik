import * as store from './store.js';
import { save } from './api.js';
import * as T from './tree.js';
import { isEditing } from './canvas.js';

function typing(e) {
  const el = e.target;
  if (!el || !el.tagName) return false;
  return el.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName);
}

export function handleKey(e) {
  const mod = e.metaKey || e.ctrlKey;
  const key = e.key.toLowerCase();
  const s = store.getState();

  if (mod && key === 's') {
    e.preventDefault();
    save();
    return;
  }
  if (typing(e) || isEditing()) return;

  if (mod && key === 'z') {
    e.preventDefault();
    if (e.shiftKey) store.redo();
    else store.undo();
  } else if (mod && key === 'y') {
    e.preventDefault();
    store.redo();
  } else if (mod && e.shiftKey && key === 'l') {
    e.preventDefault();
    store.setState({ left: s.left === 'layers' ? null : 'layers' });
  } else if (mod && e.shiftKey && key === 'a') {
    e.preventDefault();
    store.setState({ left: s.left === 'modules' ? null : 'modules' });
  } else if (!s.selected) {
    return;
  } else if (mod && key === 'c') {
    store.copyNode(s.selected);
  } else if (mod && key === 'v') {
    e.preventDefault();
    store.pasteAfter(s.selected);
  } else if (mod && key === 'd') {
    e.preventDefault();
    store.duplicateNode(s.selected);
  } else if (key === 'delete' || key === 'backspace') {
    e.preventDefault();
    store.removeNode(s.selected);
  } else if (key === 'escape') {
    const parent = T.parentOf(s.tree, s.selected);
    store.select(parent ? parent.id : null);
  }
}
