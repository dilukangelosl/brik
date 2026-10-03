import { on } from '../core.js';

const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function countUp(el, ms) {
  const target = parseFloat(el.dataset.brikPct) || 0;
  const start = performance.now();
  const tick = (now) => {
    const t = Math.min(1, (now - start) / ms);
    // Matches the bar's ease-out curve closely enough that the number and bar finish together.
    const eased = 1 - Math.pow(1 - t, 4);
    el.textContent = `${Math.round(target * eased)}%`;
    if (t < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

function fill(list) {
  list.classList.add('is-in');
  if (reduced) return;
  const ms = parseFloat(getComputedStyle(list).getPropertyValue('--brik-bar-ms')) || 1200;
  list.querySelectorAll('[data-brik-pct]').forEach((el) => countUp(el, ms));
}

const io =
  'IntersectionObserver' in window
    ? new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (entry.isIntersecting) {
              io.unobserve(entry.target);
              fill(entry.target);
            }
          }
        },
        { threshold: 0.25 }
      )
    : null;

on('.brik-progress--animate', (list) => {
  if (io) io.observe(list);
  else list.classList.add('is-in');
});
