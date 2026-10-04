// Audit state and orchestration: runs the canvas checks, fetches the server report, merges
// both, scores them and applies fixes through the builder store (so every fix is undoable).
import { useSyncExternalStore, api } from '../../wp.js';
import * as store from '../../store.js';
import * as T from '../../tree.js';
import { element, scrollTo } from '../../canvas.js';
import { canvasDoc } from './dom.js';
import { runA11y, outline as readOutline } from './a11y.js';
import { runSeo, pageLinks } from './seo.js';
import { sweep, setWidth } from './responsive.js';
import { CANVAS_RULES, RULES, score, category } from './rules.js';

const listeners = new Set();
let state = {
  status: 'idle', // idle | running | done
  ranAt: null,
  server: null,
  serverError: null,
  canvas: [],
  findings: { a11y: [], seo: [], responsive: [] },
  passes: { a11y: [], seo: [], responsive: [] },
  scores: { a11y: null, seo: null, responsive: null, overall: null },
  outline: [],
  meta: {},
  links: {},
  sizes: {},
  checkExternal: false,
  linksChecked: false,
  responsive: { status: 'idle', progress: null, perWidth: {}, ranAt: null, findings: [] },
  scope: null,
  tab: 'a11y',
};

export function getAudit() {
  return state;
}

export function setAudit(patch) {
  state = { ...state, ...(typeof patch === 'function' ? patch(state) : patch) };
  listeners.forEach((l) => l());
}

export function useAudit(selector = (s) => s) {
  return useSyncExternalStore(
    (l) => {
      listeners.add(l);
      return () => listeners.delete(l);
    },
    () => selector(state)
  );
}

/* ------------------------------------------------------------------------
 * Running.
 * ---------------------------------------------------------------------- */

let seq = 0;

/** Server findings for the current (unsaved) tree. */
async function fetchServer() {
  const s = store.getState();
  return api({ path: `/brik/v1/audit/${s.post.id}`, method: 'POST', data: { tree: s.tree } });
}

async function fetchLinks(doc) {
  const urls = [...new Set(pageLinks(doc).map((l) => l.href).filter((h) => h && !h.startsWith('#') && !/^(mailto|tel|javascript):/i.test(h)))];
  const todo = urls.filter((u) => !(u in state.links));
  if (!todo.length) return state.links;
  const res = await api({ path: '/brik/v1/audit/links', method: 'POST', data: { urls: todo, external: state.checkExternal, post_id: store.getState().post.id } });
  return { ...state.links, ...(res.results || {}) };
}

async function fetchSizes(doc) {
  const urls = [...new Set([...doc.querySelectorAll('img')].map((i) => i.currentSrc || i.src).filter((u) => u && !u.startsWith('data:') && !(u in state.sizes)))];
  if (!urls.length) return state.sizes;
  const res = await api({ path: '/brik/v1/audit/media', method: 'POST', data: { urls, post_id: store.getState().post.id } });
  return { ...state.sizes, ...(res.sizes || {}) };
}

/**
 * Run the audit. full: also ask the server (and check links/media); otherwise only re-run the
 * canvas checks against the last server report.
 */
export async function run({ full = true, links = false } = {}) {
  const doc = canvasDoc();
  if (!doc || !store.getState().canvasReady) return;
  const mine = ++seq;
  setAudit({ status: 'running' });
  let server = state.server;
  let serverError = state.serverError;
  let linkResults = state.links;
  let sizes = state.sizes;
  if (full) {
    const jobs = [
      fetchServer().then(
        (r) => ((server = r), (serverError = null)),
        (e) => (serverError = e.message || 'Server audit failed')
      ),
      fetchSizes(doc).then((r) => (sizes = r), () => {}),
    ];
    if (links) jobs.push(fetchLinks(doc).then((r) => (linkResults = r), () => {}));
    await Promise.all(jobs);
  }
  if (mine !== seq) return;
  const fresh = canvasDoc();
  if (!fresh) return;
  let canvas = [];
  try {
    canvas = [...runA11y(fresh), ...runSeo(fresh, { links: linkResults, sizes })];
  } catch (e) {
    // A check failing must never break the builder; report what we have.
    console.error('Brik audit:', e); // eslint-disable-line no-console
  }
  const outline = readOutline(fresh).map(({ level, text, node, theme }) => ({ level, text, node, theme }));
  setAudit({ server, serverError, links: linkResults, sizes, canvas, outline, linksChecked: state.linksChecked || links });
  compute();
  setAudit({ status: 'done', ranAt: Date.now() });
}

