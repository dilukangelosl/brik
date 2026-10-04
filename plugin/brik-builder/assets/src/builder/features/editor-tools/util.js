// Shared helpers for the editor tools: canvas access, responsive values, fluid math, colours.
import { useSyncExternalStore } from '../../wp.js';
import * as store from '../../store.js';

export const DEVICES = [
  { id: 'desktop', icon: 'monitor', label: 'Desktop', short: 'D' },
  { id: 'tablet', icon: 'tablet', label: 'Tablet', short: 'T' },
  { id: 'mobile', icon: 'smartphone', label: 'Mobile', short: 'M' },
];

export const BREAKPOINTS = { tablet: 980, mobile: 767 };

/* ------------------------------------------------------------------------
 * Canvas.
 * ---------------------------------------------------------------------- */

export function frame() {
  return document.querySelector('.bk-canvas-wrap iframe') || document.querySelector('iframe');
}

export function frameDoc() {
  const f = frame();
  try {
    return f && f.contentDocument && f.contentDocument.body ? f.contentDocument : null;
  } catch (e) {
    return null;
  }
}

export function nodeEl(id) {
  const doc = frameDoc();
  return doc && id ? doc.querySelector(`[data-brik-id="${CSS.escape(id)}"]`) : null;
}

/*
 * A counter that changes whenever the canvas re-renders (new markup or new generated CSS),
 * its document reloads or its size changes. Components read it to refresh measurements.
 */
let version = 0;
const listeners = new Set();
let watchedDoc = null;
let observer = null;
let resizeObs = null;
let pending = false;

function bump() {
  if (pending) return;
  pending = true;
  // Animated modules mutate the DOM constantly; a short delay keeps re-measuring cheap.
  setTimeout(() => {
    pending = false;
    version++;
    listeners.forEach((l) => l());
  }, 120);
}

function watch() {
  const doc = frameDoc();
  if (!doc || doc === watchedDoc) return;
  watchedDoc = doc;
  if (observer) observer.disconnect();
  if (resizeObs) resizeObs.disconnect();
  observer = new MutationObserver((records) => {
    // Our own overlay and forced-state classes don't count as renders.
    if (records.every((r) => r.target.closest && r.target.closest('#brik-ui'))) return;
    bump();
  });
  observer.observe(doc.head, { childList: true, subtree: true, characterData: true });
  observer.observe(doc.body, { childList: true, subtree: true });
  resizeObs = new doc.defaultView.ResizeObserver(bump);
  resizeObs.observe(doc.documentElement);
  bump();
}

store.subscribe(() => {
  watch();
});

export function useCanvasVersion() {
  return useSyncExternalStore(
    (l) => {
      watch();
      listeners.add(l);
      return () => listeners.delete(l);
    },
    () => version
  );
}

