// Animated text effects. The server renders the final text; this only layers motion on top.
import { on, reducedMotion, inCanvas } from './_api.js';

const GLYPHS_UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#%&*+=?';
const GLYPHS_LOWER = 'abcdefghijklmnopqrstuvwxyz0123456789#%&*+=?';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Tracks whether an element is on screen; wait() resolves once it is. */
function visibility(el, threshold = 0) {
  let visible = false;
  let waiters = [];
  const io = new IntersectionObserver(
    ([e]) => {
      visible = e.isIntersecting;
      if (visible) {
        waiters.forEach((w) => w());
        waiters = [];
      }
    },
    { threshold }
  );
  io.observe(el);
  return {
    get visible() {
      return visible;
    },
    wait: () => (visible ? Promise.resolve() : new Promise((r) => waiters.push(r))),
  };
}

/**
 * Wrap every word (and optionally every letter) of the editable parts in spans.
 * Inline markup such as <strong> is kept. Returns the word spans in reading order.
 */
function split(heading, letters) {
  const words = [];
  const chars = [];
  const walker = document.createTreeWalker(heading, NodeFilter.SHOW_TEXT);
  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);
  for (const node of nodes) {
    if (!node.nodeValue.trim()) continue;
    const frag = document.createDocumentFragment();
    for (const piece of node.nodeValue.split(/(\s+)/)) {
      if (!piece) continue;
      if (/^\s+$/.test(piece)) {
        frag.appendChild(document.createTextNode(piece));
        continue;
      }
      const w = document.createElement('span');
      w.className = 'brik-at-w';
      w.setAttribute('aria-hidden', 'true');
      w.style.setProperty('--i', words.length);
      if (letters) {
        for (const ch of Array.from(piece)) {
          const c = document.createElement('span');
          c.className = 'brik-at-c';
          c.textContent = ch;
          c.style.setProperty('--j', chars.length);
          chars.push(c);
          w.appendChild(c);
        }
      } else {
        w.textContent = piece;
      }
      words.push(w);
      frag.appendChild(w);
    }
    node.parentNode.replaceChild(frag, node);
  }
  return { words, chars };
}

/** Screen readers get one plain copy; the split spans are aria-hidden. */
function srCopy(heading, text) {
  const sr = document.createElement('span');
  sr.className = 'sr-only brik-at-sr';
  sr.textContent = text.replace(/\s+/g, ' ').trim();
  heading.appendChild(sr);
}

/**
 * In the builder, double-clicking a part makes it editable. Put the original markup back
 * first so the editor never saves the effect's spans.
 */
function editable(root, state) {
  if (!inCanvas()) return;
  const parts = root.querySelectorAll('[data-brik-inline]');
  const originals = new Map([...parts].map((p) => [p, p.innerHTML]));
  const restore = (part) => {
    if (state.dead) return;
    state.dead = true;
    root.classList.remove('is-armed');
    root.classList.add('is-in', 'is-editing');
    originals.forEach((html, p) => (p.innerHTML = html));
    root.querySelectorAll('.brik-at-sr').forEach((s) => s.remove());
    // The editor already placed the caret; put it back at the end of the restored text.
    const doc = part.ownerDocument;
    const range = doc.createRange();
    range.selectNodeContents(part);
    range.collapse(false);
    const sel = doc.getSelection();
    sel.removeAllRanges();
    sel.addRange(range);
  };
  root.addEventListener('focusin', (e) => {
    const part = e.target.closest('[data-brik-inline]');
    if (part) restore(part);
  });
  // Focus events can be skipped when the canvas frame isn't focused; the attribute isn't.
  const mo = new MutationObserver((records) => {
    for (const r of records) {
      if (r.target.isContentEditable || r.target.getAttribute('contenteditable') === 'true') {
        restore(r.target);
        mo.disconnect();
        return;
      }
    }
  });
  parts.forEach((p) => mo.observe(p, { attributes: true, attributeFilter: ['contenteditable'] }));
}

function startWhen(root, vis) {
  if (root.dataset.trigger === 'load') return Promise.resolve();
  return vis.wait();
}

/* ------------------------------------------------------------------------
 * Word and letter reveals: blur_in, generate, flip3d.
 * ---------------------------------------------------------------------- */

function reveal(root, heading, effect, speed, loop) {
  const state = { dead: false };
  const text = heading.textContent;
  editable(root, state);
  const { words, chars } = split(heading, effect === 'flip3d');
  srCopy(heading, text);

  const count = effect === 'flip3d' ? chars.length : words.length;
  // Long text would take ages word by word, so the whole reveal is capped at about two seconds.
  const stagger = Math.min({ blur_in: 90, generate: 140, flip3d: 28 }[effect], 2000 / Math.max(1, count)) / speed;
  root.style.setProperty('--brik-at-stagger', `${stagger}ms`);
  const total = count * stagger + 900 / speed;

  const vis = visibility(root, 0.25);
  root.classList.add('is-armed');
  // Two frames so the hidden state is painted before the transition starts.
  const play = () =>
    new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(() => {
      root.classList.add('is-in');
      r();
    })));

  (async () => {
    await startWhen(root, vis);
    while (!state.dead) {
      await play();
      if (!loop) return;
      await sleep(total + 2600);
      await vis.wait();
      if (state.dead) return;
      root.classList.remove('is-in');
    }
  })();
}

