import { on } from '../core.js';

const io =
  'IntersectionObserver' in window
    ? new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              entry.target.classList.add('is-in');
              io.unobserve(entry.target);
            }
          }
        },
        { rootMargin: '0px 0px -10% 0px' }
      )
    : null;

if (io && !document.body?.classList.contains('brik-canvas-mode')) {
  document.documentElement.classList.add('brik-anim-ready');
}

on('[data-brik-anim]', (el) => {
  if (io) io.observe(el);
  else el.classList.add('is-in');
});
