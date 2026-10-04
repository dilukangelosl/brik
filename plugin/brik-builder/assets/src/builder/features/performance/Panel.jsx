import { useState, useEffect } from '../../wp.js';
import * as store from '../../store.js';
import { scrollTo } from '../../canvas.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { cn, Button, IconButton, Dialog, Empty } from '../../ui.jsx';
import { usePerf, analyze, clean } from './state.js';

export const AREAS = [
  { key: 'js', label: 'JavaScript', icon: 'file-code' },
  { key: 'css', label: 'CSS', icon: 'paintbrush' },
  { key: 'dom', label: 'DOM size', icon: 'network' },
  { key: 'images', label: 'Images', icon: 'image' },
  { key: 'fonts', label: 'Fonts', icon: 'type' },
  { key: 'third_party', label: 'Third parties', icon: 'globe' },
];

const FIX_LABELS = {
  wrapper: 'Redundant wrappers',
  empty_structure: 'Empty sections and rows',
  empty_module: 'Empty headings and text',
  duplicate: 'Duplicate elements',
  defaults: 'Settings equal to defaults',
  hidden: 'Hidden on every device',
  custom_css: 'Empty custom CSS',
  image_size: 'Oversized images',
};

export function kb(bytes) {
  if (!bytes) return '0 KB';
  if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
  const k = bytes / 1024;
  return `${k < 10 ? k.toFixed(1) : Math.round(k)} KB`;
}

export function tone(score) {
  if (score === null || score === undefined) return 'muted';
  return score >= 90 ? 'good' : score >= 50 ? 'ok' : 'bad';
}

const TONES = {
  good: { stroke: 'stroke-emerald-500', text: 'text-emerald-600', bar: 'bg-emerald-500' },
  ok: { stroke: 'stroke-amber-500', text: 'text-amber-600', bar: 'bg-amber-500' },
  bad: { stroke: 'stroke-red-500', text: 'text-red-600', bar: 'bg-red-500' },
  muted: { stroke: 'stroke-muted-foreground/40', text: 'text-muted-foreground', bar: 'bg-muted-foreground/40' },
};

export function ScoreRing({ score, size = 96, stroke = 8, children }) {
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const t = TONES[tone(score)];
  const pct = score === null || score === undefined ? 0 : score / 100;
  return (
    <span className="relative inline-flex shrink-0 items-center justify-center" style={{ width: size, height: size }}>
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} className="-rotate-90" aria-hidden="true">
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} className="stroke-muted" />
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" strokeWidth={stroke} strokeLinecap="round" className={cn('transition-[stroke-dashoffset] duration-700', t.stroke)} strokeDasharray={c} strokeDashoffset={c * (1 - pct)} />
      </svg>
      {children}
    </span>
  );
}

function detail(key, r) {
  switch (key) {
    case 'js': {
      const n = r.js.scripts.length;
      return `${kb(r.js.total)} · ${n} script${n === 1 ? '' : 's'}`;
    }
    case 'css':
      return r.optimized ? `${kb(r.css.optimized)} of ${kb(r.css.full)}` : `${kb(r.css.full)}, not optimized`;
    case 'dom':
      return `${r.dom.elements.toLocaleString()} elements`;
    case 'images': {
      const flagged = r.images.heavy + r.images.oversized;
      return `${r.images.count} image${r.images.count === 1 ? '' : 's'}${r.images.bytes ? ` · ${kb(r.images.bytes)}` : ''}${flagged ? ` · ${flagged} flagged` : ''}`;
    }
    case 'fonts': {
      const n = r.fonts.families.length;
      const mode = { local: 'self-hosted', google: 'Google Fonts', system: 'system fonts', off: 'no web fonts' }[r.fonts.mode] || r.fonts.mode;
      return `${n} famil${n === 1 ? 'y' : 'ies'} · ${mode}`;
    }
    case 'third_party': {
      const n = r.third_party.hosts.length;
      return n ? `${n} host${n === 1 ? '' : 's'}` : 'none';
    }
  }
  return '';
}

const CATEGORY_ICONS = { js: 'file-code', css: 'paintbrush', dom: 'network', images: 'image', fonts: 'type', third_party: 'globe' };
const SEVERITY = {
  high: 'text-red-600',
  medium: 'text-amber-600',
  low: 'text-muted-foreground',
};

function savingText(s) {
  if (!s) return '';
  const total = (s.js || 0) + (s.css || 0) + (s.images || 0);
  return total ? `−${kb(total)}` : '';
}

function goTo(id) {
  if (!id || !T.find(store.getState().tree, id)) {
    store.toast('That element is not on the page any more');
    return;
  }
  store.select(id);
  scrollTo(id);
}

