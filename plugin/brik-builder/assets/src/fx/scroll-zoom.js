// Scroll zoom: while the stage is pinned, --g grows the card to full screen and --t fades the
// overlay text in. Without this script the scene shows its finished state.
import { on } from './_api.js';
import { easeInOut, onScroll, pinProgress, span, still } from './_3d.js';

on('.brik-szi', (el) => {
  if (still()) return;
  el.classList.add('is-live');
  let last = '';
  onScroll(el, (rect, vh) => {
    const p = pinProgress(rect, vh);
    const g = easeInOut(span(p, 0, 0.72)).toFixed(4);
    const t = span(p, 0.7, 0.95).toFixed(3);
    const o = (1 - span(p, 0, 0.22)).toFixed(3);
    if (g + t + o === last) return;
    last = g + t + o;
    el.style.setProperty('--g', g);
    el.style.setProperty('--t', t);
    el.style.setProperty('--o', o);
  });
});
