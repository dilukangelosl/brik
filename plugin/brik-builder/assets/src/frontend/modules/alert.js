import { on } from '../core.js';

on('.brik-alert [data-brik-dismiss]', (button) => {
  button.addEventListener('click', () => {
    // Keep the alert in the builder canvas so it stays editable.
    if (document.body.classList.contains('brik-canvas-mode')) return;
    const el = button.closest('.brik-alert');
    if (el) el.hidden = true;
  });
});
