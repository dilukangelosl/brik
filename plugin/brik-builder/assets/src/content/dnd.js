// Pointer-driven sorting for the nested field list.
//
// Rows carry data-drop-row (field key), data-parent ('' for top level) and data-index; empty
// lists and list ends carry data-drop-list (parent key) and data-index. While dragging we look
// up the element under the pointer and work out a { parent, index } target, which the editor
// renders as an indicator line and applies on drop.
import { useState, useRef, useEffect } from './wp.js';

export function useFieldDrag({ onDrop, canDrop }) {
  const [drag, setDrag] = useState(null); // { key, label, icon, x, y, target, line }
  const live = useRef(null);
  const scroller = useRef(0);

  useEffect(() => () => cancelAnimationFrame(scroller.current), []);

  const start = (e, field) => {
    if (e.button !== 0) return;
    e.preventDefault();
    const sx = e.clientX;
    const sy = e.clientY;
    let started = false;

    const autoscroll = () => {
      const d = live.current;
      if (!d) return;
      const edge = 70;
      let dy = 0;
      if (d.y < edge + 32) dy = -Math.ceil((edge + 32 - d.y) / 6);
      else if (d.y > window.innerHeight - edge) dy = Math.ceil((d.y - (window.innerHeight - edge)) / 6);
      if (dy) {
        window.scrollBy(0, dy);
        update(d.x, d.y);
      }
      scroller.current = requestAnimationFrame(autoscroll);
    };

    const update = (x, y) => {
      const target = hitTest(x, y, field.key, canDrop);
      live.current = { ...live.current, x, y, target: target && target.drop, line: target && target.line };
      setDrag({ ...live.current });
    };

    const move = (ev) => {
      if (!started) {
        if (Math.abs(ev.clientX - sx) + Math.abs(ev.clientY - sy) < 4) return;
        started = true;
        document.body.classList.add('bk-is-dragging');
        live.current = { key: field.key, label: field.label || field.name || '', type: field.type };
        scroller.current = requestAnimationFrame(autoscroll);
      }
      update(ev.clientX, ev.clientY);
    };

    const up = () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
      window.removeEventListener('keydown', esc);
      cancelAnimationFrame(scroller.current);
      document.body.classList.remove('bk-is-dragging');
      const d = live.current;
      live.current = null;
      setDrag(null);
      if (started && d && d.target) onDrop(d.key, d.target.parent, d.target.index);
    };

    const esc = (ev) => {
      if (ev.key === 'Escape') {
        live.current = { ...live.current, target: null };
        up();
      }
    };

    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    window.addEventListener('keydown', esc);
  };

  return { drag, start };
}

function hitTest(x, y, dragKey, canDrop) {
  const el = document.elementFromPoint(x, y);
  if (!el) return null;

  const list = el.closest('[data-drop-list]');
  const row = el.closest('[data-drop-row]');

  // Prefer the innermost of the two.
  if (list && (!row || row.contains(list))) {
    const parent = list.dataset.dropList || null;
    const index = parseInt(list.dataset.index || '0', 10);
    if (canDrop(dragKey, parent)) {
      const r = list.getBoundingClientRect();
      return { drop: { parent, index }, line: { left: r.left + 8, width: r.width - 16, top: r.top + Math.min(r.height / 2, 14) } };
    }
    return null;
  }
  if (!row) return null;

  const parent = row.dataset.parent || null;
  const index = parseInt(row.dataset.index, 10);
  const head = row.querySelector('[data-row-head]') || row;
  const r = head.getBoundingClientRect();
  const full = row.getBoundingClientRect();
  const before = y < r.top + r.height / 2;
  if (!canDrop(dragKey, parent)) return null;
  if (!before && row.dataset.accepts && row.dataset.dropRow !== dragKey && canDrop(dragKey, row.dataset.dropRow)) {
    return { drop: { parent: row.dataset.dropRow, index: 0 }, line: { left: r.left + 28, width: r.width - 28, top: r.bottom + 2 } };
  }
  if (row.dataset.dropRow === dragKey) return { drop: { parent, index }, line: { left: full.left, width: full.width, top: full.top - 3 } };
  return before ? { drop: { parent, index }, line: { left: full.left, width: full.width, top: full.top - 3 } } : { drop: { parent, index: index + 1 }, line: { left: full.left, width: full.width, top: full.bottom + 2 } };
}
