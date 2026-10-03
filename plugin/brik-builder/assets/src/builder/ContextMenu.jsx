import { useEffect } from './wp.js';
import * as store from './store.js';
import * as T from './tree.js';
import { addAfter } from './canvas.js';
import { Icon } from './icons.js';

export function ContextMenu() {
  const menu = store.useStore((s) => s.menu);
  const hasClip = store.useStore((s) => !!s.clipboard);
  const hasStyles = store.useStore((s) => !!s.styleClipboard);

  useEffect(() => {
    if (!menu) return;
    const close = () => store.setState({ menu: null });
    window.addEventListener('mousedown', close);
    window.addEventListener('blur', close);
    return () => {
      window.removeEventListener('mousedown', close);
      window.removeEventListener('blur', close);
    };
  }, [menu]);

  if (!menu) return null;
  const s = store.getState();
  const node = T.find(s.tree, menu.id);
  if (!node) return null;
  const parent = T.parentOf(s.tree, node.id);

  const items = [
    ['plus', 'Add after', () => addAfter(node.id)],
    ['copy', 'Duplicate', () => store.duplicateNode(node.id), '⌘D'],
    ['clipboard-copy', 'Copy', () => store.copyNode(node.id), '⌘C'],
    ['clipboard-paste', 'Paste after', () => store.pasteAfter(node.id), '⌘V', !hasClip],
    null,
    ['paintbrush', 'Copy styles', () => store.copyStyles(node.id)],
    ['paint-roller', 'Paste styles', () => store.pasteStyles(node.id), '', !hasStyles],
    ['eraser', 'Reset styles', () => store.resetStyles(node.id)],
    null,
    ['library', 'Save to library', () => store.openModal('save-library', { id: node.id })],
    ...(parent ? [['arrow-up-left', 'Select parent', () => store.select(parent.id), 'Esc']] : []),
    null,
    ['trash-2', 'Delete', () => store.removeNode(node.id), 'Del'],
  ];

  const x = Math.min(menu.x, window.innerWidth - 220);
  const y = Math.min(menu.y, window.innerHeight - 380);
  return (
    <div className="fixed z-[1100] w-52 rounded-lg border border-border bg-popover p-1 text-sm text-popover-foreground shadow-lg" style={{ left: x, top: y }} onMouseDown={(e) => e.stopPropagation()}>
      {items.map((item, i) =>
        item ? (
          <button
            key={item[1]}
            type="button"
            disabled={item[4]}
            className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent cursor-pointer"
            onClick={() => {
              store.setState({ menu: null });
              item[2]();
            }}
          >
            <Icon name={item[0]} size={14} className="text-muted-foreground" />
            <span className="flex-1">{item[1]}</span>
            {item[3] && <span className="text-[10px] text-muted-foreground">{item[3]}</span>}
          </button>
        ) : (
          <hr key={`sep${i}`} className="my-1 border-border" />
        )
      )}
    </div>
  );
}
