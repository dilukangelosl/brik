// Hero parallax: --q (0 → 1) straightens the tilted plane over the first screen of scrolling,
// --r (0 → 1) slides the rows sideways across the whole scene.
import { on } from './_api.js';
import { easeOut, onScroll, span, still } from './_3d.js';

on('.brik-hpx', (el) => {
  if (still()) return;
  let last = '';
  onScroll(el, (rect, vh) => {
    const y = window.scrollY;
    const top = rect.top + y;
    const start = Math.max(0, top - vh * 0.6);
    const q = easeOut(span(y, start, start + vh * 0.8)).toFixed(4);
    const r = span(y, start, top + rect.height - vh * 0.4).toFixed(4);
    if (q + r === last) return;
    last = q + r;
    el.style.setProperty('--q', q);
    el.style.setProperty('--r', r);
  });
});
