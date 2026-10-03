import { on } from '../core.js';

/*
 * Single product modules.
 *
 * Variation logic stays with WooCommerce (wc-add-to-cart-variation): this file listens to its
 * found_variation / reset_data events and tells the other product modules on the page through
 * a "brik:variation" event, so price, gallery, stock, badges and SKU follow the chosen variation.
 */

const cfg = () => window.brikWooProduct || { i18n: {} };
const t = (key, fallback) => (cfg().i18n && cfg().i18n[key]) || fallback;
const $ = () => window.jQuery;

const icons = {
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
  alert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>',
  x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
};

const emit = (productId, variation) => {
  document.dispatchEvent(new CustomEvent('brik:variation', { detail: { productId: String(productId), variation } }));
};

const onVariation = (productId, handler) => {
  document.addEventListener('brik:variation', (e) => {
    if (e.detail && e.detail.productId === String(productId)) handler(e.detail.variation);
  });
};

/* Toasts ----------------------------------------------------------------- */

let region;

function toast({ title, text = '', image = '', error = false, actions = [] }) {
  if (!region) {
    region = document.createElement('div');
    region.className = 'brik-woo-toasts';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    document.body.appendChild(region);
  }
  region.classList.toggle('has-sticky', !!document.querySelector('.brik-atc-sticky.is-visible'));

  const el = document.createElement('div');
  el.className = `brik-woo-toast${error ? ' is-error' : ''}`;
  const media = image
    ? `<img class="brik-woo-toast-media" src="${encodeURI(image)}" alt="">`
    : `<span class="brik-woo-toast-icon">${error ? icons.alert : icons.check}</span>`;
  el.innerHTML = `${media}<div class="brik-woo-toast-body"><p class="brik-woo-toast-title"></p><p class="brik-woo-toast-text"></p><div class="brik-woo-toast-actions"></div></div><button type="button" class="brik-woo-toast-close">${icons.x}</button>`;
  el.querySelector('.brik-woo-toast-title').textContent = title;
  const p = el.querySelector('.brik-woo-toast-text');
  if (text) p.textContent = text;
  else p.remove();
  const bar = el.querySelector('.brik-woo-toast-actions');
  actions.forEach((a) => {
    const link = document.createElement('a');
    link.href = a.href;
    link.textContent = a.label;
    if (a.primary) link.className = 'is-primary';
    bar.appendChild(link);
  });
  if (!actions.length) bar.remove();
  const close = el.querySelector('.brik-woo-toast-close');
  close.setAttribute('aria-label', t('close', 'Dismiss'));

  let timer;
  const dismiss = () => {
    clearTimeout(timer);
    el.classList.add('is-leaving');
    setTimeout(() => el.remove(), 200);
  };
  const arm = () => {
    clearTimeout(timer);
    timer = setTimeout(dismiss, error ? 6000 : 5000);
  };
  close.addEventListener('click', dismiss);
  el.addEventListener('mouseenter', () => clearTimeout(timer));
  el.addEventListener('mouseleave', arm);
  arm();

  while (region.children.length > 2) region.firstElementChild.remove();
  region.appendChild(el);
}

/* Cart fragments ----------------------------------------------------------- */

function applyFragments(fragments) {
  if (!fragments) return;
  Object.entries(fragments).forEach(([selector, html]) => {
    let nodes;
    try {
      nodes = document.querySelectorAll(selector);
    } catch (e) {
      return;
    }
    nodes.forEach((node) => {
      const tpl = document.createElement('template');
      tpl.innerHTML = String(html).trim();
      const fresh = tpl.content.firstElementChild;
      if (fresh) node.replaceWith(fresh);
    });
  });
}

function afterAdd(data) {
  applyFragments(data.fragments);
  document.querySelectorAll('[data-brik-cart-count]').forEach((n) => {
    n.textContent = String(data.count);
    n.hidden = !data.count;
  });
  // Let WooCommerce's cart fragments script, mini carts and other plugins catch up. No button is
  // passed: WooCommerce would append its own "View cart" link next to it; the toast has one.
  const jq = $();
  if (jq) jq(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, jq()]);
  document.body.dispatchEvent(new CustomEvent('brik:added_to_cart', { detail: data }));
}

/* Add to cart ------------------------------------------------------------ */

function variationsOf(form) {
  try {
    return JSON.parse(form.getAttribute('data-product_variations') || 'false') || [];
  } catch (e) {
    return [];
  }
}

