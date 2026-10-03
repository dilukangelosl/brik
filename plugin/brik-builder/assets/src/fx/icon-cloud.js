// Icon cloud: icons on a Fibonacci sphere, projected with plain 2D transforms each frame.
import { on, loop, reducedMotion } from './_api.js';

on('.brik-cloud', (root) => {
  const items = [...root.querySelectorAll('.brik-cloud-item')];
  const n = items.length;
  if (!n) return;
  const speed = parseFloat(root.dataset.speed) || 1;
  const interactive = root.dataset.interactive === '1';

  // Evenly spread points on a unit sphere.
  const golden = Math.PI * (3 - Math.sqrt(5));
  const points = items.map((_, i) => {
    const y = n === 1 ? 0 : 1 - (i / (n - 1)) * 2;
    const r = Math.sqrt(1 - y * y);
    return [Math.cos(golden * i) * r, y, Math.sin(golden * i) * r];
  });

  root.classList.add('is-3d');
  let radius = 0;
  const measure = () => {
    const icon = items[0].offsetWidth || 34;
    radius = Math.max(40, root.clientWidth / 2 - icon * 0.7);
  };
  new ResizeObserver(measure).observe(root);
  measure();

  let ax = -0.35;
  let ay = 0;
  const base = 0.00022 * speed;
  let vx = 0;
  let vy = base;
  let steer = null;
  let drag = null;

  const render = () => {
    const cx = Math.cos(ax);
    const sx = Math.sin(ax);
    const cy = Math.cos(ay);
    const sy = Math.sin(ay);
    for (let i = 0; i < n; i++) {
      const [x0, y0, z0] = points[i];
      // Rotate around Y, then X.
      const x1 = x0 * cy + z0 * sy;
      const z1 = -x0 * sy + z0 * cy;
      const y2 = y0 * cx - z1 * sx;
      const z2 = y0 * sx + z1 * cx;
      const depth = (z2 + 1) / 2;
      const scale = 0.55 + depth * 0.6;
      const s = items[i].style;
      s.transform = `translate(${(x1 * radius).toFixed(1)}px, ${(y2 * radius).toFixed(1)}px) scale(${scale.toFixed(3)})`;
      s.opacity = (0.22 + depth * 0.78).toFixed(2);
      s.zIndex = String(Math.round(depth * 100));
    }
  };

  render();
  if (reducedMotion()) return;

  if (interactive) {
    root.addEventListener('pointermove', (e) => {
      const r = root.getBoundingClientRect();
      if (drag) {
        ay += (e.clientX - drag.x) * 0.006;
        ax -= (e.clientY - drag.y) * 0.006;
        vy = (e.clientX - drag.x) * 0.00035;
        vx = -(e.clientY - drag.y) * 0.00035;
        drag = { x: e.clientX, y: e.clientY };
        return;
      }
      if (e.pointerType === 'touch') return;
      // The further from the center, the faster it turns toward the cursor.
      steer = [((e.clientX - r.left) / r.width - 0.5) * 2, ((e.clientY - r.top) / r.height - 0.5) * 2];
    });
    root.addEventListener('pointerleave', () => (steer = null));
    root.addEventListener('pointerdown', (e) => {
      drag = { x: e.clientX, y: e.clientY };
      root.setPointerCapture(e.pointerId);
      root.classList.add('is-dragging');
    });
    const end = () => {
      drag = null;
      root.classList.remove('is-dragging');
    };
    root.addEventListener('pointerup', end);
    root.addEventListener('pointercancel', end);
  }

  loop(root, (t, dt) => {
    if (!drag) {
      const tx = steer ? -steer[1] * 0.0011 * speed : 0;
      const ty = steer ? steer[0] * 0.0011 * speed : base;
      // Ease toward the target spin so steering and drag releases feel weighty.
      vx += (tx - vx) * 0.04;
      vy += (ty - vy) * 0.04;
      ax += vx * dt;
      ay += vy * dt;
    }
    render();
  });
});
