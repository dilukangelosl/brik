// Responsive timeline and fluid values, shown in every field label.
import { useState } from '../../wp.js';
import * as store from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Button, Popover, Input } from '../../ui.jsx';
import { DEVICES, own, resolve, setDevice, shortValue, clamp, parseClamp, toPx, fmt, FLUID_MIN, FLUID_MAX } from './util.js';

const NOT_FLUID = ['line_height', 'z_index', 'opacity'];

// Typical viewport per device, used to show what a fluid value works out to.
const SAMPLE = { desktop: 1440, tablet: 820, mobile: 390 };

/* ------------------------------------------------------------------------
 * Timeline: D 48px · T 38px · M 30px
 * ---------------------------------------------------------------------- */

export function Timeline({ node, fkey, field }) {
  const device = store.useStore((s) => store.effectiveDevice(s));
  const mode = store.useStore((s) => s.mode);
  if (!field || !field.responsive || mode === 'hover') return null;
  const attrs = node.attrs || {};
  const any = DEVICES.some((d) => own(attrs, fkey, d.id) !== undefined);
  if (!any) return null;
  const fluid = parseClamp(own(attrs, fkey, 'desktop'));

  return (
    <span className="bk-timeline flex items-center rounded-md border border-border bg-background p-px" data-bk-timeline={fkey}>
      {DEVICES.map((d, i) => {
        const mine = own(attrs, fkey, d.id);
        const { value, from } = resolve(attrs, fkey, d.id);
        const inherited = mine === undefined;
        const active = device === d.id;
        const fromLabel = from ? DEVICES.find((x) => x.id === from).label : '';
        const title = inherited
          ? value === undefined
            ? `${d.label}: default`
            : `${d.label}: ${value} — inherited from ${fromLabel}`
          : `${d.label}: ${value}${d.id === 'desktop' ? '' : ' (override)'} — click to edit ${d.label.toLowerCase()} values`;
        return (
          <span key={d.id} className="group/chip relative flex items-center">
            {i > 0 && <span className="mx-px h-3 w-px bg-border" />}
            <button
              type="button"
              title={title}
              data-device={d.id}
              data-inherited={inherited ? '1' : '0'}
              onClick={() => setDevice(d.id)}
              className={cn(
                'flex h-[18px] max-w-[64px] items-center gap-0.5 rounded-[4px] px-1 font-mono text-[10px] leading-none cursor-pointer transition-colors',
                active ? 'bg-foreground text-background' : 'hover:bg-accent',
                !active && inherited && 'text-muted-foreground/60',
                !active && !inherited && 'text-foreground'
              )}
            >
              <span className={cn('font-sans text-[9px] font-semibold', active ? 'opacity-70' : 'opacity-50')}>{d.short}</span>
              <span className={cn('truncate', inherited && !active && 'italic')}>{fluid && (inherited || d.id === 'desktop') ? `~${fmt(Math.round(fluid.at(SAMPLE[d.id])))}` : chipValue(value)}</span>
            </button>
            {!inherited && d.id !== 'desktop' && (
              <button
                type="button"
                title={`Reset ${d.label.toLowerCase()} (inherit from ${d.id === 'mobile' && own(attrs, fkey, 'tablet') !== undefined ? 'Tablet' : 'Desktop'})`}
                onClick={(e) => {
                  e.stopPropagation();
                  store.setAttrs(node.id, { [`${fkey}@${d.id}`]: undefined }, `Reset ${d.label.toLowerCase()} ${fkey}`);
                }}
                className="absolute -right-1 -top-1.5 z-10 hidden size-3 items-center justify-center rounded-full bg-foreground text-background group-hover/chip:flex cursor-pointer"
              >
                <Icon name="x" size={8} />
              </button>
            )}
          </span>
        );
      })}
      <TimelineMenu node={node} fkey={fkey} attrs={attrs} field={field} />
    </span>
  );
}

/** Chips are tiny: px lengths drop their unit (the tooltip has the full value). */
function chipValue(v) {
  const s = shortValue(v);
  return /^-?[\d.]+px$/.test(s) ? s.slice(0, -2) : s;
}

