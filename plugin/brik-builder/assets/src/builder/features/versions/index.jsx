// Builder feature: versions, staging and scheduled publishing.
import { config } from '../../wp.js';
import { useStore, openModal, subscribe, getState, setState } from '../../store.js';
import { addSlot, registerPanel, registerModal } from '../../registry.js';
import { Icon } from '../../icons.js';
import { cn, Button, Popover } from '../../ui.jsx';
import * as M from './model.js';
import { VersionsPanel, stageCls } from './Panel.jsx';
import { CompareModal, DeployModal, ScheduleModal, NameModal, RestoreModal } from './Modals.jsx';

/* ------------------------------------------------------------------------
 * Top bar: status pill and the staging/publish menu.
 * ---------------------------------------------------------------------- */

const PILL = {
  draft: ['Draft', 'Not published. Save to staging to share a private preview link.'],
  preview: ['Staging changes', 'Not published yet; staging is viewable through the preview link.'],
  staging: ['Staging changes', 'Staged changes are waiting. Visitors still see the live page.'],
  published: ['Published', 'The live page is up to date.'],
  scheduled: ['Scheduled', ''],
};

function StatusPill() {
  const st = useStore((s) => (s.ver || {}).state);
  if (!st) return null;
  const scheduled = st.schedules && st.schedules.length ? st.schedules[0] : null;
  const key = scheduled ? 'scheduled' : st.stage;
  const [label, hint] = PILL[key];
  const title = scheduled ? `${scheduled.action === 'deploy' ? 'Deploy' : 'Rollback'} scheduled for ${scheduled.local}` : hint;
  return (
    <button
      type="button"
      title={title}
      onClick={() => getState().left !== 'versions' && setLeft('versions')}
      className={cn('inline-flex h-6 cursor-pointer items-center gap-1.5 rounded-full px-2.5 text-xs font-medium whitespace-nowrap transition-opacity hover:opacity-80', stageCls(key))}
    >
      <span className={cn('size-1.5 rounded-full bg-current', key === 'staging' || key === 'preview' ? 'animate-pulse' : '')} />
      {label}
      {scheduled && <span className="font-normal opacity-80">· {scheduled.in}</span>}
    </button>
  );
}

function setLeft(id) {
  setState({ left: id });
}

function PublishMenu() {
  const st = useStore((s) => (s.ver || {}).state) || {};
  const busy = useStore((s) => (s.ver || {}).busy);
  const previewing = useStore((s) => !!(s.ver || {}).previewing);
  const dirty = useStore((s) => s.dirty);
  const editing = useStore((s) => !!(s.ver || {}).editingStaging);
  const stale = dirty || !st.staging;

  return (
    <div className="flex items-center gap-1.5">
      <StatusPill />
      <div className="inline-flex items-center rounded-md shadow-xs">
        <Button
          size="sm"
          variant="outline"
          icon="git-branch"
          className="rounded-r-none border-r-0 shadow-none"
          disabled={busy === 'staging' || previewing || (!stale && editing)}
          title="Save to staging: the live page stays unchanged"
          onClick={M.saveStaging}
        >
          {busy === 'staging' ? 'Saving…' : !stale && editing ? 'Staged' : 'Save to staging'}
        </Button>
        <Popover
          align="end"
          width={260}
          trigger={
            <button type="button" aria-label="Publishing options" className="inline-flex h-8 w-7 cursor-pointer items-center justify-center rounded-r-md border border-input bg-background hover:bg-accent">
              <Icon name="chevron-down" size={14} />
            </button>
          }
        >
          {(close) => (
            <div className="-m-1.5 text-sm">
              <MenuItem icon="git-branch" label="Save to staging" hint="Live page stays unchanged" disabled={previewing} onClick={() => (close(), M.saveStaging())} />
              <MenuItem icon="git-compare" label="Compare & deploy…" hint="Staging ↔ production" onClick={() => (close(), openModal('versions-deploy'))} />
              <MenuItem icon="calendar-clock" label="Schedule…" hint="Deploy or roll back later" onClick={() => (close(), openModal('versions-schedule'))} />
              <hr className="my-1 border-border" />
              <MenuItem icon="link" label="Copy preview link" hint={st.staging ? 'Anyone with the link can view staging' : 'Save to staging first'} onClick={() => (close(), M.copyPreviewLink())} />
              <MenuItem icon="external-link" label="Open staging preview" disabled={!st.staging} onClick={() => (close(), window.open(st.preview_url, '_blank', 'noopener'))} />
              <MenuItem icon="refresh-cw" label="Reset preview link" hint="Old links stop working" onClick={() => (close(), M.regenerateLink())} />
              <hr className="my-1 border-border" />
              <MenuItem icon="rotate-ccw-clock" label="Version history" onClick={() => (close(), setLeft('versions'))} />
              {st.staging && <MenuItem icon="trash" label="Discard staging" danger onClick={() => (close(), M.discardStaging())} />}
            </div>
          )}
        </Popover>
      </div>
      <span className="mx-0.5 h-5 w-px bg-border" />
    </div>
  );
}

