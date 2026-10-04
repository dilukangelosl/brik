// "Why is this broken?": inspects an element in the canvas and explains what hides,
// clips or obscures it, with one-click fixes that go through the undoable store.
import * as store from '../../store.js';
import * as T from '../../tree.js';
import { nodeEl, resolve, rgba, contrast, backgroundOf, nodeLabel } from './util.js';

const DEVICE_LABEL = { desktop: 'desktop', tablet: 'tablet', mobile: 'mobile' };

/** Attribute key for the breakpoint being edited (responsive fields only). */
function devKey(node, key, device) {
  const s = store.getState();
  const def = s.schema && s.schema.byType[node.type];
  const field = def && def.fields[key];
  return device !== 'desktop' && field && field.responsive ? `${key}@${device}` : key;
}

function set(id, patch, label) {
  store.setAttrs(id, patch, label);
}

function px(n) {
  return `${Math.round(n)}px`;
}

function isStructural(type) {
  return T.STRUCTURAL ? T.STRUCTURAL.includes(type) : ['section', 'row', 'column'].includes(type);
}

/**
 * Run every check. Returns { node, found, issues: [...], passed: [...] }.
 * Issue: { key, level: 'error'|'warn'|'info', title, detail, fix?: { label, run } }.
 */
export function diagnose(id, { scroll = true } = {}) {
  const s = store.getState();
  const node = T.find(s.tree, id);
  const issues = [];
  const passed = [];
  if (!node) return { node: null, found: false, issues, passed };
  const device = store.effectiveDevice(s);
  const attrs = node.attrs || {};
  const path = T.pathTo(s.tree, id) || [];
  const add = (issue) => issues.push(issue);

  // Display conditions only apply on the live site: the canvas always shows the element.
  for (const n of [...path, node]) {
    const a = n.attrs || {};
    const who = n.id === id ? 'This element' : `Its parent “${nodeLabel(n)}”`;
    if (a.display === 'logged_in' || a.display === 'logged_out') {
      add({
        key: `display-${n.id}`,
        level: 'warn',
        title: a.display === 'logged_in' ? 'Only shown to logged-in users' : 'Only shown to logged-out visitors',
        detail: `${who} is hidden on the live site for ${a.display === 'logged_in' ? 'visitors who are not logged in' : 'logged-in users (including you)'}. The builder always shows it.`,
        fix: { label: 'Show to everyone', run: () => set(n.id, { display: undefined }, 'Show to everyone') },
      });
    }
    const now = Date.now();
    if (a.show_from && Date.parse(a.show_from) > now) {
      add({
        key: `from-${n.id}`,
        level: 'warn',
        title: 'Scheduled for later',
        detail: `${who} appears on the live site from ${new Date(a.show_from).toLocaleString()}.`,
        fix: { label: 'Show now', run: () => set(n.id, { show_from: undefined }, 'Remove start date') },
      });
    }
    if (a.show_until && Date.parse(a.show_until) < now) {
      add({
        key: `until-${n.id}`,
        level: 'error',
        title: 'Expired',
        detail: `${who} stopped showing on ${new Date(a.show_until).toLocaleString()}, so it is hidden on the live site.`,
        fix: { label: 'Remove end date', run: () => set(n.id, { show_until: undefined }, 'Remove end date') },
      });
    }
    const rules = a.visibility_rules || a.conditions;
    if (Array.isArray(rules) && rules.length) {
      add({
        key: `rules-${n.id}`,
        level: 'info',
        title: 'Has display conditions',
        detail: `${who} has ${rules.length} visibility rule${rules.length > 1 ? 's' : ''}; it may be hidden on the live site depending on the visitor or page.`,
        fix: { label: 'Remove rules', run: () => set(n.id, { visibility_rules: undefined, conditions: undefined }, 'Remove visibility rules') },
      });
    }
  }

  // Hidden on the current device (the element or a parent).
  for (const n of [...path, node]) {
    const hide = Array.isArray((n.attrs || {}).hide_on) ? n.attrs.hide_on : [];
    if (hide.includes(device)) {
      const rest = hide.filter((d) => d !== device);
      add({
        key: `hide-${n.id}`,
        level: 'error',
        title: n.id === id ? `Hidden on ${DEVICE_LABEL[device]}` : `Parent hidden on ${DEVICE_LABEL[device]}`,
        detail: n.id === id ? `“Hide on” includes ${DEVICE_LABEL[device]} (Advanced → Visibility).` : `Its parent “${nodeLabel(n)}” is set to hide on ${DEVICE_LABEL[device]}, which hides everything inside it.`,
        fix: { label: `Show on ${DEVICE_LABEL[device]}`, run: () => set(n.id, { hide_on: rest.length ? rest : undefined }, `Show on ${device}`) },
      });
    }
  }

  const el = nodeEl(id);
  if (!el) {
    add({ key: 'missing', level: 'error', title: 'Not in the canvas', detail: 'The element did not render. Its module may be missing or it returned no markup.' });
    return { node, found: false, issues, passed };
  }

  const doc = el.ownerDocument;
  const win = doc.defaultView;
  const cs = win.getComputedStyle(el);

  // display / visibility / opacity — on the element or an ancestor.
  let hiddenBy = null;
  for (let n = el; n && n !== doc.body; n = n.parentElement) {
    const c = win.getComputedStyle(n);
    if (c.display === 'none') {
      hiddenBy = { el: n, why: 'display:none' };
      break;
    }
  }
  if (hiddenBy && !issues.some((i) => i.key.startsWith('hide-'))) {
    const owner = hiddenBy.el.closest('[data-brik-id]');
    const ownerNode = owner ? T.find(s.tree, owner.dataset.brikId) : null;
    const css = ownerNode && (ownerNode.attrs || {}).custom_css;
    const fromCustom = css && /display\s*:\s*none/i.test(css);
    add({
      key: 'display-none',
      level: 'error',
      title: 'Not displayed (display: none)',
      detail: fromCustom
        ? `Custom CSS on “${nodeLabel(ownerNode)}” sets display: none.`
        : `${owner === el ? 'The element' : `“${ownerNode ? nodeLabel(ownerNode) : hiddenBy.el.tagName.toLowerCase()}”`} gets display: none from a stylesheet${owner && owner !== hiddenBy.el ? ' (on an inner element)' : ''}.`,
      fix: fromCustom ? { label: 'Remove from custom CSS', run: () => set(ownerNode.id, { custom_css: css.replace(/display\s*:\s*none\s*(!important)?\s*;?/gi, '').trim() || undefined }, 'Remove display:none') } : undefined,
    });
  }
  if (!hiddenBy && cs.visibility === 'hidden') {
    add({ key: 'visibility', level: 'error', title: 'Invisible (visibility: hidden)', detail: 'The element takes up space but is not painted. Check its custom CSS or classes.' });
  }

  let transparent = null;
  for (let n = el; n && n !== doc.body; n = n.parentElement) {
    if (parseFloat(win.getComputedStyle(n).opacity) < 0.02) {
      transparent = n;
      break;
    }
  }
  if (!hiddenBy && transparent) {
    const owner = transparent.closest('[data-brik-id]');
    const ownerNode = owner ? T.find(s.tree, owner.dataset.brikId) : node;
    const anim = transparent.hasAttribute('data-brik-anim');
    const op = resolve(ownerNode.attrs || {}, 'opacity', 'desktop').value;
    add({
      key: 'opacity',
      level: 'error',
      title: anim ? 'Entrance animation has not played' : 'Fully transparent (opacity 0)',
      detail: anim ? 'It starts invisible and fades in when scrolled into view. If it never enters the viewport (or scripts fail) it stays hidden.' : `${owner === el ? 'Its' : `“${nodeLabel(ownerNode)}”’s`} opacity is 0${op !== undefined ? ' (Design → Filters → Opacity)' : ''}.`,
      fix: anim
        ? { label: 'Remove animation', run: () => set(ownerNode.id, { animation: undefined }, 'Remove animation') }
        : op !== undefined
          ? { label: 'Set opacity to 100%', run: () => set(ownerNode.id, { opacity: undefined }, 'Reset opacity') }
          : undefined,
    });
  } else if (!hiddenBy && attrs.animation) {
    passed.push('Entrance animation set — it plays when the element scrolls into view');
  }

  if (hiddenBy) {
    return { node, found: true, issues, passed };
  }
  passed.push(`Displayed on ${DEVICE_LABEL[device]}`);

  // scrollIntoView() (layers panel, canvas.scrollTo) also scrolls overflow:hidden parents,
  // which visitors never see; put them back so clipping is measured as on the live page.
  for (let p = el.parentElement; p && p !== doc.body; p = p.parentElement) {
    const pc = win.getComputedStyle(p);
    if (/hidden|clip/.test(pc.overflowX) && p.scrollLeft) p.scrollLeft = 0;
    if (/hidden|clip/.test(pc.overflowY) && p.scrollTop) p.scrollTop = 0;
  }
  if (scroll) {
    const r0 = el.getBoundingClientRect();
    if (r0.bottom < 0 || r0.top > win.innerHeight) win.scrollTo(0, Math.max(0, r0.top + win.scrollY - win.innerHeight / 2 + r0.height / 2));
  }
  const r = el.getBoundingClientRect();

  // Zero size.
  if (r.width < 1 || r.height < 1) {
    const which = r.width < 1 && r.height < 1 ? 'width and height' : r.width < 1 ? 'width' : 'height';
    const sizeKey = ['height', 'width', 'max_width', 'min_height'].find((k) => {
      const v = resolve(attrs, k, device).value;
      return v !== undefined && /^0(px|%|rem|em)?$/.test(String(v).trim());
    });
    add({
      key: 'size',
      level: 'error',
      title: `Zero ${which}`,
      detail: sizeKey ? `${sizeKey.replace('_', ' ')} is set to ${resolve(attrs, sizeKey, device).value}.` : `It renders ${px(r.width)} × ${px(r.height)}${isStructural(node.type) ? ' — it has nothing inside that takes up space.' : '. Its content may be empty or collapsed by its container.'}`,
      fix: sizeKey
        ? {
            label: `Clear ${sizeKey.replace('_', ' ')}`,
            run: () => {
              // Clear it where it's set, which may be a wider breakpoint this one inherits from.
              const from = resolve(attrs, sizeKey, device).from;
              set(id, { [from === 'desktop' ? sizeKey : `${sizeKey}@${from}`]: undefined }, `Clear ${sizeKey}`);
            },
          }
        : undefined,
    });
  } else {
    passed.push(`Has a size (${px(r.width)} × ${px(r.height)})`);
  }

  // Empty content.
  const media = el.querySelector('img, svg, video, iframe, canvas, picture, input, textarea, select, button, hr, [style*="background-image"]');
  const text = (el.textContent || '').trim();
  if (isStructural(node.type)) {
    if (!(node.children || []).length) {
      add({
        key: 'empty',
        level: 'warn',
        title: `Empty ${node.type}`,
        detail: `This ${node.type} has no elements in it.`,
        fix: node.type === 'column' ? { label: 'Add an element', run: () => store.openModal('modules', { parent: id, index: 0 }) } : undefined,
      });
    }
  } else if (!text && !media && cs.backgroundImage === 'none') {
    add({ key: 'empty', level: 'warn', title: 'No visible content', detail: 'It has no text or image, so there is nothing to see. Fill in its Content tab.' });
  } else {
    passed.push('Has content');
  }

  // Clipped by an ancestor with overflow other than visible.
  if (r.width >= 1 && r.height >= 1) {
    let clipped = null;
    for (let p = el.parentElement; p && p !== doc.body && p !== doc.documentElement; p = p.parentElement) {
      const pc = win.getComputedStyle(p);
      const ox = pc.overflowX !== 'visible';
      const oy = pc.overflowY !== 'visible';
      if (!ox && !oy) continue;
      const pr = p.getBoundingClientRect();
      const outX = ox && (r.left < pr.left - 1 || r.right > pr.right + 1);
      const outY = oy && (r.top < pr.top - 1 || r.bottom > pr.bottom + 1);
      if (outX || outY) {
        const hiddenArea = Math.max(0, pr.left - r.left) + Math.max(0, r.right - pr.right) + Math.max(0, pr.top - r.top) + Math.max(0, r.bottom - pr.bottom);
        clipped = { p, hiddenArea, overflow: ox ? pc.overflowX : pc.overflowY, outside: r.right <= pr.left || r.left >= pr.right || r.bottom <= pr.top || r.top >= pr.bottom };
        break;
      }
    }
    if (clipped) {
      const owner = clipped.p.closest('[data-brik-id]');
      const ownerNode = owner ? T.find(s.tree, owner.dataset.brikId) : null;
      const own = owner === clipped.p;
      add({
        key: 'clip',
        level: clipped.outside ? 'error' : 'warn',
        title: clipped.outside ? 'Cut off completely by its container' : 'Partly cut off by its container',
        detail: `${ownerNode ? `“${nodeLabel(ownerNode)}”` : 'A parent'} has overflow: ${clipped.overflow}${own ? '' : ' on an inner element'} and the element sticks out of it.`,
        fix: ownerNode && own ? { label: `Set “${nodeLabel(ownerNode)}” overflow to visible`, run: () => set(ownerNode.id, { overflow: 'visible' }, 'Overflow visible') } : undefined,
      });
    } else {
      passed.push('Not clipped by a parent');
    }
  }

  // Negative margins.
  const margins = ['Top', 'Right', 'Bottom', 'Left'].map((side) => parseFloat(cs[`margin${side}`]) || 0);
  if (margins.some((m) => m < -0.5)) {
    const fixed = margins.map((m) => (m < 0 ? '0' : `${Math.round(m)}px`)).join(' ');
    add({
      key: 'margin',
      level: 'warn',
      title: 'Negative margins pull it out of place',
      detail: `Margins are ${margins.map((m) => px(m)).join(' ')}. Negative values make it overlap neighbours or leave its container.`,
      fix: { label: 'Remove negative margins', run: () => set(id, { [devKey(node, 'margin', device)]: fixed }, 'Remove negative margins') },
    });
  }

  // Positioned off-screen.
  const docW = doc.documentElement.clientWidth;
  const top = r.top + win.scrollY;
  const offscreen = r.right <= 0 || r.left >= docW || top + r.height <= 0 || top >= doc.documentElement.scrollHeight;
  if (offscreen && r.width >= 1) {
    const positioned = ['absolute', 'fixed'].includes(cs.position);
    const patch = {};
    for (const k of positioned ? ['position', 'top', 'right', 'bottom', 'left'] : ['translate_x', 'translate_y', 'margin']) {
      for (const suffix of ['', '@tablet', '@mobile']) patch[k + suffix] = undefined;
    }
    add({
      key: 'offscreen',
      level: 'error',
      title: 'Placed off-screen',
      detail: positioned ? `position: ${cs.position} with offsets places it outside the page (left ${px(r.left)}, top ${px(top)}).` : `It is pushed outside the page (left ${px(r.left)}, top ${px(top)}) by transforms or margins.`,
      fix: { label: positioned ? 'Reset position' : 'Reset offsets', run: () => set(id, patch, 'Reset position') },
    });
  } else if (r.right > docW + 1 && !['fixed'].includes(cs.position)) {
    add({ key: 'overflow-page', level: 'warn', title: 'Sticks out past the screen edge', detail: `Its right edge is at ${px(r.right)} on a ${px(docW)} wide screen, which causes sideways scrolling.`, fix: { label: 'Limit width to 100%', run: () => set(id, { [devKey(node, 'max_width', device)]: '100%' }, 'Max width 100%') } });
  } else {
    passed.push('On screen');
  }

  // Wider than its parent.
  const parentEl = el.parentElement;
  if (parentEl && !['absolute', 'fixed'].includes(cs.position)) {
    const pr = parentEl.getBoundingClientRect();
    if (r.width > pr.width + 1.5) {
      const w = resolve(attrs, 'width', device).value;
      add({
        key: 'wide',
        level: 'warn',
        title: 'Wider than its container',
        detail: `${px(r.width)} wide inside a ${px(pr.width)} container${w !== undefined ? ` (width is set to ${w})` : ''}. It overflows on this screen size.`,
        fix: { label: w !== undefined ? 'Use max width 100%' : 'Limit width to 100%', run: () => set(id, { [devKey(node, 'max_width', device)]: '100%', ...(w !== undefined ? { [devKey(node, 'width', device)]: '100%' } : {}) }, 'Fit container') },
      });
    } else {
      passed.push('Fits inside its container');
    }
  }

  // Covered by another element.
  if (r.width >= 1 && r.height >= 1 && !offscreen) {
    const cx = Math.min(Math.max(r.left + r.width / 2, 1), win.innerWidth - 1);
    const cy = Math.min(Math.max(r.top + r.height / 2, 1), win.innerHeight - 1);
    const stack = doc.elementsFromPoint(cx, cy).filter((n) => !n.closest('#brik-ui'));
    const hit = stack[0];
    if (hit && hit !== el && !el.contains(hit) && !hit.contains(el)) {
      const cover = hit.closest('[data-brik-id]');
      const coverNode = cover && cover !== el && !cover.contains(el) ? T.find(s.tree, cover.dataset.brikId) : null;
      const z = parseInt(win.getComputedStyle(cover || hit).zIndex, 10) || 0;
      add({
        key: 'covered',
        level: 'error',
        title: 'Covered by another element',
        detail: `${coverNode ? `“${nodeLabel(coverNode)}”` : `A ${hit.tagName.toLowerCase()} element`} sits on top of it${z ? ` (z-index ${z})` : ''}.`,
        fix: {
          label: `Bring to front (z-index ${z + 1})`,
          run: () => set(id, { z_index: String(z + 1), ...(cs.position === 'static' && !attrs.position ? { position: 'relative' } : {}) }, 'Bring to front'),
        },
      });
    } else {
      passed.push('Not covered by other elements');
    }
  }

  // Text contrast.
  if (text) {
    const walker = doc.createTreeWalker(el, win.NodeFilter.SHOW_TEXT, { acceptNode: (t) => (t.textContent.trim() ? 1 : 3) });
    const first = walker.nextNode();
    const host = first ? first.parentElement : el;
    const hc = win.getComputedStyle(host);
    const fg = rgba(hc.color);
    const bg = backgroundOf(host);
    if (fg && bg.color) {
      const effective = fg[3] < 1 ? [0, 1, 2].map((i) => fg[i] * fg[3] + bg.color[i] * (1 - fg[3])) : fg;
      const ratio = contrast(effective, bg.color);
      const rootCs = win.getComputedStyle(doc.documentElement);
      const tokenFor = (name) => rgba(rootCs.getPropertyValue(name).trim());
      const options = [
        ['var(--foreground)', tokenFor('--foreground')],
        ['var(--background)', tokenFor('--background')],
      ].filter(([, c]) => c);
      const best = options.sort((a, b) => contrast(b[1], bg.color) - contrast(a[1], bg.color))[0];
      if (ratio < 3) {
        add({
          key: 'contrast',
          level: ratio < 1.5 ? 'error' : 'warn',
          title: ratio < 1.5 ? 'Text is the same colour as its background' : 'Low text contrast',
          detail: `Contrast is ${ratio.toFixed(2)}:1${ratio < 1.5 ? ', so the text is practically invisible' : '; aim for at least 4.5:1 for body text'}.`,
          fix: best ? { label: `Use ${best[0] === 'var(--foreground)' ? 'foreground' : 'background'} colour`, run: () => set(id, { text_color: best[0] }, 'Fix text colour') } : undefined,
        });
      } else {
        passed.push(`Readable contrast (${ratio.toFixed(1)}:1)`);
      }
    } else if (bg.image) {
      passed.push('Text over an image — check contrast by eye');
    }
  }

  return { node, found: true, issues, passed };
}
