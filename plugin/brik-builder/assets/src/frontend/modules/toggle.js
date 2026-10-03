import { on } from '../core.js';

on('[data-brik-showmore]', (el) => {
  const content = el.querySelector('.brik-showmore-content');
  const btn = el.querySelector('.brik-showmore-btn');
  if (!content || !btn) return;
  const label = btn.querySelector('span');

  const set = (open) => {
    el.dataset.state = open ? 'open' : 'closed';
    btn.setAttribute('aria-expanded', String(open));
    if (label) label.textContent = open ? btn.dataset.less : btn.dataset.more;
  };

  // Nothing to reveal when the content already fits.
  const measure = () => {
    if (el.dataset.state !== 'closed') return;
    el.classList.toggle('is-short', content.scrollHeight <= content.clientHeight + 2);
  };

  btn.addEventListener('click', () => {
    const open = el.dataset.state !== 'open';
    set(open);
    if (!open && el.getBoundingClientRect().top < 0) el.scrollIntoView({ block: 'start', behavior: 'smooth' });
  });

  if ('ResizeObserver' in window) new ResizeObserver(measure).observe(content);
  else measure();
});
