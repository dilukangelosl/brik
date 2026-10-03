// Big text: scroll scale (--p), and for video fills an SVG clip path that mirrors the real
// headline's lines. The heading stays in the DOM (transparent) for selection and screen readers.
import { on, reducedMotion } from './_api.js';
import { onScroll, span, still } from './_3d.js';

const SVG = 'http://www.w3.org/2000/svg';
let ruler;

function metrics(font) {
  ruler = ruler || document.createElement('canvas').getContext('2d');
  ruler.font = font;
  const m = ruler.measureText('Hg');
  return { ascent: m.fontBoundingBoxAscent || m.actualBoundingBoxAscent, descent: m.fontBoundingBoxDescent || m.actualBoundingBoxDescent };
}

function mask(el) {
  const stage = el.querySelector('.brik-bt-stage');
  const text = el.querySelector('.brik-bt-text');
  const clip = el.querySelector('.brik-bt-clip clipPath');
  const video = el.querySelector('.brik-bt-video');
  if (!stage || !text || !clip || !video) return;

  const layout = () => {
    const cs = getComputedStyle(text);
    const font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
    const lh = parseFloat(cs.lineHeight) || parseFloat(cs.fontSize);
    const { ascent, descent } = metrics(font);
    const left = cs.textAlign === 'left' || cs.textAlign === 'start';
    clip.replaceChildren();
    text.querySelectorAll('.brik-bt-line').forEach((line) => {
      // offset* values ignore the scroll scale transform, which is what the clip needs.
      const t = document.createElementNS(SVG, 'text');
      t.setAttribute('x', left ? line.offsetLeft : line.offsetLeft + line.offsetWidth / 2);
      t.setAttribute('y', line.offsetTop + (lh - (ascent + descent)) / 2 + ascent);
      t.setAttribute('text-anchor', left ? 'start' : 'middle');
      t.style.font = font;
      t.style.letterSpacing = cs.letterSpacing;
      t.textContent = line.innerText;
      clip.appendChild(t);
    });
    el.classList.add('is-ready');
  };

  new ResizeObserver(layout).observe(stage);
  if (document.fonts) document.fonts.ready.then(layout);

  if (reducedMotion()) {
    video.addEventListener('loadeddata', () => (video.currentTime = 0.5), { once: true });
    return;
  }
  // Play only while visible; muted inline playback is allowed without a gesture.
  new IntersectionObserver(([e]) => (e.isIntersecting ? video.play().catch(() => {}) : video.pause())).observe(el);
}

on('.brik-bt', (el) => {
  mask(el);
  if (!el.classList.contains('brik-bt--scale') || still()) return;
  let last = -1;
  onScroll(el, (rect, vh) => {
    const p = Math.round(span(vh - rect.top, 0, vh + rect.height) * 1000) / 1000;
    if (p !== last) {
      last = p;
      el.style.setProperty('--p', p);
    }
  });
});
