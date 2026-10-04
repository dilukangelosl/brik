// Accessibility checks against the live canvas DOM.
import { toRgba, gradientColors, linearGradientAt, over, ratio, hex } from './color.js';
import { INTERACTIVE, pageRoot, ownerId, ownerNode, isHidden, ariaHidden, isFocusable, accessibleName, describe, elements, isEditorUi } from './dom.js';
import { category } from './rules.js';

const ALWAYS_GENERIC = ['click here', 'here', 'click', 'link', 'this', 'this link', 'click this'];
const GENERIC = ['read more', 'learn more', 'more', 'details', 'more info', 'continue', 'continue reading', 'see more', 'view more', 'go', 'find out more'];

export function finding(rule, severity, el, message, extra = {}) {
  return { rule, category: category(rule), severity, node: el ? ownerId(el) : null, el: el || null, message, source: 'canvas', ...extra };
}

export function filenameLike(alt) {
  alt = String(alt || '').trim();
  return /\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i.test(alt) || /^(img|image|dsc|dscn|pxl|photo|screenshot|screen shot|unnamed|untitled)[\s_-]*\d/i.test(alt) || (/^[a-z0-9]+([_-][a-z0-9]+){2,}$/i.test(alt) && !/\s/.test(alt));
}

export function runA11y(doc) {
  const out = [];
  const root = pageRoot(doc);
  if (!root) return out;
  const all = elements(root);

  headings(doc, out);
  images(root, out);
  contrast(all, out);
  names(root, out);
  forms(root, out);
  focus(doc, root, out);
  misc(doc, root, all, out);
  return out;
}

/* ------------------------------------------------------------------------
 * Headings: the whole document, since the theme may print the page title.
 * ---------------------------------------------------------------------- */

export function outline(doc) {
  const root = pageRoot(doc);
  return [...doc.body.querySelectorAll('h1, h2, h3, h4, h5, h6')]
    .filter((el) => !isEditorUi(el) && !isHidden(el))
    .map((el) => ({ el, level: +el.tagName[1], text: accessibleName(el), node: ownerId(el), theme: !root || !root.contains(el) }));
}

function headingFix(el, level) {
  const node = ownerNode(el);
  if (!node || node.type !== 'heading' || !el.classList.contains('brik-heading-text')) return [];
  return [{ id: 'level', label: `Change to ${level.toUpperCase()}`, patch: { level }, safe: true }];
}

function headings(doc, out) {
  let h1 = 0;
  let prev = 1;
  for (const h of outline(doc)) {
    if (!h.text) {
      out.push(finding('heading-empty', 'error', h.el, 'Empty heading. Screen reader users navigate by headings and will hear nothing here.'));
      continue;
    }
    if (h.level === 1) {
      h1++;
      if (h1 > 1) {
        out.push(finding('heading-multiple-h1', 'warning', h.el, 'More than one H1. Keep one H1 for the page topic and use H2 for sections.', { fixes: headingFix(h.el, 'h2') }));
      }
    } else if (h.level > prev + 1) {
      out.push(
        finding('heading-skipped', 'warning', h.el, `Heading level skipped: H${h.level} follows H${prev}.`, {
          data: { level: h.level, prev },
          fixes: headingFix(h.el, `h${prev + 1}`),
        })
      );
    }
    prev = h.level;
  }
  if (h1 === 0) out.push(finding('h1-count', 'warning', null, 'No H1 on the page. Add one heading that states what the page is about.'));
  else if (h1 > 1) out.push(finding('h1-count', 'error', null, `${h1} H1 headings. Search engines expect one main heading per page.`, { data: { count: h1 } }));
}

/* ------------------------------------------------------------------------
 * Images.
 * ---------------------------------------------------------------------- */

