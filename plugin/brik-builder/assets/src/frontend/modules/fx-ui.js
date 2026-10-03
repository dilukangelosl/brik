import { on } from '../core.js';

// Pauses the endless CSS animations of effect modules while they are off screen.
const io =
  'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        for (const e of entries) e.target.classList.toggle('is-offscreen', !e.isIntersecting);
      })
    : null;

on('[data-brik-fx-pause]', (el) => {
  if (io) io.observe(el);
});
