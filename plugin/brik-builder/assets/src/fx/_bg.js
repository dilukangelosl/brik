// Shared plumbing for the section background effects (bg-*.js).
import { loop, fitCanvas, cssColor, reducedMotion, inCanvas } from './_api.js';

let probe;

/** Any CSS color (oklch, color-mix, var()) as [r, g, b] 0-255. */
export function rgb(el, value) {
  if (!probe) {
    const c = document.createElement('canvas');
    c.width = c.height = 1;
    probe = c.getContext('2d', { willReadFrequently: true });
  }
  probe.clearRect(0, 0, 1, 1);
  probe.fillStyle = '#888';
  probe.fillStyle = cssColor(el, value, '#888');
  probe.fillRect(0, 0, 1, 1);
  const d = probe.getImageData(0, 0, 1, 1).data;
  return [d[0], d[1], d[2]];
}

export const rgba = (c, a) => `rgba(${c[0]},${c[1]},${c[2]},${a})`;

export const mix = (a, b, t) => [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t, a[2] + (b[2] - a[2]) * t];

/**
 * Settings from the data attributes and custom properties PHP printed.
 * `lite` is the builder preview: lower resolution and frame rate.
 */
export function settings(el) {
  const d = el.dataset;
  const s = {
    intensity: Math.max(0, Math.min(1, (Number(d.intensity) || 0) / 100)),
    speed: (Number(d.speed) || 0) / 100,
    interactive: d.interactive === '1',
    lite: inCanvas(),
    colors: [],
  };
  s.still = reducedMotion() || s.speed === 0;
  s.read = () => {
    s.colors = [rgb(el, 'var(--fx-color)'), rgb(el, 'var(--fx-color2)')];
  };
  s.read();
  return s;
}

/** Re-run cb when the page switches between light and dark. */
export function onTheme(el, cb) {
  const mo = new MutationObserver(() => {
    if (!el.isConnected) return mo.disconnect();
    cb();
  });
  mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
  mo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
}

/** Canvas filling the effect layer. onResize(w, h, dpr) runs now and on every resize. */
export function canvas(el, s, onResize, type = '2d', res = 0) {
  const c = document.createElement('canvas');
  el.appendChild(c);
  const ctx = type === '2d' ? c.getContext('2d') : c.getContext('webgl', { premultipliedAlpha: true, antialias: false, alpha: true });
  fitCanvas(c, (w, h, dpr) => {
    // Soft effects can render below 1x; the builder preview never goes above 1x.
    const want = res || (s.lite ? Math.min(dpr, 1) : dpr);
    if (want !== dpr) {
      c.width = Math.max(1, Math.round(w * want));
      c.height = Math.max(1, Math.round(h * want));
      dpr = want;
    }
    onResize(w, h, dpr);
  });
  return { canvas: c, ctx };
}

/**
 * Drive draw(t, dt) in a loop that pauses off-screen. t is scaled by the speed setting.
 * With reduced motion (or speed 0) it draws a single frame at `still` ms instead.
 * Returns a function that redraws the still frame (call it after a resize).
 */
export function animate(el, s, draw, stillAt = 4000) {
  if (s.still) {
    const once = () => draw(stillAt, 16);
    once();
    return once;
  }
  let t = 0;
  let acc = 0;
  const stop = loop(el, (now, dt) => {
    if (!el.isConnected) {
      stop();
      return;
    }
    if (s.lite) {
      // About 30fps in the builder.
      acc += dt;
      if (acc < 30) return;
      dt = acc;
      acc = 0;
    }
    t += dt * s.speed;
    draw(t, dt * s.speed);
  });
  return () => {};
}

/** Pointer position relative to the effect layer, tracked on the section itself. */
export function pointer(el) {
  const p = { x: 0, y: 0, active: false };
  const host = el.parentElement || el;
  host.addEventListener(
    'pointermove',
    (e) => {
      const r = el.getBoundingClientRect();
      p.x = e.clientX - r.left;
      p.y = e.clientY - r.top;
      p.active = true;
    },
    { passive: true }
  );
  host.addEventListener('pointerleave', () => {
    p.active = false;
  });
  return p;
}

/**
 * Ease the --mx / --my custom properties towards the pointer, or along a slow drift
 * when the pointer is away (or the effect isn't interactive).
 */
export function follow(el, s) {
  if (s.still) return;
  const ptr = pointer(el);
  let x = 0.5;
  let y = 0.4;
  let last = '';
  animate(el, s, (t, dt) => {
    const w = el.clientWidth || 1;
    const h = el.clientHeight || 1;
    let tx = 0.5 + 0.3 * Math.sin(t * 0.00023);
    let ty = 0.4 + 0.2 * Math.sin(t * 0.00031 + 1.3);
    if (s.interactive && ptr.active) {
      tx = ptr.x / w;
      ty = ptr.y / h;
    }
    const k = 1 - Math.pow(0.88, dt / 16 || 1);
    x += (tx - x) * k;
    y += (ty - y) * k;
    // Each write repaints the masked layers, so skip sub-pixel changes.
    const key = `${(x * 100).toFixed(1)},${(y * 100).toFixed(1)}`;
    if (key === last) return;
    last = key;
    el.style.setProperty('--mx', `${(x * 100).toFixed(1)}%`);
    el.style.setProperty('--my', `${(y * 100).toFixed(1)}%`);
  });
}
