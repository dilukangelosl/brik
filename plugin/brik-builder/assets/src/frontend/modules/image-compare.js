import { on } from '../core.js';

on('[data-brik-compare]', (el) => {
  const range = el.querySelector('.brik-compare-range');
  if (!range) return;
  const vertical = el.classList.contains('brik-compare--v');

  // The range input carries keyboard and screen reader support; in vertical mode
  // its value is inverted so ArrowUp moves the divider up.
  const set = (pos) => {
    pos = Math.max(0, Math.min(100, pos));
    el.style.setProperty('--brik-pos', `${pos}%`);
    range.value = String(vertical ? 100 - pos : pos);
    range.setAttribute('aria-valuetext', `${Math.round(pos)}%`);
  };

  range.addEventListener('input', () => {
    const v = parseFloat(range.value);
    set(vertical ? 100 - v : v);
  });

  const fromPointer = (e) => {
    const r = el.getBoundingClientRect();
    set(vertical ? ((e.clientY - r.top) / r.height) * 100 : ((e.clientX - r.left) / r.width) * 100);
  };

  el.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    el.setPointerCapture(e.pointerId);
    el.classList.add('is-dragging');
    fromPointer(e);
  });
  el.addEventListener('pointermove', (e) => {
    if (el.hasPointerCapture(e.pointerId)) fromPointer(e);
  });
  const end = (e) => {
    if (el.hasPointerCapture(e.pointerId)) el.releasePointerCapture(e.pointerId);
    el.classList.remove('is-dragging');
    // Focus after the press so the browser's own mousedown focus handling doesn't undo it.
    range.focus({ preventScroll: true });
  };
  el.addEventListener('pointerup', end);
  el.addEventListener('pointercancel', end);
});