function MenuItem({ icon, label, hint, onClick, disabled, danger }) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={cn('flex w-full cursor-pointer items-start gap-2.5 rounded-md px-2 py-1.5 text-left hover:bg-accent disabled:pointer-events-none disabled:opacity-50', danger && 'text-destructive')}
    >
      <Icon name={icon} size={15} className={cn('mt-0.5', danger ? '' : 'text-muted-foreground')} />
      <span className="min-w-0">
        <span className="block font-medium">{label}</span>
        {hint && <span className="block text-xs text-muted-foreground">{hint}</span>}
      </span>
    </button>
  );
}

/* ------------------------------------------------------------------------
 * Canvas overlays: version preview banner and the "editing staging" marker.
 * ---------------------------------------------------------------------- */

function CanvasOverlay() {
  const p = useStore((s) => (s.ver || {}).previewing);
  const editing = useStore((s) => !!(s.ver || {}).editingStaging);
  if (p) {
    const v = p.version;
    return (
      <>
        <div className="pointer-events-none absolute inset-0 z-[5] ring-2 ring-amber-400 ring-inset" />
        <div className="absolute top-3 left-1/2 z-[6] flex -translate-x-1/2 items-center gap-1 rounded-full border border-border bg-background/95 py-1 pr-1 pl-3 text-sm shadow-lg backdrop-blur bk-zoom">
          <Icon name="eye" size={15} className="text-amber-600" />
          <span className="ml-1 font-medium whitespace-nowrap">Previewing version {v.number}</span>
          <span className="mr-2 text-xs whitespace-nowrap text-muted-foreground">
            {v.name ? `${v.name} · ` : ''}
            {v.time}
          </span>
          <Button size="xs" variant="ghost" icon="git-compare" onClick={() => openModal('versions-compare', { version: v })}>
            Compare
          </Button>
          <Button size="xs" variant="outline" icon="rotate-ccw" onClick={() => openModal('versions-restore', { version: v })}>
            Restore
          </Button>
          <Button size="xs" variant="default" icon="arrow-left" className="rounded-full" onClick={M.exitPreview}>
            Back
          </Button>
        </div>
      </>
    );
  }
  if (editing) {
    return (
      <div className="pointer-events-none absolute bottom-3 left-3 z-[5] flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50/95 px-2.5 py-1 text-xs font-medium text-amber-900 shadow-sm dark:border-amber-500/40 dark:bg-amber-950/80 dark:text-amber-200">
        <Icon name="git-branch" size={13} />
        Editing staging · live page unchanged
      </div>
    );
  }
  return null;
}

/* ------------------------------------------------------------------------
 * Registration.
 * ---------------------------------------------------------------------- */

addSlot('topBarRight', PublishMenu, 5);
addSlot('canvasOverlay', CanvasOverlay);
registerPanel({ id: 'versions', label: 'Versions', icon: 'rotate-ccw-clock', component: VersionsPanel, order: 30, wide: true });
registerModal('versions-compare', CompareModal);
registerModal('versions-deploy', DeployModal);
registerModal('versions-schedule', ScheduleModal);
registerModal('versions-name', NameModal);
registerModal('versions-restore', RestoreModal);

// Start once the builder has loaded its schema (the app is mounted by then).
if (config && config.post) {
  const off = subscribe(() => {
    if (getState().schema) {
      off();
      M.start();
    }
  });
}
