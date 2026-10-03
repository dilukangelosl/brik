import { on, mount } from '../core.js';

// Listings render on the server with the URL's bf_{key}_* parameters, so a reload or a shared
// link already shows the right results. This script only swaps results in place.

const inCanvas = () => document.body.classList.contains('brik-canvas-mode');
const restBase = () => (window.brikFront && window.brikFront.rest) || '/wp-json/brik/v1/';
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const prefix = (key) => `bf_${key}_`;
const controllers = new Map();

function urlParams(key) {
  const out = {};
  new URLSearchParams(window.location.search).forEach((v, k) => {
    if (k.startsWith(prefix(key)) && k !== `${prefix(key)}page`) out[k] = v;
  });
  return out;
}

function urlFor(key, params, page) {
  const url = new URL(window.location.href);
  [...url.searchParams.keys()].filter((k) => k.startsWith(prefix(key))).forEach((k) => url.searchParams.delete(k));
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  if (page > 1) url.searchParams.set(`${prefix(key)}page`, String(page));
  return url;
}

function controller(root) {
  const key = root.dataset.brikListing;
  const results = root.querySelector('.brik-listing-results');
  let params = urlParams(key);
  let page = Number(root.dataset.page) || 1;
  let request = 0;
  let observer = null;

  const body = (p) => {
    const data = {
      post_id: Number(root.dataset.post),
      node_id: root.dataset.node,
      filters: params,
      page: p,
      page_url: urlFor(key, params, 1).toString(),
    };
    if (root.dataset.current) data.current = Number(root.dataset.current);
    if (root.dataset.main) {
      try {
        data.main = JSON.parse(root.dataset.main);
      } catch (e) {
        /* ignore */
      }
    }
    return data;
  };

  async function load(p) {
    const id = ++request;
    root.setAttribute('aria-busy', 'true');
    try {
      const res = await fetch(`${restBase()}listing`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body(p)),
      });
      if (!res.ok) throw new Error(res.statusText);
      const json = await res.json();
      return id === request ? json : null;
    } finally {
      if (id === request) root.removeAttribute('aria-busy');
    }
  }

  function setMeta(json) {
    page = json.page;
    root.dataset.page = String(json.page);
    root.dataset.pages = String(json.pages);
    root.dataset.total = String(json.total);
    root.dispatchEvent(new CustomEvent('brik:listing-updated', { bubbles: true, detail: { key, total: json.total, pages: json.pages, page: json.page } }));
  }

  function replace(json) {
    results.innerHTML = json.html;
    results.classList.remove('is-swapped');
    void results.offsetWidth;
    results.classList.add('is-swapped');
    setMeta(json);
    mount(results);
    watch();
  }

  function append(json) {
    const tpl = document.createElement('template');
    tpl.innerHTML = json.html;
    const items = results.querySelector('.brik-listing-items');
    const fresh = tpl.content.querySelector('.brik-listing-items');
    if (!items || !fresh) return replace(json);
    const added = [...fresh.children];
    added.forEach((el, i) => {
      el.style.setProperty('--brik-i', String(i));
      el.classList.add('is-entering');
      items.append(el);
    });
    fresh.remove();
    results.querySelectorAll(':scope > .brik-listing-more, :scope > .brik-pagination').forEach((el) => el.remove());
    results.append(...tpl.content.childNodes);
    setMeta(json);
    mount(items);
    watch();
    added[0]?.querySelector('a[href]:not([tabindex="-1"])')?.focus({ preventScroll: true });
  }

  const fallback = (p) => {
    window.location.href = urlFor(key, params, p).toString();
  };

  async function refresh(next) {
    params = next;
    window.history.replaceState(window.history.state, '', urlFor(key, params, 1));
    try {
      const json = await load(1);
      if (json) replace(json);
    } catch (e) {
      fallback(1);
    }
  }

  async function go(p) {
    try {
      const json = await load(p);
      if (!json) return;
      replace(json);
      window.history.replaceState(window.history.state, '', urlFor(key, params, json.page));
      const top = root.getBoundingClientRect().top;
      if (top < 0) root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
    } catch (e) {
      fallback(p);
    }
  }

  async function more(link) {
    if (link.getAttribute('aria-busy') === 'true') return;
    link.setAttribute('aria-busy', 'true');
    try {
      const json = await load(page + 1);
      if (json) append(json);
      else link.removeAttribute('aria-busy');
    } catch (e) {
      fallback(page + 1);
    }
  }

  // Infinite scroll clicks the load-more link when it comes near the viewport.
  function watch() {
    if (root.dataset.pagination !== 'infinite' || !('IntersectionObserver' in window)) return;
    if (observer) observer.disconnect();
    const link = results.querySelector('[data-brik-listing-more]');
    if (!link) return;
    observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) more(link);
      },
      { rootMargin: '400px 0px' },
    );
    observer.observe(link);
  }

  root.addEventListener('click', (e) => {
    if (inCanvas()) {
      if (e.target.closest('a')) e.preventDefault();
      return;
    }
    const link = e.target.closest('[data-brik-listing-more]');
    if (link) {
      e.preventDefault();
      more(link);
      return;
    }
    const pageLink = e.target.closest('.brik-pagination a[href]');
    if (pageLink && results.contains(pageLink)) {
      const n = Number(new URL(pageLink.href).searchParams.get(`${prefix(key)}page`)) || 1;
      e.preventDefault();
      go(n);
    }
  });

  watch();
  return { key, refresh, params: () => params };
}

