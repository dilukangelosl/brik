import { on } from '../core.js';

const KEY = 'brik-theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');
const root = document.documentElement;

function stored() {
  try {
    const v = localStorage.getItem(KEY);
    return v === 'light' || v === 'dark' || v === 'system' ? v : null;
  } catch (e) {
    return null;
  }
}

function save(mode) {
  try {
    localStorage.setItem(KEY, mode);
  } catch (e) {
    // Private mode: the choice lasts for this page only.
  }
}

const isDark = (mode) => mode === 'dark' || (mode === 'system' && media.matches);

function apply(mode) {
  const dark = isDark(mode);
  root.classList.toggle('dark', dark);
  // A page-level dark setting sits on <body>; an explicit light choice should win over it.
  if (!dark) document.body.classList.remove('dark');
  sync(mode);
}

function sync(mode) {
  const dark = root.classList.contains('dark') || document.body.classList.contains('dark');
  document.querySelectorAll('[data-brik-theme]').forEach((el) => {
    const kind = el.dataset.brikTheme;
    if (kind === 'segmented') {
      el.querySelectorAll('[data-mode]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.mode === mode)));
    } else if (kind === 'switch') {
      el.setAttribute('aria-checked', String(dark));
    } else {
      el.setAttribute('aria-pressed', String(dark));
    }
  });
}

const canvas = () => document.body.classList.contains('brik-canvas-mode');
let current = stored();

// An explicit choice made on another page applies everywhere, even without a toggle here.
if ((current === 'light' || current === 'dark') && !canvas()) {
  apply(current);
}

media.addEventListener?.('change', () => {
  if (current === 'system' || (!current && document.querySelector('[data-brik-theme]'))) apply('system');
});

on('[data-brik-theme]', (el) => {
  if (canvas()) return;
  if (!current) {
    current = 'system';
    apply('system');
  } else {
    sync(current);
  }

  const choose = (mode) => {
    current = mode;
    save(mode);
    apply(mode);
  };

  if (el.dataset.brikTheme === 'segmented') {
    el.addEventListener('click', (e) => {
      const b = e.target.closest('[data-mode]');
      if (b) choose(b.dataset.mode);
    });
  } else {
    el.addEventListener('click', () => {
      const dark = root.classList.contains('dark') || document.body.classList.contains('dark');
      choose(dark ? 'light' : 'dark');
    });
  }
});
