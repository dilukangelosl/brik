import { on, mount } from '../core.js';

// WooCommerce shop behaviour: AJAX add to cart, the mini cart, product grids with filters,
// sorting and pagination, quick view, and quantity steppers on the cart page. Everything
// renders on the server first (and works without JavaScript); this only swaps markup in place.

const cfg = () => window.brikWoo || {};
const inCanvas = () => document.body.classList.contains('brik-canvas-mode');
const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const restBase = () => (window.brikFront && window.brikFront.rest) || '/wp-json/brik/v1/';
const t = (key, fallback) => (cfg().i18n && cfg().i18n[key]) || fallback;
const jq = () => (typeof window.jQuery === 'function' ? window.jQuery : null);

function wcAjax(endpoint) {
  const tpl = cfg().wcAjax || '/?wc-ajax=%%endpoint%%';
  return tpl.replace('%%endpoint%%', endpoint);
}

async function post(endpoint, data) {
  const body = data instanceof FormData ? data : new URLSearchParams(data);
  const res = await fetch(wcAjax(endpoint), { method: 'POST', credentials: 'same-origin', body, headers: { Accept: 'application/json' } });
  const json = await res.json().catch(() => null);
  if (!json) throw new Error(res.statusText || 'Request failed');
  return json;
}

/* ------------------------------------------------------------------------
 * Toasts.
 * ---------------------------------------------------------------------- */

function toast(message, tone = 'default') {
  let region = document.querySelector('.brik-woo-toasts');
  if (!region) {
    region = document.createElement('div');
    region.className = 'brik brik-woo-toasts';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    document.body.append(region);
  }
  const el = document.createElement('div');
  el.className = `brik-woo-toast is-${tone}`;
  el.textContent = message;
  region.append(el);
  setTimeout(() => {
    el.classList.add('is-leaving');
    setTimeout(() => el.remove(), 300);
  }, 4200);
}

/* ------------------------------------------------------------------------
 * Fragments and cart events.
 * ---------------------------------------------------------------------- */

let selfTrigger = false;

function applyFragments(fragments) {
  if (!fragments || typeof fragments !== 'object') return;
  Object.entries(fragments).forEach(([selector, html]) => {
    let targets = [];
    try {
      targets = document.querySelectorAll(selector);
    } catch (e) {
      return;
    }
    targets.forEach((el) => {
      const tpl = document.createElement('template');
      tpl.innerHTML = String(html).trim();
      const fresh = tpl.content.firstElementChild;
      if (!fresh) return;
      // Keep the count badge animation: bump only when the number changed.
      if (el.matches('.brik-mc-count') && el.dataset.count !== fresh.dataset.count) fresh.classList.add('is-bumped');
      el.replaceWith(fresh);
    });
  });
  afterFragments();
}

function afterFragments() {
  document.querySelectorAll('[data-brik-mini-cart]').forEach((root) => {
    const content = root.querySelector('.brik-mc-content');
    if (!content) return;
    const head = root.querySelector('[data-mc-head-count]');
    if (head && content.dataset.count !== undefined) head.textContent = content.dataset.count;
    shippingBar(root, content);
  });
  const count = document.querySelector('.brik-mc-count');
  if (count) {
    document.querySelectorAll('[data-brik-mini-cart] .brik-mc-trigger').forEach((a) => {
      const n = Number(count.dataset.count || 0);
      a.setAttribute('aria-label', (n === 1 ? t('cartOne', 'Cart, %d item') : t('cartMany', 'Cart, %d items')).replace('%d', n));
    });
  }
}

function emit(type, detail = {}) {
  document.dispatchEvent(new CustomEvent('brik:cart', { detail: { type, ...detail } }));
  const $ = jq();
  if (!$) return;
  selfTrigger = true;
  try {
    if (type === 'added') $(document.body).trigger('added_to_cart', [detail.fragments, detail.cart_hash, undefined]);
    if (type === 'removed') $(document.body).trigger('removed_from_cart', [detail.fragments, detail.cart_hash, undefined]);
  } finally {
    selfTrigger = false;
  }
}

let refreshTimer;
function refreshFragments() {
  clearTimeout(refreshTimer);
  refreshTimer = setTimeout(async () => {
    try {
      const json = await post('get_refreshed_fragments', { time: Date.now() });
      applyFragments(json.fragments);
    } catch (e) {
      /* the next cart change will try again */
    }
  }, 150);
}

