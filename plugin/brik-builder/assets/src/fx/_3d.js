// Math, scroll and color helpers shared by the 3D and scroll-driven effects.
import { reducedMotion, inCanvas } from './_api.js';

export const clamp = (v, min = 0, max = 1) => Math.min(max, Math.max(min, v));
export const lerp = (a, b, t) => a + (b - a) * t;
/** Where v sits between a and b, clamped to 0..1. */
export const span = (v, a, b) => clamp((v - a) / (b - a || 1));
export const easeOut = (t) => 1 - Math.pow(1 - t, 3);
export const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

export const isSmall = () => window.matchMedia('(max-width: 767px)').matches;
export const canHover = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/** Effects render their resting state in the builder and for visitors who prefer less motion. */
export const still = () => reducedMotion() || inCanvas();

/** Read a JSON data attribute without throwing on bad markup. */
export function data(el, name, fallback = {}) {
  try {
    return JSON.parse(el.getAttribute(`data-${name}`) || '') || fallback;
  } catch (e) {
    return fallback;
  }
}

/**
 * Call update(rect, viewportHeight) once per frame while the page scrolls or resizes, but only
 * while the element is near the viewport. Listeners are passive and work is batched in rAF so
 * reads (rects) and writes (transforms) never interleave. Returns a function forcing an update.
 */
export function onScroll(el, update, margin = '25%') {
  let raf = 0;
  let active = false;
  const run = () => {
    raf = 0;
    if (!el.isConnected) return detach();
    update(el.getBoundingClientRect(), window.innerHeight);
  };
  const queue = () => {
    if (!raf) raf = requestAnimationFrame(run);
  };
  const attach = () => {
    if (active) return;
    active = true;
    window.addEventListener('scroll', queue, { passive: true });
    window.addEventListener('resize', queue, { passive: true });
    queue();
  };
  const detach = () => {
    active = false;
    window.removeEventListener('scroll', queue);
    window.removeEventListener('resize', queue);
  };
  new IntersectionObserver(([e]) => (e.isIntersecting ? attach() : (queue(), detach())), {
    rootMargin: `${margin} 0px`,
  }).observe(el);
  queue();
  return queue;
}

/**
 * Progress of an element scrolling through the viewport: 0 when its top meets the
 * viewport at `from` (fraction of the viewport height) and 1 when it reaches `to`.
 */
export const enterProgress = (rect, vh, from = 1, to = 0) => span(vh * from - rect.top, 0, vh * (from - to));

/** Progress through a tall "pinned" scene: 0 when it reaches the top, 1 when its end does. */
export const pinProgress = (rect, vh) => span(-rect.top, 0, Math.max(1, rect.height - vh));

/** Add .is-paused while the element is off screen so CSS animations stop costing frames. */
export function pauseOffscreen(el, onChange) {
  new IntersectionObserver(([e]) => {
    el.classList.toggle('is-paused', !e.isIntersecting);
    if (e.isIntersecting) el.classList.add('is-in');
    onChange && onChange(e.isIntersecting);
  }).observe(el);
}

let probe;
/** Any CSS color (hex, oklch, var(--token)) as [r, g, b, a] in 0..1, resolved in el's context. */
export function rgba(el, value, fallback = [0.5, 0.5, 0.5, 1]) {
  if (!value) return fallback;
  const tmp = document.createElement('span');
  tmp.style.cssText = 'display:none';
  tmp.style.color = value;
  if (!tmp.style.color) return fallback;
  el.appendChild(tmp);
  const computed = getComputedStyle(tmp).color;
  tmp.remove();
  // Let the canvas convert modern color spaces to sRGB.
  probe = probe || document.createElement('canvas').getContext('2d', { willReadFrequently: true });
  if (!probe) return fallback;
  probe.clearRect(0, 0, 1, 1);
  probe.fillStyle = '#000';
  probe.fillStyle = computed;
  probe.fillRect(0, 0, 1, 1);
  const [r, g, b, a] = probe.getImageData(0, 0, 1, 1).data;
  return [r / 255, g / 255, b / 255, a / 255];
}

/** Run fn when the color scheme may have changed (system setting, theme toggle, class swap). */
export function onThemeChange(fn) {
  let t = 0;
  const later = () => {
    clearTimeout(t);
    t = setTimeout(fn, 50);
  };
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', later);
  const mo = new MutationObserver(later);
  mo.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
  mo.observe(document.body, { attributes: true, attributeFilter: ['class'] });
}

/** Decode the 180×90 land bitmap printed by brik_3d_land_mask(). */
export function landMask(b64) {
  let bytes = new Uint8Array(0);
  try {
    bytes = Uint8Array.from(atob(b64 || ''), (c) => c.charCodeAt(0));
  } catch (e) {
    /* An empty mask simply draws an ocean globe. */
  }
  return (lat, lng) => {
    const row = Math.min(89, Math.max(0, Math.floor((90 - lat) / 2)));
    const col = Math.min(179, Math.max(0, Math.floor((lng + 180) / 2)));
    const bit = row * 180 + col;
    return bit >> 3 < bytes.length && (bytes[bit >> 3] & (0x80 >> (bit & 7))) !== 0;
  };
}

/** Unit vector for a latitude/longitude in degrees. Longitude 0 faces +z. */
export function sphere(lat, lng) {
  const p = (lat * Math.PI) / 180;
  const l = (lng * Math.PI) / 180;
  return [Math.cos(p) * Math.sin(l), Math.sin(p), Math.cos(p) * Math.cos(l)];
}

/**
 * Pointer-driven tilt: calls apply(x, y) with values in -1..1, eased towards the pointer while
 * hovering and back to 0 on leave. Only runs frames while easing.
 */
export function hoverTilt(el, apply, ease = 0.12) {
  let tx = 0;
  let ty = 0;
  let x = 0;
  let y = 0;
  let raf = 0;
  const tick = () => {
    x += (tx - x) * ease;
    y += (ty - y) * ease;
    apply(x, y);
    raf = Math.abs(tx - x) + Math.abs(ty - y) > 0.001 ? requestAnimationFrame(tick) : 0;
  };
  const kick = () => {
    if (!raf) raf = requestAnimationFrame(tick);
  };
  el.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse') return;
    const r = el.getBoundingClientRect();
    tx = clamp(((e.clientX - r.left) / r.width) * 2 - 1, -1, 1);
    ty = clamp(((e.clientY - r.top) / r.height) * 2 - 1, -1, 1);
    kick();
  });
  el.addEventListener('pointerleave', () => {
    tx = 0;
    ty = 0;
    kick();
  });
}
