// Mobile menu: a modal <dialog> (top layer, inert page, Escape) whose backdrop and panel
// animate with an .is-open class, so the dialog is only closed once the exit transition ends.

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function waitForTransition(el, ms) {
  return new Promise((resolve) => {
    let done = false;
    const finish = () => {
      if (done) return;
      done = true;
      el.removeEventListener('transitionend', onEnd);
      resolve();
    };
    const onEnd = (e) => e.target === el && finish();
    el.addEventListener('transitionend', onEnd);
    // Fallback for interrupted or overridden transitions.
    setTimeout(finish, ms);
  });
}

export function setupMobile(nav) {
  const toggle = nav.querySelector(':scope > .brik-menu-bar > [data-brik-menu-toggle]');
  const dialog = nav.querySelector(':scope > dialog.brik-mobile-menu');
  if (!toggle || !dialog || typeof dialog.showModal !== 'function') return;

  const panel = dialog.querySelector('.brik-mm-panel');
  const levels = dialog.querySelector('.brik-mm-levels');
  const isDropdown = dialog.classList.contains('brik-mobile-menu--dropdown');
  const autoHeight = isDropdown || dialog.classList.contains('brik-mobile-menu--bottom');
  let closing = null;

  const setExpanded = (open) => toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

  const open = () => {
    if (dialog.open) return;
    if (isDropdown) {
      // The panel hangs below the header, which stays visible above it.
      const header = nav.closest('.brik-section') || nav;
      const bottom = Math.max(0, Math.round(header.getBoundingClientRect().bottom));
      dialog.style.setProperty('--brik-mm-top', `${bottom}px`);
    }
    resetLevels(true);
    dialog.showModal();
    setExpanded(true);
    // Two frames: the dialog has to be rendered closed once for the entrance to transition.
    requestAnimationFrame(() => requestAnimationFrame(() => dialog.classList.add('is-open')));
  };

  const close = (after) => {
    if (!dialog.open) return Promise.resolve();
    if (closing) return closing;
    setExpanded(false);
    dialog.classList.remove('is-open');
    const wait = reducedMotion() ? Promise.resolve() : waitForTransition(panel, 450);
    closing = wait.then(() => {
      dialog.close();
      dialog.classList.remove('is-dragging');
      panel.style.removeProperty('transform');
      closing = null;
      after?.();
    });
    return closing;
  };

  toggle.addEventListener('click', () => (dialog.open ? close() : open()));

  dialog.addEventListener('cancel', (e) => {
    e.preventDefault();
    close();
  });

  dialog.addEventListener('close', () => {
    dialog.classList.remove('is-open');
    setExpanded(false);
    if (toggle.offsetParent) toggle.focus({ preventScroll: true });
  });

  dialog.addEventListener('click', (e) => {
    // The dialog box itself is transparent: a click on it (or the backdrop) is a click outside.
    if (e.target === dialog || e.target.closest('[data-brik-mm-close]')) {
      close();
      return;
    }
    const expand = e.target.closest('[data-brik-mm-expand]');
    if (expand) {
      e.preventDefault();
      toggleSection(expand);
      return;
    }
    const next = e.target.closest('[data-brik-mm-next]');
    if (next) {
      e.preventDefault();
      goTo(dialog.querySelector(`#${CSS.escape(next.dataset.brikMmNext)}`));
      return;
    }
    if (e.target.closest('[data-brik-mm-back]')) {
      e.preventDefault();
      back();
      return;
    }
    const link = e.target.closest('a[href]');
    if (!link || e.defaultPrevented) return;
    // Same-page anchors scroll once the menu is gone; the scroll lock would swallow them.
    if (link.hash && link.pathname === location.pathname && link.host === location.host) {
      e.preventDefault();
      close(() => {
        const target = document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (target) {
          history.pushState(null, '', link.hash);
          target.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth' });
        }
      });
    } else {
      close();
    }
  });

  // Accordion sections expand in place.
  function toggleSection(button) {
    const sub = dialog.querySelector(`#${CSS.escape(button.getAttribute('aria-controls'))}`);
    if (!sub) return;
    const expanded = button.getAttribute('aria-expanded') !== 'true';
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    sub.classList.toggle('is-open', expanded);
  }

  // Drilldown levels.
  const stack = [];
  const rootLevel = levels?.querySelector('[data-brik-mm-level="root"]');

  function sizeLevels(level) {
    if (!levels || !autoHeight) return;
    levels.style.setProperty('--brik-mm-h', `${level.scrollHeight}px`);
  }

  function activate(level, previous, forward) {
    previous.classList.remove('is-active');
    previous.classList.toggle('is-behind', forward);
    previous.inert = true;
    level.classList.remove('is-behind');
    level.classList.add('is-active');
    level.inert = false;
    sizeLevels(level);
    const focusTarget = forward ? level.querySelector('[data-brik-mm-back]') : level.querySelector(`[data-brik-mm-next="${previous.id}"]`);
    focusTarget?.focus({ preventScroll: true });
  }

  function goTo(level) {
    if (!level) return;
    const current = stack[stack.length - 1] || rootLevel;
    stack.push(level);
    level.scrollTop = 0;
    activate(level, current, true);
  }

  function back() {
    const current = stack.pop();
    if (!current) return;
    activate(stack[stack.length - 1] || rootLevel, current, false);
  }

  function resetLevels(measure) {
    if (!levels) return;
    stack.length = 0;
    levels.querySelectorAll('[data-brik-mm-level]').forEach((level) => {
      const root = level === rootLevel;
      level.classList.toggle('is-active', root);
      level.classList.remove('is-behind');
      level.inert = !root;
    });
    if (measure) requestAnimationFrame(() => sizeLevels(rootLevel));
  }

  if (levels) {
    dialog.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowLeft' && stack.length && !e.target.matches('input, textarea')) {
        e.preventDefault();
        back();
      }
    });
  }

  // Bottom sheet: drag the handle down to dismiss.
  const handle = dialog.querySelector('[data-brik-mm-handle]');
  if (handle) {
    let startY = 0;
    let delta = 0;
    handle.addEventListener('pointerdown', (e) => {
      startY = e.clientY;
      delta = 0;
      handle.setPointerCapture(e.pointerId);
      dialog.classList.add('is-dragging');
    });
    handle.addEventListener('pointermove', (e) => {
      if (!dialog.classList.contains('is-dragging')) return;
      delta = Math.max(0, e.clientY - startY);
      panel.style.transform = `translateY(${delta}px)`;
    });
    const release = () => {
      if (!dialog.classList.contains('is-dragging')) return;
      dialog.classList.remove('is-dragging');
      panel.style.removeProperty('transform');
      if (delta > Math.min(120, panel.offsetHeight * 0.25)) close();
    };
    handle.addEventListener('pointerup', release);
    handle.addEventListener('pointercancel', release);
  }

  // Leaving the collapsed layout (rotating a tablet, resizing) closes the menu at once.
  window.addEventListener('resize', () => {
    if (dialog.open && !toggle.offsetParent) {
      dialog.classList.remove('is-open');
      dialog.close();
    }
  });
}
