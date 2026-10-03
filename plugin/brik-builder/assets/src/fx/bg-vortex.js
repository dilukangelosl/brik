// Particles swirling around the center of the section, leaving fading trails.
import { on } from './_api.js';
import { settings, canvas, animate, pointer, onTheme, rgba, mix } from './_bg.js';

on('[data-brik-effect="vortex"]', (el) => {
  const s = settings(el);
  const ptr = pointer(el);
  let pts = [];
  let W = 0;
  let H = 0;
  let D = 1;
  let redraw = () => {};

  const reset = (p, fresh) => {
    // Spawn on a ring band so the swirl reads as a vortex, not noise.
    const ang = Math.random() * Math.PI * 2;
    const rad = (0.15 + Math.random() * 0.75) * Math.max(W, H) * 0.6;
    p.x = W / 2 + Math.cos(ang) * rad;
    p.y = H / 2 + Math.sin(ang) * rad * 0.6;
    p.vx = 0;
    p.vy = 0;
    p.life = fresh ? Math.random() * 400 : 0;
    p.ttl = 200 + Math.random() * 300;
    p.sp = 0.6 + Math.random() * 1.2;
    p.w = 0.6 + Math.random() * 1.6;
    p.c = Math.random();
    return p;
  };

  let ctx = null;
  ctx = canvas(el, s, (w, h, dpr) => {
    W = w;
    H = h;
    D = dpr;
    const n = Math.min(s.lite ? 350 : 900, Math.round(((w * h) / 1500) * (0.3 + s.intensity)));
    pts = Array.from({ length: n }, () => reset({}, true));
    redraw();
  }).ctx;
  onTheme(el, () => s.read());

  const step = (t, k, fade) => {
    const [c1, c2] = s.colors;
    ctx.setTransform(D, 0, 0, D, 0, 0);
    if (fade) {
      ctx.globalCompositeOperation = 'destination-out';
      ctx.fillStyle = `rgba(0,0,0,${0.06 * k})`;
      ctx.fillRect(0, 0, W, H);
      ctx.globalCompositeOperation = 'source-over';
    }
    const cx = W / 2;
    const cy = H / 2;
    const gain = 0.35 + s.intensity * 0.6;
    const near = s.interactive && ptr.active;
    // A handful of pre-mixed strokes; each particle picks the nearest and sets its alpha.
    const strokes = [0, 1, 2, 3, 4, 5, 6, 7].map((i) => rgba(mix(c1, c2, i / 7), 1));
    ctx.lineCap = 'round';
    for (const p of pts) {
      const dx = p.x - cx;
      const dy = (p.y - cy) / 0.6;
      const r = Math.sqrt(dx * dx + dy * dy) + 1;
      // Tangent to the ring, bent by a slow-moving field and a faint pull inwards.
      let a = Math.atan2(dy, dx) + Math.PI / 2 + 0.25;
      a += Math.sin(p.x * 0.004 + t * 0.0002) * Math.cos(p.y * 0.004 - t * 0.00015) * 0.9;
      let tx = Math.cos(a) * p.sp;
      let ty = Math.sin(a) * p.sp * 0.6;
      tx -= (dx / r) * 0.12;
      ty -= (dy / r) * 0.08;
      if (near) {
        const mx = p.x - ptr.x;
        const my = p.y - ptr.y;
        const d2 = mx * mx + my * my;
        if (d2 < 32400) {
          const f = (1 - Math.sqrt(d2) / 180) * 2.2;
          tx += (-my / 180) * f + (mx / 180) * f * 0.5;
          ty += (mx / 180) * f + (my / 180) * f * 0.5;
        }
      }
      p.vx += (tx - p.vx) * 0.08 * k;
      p.vy += (ty - p.vy) * 0.08 * k;
      const ox = p.x;
      const oy = p.y;
      p.x += p.vx * k;
      p.y += p.vy * k;
      p.life += k;
      const lf = p.life / p.ttl;
      const fio = lf < 0.5 ? lf * 2 : (1 - lf) * 2;
      ctx.strokeStyle = strokes[Math.round(p.c * 7)];
      ctx.globalAlpha = Math.max(0, fio) * gain;
      ctx.lineWidth = p.w;
      ctx.beginPath();
      ctx.moveTo(ox, oy);
      ctx.lineTo(p.x, p.y);
      ctx.stroke();
      if (p.life > p.ttl || p.x < -50 || p.x > W + 50 || p.y < -50 || p.y > H + 50) reset(p, false);
    }
    ctx.globalAlpha = 1;
  };

  const draw = (t, dt) => {
    if (s.still) {
      ctx.setTransform(1, 0, 0, 1, 0, 0);
      ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
      for (let i = 0; i < 90; i++) step(i * 16, 1, true);
      return;
    }
    step(t, Math.min(dt, 48) / 16, true);
  };
  redraw = animate(el, s, draw);
});
