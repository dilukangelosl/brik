// Responsive problem finder: resizes the canvas through common widths and measures the layout.
import { getState, setState, subscribe } from '../../store.js';
import * as T from '../../tree.js';
import { canvasFrame, canvasDoc, pageRoot, ownerId, isEditorUi, isHidden, INTERACTIVE, describe } from './dom.js';
import { finding } from './a11y.js';

export const WIDTHS = [1440, 1200, 1024, 820, 768, 600, 390, 360];
const TOUCH = 820;

const frame = () => new Promise((r) => requestAnimationFrame(() => r()));
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

/*
 * Widths wider than the canvas area: the iframe is a flex item and would shrink to fit, so it is
 * pinned at full width and scaled down to stay visible. Only properties the builder's canvas
 * component never sets are used (flex-shrink, scale, translate, min-height), so nothing it renders
 * is overwritten. Undone as soon as the view changes.
 */
let fitted = null;
let watching = false;

function fit(width) {
  const f = canvasFrame();
  if (!f || !f.parentElement) return;
  const wrap = f.parentElement;
  f.style.flexShrink = '0';
  if (width > wrap.clientWidth) {
    const scale = wrap.clientWidth / width;
    const tall = wrap.clientHeight / scale;
    f.style.minHeight = `${tall}px`;
    f.style.scale = String(scale);
    f.style.translate = `0 ${-(tall - wrap.clientHeight) / 2}px`;
  } else {
    Object.assign(f.style, { minHeight: '', scale: '', translate: '' });
  }
  fitted = width;
  if (!watching) {
    watching = true;
    subscribe(() => {
      const s = getState();
      if (fitted && (s.device !== 'custom' || s.canvasWidth !== fitted)) unfit();
    });
  }
}

export function unfit() {
  const f = canvasFrame();
  if (f) Object.assign(f.style, { flexShrink: '', minHeight: '', scale: '', translate: '' });
  fitted = null;
}

/** Put the canvas at a width and wait until the iframe has laid out at that size. */
export async function setWidth(width) {
  fitted = null;
  setState({ device: 'custom', canvasWidth: width });
  await frame();
  await frame();
  // Only step in when the canvas couldn't give the iframe the full width itself.
  const first = canvasDoc();
  if (first && Math.abs(first.documentElement.clientWidth - width) > 20) fit(width);
  for (let i = 0; i < 40; i++) {
    await frame();
    const doc = canvasDoc();
    if (doc && Math.abs(doc.documentElement.clientWidth - width) <= 20) break;
    await wait(25);
  }
  await wait(180); // let transitions and responsive scripts settle
}

/** Px value of a length attribute if it is a fixed px size. */
function px(value) {
  if (typeof value === 'number') return value;
  const m = String(value || '').trim().match(/^(\d+(?:\.\d+)?)(px)?$/);
  return m ? parseFloat(m[1]) : null;
}

/** Attribute value active at a canvas width (desktop / @tablet ≤980 / @mobile ≤767). */
function attrAt(attrs, key, width) {
  if (width <= 767 && attrs[`${key}@mobile`] !== undefined) return attrs[`${key}@mobile`];
  if (width <= 980 && attrs[`${key}@tablet`] !== undefined) return attrs[`${key}@tablet`];
  return attrs[key];
}

function fixedWidth(el, width) {
  const id = ownerId(el);
  const node = id && T.find(getState().tree, id);
  if (node && node.attrs) {
    for (const key of ['width', 'min_width', 'img_width']) {
      const v = px(attrAt(node.attrs, key, width));
      if (v && v > width * 0.5) return { px: v, attr: key };
    }
  }
  for (const prop of ['width', 'minWidth']) {
    const v = px(el.style[prop]);
    if (v && v > width * 0.5) return { px: v };
  }
  return null;
}

