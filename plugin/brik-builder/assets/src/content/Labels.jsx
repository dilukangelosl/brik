// Label overrides, prefilled with what WordPress would generate from singular / plural.
import { useState, useEffect, __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Input } from '../builder/ui.jsx';
import { previewLabels } from './lib.js';

function humanize(key) {
  const s = key.replace(/_/g, ' ');
  return s.charAt(0).toUpperCase() + s.slice(1);
}

export function useGeneratedLabels(singular, plural, kind) {
  const [labels, setLabels] = useState({});
  useEffect(() => {
    if (!singular && !plural) return;
    const t = setTimeout(() => {
      previewLabels(singular || plural, plural || singular, kind).then((res) => {
        const l = (res && (res.labels || res)) || {};
        setLabels(typeof l === 'object' ? l : {});
      });
    }, 300);
    return () => clearTimeout(t);
  }, [singular, plural]);
  return labels;
}

export function LabelsEditor({ value = {}, onChange, generated }) {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const keys = Array.from(new Set([...Object.keys(generated || {}), ...Object.keys(value || {})])).filter((k) => typeof (generated[k] ?? value[k] ?? '') === 'string');
  const overridden = Object.keys(value || {}).filter((k) => value[k]).length;
  const shown = keys.filter((k) => !q || `${k} ${generated[k] || ''}`.toLowerCase().includes(q.toLowerCase()));

  return (
    <div className="rounded-lg border border-border">
      <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full items-center justify-between gap-3 px-3.5 py-3 text-left cursor-pointer hover:bg-muted/40">
        <span>
          <span className="block text-[13px] font-medium">{__('Labels', 'brik-builder')}</span>
          <span className="block text-xs text-muted-foreground">
            {overridden ? sprintf(__('%1$d of %2$d customised', 'brik-builder'), overridden, keys.length) : sprintf(__('%d labels generated from the names above', 'brik-builder'), keys.length)}
          </span>
        </span>
        <Icon name="chevron-down" size={16} className={cn('text-muted-foreground transition-transform', open && 'rotate-180')} />
      </button>
      {open && (
        <div className="border-t border-border p-3.5">
          {keys.length > 8 && <Input className="mb-3" placeholder={__('Find a label…', 'brik-builder')} value={q} onChange={(e) => setQ(e.target.value)} />}
          <div className="grid gap-x-4 gap-y-2.5 sm:grid-cols-2">
            {shown.map((k) => (
              <label key={k} className="flex flex-col gap-1">
                <span className="flex items-center justify-between text-xs text-muted-foreground">
                  {humanize(k)}
                  {value[k] && (
                    <button type="button" className="text-[11px] hover:text-foreground cursor-pointer" onClick={() => onChange({ ...value, [k]: undefined })}>
                      {__('Reset', 'brik-builder')}
                    </button>
                  )}
                </span>
                <Input
                  value={value[k] || ''}
                  placeholder={generated[k] || ''}
                  onChange={(e) => {
                    const next = { ...value, [k]: e.target.value };
                    if (!e.target.value) delete next[k];
                    onChange(next);
                  }}
                  className={value[k] ? 'border-brand/40' : ''}
                />
              </label>
            ))}
          </div>
          {!keys.length && <p className="text-xs text-muted-foreground">{__('Enter a singular and plural name to see the labels.', 'brik-builder')}</p>}
        </div>
      )}
    </div>
  );
}

/** Drops empty overrides before saving. */
export function cleanLabels(labels) {
  const out = {};
  for (const [k, v] of Object.entries(labels || {})) if (v) out[k] = v;
  return out;
}
