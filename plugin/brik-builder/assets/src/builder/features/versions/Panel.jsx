// Versions left panel: workflow card, scheduled actions and the version timeline.
import { useState, useEffect, config } from '../../wp.js';
import { useStore, openModal } from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Button, IconButton, Empty, Popover, Input } from '../../ui.jsx';
import * as M from './model.js';

const STEPS = [
  ['draft', 'Draft'],
  ['preview', 'Preview'],
  ['staging', 'Staging'],
  ['published', 'Published'],
];

export function VersionsPanel() {
  const ver = useStore((s) => s.ver) || {};
  useEffect(() => {
    M.refresh();
  }, []);
  const st = ver.state;
  const groups = ver.groups || [];
  const heads = M.heads(groups);
  const previewing = ver.previewing && ver.previewing.version.id;

  return (
    <div className="flex min-h-full flex-col">
      {st && <Workflow st={st} ver={ver} />}
      {st && st.schedules && st.schedules.length > 0 && <Schedules items={st.schedules} />}

      <div className="flex items-center justify-between px-4 pt-4 pb-1">
        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">History</h3>
        <IconButton icon="refresh-cw" label="Refresh" size="icon-sm" onClick={() => M.refresh()} />
      </div>

      {ver.loading && (
        <div className="space-y-3 p-4">
          {[0, 1, 2, 3].map((i) => (
            <div key={i} className="flex gap-3">
              <div className="size-7 animate-pulse rounded-full bg-muted" />
              <div className="flex-1 space-y-2">
                <div className="h-3 w-1/3 animate-pulse rounded bg-muted" />
                <div className="h-3 w-4/5 animate-pulse rounded bg-muted" />
              </div>
            </div>
          ))}
        </div>
      )}
      {ver.error && <p className="px-4 py-2 text-sm text-destructive">{ver.error}</p>}
      {!ver.loading && !groups.length && !ver.error && (
        <div className="p-4">
          <Empty icon="rotate-ccw-clock" title="No versions yet">
            Every save is recorded here, so you can preview, compare and restore earlier states.
          </Empty>
        </div>
      )}

      <div className="flex-1 pb-3">
        {groups.map((g) => (
          <section key={g.day}>
            <div className="sticky top-0 z-[1] border-b border-border/60 bg-background/95 px-4 py-1.5 text-[11px] font-medium text-muted-foreground backdrop-blur">{g.label}</div>
            <ol className="relative list-none px-2 py-1">
              {g.items.map((v) => (
                <VersionRow key={v.id} v={v} live={heads.live === v.id} staged={st && st.staging && heads.staging === v.id} active={previewing === v.id} />
              ))}
            </ol>
          </section>
        ))}
      </div>

      {st && <Footer st={st} />}
    </div>
  );
}

function Workflow({ st, ver }) {
  const idx = STEPS.findIndex(([k]) => k === st.stage);
  const counts = st.counts || {};
  const changes = (counts.added || 0) + (counts.removed || 0) + (counts.changed || 0);
  return (
    <div className="m-3 rounded-xl border border-border bg-card p-3 shadow-xs">
      <ol className="grid list-none grid-cols-4 gap-1 p-0" aria-label="Publishing status">
        {STEPS.map(([key, label], i) => (
          <li key={key} className="min-w-0" aria-current={i === idx ? 'step' : undefined}>
            <span className={cn('block h-1 rounded-full', i < idx ? 'bg-foreground/25' : i === idx ? stageBar(key) : 'bg-muted')} />
            <span className={cn('mt-1.5 block truncate text-[11px]', i === idx ? 'font-semibold text-foreground' : i < idx ? 'text-muted-foreground' : 'text-muted-foreground/60')}>{label}</span>
          </li>
        ))}
      </ol>

      <div className="mt-3 text-sm">
        {st.staging ? (
          <>
            <p className="font-medium">
              {st.pending ? `${changes || 'Some'} staged ${changes === 1 ? 'change' : 'changes'} not live yet` : 'Staging matches the live page'}
            </p>
            <p className="mt-0.5 text-xs text-muted-foreground">
              Saved {st.staged_ago} ago{st.staged_by ? ` by ${st.staged_by}` : ''}
              {ver.editingStaging ? ' · you are editing the staging copy' : ''}
            </p>
          </>
        ) : (
          <>
            <p className="font-medium">{st.stage === 'published' ? 'Live page is up to date' : 'Not published yet'}</p>
            <p className="mt-0.5 text-xs text-muted-foreground">Save to staging to review changes privately before they go live.</p>
          </>
        )}
      </div>

      <div className="mt-3 flex flex-wrap gap-1.5">
        {st.staging ? (
          <>
            <Button size="xs" variant="brand" icon="rocket" onClick={() => openModal('versions-deploy')}>
              Compare &amp; deploy
            </Button>
            <Button size="xs" variant="outline" icon="link" onClick={M.copyPreviewLink}>
              Copy link
            </Button>
            <IconButton icon="external-link" label="Open staging preview" size="icon-sm" onClick={() => window.open(st.preview_url, '_blank', 'noopener')} />
          </>
        ) : (
          <Button size="xs" variant="outline" icon="git-branch" disabled={ver.busy === 'staging'} onClick={M.saveStaging}>
            {ver.busy === 'staging' ? 'Saving…' : 'Save to staging'}
          </Button>
        )}
        {st.staging ? (
          <IconButton icon="calendar-clock" label="Schedule" size="icon-sm" onClick={() => openModal('versions-schedule')} />
        ) : (
          <Button size="xs" variant="ghost" icon="calendar-clock" onClick={() => openModal('versions-schedule')}>
            Schedule
          </Button>
        )}
      </div>
    </div>
  );
}

