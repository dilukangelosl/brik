// Versions & staging dialogs.
import { useState, useEffect } from '../../wp.js';
import { useStore, closeModal, openModal, getState, toast } from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Dialog, Button, Input, Switch, Label, Select, Tabs } from '../../ui.jsx';
import * as M from './model.js';
import { SourceBadge } from './Panel.jsx';

const STATUS = {
  added: { sign: '+', label: 'Added', chip: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300', bar: 'bg-emerald-500' },
  removed: { sign: '−', label: 'Removed', chip: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300', bar: 'bg-rose-500' },
  changed: { sign: '~', label: 'Changed', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300', bar: 'bg-amber-500' },
  unchanged: { sign: '=', label: 'Unchanged', chip: 'bg-muted text-muted-foreground', bar: 'bg-border' },
};

function useCompare(a, b, deps = []) {
  const [data, setData] = useState(null);
  const [error, setError] = useState(null);
  const load = () => {
    setError(null);
    return M.compare(a, b)
      .then(setData)
      .catch((e) => setError(e.message || 'Could not compare'));
  };
  useEffect(() => {
    load();
  }, deps);
  return [data, error, load];
}

function Counts({ counts }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      {['added', 'changed', 'removed', 'unchanged'].map((k) => (
        <span key={k} className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium', STATUS[k].chip, !counts[k] && 'opacity-50')}>
          <span className="font-mono">{STATUS[k].sign}</span>
          {counts[k]} {STATUS[k].label.toLowerCase()}
        </span>
      ))}
    </div>
  );
}

function Skeleton() {
  return (
    <div className="space-y-2">
      {[0, 1, 2].map((i) => (
        <div key={i} className="h-16 animate-pulse rounded-lg bg-muted" />
      ))}
    </div>
  );
}

/**
 * Two-column section diff. "before" is the a side, "after" the b side; the left column shows
 * `left` (a or b) so callers can put CURRENT on the left.
 */
function SectionDiff({ data, leftLabel, rightLabel, leftSide = 'b', action }) {
  const [hide, setHide] = useState(false);
  const rows = data.sections.filter((r) => !hide || r.status !== 'unchanged');
  const has = (r, side) => (side === 'a' ? r.status !== 'added' : r.status !== 'removed');
  const rightSide = leftSide === 'a' ? 'b' : 'a';
  const nameOn = (r, side) => (side === 'a' && r.name_a ? r.name_a : r.name);
  return (
    <div>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <Counts counts={data.counts} />
        <label className="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
          <Switch checked={hide} onChange={setHide} label="Hide unchanged" />
          Hide unchanged
        </label>
      </div>
      {(data.page_changed || data.reordered) && (
        <div className="mb-3 flex flex-wrap gap-2 text-xs">
          {data.page_changed && <span className="rounded-md border border-border px-2 py-1">Page settings differ</span>}
          {data.reordered && <span className="rounded-md border border-border px-2 py-1">Section order differs</span>}
        </div>
      )}
      <div className="overflow-hidden rounded-lg border border-border">
        <div className="grid grid-cols-[1fr_1fr] border-b border-border bg-muted/50 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
          <div className="px-3 py-2">{leftLabel}</div>
          <div className="border-l border-border px-3 py-2">{rightLabel}</div>
        </div>
        {rows.length === 0 && <p className="p-6 text-center text-sm text-muted-foreground">No differences.</p>}
        {rows.map((r) => {
          const st = STATUS[r.status];
          return (
            <div key={r.id} className="group relative grid grid-cols-[1fr_1fr] border-b border-border last:border-b-0">
              <span className={cn('absolute top-0 bottom-0 left-0 w-0.5', st.bar)} />
              {[leftSide, rightSide].map((side, i) => (
                <div key={side} className={cn('min-w-0 px-3 py-2.5', i === 1 && 'border-l border-border')}>
                  {has(r, side) ? (
                    <div className="flex items-center gap-2">
                      <Icon name="rectangle-horizontal" size={14} className="shrink-0 text-muted-foreground" />
                      <span className={cn('truncate text-sm font-medium', r.status === 'unchanged' && 'text-muted-foreground')}>{nameOn(r, side)}</span>
                    </div>
                  ) : (
                    <div className="flex h-5 items-center rounded border border-dashed border-border px-2 text-[11px] text-muted-foreground">not present</div>
                  )}
                  {i === 0 && (
                    <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                      <span className={cn('rounded px-1.5 py-px text-[10px] font-semibold', st.chip)}>
                        <span className="font-mono">{st.sign}</span> {st.label}
                      </span>
                      {r.status === 'changed' &&
                        r.changes.slice(0, 4).map((c) => (
                          <span key={c} className="rounded bg-muted px-1.5 py-px text-[11px] text-foreground/80">
                            {c}
                          </span>
                        ))}
                      {r.status === 'changed' && r.changes.length > 4 && <span className="text-[11px] text-muted-foreground">+{r.changes.length - 4} more</span>}
                    </div>
                  )}
                  {i === 1 && action && r.status !== 'unchanged' && <div className="mt-1.5">{action(r)}</div>}
                </div>
              ))}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Compare a version with what the editor shows.
 * ---------------------------------------------------------------------- */

export function CompareModal({ version }) {
  const [data, error] = useCompare(version.id, 'current', [version.id]);
  const [busy, setBusy] = useState(null);
  const target = M.restoreTarget();
  const restore = async (sections) => {
    setBusy(sections ? sections[0] : 'all');
    try {
      await M.restore(version, sections);
      closeModal();
    } catch (e) {
      toast(e.message || 'Could not restore', 'error');
      setBusy(null);
    }
  };
  const actionLabel = (r) => (r.status === 'added' ? 'Remove this section' : r.status === 'removed' ? 'Bring back this section' : 'Restore only this section');
  return (
    <Dialog
      size="xl"
      title={`Compare with version ${version.number}`}
      description={
        <span className="inline-flex flex-wrap items-center gap-1.5">
          {version.name ? <strong className="font-medium text-foreground">{version.name}</strong> : null}
          <span>
            {version.when || version.day} · {version.time} · {version.author.name}
          </span>
          <SourceBadge source={version.source} />
        </span>
      }
      onClose={closeModal}
      footer={
        <>
          <span className="mr-auto self-center text-xs text-muted-foreground">Restores go to the {target === 'staging' ? 'staging copy' : 'live page'} and are recorded as a new version.</span>
          <Button size="sm" variant="ghost" onClick={() => (closeModal(), M.preview(version))} icon="eye">
            Preview
          </Button>
          <Button size="sm" variant="outline" onClick={closeModal}>
            Close
          </Button>
          <Button size="sm" variant="brand" icon="rotate-ccw" disabled={!!busy || !data || data.identical} onClick={() => restore(null)}>
            {busy === 'all' ? 'Restoring…' : `Restore all of v${version.number}`}
          </Button>
        </>
      }
    >
      {error && <p className="text-sm text-destructive">{error}</p>}
      {!data && !error && <Skeleton />}
      {data && (
        <SectionDiff
          data={data}
          leftSide="b"
          leftLabel={M.isDirty() ? 'Current (unsaved)' : 'Current'}
          rightLabel={`Version ${version.number}`}
          action={(r) => (
            <Button size="xs" variant="outline" icon="rotate-ccw" disabled={!!busy} onClick={() => restore([r.id])}>
              {busy === r.id ? 'Restoring…' : actionLabel(r)}
            </Button>
          )}
        />
      )}
    </Dialog>
  );
}

/* ------------------------------------------------------------------------
 * Staging ↔ production, with deploy.
 * ---------------------------------------------------------------------- */

export function DeployModal() {
  const st = useStore((s) => (s.ver || {}).state) || {};
  const busy = useStore((s) => (s.ver || {}).busy);
  const dirty = useStore((s) => s.dirty);
  // Unsaved edits are part of what gets deployed, so compare against the editor then.
  const editing = M.editingStaging() || dirty || !st.staging;
  const [data, error] = useCompare('live', editing ? 'current' : 'staging', []);
  const [confirmDiscard, setConfirmDiscard] = useState(false);
  return (
    <Dialog
      size="xl"
      title="Compare staging ↔ production"
      description="Review what changes when staging goes live. Visitors keep seeing production until you deploy."
      onClose={closeModal}
      footer={
        <>
          {st.staging &&
            (confirmDiscard ? (
              <span className="mr-auto flex items-center gap-2 text-xs">
                Discard all staged changes?
                <Button size="xs" variant="destructive" onClick={() => M.discardStaging().then(closeModal)}>
                  Discard
                </Button>
                <Button size="xs" variant="ghost" onClick={() => setConfirmDiscard(false)}>
                  Keep
                </Button>
              </span>
            ) : (
              <Button size="sm" variant="ghost" className="mr-auto text-destructive hover:text-destructive" icon="trash" onClick={() => setConfirmDiscard(true)}>
                Discard staging
              </Button>
            ))}
          <Button size="sm" variant="outline" icon="calendar-clock" onClick={() => openModal('versions-schedule', { action: 'deploy' })}>
            Schedule…
          </Button>
          <Button
            size="sm"
            variant="brand"
            icon="rocket"
            disabled={!!busy || !data || data.identical || st.can_deploy === false}
            title={st.can_deploy === false ? 'You need permission to publish to deploy' : undefined}
            onClick={() => M.deploy().then(closeModal, () => {})}
          >
            {busy === 'deploy' ? 'Deploying…' : 'Deploy changes'}
          </Button>
        </>
      }
    >
      <div className="mb-4 grid grid-cols-2 gap-3">
        <SideCard icon="globe" title="Production" tone="emerald" text={st.stage === 'preview' || st.stage === 'draft' ? 'Not published yet' : 'What visitors see now'} />
        <SideCard
          icon="git-branch"
          title={editing && dirty ? 'Staging (with unsaved edits)' : 'Staging'}
          tone="amber"
          text={st.staging ? `Saved ${st.staged_ago} ago${st.staged_by ? ` by ${st.staged_by}` : ''}` : 'Your current edits'}
          link={st.preview_url}
        />
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
      {!data && !error && <Skeleton />}
      {data && <SectionDiff data={data} leftSide="a" leftLabel="Production" rightLabel="Staging" />}
    </Dialog>
  );
}

function SideCard({ icon, title, text, tone, link }) {
  return (
    <div className="flex items-center gap-3 rounded-lg border border-border p-3">
      <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-md', tone === 'emerald' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300')}>
        <Icon name={icon} size={16} />
      </span>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium">{title}</p>
        <p className="truncate text-xs text-muted-foreground">{text}</p>
      </div>
      {link && (
        <Button size="xs" variant="ghost" icon="link" onClick={M.copyPreviewLink} title="Copy preview link">
          Link
        </Button>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Schedule a deploy or a rollback.
 * ---------------------------------------------------------------------- */

export function ScheduleModal({ action: initial = 'deploy', version = null }) {
  const st = useStore((s) => (s.ver || {}).state) || {};
  const groups = useStore((s) => (s.ver || {}).groups) || [];
  const [action, setAction] = useState(version ? 'rollback' : initial);
  const [when, setWhen] = useState(M.inAnHour(st.now_local));
  const [versionId, setVersionId] = useState(version ? String(version.id) : '');
  const [busy, setBusy] = useState(false);
  const versions = groups.flatMap((g) => g.items.map((v) => ({ value: String(v.id), label: `v${v.number}${v.name ? ` — ${v.name}` : ''} · ${g.label} ${v.time} · ${v.summary}`.slice(0, 90) })));
  const submit = async () => {
    setBusy(true);
    try {
      await M.schedule(action, when, action === 'rollback' ? Number(versionId) : 0);
      closeModal();
    } catch (e) {
      toast(e.message || 'Could not schedule', 'error');
      setBusy(false);
    }
  };
  return (
    <Dialog
      size="sm"
      title="Schedule"
      description={`Runs automatically at the chosen time (${st.timezone || 'site timezone'}).`}
      onClose={closeModal}
      footer={
        <>
          <Button size="sm" variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button size="sm" variant="brand" icon="calendar-clock" disabled={busy || !when || (action === 'rollback' && !versionId) || st.can_deploy === false} onClick={submit}>
            {busy ? 'Scheduling…' : action === 'deploy' ? 'Schedule deploy' : 'Schedule rollback'}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <Tabs
          className="w-full"
          value={action}
          onChange={setAction}
          tabs={[
            { value: 'deploy', label: 'Deploy staging', icon: 'rocket' },
            { value: 'rollback', label: 'Roll back', icon: 'rotate-ccw' },
          ]}
        />
        {action === 'deploy' ? (
          <p className="rounded-md bg-muted/60 p-3 text-xs leading-relaxed text-muted-foreground">
            Whatever is in staging at that moment goes live.{' '}
            {getState().dirty || !st.staging ? 'Your current edits will be saved to staging first.' : st.pending ? 'Staging currently has changes waiting.' : ''}
          </p>
        ) : (
          <div className="space-y-1.5">
            <Label>Version to restore</Label>
            <Select options={versions} value={versionId} onChange={setVersionId} placeholder="Choose a version…" />
            <p className="text-xs text-muted-foreground">Useful for ending a promotion: the live page returns to this version.</p>
          </div>
        )}
        <div className="space-y-1.5">
          <Label htmlFor="bk-ver-when">Date and time</Label>
          <Input id="bk-ver-when" type="datetime-local" value={when} min={st.now_local} onChange={(e) => setWhen(e.target.value)} />
        </div>
        {st.schedules && st.schedules.length > 0 && (
          <div className="space-y-1.5">
            <Label>Already scheduled</Label>
            <ul className="divide-y divide-border rounded-md border border-border">
              {st.schedules.map((s) => (
                <li key={s.id} className="flex items-center gap-2 px-2.5 py-1.5 text-xs">
                  <Icon name={s.action === 'deploy' ? 'rocket' : 'rotate-ccw'} size={13} className="text-muted-foreground" />
                  <span className="flex-1 truncate">
                    {s.action === 'deploy' ? 'Deploy' : `Roll back to v${s.version_num}`} · {s.local}
                  </span>
                  <button type="button" className="cursor-pointer text-muted-foreground hover:text-destructive" onClick={() => M.cancelSchedule(s.id)}>
                    Cancel
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </Dialog>
  );
}

/* ------------------------------------------------------------------------
 * Name / pin and restore confirmation.
 * ---------------------------------------------------------------------- */

export function NameModal({ version }) {
  const [name, setName] = useState(version.name || '');
  const [pinned, setPinned] = useState(!!version.pinned);
  const save = () => M.nameVersion(version.id, name.trim(), pinned).then(() => (closeModal(), toast(name.trim() ? 'Version named' : 'Saved', 'success')));
  return (
    <Dialog
      size="sm"
      title={`Version ${version.number}`}
      description={version.summary}
      onClose={closeModal}
      footer={
        <>
          <Button size="sm" variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button size="sm" onClick={save}>
            Save
          </Button>
        </>
      }
    >
      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          save();
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="bk-ver-name">Name</Label>
          <Input id="bk-ver-name" autoFocus placeholder="e.g. Approved by client" value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <label className="flex cursor-pointer items-start justify-between gap-4 rounded-md border border-border p-3">
          <span>
            <span className="block text-sm font-medium">Keep forever</span>
            <span className="block text-xs text-muted-foreground">Named and pinned versions are never pruned.</span>
          </span>
          <Switch checked={pinned || !!name.trim()} onChange={setPinned} label="Keep forever" />
        </label>
      </form>
    </Dialog>
  );
}

export function RestoreModal({ version }) {
  const [busy, setBusy] = useState(false);
  const target = M.restoreTarget();
  const dirty = M.isDirty();
  return (
    <Dialog
      size="sm"
      title={`Restore version ${version.number}?`}
      onClose={closeModal}
      footer={
        <>
          <Button size="sm" variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button
            size="sm"
            variant="brand"
            icon="rotate-ccw"
            disabled={busy}
            onClick={async () => {
              setBusy(true);
              try {
                await M.restore(version);
                closeModal();
              } catch (e) {
                toast(e.message || 'Could not restore', 'error');
                setBusy(false);
              }
            }}
          >
            {busy ? 'Restoring…' : 'Restore'}
          </Button>
        </>
      }
    >
      <div className="space-y-3 text-sm">
        <p className="rounded-md bg-muted/60 p-3 text-xs">
          <span className="font-medium text-foreground">{version.name || version.summary}</span>
          <br />
          <span className="text-muted-foreground">
            {version.when || version.day} · {version.time} · {version.author.name}
          </span>
        </p>
        <p className="text-muted-foreground">
          The {target === 'staging' ? <strong className="text-foreground">staging copy</strong> : <strong className="text-foreground">live page</strong>} is replaced with this version. The restore is recorded as a new version, so it can be undone.
          {dirty ? ' Unsaved edits stay in undo history.' : ''}
        </p>
        <button type="button" className="cursor-pointer text-xs font-medium text-foreground underline-offset-4 hover:underline" onClick={() => openModal('versions-compare', { version })}>
          Compare first, or restore only some sections →
        </button>
      </div>
    </Dialog>
  );
}