function initVariations(root, form, productId) {
  const fields = Array.from(form.querySelectorAll('.brik-var-field--buttons'));

  const sync = () => {
    fields.forEach((field) => {
      const select = field.querySelector('select');
      if (!select) return;
      const value = select.value;
      field.querySelectorAll('.brik-var-option').forEach((btn) => {
        const v = btn.getAttribute('data-value');
        const option = Array.from(select.options).find((o) => o.value === v);
        btn.hidden = !option;
        btn.classList.toggle('is-unavailable', !!option && option.disabled);
        btn.setAttribute('aria-pressed', String(v === value && v !== ''));
      });
    });
    // "Color: Blue" next to each attribute label.
    form.querySelectorAll('table.variations tr').forEach((row) => {
      const select = row.querySelector('select');
      const label = row.querySelector('th.label label') || row.querySelector('th.label');
      if (!select || !label) return;
      let chosen = label.querySelector('.brik-var-chosen');
      const text = select.value && select.selectedOptions[0] ? select.selectedOptions[0].textContent.trim() : '';
      if (!chosen) {
        chosen = document.createElement('span');
        chosen.className = 'brik-var-chosen';
        label.appendChild(chosen);
      }
      chosen.textContent = text ? `: ${text}` : '';
    });
  };

  fields.forEach((field) => {
    const select = field.querySelector('select');
    field.addEventListener('click', (e) => {
      const btn = e.target.closest('.brik-var-option');
      if (!btn || !select) return;
      const value = btn.getAttribute('data-value');
      select.value = select.value === value ? '' : value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      sync();
    });
  });

  form.addEventListener('change', sync);
  const jq = $();
  if (jq) {
    jq(form)
      .on('woocommerce_update_variation_values', sync)
      .on('found_variation', (e, variation) => emit(productId, variation))
      .on('reset_data', () => {
        sync();
        emit(productId, null);
      });
  }

  // WooCommerce may have picked the default variation before this script ran.
  setTimeout(() => {
    sync();
    const id = form.querySelector('input.variation_id');
    if (id && id.value) {
      const variation = variationsOf(form).find((v) => String(v.variation_id) === id.value);
      if (variation) emit(productId, variation);
    }
  }, 0);

  return sync;
}

function shake(form) {
  const empty = Array.from(form.querySelectorAll('table.variations select')).find((s) => !s.value);
  const target = empty ? empty.closest('td')?.querySelector('.brik-var-options') || empty : null;
  if (!target) return;
  target.classList.remove('is-shaking');
  void target.offsetWidth;
  target.classList.add('is-shaking');
  setTimeout(() => target.classList.remove('is-shaking'), 450);
}

function needsChoice(form) {
  if (!form.classList.contains('variations_form')) return false;
  const id = form.querySelector('input.variation_id');
  return !id || !id.value || id.value === '0';
}