function stageBar(key) {
  return { draft: 'bg-zinc-400', preview: 'bg-sky-500', staging: 'bg-amber-500', published: 'bg-emerald-500' }[key];
}

export function stageCls(key) {
  return {
    draft: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    preview: 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
    staging: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    published: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    scheduled: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
  }[key];
}

function Schedules({ items }) {
  return (
    <div className="mx-3 mb-1 overflow-hidden rounded-xl border border-indigo-200 bg-indigo-50/60 dark:border-indigo-500/30 dark:bg-indigo-500/10">
      <div className="flex items-center gap-1.5 px-3 pt-2.5 pb-1 text-xs font-semibold text-indigo-700 dark:text-indigo-300">
        <Icon name="calendar-clock" size={13} />
        Scheduled
      </div>
      <ul className="list-none divide-y divide-indigo-200/70 dark:divide-indigo-500/20">
        {items.map((s) => (
          <li key={s.id} className="flex items-center gap-2 px-3 py-2">
            <span className={cn('flex size-6 shrink-0 items-center justify-center rounded-md', s.action === 'deploy' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300')}>
              <Icon name={s.action === 'deploy' ? 'rocket' : 'rotate-ccw'} size={13} />
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-xs font-medium">{s.action === 'deploy' ? 'Deploy staging' : `Roll back to v${s.version_num}`}</p>
              <p className="truncate text-[11px] text-muted-foreground">
                {s.local} · in {s.in}
              </p>
            </div>
            <IconButton icon="x" label="Cancel" size="icon-sm" onClick={() => M.cancelSchedule(s.id)} />
          </li>
        ))}
      </ul>
    </div>
  );
}

function Avatar({ author }) {
  const [failed, setFailed] = useState(false);
  return (
    <span className="relative z-[1] flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted text-[10px] font-semibold text-muted-foreground ring-2 ring-background" title={author.name}>
      {author.initials || '?'}
      {author.avatar && !failed && <img src={author.avatar} alt="" className="absolute inset-0 size-full object-cover" onError={() => setFailed(true)} />}
    </span>
  );
}

export function SourceBadge({ source }) {
  const s = M.SOURCES[source] || M.SOURCES.builder;
  return <span className={cn('rounded px-1.5 py-px text-[10px] font-medium', s.cls)}>{s.label}</span>;
}

function VersionRow({ v, live, staged, active }) {
  const named = !!v.name;
  return (
    <li
      className={cn(
        'group relative flex gap-2.5 rounded-lg px-2 py-2 transition-colors',
        active ? 'bg-amber-50 ring-1 ring-amber-300 dark:bg-amber-500/10 dark:ring-amber-500/40' : 'hover:bg-accent/60'
      )}
    >
      <span className="absolute top-0 bottom-0 left-[1.4rem] w-px bg-border group-first:top-4 group-last:bottom-auto group-last:h-4" aria-hidden="true" />
      <Avatar author={v.author} />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="font-mono text-[11px] font-semibold">v{v.number}</span>
          <span className="text-[11px] text-muted-foreground">{v.time}</span>
          <SourceBadge source={v.source} />
          {live && <span className="rounded-full bg-emerald-500/10 px-1.5 py-px text-[10px] font-medium text-emerald-700 dark:text-emerald-300">Live</span>}
          {staged && <span className="rounded-full bg-amber-500/15 px-1.5 py-px text-[10px] font-medium text-amber-800 dark:text-amber-300">Staged</span>}
          {(v.pinned || named) && <Icon name="pin" size={11} className="text-muted-foreground" />}
        </div>
        {named && <p className="mt-0.5 truncate text-sm font-medium">{v.name}</p>}
        <p className={cn('mt-0.5 line-clamp-2 text-xs leading-snug', named ? 'text-muted-foreground' : 'text-foreground/80')}>{v.summary}</p>
        <p className="mt-0.5 truncate text-[11px] text-muted-foreground">{v.author.name}</p>
      </div>
      <div className={cn('absolute top-1.5 right-1.5 flex items-center gap-0.5 rounded-md border border-border bg-background p-0.5 shadow-sm transition-opacity', active ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100')}>
        <IconButton icon="eye" label="Preview in canvas" size="icon-sm" onClick={() => (active ? M.exitPreview() : M.preview(v))} />
        <IconButton icon="git-compare" label="Compare with current" size="icon-sm" onClick={() => openModal('versions-compare', { version: v })} />
        <IconButton icon="rotate-ccw" label="Restore this version" size="icon-sm" onClick={() => openModal('versions-restore', { version: v })} />
        <IconButton icon="pin" label="Name or pin" size="icon-sm" onClick={() => openModal('versions-name', { version: v })} />
      </div>
    </li>
  );
}

function Footer({ st }) {
  const [limit, setLimit] = useState(st.limit);
  return (
    <div className="sticky bottom-0 z-[2] flex items-center justify-between gap-2 border-t border-border bg-background px-4 py-2 text-[11px] text-muted-foreground">
      <span>Keeps the last {st.limit} versions plus named ones.</span>
      {config.canManage && (
        <Popover
          align="end"
          width={220}
          trigger={
            <button type="button" className="cursor-pointer font-medium text-foreground hover:underline">
              Change
            </button>
          }
        >
          {(close) => (
            <form
              className="space-y-2"
              onSubmit={(e) => {
                e.preventDefault();
                M.saveLimit(Number(limit) || 50).then(close);
              }}
            >
              <label className="text-xs font-medium">Versions to keep per page</label>
              <Input type="number" min="1" max="500" value={limit} onChange={(e) => setLimit(e.target.value)} />
              <Button size="xs" type="submit" className="w-full">
                Save
              </Button>
            </form>
          )}
        </Popover>
      )}
    </div>
  );
}
