// Compact inputs for the sentence-style rule editors.
import { useState } from '../../wp.js';
import { Icon } from '../../icons.js';
import { cn, inputClass, Popover, Input } from '../../ui.jsx';

// The shared input class stretches to the container; sentence rows size inputs themselves.
const fit = inputClass.replace('w-full ', '');

/** Text/number/date input that keeps its own width (w-* classes). */
export function Field({ className, ...props }) {
  return <input className={cn(fit, /(^|\s)w-/.test(className || '') ? '' : 'w-40', className)} {...props} />;
}

/** Native select with optional option groups: options [{ value, label, group? }]. */
export function GroupSelect({ value, options, onChange, placeholder, className, title }) {
  const groups = [];
  const index = {};
  for (const o of options) {
    const g = o.group || '';
    if (!(g in index)) {
      index[g] = groups.length;
      groups.push({ label: g, items: [] });
    }
    groups[index[g]].items.push(o);
  }
  const opt = (o) => (
    <option key={o.value} value={o.value}>
      {o.label}
    </option>
  );
  return (
    <select
      title={title}
      className={cn(fit, 'bk-select-chevron w-auto max-w-[220px] appearance-none pr-7 font-medium', className)}
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value)}
    >
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {groups.map((g) =>
        g.label ? (
          <optgroup key={g.label} label={g.label}>
            {g.items.map(opt)}
          </optgroup>
        ) : (
          g.items.map(opt)
        )
      )}
    </select>
  );
}

/** Pick several values from a list: shows chips, opens a searchable checklist. */
export function MultiPick({ value, options, onChange, placeholder = 'Choose…' }) {
  const [q, setQ] = useState('');
  const list = Array.isArray(value) ? value.map(String) : value ? String(value).split(',').map((v) => v.trim()) : [];
  const labelOf = (v) => {
    const o = options.find((x) => String(x.value) === v);
    return o ? o.label : v;
  };
  const toggle = (v) => onChange(list.includes(v) ? list.filter((x) => x !== v) : [...list, v]);
  const shown = options.filter((o) => !q || `${o.label} ${o.value}`.toLowerCase().includes(q.toLowerCase()));
  return (
    <Popover
      width={240}
      trigger={
        <button type="button" className={cn(fit, 'flex h-auto min-h-8 w-auto max-w-[280px] cursor-pointer flex-wrap items-center gap-1 py-1 text-left')}>
          {list.length ? (
            list.map((v) => (
              <span key={v} className="inline-flex items-center rounded bg-secondary px-1.5 py-0.5 text-xs font-medium text-secondary-foreground">
                {labelOf(v)}
              </span>
            ))
          ) : (
            <span className="text-muted-foreground">{placeholder}</span>
          )}
          <Icon name="chevron-down" size={12} className="ml-auto text-muted-foreground" />
        </button>
      }
    >
      <div className="space-y-2">
        {options.length > 8 && <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search…" className="h-7 text-xs" autoFocus />}
        <div className="max-h-56 space-y-0.5 overflow-y-auto">
          {shown.length === 0 && <p className="px-2 py-1.5 text-xs text-muted-foreground">No options</p>}
          {shown.map((o) => {
            const on = list.includes(String(o.value));
            return (
              <button
                key={o.value}
                type="button"
                className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
                onClick={() => toggle(String(o.value))}
              >
                <span className={cn('flex size-4 items-center justify-center rounded border', on ? 'border-primary bg-primary text-primary-foreground' : 'border-input')}>{on && <Icon name="check" size={11} />}</span>
                <span className="truncate">{o.label}</span>
              </button>
            );
          })}
        </div>
      </div>
    </Popover>
  );
}

/** The AND / OR word between rules; clicking flips the group's relation. */
export function Connector({ relation, onToggle }) {
  return (
    <button
      type="button"
      onClick={onToggle}
      title="Switch between AND / OR"
      className={cn(
        'my-1 ml-3 inline-flex h-5 items-center rounded-full px-2 text-[10px] font-semibold tracking-wider cursor-pointer',
        relation === 'OR' ? 'bg-amber-500/15 text-amber-700' : 'bg-brand/10 text-brand'
      )}
    >
      {relation}
    </button>
  );
}

/** Keyword at the start of a sentence row (SHOW, WHERE, ORDER BY…). */
export function Keyword({ children }) {
  return <span className="w-20 shrink-0 pt-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{children}</span>;
}

/** List value as an array (accepts comma separated text). */
export function asList(v) {
  if (Array.isArray(v)) return v;
  if (v === undefined || v === null || v === '') return [];
  return String(v)
    .split(',')
    .map((x) => x.trim())
    .filter(Boolean);
}
