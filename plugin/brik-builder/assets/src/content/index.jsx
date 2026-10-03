// Content admin app (Brik → Content): post types, taxonomies and field groups.
// Author: Diluk Angelo
import { createRoot, useState, useEffect, __ } from './wp.js';
import { Icon, setIcons } from '../builder/icons.js';
import { cn, Button } from '../builder/ui.jsx';
import { Toasts, Spinner } from './primitives.jsx';
import { useStore, getState, setState, navigate, loadContent, loadWp, loadCounts, loadIconSet, errorMessage, cfg } from './lib.js';
import { Overview } from './Overview.jsx';
import { PostTypeEditor } from './PostTypeEditor.jsx';
import { TaxonomyEditor } from './TaxonomyEditor.jsx';
import { FieldGroupEditor } from './FieldGroupEditor.jsx';
import { ImportDialog, ExportDialog } from './ImportExport.jsx';

const NAV = [
  { section: 'post-types', label: __('Post types', 'brik-builder'), icon: 'file-stack', kind: 'post_types' },
  { section: 'taxonomies', label: __('Taxonomies', 'brik-builder'), icon: 'tags', kind: 'taxonomies' },
  { section: 'field-groups', label: __('Field groups', 'brik-builder'), icon: 'text-cursor-input', kind: 'groups' },
];

function Header({ onImport, onExport }) {
  const route = useStore((s) => s.route);
  const s = useStore();
  return (
    <header className="sticky top-8 z-30 border-b border-border bg-background/95 backdrop-blur max-[782px]:top-0">
      <div className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-x-6 gap-y-2 px-6 py-2.5">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground shadow-xs">
            <Icon name="database" size={16} />
          </span>
          <div className="leading-tight">
            <h1 className="text-[15px] font-semibold tracking-tight">{__('Content', 'brik-builder')}</h1>
            <p className="text-[11px] text-muted-foreground">{__('Brik content model', 'brik-builder')}</p>
          </div>
        </div>
        <nav className="flex items-center gap-1" aria-label={__('Content sections', 'brik-builder')}>
          {NAV.map((n) => {
            const active = route.section === n.section;
            return (
              <button
                key={n.section}
                type="button"
                onClick={() => navigate(`#/${n.section}`)}
                aria-current={active ? 'page' : undefined}
                className={cn('relative inline-flex h-8 items-center gap-2 rounded-md px-3 text-[13px] font-medium transition-colors cursor-pointer', active ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground')}
                data-nav={n.section}
              >
                <Icon name={n.icon} size={15} />
                <span className="max-[900px]:hidden">{n.label}</span>
                <span className={cn('rounded-full px-1.5 text-[11px] tabular-nums', active ? 'bg-background text-foreground shadow-xs' : 'bg-muted text-muted-foreground')}>{s[n.kind].length}</span>
              </button>
            );
          })}
        </nav>
        <div className="ml-auto flex items-center gap-1.5">
          <Button variant="ghost" size="sm" icon="upload" onClick={onImport} data-import>
            <span className="max-[1000px]:hidden">{__('Import', 'brik-builder')}</span>
          </Button>
          <Button variant="ghost" size="sm" icon="download" onClick={onExport} data-export>
            <span className="max-[1000px]:hidden">{__('Export', 'brik-builder')}</span>
          </Button>
        </div>
      </div>
    </header>
  );
}

function Screen() {
  const route = useStore((s) => s.route);
  const key = `${route.section}/${route.id || ''}/${JSON.stringify(route.query)}`;
  if (!route.id) return <Overview key={key} section={route.section} />;
  if (route.section === 'post-types') return <PostTypeEditor key={key} id={route.id} query={route.query} />;
  if (route.section === 'taxonomies') return <TaxonomyEditor key={key} id={route.id} query={route.query} />;
  return <FieldGroupEditor key={key} id={route.id} query={route.query} />;
}

function App() {
  const ready = useStore((s) => s.ready);
  const error = useStore((s) => s.error);
  const [dialog, setDialog] = useState(null);

  if (error) {
    return (
      <div className="mx-auto flex max-w-md flex-col items-center gap-3 px-6 py-24 text-center">
        <span className="flex size-11 items-center justify-center rounded-full bg-destructive/10 text-destructive">
          <Icon name="circle-alert" size={20} />
        </span>
        <p className="text-sm font-medium">{__('The content model could not be loaded.', 'brik-builder')}</p>
        <p className="text-xs text-muted-foreground">{error}</p>
        <Button size="sm" variant="outline" icon="refresh-cw" onClick={() => boot()}>
          {__('Try again', 'brik-builder')}
        </Button>
      </div>
    );
  }

  return (
    <>
      <Header onImport={() => setDialog('import')} onExport={() => setDialog('export')} />
      <main className="mx-auto max-w-[1200px] px-6 pt-6 pb-10">
        {ready ? (
          <Screen />
        ) : (
          <div className="flex justify-center py-24">
            <Spinner />
          </div>
        )}
      </main>
      {dialog === 'import' && <ImportDialog onClose={() => setDialog(null)} />}
      {dialog === 'export' && <ExportDialog onClose={() => setDialog(null)} />}
      <Toasts />
    </>
  );
}

let root = null;

async function boot() {
  const el = document.getElementById('brik-content-app');
  if (!el) return;
  if (!root) {
    root = createRoot(el);
    root.render(<App />);
  }
  setState({ error: null });
  try {
    const [icons] = await Promise.all([loadIconSet(), loadWp()]);
    setIcons(icons.icons, {}, icons.aliases);
    await loadContent();
    loadCounts();
  } catch (e) {
    setState({ error: errorMessage(e, __('Unknown error', 'brik-builder')) });
  }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
else boot();