// Cart changes made by WooCommerce itself or other plugins.
function bridgeWooEvents() {
  const $ = jq();
  if (!$ || bridgeWooEvents.done) return;
  bridgeWooEvents.done = true;
  $(document.body).on('added_to_cart removed_from_cart', (e, fragments) => {
    if (selfTrigger) return;
    if (fragments) applyFragments(fragments);
    else refreshFragments();
    if (e.type === 'added_to_cart') openOnAdd();
  });
  $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', () => afterFragments());
  $(document.body).on('updated_wc_div updated_cart_totals wc_fragment_refresh', () => refreshFragments());
  // WooCommerce replaces the cart and checkout markup after its own AJAX updates.
  $(document.body).on('updated_wc_div updated_checkout', () => mount(document));
}

/* ------------------------------------------------------------------------
 * Add to cart.
 * ---------------------------------------------------------------------- */

function setState(btn, state) {
  if (!btn) return;
  btn.dataset.state = state;
  btn.toggleAttribute('aria-busy', state === 'loading');
  if (state === 'added') {
    clearTimeout(btn.brikStateTimer);
    btn.brikStateTimer = setTimeout(() => {
      btn.dataset.state = '';
    }, 2000);
  }
}

async function addToCart(data, button) {
  setState(button, 'loading');
  try {
    const json = await post('brik_add_to_cart', data);
    if (json.error) {
      setState(button, '');
      if (json.product_url && !json.message) {
        window.location.href = json.product_url;
        return false;
      }
      toast(json.message || t('error', 'Something went wrong. Please try again.'), 'error');
      return false;
    }
    applyFragments(json.fragments);
    setState(button, 'added');
    emit('added', { fragments: json.fragments, cart_hash: json.cart_hash, button });
    openOnAdd();
    return true;
  } catch (e) {
    setState(button, '');
    toast(t('error', 'Something went wrong. Please try again.'), 'error');
    return false;
  }
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest?.('[data-brik-add-to-cart]');
  if (!btn || inCanvas()) {
    if (btn) e.preventDefault();
    return;
  }
  e.preventDefault();
  if (btn.dataset.state === 'loading') return;
  addToCart({ product_id: btn.dataset.brikAddToCart, quantity: btn.dataset.quantity || 1 }, btn);
});

/* ------------------------------------------------------------------------
 * Mini cart.
 * ---------------------------------------------------------------------- */

function formatPrice(root, amount) {
  let p = {};
  try {
    p = JSON.parse(root.dataset.price || '{}');
  } catch (e) {
    p = {};
  }
  const decimals = Number.isFinite(p.decimals) ? p.decimals : 2;
  const [whole, frac] = Math.abs(amount).toFixed(decimals).split('.');
  const num = whole.replace(/\B(?=(\d{3})+(?!\d))/g, p.thousand ?? ',') + (frac ? (p.dec ?? '.') + frac : '');
  return (p.format || '%1$s%2$s').replace('%1$s', p.symbol || '').replace('%2$s', num).replace(/&nbsp;/g, ' ');
}

// The drawer markup comes from one shared fragment; a module with its own threshold redraws the bar.
function shippingBar(root, content) {
  const slot = content.querySelector('.brik-mc-ship-slot');
  if (!slot) return;
  const threshold = Number(root.dataset.threshold || 0);
  const amount = Number(content.dataset.amount || 0);
  const current = slot.querySelector('.brik-mc-ship');
  if (!threshold) {
    slot.hidden = true;
    slot.classList.add('hidden');
    return;
  }
  slot.hidden = false;
  slot.classList.remove('hidden');
  if (current && Number(current.dataset.threshold) === threshold) return;
  const left = threshold - amount;
  const pct = Math.min(100, Math.round((amount / threshold) * 1000) / 10);
  const text = left > 0 ? t('shipLeft', 'Add %s more for free shipping').replace('%s', `<strong class="font-semibold text-foreground">${formatPrice(root, left)}</strong>`) : t('shipDone', 'You’ve unlocked free shipping');
  slot.innerHTML = `<div class="brik-mc-ship grid gap-2" data-threshold="${threshold}"><p class="flex items-center gap-2 text-sm text-muted-foreground"><span>${text}</span></p><div class="h-1.5 overflow-hidden rounded-full bg-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${pct}"><div class="brik-mc-ship-fill h-full rounded-full bg-primary transition-[width] duration-500" style="width:${pct}%"></div></div></div>`;
}

