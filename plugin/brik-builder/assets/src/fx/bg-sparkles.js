// Field of tiny glinting specks that drift upwards and pulse in and out.
import { on } from './_api.js';
import { settings, canvas, animate, pointer, onTheme, rgba } from './_bg.js';

on('[data-brik-effect="sparkles"]', (el) => {
  const s = settings(el);
  const ptr = pointer(el);
  let pts = [];
  let W = 0;
  let H = 0;
  let D = 1;
  let redraw = () => {};

  const { ctx } = canvas(el, s, (w, h, dpr) => {
    W = w;
    H = h;
    D = dpr;
    const n = Math.min(s.lite ? 400 : 1200, Math.round(((w * h) / 800) * (0.25 + s.intensity)));
    pts = Array.from({ length: n }, () => ({
      x: Math.random() * w,
      y: Math.random() * h,
      r: 0.4 + Math.random() * 1.2,
      vy: -(0.004 + Math.random() * 0.012),
      vx: (Math.random() - 0.5) * 0.008,
      f: 0.0008 + Math.random() * 0.003,
      ph: Math.random() * Math.PI * 2,
      hue: Math.random(),
    }));
    redraw();
  });
  onTheme(el, () => {
    s.read();
    redraw();
  });

  const draw = (t, dt) => {
    const [c1, c2] = s.colors;
    ctx.setTransform(D, 0, 0, D, 0, 0);
    ctx.clearRect(0, 0, W, H);
    const gain = 0.7 + s.intensity * 0.3;
    const near = s.interactive && ptr.active;
    const fills = [rgba(c1, 1), rgba(c2, 1)];
    for (const p of pts) {
      p.x += p.vx * dt;
      p.y += p.vy * dt;
      if (p.y < -4) {
        p.y = H + 4;
        p.x = Math.random() * W;
      }
      let a = 0.5 + 0.5 * Math.sin(p.ph + t * p.f);
      a = 0.1 + 0.9 * a * a;
      let r = p.r;
      // Specks near the pointer flare up a little.
      if (near) {
        const dx = p.x - ptr.x;
        const dy = p.y - ptr.y;
        const d2 = dx * dx + dy * dy;
        if (d2 < 25600) {
          const k = 1 - Math.sqrt(d2) / 160;
          a = Math.min(1, a + k * 0.8);
          r += k * 0.8;
        }
      }
      if (a < 0.02) continue;
      ctx.fillStyle = fills[p.hue < 0.75 ? 0 : 1];
      ctx.globalAlpha = a * gain;
      ctx.beginPath();
      ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.globalAlpha = 1;
  };
  redraw = animate(el, s, draw);
});
