// Animated list: items pop in at the top one at a time and push the older ones down.
import { on, reducedMotion } from './_api.js';

on('.brik-alist', (root) => {
  const source = root.querySelector('.brik-alist-list');
  if (!source || reducedMotion()) return;
  const templates = [...source.children];
  if (templates.length < 2) return;

  // The original list stays for screen readers; the moving copy is decorative.
  const stage = source.cloneNode(false);
  stage.setAttribute('aria-hidden', 'true');
  stage.classList.add('brik-alist-stage');
  source.classList.add('sr-only');
  root.appendChild(stage);

  const interval = Math.max(400, parseInt(root.dataset.interval, 10) || 1600);
  const loop = root.dataset.loop === '1';
  let index = 0;
  let visible = false;
  let timer = 0;

  const add = () => {
    timer = 0;
    if (!visible) return;
    if (!loop && index >= templates.length) return;

    // FLIP: remember where the current items are, insert, then animate them from there.
    const before = new Map([...stage.children].map((el) => [el, el.getBoundingClientRect().top]));
    const item = templates[index % templates.length].cloneNode(true);
    item.classList.add('is-entering');
    stage.prepend(item);
    index++;
    for (const [el, top] of before) {
      const dy = top - el.getBoundingClientRect().top;
      if (!dy) continue;
      el.animate([{ transform: `translateY(${dy}px)` }, { transform: 'none' }], { duration: 450, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' });
    }
    item.addEventListener('animationend', () => item.classList.remove('is-entering'), { once: true });

    // Drop items that have scrolled out of the box.
    const limit = root.getBoundingClientRect().bottom;
    [...stage.children].forEach((el) => {
      if (el.getBoundingClientRect().top > limit + 40) el.remove();
    });
    timer = setTimeout(add, interval);
  };

  new IntersectionObserver(([e]) => {
    visible = e.isIntersecting;
    if (visible && !timer) timer = setTimeout(add, index ? interval : 200);
  }).observe(root);
});
