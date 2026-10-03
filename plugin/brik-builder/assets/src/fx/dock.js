// Dock magnification: icons near the cursor grow, easing toward their target size.
import { on, reducedMotion } from './_api.js';

on('.brik-dock-bar', (dock) => {
  if (reducedMotion() || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  const items = [...dock.querySelectorAll('.brik-dock-item')];
  if (!items.length) return;
  const mag = parseFloat(dock.dataset.mag) || 1.6;
  const reach = parseFloat(dock.dataset.distance) || 140;
  const base = () => parseFloat(getComputedStyle(dock).getPropertyValue('--brik-dock-size')) || 44;

  const current = items.map(() => 1);
  const target = items.map(() => 1);
  let pointerX = null;
  let raf = 0;

  const frame = () => {
    raf = 0;
    const size = base();
    let moving = false;
    items.forEach((item, i) => {
      if (pointerX === null) target[i] = 1;
      else {
        const r = item.getBoundingClientRect();
        const d = Math.abs(pointerX - (r.left + r.width / 2));
        // Smooth falloff: full size under the cursor, back to 1 at the edge of the reach.
        const t = Math.max(0, 1 - d / reach);
        target[i] = 1 + (mag - 1) * (t * t * (3 - 2 * t));
      }
      const next = current[i] + (target[i] - current[i]) * 0.28;
      if (Math.abs(next - target[i]) > 0.002) moving = true;
      current[i] = Math.abs(next - target[i]) > 0.002 ? next : target[i];
      item.style.setProperty('--s', `${(size * current[i]).toFixed(2)}px`);
    });
    if (moving) raf = requestAnimationFrame(frame);
  };
  const kick = () => {
    if (!raf) raf = requestAnimationFrame(frame);
  };

  dock.addEventListener('pointermove', (e) => {
    if (e.pointerType === 'touch') return;
    pointerX = e.clientX;
    kick();
  });
  dock.addEventListener('pointerleave', () => {
    pointerX = null;
    kick();
  });
});
