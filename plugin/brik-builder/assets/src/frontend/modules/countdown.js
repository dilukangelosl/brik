import { on } from '../core.js';

const pad = (n) => String(n).padStart(2, '0');

on('[data-brik-countdown]', (root) => {
  const target = parseInt(root.dataset.target, 10);
  if (!target) return;
  const units = {};
  root.querySelectorAll('[data-unit]').forEach((el) => (units[el.dataset.unit] = el));
  const withDays = root.dataset.days !== '0';
  const box = root.querySelector('.brik-countdown-units');
  const message = root.querySelector('.brik-countdown-expired');
  const canvas = document.body.classList.contains('brik-canvas-mode');
  let timer = 0;

  const render = () => {
    // Stop when the builder replaces this markup.
    if (!root.isConnected) {
      clearInterval(timer);
      return;
    }
    const left = Math.max(0, Math.floor((target - Date.now()) / 1000));
    const days = Math.floor(left / 86400);
    const values = {
      days,
      hours: withDays ? Math.floor(left / 3600) % 24 : Math.floor(left / 3600),
      minutes: Math.floor(left / 60) % 60,
      seconds: left % 60,
    };
    Object.entries(units).forEach(([key, el]) => {
      const text = pad(values[key]);
      if (el.textContent !== text) el.textContent = text;
    });

    if (left > 0) return;
    clearInterval(timer);
    if (canvas) return;
    const mode = root.dataset.expired;
    if (mode === 'hide') {
      root.closest('.brik-el')?.setAttribute('hidden', '');
    } else if (mode === 'message' && message) {
      if (box) box.hidden = true;
      const title = root.querySelector('.brik-countdown-title');
      if (title) title.hidden = true;
      message.hidden = false;
    }
    root.dispatchEvent(new CustomEvent('brik:countdown-end', { bubbles: true }));
  };

  render();
  timer = setInterval(render, 1000);
});