function openOnAdd() {
  const roots = [...document.querySelectorAll('[data-brik-mini-cart="drawer"], [data-brik-mini-cart="dropdown"]')];
  const root = roots.find((r) => r.dataset.autoOpen === '1' && r.querySelector('.brik-mc-trigger')?.offsetParent !== null);
  if (!root) return;
  document.querySelectorAll('dialog.brik-qv-dialog[open]').forEach((d) => d.close());
  openCart(root);
}

function openCart(root) {
  const panel = root.querySelector('.brik-mc-drawer, .brik-mc-dropdown');
  const trigger = root.querySelector('.brik-mc-trigger');
  if (!panel) return;
  if (panel.tagName === 'DIALOG') {
    if (!panel.open) panel.showModal();
    return;
  }
  panel.hidden = false;
  trigger?.setAttribute('aria-expanded', 'true');
  requestAnimationFrame(() => panel.classList.add('is-open'));
}

function closeDropdown(root) {
  const panel = root.querySelector('.brik-mc-dropdown');
  if (!panel || panel.hidden) return;
  panel.classList.remove('is-open');
  root.querySelector('.brik-mc-trigger')?.setAttribute('aria-expanded', 'false');
  setTimeout(() => {
    if (!panel.classList.contains('is-open')) panel.hidden = true;
  }, 150);
}

on('[data-brik-mini-cart]', (root) => {
  const mode = root.dataset.brikMiniCart;
  const content = root.querySelector('.brik-mc-content');
  if (content) shippingBar(root, content);
  if (mode === 'link') return;

  root.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-mc-open]');
    if (trigger) {
      e.preventDefault();
      if (inCanvas()) return;
      const panel = root.querySelector('.brik-mc-dropdown');
      if (panel && !panel.hidden) closeDropdown(root);
      else openCart(root);
      return;
    }
    if (e.target.closest('[data-mc-close]') && mode === 'dropdown') closeDropdown(root);
  });

  if (mode === 'dropdown') {
    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) closeDropdown(root);
    });
    root.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeDropdown(root);
        root.querySelector('.brik-mc-trigger')?.focus();
      }
    });
  }
});

// Quantity changes and removal inside the drawer: optimistic, then confirmed by fresh fragments.
const pending = new Map();

function lineUpdate(line, qty) {
  const key = line.dataset.key;
  const content = line.closest('.brik-mc-content');
  clearTimeout(pending.get(key));
  line.setAttribute('aria-busy', 'true');
  pending.set(
    key,
    setTimeout(async () => {
      pending.delete(key);
      const send = async (nonce) => post('brik_cart_qty', { key, qty, nonce });
      try {
        let json = await send(content?.dataset.nonce || '');
        if (json.code === 'nonce') {
          // The page was cached or the session started after it loaded: get a fresh nonce and retry.
          const fresh = await post('get_refreshed_fragments', { time: Date.now() });
          applyFragments(fresh.fragments);
          json = await send(document.querySelector('.brik-mc-content')?.dataset.nonce || '');
        }
        if (json.message) toast(json.message, 'error');
        applyFragments(json.fragments);
        emit(qty === 0 ? 'removed' : 'updated', { fragments: json.fragments, cart_hash: json.cart_hash });
      } catch (e) {
        toast(t('error', 'Something went wrong. Please try again.'), 'error');
        refreshFragments();
      }
    }, qty === 0 ? 0 : 450),
  );
}

document.addEventListener('click', (e) => {
  const remove = e.target.closest?.('.brik-mc-content [data-brik-mc-remove]');
  if (remove) {
    e.preventDefault();
    if (inCanvas()) return;
    const line = remove.closest('.brik-mc-item');
    if (!line) return;
    line.classList.add('is-removing');
    lineUpdate(line, 0);
  }
});

/* ------------------------------------------------------------------------
 * Quantity steppers (drawer, quick view, cart page).
 * ---------------------------------------------------------------------- */

function stepInput(input, dir) {
  const step = Number(input.step) || 1;
  const min = input.min === '' ? 0 : Number(input.min);
  const max = input.max === '' ? Infinity : Number(input.max);
  const next = Math.min(max, Math.max(min, (Number(input.value) || 0) + dir * step));
  if (String(next) === input.value) return false;
  input.value = String(next);
  input.dispatchEvent(new Event('input', { bubbles: true }));
  input.dispatchEvent(new Event('change', { bubbles: true }));
  return true;
}