const key = (f) => `${f.rule}|${f.node || ''}`;

/** Merge server and canvas findings, then score each category. */
function compute() {
  const server = (state.server && state.server.findings) || [];
  const fixesFrom = new Map();
  for (const f of server) if (f.fixes && f.fixes.length && !fixesFrom.has(key(f))) fixesFrom.set(key(f), f.fixes);

  const serverHeavy = new Set(server.filter((f) => f.rule === 'img-heavy').map((f) => f.node));
  const canvas = state.canvas
    .filter((f) => !(f.rule === 'img-heavy' && serverHeavy.has(f.node)))
    .map((f) => ({ ...f, fixes: f.fixes && f.fixes.length ? f.fixes : fixesFrom.get(key(f)) || [] }));

  // Without a canvas run (it failed), fall back to the server's version of canvas rules.
  const all = [...canvas, ...server.filter((f) => !CANVAS_RULES.has(f.rule) || !state.canvas.length)];
  const seen = new Set();
  const findings = { a11y: [], seo: [], responsive: state.responsive.findings };
  const passes = { a11y: [], seo: [], responsive: [] };
  for (const f of all) {
    const k = `${key(f)}|${f.message}`;
    if (seen.has(k)) continue;
    seen.add(k);
    if (f.pass) {
      passes[f.category || category(f.rule)].push({ rule: f.rule, message: f.message });
      continue;
    }
    findings[f.category || category(f.rule)].push({ ...f, label: f.label || nodeLabel(f.node) });
  }

  // Passed checks: every known rule of the category that produced nothing.
  for (const cat of ['a11y', 'seo']) {
    const failing = new Set(findings[cat].map((f) => f.rule));
    const passed = new Set(passes[cat].map((p) => p.rule));
    for (const [rule, [c]] of Object.entries(RULES)) {
      if (c !== cat || failing.has(rule) || passed.has(rule)) continue;
      if (rule === 'link-broken' && !state.linksChecked) continue;
      if (rule === 'motion') continue;
      passes[cat].push({ rule });
    }
  }
  if (state.responsive.ranAt) {
    const failing = new Set(state.responsive.findings.map((f) => f.rule));
    for (const [rule, [c]] of Object.entries(RULES)) if (c === 'responsive' && !failing.has(rule)) passes.responsive.push({ rule });
  }

  const scores = {
    a11y: score(findings.a11y),
    seo: score(findings.seo),
    responsive: state.responsive.ranAt ? score(state.responsive.findings) : null,
  };
  const parts = [scores.a11y, scores.seo, scores.responsive].filter((v) => v !== null);
  scores.overall = Math.round(parts.reduce((a, b) => a + b, 0) / parts.length);
  setAudit({ findings, passes, scores, meta: (state.server && state.server.meta) || {} });
}

export function nodeLabel(id) {
  if (!id) return 'Page';
  const s = store.getState();
  const node = T.find(s.tree, id);
  if (!node) return 'Theme';
  const def = s.schema && s.schema.byType[node.type];
  const title = def ? def.title : node.type;
  const a = node.attrs || {};
  if (a.admin_label) return `${title} · ${a.admin_label}`;
  for (const k of ['text', 'title', 'heading', 'label', 'content']) {
    if (typeof a[k] === 'string' && a[k].trim()) {
      const t = a[k].replace(/<[^>]+>/g, '').trim();
      if (t) return `${title} · ${t.length > 32 ? t.slice(0, 32) + '…' : t}`;
    }
  }
  return title;
}

/* ------------------------------------------------------------------------
 * Responsive.
 * ---------------------------------------------------------------------- */

export async function runResponsive() {
  if (state.responsive.status === 'running') return;
  setAudit({ responsive: { ...state.responsive, status: 'running', progress: { index: 0, total: 8, width: null } } });
  sweeping = true;
  try {
    const res = await sweep((progress) => setAudit({ responsive: { ...state.responsive, progress } }));
    const findings = res.findings.map((f) => ({ ...f, label: nodeLabel(f.node) }));
    setAudit({ responsive: { status: 'done', progress: null, perWidth: res.perWidth, ranAt: Date.now(), findings } });
  } catch (e) {
    setAudit({ responsive: { ...state.responsive, status: 'idle', progress: null } });
    store.toast('Responsive check failed', 'error');
  }
  sweeping = false;
  compute();
}

/* ------------------------------------------------------------------------
 * Showing and fixing.
 * ---------------------------------------------------------------------- */

