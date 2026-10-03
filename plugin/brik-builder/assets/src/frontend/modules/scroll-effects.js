import { on } from '../core.js';

// Header sections: mark them once the page scrolls, and hide/reveal on scroll direction.
const sections = new Set();
let lastY = window.scrollY;
let ticking = false;

function update() {
  const y = window.scrollY;
  const down = y > lastY;
  for (const el of sections) {
    el.classList.toggle('is-scrolled', y > 8);
    if (el.dataset.brikScroll.includes('hide')) {
      const past = y > el.offsetHeight * 2;
      if (down && past && Math.abs(y - lastY) > 4) el.classList.add('is-hidden');
      else if (!down || !past) el.classList.remove('is-hidden');
    }
  }
  lastY = y;
  ticking = false;
}

window.addEventListener(
  'scroll',
  () => {
    if (!ticking) {
      ticking = true;
      requestAnimationFrame(update);
    }
  },
  { passive: true }
);

on('[data-brik-scroll]', (el) => {
  sections.add(el);
  update();
});
