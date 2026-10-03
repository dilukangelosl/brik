// Soft, blurred ribbons of light rolling across the section.
import { on } from './_api.js';
import { settings, canvas, animate, onTheme, rgba, mix } from './_bg.js';

on('[data-brik-effect="waves"]', (el) => {
  const s = settings(el);
  let W = 0;
  let H = 0;
  let D = 1;
  let redraw = () => {};
  const waves = Array.from({ length: 5 }, (_, i) => ({
    f1: 0.0016 + i * 0.00035,
    f2: 0.0041 - i * 0.0004,
    s1: 0.00028 + i * 0.00005,
    s2: 0.00017 + i * 0.00004,
    ph: i * 1.7,
    off: (i - 2) * 0.035,
  }));

  // Rendered at reduced resolution: the blur hides it and it roughly quarters the fill cost.
  const { ctx } = canvas(
    el,
    s,
    (w, h, dpr) => {
      W = w;
      H = h;
      D = dpr;
      el.style.setProperty('--fx-blur', `${Math.max(6, Math.min(14, h / 60))}px`);
      redraw();
    },
    '2d',
    0.5
  );
  onTheme(el, () => {
    s.read();
    redraw();
  });

  const draw = (t) => {
    const [c1, c2] = s.colors;
    ctx.setTransform(D, 0, 0, D, 0, 0);
    ctx.clearRect(0, 0, W, H);
    const amp = Math.min(H * 0.18, 140);
    const lw = Math.max(24, Math.min(56, H / 14));
    const alpha = 0.2 + s.intensity * 0.45;
    ctx.lineWidth = lw;
    ctx.lineCap = 'round';
    waves.forEach((wv, i) => {
      ctx.strokeStyle = rgba(mix(c1, c2, i / (waves.length - 1)), alpha);
      ctx.beginPath();
      for (let x = -20; x <= W + 20; x += 12) {
        const y =
          H * (0.55 + wv.off) +
          amp * (0.6 * Math.sin(x * wv.f1 + t * wv.s1 + wv.ph) + 0.4 * Math.sin(x * wv.f2 - t * wv.s2 + wv.ph * 2.3));
        x === -20 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
      }
      ctx.stroke();
    });
  };
  redraw = animate(el, s, draw);
});