on('[data-brik-atc]', (root) => {
  const form = root.querySelector('form.cart');
  if (!form) return;
  const productId = root.getAttribute('data-brik-atc');

  if (document.querySelector(`[data-brik-price="${productId}"]:not(.brik-atc-sticky-price)`)) root.classList.add('has-live-price');

  // Quantity stepper.
  root.querySelectorAll('.quantity').forEach((q) => {
    const input = q.querySelector('input.qty, input[type="number"], input[type="hidden"]');
    if (!input || input.type === 'hidden') q.querySelectorAll('.brik-qty-btn').forEach((b) => b.remove());
  });
  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-brik-qty]');
    if (!btn) return;
    const input = btn.closest('.quantity')?.querySelector('input.qty');
    if (!input) return;
    const step = parseFloat(input.step) || 1;
    const min = input.min !== '' ? parseFloat(input.min) : 0;
    const max = input.max !== '' ? parseFloat(input.max) : Infinity;
    const current = parseFloat(input.value) || 0;
    const next = Math.min(max, Math.max(min, current + step * Number(btn.getAttribute('data-brik-qty'))));
    if (next === current) return;
    input.value = String(Number(next.toFixed(4)));
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  if (form.classList.contains('variations_form')) initVariations(root, form, productId);

  // WooCommerce answers a click on the disabled button with alert(); show a toast instead.
  form.addEventListener(
    'click',
    (e) => {
      const btn = e.target.closest('.single_add_to_cart_button, .brik-buy-now');
      if (!btn) return;
      const unavailable = btn.classList.contains('wc-variation-is-unavailable') || form.querySelector('.single_add_to_cart_button.wc-variation-is-unavailable');
      if (btn.classList.contains('disabled') || (btn.classList.contains('brik-buy-now') && needsChoice(form))) {
        e.preventDefault();
        e.stopPropagation();
        toast({ title: unavailable ? t('unavail', 'Sorry, this combination is unavailable.') : t('choose', 'Please choose product options first.'), error: true });
        shake(form);
      }
    },
    true
  );

  const ajax = root.getAttribute('data-ajax') === '1' && cfg().ajax && (form.method || '').toLowerCase() === 'post' && !!form.querySelector('[name="add-to-cart"], [name="brik_buy_now"]');
  if (!ajax) return;

  let busy = false;
  const submit = (e) => {
    if (busy) {
      e.preventDefault();
      return;
    }
    const submitter = e.submitter || form.querySelector('.single_add_to_cart_button');
    if (needsChoice(form)) {
      e.preventDefault();
      toast({ title: t('choose', 'Please choose product options first.'), error: true });
      shake(form);
      return;
    }
    e.preventDefault();
    const data = new FormData(form);
    if (submitter && submitter.name) data.append(submitter.name, submitter.value);

    busy = true;
    submitter?.classList.add('is-loading');
    submitter?.setAttribute('aria-busy', 'true');

    fetch(cfg().ajax.replace('%%endpoint%%', 'brik_product_atc'), { method: 'POST', body: data, credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((res) => {
        if (!res || !res.success) {
          toast({ title: t('error', 'Could not add to cart'), text: ((res && res.errors) || []).join(' '), error: true });
          return;
        }
        if (res.redirect) {
          window.location.assign(res.redirect);
          return;
        }
        afterAdd(res);
        submitter?.classList.add('is-added');
        setTimeout(() => submitter?.classList.remove('is-added'), 2000);
        toast({
          title: t('added', 'Added to cart'),
          text: res.message,
          image: res.image,
          actions: [
            { label: t('viewCart', 'View cart'), href: cfg().cart },
            { label: t('checkout', 'Checkout'), href: cfg().checkout, primary: true },
          ],
        });
      })
      .catch(() => {
        // Fall back to a regular submit so the visitor isn't stuck.
        form.removeEventListener('submit', submit);
        if (form.requestSubmit && submitter && submitter.form === form) form.requestSubmit(submitter);
        else form.submit();
      })
      .finally(() => {
        busy = false;
        submitter?.classList.remove('is-loading');
        submitter?.removeAttribute('aria-busy');
      });
  };
  form.addEventListener('submit', submit);
});

/* Sticky add to cart bar (phones) ------------------------------------------ */

on('[data-brik-atc-sticky]', (bar) => {
  const id = bar.getAttribute('data-brik-atc-sticky');
  const root = document.querySelector(`[data-brik-atc="${id}"]`);
  const form = root && root.querySelector('form.cart');
  const main = form && form.querySelector('.single_add_to_cart_button');
  if (!form || !main || !('IntersectionObserver' in window)) return;

  // Fixed positioning breaks inside transformed ancestors (entrance animations), so live on <body>.
  bar.classList.add('brik');
  document.body.appendChild(bar);
  bar.hidden = false;
  const label = bar.querySelector('.brik-atc-sticky-button span');
  const button = bar.querySelector('.brik-atc-sticky-button');

  new IntersectionObserver(
    ([entry]) => {
      const past = !entry.isIntersecting && entry.boundingClientRect.top < 0;
      bar.classList.toggle('is-visible', past);
      bar.setAttribute('aria-hidden', String(!past));
      if (past) bar.removeAttribute('inert');
      else bar.setAttribute('inert', '');
    },
    { threshold: 0 }
  ).observe(main);

  onVariation(id, (variation) => {
    if (label) label.textContent = variation ? button.getAttribute('data-label-ready') : label.getAttribute('data-choose') || label.textContent;
  });
  if (label) label.setAttribute('data-choose', label.textContent);

  button.addEventListener('click', () => {
    if (needsChoice(form)) {
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => shake(form), 400);
      return;
    }
    if (form.requestSubmit) form.requestSubmit(main);
    else main.click();
  });
});

/* Live price, stock, badge and SKU ------------------------------------------ */

const keep = (el, key, value) => {
  if (!(key in el.dataset)) el.dataset[key] = value;
  return el.dataset[key];
};

function saleLabel(el, variation) {
  if (!variation || !variation.brik_percent) return '';
  return el.getAttribute('data-format') === 'text' ? t('sale', 'Sale') : variation.brik_sale_label || `−${variation.brik_percent}%`;
}

