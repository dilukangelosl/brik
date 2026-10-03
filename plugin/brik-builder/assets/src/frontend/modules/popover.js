import { on } from '../core.js';

// Tooltips and hover cards: CSS shows them on hover and keyboard focus. This adds tap to
// open on touch screens, Escape to dismiss, and flips the side when it would leave the viewport.
const opposite = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };

function place(pop) {
  const content = pop.querySelector('.brik-pop-content');
  if (!content) return;
  const side = pop.dataset.side || 'top';
  pop.dataset.placed = side;
  content.style.removeProperty('--brik-shift');
  const r = content.getBoundingClientRect();
  const vw = document.documentElement.clientWidth;
  const vh = window.innerHeight;
  const out =
    (side === 'top' && r.top < 0) ||
    (side === 'bottom' && r.bottom > vh) ||
    (side === 'left' && r.left < 0) ||
    (side === 'right' && r.right > vw);
  if (out) pop.dataset.placed = opposite[side];

  // Keep top/bottom content inside the viewport horizontally.
  const r2 = content.getBoundingClientRect();
  let shift = 0;
  if (r2.left < 8) shift = 8 - r2.left;
  else if (r2.right > vw - 8) shift = vw - 8 - r2.right;
  if (shift) content.style.setProperty('--brik-shift', `${Math.round(shift)}px`);
}

function close(pop) {
  pop.dataset.state = 'closed';
}

on('[data-brik-pop]', (pop) => {
  const trigger = pop.querySelector('.brik-pop-trigger');
  if (!trigger) return;
  const isCard = pop.classList.contains('brik-hover-card');

  // Content is display:none until CSS shows it, so measure on the next frame.
  const later = () => requestAnimationFrame(() => place(pop));
  pop.addEventListener('pointerenter', later);
  pop.addEventListener('focusin', later);

  let touch = false;
  let wasOpen = false;
  trigger.addEventListener('pointerdown', (e) => {
    touch = e.pointerType !== 'mouse';
    wasOpen = pop.dataset.state === 'open';
  });
  trigger.addEventListener('click', (e) => {
    if (!touch) return;
    // On touch the first tap on a hover card shows it; the second follows the link.
    if (isCard && wasOpen) return;
    e.preventDefault();
    document.querySelectorAll('[data-brik-pop][data-state="open"]').forEach((p) => p !== pop && close(p));
    pop.dataset.state = wasOpen ? 'closed' : 'open';
    if (!wasOpen) later();
  });

  pop.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      pop.dataset.state = 'dismissed';
      trigger.addEventListener('blur', () => close(pop), { once: true });
    }
  });
  pop.addEventListener('pointerleave', () => {
    if (pop.dataset.state === 'dismissed') close(pop);
  });
});

document.addEventListener('pointerdown', (e) => {
  document.querySelectorAll('[data-brik-pop][data-state="open"]').forEach((p) => {
    if (!p.contains(e.target)) close(p);
  });
});
