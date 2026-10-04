// SEO checks against the canvas DOM. Page meta (title, description, indexing, FAQ schema) comes
// from the server report; this covers what the rendered page shows.
import { pageRoot, ownerId, isEditorUi } from './dom.js';
import { finding } from './a11y.js';

export const HEAVY = 500 * 1024;

const AFFILIATE = /(amzn\.to\/|amazon\.[a-z.]+\/.*[?&]tag=|[?&](ref|aff|affiliate|aff_id|affid|partner)=|\/go\/|\/recommends\/|\/refer\/|shareasale\.com|awin1\.com|clickbank\.net|impact\.com|partnerize|rstyle\.me|go\.skimresources|anrdoezrs\.net|dpbolvw\.net|jdoqocy\.com|tkqlhce\.com|utm_medium=(affiliate|sponsored|paid))/i;

export function isAffiliate(href) {
  return AFFILIATE.test(href || '');
}

/** Links on the page as { el, href, abs, node }. */
export function pageLinks(doc) {
  const root = pageRoot(doc);
  if (!root) return [];
  return [...root.querySelectorAll('a[href]')]
    .filter((a) => !isEditorUi(a) && !a.classList.contains('brik-el-link'))
    .map((a) => ({ el: a, href: a.getAttribute('href').trim(), abs: a.href, node: ownerId(a) }));
}

/**
 * @param {Document} doc
 * @param {Object}   ctx { links: url => result, sizes: url => bytes }
 */
export function runSeo(doc, ctx = {}) {
  const out = [];
  const root = pageRoot(doc);
  if (!root) return out;

  // Images: dimensions and weight.
  const dims = new Map();
  const win = doc.defaultView;
  const entries = new Map();
  try {
    for (const e of win.performance.getEntriesByType('resource')) entries.set(e.name, e);
  } catch (e) {}
  const heavy = new Set();
  for (const img of root.querySelectorAll('img')) {
    if (isEditorUi(img)) continue;
    const src = img.currentSrc || img.src;
    if (!src || src.startsWith('data:')) continue;
    if ((!img.hasAttribute('width') || !img.hasAttribute('height')) && !/\.svg(\?|$)/i.test(src)) {
      const key = ownerId(img) || '';
      dims.set(key, { el: dims.get(key)?.el || img, count: (dims.get(key)?.count || 0) + 1 });
    }
    const entry = entries.get(src);
    const bytes = Math.max(entry ? entry.encodedBodySize || entry.transferSize || 0 : 0, (ctx.sizes && ctx.sizes[src]) || 0);
    if (bytes > HEAVY && !heavy.has(src)) {
      heavy.add(src);
      const name = decodeURIComponent(src.split('?')[0].split('/').pop() || src);
      out.push(
        finding('img-heavy', 'warning', img, `Heavy image: ${(bytes / 1024).toFixed(0)} KB (${name}). Compress it or pick a smaller size; aim for under 500 KB.`, {
          data: { bytes, url: src },
        })
      );
    }
  }
  for (const [, d] of dims) {
    out.push(
      finding('img-dimensions', 'warning', d.el, `${d.count} image${d.count > 1 ? 's have' : ' has'} no width/height attributes, which causes layout shift while loading.`, {
        data: { count: d.count },
      })
    );
  }

  // Links.
  for (const l of pageLinks(doc)) {
    const rel = (l.el.getAttribute('rel') || '').toLowerCase().split(/\s+/);
    if (l.el.target === '_blank' && !rel.includes('noopener') && !rel.includes('noreferrer')) {
      out.push(finding('link-blank-noopener', 'warning', l.el, 'Link opens a new tab without rel="noopener"; the new page could control this one (tab-nabbing).', { data: { url: l.href } }));
    }
    if (isAffiliate(l.href) && !rel.includes('sponsored') && !rel.includes('nofollow')) {
      out.push(finding('link-sponsored', 'info', l.el, 'Looks like an affiliate or paid link. Google asks for rel="sponsored" (or nofollow) on these.', { data: { url: l.href } }));
    }
    if (l.href.startsWith('#') && l.href.length > 1) {
      let target = null;
      try {
        target = doc.getElementById(decodeURIComponent(l.href.slice(1)));
      } catch (e) {}
      if (!target && !/^#(top|brik-|wp-|respond|comments?)$/i.test(l.href)) {
        out.push(finding('link-broken', 'warning', l.el, `Jump link ${l.href} points to an id that isn’t on the page.`, { data: { url: l.href } }));
      }
      continue;
    }
    const r = ctx.links && (ctx.links[l.href] || ctx.links[l.abs]);
    if (r && r.status === 'broken') {
      out.push(finding('link-broken', 'error', l.el, `Broken link: ${l.href}${r.code ? ` (${r.code})` : ''}.`, { data: { url: l.href, ...r } }));
    }
  }
  return out;
}
