// Breakpoint simulator: any canvas width from 320 to 1920px, with the edited breakpoint shown.
import { useState, useEffect, useRef, useLayoutEffect } from '../../wp.js';
import * as store from '../../store.js';
import { Icon } from '../../icons.js';
import { cn } from '../../ui.jsx';
import { throttleRaf, BREAKPOINTS } from './util.js';

export const MIN_W = 320;
export const MAX_W = 1920;

export const SNAPS = [
  { w: 1920, label: '1920' },
  { w: 1440, label: '1440', note: 'Laptop' },
  { w: 1280, label: '1280' },
  { w: 1024, label: '1024', note: 'iPad landscape' },
  { w: 980, label: '980', note: 'Tablet breakpoint', bp: true },
  { w: 820, label: '820', note: 'iPad Air' },
  { w: 768, label: '768', note: 'iPad portrait' },
  { w: 767, label: '767', note: 'Mobile breakpoint', bp: true },
  { w: 600, label: '600' },
  { w: 390, label: '390', note: 'iPhone' },
  { w: 360, label: '360', note: 'Android' },
  { w: 320, label: '320' },
];

// Snap marks with a printed label; the rest are ticks only, so labels never collide.
const LABELLED = [390, 600, 767, 980, 1280, 1440];

const START = { desktop: 1440, tablet: 820, mobile: 390 };

export function deviceFor(w) {
  return w <= BREAKPOINTS.mobile ? 'mobile' : w <= BREAKPOINTS.tablet ? 'tablet' : 'desktop';
}

const RANGE = { desktop: `≥ ${BREAKPOINTS.tablet + 1}px`, tablet: `${BREAKPOINTS.mobile + 1}–${BREAKPOINTS.tablet}px`, mobile: `≤ ${BREAKPOINTS.mobile}px` };
const LABEL = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };

const setWidth = throttleRaf((w) => {
  store.setState({ device: 'custom', canvasWidth: Math.round(Math.max(MIN_W, Math.min(MAX_W, w))) });
});

export function clampWidth(w) {
  return Math.round(Math.max(MIN_W, Math.min(MAX_W, w)));
}

/* ------------------------------------------------------------------------
 * Top bar: "Custom" button + width readout.
 * ---------------------------------------------------------------------- */

export function SimulatorButton() {
  const device = store.useStore((s) => s.device);
  const width = store.useStore((s) => s.canvasWidth);
  const custom = device === 'custom';
  const w = width || 1280;
  const editing = deviceFor(w);
  const toggle = () => {
    if (custom) store.setState({ device: editing });
    else store.setState({ device: 'custom', canvasWidth: START[device] || width || 1280 });
  };
  return (
    <>
      <button
        type="button"
        data-bk-sim-toggle
        title={custom ? 'Exit custom width' : 'Custom width — drag to any screen size'}
        onClick={toggle}
        className={cn('inline-flex h-7 items-center justify-center gap-1.5 rounded-md px-2 cursor-pointer', custom ? 'bg-background text-foreground shadow-sm' : 'w-9 text-muted-foreground hover:text-foreground')}
      >
        <Icon name="ruler-dimension-line" size={15} />
        {custom && (
          <span className="flex items-center gap-1.5 text-xs">
            <span className="font-mono tabular-nums" data-bk-sim-width>
              {w}
            </span>
            <span className="rounded bg-muted px-1 py-px text-[10px] font-medium text-muted-foreground">{LABEL[editing]}</span>
          </span>
        )}
      </button>
    </>
  );
}

/* ------------------------------------------------------------------------
 * Canvas overlay: ruler, edge handles, readout.
 * ---------------------------------------------------------------------- */

export function SimulatorOverlay() {
  const device = store.useStore((s) => s.device);
  if (device !== 'custom') return null;
  return <Ruler />;
}

function useFrameRect() {
  const [rect, setRect] = useState(null);
  const width = store.useStore((s) => s.canvasWidth);
  useLayoutEffect(() => {
    let raf = 0;
    const measure = () => {
      const f = document.querySelector('.bk-canvas-wrap iframe');
      const wrap = document.querySelector('.bk-canvas-wrap');
      if (!f || !wrap) return;
      const a = f.getBoundingClientRect();
      const b = wrap.getBoundingClientRect();
      setRect({ left: a.left - b.left, right: a.right - b.left, width: a.width, wrap: b.width, scale: a.width / (f.offsetWidth || a.width) });
    };
    measure();
    raf = requestAnimationFrame(measure);
    window.addEventListener('resize', measure);
    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', measure);
    };
  }, [width]);
  return rect;
}

