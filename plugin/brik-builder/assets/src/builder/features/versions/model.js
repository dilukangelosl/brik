// Versions & staging: REST calls and the feature's slice of the builder store (state.ver).
import { api, config } from '../../wp.js';
import { getState, setState, subscribe, commit, toast } from '../../store.js';

const base = () => `/brik/v1/versions/${config.post.id}`;
const stagingBase = () => `/brik/v1/staging/${config.post.id}`;

export const SOURCES = {
  builder: { label: 'Builder', cls: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' },
  mcp: { label: 'MCP', cls: 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300' },
  staging: { label: 'Staging', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300' },
  deploy: { label: 'Deploy', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
  restore: { label: 'Restore', cls: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300' },
  schedule: { label: 'Scheduled', cls: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300' },
};

export function ver() {
  return getState().ver || {};
}

export function setVer(patch) {
  setState((s) => ({ ver: { ...(s.ver || {}), ...(typeof patch === 'function' ? patch(s.ver || {}) : patch) } }));
}

let loading = null;

/** Reload the version list and workflow state. */
export function refresh() {
  if (loading) return loading;
  setVer({ loading: !ver().groups });
  loading = api({ path: base() })
    .then((res) => setVer({ groups: res.groups, state: res.state, loading: false, error: null }))
    .catch((e) => setVer({ loading: false, error: e.message || 'Could not load versions' }))
    .finally(() => (loading = null));
  return loading;
}

export function setWorkflow(state) {
  if (state) setVer({ state });
}

export function getVersion(id) {
  return api({ path: `${base()}/${id}` });
}

export function nameVersion(id, name, pinned) {
  return api({ path: `${base()}/name`, method: 'POST', data: { version_id: id, name, pinned } }).then((v) => (refresh(), v));
}

/** Compare two refs (version id | 'live' | 'staging' | 'current'); 'current' is the editor's tree. */
export function compare(a, b) {
  // While a version is previewed, "current" still means the work it temporarily replaced.
  const s = ver().previewing ? ver().previewing.saved : getState();
  const usesEditor = a === 'current' || b === 'current';
  return api({
    path: `${base()}/compare`,
    method: 'POST',
    data: usesEditor ? { a, b, tree: s.tree, page: s.page } : { a, b },
  });
}

/** Whether the editor's own work has unsaved changes (ignoring a version preview). */
export function isDirty() {
  return ver().previewing ? !!ver().previewing.saved.dirty : !!getState().dirty;
}

export function editingStaging() {
  return !!ver().editingStaging;
}

/** Where restores and staging saves go: the staging copy while it is being edited. */
export function restoreTarget() {
  const st = ver().state || {};
  if (editingStaging()) return 'staging';
  if (getState().status === 'publish' && st.can_deploy === false) return 'staging';
  return 'live';
}

/**
 * Restore a version (or some sections) on the server and bring the editor in line.
 * Section restores are applied on top of what the editor currently shows.
 */
export async function restore(version, sections = null) {
  if (ver().previewing) exitPreview();
  const s = getState();
  const target = restoreTarget();
  const data = { version_id: version.id, target };
  if (sections) {
    data.sections = sections;
    data.tree = s.tree;
  }
  const res = await api({ path: `${base()}/restore`, method: 'POST', data });
  const label = sections ? `Restore ${sections.length === 1 ? 'section' : 'sections'} from v${version.number}` : `Restore v${version.number}`;
  commit(label, res.tree, { full: true }, { page: res.page, selected: null });
  setState({ dirty: false, post: res.post, status: res.post.status });
  if (target === 'staging') setVer({ editingStaging: true });
  setWorkflow(res.state);
  refresh();
  toast(target === 'staging' ? `${label} → staging` : label, 'success');
  return res;
}

/* ------------------------------------------------------------------------
 * Staging.
 * ---------------------------------------------------------------------- */

export async function saveStaging() {
  const s = getState();
  if (ver().previewing) exitPreview();
  setVer({ busy: 'staging' });
  try {
    const state = await api({ path: stagingBase(), method: 'POST', data: { tree: s.tree, page: s.page } });
    setState({ dirty: false });
    setVer({ editingStaging: true, state, busy: null });
    refresh();
    toast('Saved to staging — live page unchanged', 'success');
    return state;
  } catch (e) {
    setVer({ busy: null });
    toast(e.message || 'Could not save to staging', 'error');
    throw e;
  }
}

export async function deploy() {
  setVer({ busy: 'deploy' });
  try {
    if (getState().dirty || !(ver().state || {}).staging) await saveStaging();
    const res = await api({ path: `${stagingBase()}/deploy`, method: 'POST' });
    setState({ post: res.post, status: res.post.status, dirty: false });
    setVer({ editingStaging: false, state: res.state, busy: null });
    refresh();
    toast('Deployed — changes are live', 'success');
    return res;
  } catch (e) {
    setVer({ busy: null });
    toast(e.message || 'Could not deploy', 'error');
    throw e;
  }
}

export async function discardStaging() {
  const state = await api({ path: stagingBase(), method: 'DELETE' });
  if (editingStaging()) {
    const post = await api({ path: `/brik/v1/posts/${config.post.id}` });
    setState({ tree: post.tree, page: post.page, post, dirty: false, selected: null, past: [], future: [], change: { full: true, seq: Math.random() } });
  }
  setVer({ editingStaging: false, state });
  refresh();
  toast('Staging discarded');
}

export async function regenerateLink() {
  const state = await api({ path: `${stagingBase()}/token`, method: 'POST' });
  setVer({ state });
  toast('New preview link created — old links stop working', 'success');
  return state;
}

export async function copyPreviewLink() {
  const st = ver().state;
  if (!st) return;
  try {
    await navigator.clipboard.writeText(st.preview_url);
    toast(st.staging ? 'Preview link copied' : 'Link copied — it shows the live page until you save to staging', st.staging ? 'success' : 'info');
  } catch (e) {
    window.prompt('Preview link', st.preview_url);
  }
}

export async function schedule(action, time, versionId = 0) {
  if (action === 'deploy' && (getState().dirty || !(ver().state || {}).staging)) await saveStaging();
  const res = await api({ path: `/brik/v1/schedule/${config.post.id}`, method: 'POST', data: { action, time, version_id: versionId } });
  setVer({ state: res.state });
  toast(`${action === 'deploy' ? 'Deploy' : 'Rollback'} scheduled for ${res.schedule.local}`, 'success');
  return res;
}

export async function cancelSchedule(id) {
  const state = await api({ path: `/brik/v1/schedule/${config.post.id}/${id}`, method: 'DELETE' });
  setVer({ state });
  toast('Scheduled action cancelled');
}

export async function saveLimit(limit) {
  const res = await api({ path: '/brik/v1/versions/settings', method: 'POST', data: { limit } });
  setVer((v) => ({ state: { ...(v.state || {}), limit: res.limit } }));
}

/* ------------------------------------------------------------------------
 * Previewing a version in the canvas without touching history.
 * ---------------------------------------------------------------------- */

export async function preview(version) {
  const full = version.tree ? version : await getVersion(version.id);
  const s = getState();
  const saved = ver().previewing ? ver().previewing.saved : { tree: s.tree, page: s.page, dirty: s.dirty, past: s.past, future: s.future, selected: s.selected };
  setVer({ previewing: { version: { ...version, ...full }, saved } });
  setState({ tree: full.tree, page: full.page || {}, selected: null, change: { full: true, seq: Math.random() } });
}

export function exitPreview() {
  const p = ver().previewing;
  if (!p) return;
  setVer({ previewing: null });
  setState({ ...p.saved, change: { full: true, seq: Math.random() } });
}

/* ------------------------------------------------------------------------
 * Wiring.
 * ---------------------------------------------------------------------- */

let started = false;

/** Loads state once the app is up; picks up the staging copy for editing. */
export function start() {
  if (started) return;
  started = true;

  // Builder saves go through POST /posts/{id}; guard them while previewing and mark that
  // a save made while editing staging publishes (and clears) the staging copy.
  window.wp.apiFetch.use((options, next) => {
    const path = options.path || '';
    if ((options.method || 'GET').toUpperCase() === 'POST' && path.startsWith(`/brik/v1/posts/${config.post.id}`) && options.data && 'tree' in options.data) {
      if (ver().previewing) return Promise.reject({ message: 'Close the version preview before saving' });
      if (editingStaging()) {
        options = { ...options, data: { ...options.data, brik_clear_staging: true } };
        return next(options).then((res) => {
          setVer({ editingStaging: false });
          return res;
        });
      }
    }
    return next(options);
  });

  refresh().then(() => {
    const st = ver().state;
    if (!st || !st.staging) return;
    api({ path: stagingBase() }).then((full) => {
      if (getState().dirty || !full.tree) return;
      setVer({ editingStaging: true, state: full });
      const apply = () => setState({ tree: full.tree, page: full.page || getState().page, change: { full: true, seq: Math.random() } });
      if (getState().canvasReady) apply();
      else {
        const off = subscribe(() => {
          if (getState().canvasReady) {
            off();
            apply();
          }
        });
      }
    });
  });

  // A save replaces state.post; refresh the list after each one.
  let post = getState().post;
  let timer = null;
  subscribe(() => {
    const s = getState();
    if (s.post !== post) {
      post = s.post;
      clearTimeout(timer);
      timer = setTimeout(refresh, 150);
    }
  });
}

/** The latest version recorded for live and for staging (for "Live"/"Staging" chips). */
export function heads(groups) {
  const out = { live: null, staging: null };
  for (const g of groups || []) {
    for (const v of g.items) {
      if (v.target === 'staging' && !out.staging) out.staging = v.id;
      if (v.target !== 'staging' && !out.live) out.live = v.id;
    }
  }
  return out;
}

/** Local "YYYY-MM-DDTHH:MM" an hour after the site's current time. */
export function inAnHour(nowLocal) {
  const d = nowLocal ? new Date(`${nowLocal}:00`) : new Date();
  d.setMinutes(0, 0, 0);
  d.setHours(d.getHours() + 1);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