function images(root, out) {
  const galleries = new Map();
  for (const img of root.querySelectorAll('img')) {
    if (isEditorUi(img)) continue;
    const node = ownerNode(img);
    const decorative = img.getAttribute('role') === 'presentation' || img.getAttribute('role') === 'none' || img.getAttribute('aria-hidden') === 'true' || ariaHidden(img);
    if (decorative) continue;
    const alt = img.getAttribute('alt');
    const moduleImage = node && (node.type === 'image' || node.type === 'gallery');

    if (node && node.type === 'gallery') {
      const g = galleries.get(node.id) || { el: img.closest('[data-brik-id]'), total: 0, missing: 0, names: 0 };
      g.total++;
      if (!alt || !alt.trim()) g.missing++;
      else if (filenameLike(alt)) g.names++;
      galleries.set(node.id, g);
      continue;
    }
    if (alt === null) {
      out.push(finding('img-alt-missing', 'error', img, 'Image without an alt attribute. Add alt text, or alt="" if it is decorative.'));
    } else if (!alt.trim() && moduleImage) {
      // Brik renders alt="" when no alt is set, which reads as decorative without meaning to be.
      out.push(finding('img-alt-missing', 'error', img, 'Image has no alt text. Describe it, or mark it decorative if it adds no information.'));
    } else if (alt.trim() && filenameLike(alt)) {
      out.push(finding('img-alt-filename', 'warning', img, `Alt text looks like a file name: “${alt}”.`));
    } else if (alt.length > 150) {
      out.push(finding('img-alt-long', 'warning', img, `Alt text is very long (${alt.length} characters). Keep it short; put details in a caption.`));
    }
  }
  for (const g of galleries.values()) {
    if (g.missing) out.push(finding('img-alt-missing', 'error', g.el, `${g.missing} of ${g.total} gallery images have no alt text.`, { data: { missing: g.missing, total: g.total } }));
    else if (g.names) out.push(finding('img-alt-filename', 'warning', g.el, `${g.names} gallery image${g.names > 1 ? 's use' : ' uses'} a file name as alt text.`));
  }
}

/* ------------------------------------------------------------------------
 * Contrast: effective colours from the computed styles of the element and its ancestors.
 * ---------------------------------------------------------------------- */

const WHITE = [255, 255, 255, 1];

/**
 * Background colours behind an element, sampled at a few points across its text (gradients
 * vary along their length). Returns { colors: [rgba…] } or { unknown: true } over images/video.
 */
function backdrop(el) {
  const win = el.ownerDocument.defaultView;
  const r = el.getBoundingClientRect();
  const y = r.top + r.height / 2;
  const points = r.width > 24 ? [[r.left + 6, y], [r.left + r.width / 2, y], [r.right - 6, y]] : [[r.left + r.width / 2, y]];
  const layers = [];
  for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
    const cs = win.getComputedStyle(n);
    const img = cs.backgroundImage;
    if (img && img !== 'none') {
      if (/url\(/.test(img)) return { unknown: true };
      const box = n.getBoundingClientRect();
      const linear = /^(repeating-)?linear-gradient/.test(img);
      const stops = gradientColors(img);
      if (stops.length) {
        layers.push(linear ? { at: (x, yy) => linearGradientAt(img, box, x, yy) || stops[0] } : { stops });
        if (stops.every((st) => st[3] >= 1)) break;
      }
    }
    if (n !== el && ['VIDEO', 'CANVAS', 'IFRAME'].includes(n.tagName)) return { unknown: true };
    const bg = toRgba(cs.backgroundColor);
    if (bg && bg[3] > 0) {
      layers.push({ stops: [bg] });
      if (bg[3] >= 1) break;
    }
  }
  // Composite from the bottom up, starting on white (the canvas default).
  const colors = [];
  for (const [x, yy] of points) {
    let bases = [WHITE];
    for (let i = layers.length - 1; i >= 0; i--) {
      const l = layers[i];
      const tops = l.at ? [l.at(x, yy)] : l.stops;
      const next = [];
      for (const b of bases) for (const t of tops) next.push(over(t, b));
      bases = next.slice(0, 12);
    }
    colors.push(...bases);
  }
  return { colors };
}

function hasOwnText(el) {
  for (const c of el.childNodes) if (c.nodeType === 3 && c.nodeValue.trim()) return true;
  return false;
}

function contrast(all, out) {
  const worst = new Map();
  for (const el of all) {
    if (!hasOwnText(el) || isHidden(el) || ariaHidden(el)) continue;
    const tag = el.tagName;
    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'OPTION', 'TEXTAREA'].includes(tag)) continue;
    if (el.closest('[disabled], [aria-disabled="true"]')) continue;
    const rect = el.getBoundingClientRect();
    if (rect.width <= 1 || rect.height <= 1) continue;
    const win = el.ownerDocument.defaultView;
    const cs = win.getComputedStyle(el);
    if (parseFloat(cs.opacity) === 0) continue;
    // Gradient-filled text (background-clip: text) can't be judged from colours alone.
    const fill = cs.webkitTextFillColor || cs.getPropertyValue('-webkit-text-fill-color');
    if (fill && toRgba(fill) && toRgba(fill)[3] === 0) continue;
    let fg = toRgba(cs.color);
    if (!fg || fg[3] === 0) continue;
    let opacity = 1;
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) opacity *= parseFloat(win.getComputedStyle(n).opacity) || 1;
    if (opacity < 0.1) continue;
    fg = [fg[0], fg[1], fg[2], fg[3] * opacity];

    const back = backdrop(el);
    if (back.unknown) continue;
    let low = null;
    let bgUsed = null;
    for (const b of back.colors) {
      const r = ratio(fg, b);
      if (low === null || r < low) {
        low = r;
        bgUsed = b;
      }
    }
    const size = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const large = size >= 24 || (bold && size >= 18.66);
    const need = large ? 3 : 4.5;
    if (low >= need) continue;

    const key = ownerId(el) || 'page';
    const prev = worst.get(key);
    if (!prev || low < prev.data.ratio) {
      worst.set(
        key,
        finding('contrast', low < need * 0.75 ? 'error' : 'warning', el, `Low text contrast ${low}:1 (needs ${need}:1${large ? ' for large text' : ''}).`, {
          data: { ratio: low, required: need, fg: hex(over(fg, bgUsed)), bg: hex(bgUsed), large, sample: describe(el) },
        })
      );
    }
  }
  for (const f of worst.values()) out.push(f);
}

