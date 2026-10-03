// Shared frame for the editors: header, section nav, sticky save bar and unsaved-changes guard.
import { useState, useEffect, useRef, useCallback, __ } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button } from '../builder/ui.jsx';
import { confirmDialog, Kbd, modKey } from './primitives.jsx';
import { setState, setGuard, navigate, getState } from './lib.js';

/**
 * Draft state with dirty tracking against the last saved copy.
 * @returns {[Object, Function, boolean, Function]} draft, update(patch|fn), dirty, markSaved(next)
 */
export function useDraft(initial) {
  const [draft, setDraft] = useState(initial);
  const saved = useRef(JSON.stringify(initial));
  const dirty = JSON.stringify(draft) !== saved.current;

  useEffect(() => {
    if (getState().dirty !== dirty) setState({ dirty });
  }, [dirty]);

  useEffect(() => {
    setGuard(() =>
      confirmDialog({
        title: __('Discard unsaved changes?', 'brik-builder'),
        message: __('You have changes that haven’t been saved yet.', 'brik-builder'),
        confirm: __('Discard changes', 'brik-builder'),
        cancel: __('Keep editing', 'brik-builder'),
        danger: true,
      })
    );
    return () => {
      setGuard(null);
      setState({ dirty: false });
    };
  }, []);

  const update = useCallback((patch) => setDraft((d) => ({ ...d, ...(typeof patch === 'function' ? patch(d) : patch) })), []);
  const markSaved = useCallback((next) => {
    saved.current = JSON.stringify(next);
    setDraft(next);
    setState({ dirty: false });
  }, []);
  return [draft, update, dirty, markSaved, setDraft];
}

export function useSaveShortcut(fn) {
  const ref = useRef(fn);
  ref.current = fn;
  useEffect(() => {
    const onKey = (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        ref.current();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);
}

export function EditorHeader({ back, backLabel, title, subtitle, icon, badges, actions }) {
  return (
    <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
      <div className="min-w-0">
        <button type="button" onClick={() => navigate(back)} className="mb-3 inline-flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground cursor-pointer">
          <Icon name="arrow-left" size={14} />
          {backLabel}
        </button>
        <div className="flex items-center gap-3">
          {icon && <span className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-border bg-card shadow-xs">{icon}</span>}
          <div className="min-w-0">
            <h1 className="flex flex-wrap items-center gap-2 text-xl font-semibold tracking-tight">
              <span className="truncate">{title}</span>
              {badges}
            </h1>
            {subtitle && <p className="text-sm text-muted-foreground">{subtitle}</p>}
          </div>
        </div>
      </div>
      {actions && <div className="flex items-center gap-2">{actions}</div>}
    </div>
  );
}

/** Left "on this page" nav that highlights the section in view. */
export function SectionNav({ sections }) {
  const [active, setActive] = useState(sections[0] && sections[0].id);
  useEffect(() => {
    const onScroll = () => {
      let cur = sections[0] && sections[0].id;
      for (const s of sections) {
        const el = document.getElementById(`sec-${s.id}`);
        if (el && el.getBoundingClientRect().top < 170) cur = s.id;
      }
      setActive(cur);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    return () => window.removeEventListener('scroll', onScroll);
  }, [sections.map((s) => s.id).join()]);
  return (
    <nav className="sticky top-[104px] hidden w-44 shrink-0 self-start pt-1 lg:block" aria-label={__('Sections', 'brik-builder')}>
      <ul className="space-y-0.5">
        {sections.map((s) => (
          <li key={s.id}>
            <button
              type="button"
              onClick={() => {
                const el = document.getElementById(`sec-${s.id}`);
                if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 110, behavior: 'smooth' });
              }}
              className={cn('flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-left text-[13px] transition-colors cursor-pointer', active === s.id ? 'bg-card font-medium text-foreground shadow-xs ring-1 ring-border' : 'text-muted-foreground hover:text-foreground')}
            >
              <Icon name={s.icon} size={15} />
              {s.label}
              {s.error && <span className="ml-auto size-1.5 rounded-full bg-destructive" />}
            </button>
          </li>
        ))}
      </ul>
    </nav>
  );
}

export function SaveBar({ dirty, saving, onSave, onCancel, isNew, error, extra }) {
  return (
    <div className="sticky bottom-0 z-20 -mx-6 mt-8 border-t border-border bg-background/90 px-6 py-3 backdrop-blur" data-savebar>
      <div className="mx-auto flex max-w-[1200px] items-center justify-between gap-3">
        <div className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
          {error ? (
            <span className="flex items-center gap-1.5 text-destructive">
              <Icon name="circle-alert" size={14} />
              {error}
            </span>
          ) : dirty || isNew ? (
            <span className="flex items-center gap-1.5">
              <span className="size-1.5 rounded-full bg-amber-500" />
              {isNew ? __('Not saved yet', 'brik-builder') : __('Unsaved changes', 'brik-builder')}
            </span>
          ) : (
            <span className="flex items-center gap-1.5">
              <Icon name="circle-check" size={14} className="text-emerald-600" />
              {__('All changes saved', 'brik-builder')}
            </span>
          )}
          {extra}
        </div>
        <div className="flex items-center gap-2">
          <Button variant="ghost" size="sm" onClick={onCancel}>
            {dirty ? __('Cancel', 'brik-builder') : __('Back', 'brik-builder')}
          </Button>
          <Button size="sm" onClick={onSave} disabled={saving || (!dirty && !isNew)} data-save>
            {saving ? <span className="bk-spinner size-3.5 border-primary-foreground/30 border-t-primary-foreground" /> : <Icon name="save" size={14} />}
            {isNew ? __('Create', 'brik-builder') : __('Save changes', 'brik-builder')}
            <span className="ml-1 hidden opacity-60 sm:inline">{modKey}S</span>
          </Button>
        </div>
      </div>
    </div>
  );
}

export { Kbd };