function Ruler() {
  const width = store.useStore((s) => s.canvasWidth) || 1280;
  const track = useRef(null);
  const [drag, setDrag] = useState(null); // { from: 'track'|'left'|'right', x, w }
  const rect = useFrameRect();
  const editing = deviceFor(width);

  const pos = (w) => ((w - MIN_W) / (MAX_W - MIN_W)) * 100;

  const fromPointer = (clientX, snap = true) => {
    const r = track.current.getBoundingClientRect();
    let w = MIN_W + ((clientX - r.left) / r.width) * (MAX_W - MIN_W);
    if (snap) {
      const px = (MAX_W - MIN_W) / r.width; // canvas px per screen px
      const near = SNAPS.find((s) => Math.abs(s.w - w) <= 6 * px);
      if (near) w = near.w;
    }
    return clampWidth(w);
  };

  useEffect(() => {
    if (!drag) return;
    const move = (e) => {
      if (drag.from === 'track') setWidth(fromPointer(e.clientX, !e.altKey));
      else {
        // Edge handles: the canvas is centred, so each side moves by half the change.
        const delta = ((e.clientX - drag.x) * (drag.from === 'right' ? 2 : -2)) / (rect ? rect.scale || 1 : 1);
        let w = clampWidth(drag.w + delta);
        if (!e.altKey) {
          const near = SNAPS.find((s) => Math.abs(s.w - w) <= 8);
          if (near) w = near.w;
        }
        setWidth(w);
      }
    };
    const up = () => setDrag(null);
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    return () => {
      window.removeEventListener('pointermove', move);
      window.removeEventListener('pointerup', up);
    };
  }, [drag]);

  const onKey = (e) => {
    const step = e.shiftKey ? 100 : 10;
    let w = null;
    if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') w = width - step;
    else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') w = width + step;
    else if (e.key === 'Home') w = MIN_W;
    else if (e.key === 'End') w = MAX_W;
    else if (e.key === 'PageUp') w = (SNAPS.slice().reverse().find((s) => s.w > width) || {}).w;
    else if (e.key === 'PageDown') w = (SNAPS.find((s) => s.w < width) || {}).w;
    if (w) {
      e.preventDefault();
      e.stopPropagation();
      store.setState({ device: 'custom', canvasWidth: clampWidth(w) });
    }
  };

  return (
    <>
      {drag && <div className="fixed inset-0 z-40" style={{ cursor: 'ew-resize' }} />}
      {rect &&
        ['left', 'right'].map((side) => (
          <div
            key={side}
            role="presentation"
            data-bk-sim-edge={side}
            onPointerDown={(e) => {
              e.preventDefault();
              setDrag({ from: side, x: e.clientX, w: width });
            }}
            className="group absolute top-0 bottom-0 z-30 flex w-3 items-center justify-center cursor-ew-resize"
            style={{ left: side === 'left' ? Math.max(0, rect.left - 12) : Math.min(rect.wrap - 12, rect.right) }}
          >
            <span className={cn('h-14 w-1 rounded-full bg-foreground/25 transition-colors group-hover:bg-brand', drag && drag.from === side && 'bg-brand')} />
          </div>
        ))}
      {drag && rect && (
        <div className="pointer-events-none absolute top-3 z-30 -translate-x-1/2 rounded-md bg-foreground px-2 py-1 font-mono text-xs text-background shadow-lg" style={{ left: (rect.left + rect.right) / 2 }}>
          {width}px · {LABEL[editing]}
        </div>
      )}
      <div className="absolute inset-x-0 bottom-3 z-30 flex justify-center px-4 pointer-events-none">
        <div className="pointer-events-auto w-full max-w-[760px] rounded-xl border border-border bg-background/95 p-2.5 shadow-lg backdrop-blur" data-bk-sim-panel>
          <div className="mb-1.5 flex items-center justify-between gap-3 px-0.5">
            <div className="flex items-center gap-2 text-xs">
              <span className="flex items-center gap-1 font-mono text-sm font-semibold tabular-nums">
                <input
                  aria-label="Canvas width"
                  className="w-14 rounded border border-transparent bg-transparent px-1 text-right outline-none hover:border-border focus:border-ring"
                  value={width}
                  onChange={(e) => {
                    const v = parseInt(e.target.value, 10);
                    if (!isNaN(v)) store.setState({ device: 'custom', canvasWidth: v });
                  }}
                  onBlur={() => store.setState({ canvasWidth: clampWidth(width) })}
                  onKeyDown={onKey}
                />
                <span className="text-muted-foreground">px</span>
              </span>
              <span className="h-4 w-px bg-border" />
              <span className="flex items-center gap-1.5 text-muted-foreground" data-bk-sim-editing={editing}>
                <Icon name={editing === 'desktop' ? 'monitor' : editing === 'tablet' ? 'tablet' : 'smartphone'} size={13} className="text-brand" />
                Editing <strong className="font-medium text-foreground">{LABEL[editing]}</strong> values
                <span className="font-mono text-[10px]">({RANGE[editing]})</span>
              </span>
            </div>
            <div className="flex items-center gap-1">
              {[360, 390, 768, 1024, 1440].map((w) => (
                <button key={w} type="button" onClick={() => store.setState({ device: 'custom', canvasWidth: w })} className={cn('rounded px-1.5 py-0.5 font-mono text-[10px] cursor-pointer', width === w ? 'bg-foreground text-background' : 'text-muted-foreground hover:bg-accent hover:text-foreground')}>
                  {w}
                </button>
              ))}
              <button type="button" title="Close custom width" onClick={() => store.setState({ device: editing })} className="ml-1 rounded p-0.5 text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer">
                <Icon name="x" size={14} />
              </button>
            </div>
          </div>
          <div
            ref={track}
            role="slider"
            tabIndex={0}
            aria-label="Canvas width"
            aria-valuemin={MIN_W}
            aria-valuemax={MAX_W}
            aria-valuenow={width}
            aria-valuetext={`${width} pixels, ${LABEL[editing]}`}
            data-bk-sim-track
            onKeyDown={onKey}
            onPointerDown={(e) => {
              e.preventDefault();
              track.current.focus();
              setWidth(fromPointer(e.clientX, !e.altKey));
              setDrag({ from: 'track' });
            }}
            className="relative h-9 select-none rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-ew-resize"
          >
            {/* Breakpoint bands. */}
            <div className="absolute inset-x-0 top-0 flex h-4 overflow-hidden rounded-md text-[9px] font-medium uppercase tracking-wide">
              <Band from={MIN_W} to={BREAKPOINTS.mobile + 1} pos={pos} active={editing === 'mobile'} label="Mobile" />
              <Band from={BREAKPOINTS.mobile + 1} to={BREAKPOINTS.tablet + 1} pos={pos} active={editing === 'tablet'} label="Tablet" />
              <Band from={BREAKPOINTS.tablet + 1} to={MAX_W} pos={pos} active={editing === 'desktop'} label="Desktop" />
            </div>
            {/* Ticks every 80px, labelled snap marks. */}
            <div className="absolute inset-x-0 top-4 h-5">
              {Array.from({ length: (MAX_W - MIN_W) / 80 + 1 }, (_, i) => MIN_W + i * 80).map((w) => (
                <span key={w} className="absolute top-0 h-1.5 w-px bg-border" style={{ left: `${pos(w)}%` }} />
              ))}
              {SNAPS.filter((s) => s.w > MIN_W && s.w < MAX_W).map((s) => (
                <span key={s.w} className="absolute top-0 flex -translate-x-1/2 flex-col items-center" style={{ left: `${pos(s.w)}%` }} title={s.note ? `${s.w}px — ${s.note}` : `${s.w}px`}>
                  <span className={cn('w-px', s.bp ? 'h-3 bg-brand/70' : 'h-2.5 bg-muted-foreground/50')} />
                  {LABELLED.includes(s.w) && <span className={cn('mt-px font-mono text-[8px] leading-none', s.bp ? 'text-brand' : 'text-muted-foreground')}>{s.label}</span>}
                </span>
              ))}
            </div>
            {/* Thumb. */}
            <span className="pointer-events-none absolute top-0 bottom-0 -translate-x-1/2" style={{ left: `${pos(width)}%` }}>
              <span className="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 rounded bg-foreground" />
              <span className="absolute -top-1 left-1/2 size-3 -translate-x-1/2 rotate-45 rounded-[2px] bg-foreground shadow" />
            </span>
          </div>
        </div>
      </div>
    </>
  );
}

function Band({ from, to, pos, active, label }) {
  return (
    <span className={cn('flex items-center justify-center border-r border-background/60 transition-colors', active ? 'bg-brand/15 text-brand' : 'bg-muted text-muted-foreground/70')} style={{ width: `${pos(to) - pos(from)}%` }}>
      {label}
    </span>
  );
}