/* ------------------------------------------------------------------------
 * Names for links and buttons, generic link text.
 * ---------------------------------------------------------------------- */

function buttonTextFix(el) {
  const node = ownerNode(el);
  if (!node || node.type !== 'button') return [];
  return [{ id: 'text', label: 'Add button text', patch: {}, prompt: { key: 'text', label: 'Button text', value: '' } }];
}

function names(root, out) {
  const generic = new Map();
  for (const el of root.querySelectorAll('a[href], button, [role="button"], [role="link"], input[type="submit"], input[type="button"]')) {
    if (isEditorUi(el) || isHidden(el) || ariaHidden(el)) continue;
    if (el.classList.contains('brik-el-link')) continue;
    const name = accessibleName(el);
    const isLink = el.tagName === 'A' || el.getAttribute('role') === 'link';
    if (!name) {
      out.push(
        finding('name-missing', 'error', el, isLink ? 'Link has no accessible name (icon only or empty). Screen readers announce just “link”.' : 'Button has no accessible name (icon only or empty).', {
          fixes: buttonTextFix(el),
        })
      );
      continue;
    }
    if (!isLink) continue;
    const text = name.toLowerCase().replace(/[\s.!:»›→]+$/g, '').trim();
    if (ALWAYS_GENERIC.includes(text) || GENERIC.includes(text)) {
      const list = generic.get(text) || [];
      list.push(el);
      generic.set(text, list);
    }
  }
  // "Read more" is fine once in context; several pointing to different places are ambiguous.
  for (const [text, list] of generic) {
    const hrefs = new Set(list.map((a) => a.getAttribute('href')));
    if (!ALWAYS_GENERIC.includes(text) && hrefs.size < 2) continue;
    for (const el of list) {
      out.push(finding('link-generic', 'warning', el, `Link text “${accessibleName(el)}” doesn’t say where it goes${list.length > 1 ? ` (${list.length} links share it)` : ''}. Describe the destination.`));
    }
  }
}

/* ------------------------------------------------------------------------
 * Forms.
 * ---------------------------------------------------------------------- */

function forms(root, out) {
  for (const el of root.querySelectorAll('input, select, textarea')) {
    if (isEditorUi(el) || ['hidden', 'submit', 'button', 'reset', 'image'].includes(el.type) || isHidden(el)) continue;
    if (accessibleName(el)) continue;
    out.push(
      finding(
        'input-label',
        'error',
        el,
        el.placeholder ? `Field “${el.placeholder}” is only labelled by its placeholder, which disappears while typing. Add a label.` : 'Form field has no label.'
      )
    );
  }
}

/* ------------------------------------------------------------------------
 * Focus styles: rules that remove the outline without a :focus replacement.
 * ---------------------------------------------------------------------- */

function stripFocus(selector) {
  return splitSelectors(selector)
    .filter((s) => !/:not\([^)]*:focus/.test(s) && !s.includes('::'))
    .map((s) => s.replace(/:focus(-visible|-within)?/g, '').replace(/:is\(\s*\)/g, '').trim())
    .filter(Boolean);
}

function splitSelectors(sel) {
  const parts = [];
  let depth = 0;
  let cur = '';
  for (const ch of sel) {
    if (ch === '(') depth++;
    if (ch === ')') depth--;
    if (ch === ',' && depth === 0) {
      parts.push(cur);
      cur = '';
    } else cur += ch;
  }
  parts.push(cur);
  return parts.map((s) => s.trim()).filter(Boolean);
}

