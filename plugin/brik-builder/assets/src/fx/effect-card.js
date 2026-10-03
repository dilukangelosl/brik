// Pointer effects for effect cards and bento items: spotlight, tilt, magnetic,
// direction-aware overlay and encrypted reveal.
import { on, reducedMotion } from './_api.js';

const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
const finePointer = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches;

/** Calls fn(event, rect) at most once per frame while the pointer moves over el. */
function track(el, fn) {
  let raf = 0;
  let last = null;
  el.addEventListener('pointermove', (e) => {
    if (e.pointerType === 'touch') return;
    last = e;
    if (!raf) {
      raf = requestAnimationFrame(() => {
        raf = 0;
        fn(last, el.getBoundingClientRect());
      });
    }
  });
}

function spotlight(el) {
  track(el, (e, r) => {
    el.style.setProperty('--x', `${e.clientX - r.left}px`);
    el.style.setProperty('--y', `${e.clientY - r.top}px`);
  });
}

function tilt(el) {
  const max = 9;
  track(el, (e, r) => {
    const px = (e.clientX - r.left) / r.width;
    const py = (e.clientY - r.top) / r.height;
    el.style.setProperty('--rx', `${((0.5 - py) * max).toFixed(2)}deg`);
    el.style.setProperty('--ry', `${((px - 0.5) * max).toFixed(2)}deg`);
    el.style.setProperty('--gx', `${(px * 100).toFixed(1)}%`);
    el.style.setProperty('--gy', `${(py * 100).toFixed(1)}%`);
  });
  el.addEventListener('pointerleave', () => {
    el.style.setProperty('--rx', '0deg');
    el.style.setProperty('--ry', '0deg');
  });
}

function magnetic(el) {
  const pull = 0.06;
  track(el, (e, r) => {
    const dx = e.clientX - (r.left + r.width / 2);
    const dy = e.clientY - (r.top + r.height / 2);
    el.style.setProperty('--tx', `${(dx * pull).toFixed(1)}px`);
    el.style.setProperty('--ty', `${(dy * pull).toFixed(1)}px`);
  });
  el.addEventListener('pointerleave', () => {
    el.style.setProperty('--tx', '0px');
    el.style.setProperty('--ty', '0px');
  });
}

/** Edge (top|right|bottom|left) the pointer crossed, from its position relative to the center. */
function edge(e, r) {
  const x = (e.clientX - r.left - r.width / 2) / (r.width / 2);
  const y = (e.clientY - r.top - r.height / 2) / (r.height / 2);
  if (Math.abs(x) > Math.abs(y)) return x > 0 ? 'right' : 'left';
  return y > 0 ? 'bottom' : 'top';
}

function directionAware(el) {
  const panel = el.querySelector('.brik-fx-dir');
  if (!panel) return;
  const slide = panel.firstElementChild;
  el.addEventListener('pointerenter', (e) => {
    if (e.pointerType === 'touch') return;
    // Jump to the entry edge without a transition, then slide in.
    slide.style.transition = 'none';
    panel.dataset.state = 'out';
    panel.dataset.from = edge(e, el.getBoundingClientRect());
    panel.getBoundingClientRect();
    slide.style.transition = '';
    panel.dataset.state = 'in';
  });
  el.addEventListener('pointerleave', (e) => {
    if (e.pointerType === 'touch') return;
    panel.dataset.from = edge(e, el.getBoundingClientRect());
    panel.dataset.state = 'out';
  });
}

function encrypted(el) {
  const layer = el.querySelector('.brik-fx-enc-chars');
  if (!layer) return;
  const fill = () => {
    const r = el.getBoundingClientRect();
    // Roughly enough characters to cover the card in the layer's monospace font.
    const n = Math.min(4000, Math.ceil((r.width / 7.2) * (r.height / 14) * 1.1));
    let s = '';
    for (let i = 0; i < n; i++) s += CHARS[(Math.random() * CHARS.length) | 0];
    layer.textContent = s;
  };
  fill();
  let last = 0;
  track(el, (e, r) => {
    el.style.setProperty('--x', `${e.clientX - r.left}px`);
    el.style.setProperty('--y', `${e.clientY - r.top}px`);
    if (e.timeStamp - last > 60) {
      last = e.timeStamp;
      fill();
    }
  });
}

const handlers = {
  spotlight,
  tilt,
  magnetic,
  direction_aware: directionAware,
  encrypted,
};

on('[data-brik-fx-card]', (el) => {
  const effect = el.dataset.brikFxCard;
  if (!handlers[effect]) return;
  // Spotlight and encrypted are only colour; the moving effects stay still for reduced motion.
  if (reducedMotion() && !['spotlight', 'encrypted'].includes(effect)) return;
  if (!finePointer() && effect !== 'encrypted') return;
  handlers[effect](el);
});

// Bento items with a hover spotlight.
on('.brik-bento--spotlight .brik-bento-item', (el) => {
  if (finePointer()) spotlight(el);
});
