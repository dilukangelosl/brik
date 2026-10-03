import { on } from '../core.js';

const still = () =>
  document.body.classList.contains('brik-canvas-mode') ||
  window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
  !('IntersectionObserver' in window);

const ease = (t) => 1 - Math.pow(1 - t, 3);

export function format(value, decimals, separator) {
  const fixed = Math.abs(value).toFixed(decimals);
  const [int, frac] = fixed.split('.');
  const grouped = separator ? int.replace(/\B(?=(\d{3})+(?!\d))/g, separator) : int;
  const point = separator === '.' ? ',' : '.';
  return (value < 0 ? '-' : '') + grouped + (frac ? point + frac : '');
}

function readValue(el) {
  return {
    start: parseFloat(el.dataset.start) || 0,
    end: parseFloat(el.dataset.end) || 0,
    decimals: parseInt(el.dataset.decimals, 10) || 0,
    separator: el.dataset.separator ?? ',',
  };
}

function whenVisible(el, run) {
  const io = new IntersectionObserver(
    (entries) => {
      if (entries.some((e) => e.isIntersecting)) {
        io.disconnect();
        run();
      }
    },
    { threshold: 0.4 }
  );
  io.observe(el);
}

function animate(duration, step) {
  const t0 = performance.now();
  const tick = (now) => {
    const t = Math.min(1, (now - t0) / duration);
    step(ease(t));
    if (t < 1) requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
}

// The markup already holds the final value, so nothing is lost if this never runs.
on('[data-brik-counter]', (root) => {
  const el = root.querySelector('.brik-counter-value');
  if (!el || still()) return;
  const { start, end, decimals, separator } = readValue(el);
  const duration = parseInt(el.dataset.duration, 10) || 2000;
  const final = el.textContent;
  el.textContent = format(start, decimals, separator);
  whenVisible(root, () =>
    animate(duration, (p) => {
      el.textContent = p >= 1 ? final : format(start + (end - start) * p, decimals, separator);
    })
  );
});

on('[data-brik-circle]', (root) => {
  const bar = root.querySelector('.brik-ring-bar');
  const num = root.querySelector('.brik-counter-value');
  if (!bar || still()) return;
  const percent = parseFloat(root.dataset.percent) || 0;
  const duration = parseInt(root.dataset.duration, 10) || 1600;
  const decimals = num ? parseInt(num.dataset.decimals, 10) || 0 : 0;
  const final = num ? num.textContent : '';

  bar.style.strokeDashoffset = '100';
  if (num) num.textContent = '0';
  whenVisible(root, () =>
    animate(duration, (p) => {
      bar.style.strokeDashoffset = String(100 - percent * p);
      if (num) num.textContent = p >= 1 ? final : format(percent * p, decimals, '');
    })
  );
});