on('[data-brik-price]', (el) => {
  const id = el.getAttribute('data-brik-price');
  const amount = el.querySelector('.brik-price-amount');
  const badge = el.querySelector('.brik-price-badge');
  if (!amount) return;
  const originalHtml = amount.innerHTML;
  const originalBadge = badge ? { text: badge.textContent, hidden: badge.hidden } : null;
  onVariation(id, (variation) => {
    if (variation && variation.brik_price_html) {
      amount.innerHTML = variation.brik_price_html;
      if (badge) {
        const text = saleLabel(badge, variation);
        badge.textContent = text;
        badge.hidden = !text;
      }
    } else {
      amount.innerHTML = originalHtml;
      if (badge && originalBadge) {
        badge.textContent = originalBadge.text;
        badge.hidden = originalBadge.hidden;
      }
    }
  });
});

on('[data-brik-sale-badge]', (el) => {
  const original = { text: el.textContent, hidden: el.hidden };
  onVariation(el.getAttribute('data-brik-sale-badge'), (variation) => {
    const text = variation ? saleLabel(el, variation) : original.text;
    el.textContent = text;
    el.hidden = variation ? !text : original.hidden;
  });
});

on('[data-brik-stock]', (el) => {
  const text = el.querySelector('.brik-stock-text');
  const original = { status: el.getAttribute('data-status'), text: text ? text.textContent : '' };
  onVariation(el.getAttribute('data-brik-stock'), (variation) => {
    const stock = variation && variation.brik_stock;
    el.setAttribute('data-status', stock ? stock.status : original.status);
    if (text) text.textContent = stock ? stock.text : original.text;
  });
});

on('[data-brik-sku]', (el) => {
  onVariation(el.getAttribute('data-brik-sku'), (variation) => {
    el.textContent = variation && variation.sku ? variation.sku : el.getAttribute('data-default') || '';
  });
});

/* Gallery ---------------------------------------------------------------- */

