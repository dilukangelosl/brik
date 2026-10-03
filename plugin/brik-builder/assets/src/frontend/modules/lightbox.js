import { on } from '../core.js';

// One shared <dialog> serves every image and gallery on the page.
const svg = (paths) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

let box;
let items = [];
let index = 0;

function build() {
  const dialog = document.createElement('dialog');
  dialog.className = 'brik-lightbox';
  dialog.setAttribute('aria-label', 'Image viewer');
  dialog.innerHTML = `
    <figure class="brik-lightbox-figure"><img class="brik-lightbox-img" alt=""><figcaption class="brik-lightbox-caption"></figcaption></figure>
    <div class="brik-lightbox-count" aria-live="polite"></div>
    <button type="button" class="brik-lightbox-btn brik-lightbox-close" aria-label="Close">${svg('<path d="M18 6 6 18"/><path d="m6 6 12 12"/>')}</button>
    <button type="button" class="brik-lightbox-btn brik-lightbox-prev" aria-label="Previous image">${svg('<path d="m15 18-6-6 6-6"/>')}</button>
    <button type="button" class="brik-lightbox-btn brik-lightbox-next" aria-label="Next image">${svg('<path d="m9 18 6-6-6-6"/>')}</button>`;
  document.body.appendChild(dialog);

  const q = (s) => dialog.querySelector(s);
  box = { dialog, img: q('img'), caption: q('figcaption'), count: q('.brik-lightbox-count'), prev: q('.brik-lightbox-prev'), next: q('.brik-lightbox-next') };

  q('.brik-lightbox-close').addEventListener('click', () => dialog.close());
  box.prev.addEventListener('click', () => show(index - 1));
  box.next.addEventListener('click', () => show(index + 1));
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog || e.target.classList.contains('brik-lightbox-figure')) dialog.close();
  });
  dialog.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') show(index - 1);
    else if (e.key === 'ArrowRight') show(index + 1);
  });

  let startX = null;
  dialog.addEventListener('pointerdown', (e) => {
    startX = e.pointerType === 'mouse' ? null : e.clientX;
  });
  dialog.addEventListener('pointerup', (e) => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 40) show(index + (dx < 0 ? 1 : -1));
  });
  dialog.addEventListener('close', () => {
    box.img.removeAttribute('src');
  });
}

function preload(i) {
  const it = items[(i + items.length) % items.length];
  if (it) new Image().src = it.src;
}

function show(i) {
  if (!items.length) return;
  index = (i + items.length) % items.length;
  const it = items[index];
  box.img.src = it.src;
  box.img.alt = it.alt;
  box.caption.textContent = it.caption;
  box.caption.hidden = !it.caption;
  const many = items.length > 1;
  box.prev.hidden = !many;
  box.next.hidden = !many;
  box.count.textContent = many ? `${index + 1} / ${items.length}` : '';
  if (many) {
    preload(index + 1);
    preload(index - 1);
  }
}

on('[data-brik-lightbox]', (el) => {
  el.addEventListener('click', (e) => {
    e.preventDefault();
    if (!box) build();
    const group = el.getAttribute('data-brik-lightbox');
    const links = Array.from(document.querySelectorAll('[data-brik-lightbox]')).filter((a) => a.getAttribute('data-brik-lightbox') === group);
    items = links.map((a) => ({
      src: a.getAttribute('href') || a.dataset.src || '',
      alt: a.querySelector('img')?.alt || '',
      caption: a.dataset.caption || '',
    }));
    show(Math.max(0, links.indexOf(el)));
    box.dialog.showModal();
  });
});
