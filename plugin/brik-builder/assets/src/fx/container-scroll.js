// Container scroll: --p goes 0 → 1 as the framed card scrolls up into view; CSS turns it into
// the tilt, scale and title lift. Measured on the untransformed stage so the tilt can't feed back.
import { on } from './_api.js';
import { easeOut, onScroll, span, still } from './_3d.js';

on('.brik-cs', (el) => {
  if (still()) return;
  const stage = el.querySelector('.brik-cs-stage') || el;
  let last = -1;
  onScroll(el, () => {
    const vh = window.innerHeight;
    const y = window.scrollY;
    const top = stage.getBoundingClientRect().top + y;
    // Starts as the card enters (or at the very top for a hero) and settles near the top.
    const start = Math.max(0, top - vh);
    const end = Math.max(start + vh * 0.45, top - vh * 0.12);
    const p = Math.round(easeOut(span(y, start, end)) * 1000) / 1000;
    if (p !== last) {
      last = p;
      el.style.setProperty('--p', p);
    }
  });
});
