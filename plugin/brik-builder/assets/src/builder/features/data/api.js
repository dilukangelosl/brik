// REST helpers for the data feature, with small caches so pickers open instantly the second time.
import { api } from '../../wp.js';
import { getState } from '../../store.js';

const cache = new Map();

function cached(key, load) {
  if (!cache.has(key)) {
    const p = load().catch((e) => {
      cache.delete(key);
      throw e;
    });
    cache.set(key, p);
  }
  return cache.get(key);
}

export function postId() {
  const s = getState();
  return s.post ? s.post.id : 0;
}

/** Tag tree with previews for the edited post (or another post type). */
export function loadSources(postType = '') {
  const id = postId();
  const q = new URLSearchParams({ post_id: id || '', post_type: postType || '' }).toString();
  return cached(`sources:${id}:${postType}`, () => api({ path: `/brik/v1/data/sources?${q}` }));
}

/** Query builder fields, sortable fields and operators for a post type. */
export function loadFields(postType) {
  const q = new URLSearchParams({ post_type: postType, post_id: postId() || '' }).toString();
  return cached(`fields:${postType}`, () => api({ path: `/brik/v1/data/fields?${q}` }));
}

export function loadConditionTypes() {
  return cached('conditions', () => api({ path: `/brik/v1/data/conditions?post_id=${postId() || ''}` }));
}

export function previewQuery(query) {
  return api({ path: '/brik/v1/data/query/preview', method: 'POST', data: { query, post_id: postId() } });
}

/** Turn API errors into a readable message. */
export function errorText(e) {
  return (e && (e.message || (e.data && e.data.message))) || 'Something went wrong';
}
