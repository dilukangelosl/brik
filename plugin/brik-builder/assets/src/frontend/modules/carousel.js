import { on } from '../core.js';

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

on('[data-brik-carousel]', (root) => {
  const track = root.querySelector('.brik-carousel-track');
  if (!track) return;
  const slides = Array.from(track.children);
  if (slides.length < 2) return;

  const prev = root.querySelector('.brik-carousel-prev');
  const next = root.querySelector('.brik-carousel-next');
  const dotsWrap = root.querySelector('.brik-carousel-dots');
  const playBtn = root.querySelector('.brik-carousel-play');
  const loop = root.hasAttribute('data-loop');
  const canvas = document.body.classList.contains('brik-canvas-mode');
  let dots = [];
  let pages = 1;

  const offset = (i) => slides[i].offsetLeft - slides[0].offsetLeft;
  const step = () => offset(1) || track.clientWidth;
  const maxScroll = () => track.scrollWidth - track.clientWidth;
  const current = () => {
    if (track.scrollLeft >= maxScroll() - 2) return pages - 1;
    return Math.min(pages - 1, Math.round(track.scrollLeft / step()));
  };

  const goTo = (i, smooth = true) => {
    if (loop) i = (i + pages) % pages;
    i = Math.max(0, Math.min(pages - 1, i));
    track.scrollTo({ left: Math.min(offset(i), maxScroll()), behavior: smooth && !reduced() ? 'smooth' : 'auto' });
  };

  const update = () => {
    const i = current();
    dots.forEach((d, j) => d.setAttribute('aria-current', String(i === j)));
    if (prev) prev.disabled = !loop && i <= 0;
    if (next) next.disabled = !loop && i >= pages - 1;
    const visible = Math.round(track.clientWidth / step());
    slides.forEach((s, j) => {
      const shown = j >= i && j < i + Math.max(1, visible);
      s.toggleAttribute('inert', !shown && !canvas);
      s.setAttribute('aria-hidden', String(!shown));
    });
  };

  // Snap positions depend on how many cards fit, so dots are rebuilt on resize.
  const build = () => {
    const visible = Math.max(1, Math.round(track.clientWidth / step()));
    pages = Math.max(1, slides.length - visible + 1);
    if (dotsWrap && dotsWrap.childElementCount !== pages) {
      dotsWrap.textContent = '';
      dots = [];
      for (let j = 0; j < pages; j++) {
        const d = document.createElement('button');
        d.type = 'button';
        d.className = 'brik-carousel-dot';
        d.setAttribute('aria-label', `${j + 1} / ${pages}`);
        d.addEventListener('click', () => goTo(j));
        dotsWrap.appendChild(d);
        dots.push(d);
      }
    }
    root.classList.toggle('is-static', pages < 2);
    update();
  };

  let raf = 0;
  track.addEventListener(
    'scroll',
    () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(update);
    },
    { passive: true }
  );
  prev?.addEventListener('click', () => goTo(current() - 1));
  next?.addEventListener('click', () => goTo(current() + 1));
  track.addEventListener('keydown', (e) => {
    if (e.target !== track) return;
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      goTo(current() - 1);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      goTo(current() + 1);
    }
  });

  if ('ResizeObserver' in window) new ResizeObserver(build).observe(track);
  build();

  // Autoplay: off in the builder, with reduced motion, while hovered/focused or the tab is hidden.
  const delay = parseInt(root.dataset.autoplay || '0', 10);
  if (!delay) return;
  if (canvas || reduced()) {
    playBtn?.remove();
    return;
  }
  let timer = 0;
  let paused = false;
  let hold = false;
  const tick = () => {
    const i = current();
    if (!loop && i >= pages - 1) goTo(0);
    else goTo(i + 1);
  };
  const start = () => {
    clearInterval(timer);
    if (!paused && !hold && !document.hidden) timer = setInterval(tick, delay);
  };
  const stop = () => clearInterval(timer);

  if (root.hasAttribute('data-pause-hover')) {
    root.addEventListener('pointerenter', () => ((hold = true), stop()));
    root.addEventListener('pointerleave', () => ((hold = false), start()));
  }
  root.addEventListener('focusin', () => ((hold = true), stop()));
  root.addEventListener('focusout', (e) => {
    if (!root.contains(e.relatedTarget)) {
      hold = false;
      start();
    }
  });
  track.addEventListener('pointerdown', () => {
    paused = true;
    stop();
    syncPlay();
  });
  document.addEventListener('visibilitychange', start);

  const syncPlay = () => {
    if (!playBtn) return;
    playBtn.setAttribute('aria-label', paused ? playBtn.dataset.playLabel : playBtn.dataset.pauseLabel);
    playBtn.querySelector('.brik-carousel-pause-icon')?.classList.toggle('hidden', paused);
    playBtn.querySelector('.brik-carousel-play-icon')?.classList.toggle('hidden', !paused);
  };
  playBtn?.addEventListener('click', () => {
    paused = !paused;
    syncPlay();
    if (paused) stop();
    else {
      hold = false;
      start();
    }
  });
  start();
});