function TimelineMenu({ node, fkey, attrs, field }) {
  const desktop = own(attrs, fkey, 'desktop');
  const hasOverrides = own(attrs, fkey, 'tablet') !== undefined || own(attrs, fkey, 'mobile') !== undefined;
  const run = (patch, label, close) => {
    store.setAttrs(node.id, patch, label);
    close();
  };
  return (
    <Popover
      align="end"
      width={248}
      trigger={
        <button type="button" title="Responsive values" className="ml-px flex h-[18px] w-4 items-center justify-center rounded-[4px] text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer">
          <Icon name="ellipsis" size={11} />
        </button>
      }
    >
      {(close) => (
        <div className="-m-1.5 text-xs">
          <p className="px-2 pt-1 pb-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{field.label} by device</p>
          <div className="space-y-1 px-2 pb-2">
            {DEVICES.map((d) => {
              const mine = own(attrs, fkey, d.id);
              const r = resolve(attrs, fkey, d.id);
              return (
                <div key={d.id} className="flex items-center gap-2">
                  <Icon name={d.icon} size={12} className="text-muted-foreground" />
                  <span className="w-12 text-muted-foreground">{d.label}</span>
                  <span className={cn('min-w-0 flex-1 truncate font-mono text-[11px]', mine === undefined && 'text-muted-foreground')}>{r.value === undefined ? 'default' : String(typeof r.value === 'object' ? shortValue(r.value) : r.value)}</span>
                  {mine === undefined && r.from && <span className="shrink-0 rounded bg-muted px-1 py-px text-[10px] text-muted-foreground">from {r.from}</span>}
                </div>
              );
            })}
          </div>
          <hr className="border-border" />
          <div className="space-y-0.5 p-1">
            <MenuItem icon="copy" disabled={desktop === undefined} onClick={() => run({ [`${fkey}@tablet`]: desktop, [`${fkey}@mobile`]: desktop }, 'Copy desktop to tablet & mobile', close)}>
              Copy desktop → tablet & mobile
            </MenuItem>
            <MenuItem icon="tablet" disabled={desktop === undefined} onClick={() => run({ [`${fkey}@tablet`]: desktop }, 'Copy desktop to tablet', close)}>
              Copy desktop → tablet
            </MenuItem>
            <MenuItem icon="smartphone" disabled={desktop === undefined} onClick={() => run({ [`${fkey}@mobile`]: desktop }, 'Copy desktop to mobile', close)}>
              Copy desktop → mobile
            </MenuItem>
            <MenuItem icon="eraser" disabled={!hasOverrides} onClick={() => run({ [`${fkey}@tablet`]: undefined, [`${fkey}@mobile`]: undefined }, 'Clear overrides', close)}>
              Clear tablet & mobile overrides
            </MenuItem>
          </div>
          <p className="border-t border-border px-2 pt-2 text-[11px] leading-snug text-muted-foreground">Tablet inherits Desktop, Mobile inherits Tablet. Faded values are inherited.</p>
        </div>
      )}
    </Popover>
  );
}

function MenuItem({ icon, children, onClick, disabled }) {
  return (
    <button type="button" disabled={disabled} onClick={onClick} className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-accent disabled:opacity-40 disabled:hover:bg-transparent cursor-pointer">
      <Icon name={icon} size={13} className="text-muted-foreground" />
      {children}
    </button>
  );
}

/* ------------------------------------------------------------------------
 * Fluid toggle.
 * ---------------------------------------------------------------------- */

export function canBeFluid(fkey, field) {
  return !!field && field.type === 'unit' && field.responsive && !NOT_FLUID.some((k) => fkey === k || fkey.endsWith(`_${k}`));
}

/** Starting values for the fluid editor: existing clamp, else mobile and desktop px. */
export function fluidSeed(node, fkey, field) {
  const attrs = node.attrs || {};
  const base = own(attrs, fkey, 'desktop');
  const parsed = parseClamp(base);
  if (parsed) return { min: parsed.min, max: parsed.max, fluid: true };
  const max = toPx(base !== undefined ? base : field.default);
  const small = resolve(attrs, fkey, 'mobile').value;
  let min = toPx(small);
  if (max !== null && (min === null || min === max)) min = suggestMin(max);
  return { min, max, fluid: false };
}

/** A sensible phone size when there's no mobile value: large type shrinks more than small. */
export function suggestMin(max) {
  if (max <= 16) return max;
  const ratio = max >= 48 ? 0.62 : max >= 32 ? 0.72 : 0.85;
  return Math.max(14, Math.round(max * ratio));
}

