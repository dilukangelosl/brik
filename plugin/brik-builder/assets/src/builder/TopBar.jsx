import { config } from './wp.js';
import { useStore, setState, undo, redo, openModal } from './store.js';
import { save } from './api.js';
import { Icon } from './icons.js';
import { cn, Button, IconButton, Popover } from './ui.jsx';

const DEVICES = [
  ['desktop', 'monitor', 'Desktop'],
  ['tablet', 'tablet', 'Tablet'],
  ['mobile', 'smartphone', 'Phone'],
];

export function TopBar() {
  const left = useStore((s) => s.left);
  const device = useStore((s) => s.device);
  const dirty = useStore((s) => s.dirty);
  const saving = useStore((s) => s.saving);
  const status = useStore((s) => s.status);
  const title = useStore((s) => s.title);
  const canUndo = useStore((s) => s.past.length > 0);
  const canRedo = useStore((s) => s.future.length > 0);
  const area = useStore((s) => s.area);
  const published = status === 'publish';

  const togglePanel = (p) => setState({ left: left === p ? null : p });

  return (
    <header className="flex h-12 shrink-0 items-center justify-between gap-2 border-b border-border bg-background px-2">
      <div className="flex min-w-0 items-center gap-1">
        <Popover
          width={220}
          trigger={
            <button type="button" className="flex h-8 items-center gap-2 rounded-md px-2 hover:bg-accent cursor-pointer" title="Menu">
              <span className="flex size-6 items-center justify-center rounded-md bg-foreground text-background">
                <Icon name="blocks" size={14} />
              </span>
              <Icon name="chevron-down" size={14} className="text-muted-foreground" />
            </button>
          }
        >
          {(close) => (
            <div className="-m-1.5 space-y-0.5 text-sm">
              <MenuLink href={config.exitUrl} icon="arrow-left" label="Exit to dashboard" />
              {config.viewUrl && <MenuLink href={config.viewUrl} icon="external-link" label={`View ${config.typeLabel.toLowerCase()}`} target="_blank" />}
              <MenuLink href={`${config.adminUrl}admin.php?page=brik-theme-builder`} icon="layout-panel-top" label="Theme builder" />
              <MenuLink href={`${config.adminUrl}admin.php?page=brik-library`} icon="library" label="Library" />
              <hr className="my-1 border-border" />
              <MenuButton icon="history" label="History" onClick={() => (close(), openModal('history'))} />
              <MenuButton icon="settings" label={area ? 'Template settings' : `${config.typeLabel} settings`} onClick={() => (close(), openModal('page'))} />
            </div>
          )}
        </Popover>
        <span className="mx-1 h-5 w-px bg-border" />
        <IconButton icon="plus" label="Add element (⌘⇧A)" active={left === 'modules'} onClick={() => togglePanel('modules')} />
        <IconButton icon="layers" label="Layers (⌘⇧L)" active={left === 'layers'} onClick={() => togglePanel('layers')} />
        <IconButton icon="library" label="Library & layouts" active={left === 'library'} onClick={() => togglePanel('library')} />
        <span className="mx-1 h-5 w-px bg-border" />
        <button type="button" className="min-w-0 truncate rounded px-1.5 py-1 text-sm font-medium hover:bg-accent cursor-pointer" onClick={() => openModal('page')} title="Settings">
          {title || 'Untitled'}
          {area && <span className="ml-2 rounded bg-muted px-1.5 py-0.5 text-[10px] uppercase text-muted-foreground">{area} template</span>}
        </button>
      </div>

      <div className="flex items-center gap-0.5 rounded-lg bg-muted p-0.5">
        {DEVICES.map(([d, icon, label]) => (
          <button
            key={d}
            type="button"
            title={`${label} view`}
            onClick={() => setState({ device: d })}
            className={cn('inline-flex h-7 w-9 items-center justify-center rounded-md cursor-pointer', device === d ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground')}
          >
            <Icon name={icon} size={15} />
          </button>
        ))}
      </div>

      <div className="flex items-center gap-1">
        <IconButton icon="undo-2" label="Undo (⌘Z)" disabled={!canUndo} onClick={undo} />
        <IconButton icon="redo-2" label="Redo (⌘⇧Z)" disabled={!canRedo} onClick={redo} />
        <IconButton icon="palette" label="Design system" onClick={() => openModal('design')} />
        <IconButton icon="settings" label="Settings" onClick={() => openModal('page')} />
        <span className="mx-1 h-5 w-px bg-border" />
        {!published && (
          <Button size="sm" variant="outline" disabled={saving || (!dirty && status === 'draft')} onClick={() => save('draft')}>
            {saving ? 'Saving…' : dirty ? 'Save draft' : 'Saved'}
          </Button>
        )}
        {published ? (
          <Button size="sm" variant="brand" disabled={saving || !dirty} onClick={() => save()}>
            <Icon name={dirty ? 'save' : 'check'} size={14} />
            {saving ? 'Saving…' : dirty ? 'Update' : 'Saved'}
          </Button>
        ) : (
          config.canPublish && (
            <Button size="sm" variant="brand" disabled={saving} onClick={() => save('publish')}>
              Publish
            </Button>
          )
        )}
      </div>
    </header>
  );
}

function MenuLink({ href, icon, label, target }) {
  return (
    <a href={href} target={target} rel={target ? 'noreferrer' : undefined} className="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent">
      <Icon name={icon} size={14} className="text-muted-foreground" />
      {label}
    </a>
  );
}

function MenuButton({ icon, label, onClick }) {
  return (
    <button type="button" onClick={onClick} className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left hover:bg-accent cursor-pointer">
      <Icon name={icon} size={14} className="text-muted-foreground" />
      {label}
    </button>
  );
}
