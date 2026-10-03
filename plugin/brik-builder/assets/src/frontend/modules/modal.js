import { on } from '../core.js';

const open = (dialog) => {
  if (dialog && !dialog.open && typeof dialog.showModal === 'function') dialog.showModal();
};

on('[data-brik-modal]', (btn) => {
  btn.addEventListener('click', (e) => {
    e.preventDefault();
    open(document.getElementById(btn.dataset.brikModal));
  });
});

on('dialog.brik-dialog', (dialog) => {
  dialog.addEventListener('click', (e) => {
    if (e.target.closest('[data-brik-close]')) {
      dialog.close();
      return;
    }
    // A click on the dialog box itself (not the panel) is a click on the backdrop.
    if (e.target === dialog && dialog.hasAttribute('data-backdrop-close')) dialog.close();
  });

  const delay = parseInt(dialog.dataset.autoOpen || '0', 10);
  if (delay > 0 && !document.body.classList.contains('brik-canvas-mode')) {
    const key = `brik-modal-${dialog.id}`;
    let seen = false;
    try {
      seen = dialog.hasAttribute('data-once') && sessionStorage.getItem(key) === '1';
    } catch (err) {
      seen = false;
    }
    if (!seen) {
      setTimeout(() => {
        open(dialog);
        try {
          sessionStorage.setItem(key, '1');
        } catch (err) {
          // Storage can be unavailable in private modes; the modal simply shows again.
        }
      }, delay * 1000);
    }
  }
});

// Any link to #modal-id opens that modal.
document.addEventListener('click', (e) => {
  const link = e.target.closest?.('a[href^="#"]');
  if (!link || link.getAttribute('href').length < 2) return;
  let target = null;
  try {
    target = document.querySelector(link.getAttribute('href'));
  } catch (err) {
    return;
  }
  if (target && target.matches('dialog.brik-dialog')) {
    e.preventDefault();
    open(target);
  }
});