export function Fluid({ node, fkey, field }) {
  const mode = store.useStore((s) => s.mode);
  if (!canBeFluid(fkey, field) || mode === 'hover') return null;
  const attrs = node.attrs || {};
  const active = !!parseClamp(own(attrs, fkey, 'desktop'));
  return (
    <Popover
      align="end"
      width={300}
      trigger={
        <button
          type="button"
          data-bk-fluid={fkey}
          title={active ? 'Fluid value — scales smoothly between 390px and 1440px' : 'Make fluid: scale smoothly with the screen width'}
          data-active={active ? '1' : '0'}
          className={cn('flex size-[18px] items-center justify-center rounded-[4px] cursor-pointer', active ? 'bg-brand text-white' : 'text-muted-foreground hover:bg-accent hover:text-foreground')}
        >
          <Icon name="spline" size={11} />
        </button>
      }
    >
      {(close) => <FluidEditor node={node} fkey={fkey} field={field} close={close} />}
    </Popover>
  );
}

function FluidEditor({ node, fkey, field, close }) {
  const seed = fluidSeed(node, fkey, field);
  const [min, setMin] = useState(seed.min ?? '');
  const [max, setMax] = useState(seed.max ?? '');
  const valid = min !== '' && max !== '' && !isNaN(+min) && !isNaN(+max);
  const value = valid ? clamp(+min, +max) : '';

  const apply = () => {
    store.setAttrs(node.id, { [fkey]: value, [`${fkey}@tablet`]: undefined, [`${fkey}@mobile`]: undefined }, `Make ${field.label.toLowerCase()} fluid`);
    close();
  };
  const unfluid = () => {
    store.setAttrs(node.id, { [fkey]: `${fmt(+max)}px`, [`${fkey}@tablet`]: undefined, [`${fkey}@mobile`]: `${fmt(+min)}px` }, `Make ${field.label.toLowerCase()} fixed`);
    close();
  };

  return (
    <div className="space-y-3" data-bk-fluid-editor={fkey}>
      <div>
        <p className="text-sm font-medium">Fluid {field.label.toLowerCase()}</p>
        <p className="mt-0.5 text-xs text-muted-foreground">Scales smoothly with the screen instead of jumping at breakpoints.</p>
      </div>
      <div className="grid grid-cols-2 gap-2">
        <label className="space-y-1">
          <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Icon name="smartphone" size={11} /> At {FLUID_MIN}px
          </span>
          <span className="relative block">
            <Input type="number" value={min} onChange={(e) => setMin(e.target.value)} className="h-7 pr-7 font-mono text-xs" data-bk-fluid-min />
            <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">px</span>
          </span>
        </label>
        <label className="space-y-1">
          <span className="flex items-center gap-1 text-[11px] text-muted-foreground">
            <Icon name="monitor" size={11} /> At {FLUID_MAX}px
          </span>
          <span className="relative block">
            <Input type="number" value={max} onChange={(e) => setMax(e.target.value)} className="h-7 pr-7 font-mono text-xs" data-bk-fluid-max />
            <span className="absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">px</span>
          </span>
        </label>
      </div>
      {valid && <FluidChart min={+min} max={+max} />}
      {valid && <code className="block break-words rounded-md bg-muted px-2 py-1.5 font-mono text-[10px] leading-relaxed text-muted-foreground">{value}</code>}
      <div className="flex items-center justify-between gap-2">
        {seed.fluid ? (
          <Button size="xs" variant="ghost" onClick={unfluid} disabled={!valid}>
            Use fixed sizes
          </Button>
        ) : (
          <span className="text-[11px] text-muted-foreground">Removes tablet/mobile overrides.</span>
        )}
        <Button size="xs" onClick={apply} disabled={!valid} data-bk-fluid-apply>
          {seed.fluid ? 'Update' : 'Make fluid'}
        </Button>
      </div>
    </div>
  );
}