function carousel(root) {
  const track = root.querySelector('.brik-listing-items--carousel');
  const prev = root.querySelector('[data-brik-listing-prev]');
  const next = root.querySelector('[data-brik-listing-next]');
  if (!track || !prev || !next) return;
  const step = () => {
    const item = track.firstElementChild;
    return item ? item.getBoundingClientRect().width + parseFloat(getComputedStyle(track).columnGap || '0') : track.clientWidth;
  };
  const update = () => {
    prev.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
  };
  prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: reduced() ? 'auto' : 'smooth' }));
  next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: reduced() ? 'auto' : 'smooth' }));
  track.addEventListener('scroll', update, { passive: true });
  update();
}

on('[data-brik-listing]', (root) => {
  carousel(root);
  if (inCanvas()) return;
  controllers.set(root.dataset.brikListing, controller(root));
});

/* ------------------------------------------------------------------------
 * Filter bar.
 * ---------------------------------------------------------------------- */

function rangeTrack(range) {
  const min = range.querySelector('[data-lf-min]');
  const max = range.querySelector('[data-lf-max]');
  const lo = Number(min.min);
  const span = Number(min.max) - lo || 1;
  const num = (v) => (range.dataset.plain ? String(v) : Number(v).toLocaleString(document.documentElement.lang || undefined));
  const fmt = (v) => `${range.dataset.prefix || ''}${num(v)}${range.dataset.suffix || ''}`;
  const track = range.querySelector('.brik-lf-range-track');
  track.style.setProperty('--lo', `${((Number(min.value) - lo) / span) * 100}%`);
  track.style.setProperty('--hi', `${((Number(max.value) - lo) / span) * 100}%`);
  range.querySelector('[data-lf-out="min"]').textContent = fmt(min.value);
  range.querySelector('[data-lf-out="max"]').textContent = fmt(max.value);
}

function collect(form, key) {
  const out = {};
  const data = new FormData(form);
  for (const [name, value] of data.entries()) {
    const k = name.replace(/\[\]$/, '');
    if (!k.startsWith(prefix(key)) || typeof value !== 'string' || value.trim() === '') continue;
    out[k] = out[k] ? `${out[k]},${value}` : value.trim();
  }
  // A range left at its bounds is not a filter.
  form.querySelectorAll('[data-lf-range] input[type="range"]').forEach((input) => {
    const atBound = input.matches('[data-lf-min]') ? input.value === input.min : input.value === input.max;
    if (atBound) delete out[input.name];
  });
  return out;
}

function clear(form) {
  form.querySelectorAll('input, select').forEach((el) => {
    if (el.type === 'hidden' || !el.name || !el.name.startsWith('bf_')) return;
    if (el.type === 'checkbox') el.checked = false;
    else if (el.type === 'radio') el.checked = el.value === '';
    else if (el.type === 'range') el.value = el.matches('[data-lf-min]') ? el.min : el.max;
    else el.value = '';
  });
  form.querySelectorAll('[data-lf-range]').forEach(rangeTrack);
}

on('[data-brik-listing-filter]', (form) => {
  const key = form.dataset.brikListingFilter;
  const instant = form.dataset.apply !== 'button';
  const reset = form.querySelector('[data-lf-reset]');

  if (inCanvas()) {
    form.addEventListener('submit', (e) => e.preventDefault());
    if (reset) reset.addEventListener('click', (e) => e.preventDefault());
    return;
  }
  if (instant) form.querySelectorAll('[data-lf-js-hide]').forEach((el) => (el.hidden = true));

  let timer;
  const apply = () => {
    clearTimeout(timer);
    const own = collect(form, key);
    if (reset) reset.toggleAttribute('data-idle', !Object.keys(own).length);
    const ctl = controllers.get(key);
    if (!ctl) {
      form.submit();
      return;
    }
    // Keep values set by another filter bar for the same listing.
    const names = new Set([...form.elements].map((el) => (el.name || '').replace(/\[\]$/, '')));
    const params = Object.fromEntries(Object.entries(ctl.params()).filter(([k]) => !names.has(k)));
    ctl.refresh({ ...params, ...own });
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    apply();
  });

  form.addEventListener('input', (e) => {
    const range = e.target.closest('[data-lf-range]');
    if (range) {
      const min = range.querySelector('[data-lf-min]');
      const max = range.querySelector('[data-lf-max]');
      if (Number(min.value) > Number(max.value)) {
        if (e.target === min) min.value = max.value;
        else max.value = min.value;
      }
      rangeTrack(range);
      return;
    }
    if (instant && e.target.matches('[data-lf-search]')) {
      clearTimeout(timer);
      timer = setTimeout(apply, 350);
    }
  });

  form.addEventListener('change', (e) => {
    if (!instant || e.target.matches('[data-lf-search]')) return;
    apply();
  });

  if (reset) {
    reset.addEventListener('click', (e) => {
      e.preventDefault();
      clear(form);
      apply();
    });
  }
});
