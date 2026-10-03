// Desktop menu bar: dropdown and mega panels (hover intent, click mode, keyboard),
// panel placement inside the viewport and the sliding highlight.

const CLOSE_DELAY = 150;
const EDGE = 8;

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function topItems(nav) {
  const list = nav.querySelector(':scope > .brik-menu-bar > .brik-menu-list');
  return list ? [...list.children] : [];
}

function triggerOf(li) {
  return li.querySelector(':scope > .brik-menu-trigger, :scope > .brik-menu-split > .brik-menu-trigger');
}

function subOf(li) {
  return li.querySelector(':scope > .brik-menu-sub');
}

// First focusable element of a top-level item (the link or trigger keyboard users land on).
function headOf(li) {
  return li.querySelector(':scope > a, :scope > button, :scope > .brik-menu-split > a');
}

function focusables(sub) {
  return [...sub.querySelectorAll('a[href], button:not([disabled]), input, select, textarea')].filter(
    (el) => el.offsetParent !== null
  );
}

/**
 * Keep a panel inside the viewport and size the wide ones. Offsets are relative to the
 * item, which is the positioned ancestor.
 */
function place(nav, li) {
  const sub = subOf(li);
  if (!sub) return;
  const panel = sub.firstElementChild;
  const vw = document.documentElement.clientWidth;
  sub.style.setProperty('--brik-vw', `${vw}px`);
  sub.style.removeProperty('--brik-sub-top');

  const item = li.getBoundingClientRect();
  const width = panel.offsetWidth;
  const mode = sub.dataset.width;
  let x;

  if (mode === 'full') {
    x = 0;
    // Full-width panels hang from the bottom of the header, not from the link.
    const header = nav.closest('.brik-section') || nav;
    const gap = header.getBoundingClientRect().bottom - item.bottom;
    if (gap > 0) sub.style.setProperty('--brik-sub-top', `${Math.round(gap - 8)}px`);
  } else if (mode === 'container') {
    x = (vw - width) / 2;
  } else if (sub.dataset.kind === 'mega') {
    x = item.left + item.width / 2 - width / 2;
  } else {
    x = item.left;
  }
  if (mode !== 'full') x = Math.max(EDGE, Math.min(x, vw - EDGE - width));
  sub.style.left = `${Math.round(x - item.left)}px`;
  sub.style.transformOrigin = `${Math.round(item.left + item.width / 2 - x)}px 0`;
}

export function setupDesktop(nav) {
  if (!nav.classList.contains('brik-menu--horizontal')) return null;
  const items = topItems(nav).filter((li) => li.classList.contains('brik-menu-item'));
  const subs = items.filter((li) => li.classList.contains('brik-menu-has-sub') && subOf(li));
  const hoverMode = nav.dataset.trigger !== 'click';
  const timers = new WeakMap();
  const openedAt = new WeakMap();
  const indicator = setupIndicator(nav, items);

  const isOpen = (li) => li.dataset.state === 'open';

  const setState = (li, open) => {
    const state = open ? 'open' : 'closed';
    if (li.dataset.state === state) return;
    li.dataset.state = state;
    subOf(li).dataset.state = state;
    triggerOf(li)?.setAttribute('aria-expanded', open ? 'true' : 'false');
  };

  const cancelClose = (li) => {
    clearTimeout(timers.get(li));
    timers.delete(li);
  };

  const close = (li) => {
    cancelClose(li);
    setState(li, false);
    indicator?.settle();
  };

  const closeAll = (except) => subs.forEach((li) => li !== except && isOpen(li) && close(li));

  const open = (li) => {
    cancelClose(li);
    closeAll(li);
    if (isOpen(li)) return;
    place(nav, li);
    openedAt.set(li, performance.now());
    setState(li, true);
    indicator?.moveTo(li);
  };

  const scheduleClose = (li) => {
    cancelClose(li);
    timers.set(
      li,
      setTimeout(() => close(li), CLOSE_DELAY)
    );
  };

  subs.forEach((li) => {
    const trigger = triggerOf(li);
    const sub = subOf(li);
    if (!trigger) return;

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      // A hover just opened this panel; a click right after shouldn't slam it shut.
      if (isOpen(li) && hoverMode && performance.now() - (openedAt.get(li) || 0) < 400) return;
      if (isOpen(li)) close(li);
      else open(li);
    });

    if (hoverMode) {
      li.addEventListener('pointerenter', (e) => {
        if (e.pointerType === 'mouse') open(li);
      });
      li.addEventListener('pointerleave', (e) => {
        if (e.pointerType === 'mouse') scheduleClose(li);
      });
      // Keyboard users get the same panel when they tab onto the item.
      li.addEventListener('focusin', (e) => {
        if (e.target === trigger || e.target === headOf(li)) {
          if (e.target.matches(':focus-visible')) open(li);
        }
      });
    }

    li.addEventListener('focusout', (e) => {
      if (e.relatedTarget && !li.contains(e.relatedTarget)) close(li);
    });

    li.addEventListener('keydown', (e) => {
      const links = focusables(sub);
      const i = links.indexOf(document.activeElement);
      if (e.key === 'Escape' && isOpen(li)) {
        e.stopPropagation();
        close(li);
        trigger.focus();
      } else if (e.key === 'ArrowDown' && links.length) {
        e.preventDefault();
        if (!isOpen(li)) open(li);
        // Wait a frame so the panel is visible and focusable.
        requestAnimationFrame(() => {
          if (!isOpen(li)) return;
          const now = focusables(sub);
          now[Math.min(i + 1, now.length - 1)]?.focus();
        });
      } else if (e.key === 'ArrowUp' && i >= 0) {
        e.preventDefault();
        if (i === 0) trigger.focus();
        else links[i - 1].focus();
      } else if ((e.key === 'Home' || e.key === 'End') && i >= 0) {
        e.preventDefault();
        links[e.key === 'Home' ? 0 : links.length - 1].focus();
      }
    });
  });

  // Left/right arrows move between top-level items.
  const heads = items.map(headOf).filter(Boolean);
  heads.forEach((head, i) => {
    head.addEventListener('keydown', (e) => {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      const next = heads[(i + (e.key === 'ArrowRight' ? 1 : heads.length - 1)) % heads.length];
      next.focus();
    });
  });

  // Click outside or Escape anywhere closes open panels.
  document.addEventListener('pointerdown', (e) => {
    if (!nav.isConnected || nav.contains(e.target)) return;
    closeAll(null);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav.isConnected) closeAll(null);
  });

  window.addEventListener('resize', () => {
    subs.forEach((li) => isOpen(li) && place(nav, li));
  });

  return { closeAll };
}

