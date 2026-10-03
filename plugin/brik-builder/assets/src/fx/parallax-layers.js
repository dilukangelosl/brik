// Parallax layers: each layer is offset by (pointer + scroll) × its depth. Offsets ease towards
// their targets inside an rAF loop that only runs while the box is on screen.
import { on, loop } from './_api.js';
import { canHover, isSmall, onScroll, still } from './_3d.js';

on('.brik-pl', (el) => {
  if (still()) return;
  const layers = [...el.querySelectorAll('.brik-pl-layer')]
    .map((node) => ({ node, depth: parseFloat(node.getAttribute('data-depth')) || 0, x: 0, y: 0 }))
    .filter((l) => l.depth !== 0);
  if (!layers.length) return;
  const k = parseFloat(el.getAttribute('data-intensity'));
  const power = (Number.isNaN(k) ? 1 : k) * (isSmall() ? 0.6 : 1);
  let px = 0;
  let py = 0;
  let scroll = 0;

  if (el.getAttribute('data-mouse') === '1' && canHover()) {
    el.addEventListener('pointermove', (e) => {
      const r = el.getBoundingClientRect();
      px = ((e.clientX - r.left) / r.width) * 2 - 1;
      py = ((e.clientY - r.top) / r.height) * 2 - 1;
    });
    el.addEventListener('pointerleave', () => {
      px = 0;
      py = 0;
    });
  }
  if (el.getAttribute('data-scroll') === '1') {
    // -1 when the box sits a viewport below the middle of the screen, +1 a viewport above.
    onScroll(el, (rect, vh) => {
      scroll = Math.max(-1.5, Math.min(1.5, (vh / 2 - (rect.top + rect.height / 2)) / vh));
    });
  }

  loop(el, () => {
    for (const l of layers) {
      const tx = -px * l.depth * 26 * power;
      const ty = (-py * l.depth * 18 - scroll * l.depth * 120) * power;
      const nx = l.x + (tx - l.x) * 0.08;
      const ny = l.y + (ty - l.y) * 0.08;
      if (Math.abs(nx - l.x) < 0.01 && Math.abs(ny - l.y) < 0.01) continue;
      l.x = nx;
      l.y = ny;
      l.node.style.setProperty('--dx', `${nx.toFixed(2)}px`);
      l.node.style.setProperty('--dy', `${ny.toFixed(2)}px`);
    }
  });
});