export function PerformancePanel() {
  const { report, loading, error, at, stale } = usePerf();

  useEffect(() => {
    if (!report && !loading) analyze(store.getState().dirty);
  }, []);

  if (!report) {
    return (
      <div className="p-4">
        {error ? (
          <Empty icon="triangle-alert" title="Could not analyze this page">
            <p>{error}</p>
            <Button size="sm" variant="outline" className="mt-3" icon="refresh-cw" onClick={() => analyze(true)}>
              Try again
            </Button>
          </Empty>
        ) : (
          <div className="flex items-center gap-2 py-8 text-sm text-muted-foreground" role="status">
            <Icon name="loader-circle" className="animate-spin" /> Analyzing the page…
          </div>
        )}
      </div>
    );
  }

  const t = TONES[tone(report.score)];
  return (
    <div className="flex flex-col" data-perf-panel>
      <div className="flex items-center gap-4 border-b border-border p-4">
        <ScoreRing score={report.score}>
          <span className="absolute flex flex-col items-center leading-none">
            <span className={cn('text-2xl font-semibold tabular-nums', t.text)} data-perf-score>
              {report.score}
            </span>
            <span className="mt-1 text-[10px] text-muted-foreground">/100</span>
          </span>
        </ScoreRing>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-medium">Performance</p>
          <p className="mt-1 text-xs text-muted-foreground">
            {report.optimized ? 'Optimized assets on' : 'Optimized assets off'}
            {at ? ` · ${new Date(at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''}
          </p>
          {stale && <p className="mt-1 text-xs text-amber-600">Page changed since this report.</p>}
          <Button size="xs" variant="outline" className="mt-2" icon={loading ? 'loader-circle' : 'refresh-cw'} disabled={loading} onClick={() => analyze(true)}>
            {loading ? 'Analyzing…' : 'Re-analyze'}
          </Button>
        </div>
      </div>

      <div className="space-y-3 border-b border-border p-4">
        {AREAS.map((a) => {
          const score = report.scores[a.key];
          const tt = TONES[tone(score)];
          return (
            <div key={a.key} data-perf-area={a.key}>
              <div className="flex items-center gap-2 text-xs">
                <Icon name={a.icon} size={14} className="text-muted-foreground" />
                <span className="font-medium">{a.label}</span>
                <span className="ml-auto truncate text-muted-foreground">{detail(a.key, report)}</span>
                <span className={cn('w-7 text-right font-semibold tabular-nums', tt.text)}>{score}</span>
              </div>
              <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted" role="meter" aria-label={`${a.label} score`} aria-valuemin={0} aria-valuemax={100} aria-valuenow={score}>
                <div className={cn('h-full rounded-full transition-[width] duration-700', tt.bar)} style={{ width: `${score}%` }} />
              </div>
            </div>
          );
        })}
      </div>

      <div className="border-b border-border p-4">
        <p className="text-xs leading-relaxed text-muted-foreground">{report.summary}</p>
      </div>

      <div className="p-4">
        <div className="mb-2 flex items-center justify-between">
          <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Findings</p>
          <span className="text-xs text-muted-foreground tabular-nums">{report.findings.length}</span>
        </div>
        {report.findings.length ? (
          <ul className="m-0 list-none space-y-1.5 p-0" data-perf-findings>
            {report.findings.map((f) => {
              const saving = savingText(f.savings);
              const clickable = !!f.node_id;
              return (
                <li key={f.id}>
                  <button
                    type="button"
                    disabled={!clickable}
                    onClick={() => goTo(f.node_id)}
                    title={clickable ? 'Select and show this element' : undefined}
                    className={cn('flex w-full items-start gap-2.5 rounded-md border border-border p-2.5 text-left transition-colors', clickable ? 'cursor-pointer hover:border-ring/60 hover:bg-accent/50' : 'cursor-default')}
                  >
                    <Icon name={CATEGORY_ICONS[f.category] || 'info'} size={15} className={cn('mt-0.5', SEVERITY[f.severity])} />
                    <span className="min-w-0 flex-1">
                      <span className="block text-xs font-medium leading-snug">{f.title}</span>
                      <span className="mt-0.5 block text-xs leading-snug text-muted-foreground">{f.detail}</span>
                    </span>
                    {saving && <span className="shrink-0 rounded bg-emerald-500/10 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 tabular-nums">{saving}</span>}
                  </button>
                </li>
              );
            })}
          </ul>
        ) : (
          <Empty icon="circle-check" title="Nothing to fix">
            This page loads only what it needs.
          </Empty>
        )}
      </div>

      <div className="mt-auto border-t border-border p-4">
        <p className="text-sm font-medium">Clean page</p>
        <p className="mt-1 text-xs text-muted-foreground">Remove empty elements, redundant wrappers and settings that repeat the defaults. The page looks the same; the change can be undone.</p>
        <Button size="sm" variant="outline" className="mt-3 w-full" icon="wand-sparkles" onClick={() => store.openModal('perf-clean')}>
          Clean page…
        </Button>
      </div>
    </div>
  );
}

export function CleanDialog() {
  const [result, setResult] = useState(null);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    clean(true)
      .then(setResult)
      .catch((e) => setError(e.message || 'Could not check the page'));
  }, []);

  const apply = async () => {
    setBusy(true);
    try {
      const done = await clean(false);
      const s = store.getState();
      const extra = { dirty: false, selected: T.find(done.tree, s.selected) ? s.selected : null };
      if (done.fixes.some((f) => f.type === 'page')) extra.page = { ...s.page, custom_css: '' };
      // Saved on the server; applied here as one history step so it can be undone.
      store.commit('Clean page', done.tree, { full: true }, extra);
      store.closeModal();
      store.toast(`Page cleaned: ${done.fixes.length} fix${done.fixes.length === 1 ? '' : 'es'} saved`, 'success');
      analyze(false);
    } catch (e) {
      setBusy(false);
      store.toast(e.message || 'Could not clean the page', 'error');
    }
  };

  const groups = {};
  (result ? result.fixes : []).forEach((f) => {
    (groups[f.kind] = groups[f.kind] || []).push(f);
  });
  const s = result ? result.savings : null;

  return (
    <Dialog
      title="Clean page"
      description="These fixes don't change how the page looks. Optimize saves the page; undo brings the previous version back into the editor."
      onClose={store.closeModal}
      size="md"
      footer={
        <>
          <Button variant="outline" onClick={store.closeModal}>
            Cancel
          </Button>
          <Button icon={busy ? 'loader-circle' : 'wand-sparkles'} disabled={!result || !result.changed || busy} onClick={apply} data-perf-optimize>
            {busy ? 'Optimizing…' : 'Optimize'}
          </Button>
        </>
      }
    >
      {error && <p className="text-sm text-red-600">{error}</p>}
      {!result && !error && (
        <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground" role="status">
          <Icon name="loader-circle" className="animate-spin" /> Checking the page…
        </div>
      )}
      {result && (
        <div className="space-y-5" data-perf-clean>
          <div>
            <p className="mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">Potential savings</p>
            <div className="grid grid-cols-4 gap-2">
              {[
                ['CSS', kb(s.css)],
                ['JavaScript', kb(s.js)],
                ['DOM nodes', String(s.dom)],
                ['Images', kb(s.images)],
              ].map(([label, value]) => (
                <div key={label} className="rounded-lg border border-border p-2.5">
                  <p className="text-[11px] text-muted-foreground">{label}</p>
                  <p className="mt-0.5 text-sm font-semibold tabular-nums">{value}</p>
                </div>
              ))}
            </div>
          </div>

          {result.changed ? (
            <div className="space-y-3">
              {Object.entries(groups).map(([kind, list]) => (
                <div key={kind}>
                  <p className="flex items-center gap-2 text-sm font-medium">
                    {FIX_LABELS[kind] || kind}
                    <span className="rounded bg-muted px-1.5 text-[11px] tabular-nums text-muted-foreground">{list.length}</span>
                  </p>
                  <ul className="mt-1 list-none space-y-0.5 p-0">
                    {list.slice(0, 8).map((f, i) => (
                      <li key={i}>
                        <button type="button" className="w-full truncate rounded px-1.5 py-0.5 text-left text-xs text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer" onClick={() => f.node_id && goTo(f.node_id)}>
                          {f.message}
                        </button>
                      </li>
                    ))}
                    {list.length > 8 && <li className="px-1.5 text-xs text-muted-foreground">…and {list.length - 8} more</li>}
                  </ul>
                </div>
              ))}
            </div>
          ) : (
            <Empty icon="circle-check" title="Already clean">
              Nothing to remove on this page.
            </Empty>
          )}

          {result.presets && result.presets.length > 0 && (
            <div className="rounded-lg border border-border p-3">
              <p className="text-sm font-medium">Unused style presets</p>
              <p className="mt-0.5 text-xs text-muted-foreground">Not used anywhere on the site. Delete them under Global settings → Presets if you don't need them.</p>
              <p className="mt-1.5 text-xs">{result.presets.map((p) => `${p.name} (${p.type})`).join(', ')}</p>
            </div>
          )}
        </div>
      )}
    </Dialog>
  );
}

export function PerfBadge() {
  const report = usePerf((s) => s.report);
  const loading = usePerf((s) => s.loading);
  const left = store.useStore((s) => s.left);
  const score = report ? report.score : null;
  const label = report ? `Performance score ${score} of 100` : 'Performance';
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      data-perf-badge
      onClick={() => store.setState({ left: left === 'performance' ? null : 'performance' })}
      className={cn('inline-flex h-8 items-center gap-1.5 rounded-md px-1.5 text-xs font-medium cursor-pointer outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50', left === 'performance' && 'bg-accent')}
    >
      <Icon name={loading && !report ? 'loader-circle' : 'gauge'} size={16} className={cn(loading && !report && 'animate-spin', TONES[tone(score)].text)} />
      <span className={cn('tabular-nums', TONES[tone(score)].text)}>{score ?? '–'}</span>
    </button>
  );
}