/**
 * Sliding highlight (indicator and pill-container styles): one absolutely positioned
 * element follows the hovered, focused or open item and rests on the current page.
 */
function setupIndicator(nav, items) {
  const el = nav.querySelector(':scope > .brik-menu-bar > .brik-menu-list > .brik-menu-indicator');
  if (!el) return null;
  const list = el.parentElement;
  const targetOf = (li) => li?.querySelector(':scope > .brik-menu-link');
  const home = () => items.find((li) => targetOf(li)?.matches('.is-current, .is-ancestor'));
  const openItem = () => items.find((li) => li.dataset.state === 'open');
  let shown = null;
  let hovering = false;

  const moveTo = (li, instant = false) => {
    const target = targetOf(li);
    if (!target || target.offsetParent === null) {
      el.classList.remove('is-visible');
      shown = null;
      return;
    }
    const box = list.getBoundingClientRect();
    const r = target.getBoundingClientRect();
    if (instant || !shown || reducedMotion()) {
      el.classList.add('is-instant');
      // Force the jump to apply before transitions come back.
      void el.offsetWidth;
    }
    el.style.setProperty('--brik-ind-x', `${r.left - box.left}px`);
    el.style.setProperty('--brik-ind-y', `${r.top - box.top}px`);
    el.style.setProperty('--brik-ind-w', `${r.width}px`);
    el.style.setProperty('--brik-ind-h', `${r.height}px`);
    el.classList.add('is-visible');
    if (el.classList.contains('is-instant')) {
      requestAnimationFrame(() => el.classList.remove('is-instant'));
    }
    shown = li;
  };

  // While the pointer is over the bar the highlight follows it, not the open panel.
  const settle = () => {
    if (!hovering) moveTo(openItem() || home());
  };

  items.forEach((li) => {
    if (!targetOf(li)) return;
    li.addEventListener('pointerenter', () => moveTo(li));
    li.addEventListener('focusin', () => moveTo(li));
  });
  list.addEventListener('pointerenter', () => {
    hovering = true;
  });
  list.addEventListener('pointerleave', () => {
    hovering = false;
    settle();
  });
  list.addEventListener('focusout', (e) => {
    if (!list.contains(e.relatedTarget)) settle();
  });

  // Fonts and layout changes move the links; keep the highlight glued to them.
  const sync = () => (shown ? moveTo(shown, true) : settle());
  if ('ResizeObserver' in window) new ResizeObserver(sync).observe(list);
  window.addEventListener('resize', sync);
  document.fonts?.ready.then(sync);
  moveTo(home(), true);

  return { moveTo, settle };
}
