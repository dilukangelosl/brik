// DOM helpers shared by the canvas checks. Everything here reads the canvas iframe document.
import { getState } from '../../store.js';
import * as T from '../../tree.js';

export const INTERACTIVE = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [role="button"], [role="link"], [role="tab"], [role="checkbox"], [role="switch"], [role="menuitem"]';

export function canvasFrame() {
  return document.querySelector('.bk-canvas-wrap iframe') || document.querySelector('iframe');
}

export function canvasDoc() {
  const f = canvasFrame();
  try {
    return f && f.contentDocument && f.contentDocument.body ? f.contentDocument : null;
  } catch (e) {
    return null;
  }
}

/** The Brik root of the page being edited (header/footer templates have their own roots). */
export function pageRoot(doc) {
  const id = getState().post.id;
  return doc.querySelector(`[data-brik-root="${id}"]`) || doc.querySelector('[data-brik-root]');
}

/** Builder overlay and other editor-only markup that must never be audited. */
export function isEditorUi(el) {
  return !!(el.closest && el.closest('#brik-ui, .brik-canvas-empty, [data-brik-empty], #brik-audit-flash, #wpadminbar'));
}

/** Id of the tree node an element belongs to, or null (theme markup, templates). */
export function ownerId(el) {
  const owner = el.closest && el.closest('[data-brik-id]');
  if (!owner) return null;
  const id = owner.dataset.brikId;
  return T.find(getState().tree, id) ? id : null;
}

export function ownerNode(el) {
  const id = ownerId(el);
  return id ? T.find(getState().tree, id) : null;
}

export function isHidden(el) {
  if (!el.getClientRects().length) return true;
  const cs = el.ownerDocument.defaultView.getComputedStyle(el);
  return cs.visibility === 'hidden' || cs.visibility === 'collapse';
}

export function ariaHidden(el) {
  return !!el.closest('[aria-hidden="true"]');
}

export function isFocusable(el) {
  if (el.disabled) return false;
  if (el.hasAttribute('tabindex')) return parseInt(el.getAttribute('tabindex'), 10) >= 0;
  const tag = el.tagName.toLowerCase();
  if (tag === 'a') return el.hasAttribute('href');
  if (tag === 'input') return el.type !== 'hidden';
  return ['button', 'select', 'textarea', 'summary', 'iframe'].includes(tag) || el.isContentEditable;
}

function textOf(node, out) {
  for (const c of node.childNodes) {
    if (c.nodeType === 3) out.push(c.nodeValue);
    else if (c.nodeType === 1) {
      const tag = c.tagName.toLowerCase();
      if (c.getAttribute('aria-hidden') === 'true' || tag === 'script' || tag === 'style') continue;
      if (tag === 'img') out.push(' ' + (c.getAttribute('alt') || ''));
      else if (tag === 'svg') {
        const t = c.querySelector('title');
        out.push(' ' + (t ? t.textContent : c.getAttribute('aria-label') || ''));
      } else {
        const cs = c.ownerDocument.defaultView.getComputedStyle(c);
        if (cs.display === 'none' || cs.visibility === 'hidden') {
          // Visually hidden helpers (sr-only) still count; display:none doesn't.
          if (cs.display === 'none') continue;
        }
        textOf(c, out);
      }
    }
  }
  return out;
}

/** Approximate accessible name (aria-labelledby, aria-label, labels, content, alt, title). */
export function accessibleName(el) {
  const doc = el.ownerDocument;
  const by = el.getAttribute('aria-labelledby');
  if (by) {
    const t = by
      .split(/\s+/)
      .map((id) => doc.getElementById(id))
      .filter(Boolean)
      .map((n) => n.textContent)
      .join(' ')
      .trim();
    if (t) return t;
  }
  const label = (el.getAttribute('aria-label') || '').trim();
  if (label) return label;
  const tag = el.tagName.toLowerCase();
  if (['input', 'select', 'textarea'].includes(tag)) {
    if (['submit', 'button', 'reset'].includes(el.type)) return (el.value || '').trim();
    if (el.type === 'image') return (el.alt || '').trim();
    if (el.labels && el.labels.length) return [...el.labels].map((l) => l.textContent).join(' ').trim();
    return (el.getAttribute('title') || '').trim();
  }
  const text = textOf(el, []).join('').replace(/\s+/g, ' ').trim();
  return text || (el.getAttribute('title') || '').trim();
}

/** Short human description of an element for issue details. */
export function describe(el) {
  const tag = el.tagName.toLowerCase();
  const text = (el.textContent || '').replace(/\s+/g, ' ').trim();
  return text ? `<${tag}> “${text.length > 40 ? text.slice(0, 40) + '…' : text}”` : `<${tag}>`;
}

/** Every element under a root that isn't editor UI. */
export function elements(root) {
  return [...root.querySelectorAll('*')].filter((el) => !isEditorUi(el));
}