function focusRules(doc) {
  const removers = [];
  const replacers = [];
  const visit = (rules, parent) => {
    for (const r of rules) {
      if (r.selectorText !== undefined && r.style) {
        let sel = r.selectorText;
        if (parent) sel = sel.includes('&') ? sel.replace(/&/g, `:is(${parent})`) : `:is(${parent}) ${sel}`;
        const st = r.style;
        const outline = st.getPropertyValue('outline').trim();
        const style = st.getPropertyValue('outline-style').trim();
        const width = st.getPropertyValue('outline-width').trim();
        const color = st.getPropertyValue('outline-color').trim();
        const removes = style === 'none' || /^(none|0|0px)(\s|$)/.test(outline) || width === '0' || width === '0px' || /transparent/.test(outline) || color === 'transparent';
        const focusRule = /:focus/.test(sel);
        const shows =
          (outline && !removes) ||
          (style && style !== 'none') ||
          ['box-shadow', '--tw-ring-shadow', '--tw-shadow', 'border-color', 'background-color', 'text-decoration', 'text-decoration-line', 'color', 'background'].some((p) => st.getPropertyValue(p));
        if (removes) removers.push(...stripFocus(sel));
        if (focusRule && shows) replacers.push(...stripFocus(sel));
        if (r.cssRules && r.cssRules.length) visit(r.cssRules, sel);
      } else if (r.cssRules) {
        visit(r.cssRules, parent);
      }
    }
  };
  for (const sheet of doc.styleSheets) {
    let rules;
    try {
      rules = sheet.cssRules;
    } catch (e) {
      continue; // Cross-origin stylesheet (web fonts).
    }
    if (rules) visit(rules, null);
  }
  return { removers, replacers };
}

function matchesAny(el, selectors) {
  for (const s of selectors) {
    try {
      if (el.matches(s)) return true;
    } catch (e) {}
  }
  return false;
}

function focus(doc, root, out) {
  const { removers, replacers } = focusRules(doc);
  const seen = new Set();
  for (const el of root.querySelectorAll(INTERACTIVE)) {
    if (isEditorUi(el) || isHidden(el) || !isFocusable(el)) continue;
    const inline = /none|^0/.test(el.style.outline || el.style.outlineStyle || '');
    if (!inline && !matchesAny(el, removers)) continue;
    if (matchesAny(el, replacers)) continue;
    const key = ownerId(el) || 'page';
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(finding('focus-removed', 'warning', el, `Focus outline is removed on ${describe(el)} with no replacement style, so keyboard users can’t see where they are.`));
  }
}

/* ------------------------------------------------------------------------
 * Everything else.
 * ---------------------------------------------------------------------- */

function misc(doc, root, all, out) {
  const lang = doc.documentElement.getAttribute('lang');
  if (!lang || !lang.trim()) out.push(finding('lang-missing', 'error', null, 'The page has no lang attribute, so screen readers may use the wrong pronunciation. It comes from the theme (language_attributes()).'));

  for (const el of all) {
    const tag = el.tagName.toLowerCase();
    const ti = el.getAttribute('tabindex');
    if (ti !== null && parseInt(ti, 10) > 0) out.push(finding('tabindex-positive', 'warning', el, `tabindex="${ti}" changes the natural tab order and usually confuses keyboard users.`));

    if (el.getAttribute('aria-hidden') === 'true') {
      const bad = (isFocusable(el) && el.getAttribute('tabindex') !== '-1') || [...el.querySelectorAll(INTERACTIVE)].some((c) => isFocusable(c) && !isHidden(c));
      if (bad) out.push(finding('aria-hidden-focusable', 'error', el, 'aria-hidden="true" on (or around) a focusable element: keyboard users land on something screen readers can’t see.'));
    }

    if (el.hasAttribute('onclick') && !['a', 'button', 'input', 'select', 'textarea', 'summary', 'label'].includes(tag) && !el.hasAttribute('role')) {
      out.push(finding('div-button', 'warning', el, `Clickable <${tag}> isn’t a button: keyboard and screen reader users can’t use it.`));
    } else if (el.getAttribute('role') === 'button' && tag !== 'button' && !isFocusable(el)) {
      out.push(finding('div-button', 'warning', el, 'Element acts as a button but can’t be reached with the keyboard. Use a real <button>.'));
    }

    if ((tag === 'video' || tag === 'audio') && el.autoplay && !el.muted && !el.hasAttribute('muted')) {
      out.push(finding('autoplay-sound', 'error', el, 'Media plays sound automatically. Mute it or let visitors start it.'));
    }
  }

  if (root.querySelector('[data-brik-anim]')) {
    out.push(finding('motion', 'info', null, 'Entrance animations are off for visitors who ask for reduced motion (handled globally by Brik).', { pass: true }));
  }
}
