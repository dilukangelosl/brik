import { on } from '../core.js';

// Pauses endless CSS animations while they are off screen: effect modules, animated section
// backgrounds, and (once this script is on the page anyway) whole sections, which also covers
// keyframes added through custom CSS.
const io =
  'IntersectionObserver' in window
    ? new IntersectionObserver((entries) => {
        for (const e of entries) e.target.classList.toggle('is-offscreen', !e.isIntersecting);
      })
    : null;

let sections = false;
const watch = (el) => {
  if (!io) return;
  io.observe(el);
  if (!sections) {
    sections = true;
    document.querySelectorAll('.brik-section').forEach((s) => io.observe(s));
  }
};

on('[data-brik-fx-pause]', watch);
on('.brik-bg-effect', watch);

// Decorative looping videos: load and play only while visible.
const videos =
  'IntersectionObserver' in window
    ? new IntersectionObserver(
        (entries) => {
          for (const e of entries) {
            if (e.isIntersecting) e.target.play().catch(() => {});
            else e.target.pause();
          }
        },
        { rootMargin: '200px 0px' }
      )
    : null;

on('video[data-brik-autoplay]', (el) => {
  el.muted = true;
  if (videos) videos.observe(el);
  else el.play().catch(() => {});
});
