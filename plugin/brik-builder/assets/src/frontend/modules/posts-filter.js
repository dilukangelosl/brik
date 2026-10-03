import { on, mount } from '../core.js';

function applyFilter(root, slug) {
  root.querySelectorAll('.brik-posts-items > [data-terms]').forEach((item) => {
    const match = !slug || item.dataset.terms.split(' ').includes(slug);
    if (match && item.hidden) {
      item.classList.remove('is-entering');
      void item.offsetWidth;
      item.classList.add('is-entering');
    }
    item.hidden = !match;
  });
}

function activeSlug(root) {
  const btn = root.querySelector('[data-brik-filter][aria-pressed="true"]');
  return btn ? btn.dataset.brikFilter : '';
}

function setupFilter(root) {
  const bar = root.querySelector('.brik-posts-filter');
  if (!bar) return;
  bar.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-brik-filter]');
    if (!btn) return;
    bar.querySelectorAll('[data-brik-filter]').forEach((b) => b.setAttribute('aria-pressed', b === btn ? 'true' : 'false'));
    applyFilter(root, btn.dataset.brikFilter);
  });
}

// "Load more" is a plain link to the next page; with JS the next page's items are appended in place.
function setupLoadMore(root) {
  const id = root.dataset.brikPosts;
  root.addEventListener('click', async (e) => {
    const link = e.target.closest('[data-brik-loadmore]');
    if (!link || document.body.classList.contains('brik-canvas-mode')) return;
    e.preventDefault();
    if (link.getAttribute('aria-busy') === 'true') return;
    link.setAttribute('aria-busy', 'true');
    try {
      const res = await fetch(link.href, { credentials: 'same-origin' });
      if (!res.ok) throw new Error(res.statusText);
      const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
      const next = doc.querySelector(`[data-brik-posts="${CSS.escape(id)}"]`);
      if (!next) throw new Error('Posts not found on the next page');

      const items = root.querySelector('.brik-posts-items');
      const added = [...next.querySelectorAll('.brik-posts-items > *')];
      added.forEach((el) => {
        el.classList.add('is-entering');
        items.append(el);
      });

      // Terms that only appear on later pages get their own filter buttons.
      const bar = root.querySelector('.brik-posts-filter');
      next.querySelectorAll('.brik-posts-filter [data-brik-filter]').forEach((btn) => {
        if (bar && !bar.querySelector(`[data-brik-filter="${CSS.escape(btn.dataset.brikFilter)}"]`)) {
          btn.setAttribute('aria-pressed', 'false');
          bar.append(btn);
        }
      });
      applyFilter(root, activeSlug(root));

      const more = next.querySelector('[data-brik-loadmore]');
      if (more) {
        link.href = more.href;
        link.removeAttribute('aria-busy');
      } else {
        link.closest('.brik-posts-more')?.remove();
      }
      mount(items);
      added[0]?.querySelector('a[href]:not([tabindex="-1"])')?.focus({ preventScroll: true });
    } catch (err) {
      window.location.href = link.href;
    }
  });
}

on('[data-brik-posts]', (root) => {
  setupFilter(root);
  setupLoadMore(root);
});