/* ------------------------------------------------------------------------
 * Scramble: letters decode from random glyphs, again on hover.
 * ---------------------------------------------------------------------- */

function scramble(root, heading, speed, loop) {
  const state = { dead: false, running: false };
  const text = heading.textContent;
  editable(root, state);
  const { chars } = split(heading, true);
  srCopy(heading, text);
  const finals = chars.map((c) => c.textContent);
  // Fixed widths while scrambling so random glyphs never reflow the line. Measured per run,
  // when the final letters are showing, so web fonts and responsive sizes are accounted for.
  const lock = () => {
    chars.forEach((c) => (c.style.width = ''));
    const widths = chars.map((c) => c.getBoundingClientRect().width);
    chars.forEach((c, i) => (c.style.width = `${widths[i]}px`));
  };
  const unlock = () => chars.forEach((c) => (c.style.width = ''));

  const glyph = (ch) => {
    const set = ch === ch.toLowerCase() && ch !== ch.toUpperCase() ? GLYPHS_LOWER : GLYPHS_UPPER;
    return set[(Math.random() * set.length) | 0];
  };

  const run = () =>
    new Promise((resolve) => {
      if (state.running || state.dead) return resolve();
      state.running = true;
      lock();
      const step = 32 / speed;
      const spread = Math.max(8, chars.length * 0.9);
      let frame = 0;
      const tick = () => {
        if (state.dead) return resolve();
        let done = 0;
        chars.forEach((c, i) => {
          if (frame >= i * 0.9 + 6) {
            c.textContent = finals[i];
            c.classList.remove('is-scrambling');
            done++;
          } else if (finals[i].trim()) {
            c.textContent = glyph(finals[i]);
            c.classList.add('is-scrambling');
          }
        });
        frame++;
        if (done < chars.length && frame < spread + 40) setTimeout(tick, step);
        else {
          chars.forEach((c, i) => (c.textContent = finals[i]));
          unlock();
          state.running = false;
          resolve();
        }
      };
      tick();
    });

  const vis = visibility(root, 0.25);
  root.addEventListener('pointerenter', () => run());
  (async () => {
    await startWhen(root, vis);
    if (document.fonts) await document.fonts.ready;
    while (!state.dead) {
      await run();
      if (!loop) return;
      await sleep(4500);
      await vis.wait();
    }
  })();
}

/* ------------------------------------------------------------------------
 * Scroll reveal: words go from faint to full as the headline scrolls through.
 * ---------------------------------------------------------------------- */

function scrollReveal(root, heading) {
  const state = { dead: false };
  const text = heading.textContent;
  editable(root, state);
  const { words } = split(heading, false);
  srCopy(heading, text);
  root.classList.add('is-armed');

  const scroller = window;
  let raf = 0;
  let visible = false;
  const last = new Array(words.length).fill(-1);
  const update = () => {
    raf = 0;
    if (state.dead) return;
    const r = root.getBoundingClientRect();
    const vh = window.innerHeight;
    // 0 when the top enters at 90% of the viewport, 1 when the bottom reaches 45%.
    const start = vh * 0.9;
    const end = vh * 0.45;
    const p = Math.min(1, Math.max(0, (start - r.top) / (start - end + r.height)));
    const n = words.length;
    words.forEach((w, i) => {
      const o = Math.min(1, Math.max(0, p * (n + 2) - i));
      const v = Math.round(o * 100) / 100;
      if (v !== last[i]) {
        last[i] = v;
        w.style.setProperty('--o', v);
      }
    });
  };
  const request = () => {
    if (visible && !raf) raf = requestAnimationFrame(update);
  };
  new IntersectionObserver(([e]) => {
    visible = e.isIntersecting;
    request();
  }).observe(root);
  scroller.addEventListener('scroll', request, { passive: true });
  window.addEventListener('resize', request);
  update();
}

/* ------------------------------------------------------------------------
 * Rotating words.
 * ---------------------------------------------------------------------- */

function rotate(root, speed, loop) {
  const slot = root.querySelector('.brik-at-rotate');
  if (!slot) return;
  const items = [...slot.querySelectorAll('.brik-at-word')];
  if (items.length < 2) return;
  const sr = document.createElement('span');
  sr.className = 'sr-only';
  sr.textContent = items.map((i) => i.textContent).join(', ');
  items[0].setAttribute('aria-hidden', 'true');
  slot.before(sr);

  const vis = visibility(root);
  let index = 0;
  (async () => {
    for (;;) {
      await sleep(2400 / speed);
      await vis.wait();
      const current = items[index];
      index = (index + 1) % items.length;
      const next = items[index];
      current.classList.remove('is-active');
      current.classList.add('is-leaving');
      next.classList.add('is-active');
      setTimeout(() => current.classList.remove('is-leaving'), 700);
      if (!loop && index === items.length - 1) return;
    }
  })();
}

