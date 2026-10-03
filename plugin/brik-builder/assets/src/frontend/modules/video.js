import { on } from '../core.js';

on('[data-brik-video]', (frame) => {
  const thumb = frame.querySelector('.brik-video-thumb[data-fallback]');
  if (thumb) {
    // YouTube serves a 120px grey placeholder when there is no maxres thumbnail.
    const check = () => {
      if (thumb.naturalWidth && thumb.naturalWidth <= 120) thumb.src = thumb.dataset.fallback;
    };
    if (thumb.complete) check();
    else thumb.addEventListener('load', check, { once: true });
    thumb.addEventListener('error', () => (thumb.src = thumb.dataset.fallback), { once: true });
  }

  const button = frame.querySelector('.brik-video-facade');
  if (!button) return;
  button.addEventListener('click', () => {
    const iframe = document.createElement('iframe');
    iframe.src = frame.dataset.brikVideo;
    iframe.title = frame.dataset.title || 'Video';
    iframe.className = 'absolute inset-0 h-full w-full';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    iframe.allowFullscreen = true;
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    button.replaceWith(iframe);
    iframe.focus();
  });
});