/** Value against viewport width, 320–1920px, with the breakpoints marked. */
export function FluidChart({ min, max, height = 72 }) {
  const W = 268;
  const H = height;
  const x0 = 320;
  const x1 = 1920;
  const lo = Math.min(min, max);
  const hi = Math.max(min, max);
  const pad = Math.max(2, (hi - lo) * 0.25);
  const y = (v) => H - 14 - ((v - (lo - pad)) / (hi + pad - (lo - pad))) * (H - 22);
  const x = (w) => ((w - x0) / (x1 - x0)) * W;
  const at = (w) => (w <= FLUID_MIN ? min : w >= FLUID_MAX ? max : min + ((max - min) * (w - FLUID_MIN)) / (FLUID_MAX - FLUID_MIN));
  const pts = [x0, FLUID_MIN, FLUID_MAX, x1].map((w) => `${x(w).toFixed(1)},${y(at(w)).toFixed(1)}`).join(' ');
  return (
    <svg width={W} height={H} className="block overflow-visible rounded-md border border-border bg-muted/40" aria-hidden="true">
      <rect x={x(768)} y="0" width={x(981) - x(768)} height={H - 12} className="fill-foreground/[0.04]" />
      {[767, 980].map((b) => (
        <line key={b} x1={x(b)} x2={x(b)} y1="0" y2={H - 12} className="stroke-border" strokeDasharray="2 2" />
      ))}
      <polyline points={pts} fill="none" className="stroke-brand" strokeWidth="2" strokeLinejoin="round" />
      {[FLUID_MIN, FLUID_MAX].map((w) => (
        <g key={w}>
          <circle cx={x(w)} cy={y(at(w))} r="3" className="fill-background stroke-brand" strokeWidth="1.5" />
          <text x={x(w)} y={y(at(w)) - 6} textAnchor="middle" className="fill-foreground font-mono text-[9px]">
            {fmt(at(w))}
          </text>
        </g>
      ))}
      {[
        [390, '390'],
        [768, '768'],
        [981, '980'],
        [1440, '1440'],
      ].map(([w, t]) => (
        <text key={w} x={x(w)} y={H - 2} textAnchor="middle" className="fill-muted-foreground text-[8px]">
          {t}
        </text>
      ))}
    </svg>
  );
}

/* ------------------------------------------------------------------------
 * Make all typography fluid.
 * ---------------------------------------------------------------------- */

export function typographyPlan(node, def) {
  const out = [];
  for (const [key, field] of Object.entries(def.fields || {})) {
    if (!key.endsWith('font_size') || !canBeFluid(key, field)) continue;
    const seed = fluidSeed(node, key, field);
    if (seed.fluid || seed.max === null || seed.min === null) continue;
    if (own(node.attrs || {}, key, 'desktop') === undefined && field.default === undefined) continue;
    out.push({ key, label: field.group_label ? `${field.group_label} · ${field.label}` : field.label, min: seed.min, max: seed.max });
  }
  return out;
}

export function FluidTypography({ node, def }) {
  const plan = typographyPlan(node, def);
  if (!plan.length) return null;
  const apply = (close) => {
    const patch = {};
    for (const p of plan) {
      patch[p.key] = clamp(p.min, p.max);
      patch[`${p.key}@tablet`] = undefined;
      patch[`${p.key}@mobile`] = undefined;
    }
    store.setAttrs(node.id, patch, 'Make typography fluid');
    store.toast(`${plan.length} font size${plan.length > 1 ? 's' : ''} made fluid`, 'success');
    close();
  };
  return (
    <Popover
      align="end"
      width={280}
      trigger={
        <Button size="xs" variant="outline" icon="spline" title="Make all typography fluid" data-bk-fluid-all>
          Fluid type
        </Button>
      }
    >
      {(close) => (
        <div className="space-y-3">
          <div>
            <p className="text-sm font-medium">Make all typography fluid</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Font sizes scale smoothly from a 390px phone to a 1440px desktop.</p>
          </div>
          <div className="space-y-1">
            {plan.map((p) => (
              <div key={p.key} className="flex items-center justify-between gap-2 text-xs">
                <span className="truncate text-muted-foreground">{p.label}</span>
                <span className="shrink-0 font-mono text-[11px]">
                  {fmt(p.min)} → {fmt(p.max)}px
                </span>
              </div>
            ))}
          </div>
          <Button size="xs" className="w-full" onClick={() => apply(close)} data-bk-fluid-all-apply>
            Apply to {plan.length} size{plan.length > 1 ? 's' : ''}
          </Button>
        </div>
      )}
    </Popover>
  );
}
