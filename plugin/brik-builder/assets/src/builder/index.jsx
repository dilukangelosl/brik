import { createRoot, useEffect, useRef, useState, config } from './wp.js';
import * as store from './store.js';
import { loadSchema, loadSettings, loadIcons, backupKey } from './api.js';
import { attach } from './canvas.js';
import { setIcons } from './icons.js';
import { handleKey } from './keys.js';
import { TopBar } from './TopBar.jsx';
import { LeftPanel } from './LeftPanel.jsx';
import { SettingsPanel } from './SettingsPanel.jsx';
import { Modals } from './Modals.jsx';
import { ContextMenu } from './ContextMenu.jsx';
import { Toasts } from './Toasts.jsx';
import { Slot } from './registry.js';
import './features/index.js';

const WIDTHS = { desktop: '100%', tablet: '820px', mobile: '390px' };

// Desktop is previewed at least this wide (scaled to fit) so desktop styles apply.
const DESKTOP_MIN = 1280;

function Canvas() {
  const ref = useRef(null);
  const wrap = useRef(null);
  const device = store.useStore((s) => s.device);
  const customWidth = store.useStore((s) => s.canvasWidth);
  const ready = store.useStore((s) => !!s.canvasReady);
  const [box, setBox] = useState({ w: 0, h: 0 });
  useEffect(() => {
    attach(ref.current);
    const ro = new ResizeObserver(([e]) => setBox({ w: e.contentRect.width, h: e.contentRect.height }));
    ro.observe(wrap.current);
    return () => ro.disconnect();
  }, []);

  // Desktop, and custom widths wider than the canvas area, are scaled down to fit.
  const fitWidth = device === 'desktop' ? DESKTOP_MIN : device === 'custom' && customWidth ? customWidth : 0;
  const scale = fitWidth && box.w && box.w < fitWidth ? box.w / fitWidth : 1;
  const style =
    scale < 1
      ? { width: fitWidth, height: box.h / scale, transform: `scale(${scale})`, transformOrigin: 'top left', position: 'absolute', left: 0, top: 0 }
      : { width: device === 'custom' && customWidth ? `${customWidth}px` : WIDTHS[device], height: '100%' };
  if (device !== 'desktop') style.boxShadow = '0 0 0 1px var(--border), 0 10px 30px -10px rgb(0 0 0 / .2)';

  return (
    <div ref={wrap} className="bk-canvas-wrap relative flex flex-1 justify-center overflow-hidden bg-muted/60">
      <iframe ref={ref} title="Page preview" src={config.canvasUrl} className="bg-white" style={style} />
      <Slot name="canvasOverlay" />
      {!ready && (
        <div className="absolute inset-0 flex items-center justify-center bg-background">
          <span className="bk-spinner" />
        </div>
      )}
    </div>
  );
}

function App() {
  const schema = store.useStore((s) => s.schema);
  if (!schema) {
    return (
      <div className="flex h-screen items-center justify-center">
        <span className="bk-spinner" />
      </div>
    );
  }
  return (
    <div className="flex h-screen flex-col overflow-hidden bg-background text-foreground">
      <TopBar />
      <div className="flex min-h-0 flex-1">
        <LeftPanel />
        <Canvas />
        <aside className="w-80 shrink-0 overflow-hidden border-l border-border bg-background">
          <SettingsPanel />
        </aside>
      </div>
      <Modals />
      <ContextMenu />
      <Toasts />
    </div>
  );
}

async function boot() {
  const root = createRoot(document.getElementById('brik-app'));
  root.render(<App />);

  const [schema, settings, icons] = await Promise.all([loadSchema(), loadSettings(), loadIcons()]);
  setIcons(icons.icons, icons.brands, icons.aliases);
  store.setState({ schema, settings, left: store.getState().tree.length ? null : 'modules' });

  window.addEventListener('keydown', handleKey);
  window.addEventListener('beforeunload', (e) => {
    if (store.getState().dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });

  // Keep a local copy of unsaved work in case the tab closes.
  let timer = null;
  store.subscribe(() => {
    const s = store.getState();
    if (!s.dirty) return;
    clearTimeout(timer);
    timer = setTimeout(() => {
      try {
        localStorage.setItem(backupKey(), JSON.stringify({ time: Date.now(), tree: s.tree, page: s.page }));
      } catch (e) {}
    }, 1000);
  });

  try {
    const backup = JSON.parse(localStorage.getItem(backupKey()) || 'null');
    if (backup && JSON.stringify(backup.tree) !== JSON.stringify(store.getState().tree)) {
      store.openModal('restore', { backup });
    }
  } catch (e) {}
}

boot();
