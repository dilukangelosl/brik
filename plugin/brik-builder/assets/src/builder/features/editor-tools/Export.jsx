// "No lock-in": convert the page to core blocks or static HTML and switch Brik off for it.
import { useState, useEffect } from '../../wp.js';
import { api, config } from '../../wp.js';
import * as store from '../../store.js';
import { save } from '../../api.js';
import { Icon } from '../../icons.js';
import { cn, Button, Dialog, Popover, Tabs } from '../../ui.jsx';
import { HtmlCode } from './Code.jsx';

const MODES = [
  {
    id: 'blocks',
    icon: 'blocks',
    title: 'Gutenberg blocks',
    badge: 'Recommended',
    text: 'Sections, columns, headings, text, buttons, images, galleries, videos, accordions… become core blocks you can keep editing. Anything without a core equivalent becomes a Custom HTML block.',
  },
  {
    id: 'static',
    icon: 'file-code',
    title: 'Static HTML + CSS',
    text: 'One Custom HTML block with the exact rendered page and only the CSS it uses. Looks identical, no plugin needed, but edited as code.',
  },
];

export function MoreMenu() {
  return (
    <Popover
      align="end"
      width={240}
      trigger={<Button size="icon" variant="ghost" icon="ellipsis" title="More" aria-label="More" data-bk-more />}
    >
      {(close) => (
        <div className="-m-1.5 space-y-0.5 text-sm">
          <button
            type="button"
            data-bk-export-open
            onClick={() => {
              close();
              store.openModal('bk-export');
            }}
            className="flex w-full items-start gap-2 rounded px-2 py-1.5 text-left hover:bg-accent cursor-pointer"
          >
            <Icon name="unplug" size={14} className="mt-0.5 text-muted-foreground" />
            <span>
              <span className="block">Remove builder…</span>
              <span className="block text-xs text-muted-foreground">Convert to blocks or static HTML</span>
            </span>
          </button>
        </div>
      )}
    </Popover>
  );
}