/* ------------------------------------------------------------------------
 * Typewriter.
 * ---------------------------------------------------------------------- */

function typewriter(root, speed, loop) {
  const typed = root.querySelector('.brik-at-typed');
  if (!typed) return;
  let words = [];
  try {
    words = JSON.parse(root.dataset.words || '[]');
  } catch (e) {
    words = [];
  }
  if (!words.length) return;
  const sr = document.createElement('span');
  sr.className = 'sr-only';
  sr.textContent = words.join(', ');
  const slot = typed.closest('.brik-at-slot');
  slot.before(sr);
  slot.setAttribute('aria-hidden', 'true');

  const vis = visibility(root);
  const typeDelay = 75 / speed;
  const eraseDelay = 38 / speed;
  const hold = 1700 / speed;
  const caretTyping = (on) => root.classList.toggle('is-typing', on);

  (async () => {
    await startWhen(root, vis);
    let i = 0;
    // The first phrase is already on screen: show it, then erase and move on.
    if (words.length === 1 && !loop) return;
    for (;;) {
      await sleep(hold);
      await vis.wait();
      const isLast = i === words.length - 1;
      if (isLast && !loop) return;
      caretTyping(true);
      let text = words[i];
      while (text.length) {
        text = Array.from(text).slice(0, -1).join('');
        typed.textContent = text;
        await sleep(eraseDelay);
      }
      i = (i + 1) % words.length;
      await sleep(240 / speed);
      const target = Array.from(words[i]);
      for (let n = 1; n <= target.length; n++) {
        typed.textContent = target.slice(0, n).join('');
        await sleep(typeDelay * (0.6 + Math.random() * 0.8));
      }
      caretTyping(false);
    }
  })();
}

/* ------------------------------------------------------------------------
 * Highlight: the marker draws in once the text is in view.
 * ---------------------------------------------------------------------- */

function highlight(root, speed, loop) {
  const vis = visibility(root, 0.6);
  root.classList.add('is-armed');
  (async () => {
    await startWhen(root, vis);
    for (;;) {
      requestAnimationFrame(() => root.classList.add('is-in'));
      if (!loop) return;
      await sleep(5200 / speed);
      await vis.wait();
      root.classList.remove('is-in');
      await sleep(900 / speed);
    }
  })();
}

/* ------------------------------------------------------------------------
 * Sparkles: small stars twinkle at random spots around the text.
 * ---------------------------------------------------------------------- */

const STAR =
  '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 0c.6 6.4 5 11 12 12-7 1-11.4 5.6-12 12-.6-6.4-5-11-12-12 7-1 11.4-5.6 12-12Z"/></svg>';

function sparkles(root, speed) {
  const box = root.querySelector('.brik-at-sparkles');
  if (!box) return;
  const count = 7;
  const place = (s) => {
    s.style.left = `${Math.random() * 100}%`;
    s.style.top = `${Math.random() * 100}%`;
    s.style.setProperty('--size', `${(0.28 + Math.random() * 0.32).toFixed(2)}em`);
    s.style.setProperty('--dur', `${((1.4 + Math.random() * 1.4) / speed).toFixed(2)}s`);
    s.style.setProperty('--tone', Math.random() > 0.5 ? 'var(--brik-at-c1, #6366f1)' : 'var(--brik-at-c3, #ec4899)');
  };
  for (let i = 0; i < count; i++) {
    const s = document.createElement('span');
    s.className = 'brik-at-star';
    s.innerHTML = STAR;
    place(s);
    s.style.animationDelay = `${(-Math.random() * 2).toFixed(2)}s`;
    if (!reducedMotion()) s.addEventListener('animationiteration', () => place(s));
    box.appendChild(s);
  }
}

on('.brik-at', (root) => {
  const effect = root.dataset.brikAt;
  const heading = root.querySelector('.brik-at-heading');
  const speed = Math.max(0.25, parseFloat(root.dataset.speed) || 1);
  const loop = root.dataset.loop === '1';
  if (!heading) return;

  // Sparkles are decoration; a still set is fine with reduced motion.
  if (effect === 'sparkles') return sparkles(root, speed);
  if (reducedMotion()) return;

  switch (effect) {
    case 'blur_in':
    case 'generate':
    case 'flip3d':
      return reveal(root, heading, effect, speed, loop);
    case 'scramble':
      return scramble(root, heading, speed, loop);
    case 'scroll_reveal':
      return scrollReveal(root, heading);
    case 'rotate':
      return rotate(root, speed, loop);
    case 'typewriter':
      return typewriter(root, speed, loop);
    case 'highlight':
      return highlight(root, speed, loop);
  }
});
