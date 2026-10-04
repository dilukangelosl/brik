// Variable picker: a small button in field labels that sets a value to var(--name).
import { useState, useMemo } from '../../wp.js';
import { useStore, setAttr, attrKey } from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Popover, Input } from '../../ui.jsx';
import { useDesign } from './data.js';

const TOKENS = ['background', 'foreground', 'card', 'card-foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'destructive', 'border', 'input', 'ring'];

const GROUP_LABELS = { color: 'Colors', space: 'Spacing', radius: 'Radius', text: 'Font sizes', shadow: 'Shadows', size: 'Sizes', other: 'Other' };

/** Which variable groups fit a field, most relevant first. */
export function groupsFor(field, fkey = '') {
  const k = String(fkey);
  if (field.type === 'color') return ['color'];
  if (field.type === 'shadow') return ['shadow'];
  if (field.type === 'spacing') return /radius/.test(k) ? ['radius', 'space'] : /border_width/.test(k) ? ['size', 'space'] : ['space', 'size'];
  if (field.type === 'unit') {
    if (/font_size/.test(k)) return ['text', 'size'];
    if (/radius/.test(k)) return ['radius', 'space'];
    if (/line_height|letter_spacing/.test(k)) return ['other', 'size'];
    return ['space', 'size'];
  }
  return [];
}

export function supportsVariables(field) {
  return ['unit', 'spacing', 'color', 'shadow'].includes(field && field.type);
}

/** Every variable as { name, value, preview, group, label }. */
export function useVariables() {
  const design = useDesign();
  const settings = useStore((s) => s.settings);
  return useMemo(() => {
    const out = [];
    const radius = (settings && settings.radius) || '0.625rem';
    const resolve = (v) => String(v || '').replace(/var\(--radius\)/g, radius);
    const resolved = (settings && settings.resolved && settings.resolved.light) || {};
    for (const t of TOKENS) out.push({ name: t, group: 'color', value: resolved[t] || '', preview: resolved[t] || `var(--${t})`, label: t, kind: 'token' });
    for (const c of (settings && settings.colors) || []) out.push({ name: `brik-color-${c.id}`, group: 'color', value: c.value, preview: c.value, label: c.name || c.id, kind: 'global' });
    const vars = (design && design.variables) || {};
    for (const g of ['space', 'radius', 'text', 'shadow']) {
      for (const v of vars[g] || []) out.push({ name: v.name, group: g, value: v.css || v.value, preview: resolve(v.value), label: v.name });
    }
    for (const v of vars.custom || []) out.push({ name: v.name, group: v.group || 'other', value: v.value, preview: resolve(v.value), label: v.name, kind: 'custom' });
    return out;
  }, [design, settings]);
}

export function VarPreview({ v }) {
  if (v.group === 'color') {
    return (
      <span className="bk-checker relative size-5 shrink-0 overflow-hidden rounded border border-border">
        <span className="absolute inset-0" style={{ background: v.preview }} />
      </span>
    );
  }
  if (v.group === 'radius') {
    return <span className="size-5 shrink-0 border-2 border-foreground/50 border-b-0 border-r-0" style={{ borderTopLeftRadius: v.preview }} />;
  }
  if (v.group === 'shadow') {
    return <span className="size-5 shrink-0 rounded bg-background ring-1 ring-border/50" style={{ boxShadow: v.preview }} />;
  }
  if (v.group === 'text') {
    return (
      <span className="flex h-5 w-7 shrink-0 items-center justify-center overflow-hidden font-semibold leading-none" style={{ fontSize: `min(${v.preview}, 20px)` }}>
        Aa
      </span>
    );
  }
  if (v.group === 'space' || v.group === 'size') {
    return (
      <span className="flex h-5 w-12 shrink-0 items-center">
        <span className="h-2 max-w-12 rounded-sm bg-brand/60" style={{ width: v.preview }} />
      </span>
    );
  }
  return <Icon name="variable" size={14} className="shrink-0 text-muted-foreground" />;
}

/**
 * The picker. `value` is the current field value, `onPick(value)` receives "var(--name)" (or '' to clear).
 */
export function VariablePicker({ field, fkey, value, onPick, trigger }) {
  const all = useVariables();
  const [q, setQ] = useState('');
  const groups = groupsFor(field, fkey);
  const current = typeof value === 'string' ? (value.match(/^var\(--([a-z0-9-]+)\)$/) || [])[1] : null;

  const term = q.trim().toLowerCase();
  const list = all.filter((v) => (term ? `${v.name} ${v.label}`.toLowerCase().includes(term) : groups.includes(v.group)));
  const byGroup = {};
  for (const v of list) (byGroup[v.group] = byGroup[v.group] || []).push(v);
  const order = [...groups, ...Object.keys(byGroup).filter((g) => !groups.includes(g))];

  return (
    <Popover
      align="end"
      width={272}
      trigger={
        trigger || (
          <button type="button" title="Use a variable" aria-label="Use a variable" className={cn('inline-flex cursor-pointer items-center rounded text-muted-foreground hover:text-foreground', current && 'text-brand hover:text-brand')}>
            <Icon name="variable" size={13} />
          </button>
        )
      }
    >
      {(close) => (
        <div className="-m-1 space-y-2">
          <div className="relative">
            <Icon name="search" size={13} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <Input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search variables" className="h-7 pl-7 text-xs" />
          </div>
          <div className="max-h-72 space-y-2 overflow-y-auto pr-0.5">
            {order
              .filter((g) => byGroup[g])
              .map((g) => (
                <div key={g}>
                  <p className="px-1.5 pb-1 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">{GROUP_LABELS[g] || g}</p>
                  {byGroup[g].map((v) => (
                    <button
                      key={v.name}
                      type="button"
                      onClick={() => {
                        onPick(`var(--${v.name})`);
                        close();
                      }}
                      className={cn('flex w-full cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-left hover:bg-accent', current === v.name && 'bg-accent')}
                    >
                      <VarPreview v={v} />
                      <span className="min-w-0 flex-1 truncate font-mono text-[11px]">{v.kind === 'global' ? v.label : `--${v.name}`}</span>
                      <span className="max-w-20 truncate text-[10px] text-muted-foreground">{v.group === 'shadow' ? '' : String(v.value).replace(/^oklch\(([^)]*)\)$/, 'oklch')}</span>
                      {current === v.name && <Icon name="check" size={12} className="text-brand" />}
                    </button>
                  ))}
                </div>
              ))}
            {!list.length && <p className="px-1.5 py-3 text-center text-xs text-muted-foreground">No matching variables.</p>}
          </div>
          {current && (
            <button
              type="button"
              className="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-md border border-border py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
              onClick={() => {
                onPick('');
                close();
              }}
            >
              <Icon name="unlink" size={12} />
              Detach variable
            </button>
          )}
        </div>
      )}
    </Popover>
  );
}

/** fieldLabel slot: variable button on unit, spacing, color and shadow fields of the selected element. */
export function FieldVariableButton({ node, fkey, field }) {
  const mode = useStore((s) => s.mode);
  const device = useStore((s) => s.device);
  if (!node || !supportsVariables(field) || node.type === 'global') return null;
  const k = attrKey(fkey, field);
  const value = (node.attrs || {})[k];
  return <VariablePicker field={field} fkey={fkey} value={value} onPick={(v) => setAttr(node.id, fkey, v || undefined, field)} />;
}
