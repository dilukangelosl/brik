// Drifting particles joined by faint lines; they scatter away from the pointer.
import { on } from './_api.js';
import { settings, canvas, animate, pointer, onTheme, rgba } from './_bg.js';

on('[data-brik-effect="particles"]', (el) => {
  const s = settings(el);
  const ptr = pointer(el);
  const pts = [];
  let W = 0;
  let H = 0;
  let D = 1;
  let redraw = () => {};

  const { ctx } = canvas(el, s, (w, h, dpr) => {
    W = w;
    H = h;
    D = dpr;
    // Density scales with area so wide heroes don't get sparse; capped for the O(n²) links.
    const target = Math.min(s.lite ? 70 : 140, Math.round(((w * h) / 9000) * (0.4 + s.intensity)));
    while (pts.length < target) {
      pts.push({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        r: 0.6 + Math.random() * 1.4,
        ox: 0,
        oy: 0,
      });
    }
    pts.length = target;
    redraw();
  });
  onTheme(el, () => {
    s.read();
    redraw();
  });

  const link = 120;
  const draw = (t, dt) => {
    const k = dt / 16;
    ctx.setTransform(D, 0, 0, D, 0, 0);
    ctx.clearRect(0, 0, W, H);
    const [c1, c2] = s.colors;
    const a = 0.35 + s.intensity * 0.65;

    for (const p of pts) {
      p.x += p.vx * k;
      p.y += p.vy * k;
      if (p.x < -10) p.x = W + 10;
      if (p.x > W + 10) p.x = -10;
      if (p.y < -10) p.y = H + 10;
      if (p.y > H + 10) p.y = -10;
      // Pointer pushes an offset that eases back, so the field reshapes and settles.
      if (s.interactive && ptr.active) {
        const dx = p.x + p.ox - ptr.x;
        const dy = p.y + p.oy - ptr.y;
        const d2 = dx * dx + dy * dy;
        if (d2 < 22500 && d2 > 0.01) {
          const d = Math.sqrt(d2);
          const f = ((150 - d) / 150) * 3 * k;
          p.ox += (dx / d) * f;
          p.oy += (dy / d) * f;
        }
      }
      p.ox *= 0.94;
      p.oy *= 0.94;
    }

    ctx.lineWidth = 1;
    for (let i = 0; i < pts.length; i++) {
      const p = pts[i];
      const px = p.x + p.ox;
      const py = p.y + p.oy;
      for (let j = i + 1; j < pts.length; j++) {
        const q = pts[j];
        const dx = px - q.x - q.ox;
        const dy = py - q.y - q.oy;
        const d2 = dx * dx + dy * dy;
        if (d2 < link * link) {
          ctx.strokeStyle = rgba(c2, (1 - Math.sqrt(d2) / link) * 0.22 * a);
          ctx.beginPath();
          ctx.moveTo(px, py);
          ctx.lineTo(q.x + q.ox, q.y + q.oy);
          ctx.stroke();
        }
      }
    }
    ctx.fillStyle = rgba(c1, 0.55 + a * 0.4);
    ctx.beginPath();
    for (const p of pts) {
      ctx.moveTo(p.x + p.ox + p.r, p.y + p.oy);
      ctx.arc(p.x + p.ox, p.y + p.oy, p.r, 0, Math.PI * 2);
    }
    ctx.fill();
  };
  redraw = animate(el, s, draw, 0);
});
