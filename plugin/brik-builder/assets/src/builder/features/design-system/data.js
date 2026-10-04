// Design system data: variables, classes, components and the canvas stylesheet refresh.
import { api, config, useEffect } from '../../wp.js';
import { getState, setState, useStore, toast } from '../../store.js';
import { reload } from '../../canvas.js';

let loading = null;

export function loadDesign(force = false) {
  if (loading && !force) return loading;
  loading = api({ path: '/brik/v1/design' })
    .then((design) => {
      setState({ design });
      return design;
    })
    .catch((e) => {
      loading = null;
      throw e;
    });
  return loading;
}

/** Design data, loaded on first use. */
export function useDesign() {
  const design = useStore((s) => s.design);
  useEffect(() => {
    if (!design) loadDesign().catch(() => {});
  }, []);
  return design;
}

/** Store a design payload from the server and push its CSS into the canvas. */
export function applyPayload(payload) {
  setState((s) => ({
    design: { ...(s.design || {}), ...payload },
    settings: s.settings ? { ...s.settings, classes: payload.classes, variables: payload.saved } : s.settings,
  }));
  if (payload.css !== undefined) pushCss(payload.css);
  return payload;
}

function canvasDoc() {
  const frame = document.querySelector('.bk-canvas-wrap iframe');
  try {
    return frame ? frame.contentDocument : null;
  } catch (e) {
    return null;
  }
}

/**
 * Swap the global stylesheet (tokens, variables, classes) inside the canvas without a reload.
 * Falls back to reloading the canvas when the page doesn't carry it.
 */
export function pushCss(css) {
  const doc = canvasDoc();
  if (!doc || !doc.head) return reload();
  let live = doc.getElementById('brik-ds-live');
  if (!live) {
    live = doc.createElement('style');
    live.id = 'brik-ds-live';
    // Sit right after the stylesheet that printed the global CSS (plugin or theme inline
    // style), so the fresh copy takes its place in the cascade.
    const anchor = [...doc.querySelectorAll('style')].filter((s) => s.textContent.includes('--space-3xs:')).pop();
    if (anchor) anchor.after(live);
    else doc.head.appendChild(live);
  }
  live.textContent = css;
}

export async function refreshCss() {
  const res = await api({ path: '/brik/v1/design/css' });
  pushCss(res.css);
}

/** Re-render the whole canvas (class names in markup changed). */
export function rerender() {
  setState({ change: { full: true, seq: Math.random() } });
}

export async function saveVariables(variables) {
  try {
    return applyPayload(await api({ path: '/brik/v1/design/variables', method: 'POST', data: { variables } }));
  } catch (e) {
    toast(e.message || 'Could not save variables', 'error');
    throw e;
  }
}

export async function saveClass(id, data) {
  try {
    return applyPayload(await api({ path: `/brik/v1/design/classes/${id}`, method: 'POST', data }));
  } catch (e) {
    toast(e.message || 'Could not save class', 'error');
    throw e;
  }
}

export async function deleteClass(id) {
  return applyPayload(await api({ path: `/brik/v1/design/classes/${id}`, method: 'DELETE' }));
}

export async function duplicateClass(id) {
  return applyPayload(await api({ path: `/brik/v1/design/classes/${id}/duplicate`, method: 'POST' }));
}

export function classesOf(s = getState()) {
  return (s.design && s.design.classes) || (s.settings && s.settings.classes) || {};
}

export function useClasses() {
  return useStore((s) => classesOf(s));
}

/* ------------------------------------------------------------------------
 * Usage (site-wide counts), refreshed on demand.
 * ---------------------------------------------------------------------- */

export async function loadUsage() {
  const usage = await api({ path: '/brik/v1/design/usage' });
  setState({ designUsage: usage });
  return usage;
}

export function useUsage() {
  const usage = useStore((s) => s.designUsage);
  useEffect(() => {
    if (!usage) loadUsage().catch(() => {});
  }, []);
  return usage;
}

/* ------------------------------------------------------------------------
 * Components.
 * ---------------------------------------------------------------------- */

let componentsLoading = null;

export function loadComponents(force = false) {
  if (componentsLoading && !force) return componentsLoading;
  componentsLoading = api({ path: '/brik/v1/design/components' })
    .then((res) => {
      setState({ components: res.items });
      return res.items;
    })
    .catch(() => {
      componentsLoading = null;
      return [];
    });
  return componentsLoading;
}

export function useComponents() {
  const items = useStore((s) => s.components);
  useEffect(() => {
    if (!items) loadComponents();
  }, []);
  return items;
}

const masters = new Map();

/** Master tree and override policy of a component (cached; refreshed when the tab regains focus). */
export function loadMaster(id, force = false) {
  if (!force && masters.has(id)) return masters.get(id);
  const p = api({ path: `/brik/v1/design/components/${id}` }).then((res) => {
    setState((s) => ({ masters: { ...(s.masters || {}), [id]: res } }));
    return res;
  });
  p.catch(() => masters.delete(id));
  masters.set(id, p);
  return p;
}

export function useMaster(id) {
  const master = useStore((s) => (s.masters || {})[id]);
  useEffect(() => {
    if (id) loadMaster(id).catch(() => {});
  }, [id]);
  return master;
}

// The master may be edited in another tab: refresh what we show when coming back.
window.addEventListener('focus', () => {
  if (!masters.size) return;
  const ids = [...masters.keys()];
  masters.clear();
  ids.forEach((id) => loadMaster(id).catch(() => {}));
  loadComponents(true);
  setState({ change: { full: true, seq: Math.random() } });
});

export function builderUrl(id) {
  return `${config.adminUrl}post.php?post=${id}&action=brik`;
}

/** The document being edited is a component master. */
export function editingComponent() {
  const lib = config.post && config.post.library;
  return lib && lib.kind === 'component' ? lib : null;
}

/** Elements using a class: stored usage elsewhere plus the live (unsaved) tree of this page. */
export function classCount(usage, id, tree) {
  const postId = config.post && config.post.id;
  let live = 0;
  const walk = (nodes) =>
    nodes.forEach((n) => {
      if (n.attrs && Array.isArray(n.attrs.classes) && n.attrs.classes.includes(id)) live++;
      if (n.children) walk(n.children);
    });
  walk(tree || []);
  if (!usage || !usage.classes) return live;
  const here = (usage.class_posts && usage.class_posts[id] && usage.class_posts[id][postId]) || 0;
  return (usage.classes[id] || 0) - here + live;
}
