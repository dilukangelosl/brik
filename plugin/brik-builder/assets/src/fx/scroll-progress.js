// Scroll progress bar: scales a fixed bar with the page or reading position.
import { on } from './_api.js';

on('.brik-sp', (root) => {
  const bar = root.querySelector('.brik-sp-bar');
  if (!bar) return;
  const reading = root.dataset.target === 'content';
  const find = () =>
    (root.dataset.selector && document.querySelector(root.dataset.selector)) ||
    document.querySelector('.brik-post_content, .brik-post-content, article, main');

  let raf = 0;
  let last = -1;
  const update = () => {
    raf = 0;
    const vh = window.innerHeight;
    let p;
    const target = reading ? find() : null;
    if (target) {
      const r = target.getBoundingClientRect();
      p = (vh * 0.5 - r.top) / Math.max(1, r.height - vh * 0.5);
    } else {
      const max = document.documentElement.scrollHeight - vh;
      p = max > 0 ? window.scrollY / max : 0;
    }
    p = Math.min(1, Math.max(0, p));
    if (Math.abs(p - last) < 0.0005) return;
    last = p;
    bar.style.transform = `scaleX(${p.toFixed(4)})`;
    root.classList.toggle('is-active', p > 0);
  };
  const request = () => {
    if (!raf) raf = requestAnimationFrame(update);
  };
  window.addEventListener('scroll', request, { passive: true });
  window.addEventListener('resize', request);
  update();
});
