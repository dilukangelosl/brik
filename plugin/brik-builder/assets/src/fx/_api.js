// Shared helpers for effect scripts. They run after the main front-end bundle.

export const on = (selector, init) => window.brik.on(selector, init);

export const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const inCanvas = () => document.body.classList.contains('brik-canvas-mode');

/**
 * Animation loop that only runs while the element is on screen and the tab is visible.
 * draw(time, dt) is called every frame. Returns a stop function.
 */
export function loop(el, draw) {
  let visible = false;
  let raf = 0;
  let last = 0;
  const tick = (t) => {
    const dt = last ? Math.min(t - last, 64) : 16;
    last = t;
    draw(t, dt);
    raf = requestAnimationFrame(tick);
  };
  const start = () => {
    if (!raf && visible && !document.hidden) {
      last = 0;
      raf = requestAnimationFrame(tick);
    }
  };
  const stop = () => {
    cancelAnimationFrame(raf);
    raf = 0;
  };
  const io = new IntersectionObserver(([e]) => {
    visible = e.isIntersecting;
    visible ? start() : stop();
  });
  io.observe(el);
  document.addEventListener('visibilitychange', () => (document.hidden ? stop() : start()));
  return () => {
    stop();
    io.disconnect();
  };
}

/** Canvas sized to its parent, with devicePixelRatio handling. Calls onResize(w, h, dpr). */
export function fitCanvas(canvas, onResize) {
  const resize = () => {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const r = canvas.parentElement.getBoundingClientRect();
    canvas.width = Math.max(1, Math.round(r.width * dpr));
    canvas.height = Math.max(1, Math.round(r.height * dpr));
    canvas.style.width = `${r.width}px`;
    canvas.style.height = `${r.height}px`;
    onResize && onResize(r.width, r.height, dpr);
  };
  new ResizeObserver(resize).observe(canvas.parentElement);
  resize();
}

/** Read a CSS color (including var(--token)) as an rgb string usable in canvas. */
export function cssColor(el, value, fallback = '#888') {
  if (!value) return fallback;
  const probe = document.createElement('span');
  probe.style.color = value;
  probe.style.display = 'none';
  el.appendChild(probe);
  const c = getComputedStyle(probe).color;
  probe.remove();
  return c || fallback;
}