export function onCanvasChange(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

/* ------------------------------------------------------------------------
 * Responsive values.
 * ---------------------------------------------------------------------- */

export function own(attrs, key, device) {
  const v = (attrs || {})[device === 'desktop' ? key : `${key}@${device}`];
  return v === undefined || v === null || v === '' ? undefined : v;
}

/** Value used on a device and the device it comes from. */
export function resolve(attrs, key, device) {
  const chain = device === 'mobile' ? ['mobile', 'tablet', 'desktop'] : device === 'tablet' ? ['tablet', 'desktop'] : ['desktop'];
  for (const d of chain) {
    const v = own(attrs, key, d);
    if (v !== undefined) return { value: v, from: d };
  }
  return { value: undefined, from: null };
}

export function setDevice(device) {
  store.setState({ device });
}

export function shortValue(v) {
  if (v === undefined) return '—';
  if (Array.isArray(v)) return `${v.length}×`;
  if (typeof v === 'object') return v.url ? 'image' : '•';
  const s = String(v);
  const fluid = parseClamp(s);
  if (fluid) return `${fmt(fluid.min)}↔${fmt(fluid.max)}`;
  return s;
}

/* ------------------------------------------------------------------------
 * Fluid values.
 * ---------------------------------------------------------------------- */

export const FLUID_MIN = 390;
export const FLUID_MAX = 1440;

export function fmt(n) {
  const s = (Math.round(n * 10000) / 10000).toFixed(4).replace(/\.?0+$/, '');
  return s === '-0' ? '0' : s;
}

/** clamp() equal to `a` px at 390px and `b` px at 1440px, linear in between. */
export function clamp(a, b, w1 = FLUID_MIN, w2 = FLUID_MAX) {
  a = Number(a);
  b = Number(b);
  if (Math.abs(a - b) < 0.0001) return `${fmt(a)}px`;
  const slope = (b - a) / (w2 - w1);
  const intercept = a - slope * w1;
  const vw = slope * 100;
  return `clamp(${fmt(Math.min(a, b))}px, calc(${fmt(intercept)}px ${vw < 0 ? '-' : '+'} ${fmt(Math.abs(vw))}vw), ${fmt(Math.max(a, b))}px)`;
}

const CLAMP_RE = /^clamp\(\s*(-?[\d.]+)px\s*,\s*calc\(\s*(-?[\d.]+)px\s*([+-])\s*(-?[\d.]+)vw\s*\)\s*,\s*(-?[\d.]+)px\s*\)$/;

/** Parse a clamp() written by clamp(): { min, max (values at 390/1440), at(width) }. */
export function parseClamp(value) {
  const m = typeof value === 'string' && value.trim().match(CLAMP_RE);
  if (!m) return null;
  const lo = +m[1];
  const hi = +m[5];
  const inter = +m[2];
  const vw = +m[4] * (m[3] === '-' ? -1 : 1);
  const at = (w) => Math.max(lo, Math.min(hi, inter + (vw * w) / 100));
  return { min: Math.round(at(FLUID_MIN) * 100) / 100, max: Math.round(at(FLUID_MAX) * 100) / 100, at, lo, hi, inter, vw };
}

/** A CSS length in px, or null (%, auto, var()…). Clamp values give their 1440px value. */
export function toPx(value) {
  if (value === undefined || value === null) return null;
  const s = String(value).trim();
  let m = s.match(/^(-?[\d.]+)(px)?$/);
  if (m) return +m[1];
  m = s.match(/^(-?[\d.]+)r?em$/);
  if (m) return +m[1] * 16;
  const c = parseClamp(s);
  return c ? c.max : null;
}

/* ------------------------------------------------------------------------
 * Colours.
 * ---------------------------------------------------------------------- */

let ctx2d = null;

/** Any CSS colour (oklch, color-mix, named…) to [r, g, b, a] using the browser's parser. */
export function rgba(color) {
  if (!color) return null;
  const m = String(color).match(/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/);
  if (m) {
    let a = m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : +m[4];
    return [+m[1], +m[2], +m[3], a];
  }
  if (!ctx2d) {
    const c = document.createElement('canvas');
    c.width = c.height = 1;
    ctx2d = c.getContext('2d', { willReadFrequently: true });
  }
  ctx2d.clearRect(0, 0, 1, 1);
  ctx2d.fillStyle = '#00000000';
  ctx2d.fillStyle = color;
  ctx2d.fillRect(0, 0, 1, 1);
  const d = ctx2d.getImageData(0, 0, 1, 1).data;
  return [d[0], d[1], d[2], d[3] / 255];
}

function lum([r, g, b]) {
  const f = (c) => {
    c /= 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}

export function contrast(a, b) {
  const l1 = lum(a);
  const l2 = lum(b);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

/** Composite a translucent colour over another. */
export function over(top, bottom) {
  const a = top[3];
  return [0, 1, 2].map((i) => top[i] * a + bottom[i] * (1 - a)).concat(1);
}

/** The colour actually behind an element: first opaque background up the tree. */
export function backgroundOf(el) {
  const layers = [];
  for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
    const cs = n.ownerDocument.defaultView.getComputedStyle(n);
    if (cs.backgroundImage && cs.backgroundImage !== 'none') return { color: null, image: true, el: n };
    const c = rgba(cs.backgroundColor);
    if (c && c[3] > 0) {
      layers.push(c);
      if (c[3] >= 0.99) break;
    }
  }
  let out = [255, 255, 255, 1];
  for (let i = layers.length - 1; i >= 0; i--) out = over(layers[i], out);
  return { color: out, image: false };
}

/* ------------------------------------------------------------------------
 * Misc.
 * ---------------------------------------------------------------------- */

export function injectStyle(id, css) {
  let el = document.getElementById(id);
  if (!el) {
    el = document.createElement('style');
    el.id = id;
    document.head.appendChild(el);
  }
  el.textContent = css;
}

export async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
  } catch (e) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    ta.remove();
  }
  store.toast('Copied to clipboard', 'success');
}

export function escapeHtml(s) {
  return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
}

/** Label of a node for messages: admin label or module title. */
export function nodeLabel(node) {
  if (!node) return '';
  const s = store.getState();
  const def = s.schema && s.schema.byType[node.type];
  return (node.attrs && node.attrs.admin_label) || (def ? def.title : node.type);
}

export function throttleRaf(fn) {
  let queued = null;
  return (...args) => {
    const first = queued === null;
    queued = args;
    if (first) {
      requestAnimationFrame(() => {
        const a = queued;
        queued = null;
        fn(...a);
      });
    }
  };
}