/** Screen-reader-only helpers: 1px boxes, clipped to nothing. */
function visuallyHidden(el, s, r) {
  if (r.width <= 2 || r.height <= 2) return true;
  const clip = s.clip || '';
  return /rect\(\s*0(px)?[\s,]+0(px)?[\s,]+0(px)?[\s,]+0(px)?\s*\)/.test(clip) || /inset\(\s*50%/.test(s.clipPath || '') || el.closest('.sr-only, .screen-reader-text, .visually-hidden') !== null;
}

/** Elements that stick out of the viewport and aren't clipped by an ancestor. */
function culprits(doc, root, width) {
  const win = doc.defaultView;
  const styles = new Map();
  const cs = (el) => {
    let s = styles.get(el);
    if (!s) {
      s = win.getComputedStyle(el);
      styles.set(el, s);
    }
    return s;
  };
  const clipped = (el) => {
    // Clipping inside the page (carousels, marquees, sections set to hidden) is intentional;
    // the theme's page wrappers clipping it just means the content is cut off.
    for (let p = el.parentElement; p && p !== root.parentElement; p = p.parentElement) {
      const s = cs(p);
      if (s.position === 'fixed') return true;
      if (/(hidden|clip|auto|scroll)/.test(s.overflowX) || /(hidden|clip)/.test(s.overflow)) {
        const r = p.getBoundingClientRect();
        if (r.right <= width + 1 && r.left >= -1) return true;
      }
    }
    return false;
  };
  const positionedAway = (el) => {
    for (let p = el; p && p !== root; p = p.parentElement) if (cs(p).position === 'absolute') return true;
    return false;
  };
  const found = [];
  for (const el of root.querySelectorAll('*')) {
    if (isEditorUi(el) || (el.closest('svg') !== null && el.tagName.toLowerCase() !== 'svg')) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;
    if (r.right <= width + 1 && r.left >= -1) continue;
    const s = cs(el);
    if (s.position === 'fixed' || s.visibility === 'hidden' || parseFloat(s.opacity) === 0) continue;
    // Moved fully off screen on purpose (honeypots, skip links, visually hidden text).
    if ((r.right <= 0 || r.left >= width) && positionedAway(el)) continue;
    if (visuallyHidden(el, s, r)) continue;
    if (clipped(el)) continue;
    found.push(el);
  }
  // Keep the outermost element of each overflowing branch.
  return found.filter((el) => !found.some((o) => o !== el && o.contains(el)));
}

function tooSmall(el, width) {
  if (width > TOUCH) return null;
  const r = el.getBoundingClientRect();
  if (r.width < 1 || r.height < 1) return null;
  // Links inside running text are exempt (WCAG 2.5.8 inline exception).
  const s = el.ownerDocument.defaultView.getComputedStyle(el);
  if (s.display === 'inline' && el.parentElement) {
    const parentText = (el.parentElement.textContent || '').replace(el.textContent || '', '').trim();
    if (parentText.length > 1) return null;
  }
  if (r.width >= 44 && r.height >= 44) return null;
  return { w: Math.round(r.width), h: Math.round(r.height) };
}

/** Run every check at the current width. */
export function checkWidth(doc, width) {
  const out = [];
  const root = pageRoot(doc);
  if (!root) return out;
  const win = doc.defaultView;
  const scrolls = doc.documentElement.scrollWidth > doc.documentElement.clientWidth + 1;

  for (const el of culprits(doc, root, width)) {
    const r = el.getBoundingClientRect();
    const tag = el.tagName.toLowerCase();
    const fixed = fixedWidth(el, width);
    const amount = Math.round(Math.max(r.right - width, -r.left));
    if (fixed) {
      out.push(finding('fixed-width', 'error', el, `Fixed width ${fixed.px}px doesn’t fit the ${width}px screen.`, { data: { width: fixed.px, attr: fixed.attr } }));
    } else if (tag === 'img' || tag === 'video' || tag === 'iframe') {
      out.push(finding('img-wide', 'error', el, `${tag === 'img' ? 'Image' : 'Media'} is ${Math.round(r.width)}px wide on a ${width}px screen.`, { data: { width: Math.round(r.width) } }));
    } else if (scrolls) {
      out.push(finding('overflow', 'error', el, `Causes horizontal scrolling: ${describe(el)} sticks out ${amount}px past the edge.`, { data: { by: amount } }));
    } else {
      out.push(finding('offscreen', 'warning', el, `${describe(el)} extends ${amount}px past the screen edge and is cut off.`, { data: { by: amount } }));
    }
  }

  // Text clipping and headings.
  for (const el of root.querySelectorAll('h1, h2, h3, h4, h5, h6, .brik-heading-text, p, span, a, button, li, label')) {
    if (isEditorUi(el) || isHidden(el)) continue;
    const s = win.getComputedStyle(el);
    const isHeading = /^H[1-6]$/.test(el.tagName) || el.classList.contains('brik-heading-text');
    if (isHeading) {
      const size = parseFloat(s.fontSize);
      if (el.scrollWidth > el.clientWidth + 2 && s.overflowX === 'visible' && el.clientWidth > 0) {
        out.push(finding('heading-size', 'error', el, `A word in this heading is wider than its box at ${width}px (${Math.round(size)}px text). Reduce the size for this breakpoint.`, { data: { size } }));
      } else if (size > width * 0.13) {
        out.push(finding('heading-size', 'warning', el, `Heading text is ${Math.round(size)}px — over 13% of a ${width}px screen. Set a smaller ${width <= 767 ? 'mobile' : 'tablet'} size.`, { data: { size } }));
      }
      continue;
    }
    if (!el.firstChild || el.clientWidth === 0) continue;
    if (visuallyHidden(el, s, el.getBoundingClientRect())) continue;
    const clips = /(hidden|clip)/.test(s.overflowX) || s.textOverflow === 'ellipsis';
    const lineClamp = s.webkitLineClamp && s.webkitLineClamp !== 'none';
    if (clips && !lineClamp && el.scrollWidth > el.clientWidth + 1 && [...el.childNodes].some((c) => c.nodeType === 3 && c.nodeValue.trim())) {
      out.push(finding('text-clip', 'warning', el, `Text is cut off: ${describe(el)} doesn’t fit at ${width}px.`));
    } else if ((el.tagName === 'A' || el.tagName === 'BUTTON') && s.display !== 'inline' && el.scrollWidth > el.clientWidth + 2) {
      out.push(finding('text-clip', 'warning', el, `Button text spills out of the button at ${width}px.`));
    }
  }

  // Tap targets and overlaps.
  const controls = [...root.querySelectorAll(INTERACTIVE)].filter((el) => !isEditorUi(el) && !isHidden(el) && !el.classList.contains('brik-el-link'));
  const seenSmall = new Set();
  for (const el of controls) {
    const small = tooSmall(el, width);
    if (!small) continue;
    const key = ownerId(el) || 'page';
    if (seenSmall.has(key)) continue;
    seenSmall.add(key);
    const severe = small.w < 24 || small.h < 24;
    out.push(
      finding('tap-target', severe ? 'warning' : 'info', el, `Tap target ${small.w}×${small.h}px is smaller than ${severe ? 'the 24px minimum' : 'the recommended 44×44px'}.`, {
        data: small,
      })
    );
  }
  const rects = controls.map((el) => ({ el, r: el.getBoundingClientRect() })).filter((x) => x.r.width > 0 && x.r.height > 0 && parseFloat(win.getComputedStyle(x.el).opacity) > 0);
  const seenOverlap = new Set();
  for (let i = 0; i < rects.length; i++) {
    for (let j = i + 1; j < rects.length; j++) {
      const a = rects[i];
      const b = rects[j];
      if (a.el.contains(b.el) || b.el.contains(a.el)) continue;
      const w = Math.min(a.r.right, b.r.right) - Math.max(a.r.left, b.r.left);
      const h = Math.min(a.r.bottom, b.r.bottom) - Math.max(a.r.top, b.r.top);
      if (w <= 2 || h <= 2) continue;
      const area = Math.min(a.r.width * a.r.height, b.r.width * b.r.height);
      if ((w * h) / area < 0.25) continue;
      const key = (ownerId(a.el) || '') + (ownerId(b.el) || '');
      if (seenOverlap.has(key)) continue;
      seenOverlap.add(key);
      out.push(finding('overlap', 'warning', b.el, `${describe(b.el)} overlaps ${describe(a.el)} at ${width}px, so taps may hit the wrong one.`));
    }
  }
  return out;
}

/**
 * Sweep all widths. Returns merged findings, each with { widths: [...], breaksAt }.
 * onProgress({ index, total, width }) is called before each width.
 */
export async function sweep(onProgress = () => {}, widths = WIDTHS) {
  const s = getState();
  const restore = { device: s.device, canvasWidth: s.canvasWidth };
  const merged = new Map();
  const perWidth = {};
  try {
    for (let i = 0; i < widths.length; i++) {
      const width = widths[i];
      onProgress({ index: i, total: widths.length, width });
      await setWidth(width);
      const doc = canvasDoc();
      if (!doc) continue;
      const found = checkWidth(doc, width);
      perWidth[width] = found.filter((f) => f.severity !== 'info').length;
      for (const f of found) {
        const key = `${f.rule}|${f.node || ''}`;
        const prev = merged.get(key);
        if (prev) {
          prev.widths.push(width);
          // Keep the description from the narrowest width (the worst case).
          if (width < prev.width) Object.assign(prev, { message: f.message, data: f.data, el: f.el, width, severity: worse(prev.severity, f.severity) });
        } else {
          merged.set(key, { ...f, widths: [width], width, breaksAt: width });
        }
      }
    }
  } finally {
    unfit();
    setState({ device: restore.device, canvasWidth: restore.canvasWidth });
  }
  return { findings: [...merged.values()], perWidth };
}

function worse(a, b) {
  const rank = { info: 0, warning: 1, error: 2 };
  return rank[b] > rank[a] ? b : a;
}
