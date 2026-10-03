import { on } from '../core.js';

on('[data-brik-tabs]', (root) => {
  const list = root.querySelector(':scope > [role="tablist"]');
  if (!list) return;
  const tabs = () => Array.from(list.querySelectorAll(':scope > [role="tab"]'));
  const vertical = root.dataset.orientation === 'vertical';

  const select = (tab, focus) => {
    for (const t of tabs()) {
      const on = t === tab;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      const panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !on;
    }
    if (focus) tab.focus();
    tab.scrollIntoView({ block: 'nearest', inline: 'nearest' });
  };

  list.addEventListener('click', (e) => {
    const tab = e.target.closest('[role="tab"]');
    if (tab && list.contains(tab)) select(tab, false);
  });

  list.addEventListener('keydown', (e) => {
    const all = tabs();
    const i = all.indexOf(document.activeElement);
    if (i < 0) return;
    const prev = vertical ? 'ArrowUp' : 'ArrowLeft';
    const next = vertical ? 'ArrowDown' : 'ArrowRight';
    let j = null;
    if (e.key === prev) j = (i - 1 + all.length) % all.length;
    else if (e.key === next) j = (i + 1) % all.length;
    else if (e.key === 'Home') j = 0;
    else if (e.key === 'End') j = all.length - 1;
    if (j === null) return;
    e.preventDefault();
    select(all[j], true);
  });

  // Deep links: #panel-id or #tab-id opens that tab.
  const fromHash = () => {
    const id = decodeURIComponent(location.hash.slice(1));
    if (!id) return;
    const tab = tabs().find((t) => t.id === id || t.getAttribute('aria-controls') === id);
    if (tab) select(tab, false);
  };
  fromHash();
  window.addEventListener('hashchange', fromHash);
});
