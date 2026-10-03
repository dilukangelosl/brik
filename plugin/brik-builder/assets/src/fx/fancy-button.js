// Fancy buttons: magnetic pull and click ripples.
import { on, reducedMotion } from './_api.js';

const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

function magnetic(el) {
  // Track a padded area around the button so the pull starts before the pointer touches it.
  const reach = 40;
  const strength = 0.3;
  let raf = 0;
  let active = false;
  const reset = () => {
    active = false;
    el.style.setProperty('--mx', '0px');
    el.style.setProperty('--my', '0px');
    el.classList.remove('is-pulled');
  };
  window.addEventListener(
    'pointermove',
    (e) => {
      if (raf || e.pointerType === 'touch') return;
      raf = requestAnimationFrame(() => {
        raf = 0;
        const r = el.getBoundingClientRect();
        const inside = e.clientX > r.left - reach && e.clientX < r.right + reach && e.clientY > r.top - reach && e.clientY < r.bottom + reach;
        if (!inside) {
          if (active) reset();
          return;
        }
        active = true;
        el.classList.add('is-pulled');
        const dx = e.clientX - (r.left + r.width / 2);
        const dy = e.clientY - (r.top + r.height / 2);
        el.style.setProperty('--mx', `${(dx * strength).toFixed(1)}px`);
        el.style.setProperty('--my', `${(dy * strength).toFixed(1)}px`);
      });
    },
    { passive: true }
  );
  document.addEventListener('pointerleave', reset);
}

function ripple(el) {
  el.addEventListener('pointerdown', (e) => {
    const r = el.getBoundingClientRect();
    const size = Math.max(r.width, r.height) * 2.2;
    const dot = document.createElement('span');
    dot.className = 'brik-fxb-ripple';
    dot.setAttribute('aria-hidden', 'true');
    dot.style.width = dot.style.height = `${size}px`;
    dot.style.left = `${e.clientX - r.left - size / 2}px`;
    dot.style.top = `${e.clientY - r.top - size / 2}px`;
    el.appendChild(dot);
    dot.addEventListener('animationend', () => dot.remove());
  });
}

on('[data-brik-fxb="magnetic"]', (el) => {
  if (finePointer() && !reducedMotion()) magnetic(el);
});

on('[data-brik-fxb="ripple"]', (el) => {
  if (!reducedMotion()) ripple(el);
});
