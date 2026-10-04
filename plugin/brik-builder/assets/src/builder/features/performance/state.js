// Shared state for the performance panel and the top-bar badge.
import { api, useSyncExternalStore } from '../../wp.js';
import * as store from '../../store.js';

let state = { report: null, loading: false, error: null, at: null, stale: false };
const listeners = new Set();

function set(patch) {
  state = { ...state, ...patch };
  listeners.forEach((l) => l());
}

export function usePerf(selector = (s) => s) {
  return useSyncExternalStore(
    (l) => {
      listeners.add(l);
      return () => listeners.delete(l);
    },
    () => selector(state)
  );
}

export function getPerf() {
  return state;
}

let pending = null;

/**
 * Analyze the page. With current = true the builder's unsaved tree is analyzed;
 * otherwise the saved page.
 */
export function analyze(current = false) {
  if (pending) return pending;
  const s = store.getState();
  set({ loading: true, error: null });
  const path = `/brik/v1/perf/${s.post.id}`;
  pending = (current ? api({ path, method: 'POST', data: { tree: s.tree } }) : api({ path }))
    .then((report) => set({ report, loading: false, at: Date.now(), stale: false }))
    .catch((e) => set({ loading: false, error: e.message || 'Could not analyze the page' }))
    .finally(() => {
      pending = null;
    });
  return pending;
}

export function clean(dryRun) {
  const s = store.getState();
  return api({ path: `/brik/v1/perf/${s.post.id}/clean`, method: 'POST', data: { dry_run: dryRun, tree: s.tree } });
}

export function markStale() {
  if (state.report && !state.stale) set({ stale: true });
}

// Re-analyze after every save; mark the report stale while editing.
let wasSaving = false;
let lastTree = null;
export function watch() {
  store.subscribe(() => {
    const s = store.getState();
    if (wasSaving && !s.saving && !s.dirty) analyze(false);
    wasSaving = s.saving;
    if (lastTree && s.tree !== lastTree) markStale();
    lastTree = s.tree;
  });
  // A first report shortly after the builder opens (the canvas loads first).
  setTimeout(() => analyze(false), 2500);
}