/** Flash an outline around an element in the canvas. */
export function flash(el) {
  const doc = canvasDoc();
  if (!doc || !el || !el.isConnected) return;
  if (!doc.getElementById('brik-audit-style')) {
    const style = doc.createElement('style');
    style.id = 'brik-audit-style';
    style.textContent =
      '#brik-audit-flash{position:absolute;z-index:2147483646;pointer-events:none;border-radius:6px;box-shadow:0 0 0 2px #f59e0b,0 0 0 6px rgb(245 158 11 / .35);animation:brik-audit-flash 1.8s ease-out forwards}' +
      '@keyframes brik-audit-flash{0%,60%{opacity:1}100%{opacity:0}}' +
      '@media (prefers-reduced-motion: reduce){#brik-audit-flash{animation-duration:2.4s}}';
    doc.head.appendChild(style);
  }
  const old = doc.getElementById('brik-audit-flash');
  if (old) old.remove();
  const win = doc.defaultView;
  const r = el.getBoundingClientRect();
  const box = doc.createElement('div');
  box.id = 'brik-audit-flash';
  Object.assign(box.style, { top: `${r.top + win.scrollY - 3}px`, left: `${r.left + win.scrollX - 3}px`, width: `${r.width + 6}px`, height: `${r.height + 6}px` });
  doc.body.appendChild(box);
  setTimeout(() => box.remove(), 2000);
}

function target(f) {
  if (f.el && f.el.isConnected) return f.el;
  return f.node ? element(f.node) : null;
}

/** Select the element of a finding, scroll it into view and flash it. */
export async function show(f, width = null) {
  if (width) {
    await setWidth(width);
  }
  if (f.node) store.select(f.node);
  const el = target(f);
  if (!el) return;
  el.scrollIntoView({ block: 'center', behavior: 'smooth' });
  setTimeout(() => flash(target(f)), 380);
}

/** Apply one fix as a single undoable change. */
export function applyFix(f, fix, value) {
  if (!f.node || !T.find(store.getState().tree, f.node)) {
    store.toast('That element no longer exists', 'error');
    return;
  }
  const patch = { ...(fix.patch || {}) };
  if (fix.prompt) {
    const v = String(value ?? fix.prompt.value ?? '').trim();
    if (!v) return;
    patch[fix.prompt.key] = v;
  }
  store.setAttrs(f.node, patch, `Audit: ${fix.label}`);
  store.toast(`${fix.label} — undo with ⌘Z`, 'success');
  // Drop the finding right away; the scheduled re-run confirms it.
  const drop = (list) => list.filter((x) => x !== f && !(key(x) === key(f) && x.message === f.message));
  setAudit({
    canvas: drop(state.canvas),
    server: state.server ? { ...state.server, findings: drop(state.server.findings) } : state.server,
  });
  compute();
}

/** Every safe one-click fix in a category. */
export function safeFixes(cat) {
  return state.findings[cat].filter((f) => f.node && (f.fixes || []).some((x) => x.safe && !x.prompt));
}

export function applySafe(cat) {
  const list = safeFixes(cat);
  if (!list.length) return;
  const s = store.getState();
  let tree = s.tree;
  const ids = new Set();
  for (const f of list) {
    const fix = f.fixes.find((x) => x.safe && !x.prompt);
    if (!T.find(tree, f.node)) continue;
    tree = T.update(tree, f.node, (n) => {
      const attrs = { ...(n.attrs || {}) };
      for (const [k, v] of Object.entries(fix.patch)) {
        if (v === null || v === undefined || v === '') delete attrs[k];
        else attrs[k] = v;
      }
      return { ...n, attrs };
    });
    ids.add(f.node);
  }
  store.commit(`Audit: ${list.length} fixes`, tree, { ids: [...ids] });
  store.toast(`Applied ${list.length} fix${list.length > 1 ? 'es' : ''} — undo with ⌘Z`, 'success');
}

/* ------------------------------------------------------------------------
 * Automatic re-runs after edits.
 * ---------------------------------------------------------------------- */

let timer = null;
let lastTree = null;
let sweeping = false;
let wasReady = false;

export function startAuto() {
  store.subscribe(() => {
    const s = store.getState();
    if (s.canvasReady && !wasReady) {
      wasReady = true;
      schedule(1200);
    }
    if (s.tree !== lastTree) {
      const first = lastTree === null;
      lastTree = s.tree;
      if (!first && wasReady) schedule(1500);
    }
  });
}

function schedule(ms) {
  clearTimeout(timer);
  timer = setTimeout(() => {
    if (sweeping) return schedule(1000);
    run({ full: true });
  }, ms);
}