on('[data-brik-gallery]', (root) => {
  const track = root.querySelector('.brik-pg-track');
  if (!track) return;
  const slides = Array.from(track.querySelectorAll('.brik-pg-slide'));
  const thumbs = Array.from(root.querySelectorAll('.brik-pg-thumb'));
  const dots = Array.from(root.querySelectorAll('.brik-pg-dot'));
  const prev = root.querySelector('[data-brik-pg="-1"]');
  const next = root.querySelector('[data-brik-pg="1"]');
  const thumbBox = root.querySelector('.brik-pg-thumbs');
  let index = 0;

  const go = (i, smooth = true) => {
    const target = Math.max(0, Math.min(slides.length - 1, i));
    track.scrollTo({ left: target * track.clientWidth * (getComputedStyle(track).direction === 'rtl' ? -1 : 1), behavior: smooth ? 'smooth' : 'auto' });
    mark(target);
  };

  const mark = (i) => {
    index = i;
    thumbs.forEach((b, j) => (j === i ? b.setAttribute('aria-current', 'true') : b.removeAttribute('aria-current')));
    dots.forEach((d, j) => d.setAttribute('aria-current', String(j === i)));
    if (prev) prev.disabled = i === 0;
    if (next) next.disabled = i === slides.length - 1;
    const thumb = thumbs[i];
    if (thumb && thumbBox) {
      const vertical = thumbBox.scrollHeight > thumbBox.clientHeight + 2;
      if (vertical) thumbBox.scrollTo({ top: thumb.offsetTop - thumbBox.clientHeight / 2 + thumb.clientHeight / 2, behavior: 'smooth' });
      else thumbBox.scrollTo({ left: thumb.offsetLeft - thumbBox.clientWidth / 2 + thumb.clientWidth / 2, behavior: 'smooth' });
    }
  };
  mark(0);

  let frame;
  track.addEventListener(
    'scroll',
    () => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const i = Math.round(Math.abs(track.scrollLeft) / Math.max(1, track.clientWidth));
        if (i !== index) mark(i);
      });
    },
    { passive: true }
  );

  thumbs.forEach((b, i) => b.addEventListener('click', () => go(i)));
  prev?.addEventListener('click', () => go(index - 1));
  next?.addEventListener('click', () => go(index + 1));
  track.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      go(index - 1);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      go(index + 1);
    }
  });
  window.addEventListener('resize', () => go(index, false));

  // Magnify under the pointer.
  if (root.classList.contains('brik-pg--zoom') && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    slides.forEach((slide) => {
      slide.addEventListener('pointerenter', () => slide.classList.add('is-zooming'));
      slide.addEventListener('pointerleave', () => slide.classList.remove('is-zooming'));
      slide.addEventListener('pointermove', (e) => {
        const r = slide.getBoundingClientRect();
        slide.style.setProperty('--zx', `${((e.clientX - r.left) / r.width) * 100}%`);
        slide.style.setProperty('--zy', `${((e.clientY - r.top) / r.height) * 100}%`);
      });
    });
  }

  // Variation images: jump to the slide when the image is in the gallery, otherwise show it
  // in the first slide until the selection is reset.
  const first = slides[0];
  const firstImg = first && first.querySelector('img');
  const firstLink = first && first.querySelector('a');
  const firstThumb = thumbs[0] && thumbs[0].querySelector('img');
  let swapped = false;
  const original = firstImg
    ? {
        src: firstImg.getAttribute('src'),
        srcset: firstImg.getAttribute('srcset'),
        sizes: firstImg.getAttribute('sizes'),
        href: firstLink ? firstLink.getAttribute('href') : '',
        thumb: firstThumb ? firstThumb.getAttribute('src') : '',
        thumbSrcset: firstThumb ? firstThumb.getAttribute('srcset') : '',
      }
    : null;

  const setAttr = (el, name, value) => (value ? el.setAttribute(name, value) : el.removeAttribute(name));

  const restore = () => {
    if (!swapped || !original) return;
    setAttr(firstImg, 'src', original.src);
    setAttr(firstImg, 'srcset', original.srcset);
    setAttr(firstImg, 'sizes', original.sizes);
    if (firstLink) setAttr(firstLink, 'href', original.href);
    if (firstThumb) {
      setAttr(firstThumb, 'src', original.thumb);
      setAttr(firstThumb, 'srcset', original.thumbSrcset);
    }
    swapped = false;
  };

  onVariation(root.getAttribute('data-brik-gallery'), (variation) => {
    const image = variation && variation.image;
    if (!image || !image.src) {
      if (swapped) {
        restore();
        go(0);
      }
      return;
    }
    const match = slides.findIndex((s) => String(s.getAttribute('data-image-id')) === String(variation.image_id));
    if (match > 0 || (match === 0 && !swapped)) {
      restore();
      go(match);
      return;
    }
    if (!firstImg) return;
    first.classList.add('is-swapping');
    const done = () => first.classList.remove('is-swapping');
    firstImg.addEventListener('load', done, { once: true });
    setTimeout(done, 600);
    setAttr(firstImg, 'srcset', image.srcset || '');
    setAttr(firstImg, 'sizes', image.sizes || '');
    firstImg.setAttribute('src', image.src);
    if (firstLink) firstLink.setAttribute('href', image.full_src || image.src);
    if (firstThumb) {
      firstThumb.removeAttribute('srcset');
      firstThumb.setAttribute('src', image.gallery_thumbnail_src || image.thumb_src || image.src);
    }
    swapped = true;
    go(0);
  });
});

/* Reviews ---------------------------------------------------------------- */

// Links to #reviews open the tab or accordion item that holds the reviews.
function revealReviews() {
  const target = document.getElementById('reviews');
  if (!target) return false;
  const panel = target.closest('[role="tabpanel"]');
  if (panel && panel.hidden) {
    const tab = document.querySelector(`[role="tab"][aria-controls="${panel.id}"]`);
    if (tab) tab.click();
  }
  const details = target.closest('details');
  if (details && !details.open) details.open = true;
  target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  return true;
}

document.addEventListener('click', (e) => {
  const link = e.target.closest('a[href$="#reviews"]');
  if (!link) return;
  const url = new URL(link.href, window.location.href);
  if (url.pathname !== window.location.pathname) return;
  if (revealReviews()) {
    e.preventDefault();
    history.replaceState(null, '', '#reviews');
  }
});

on('.brik-reviews', (el) => {
  if (window.location.hash === '#reviews' || /^#comment-\d+$/.test(window.location.hash)) {
    setTimeout(() => {
      if (window.location.hash === '#reviews') revealReviews();
      else {
        const c = document.querySelector(window.location.hash);
        const panel = c && c.closest('[role="tabpanel"]');
        if (panel && panel.hidden) document.querySelector(`[role="tab"][aria-controls="${panel.id}"]`)?.click();
        c?.scrollIntoView({ block: 'center' });
      }
    }, 50);
  }
  el.querySelectorAll('.brik-rate-input').forEach((input) => {
    input.addEventListener('invalid', () => {
      const box = input.closest('.brik-rate');
      box?.classList.remove('is-invalid');
      void box?.offsetWidth;
      box?.classList.add('is-invalid');
    });
  });
});
