import { on } from '../core.js';

on('.brik-pricing_table', (el) => {
  const grid = el.querySelector('.brik-pricing-grid');
  const buttons = el.querySelectorAll('[data-brik-billing]');
  if (!grid || !buttons.length) return;
  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      grid.dataset.billing = button.dataset.brikBilling;
      buttons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
    });
  });
});
