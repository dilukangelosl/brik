import { api, config } from './wp.js';
import { getState, setState, toast } from './store.js';

export async function loadSchema() {
  const schema = await api({ path: '/brik/v1/schema' });
  schema.byType = {};
  for (const mod of schema.modules) {
    schema.byType[mod.type] = mod;
  }
  return schema;
}

export async function loadSettings() {
  return api({ path: '/brik/v1/settings' });
}

export async function saveSettings(changes) {
  const settings = await api({ path: '/brik/v1/settings', method: 'POST', data: changes });
  setState({ settings });
  return settings;
}

export function render(tree, ids = []) {
  const s = getState();
  return api({
    path: '/brik/v1/render',
    method: 'POST',
    data: { post_id: s.post.id, tree, ids, page: s.page },
  });
}

export async function save(status) {
  const s = getState();
  if (s.saving) return;
  setState({ saving: true });
  try {
    const data = { tree: s.tree, page: s.page, title: s.title };
    if (status) data.status = status;
    if (s.area) {
      data.area = s.area;
      data.conditions = s.conditions;
    }
    const post = await api({ path: `/brik/v1/posts/${s.post.id}`, method: 'POST', data });
    setState({ post, status: post.status, dirty: false, saving: false });
    toast(status === 'publish' && s.status !== 'publish' ? 'Published' : 'Saved', 'success');
    try {
      localStorage.removeItem(backupKey());
    } catch (e) {}
  } catch (e) {
    setState({ saving: false });
    toast(e.message || 'Could not save', 'error');
  }
}

export function backupKey() {
  return `brik-backup-${config.post.id}`;
}

export function library(kind = '') {
  return api({ path: `/brik/v1/library${kind ? `?kind=${kind}` : ''}` });
}

export function libraryItem(id, context = 'root') {
  return api({ path: `/brik/v1/library/${id}?context=${context}` });
}

export function saveToLibrary(title, kind, tree, global = false) {
  return api({ path: '/brik/v1/library', method: 'POST', data: { title, kind, tree, global } });
}

export function deleteLibraryItem(id) {
  return api({ path: `/brik/v1/library/${id}`, method: 'DELETE' });
}

export function search(params) {
  const q = new URLSearchParams(params).toString();
  return api({ path: `/brik/v1/search?${q}` });
}

let icons = null;
let brands = null;
let aliases = null;
let iconTags = null;

export async function loadIcons() {
  if (!icons) {
    const base = config.iconsUrl.replace('icons.json', '');
    const json = (u) => fetch(u).then((r) => r.json());
    [icons, brands, aliases] = await Promise.all([json(config.iconsUrl), json(config.brandsUrl), json(`${base}icon-aliases.json`)]);
  }
  return { icons, brands, aliases };
}

export async function loadIconTags() {
  if (!iconTags) iconTags = await fetch(config.tagsUrl).then((r) => r.json());
  return iconTags;
}

export function iconsSync() {
  return icons;
}
