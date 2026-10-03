// Twinkling star field with the occasional shooting star.
import { on } from './_api.js';
import { settings, canvas, animate, onTheme, rgba } from './_bg.js';

on('[data-brik-effect="stars"]', (el) => {
  const s = settings(el);
  let stars = [];
  let shots = [];
  let next = 1200;
  let W = 0;
  let H = 0;
  let D = 1;
  let redraw = () => {};

  const { ctx } = canvas(el, s, (w, h, dpr) => {
    W = w;
    H = h;
    D = dpr;
    const n = Math.min(s.lite ? 220 : 520, Math.round(((w * h) / 2600) * (0.3 + s.intensity)));
    stars = Array.from({ length: n }, () => {
      const big = Math.random() < 0.08;
      return {
        x: Math.random() * w,
        y: Math.random() * h,
        r: big ? 0.9 + Math.random() * 0.7 : 0.3 + Math.random() * 0.6,
        a: 0.25 + Math.random() * 0.6,
        tw: Math.random() < 0.6 ? 0.0006 + Math.random() * 0.0024 : 0,
        ph: Math.random() * Math.PI * 2,
      };
    });
    redraw();
  });
  onTheme(el, () => {
    s.read();
    redraw();
  });

  const spawn = () => {
    // Enter from the top or left edge, travel down-right at a shallow angle.
    const ang = (Math.PI / 180) * (20 + Math.random() * 25);
    const fromTop = Math.random() < 0.6;
    shots.push({
      x: fromTop ? Math.random() * W * 0.8 : -20,
      y: fromTop ? -20 : Math.random() * H * 0.5,
      vx: Math.cos(ang),
      vy: Math.sin(ang),
      v: 0.6 + Math.random() * 0.5,
      len: 90 + Math.random() * 120,
      life: 0,
    });
  };

  const draw = (t, dt) => {
    const [c1, c2] = s.colors;
    ctx.setTransform(D, 0, 0, D, 0, 0);
    ctx.clearRect(0, 0, W, H);
    const gain = 0.45 + s.intensity * 0.55;
    // One fill colour, per-star alpha: far cheaper than a colour string per star.
    ctx.fillStyle = rgba(c1, 1);
    for (const st of stars) {
      const a = st.tw ? st.a * (0.55 + 0.45 * Math.sin(st.ph + t * st.tw)) : st.a;
      ctx.globalAlpha = a * gain;
      if (st.r > 0.9) {
        ctx.beginPath();
        ctx.arc(st.x, st.y, st.r, 0, Math.PI * 2);
        ctx.fill();
      } else {
        ctx.fillRect(st.x - st.r, st.y - st.r, st.r * 2, st.r * 2);
      }
    }
    ctx.globalAlpha = 1;
    if (s.still) return;

    next -= dt;
    if (next <= 0) {
      spawn();
      next = (2600 + Math.random() * 3400) / (0.5 + s.intensity);
    }
    shots = shots.filter((sh) => {
      sh.life += dt;
      sh.x += sh.vx * sh.v * dt;
      sh.y += sh.vy * sh.v * dt;
      const tail = Math.min(sh.len, sh.life * 0.6);
      const tx = sh.x - sh.vx * tail;
      const ty = sh.y - sh.vy * tail;
      const g = ctx.createLinearGradient(sh.x, sh.y, tx, ty);
      g.addColorStop(0, rgba(c1, 0.95));
      g.addColorStop(0.25, rgba(c2, 0.5));
      g.addColorStop(1, rgba(c2, 0));
      ctx.strokeStyle = g;
      ctx.lineWidth = 1.4;
      ctx.lineCap = 'round';
      ctx.beginPath();
      ctx.moveTo(sh.x, sh.y);
      ctx.lineTo(tx, ty);
      ctx.stroke();
      ctx.fillStyle = rgba(c1, 0.9);
      ctx.beginPath();
      ctx.arc(sh.x, sh.y, 1.4, 0, Math.PI * 2);
      ctx.fill();
      return sh.x < W + sh.len && sh.y < H + sh.len;
    });
  };
  redraw = animate(el, s, draw);
});
