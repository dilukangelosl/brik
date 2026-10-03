const handlers = [];
let started = false;

/**
 * Run init(element) for every element matching selector, now and whenever the
 * builder canvas inserts new markup.
 */
export function on(selector, init) {
  handlers.push({ selector, init });
  // Effect scripts load later than the main bundle; mount them right away.
  if (started) mount(document);
}

export function mount(root = document) {
  for (const { selector, init } of handlers) {
    root.querySelectorAll(selector).forEach((el) => {
      const key = `brik${selector}`;
      if (el[key]) return;
      el[key] = true;
      try {
        init(el);
      } catch (e) {
        console.error(e);
      }
    });
  }
}

export function ready() {
  const start = () => {
    started = true;
    mount(document);
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
  window.brik = Object.assign(window.brik || {}, { mount, on });
}