export function ExportModal() {
  const dirty = store.useStore((s) => s.dirty);
  const post = store.useStore((s) => s.post);
  const [mode, setMode] = useState('blocks');
  const [view, setView] = useState('preview');
  const [data, setData] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(null);
  const [confirm, setConfirm] = useState(false);

  const load = async (m) => {
    if (data[m]) return;
    setLoading(true);
    setError(null);
    try {
      const res = await api({ path: `/brik/v1/editor/export/${post.id}?mode=${m}` });
      setData((d) => ({ ...d, [m]: res }));
    } catch (e) {
      setError(e.message || 'Preview failed');
    }
    setLoading(false);
  };

  useEffect(() => {
    if (!dirty) load(mode);
  }, [mode, dirty]);

  const convert = async () => {
    setBusy(true);
    setError(null);
    try {
      const res = await api({ path: `/brik/v1/editor/export/${post.id}`, method: 'POST', data: { mode } });
      setDone(res);
      store.setState({ dirty: false });
    } catch (e) {
      setError(e.message || 'Conversion failed');
    }
    setBusy(false);
  };

  const restore = async () => {
    setBusy(true);
    try {
      await api({ path: `/brik/v1/editor/restore/${post.id}`, method: 'POST' });
      store.toast('Brik restored', 'success');
      window.location.reload();
    } catch (e) {
      setError(e.message || 'Restore failed');
      setBusy(false);
    }
  };

  const current = data[mode];

  if (done) {
    return (
      <Dialog title="Page converted" onClose={store.closeModal} size="md">
        <div className="space-y-4" data-bk-export-done>
          <div className="flex items-start gap-3 rounded-lg border border-emerald-500/30 bg-emerald-500/5 p-3">
            <Icon name="circle-check" size={18} className="mt-0.5 text-emerald-600" />
            <div className="text-sm">
              <p className="font-medium">“{post.title}” no longer depends on Brik.</p>
              <p className="mt-0.5 text-muted-foreground">
                {done.mode === 'blocks' ? `${done.stats.mapped} elements became core blocks${done.stats.fallback ? `, ${done.stats.fallback} became Custom HTML` : ''}.` : 'The page is now one Custom HTML block.'} The Brik version is backed up.
              </p>
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            <Button size="sm" icon="pencil" onClick={() => (window.location.href = done.edit_url)}>
              Open in block editor
            </Button>
            <Button size="sm" variant="outline" icon="external-link" onClick={() => window.open(done.view_url, '_blank')}>
              View page
            </Button>
            <Button size="sm" variant="ghost" icon="undo-2" disabled={busy} onClick={restore}>
              Undo — restore Brik
            </Button>
          </div>
        </div>
      </Dialog>
    );
  }

  return (
    <Dialog
      title="Remove builder from this page"
      description="Brik never locks you in. Convert this page so it works without the plugin; the Brik version is kept as a backup you can restore."
      onClose={store.closeModal}
      size="xl"
      footer={
        <div className="flex w-full items-center justify-between gap-3">
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" checked={confirm} onChange={(e) => setConfirm(e.target.checked)} data-bk-export-confirm />
            Switch Brik off for this page (backup kept)
          </label>
          <span className="flex gap-2">
            <Button size="sm" variant="ghost" onClick={store.closeModal}>
              Cancel
            </Button>
            <Button size="sm" variant="destructive" icon="unplug" disabled={!confirm || busy || dirty || !current} onClick={convert} data-bk-export-convert>
              {busy ? 'Converting…' : mode === 'blocks' ? 'Convert to blocks' : 'Convert to static HTML'}
            </Button>
          </span>
        </div>
      }
    >
      <div className="grid gap-4 md:grid-cols-[260px_1fr]" data-bk-export>
        <div className="space-y-2">
          {MODES.map((m) => (
            <button key={m.id} type="button" data-mode={m.id} onClick={() => setMode(m.id)} className={cn('w-full rounded-lg border p-3 text-left transition-colors cursor-pointer', mode === m.id ? 'border-foreground ring-1 ring-foreground' : 'border-border hover:bg-accent/50')}>
              <span className="flex items-center gap-2 text-sm font-medium">
                <Icon name={m.icon} size={15} />
                {m.title}
                {m.badge && <span className="rounded bg-brand/10 px-1.5 py-px text-[10px] font-medium text-brand">{m.badge}</span>}
              </span>
              <span className="mt-1 block text-xs leading-relaxed text-muted-foreground">{m.text}</span>
            </button>
          ))}
          {current && (
            <div className="space-y-1.5 rounded-lg bg-muted/60 p-3 text-xs" data-bk-export-stats>
              {mode === 'blocks' ? (
                <>
                  <Stat label="Mapped to core blocks" value={current.stats.mapped} />
                  <Stat label="Kept as Custom HTML" value={current.stats.fallback} warn={current.stats.fallback > 0} />
                  {Object.keys(current.stats.types || {}).length > 0 && (
                    <div className="flex flex-wrap gap-1 pt-1">
                      {Object.entries(current.stats.types).map(([t, n]) => (
                        <span key={t} className="rounded border border-border bg-background px-1.5 py-px font-mono text-[10px]">
                          {t} ×{n}
                        </span>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <Stat label="Elements" value={current.stats.fallback} />
              )}
              <Stat label="CSS included" value={`${(current.css_bytes / 1024).toFixed(1)} KB`} />
              <p className="pt-1 text-[11px] leading-snug text-muted-foreground">Interactive extras (tabs, carousels, counters, forms) become static. Accordions keep working.</p>
            </div>
          )}
        </div>
        <div className="min-w-0">
          {dirty ? (
            <div className="flex h-[440px] flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-border text-center">
              <Icon name="save" size={22} className="text-muted-foreground" />
              <p className="text-sm font-medium">Save your changes first</p>
              <p className="max-w-xs text-xs text-muted-foreground">The conversion uses the saved page, so unsaved edits would be lost.</p>
              <Button size="sm" onClick={() => save()}>
                Save now
              </Button>
            </div>
          ) : (
            <>
              <div className="mb-2 flex items-center justify-between">
                <Tabs
                  value={view}
                  onChange={setView}
                  tabs={[
                    { value: 'preview', label: 'Preview', icon: 'eye' },
                    { value: 'markup', label: 'Block markup', icon: 'code-xml' },
                  ]}
                />
                {loading && <span className="bk-spinner" />}
              </div>
              {error && <p className="mb-2 rounded-md bg-destructive/10 px-3 py-2 text-xs text-destructive">{error}</p>}
              {current && current.warnings && current.warnings.map((w) => <p key={w} className="mb-2 rounded-md bg-amber-500/10 px-3 py-2 text-xs text-amber-700">{w}</p>)}
              {current && view === 'preview' && <iframe title="Converted page preview" srcDoc={current.preview} className="h-[440px] w-full rounded-lg border border-border bg-white" data-bk-export-preview />}
              {current && view === 'markup' && <HtmlCode code={current.content.length > 60000 ? `${current.content.slice(0, 60000)}\n…` : current.content} className="h-[440px] max-h-none" />}
              {!current && !error && <div className="h-[440px] animate-pulse rounded-lg bg-muted" />}
            </>
          )}
        </div>
      </div>
    </Dialog>
  );
}

function Stat({ label, value, warn }) {
  return (
    <div className="flex items-center justify-between">
      <span className="text-muted-foreground">{label}</span>
      <span className={cn('font-mono font-medium tabular-nums', warn && 'text-amber-600')}>{value}</span>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Banner when the page was converted and Brik switched off.
 * ---------------------------------------------------------------------- */

export function ConvertedBanner() {
  const [status, setStatus] = useState(null);
  const [hidden, setHidden] = useState(false);
  useEffect(() => {
    if (config.post && config.post.enabled === false && config.post.tree && config.post.tree.length) {
      api({ path: `/brik/v1/editor/status/${config.post.id}` })
        .then(setStatus)
        .catch(() => {});
    }
  }, []);
  if (hidden || !status || status.enabled || !status.backup) return null;
  const restore = async () => {
    await api({ path: `/brik/v1/editor/restore/${config.post.id}`, method: 'POST' });
    window.location.reload();
  };
  return (
    <div className="absolute inset-x-0 top-3 z-30 flex justify-center px-4" data-bk-converted>
      <div className="flex items-center gap-3 rounded-lg border border-amber-500/40 bg-background px-3 py-2 text-sm shadow-lg">
        <Icon name="unplug" size={16} className="text-amber-600" />
        <span>
          Converted to {status.backup.mode === 'blocks' ? 'blocks' : 'static HTML'} on {new Date(status.backup.time * 1000).toLocaleDateString()}. Brik is off for this page.
        </span>
        <Button size="xs" onClick={restore}>
          Restore Brik version
        </Button>
        <button type="button" onClick={() => setHidden(true)} className="text-muted-foreground hover:text-foreground cursor-pointer" aria-label="Dismiss">
          <Icon name="x" size={14} />
        </button>
      </div>
    </div>
  );
}
