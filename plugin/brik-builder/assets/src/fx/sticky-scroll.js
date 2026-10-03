// Sticky scroll: the step closest to the middle of the viewport is active; its visual fades in
// on the pinned panel. The rail fill follows the middle of the viewport down the rail.
import { on } from './_api.js';
import { onScroll, span } from './_3d.js';

on('.brik-ss', (el) => {
  const steps = [...el.querySelectorAll('.brik-ss-step')];
  const layers = [...el.querySelectorAll('.brik-ss-layer')];
  const rail = el.querySelector('.brik-ss-rail');
  const fill = el.querySelector('.brik-ss-rail-fill');
  if (!steps.length) return;
  let active = -1;
  let filled = -1;

  const activate = (i) => {
    if (i === active) return;
    active = i;
    steps.forEach((s, n) => s.classList.toggle('is-active', n === i));
    layers.forEach((l, n) => l.classList.toggle('is-active', n === i));
  };

  onScroll(el, (rect, vh) => {
    const mid = vh / 2;
    let best = 0;
    let dist = Infinity;
    steps.forEach((s, i) => {
      const r = s.getBoundingClientRect();
      const d = Math.abs(r.top + r.height / 2 - mid);
      if (d < dist) {
        dist = d;
        best = i;
      }
    });
    activate(best);
    if (fill && rail) {
      const t = rail.getBoundingClientRect();
      const p = Math.round(span(mid - t.top, 0, t.height) * 1000) / 1000;
      if (p !== filled) {
        filled = p;
        fill.style.transform = `scaleY(${p})`;
      }
    }
  });
});
