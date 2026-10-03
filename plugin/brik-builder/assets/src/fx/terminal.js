// Terminal: types commands character by character and reveals output lines in order.
import { on, reducedMotion } from './_api.js';

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

on('.brik-term', (root) => {
  if (reducedMotion()) return;
  const lines = [...root.querySelectorAll('.brik-term-line')];
  if (!lines.length) return;
  const speed = parseInt(root.dataset.speed, 10) || 45;
  const gap = parseInt(root.dataset.gap, 10) || 0;
  const loop = root.dataset.loop === '1';
  const pause = parseInt(root.dataset.pause, 10) || 4000;

  // Commands are split into characters once; hidden characters keep their space.
  const typed = new Map();
  lines.forEach((line) => {
    if (!line.classList.contains('brik-term-line--command')) return;
    const text = line.querySelector('.brik-term-text');
    const chars = Array.from(text.textContent).map((ch) => {
      const s = document.createElement('span');
      s.className = 'brik-term-ch';
      s.textContent = ch;
      return s;
    });
    text.replaceChildren(...chars);
    typed.set(line, chars);
  });
  const caret = document.createElement('span');
  caret.className = 'brik-term-caret';
  caret.setAttribute('aria-hidden', 'true');

  let visible = false;
  let wake = null;
  new IntersectionObserver(([e]) => {
    visible = e.isIntersecting;
    if (visible && wake) {
      wake();
      wake = null;
    }
  }).observe(root);
  const whenVisible = () => (visible ? Promise.resolve() : new Promise((r) => (wake = r)));

  const reset = () => {
    root.classList.add('is-armed');
    lines.forEach((l) => l.classList.remove('is-shown'));
    typed.forEach((chars) => chars.forEach((c) => c.classList.remove('is-shown')));
  };

  const play = async () => {
    for (const line of lines) {
      const extra = parseInt(line.dataset.delay, 10);
      await sleep(Number.isFinite(extra) ? extra : gap);
      await whenVisible();
      line.classList.add('is-shown');
      const chars = typed.get(line);
      if (!chars) continue;
      root.classList.add('is-typing');
      for (const c of chars) {
        c.classList.add('is-shown');
        c.after(caret);
        await sleep(speed * (0.5 + Math.random()));
      }
      root.classList.remove('is-typing');
      // A short beat after hitting enter.
      await sleep(260);
    }
    // Park the caret on a fresh prompt-less line end.
    lines[lines.length - 1].querySelector('.brik-term-text').appendChild(caret);
  };

  (async () => {
    reset();
    if (root.dataset.trigger !== 'load') await whenVisible();
    for (;;) {
      await play();
      if (!loop) return;
      await sleep(pause);
      await whenVisible();
      root.classList.add('is-clearing');
      await sleep(350);
      reset();
      root.classList.remove('is-clearing');
    }
  })();
});
