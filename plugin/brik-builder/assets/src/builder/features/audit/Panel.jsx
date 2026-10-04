import { useState, useEffect, useMemo } from '../../wp.js';
import * as store from '../../store.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { cn, Button, IconButton, Input, Switch } from '../../ui.jsx';
import { useAudit, setAudit, getAudit, run, runResponsive, show, applyFix, applySafe, safeFixes } from './engine.js';
import { setWidth, WIDTHS } from './responsive.js';
import { title as ruleTitle, tone } from './rules.js';

const TONES = {
  good: { stroke: 'stroke-emerald-500', text: 'text-emerald-600' },
  ok: { stroke: 'stroke-amber-500', text: 'text-amber-600' },
  bad: { stroke: 'stroke-red-500', text: 'text-red-600' },
  muted: { stroke: 'stroke-muted-foreground/40', text: 'text-muted-foreground' },
};

export function Ring({ score, size = 40, stroke = 4, label = true, className }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const t = TONES[tone(score)];
  const pct = score === null || score === undefined ? 0 : score / 100;
  return (
    <span className={cn('relative inline-flex shrink-0 items-center justify-center', className)} style={{ width: size, height: size }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90" aria-hidden="true">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} className="stroke-muted" />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} strokeLinecap="round" className={cn('transition-[stroke-dashoffset] duration-500', t.stroke)} strokeDasharray={c} strokeDashoffset={c * (1 - pct)} />
      </svg>
      {label && <span className={cn('absolute font-semibold tabular-nums', size >= 40 ? 'text-xs' : 'text-[10px]', t.text)}>{score ?? '–'}</span>}
    </span>
  );
}

const TABS = [
  { id: 'a11y', label: 'Accessibility' },
  { id: 'seo', label: 'SEO' },
  { id: 'responsive', label: 'Responsive' },
];

const SEVERITY = {
  error: { icon: 'circle-x', cls: 'text-red-600', label: 'Errors' },
  warning: { icon: 'triangle-alert', cls: 'text-amber-600', label: 'Warnings' },
  info: { icon: 'info', cls: 'text-sky-600', label: 'Notices' },
};

export function AuditPanel() {
  const tab = useAudit((s) => s.tab);
  const scores = useAudit((s) => s.scores);
  const status = useAudit((s) => s.status);
  const ranAt = useAudit((s) => s.ranAt);
  const scope = useAudit((s) => s.scope);
  const serverError = useAudit((s) => s.serverError);
  const responsive = useAudit((s) => s.responsive);
  const tree = store.useStore((s) => s.tree);

  useEffect(() => {
    if (!getAudit().ranAt) run({ full: true, links: true });
  }, []);

  const scopeNode = scope ? T.find(tree, scope) : null;

  return (
    <div className="flex min-h-full flex-col">
      <div className="space-y-3 border-b border-border p-3">
        <div className="grid grid-cols-3 gap-1.5" role="tablist" aria-label="Audit">
          {TABS.map((t) => {
            const active = tab === t.id;
            const sc = scores[t.id];
            return (
              <button
                key={t.id}
                type="button"
                role="tab"
                aria-selected={active}
                onClick={() => setAudit({ tab: t.id })}
                className={cn(
                  'flex flex-col items-center gap-1.5 rounded-lg border px-1 pt-2.5 pb-2 text-xs font-medium transition-colors cursor-pointer outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50',
                  active ? 'border-border bg-background text-foreground shadow-sm' : 'border-transparent bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground'
                )}
              >
                {t.id === 'responsive' && responsive.status === 'running' ? (
                  <span className="flex size-10 items-center justify-center">
                    <span className="bk-spinner" />
                  </span>
                ) : (
                  <Ring score={sc} size={40} />
                )}
                {t.label}
              </button>
            );
          })}
        </div>
        <div className="flex items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground">
            {status === 'running' ? 'Checking…' : ranAt ? `Checked ${ago(ranAt)}` : 'Not checked yet'}
            {serverError && <span className="text-red-600"> · server checks unavailable</span>}
          </p>
          <Button size="xs" variant="outline" icon="refresh-cw" disabled={status === 'running'} onClick={() => run({ full: true, links: true })}>
            Re-run
          </Button>
        </div>
        {scopeNode && (
          <div className="flex items-center justify-between gap-2 rounded-md border border-dashed border-border bg-muted/40 px-2.5 py-1.5 text-xs">
            <span className="flex min-w-0 items-center gap-1.5">
              <Icon name="crosshair" size={13} className="text-muted-foreground" />
              <span className="truncate">
                Only <strong className="font-medium">{T.label(scopeNode, store.getState().schema)}</strong> and its content
              </span>
            </span>
            <button type="button" className="shrink-0 font-medium text-foreground underline-offset-2 hover:underline cursor-pointer" onClick={() => setAudit({ scope: null })}>
              Show all
            </button>
          </div>
        )}
      </div>
      <div className="flex-1">{tab === 'responsive' ? <ResponsiveTab /> : <IssuesTab cat={tab} />}</div>
    </div>
  );
}

function ago(t) {
  const s = Math.round((Date.now() - t) / 1000);
  return s < 10 ? 'just now' : s < 60 ? `${s}s ago` : `${Math.round(s / 60)} min ago`;
}

/** Findings limited to the scoped element and its descendants. */
function useScoped(list) {
  const scope = useAudit((s) => s.scope);
  const tree = store.useStore((s) => s.tree);
  return useMemo(() => {
    if (!scope) return list;
    const node = T.find(tree, scope);
    if (!node) return list;
    return list.filter((f) => f.node && (f.node === scope || T.find([node], f.node)));
  }, [list, scope, tree]);
}

function IssuesTab({ cat }) {
  const all = useAudit((s) => s.findings[cat]);
  const passes = useAudit((s) => s.passes[cat]);
  const status = useAudit((s) => s.status);
  const scope = useAudit((s) => s.scope);
  const findings = useScoped(all);
  const safe = useMemo(() => safeFixes(cat), [all]);

  if (!findings.length && status === 'running') {
    return (
      <div className="flex items-center justify-center p-10">
        <span className="bk-spinner" />
      </div>
    );
  }

  return (
    <div className="space-y-4 p-3">
      {cat === 'seo' && !scope && <SeoOverview />}
      {safe.length > 0 && !scope && (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/40 p-2.5">
          <p className="text-xs text-muted-foreground">
            <span className="font-medium text-foreground">{safe.length} issue{safe.length > 1 ? 's' : ''}</span> can be fixed in one click.
          </p>
          <Button size="xs" icon="wand-sparkles" onClick={() => applySafe(cat)}>
            Fix all
          </Button>
        </div>
      )}
      {!findings.length && (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-7 text-center">
          <span className="flex size-9 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
            <Icon name="check" size={18} />
          </span>
          <p className="text-sm font-medium">{scope ? 'No issues in this element' : 'No issues found'}</p>
          <p className="text-xs text-muted-foreground">{cat === 'a11y' ? 'Every automated check passed. Test with a keyboard and a screen reader too.' : 'The page structure looks good to search engines.'}</p>
        </div>
      )}
      {['error', 'warning', 'info'].map((sev) => (
        <SeverityGroup key={sev} severity={sev} findings={findings.filter((f) => f.severity === sev)} />
      ))}
      {!scope && passes.length > 0 && <Passed passes={passes} />}
    </div>
  );
}

function SeverityGroup({ severity, findings, responsive }) {
  const [open, setOpen] = useState(severity !== 'info' || findings.length <= 4);
  if (!findings.length) return null;
  const sev = SEVERITY[severity];
  const byRule = new Map();
  for (const f of findings) byRule.set(f.rule, [...(byRule.get(f.rule) || []), f]);
  return (
    <section>
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="mb-1.5 flex w-full items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase cursor-pointer hover:text-foreground">
        <Icon name={sev.icon} size={14} className={sev.cls} />
        {sev.label}
        <span className="rounded-full bg-muted px-1.5 py-px text-[10px] font-semibold tabular-nums text-foreground">{findings.length}</span>
        <Icon name="chevron-down" size={14} className={cn('ml-auto transition-transform', !open && '-rotate-90')} />
      </button>
      {open && (
        <div className="space-y-2">
          {[...byRule].map(([rule, list]) => (
            <div key={rule} className="overflow-hidden rounded-lg border border-border bg-background">
              <div className="flex items-center justify-between gap-2 border-b border-border bg-muted/40 px-2.5 py-1.5">
                <span className="text-xs font-medium">{ruleTitle(rule)}</span>
                {list.length > 1 && <span className="text-[11px] text-muted-foreground tabular-nums">{list.length}</span>}
              </div>
              <ul className="divide-y divide-border">
                {list.slice(0, 30).map((f, i) => (
                  <Issue key={`${f.node}${i}${f.message}`} f={f} severity={severity} responsive={responsive} />
                ))}
              </ul>
              {list.length > 30 && <p className="px-2.5 py-1.5 text-[11px] text-muted-foreground">and {list.length - 30} more…</p>}
            </div>
          ))}
        </div>
      )}
    </section>
  );
}

function Issue({ f, severity, responsive }) {
  const [prompt, setPrompt] = useState(null);
  const [value, setValue] = useState('');
  const sev = SEVERITY[severity];
  const selected = store.useStore((s) => s.selected === f.node && !!f.node);
  const fixes = f.fixes || [];

  const go = () => show(f, responsive ? f.breaksAt : null);

  return (
    <li className={cn('group px-2.5 py-2', selected && 'bg-accent/50')}>
      <div className="flex gap-2">
        <Icon name={sev.icon} size={14} className={cn('mt-0.5', sev.cls)} />
        <div className="min-w-0 flex-1 space-y-1.5">
          <button type="button" onClick={go} className="block w-full text-left text-[13px] leading-snug text-foreground cursor-pointer hover:underline decoration-muted-foreground/40 underline-offset-2">
            {f.message}
          </button>
          <div className="flex flex-wrap items-center gap-1.5">
            {(f.node || f.el) && (
              <button type="button" onClick={go} className="inline-flex max-w-[75%] items-center gap-1 rounded-md bg-muted px-1.5 py-0.5 text-[11px] text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer" title="Select and show on the canvas">
                <Icon name="crosshair" size={11} />
                <span className="truncate">{f.label || 'Element'}</span>
              </button>
            )}
            {f.data && f.data.fg && f.data.bg && (
              <span className="inline-flex items-center gap-1 rounded-md border border-border px-1.5 py-0.5 text-[11px] tabular-nums" title={`${f.data.sample ? f.data.sample + ": " : ""}${f.data.fg} on ${f.data.bg}`}>
                <span className="flex size-3.5 items-center justify-center rounded-sm text-[9px] font-bold" style={{ background: f.data.bg, color: f.data.fg, boxShadow: 'inset 0 0 0 1px rgb(0 0 0 / .1)' }}>
                  A
                </span>
                {f.data.ratio}:1
              </span>
            )}
            {responsive && f.widths && <WidthChips widths={f.widths} />}
          </div>
          {(fixes.length > 0 || responsive) && (
            <div className="flex flex-wrap gap-1.5 pt-0.5">
              {responsive && (
                <Button size="xs" variant="outline" icon="eye" onClick={go}>
                  Show me at {f.breaksAt}px
                </Button>
              )}
              {fixes.map((fix) => (
                <Button
                  key={fix.id}
                  size="xs"
                  variant={fix.safe ? 'default' : 'outline'}
                  icon={fix.prompt ? 'pencil' : 'wand-sparkles'}
                  onClick={() => {
                    if (fix.prompt) {
                      setPrompt(prompt === fix ? null : fix);
                      setValue(fix.prompt.value || '');
                    } else applyFix(f, fix);
                  }}
                >
                  {fix.label}
                </Button>
              ))}
            </div>
          )}
          {prompt && (
            <form
              className="flex gap-1.5 pt-0.5"
              onSubmit={(e) => {
                e.preventDefault();
                if (value.trim()) applyFix(f, prompt, value);
              }}
            >
              <Input autoFocus value={value} placeholder={prompt.prompt.label} aria-label={prompt.prompt.label} onChange={(e) => setValue(e.target.value)} className="h-7 text-xs" />
              <Button size="xs" type="submit" disabled={!value.trim()}>
                Apply
              </Button>
            </form>
          )}
        </div>
      </div>
    </li>
  );
}

function WidthChips({ widths }) {
  const sorted = [...widths].sort((a, b) => b - a);
  const contiguous = sorted.length > 2 && WIDTHS.slice(WIDTHS.indexOf(sorted[0])).every((w) => sorted.includes(w));
  const text = contiguous ? `≤ ${sorted[0]}px` : sorted.length > 3 ? `${sorted.length} widths` : sorted.map((w) => `${w}`).join(', ') + 'px';
  return <span className="inline-flex items-center gap-1 rounded-md border border-border px-1.5 py-0.5 text-[11px] text-muted-foreground tabular-nums" title={sorted.map((w) => `${w}px`).join(', ')}>
    <Icon name="smartphone" size={11} />
    {text}
  </span>;
}

function Passed({ passes }) {
  const [open, setOpen] = useState(false);
  return (
    <section>
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="mb-1.5 flex w-full items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase cursor-pointer hover:text-foreground">
        <Icon name="circle-check" size={14} className="text-emerald-600" />
        Passed
        <span className="rounded-full bg-muted px-1.5 py-px text-[10px] font-semibold tabular-nums text-foreground">{passes.length}</span>
        <Icon name="chevron-down" size={14} className={cn('ml-auto transition-transform', !open && '-rotate-90')} />
      </button>
      {open && (
        <ul className="space-y-1 rounded-lg border border-border p-2">
          {passes.map((p) => (
            <li key={p.rule} className="flex items-start gap-2 text-xs">
              <Icon name="check" size={13} className="mt-px text-emerald-600" />
              <span>
                {ruleTitle(p.rule)}
                {p.message && <span className="block text-muted-foreground">{p.message}</span>}
              </span>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}

/* ------------------------------------------------------------------------
 * SEO overview: search preview, heading outline, link checking.
 * ---------------------------------------------------------------------- */

function Meter({ value, min, max }) {
  const ok = value >= min && value <= max;
  const pct = Math.min(100, (value / (max * 1.25)) * 100);
  return (
    <span className="flex items-center gap-1.5 text-[11px] text-muted-foreground tabular-nums">
      <span className="relative h-1 w-14 overflow-hidden rounded-full bg-muted">
        <span className={cn('absolute inset-y-0 left-0 rounded-full', ok ? 'bg-emerald-500' : value ? 'bg-amber-500' : 'bg-red-500')} style={{ width: `${pct}%` }} />
      </span>
      {value}/{max}
    </span>
  );
}

function SeoOverview() {
  const meta = useAudit((s) => s.meta);
  const outline = useAudit((s) => s.outline);
  const external = useAudit((s) => s.checkExternal);
  const linksChecked = useAudit((s) => s.linksChecked);
  const status = useAudit((s) => s.status);
  const [open, setOpen] = useState(true);

  let prev = 1;
  const rows = outline.map((row) => {
    const skipped = row.level > prev + 1;
    prev = row.level;
    return { ...row, skipped };
  });

  return (
    <div className="space-y-3">
      {meta.title !== undefined && (
        <div className="rounded-lg border border-border p-3">
          <div className="mb-2 flex items-center justify-between">
            <span className="text-xs font-medium">Search preview</span>
            <span className="text-[10px] text-muted-foreground uppercase">{meta.seo_plugin === 'yoast' ? 'Yoast SEO' : meta.seo_plugin === 'rankmath' ? 'Rank Math' : 'WordPress'}</span>
          </div>
          <p className="truncate text-[11px] text-muted-foreground">{meta.url}</p>
          <p className="truncate text-[15px] leading-snug text-[#1a0dab] dark:text-sky-400">{meta.title}</p>
          <p className="line-clamp-2 text-xs text-muted-foreground">{meta.description || <em>No meta description — search engines will pick text from the page.</em>}</p>
          <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 border-t border-border pt-2">
            <span className="flex items-center gap-1.5 text-[11px]">
              Title <Meter value={meta.title_length || 0} min={30} max={60} />
            </span>
            <span className="flex items-center gap-1.5 text-[11px]">
              Description <Meter value={meta.description_length || 0} min={70} max={160} />
            </span>
          </div>
        </div>
      )}

      <div className="rounded-lg border border-border">
        <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full items-center justify-between px-3 py-2 text-xs font-medium cursor-pointer">
          <span className="flex items-center gap-1.5">
            <Icon name="list-tree" size={14} className="text-muted-foreground" />
            Heading outline
          </span>
          <span className="flex items-center gap-1 text-muted-foreground">
            {outline.length}
            <Icon name="chevron-down" size={14} className={cn('transition-transform', !open && '-rotate-90')} />
          </span>
        </button>
        {open && (
          <ol className="max-h-64 list-none space-y-px overflow-y-auto border-t border-border p-1.5">
            {!rows.length && <li className="px-1.5 py-1 text-xs text-muted-foreground">No headings on this page.</li>}
            {rows.map((row, i) => (
              <li key={i}>
                <button
                  type="button"
                  disabled={!row.node}
                  onClick={() => row.node && show({ node: row.node })}
                  className={cn('flex w-full items-center gap-1.5 rounded px-1.5 py-1 text-left text-xs hover:bg-accent disabled:cursor-default disabled:hover:bg-transparent cursor-pointer', row.theme && 'opacity-60')}
                  style={{ paddingLeft: 6 + (row.level - 1) * 12 }}
                >
                  <span className={cn('inline-flex h-4 min-w-6 items-center justify-center rounded px-1 font-mono text-[10px] font-semibold', row.level === 1 ? 'bg-foreground text-background' : row.skipped ? 'bg-amber-500/15 text-amber-700' : 'bg-muted text-muted-foreground')}>H{row.level}</span>
                  <span className={cn('truncate', !row.text && 'italic text-red-600')}>{row.text || 'Empty heading'}</span>
                  {row.theme && <span className="ml-auto text-[10px] text-muted-foreground">theme</span>}
                </button>
              </li>
            ))}
          </ol>
        )}
      </div>

      <div className="flex items-center justify-between gap-2 rounded-lg border border-border px-3 py-2">
        <span className="flex items-center gap-2 text-xs">
          <Switch checked={external} label="Also check external links" onChange={(v) => setAudit({ checkExternal: v, links: {} })} />
          Check external links too
        </span>
        <Button size="xs" variant="outline" icon="link" disabled={status === 'running'} onClick={() => run({ full: true, links: true })}>
          {linksChecked ? 'Re-check' : 'Check links'}
        </Button>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Responsive.
 * ---------------------------------------------------------------------- */

function ResponsiveTab() {
  const r = useAudit((s) => s.responsive);
  const all = useScoped(r.findings);
  const passes = useAudit((s) => s.passes.responsive);
  const device = store.useStore((s) => s.device);
  const width = store.useStore((s) => s.canvasWidth);

  if (r.status === 'running') {
    const p = r.progress || { index: 0, total: WIDTHS.length };
    return (
      <div className="space-y-3 p-4">
        <p className="text-sm font-medium">Checking {p.width ? `${p.width}px` : '…'}</p>
        <div className="h-1.5 overflow-hidden rounded-full bg-muted">
          <div className="h-full rounded-full bg-primary transition-all duration-300" style={{ width: `${((p.index + 1) / p.total) * 100}%` }} />
        </div>
        <p className="text-xs text-muted-foreground">Resizing the canvas through {WIDTHS.length} widths. Your view is restored afterwards.</p>
      </div>
    );
  }

  if (!r.ranAt) {
    return (
      <div className="p-3">
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed border-border px-5 py-8 text-center">
          <span className="flex size-10 items-center justify-center rounded-full bg-muted">
            <Icon name="monitor-smartphone" size={20} />
          </span>
          <div className="space-y-1">
            <p className="text-sm font-medium">Find responsive problems</p>
            <p className="text-xs text-muted-foreground">Checks the page at {WIDTHS.length} widths from 1440px to 360px for horizontal scrolling, cut-off text, oversized headings, small tap targets and overlapping controls.</p>
          </div>
          <Button size="sm" icon="scan-search" onClick={runResponsive}>
            Find responsive problems
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-4 p-3">
      <div className="rounded-lg border border-border p-2">
        <div className="mb-1.5 flex items-center justify-between px-0.5">
          <span className="text-xs font-medium">Issues per width</span>
          {device === 'custom' && (
            <button type="button" className="text-[11px] text-muted-foreground hover:text-foreground cursor-pointer" onClick={() => store.setState({ device: 'desktop' })}>
              Back to desktop
            </button>
          )}
        </div>
        <div className="grid grid-cols-4 gap-1">
          {WIDTHS.map((w) => {
            const n = r.perWidth[w] || 0;
            const active = device === 'custom' && width === w;
            return (
              <button
                key={w}
                type="button"
                onClick={() => setWidth(w)}
                title={`Preview at ${w}px`}
                className={cn('flex flex-col items-center rounded-md border py-1 text-[11px] tabular-nums cursor-pointer transition-colors', active ? 'border-primary bg-accent' : 'border-transparent bg-muted/50 hover:bg-muted')}
              >
                <span className="font-medium">{w}</span>
                <span className={cn('flex items-center gap-0.5', n ? 'text-amber-700' : 'text-emerald-600')}>{n ? `${n} issue${n > 1 ? 's' : ''}` : <Icon name="check" size={12} />}</span>
              </button>
            );
          })}
        </div>
      </div>
      <div className="flex items-center justify-between">
        <p className="text-xs text-muted-foreground">Checked {ago(r.ranAt)}</p>
        <Button size="xs" variant="outline" icon="refresh-cw" onClick={runResponsive}>
          Run again
        </Button>
      </div>
      {!all.length && (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-7 text-center">
          <span className="flex size-9 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600">
            <Icon name="check" size={18} />
          </span>
          <p className="text-sm font-medium">No responsive problems</p>
          <p className="text-xs text-muted-foreground">Nothing overflows or overlaps at any checked width.</p>
        </div>
      )}
      {['error', 'warning', 'info'].map((sev) => (
        <SeverityGroup key={sev} severity={sev} responsive findings={all.filter((f) => f.severity === sev)} />
      ))}
      {passes.length > 0 && <Passed passes={passes} />}
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Top bar badge.
 * ---------------------------------------------------------------------- */

export function AuditBadge() {
  const scores = useAudit((s) => s.scores);
  const counts = useAudit((s) => s.findings);
  const left = store.useStore((s) => s.left);
  const errors = [...counts.a11y, ...counts.seo, ...counts.responsive].filter((f) => f.severity === 'error').length;
  const label = scores.overall === null || scores.overall === undefined ? 'Quality audit' : `Quality score ${scores.overall} — accessibility ${scores.a11y}, SEO ${scores.seo}${scores.responsive !== null ? `, responsive ${scores.responsive}` : ''}${errors ? `, ${errors} error${errors > 1 ? 's' : ''}` : ''}`;
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      onClick={() => store.setState({ left: left === 'audit' ? null : 'audit' })}
      className={cn('inline-flex h-8 items-center gap-1.5 rounded-md px-1.5 text-xs font-medium cursor-pointer outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50', left === 'audit' && 'bg-accent')}
    >
      <Ring score={scores.overall ?? null} size={22} stroke={2.5} label={false} />
      <span className={cn('tabular-nums', TONES[tone(scores.overall ?? null)].text)}>{scores.overall ?? '–'}</span>
    </button>
  );
}
