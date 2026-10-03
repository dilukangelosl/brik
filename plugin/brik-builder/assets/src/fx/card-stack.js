// Card stack: cards keep their DOM order; only their stack position (--i) changes, so the
// transition is a transform and nothing reflows. Manual navigation stops the timer for good.
import { on } from './_api.js';
import { still } from './_3d.js';

on('.brik-cardstack', (el) => {
  const list = el.querySelector('.brik-cardstack-cards');
  const cards = list ? [...list.children] : [];
  if (cards.length < 2) return;
  const order = cards.map((_, i) => i);
  let timer = 0;
  let hover = false;
  let visible = true;
  let stopped = !el.hasAttribute('data-autoplay') || still();

  const apply = () => {
    order.forEach((card, pos) => {
      const c = cards[card];
      c.style.setProperty('--i', pos);
      c.setAttribute('aria-hidden', pos ? 'true' : 'false');
      c.inert = pos !== 0;
    });
  };
  const next = () => {
    const front = cards[order[0]];
    front.classList.remove('is-leaving');
    void front.offsetWidth; // restart the leave animation when stepping quickly
    front.classList.add('is-leaving');
    order.push(order.shift());
    apply();
  };
  const prev = () => {
    order.unshift(order.pop());
    apply();
  };
  cards.forEach((c) => c.addEventListener('animationend', () => c.classList.remove('is-leaving')));

  const schedule = () => {
    clearTimeout(timer);
    if (stopped || hover || !visible) return;
    timer = setTimeout(() => {
      next();
      schedule();
    }, Number(el.getAttribute('data-interval')) || 5000);
  };
  const manual = (fn) => {
    stopped = true;
    clearTimeout(timer);
    list.setAttribute('aria-live', 'polite');
    fn();
  };

  list.addEventListener('click', (e) => {
    if (!e.target.closest('a, button') && !window.getSelection().toString()) manual(next);
  });
  list.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowRight' || e.key === 'ArrowDown') {
      e.preventDefault();
      manual(next);
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
      e.preventDefault();
      manual(prev);
    }
  });
  el.querySelectorAll('.brik-cardstack-btn').forEach((b) =>
    b.addEventListener('click', () => manual(b.dataset.dir === 'prev' ? prev : next))
  );
  if (el.hasAttribute('data-pause-hover')) {
    el.addEventListener('pointerenter', () => ((hover = true), schedule()));
    el.addEventListener('pointerleave', () => ((hover = false), schedule()));
  }
  el.addEventListener('focusin', () => ((hover = true), schedule()));
  el.addEventListener('focusout', () => ((hover = false), schedule()));
  new IntersectionObserver(([e]) => {
    visible = e.isIntersecting;
    schedule();
  }).observe(el);

  apply();
  schedule();
});