function syncStepper(box) {
  const input = box.querySelector('input');
  if (!input) return;
  const v = Number(input.value) || 0;
  const min = input.min === '' ? 0 : Number(input.min);
  const max = input.max === '' ? Infinity : Number(input.max);
  const dec = box.querySelector('[data-qty-step="-1"]');
  const inc = box.querySelector('[data-qty-step="1"]');
  // In the drawer, going below one removes the line, so minus stays enabled.
  if (dec) dec.disabled = !box.closest('.brik-mc-content') && v <= min;
  if (inc) inc.disabled = v >= max;
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest?.('[data-brik-qty] [data-qty-step]');
  if (!btn) return;
  e.preventDefault();
  const box = btn.closest('[data-brik-qty]');
  const input = box.querySelector('input');
  if (!input || inCanvas()) return;
  stepInput(input, Number(btn.dataset.qtyStep));
  syncStepper(box);
});

document.addEventListener('change', (e) => {
  const input = e.target.closest?.('.brik-mc-content [data-brik-qty] input');
  if (!input) return;
  const line = input.closest('.brik-mc-item');
  const qty = Math.max(0, parseInt(input.value, 10) || 0);
  if (qty === 0) line.classList.add('is-removing');
  lineUpdate(line, qty);
});

// WooCommerce's own quantity inputs get the same stepper.
on('.brik-woo .quantity, .brik-qv-form .quantity', (wrap) => {
  const input = wrap.querySelector('input.qty');
  if (!input || input.type === 'hidden' || wrap.querySelector('[data-qty-step]')) return;
  wrap.classList.add('brik-qty-wrap');
  wrap.setAttribute('data-brik-qty', '');
  const mk = (dir, label, path) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.dataset.qtyStep = String(dir);
    b.setAttribute('aria-label', label);
    b.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true">${path}</svg>`;
    return b;
  };
  input.before(mk(-1, t('decrease', 'Decrease quantity'), '<path d="M5 12h14"/>'));
  input.after(mk(1, t('increase', 'Increase quantity'), '<path d="M5 12h14"/><path d="M12 5v14"/>'));
  syncStepper(wrap);
  input.addEventListener('input', () => syncStepper(wrap));

  // On the cart page a change updates the cart after a short pause.
  const form = input.closest('.woocommerce-cart-form');
  if (form) {
    let timer;
    input.addEventListener('change', () => {
      clearTimeout(timer);
      timer = setTimeout(() => {
        const update = form.querySelector('[name="update_cart"]');
        if (!update) return;
        update.disabled = false;
        update.removeAttribute('aria-disabled');
        update.click();
      }, 700);
    });
  }
});

/* ------------------------------------------------------------------------
 * Products: filtering, sorting and pagination over REST.
 * ---------------------------------------------------------------------- */

const controllers = new Map();
const NATIVE = /^(min_price|max_price|rating_filter|orderby|filter_[a-z0-9_-]+)$/;
const prefix = (key) => `bf_${key}_`;

function urlParams(key) {
  const out = {};
  new URLSearchParams(window.location.search).forEach((v, k) => {
    if ((k.startsWith(prefix(key)) && k !== `${prefix(key)}page`) || NATIVE.test(k)) out[k] = v;
  });
  return out;
}

function urlFor(key, params, page) {
  const url = new URL(window.location.href);
  [...url.searchParams.keys()].filter((k) => k.startsWith(prefix(key)) || NATIVE.test(k)).forEach((k) => url.searchParams.delete(k));
  Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  if (page > 1) url.searchParams.set(`${prefix(key)}page`, String(page));
  return url;
}

function controller(root) {
  const key = root.dataset.brikProducts;
  const results = root.querySelector('.brik-products-results');
  let params = urlParams(key);
  let page = Number(root.dataset.page) || 1;
  let request = 0;
  let observer = null;

  const body = (p) => {
    const data = { post_id: Number(root.dataset.post), node_id: root.dataset.node, filters: params, page: p, page_url: urlFor(key, params, 1).toString() };
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
      const res = await fetch(`${restBase()}products`, {
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

  function setMeta(json, append = false) {
    page = json.page;
    root.dataset.page = String(json.page);
    root.dataset.pages = String(json.pages);
    root.dataset.total = String(json.total);
    root.dispatchEvent(new CustomEvent('brik:products-updated', { bubbles: true, detail: { key, total: json.total, pages: json.pages, page: json.page, perPage: json.per_page, append } }));
  }

  function replace(json) {
    results.innerHTML = json.html;
    results.classList.remove('is-swapped');
    void results.offsetWidth;
    results.classList.add('is-swapped');
    setMeta(json);
    mount(results);
    scroller(root);
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
    results.querySelectorAll(':scope > .brik-products-more, :scope > .brik-pagination').forEach((el) => el.remove());
    results.append(...tpl.content.childNodes);
    setMeta(json, true);
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
      if (json) {
        append(json);
        window.history.replaceState(window.history.state, '', urlFor(key, params, 1));
      } else link.removeAttribute('aria-busy');
    } catch (e) {
      fallback(page + 1);
    }
  }

  function watch() {
    if (root.dataset.pagination !== 'infinite' || !('IntersectionObserver' in window)) return;
    if (observer) observer.disconnect();
    const link = results.querySelector('[data-brik-products-more]');
    if (!link) return;
    observer = new IntersectionObserver((entries) => entries.some((e) => e.isIntersecting) && more(link), { rootMargin: '400px 0px' });
    observer.observe(link);
  }

  root.addEventListener('click', (e) => {
    const link = e.target.closest('[data-brik-products-more]');
    if (link) {
      e.preventDefault();
      more(link);
      return;
    }
    const clear = e.target.closest('[data-brik-products-clear]');
    if (clear) {
      e.preventDefault();
      document.querySelectorAll(`[data-brik-product-filter="${CSS.escape(key)}"]`).forEach((form) => form.brikClear?.(false));
      refresh(Object.fromEntries(Object.entries(params).filter(([k]) => k === `${prefix(key)}orderby`)));
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

// Carousel arrows for product and category carousels.
function scroller(root) {
  const track = root.querySelector('.brik-listing-items--carousel');
  const prev = root.querySelector('[data-brik-products-prev]');
  const next = root.querySelector('[data-brik-products-next]');
  if (!track || !prev || !next) return;
  const step = () => {
    const item = track.firstElementChild;
    const gap = parseFloat(getComputedStyle(track).columnGap || '0') || 0;
    const per = item ? Math.max(1, Math.floor((track.clientWidth + gap) / (item.getBoundingClientRect().width + gap))) : 1;
    return item ? per * (item.getBoundingClientRect().width + gap) : track.clientWidth;
  };
  const update = () => {
    prev.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
    prev.style.opacity = prev.disabled ? '0' : '';
    next.style.opacity = next.disabled ? '0' : '';
  };
  prev.onclick = () => track.scrollBy({ left: -step(), behavior: reduced() ? 'auto' : 'smooth' });
  next.onclick = () => track.scrollBy({ left: step(), behavior: reduced() ? 'auto' : 'smooth' });
  track.addEventListener('scroll', update, { passive: true });
  update();
}

on('[data-brik-products]', (root) => {
  scroller(root);
  if (inCanvas()) {
    root.addEventListener('click', (e) => e.target.closest('a') && e.preventDefault());
    return;
  }
  controllers.set(root.dataset.brikProducts, controller(root));
});

on('[data-brik-carousel-scroller]', (root) => scroller(root));

/* ------------------------------------------------------------------------
 * Product filters.
 * ---------------------------------------------------------------------- */

function rangeUpdate(range) {
  const min = range.querySelector('[data-pf-min]');
  const max = range.querySelector('[data-pf-max]');
  const lo = Number(min.min);
  const span = Number(min.max) - lo || 1;
  const fmt = (v) => `${range.dataset.prefix || ''}${Number(v).toLocaleString(document.documentElement.lang || undefined)}${range.dataset.suffix || ''}`;
  const track = range.querySelector('.brik-lf-range-track');
  track.style.setProperty('--lo', `${((Number(min.value) - lo) / span) * 100}%`);
  track.style.setProperty('--hi', `${((Number(max.value) - lo) / span) * 100}%`);
  range.querySelector('[data-pf-out="min"]').textContent = fmt(min.value);
  range.querySelector('[data-pf-out="max"]').textContent = fmt(max.value);
  return { min, max, fmt };
}

function collectFilters(form) {
  const out = {};
  const data = new FormData(form);
  const own = new Set([...form.querySelectorAll('.brik-pf-sheet [name]')].map((el) => el.name.replace(/\[\]$/, '')));
  for (const [name, value] of data.entries()) {
    const k = name.replace(/\[\]$/, '');
    if (!own.has(k) || typeof value !== 'string' || value.trim() === '') continue;
    out[k] = out[k] ? `${out[k]},${value}` : value.trim();
  }
  form.querySelectorAll('[data-pf-range] input[type="range"]').forEach((input) => {
    const atBound = input.matches('[data-pf-min]') ? input.value === input.min : input.value === input.max;
    if (atBound) delete out[input.name];
  });
  return { values: out, names: own };
}

function chipsFor(form) {
  const chips = [];
  form.querySelectorAll('.brik-pf-sheet input:checked[data-chip]').forEach((input) => chips.push({ label: input.dataset.chip, input }));
  form.querySelectorAll('.brik-pf-sheet select').forEach((select) => {
    const opt = select.selectedOptions[0];
    if (select.value && opt?.dataset.chip) chips.push({ label: opt.dataset.chip, input: select });
  });
  form.querySelectorAll('.brik-pf-sheet input[type="search"]').forEach((input) => {
    if (input.value.trim()) chips.push({ label: `“${input.value.trim()}”`, input });
  });
  form.querySelectorAll('[data-pf-range]').forEach((range) => {
    const { min, max, fmt } = rangeUpdate(range);
    if (min.value !== min.min || max.value !== max.max) chips.push({ label: `${fmt(min.value)} – ${fmt(max.value)}`, input: range });
  });
  return chips;
}

function resetControl(el) {
  if (el.matches('[data-pf-range]')) {
    const min = el.querySelector('[data-pf-min]');
    const max = el.querySelector('[data-pf-max]');
    min.value = min.min;
    max.value = max.max;
    rangeUpdate(el);
  } else if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
  else el.value = '';
}

function renderChips(form) {
  const box = form.querySelector('[data-pf-chips]');
  const chips = chipsFor(form);
  const badge = form.querySelector('[data-pf-count]');
  if (badge) {
    badge.textContent = String(chips.length);
    badge.hidden = !chips.length;
  }
  form.querySelectorAll('.brik-pf-group').forEach((group) => {
    const dot = group.querySelector('.brik-pf-dot');
    if (!dot) return;
    const active = chips.some((c) => group.contains(c.input));
    dot.hidden = !active;
  });
  if (!box) return;
  const list = box.querySelector('.brik-pf-chip-list');
  list.innerHTML = '';
  chips.forEach((chip) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'brik-pf-chip inline-flex h-7 items-center gap-1 rounded-full border bg-secondary pr-1.5 pl-3 text-xs font-medium text-secondary-foreground transition-colors hover:bg-secondary/70';
    b.setAttribute('aria-label', `${t('remove', 'Remove')} ${chip.label}`);
    b.innerHTML = '<span></span><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 opacity-60" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>';
    b.firstChild.textContent = chip.label;
    b.addEventListener('click', () => {
      resetControl(chip.input);
      form.brikApply();
    });
    list.append(b);
  });
  box.hidden = !chips.length;
}

on('[data-brik-product-filter]', (form) => {
  const key = form.dataset.brikProductFilter;
  const native = form.dataset.native === '1';
  const instant = form.dataset.apply !== 'button';
  const sheet = form.querySelector('dialog.brik-pf-sheet');
  const mobile = window.matchMedia('(max-width: 767px)');

  if (inCanvas()) {
    form.addEventListener('submit', (e) => e.preventDefault());
    form.addEventListener('click', (e) => e.target.closest('a') && e.preventDefault());
    return;
  }
  if (instant) form.querySelectorAll('[data-pf-js-hide]').forEach((el) => (el.hidden = true));

  // Server-rendered chips are links; from here on they are rebuilt from the controls.
  renderChips(form);

  let timer;
  const apply = () => {
    clearTimeout(timer);
    renderChips(form);
    const { values, names } = collectFilters(form);
    if (native) {
      const url = new URL(form.action || window.location.href, window.location.href);
      new URLSearchParams(window.location.search).forEach((v, k) => {
        if (!names.has(k) && !/^(paged|product-page|query_type_.+)$/.test(k)) url.searchParams.set(k, v);
      });
      Object.entries(values).forEach(([k, v]) => {
        url.searchParams.set(k, v);
        if (k.startsWith('filter_') && v.includes(',')) url.searchParams.set(`query_type_${k.slice(7)}`, 'or');
      });
      window.location.href = url.toString();
      return;
    }
    const ctl = controllers.get(key);
    if (!ctl) {
      form.submit();
      return;
    }
    // Keep values that belong to other controls (sort dropdown, a second filter bar).
    const keep = Object.fromEntries(Object.entries(ctl.params()).filter(([k]) => !names.has(k) && !NATIVE.test(k)));
    ctl.refresh({ ...keep, ...values });
  };
  form.brikApply = apply;
  form.brikClear = (run = true) => {
    form.querySelectorAll('.brik-pf-sheet input, .brik-pf-sheet select').forEach((el) => {
      if (el.type === 'range') return;
      resetControl(el);
    });
    form.querySelectorAll('[data-pf-range]').forEach(resetControl);
    if (run) apply();
    else renderChips(form);
  };

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    apply();
  });

  form.addEventListener('input', (e) => {
    const range = e.target.closest('[data-pf-range]');
    if (range) {
      const min = range.querySelector('[data-pf-min]');
      const max = range.querySelector('[data-pf-max]');
      if (Number(min.value) > Number(max.value)) {
        if (e.target === min) min.value = max.value;
        else max.value = min.value;
      }
      rangeUpdate(range);
      return;
    }
    if (instant && e.target.matches('[data-pf-search]')) {
      clearTimeout(timer);
      timer = setTimeout(apply, 400);
    }
  });

  form.addEventListener('change', (e) => {
    if (e.target.matches('[data-pf-search]')) return;
    if (instant) apply();
    else renderChips(form);
  });

  // A checked rating can be clicked again to clear it.
  form.addEventListener('pointerdown', (e) => {
    const label = e.target.closest('[data-pf-radio] label');
    const input = label?.querySelector('input[type="radio"]');
    if (input) input.dataset.was = input.checked ? '1' : '';
  });
  form.addEventListener('click', (e) => {
    const input = e.target.closest('[data-pf-radio] input[type="radio"]');
    if (input && input.dataset.was === '1') {
      input.checked = false;
      input.dataset.was = '';
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
    const clear = e.target.closest('[data-pf-clear]');
    if (clear) {
      e.preventDefault();
      form.brikClear();
    }
  });

  // Phones: the panel opens as a sheet.
  if (sheet) {
    form.querySelector('[data-pf-open]')?.addEventListener('click', () => {
      if (mobile.matches) sheet.showModal();
    });
    sheet.addEventListener('click', (e) => {
      if (e.target === sheet || e.target.closest('[data-pf-close], [data-pf-done]')) sheet.close();
    });
    mobile.addEventListener('change', () => {
      if (!mobile.matches && sheet.open) sheet.close();
    });
  }

  // Bar layout: groups are dropdowns, one open at a time.
  if (form.classList.contains('brik-pf--horizontal')) {
    const groups = [...form.querySelectorAll('details.brik-pf-group')];
    groups.forEach((d) =>
      d.addEventListener('toggle', () => {
        if (d.open && !mobile.matches) groups.forEach((o) => o !== d && (o.open = false));
      }),
    );
    document.addEventListener('click', (e) => {
      if (mobile.matches) return;
      groups.forEach((d) => !d.contains(e.target) && (d.open = false));
    });
  }

  // Keep the sheet's "Show N results" in step with the grid.
  document.addEventListener('brik:products-updated', (e) => {
    if (e.detail.key !== key) return;
    const done = form.querySelector('[data-pf-done]');
    const n = form.querySelector('[data-pf-total]');
    if (n) n.textContent = Number(e.detail.total).toLocaleString(document.documentElement.lang || undefined);
    if (done && !n) done.textContent = t('showResults', 'Show results');
  });
});

/* ------------------------------------------------------------------------
 * Sorting and result count.
 * ---------------------------------------------------------------------- */

on('[data-brik-ordering]', (form) => {
  const key = form.dataset.brikOrdering;
  const select = form.querySelector('select');
  if (inCanvas() || !select) return;
  select.addEventListener('change', () => {
    const ctl = key ? controllers.get(key) : null;
    if (!ctl) {
      form.submit();
      return;
    }
    const params = { ...ctl.params() };
    delete params.orderby;
    if (select.value) params[select.name] = select.value;
    else delete params[select.name];
    ctl.refresh(params);
  });
});

on('[data-brik-result-count]', (el) => {
  const key = el.dataset.brikResultCount;
  if (!key) return;
  let tpl = {};
  try {
    tpl = JSON.parse(el.dataset.tpl || '{}');
  } catch (e) {
    return;
  }
  document.addEventListener('brik:products-updated', (e) => {
    const d = e.detail;
    if (d.key !== key) return;
    const append = el.dataset.append === '1';
    const first = append ? 1 : (d.page - 1) * d.perPage + 1;
    const last = Math.min(d.total, d.page * d.perPage);
    let text;
    if (!d.total) text = tpl.none;
    else if (d.total === 1) text = tpl.one;
    else if (first === 1 && last >= d.total) text = (tpl.all || '').replace('%d', d.total);
    else text = (tpl.range || '').replace('%1$d', first).replace('%2$d', last).replace('%3$d', d.total);
    el.textContent = text;
  });
});

/* ------------------------------------------------------------------------
 * Quick view.
 * ---------------------------------------------------------------------- */

let qvDialog = null;

function quickViewDialog() {
  if (qvDialog) return qvDialog;
  qvDialog = document.createElement('dialog');
  qvDialog.className = 'brik brik-dialog brik-dialog--xl brik-qv-dialog';
  qvDialog.setAttribute('aria-labelledby', 'brik-qv-title');
  qvDialog.innerHTML = `<div class="brik-modal-panel relative max-h-[inherit] overflow-y-auto rounded-xl border bg-background p-5 text-foreground shadow-2xl sm:p-7"><button type="button" class="absolute top-3 right-3 z-10 inline-flex size-8 items-center justify-center rounded-md bg-background/80 opacity-70 backdrop-blur transition-opacity outline-none hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50" data-qv-close aria-label="${t('close', 'Close')}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button><div class="brik-qv-body"></div></div>`;
  document.body.append(qvDialog);
  qvDialog.addEventListener('click', (e) => {
    if (e.target === qvDialog || e.target.closest('[data-qv-close]')) qvDialog.close();
    const thumb = e.target.closest('[data-qv-thumb]');
    if (thumb) {
      const main = qvDialog.querySelector('[data-qv-main]');
      if (main && thumb.dataset.qvThumb) {
        main.removeAttribute('srcset');
        main.src = thumb.dataset.qvThumb;
      }
      qvDialog.querySelectorAll('[data-qv-thumb]').forEach((b) => b.setAttribute('aria-current', String(b === thumb)));
    }
  });
  // The real add-to-cart form, submitted over AJAX.
  qvDialog.addEventListener('submit', async (e) => {
    const form = e.target.closest('form.cart');
    if (!form) return;
    e.preventDefault();
    const data = new FormData(form);
    if (e.submitter?.name) data.set(e.submitter.name, e.submitter.value);
    const id = data.get('add-to-cart') || form.querySelector('[name="add-to-cart"]')?.value || qvDialog.querySelector('.brik-qv')?.dataset.productId;
    data.delete('add-to-cart');
    data.set('product_id', id);
    const button = form.querySelector('[type="submit"]');
    button?.classList.add('is-loading');
    button?.setAttribute('aria-busy', 'true');
    const ok = await addToCart(data, null);
    button?.classList.remove('is-loading');
    button?.removeAttribute('aria-busy');
    if (ok && qvDialog.open) qvDialog.close();
  });
  return qvDialog;
}

async function openQuickView(id, trigger) {
  const dialog = quickViewDialog();
  const body = dialog.querySelector('.brik-qv-body');
  body.innerHTML = '<div class="brik-qv-skeleton grid gap-6 md:grid-cols-2 md:gap-8"><div class="aspect-square animate-pulse rounded-lg bg-muted"></div><div class="grid content-start gap-4"><div class="h-4 w-24 animate-pulse rounded bg-muted"></div><div class="h-8 w-3/4 animate-pulse rounded bg-muted"></div><div class="h-6 w-28 animate-pulse rounded bg-muted"></div><div class="h-20 animate-pulse rounded bg-muted"></div><div class="h-10 animate-pulse rounded bg-muted"></div></div></div>';
  if (!dialog.open) dialog.showModal();
  try {
    const res = await fetch(`${restBase()}quick-view/${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!res.ok) throw new Error(res.statusText);
    const json = await res.json();
    body.innerHTML = json.html;
    mount(body);
    const $ = jq();
    const form = body.querySelector('.variations_form');
    if (form && $ && $.fn.wc_variation_form) $(form).wc_variation_form();
    body.querySelector('.brik-qv-title')?.focus?.();
  } catch (e) {
    dialog.close();
    if (trigger?.closest('[data-product-id]')) {
      const link = trigger.closest('.brik-pc')?.querySelector('.brik-pc-link');
      if (link) window.location.href = link.href;
    }
  }
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest?.('[data-brik-quick-view]');
  if (!btn) return;
  e.preventDefault();
  if (inCanvas()) return;
  openQuickView(btn.dataset.brikQuickView, btn);
});

/* ------------------------------------------------------------------------
 * Start.
 * ---------------------------------------------------------------------- */

on('[data-brik-mini-cart], [data-brik-add-to-cart], .brik-woo', () => bridgeWooEvents());
if (document.readyState !== 'loading') bridgeWooEvents();
else document.addEventListener('DOMContentLoaded', bridgeWooEvents);
