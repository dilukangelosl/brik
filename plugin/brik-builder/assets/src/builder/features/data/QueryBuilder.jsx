// Visual query builder: reads like a sentence —
// SHOW [12] [Properties] WHERE [Price] [<] [500000] AND … ORDER BY [Price ↓]
import { useState, useEffect, useMemo, useRef } from '../../wp.js';
import { closeModal, openModal, getState } from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Dialog, Button, IconButton, Input, Switch, Label } from '../../ui.jsx';
import { loadFields, previewQuery, errorText } from './api.js';
import { GroupSelect, MultiPick, Connector, Keyword, Field, asList } from './controls.jsx';
import { openPicker } from './DataPicker.jsx';

const LIST_OPS = ['in', 'not_in', 'all'];
const NO_VALUE = ['empty', 'not_empty', 'exists', 'not_exists', 'is_true', 'is_false'];
const DAY_OPS = ['last_days', 'next_days', 'older_days'];

export function defaultQuery() {
  const s = getState();
  const type = s.post && s.post.type && !['brik_template', 'brik_library', 'page'].includes(s.post.type) ? s.post.type : 'post';
  return { post_type: type, where: { relation: 'AND', rules: [] }, order: [{ by: 'post:date', dir: 'DESC' }], limit: 9, exclude_current: true };
}

function useFields(postType) {
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    if (!postType) return;
    setData(null);
    setError('');
    loadFields(postType)
      .then(setData)
      .catch((e) => setError(errorText(e)));
  }, [postType]);
  return [data, error];
}

function useDebounced(value, delay = 400) {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setV(value), delay);
    return () => clearTimeout(t);
  }, [JSON.stringify(value)]);
  return v;
}

function usePreview(query) {
  const q = useDebounced(query);
  const [state, setState] = useState({ loading: true });
  useEffect(() => {
    if (!q || !q.post_type) return;
    let live = true;
    setState((s) => ({ ...s, loading: true }));
    previewQuery(q)
      .then((r) => live && setState({ result: r }))
      .catch((e) => live && setState({ error: errorText(e) }));
    return () => {
      live = false;
    };
  }, [JSON.stringify(q)]);
  return state;
}

/* ------------------------------------------------------------------------
 * Human readable summary.
 * ---------------------------------------------------------------------- */

function valueText(field, value) {
  const list = Array.isArray(value) ? value : [value];
  const label = (v) => {
    const o = field && field.options && field.options.find((x) => String(x.value) === String(v));
    return o ? o.label : v;
  };
  return list.filter((v) => v !== '' && v !== undefined && v !== null).map(label).join(', ');
}

function ruleText(rule, data) {
  if (rule.rules) {
    const inner = rule.rules.map((r) => ruleText(r, data)).filter(Boolean);
    return inner.length ? `(${inner.join(rule.relation === 'OR' ? ' or ' : ' and ')})` : '';
  }
  if (!rule.field) return '';
  const field = data && data.fields.find((f) => f.key === rule.field);
  const ops = field && data.ops[field.type];
  const op = ops && rule.op ? ops[rule.op] : rule.op || '';
  const name = field ? field.label : rule.field;
  if (NO_VALUE.includes(rule.op)) return `${name} ${op}`;
  if (rule.op === 'between') return `${name} between ${valueText(field, asList(rule.value)[0])} and ${valueText(field, asList(rule.value)[1])}`;
  if (DAY_OPS.includes(rule.op)) return `${name} ${String(op).replace('…', rule.value || '…')}`;
  return `${name} ${op} ${valueText(field, rule.value) || '…'}`;
}

export function summary(q, data) {
  if (!q) return '';
  const type = data && data.post_types[q.post_type] ? data.post_types[q.post_type] : q.post_type;
  let text = `Show ${q.limit || 10} ${type}`;
  const where = ruleText(q.where || { rules: [] }, data);
  if (where) text += ` where ${where.replace(/^\((.*)\)$/, '$1')}`;
  const order = (q.order || [])
    .map((o) => {
      if (o.by === 'rand') return 'random';
      const f = data && data.order.find((x) => x.key === o.by);
      return `${f ? f.label : o.by} ${o.dir === 'ASC' ? '↑' : '↓'}`;
    })
    .join(', ');
  if (order) text += `, ordered by ${order}`;
  return text;
}

/* ------------------------------------------------------------------------
 * Panel control.
 * ---------------------------------------------------------------------- */

export function QueryControl({ value, onChange }) {
  const q = value && typeof value === 'object' && value.post_type ? value : null;
  const [data] = useFields(q ? q.post_type : '');
  const preview = usePreview(q);
  const edit = () => openModal('brik-query-builder', { initial: q || defaultQuery(), onApply: onChange });

  if (!q) {
    return (
      <div className="rounded-lg border border-dashed border-border p-3">
        <div className="flex items-start gap-3">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-brand/10 text-brand">
            <Icon name="list-filter" size={16} />
          </span>
          <div className="min-w-0 space-y-2">
            <p className="text-sm font-medium">Visual query</p>
            <p className="text-xs leading-snug text-muted-foreground">Filter by any field — price, dates, terms, ACF — with AND/OR groups and a live result preview.</p>
            <Button size="xs" variant="outline" icon="sparkles" onClick={edit}>
              Build query
            </Button>
          </div>
        </div>
      </div>
    );
  }

  const r = preview.result;
  return (
    <div className="space-y-2 rounded-lg border border-border bg-muted/30 p-3">
      <div className="flex items-center justify-between gap-2">
        <span className="inline-flex items-center gap-1.5 text-xs font-medium">
          <Icon name="list-filter" size={13} className="text-brand" />
          Visual query
        </span>
        <span className={cn('rounded-full px-2 py-0.5 text-[11px] font-medium tabular-nums', preview.error ? 'bg-destructive/10 text-destructive' : 'bg-background text-muted-foreground ring-1 ring-border')}>
          {preview.error ? 'Error' : r ? `${r.count} ${r.count === 1 ? 'result' : 'results'}` : '…'}
        </span>
      </div>
      <p className="text-xs leading-relaxed text-foreground/80">{summary(q, data)}</p>
      {preview.error && <p className="text-[11px] text-destructive">{preview.error}</p>}
      <div className="flex gap-1.5">
        <Button size="xs" variant="outline" icon="pencil" onClick={edit} className="flex-1">
          Edit query
        </Button>
        <IconButton icon="trash-2" label="Remove query (use the basic settings)" size="icon-sm" variant="outline" onClick={() => onChange(undefined)} />
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Dialog.
 * ---------------------------------------------------------------------- */

export function QueryDialog({ initial, onApply }) {
  const [q, setQ] = useState(() => JSON.parse(JSON.stringify(initial)));
  const [data, error] = useFields(q.post_type);
  const preview = usePreview(q);
  const set = (patch) => setQ((prev) => ({ ...prev, ...patch }));

  const types = data ? Object.entries(data.post_types).map(([value, label]) => ({ value, label })) : [{ value: q.post_type, label: q.post_type }];
  const fieldOptions = data ? data.fields.map((f) => ({ value: f.key, label: f.label, group: f.group })) : [];
  const orderOptions = data ? data.order.map((f) => ({ value: f.key, label: f.label, group: f.group || 'Other' })) : [];

  return (
    <Dialog
      size="xl"
      title="Query builder"
      description="Choose which posts the listing shows. Rules read left to right like a sentence."
      onClose={closeModal}
      footer={
        <>
          <Button variant="outline" size="sm" onClick={closeModal}>
            Cancel
          </Button>
          <Button
            size="sm"
            icon="check"
            onClick={() => {
              onApply(clean(q));
              closeModal();
            }}
          >
            Apply query
          </Button>
        </>
      }
    >
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
        <div className="min-w-0 space-y-4">
          {error && <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}

          <div className="flex flex-wrap items-start gap-2">
            <Keyword>Show</Keyword>
            <Field type="number" min={1} max={100} value={q.limit ?? ''} onChange={(e) => set({ limit: e.target.value === '' ? '' : Number(e.target.value) })} className="w-20 font-medium" aria-label="Limit" />
            <GroupSelect
              value={q.post_type}
              options={types}
              onChange={(v) => set({ post_type: v, where: { relation: 'AND', rules: [] }, order: [{ by: 'post:date', dir: 'DESC' }] })}
              title="Post type"
            />
          </div>

          <div className="flex items-start gap-2">
            <Keyword>Where</Keyword>
            <div className="min-w-0 flex-1">
              {data ? (
                <GroupEditor group={q.where || { relation: 'AND', rules: [] }} onChange={(where) => set({ where })} data={data} fieldOptions={fieldOptions} depth={0} />
              ) : (
                <span className="bk-spinner" />
              )}
            </div>
          </div>

          <div className="flex items-start gap-2">
            <Keyword>Order by</Keyword>
            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
              {(q.order || []).map((o, i) => (
                <div key={i} className="flex flex-wrap items-center gap-1.5">
                  {i > 0 && <span className="text-xs text-muted-foreground">then</span>}
                  <GroupSelect value={o.by} options={orderOptions} onChange={(by) => set({ order: q.order.map((x, j) => (j === i ? { ...x, by } : x)) })} title="Order by" />
                  {o.by !== 'rand' && (
                    <Button
                      size="sm"
                      variant="outline"
                      icon={o.dir === 'ASC' ? 'arrow-up-narrow-wide' : 'arrow-down-wide-narrow'}
                      onClick={() => set({ order: q.order.map((x, j) => (j === i ? { ...x, dir: x.dir === 'ASC' ? 'DESC' : 'ASC' } : x)) })}
                    >
                      {o.dir === 'ASC' ? 'Ascending' : 'Descending'}
                    </Button>
                  )}
                  <IconButton icon="x" label="Remove sort" size="icon-sm" onClick={() => set({ order: q.order.filter((_, j) => j !== i) })} />
                </div>
              ))}
              {(q.order || []).length < 3 && (
                <div>
                  <Button size="xs" variant="ghost" icon="plus" onClick={() => set({ order: [...(q.order || []), { by: 'post:title', dir: 'ASC' }] })}>
                    {(q.order || []).length ? 'Then by' : 'Add sort'}
                  </Button>
                </div>
              )}
            </div>
          </div>

          <div className="flex flex-wrap items-start gap-2">
            <Keyword>Options</Keyword>
            <div className="flex flex-1 flex-wrap items-center gap-x-4 gap-y-2">
              <label className="flex items-center gap-2 text-sm">
                Skip
                <Field type="number" min={0} value={q.offset ?? ''} placeholder="0" onChange={(e) => set({ offset: e.target.value === '' ? '' : Number(e.target.value) })} className="w-16" />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <Switch checked={!!q.exclude_current} onChange={(v) => set({ exclude_current: v })} label="Exclude the current post" />
                Exclude current post
              </label>
              <label className="flex items-center gap-2 text-sm">
                Search
                <Field value={q.search || ''} placeholder="keywords or {search_query}" onChange={(e) => set({ search: e.target.value })} className="w-44" />
              </label>
              <label className="flex items-center gap-2 text-sm">
                <Switch checked={q.author === 'current'} onChange={(v) => set({ author: v ? 'current' : '' })} label="Only the visitor's own posts" />
                Only the visitor's own posts
              </label>
            </div>
          </div>
        </div>

        <PreviewPane preview={preview} />
      </div>
    </Dialog>
  );
}

/** Drop unfinished rules and empty groups before saving. */
function clean(q) {
  const group = (g) => ({
    relation: g.relation === 'OR' ? 'OR' : 'AND',
    rules: (g.rules || []).map((r) => (r.rules ? group(r) : r)).filter((r) => (r.rules ? r.rules.length : r.field)),
  });
  const out = { ...q, where: group(q.where || { rules: [] }) };
  if (out.offset === '' || !out.offset) delete out.offset;
  if (!out.search) delete out.search;
  if (!out.author) delete out.author;
  if (out.limit === '') out.limit = 10;
  return out;
}

/* ------------------------------------------------------------------------
 * Rules.
 * ---------------------------------------------------------------------- */

function GroupEditor({ group, onChange, data, fieldOptions, depth, onRemove }) {
  const rules = group.rules || [];
  const update = (i, rule) => onChange({ ...group, rules: rules.map((r, j) => (j === i ? rule : r)) });
  const remove = (i) => onChange({ ...group, rules: rules.filter((_, j) => j !== i) });
  const flip = () => onChange({ ...group, relation: group.relation === 'OR' ? 'AND' : 'OR' });
  const firstField = data.fields[0] ? data.fields[0].key : '';

  return (
    <div className={cn(depth > 0 && 'rounded-lg border border-border bg-muted/30 p-2')}>
      {depth > 0 && (
        <div className="mb-1.5 flex items-center justify-between gap-2 px-1">
          <span className="text-xs text-muted-foreground">
            Match <button type="button" className="font-semibold text-foreground underline decoration-dotted underline-offset-2 cursor-pointer" onClick={flip}>{group.relation === 'OR' ? 'any' : 'all'}</button> of
          </span>
          <IconButton icon="x" label="Remove group" size="icon-sm" onClick={onRemove} />
        </div>
      )}
      {rules.length === 0 && depth === 0 && <p className="py-1.5 text-sm text-muted-foreground">All posts of this type. Add a rule to narrow them down.</p>}
      {rules.map((rule, i) => (
        <div key={i}>
          {i > 0 && <Connector relation={group.relation} onToggle={flip} />}
          {rule.rules ? (
            <GroupEditor group={rule} onChange={(g) => update(i, g)} data={data} fieldOptions={fieldOptions} depth={depth + 1} onRemove={() => remove(i)} />
          ) : (
            <RuleRow rule={rule} onChange={(r) => update(i, r)} onRemove={() => remove(i)} data={data} fieldOptions={fieldOptions} />
          )}
        </div>
      ))}
      <div className="mt-2 flex gap-1">
        <Button size="xs" variant="ghost" icon="plus" onClick={() => onChange({ ...group, rules: [...rules, { field: firstField, op: '', value: '' }] })}>
          Add rule
        </Button>
        {depth < 3 && (
          <Button size="xs" variant="ghost" icon="brackets" onClick={() => onChange({ ...group, rules: [...rules, { relation: group.relation === 'OR' ? 'AND' : 'OR', rules: [{ field: firstField, op: '', value: '' }] }] })}>
            Add group
          </Button>
        )}
      </div>
    </div>
  );
}

function RuleRow({ rule, onChange, onRemove, data, fieldOptions }) {
  const field = data.fields.find((f) => f.key === rule.field);
  const ops = field ? data.ops[field.type] : {};
  const op = rule.op && ops[rule.op] ? rule.op : Object.keys(ops)[0];
  const setField = (key) => {
    const f = data.fields.find((x) => x.key === key);
    onChange({ field: key, op: f ? Object.keys(data.ops[f.type])[0] : '', value: '' });
  };
  const setOp = (next) => {
    const wasList = LIST_OPS.includes(op) || op === 'between';
    const isList = LIST_OPS.includes(next) || next === 'between';
    onChange({ ...rule, op: next, value: wasList === isList && !NO_VALUE.includes(next) ? rule.value : '' });
  };
  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-background p-1.5 shadow-xs">
      <GroupSelect value={rule.field} options={fieldOptions} onChange={setField} placeholder={field ? undefined : 'Choose a field'} title="Field" />
      {field && (
        <GroupSelect
          value={op}
          options={Object.entries(ops).map(([value, label]) => ({ value, label }))}
          onChange={setOp}
          className="font-normal"
          title="Operator"
        />
      )}
      {field && <ValueInput field={field} op={op} value={rule.value} onChange={(value) => onChange({ ...rule, op, value })} />}
      {!field && rule.field && <span className="text-xs text-destructive">Unknown field “{rule.field}”</span>}
      <span className="ml-auto" />
      <IconButton icon="trash-2" label="Remove rule" size="icon-sm" onClick={onRemove} />
    </div>
  );
}

function TagButton({ onPick }) {
  return <IconButton icon="database" label="Use dynamic data" size="icon-sm" variant="ghost" onClick={() => openPicker({ mode: 'text', onPick })} />;
}

function ValueInput({ field, op, value, onChange }) {
  if (NO_VALUE.includes(op)) return null;
  const type = field.type;
  const options = field.options || [];

  if (DAY_OPS.includes(op)) {
    return (
      <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
        <Field type="number" min={0} value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="w-20" placeholder="30" />
        days
      </span>
    );
  }

  if (op === 'between') {
    const [a, b] = asList(value);
    const input = type === 'date' ? 'date' : type === 'number' ? 'number' : 'text';
    return (
      <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
        <Field type={input} value={a ?? ''} onChange={(e) => onChange([e.target.value, b ?? ''])} className="w-32" />
        and
        <Field type={input} value={b ?? ''} onChange={(e) => onChange([a ?? '', e.target.value])} className="w-32" />
      </span>
    );
  }

  if (LIST_OPS.includes(op)) {
    if (options.length) return <MultiPick value={asList(value)} options={options} onChange={onChange} />;
    return <Field value={asList(value).join(', ')} placeholder="value one, value two" onChange={(e) => onChange(asList(e.target.value))} className="w-56" />;
  }

  if (options.length) {
    return <GroupSelect value={value ?? ''} options={options} onChange={onChange} placeholder="Choose…" className="font-normal" />;
  }
  if (type === 'date') {
    return <Field type="date" value={value ?? ''} onChange={(e) => onChange(e.target.value)} className="w-40" />;
  }
  return (
    <span className="flex items-center gap-0.5">
      <Field
        type={type === 'number' && !(typeof value === 'string' && value.includes('{')) ? 'number' : 'text'}
        value={value ?? ''}
        placeholder={type === 'number' ? '0' : type === 'post' ? 'Post ID' : 'Value'}
        onChange={(e) => onChange(e.target.value)}
        className="w-36"
      />
      <TagButton onPick={(t) => onChange(t)} />
    </span>
  );
}

/* ------------------------------------------------------------------------
 * Live preview.
 * ---------------------------------------------------------------------- */

function PreviewPane({ preview }) {
  const [code, setCode] = useState(false);
  const [copied, setCopied] = useState(false);
  const r = preview.result;
  return (
    <aside className="flex min-w-0 flex-col gap-3 rounded-xl border border-border bg-muted/30 p-4 lg:sticky lg:top-0 lg:self-start">
      <div className="flex items-baseline justify-between gap-2">
        <Label>Results</Label>
        {preview.loading && <span className="bk-spinner scale-75" />}
      </div>
      {preview.error ? (
        <p className="rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">{preview.error}</p>
      ) : r ? (
        <>
          <p className="flex items-baseline gap-2">
            <span className="text-3xl font-semibold tabular-nums tracking-tight">{r.count}</span>
            <span className="text-sm text-muted-foreground">{r.total > r.count ? `shown of ${r.total} matching` : r.count === 1 ? 'post' : 'posts'}</span>
          </p>
          {r.items.length === 0 ? (
            <p className="rounded-md border border-dashed border-border px-3 py-6 text-center text-xs text-muted-foreground">No posts match these rules.</p>
          ) : (
            <ul className="space-y-1">
              {r.items.map((it) => (
                <li key={it.id} className="flex items-center gap-2.5 rounded-md bg-background p-1.5 ring-1 ring-border">
                  {it.image ? (
                    <img src={it.image} alt="" className="size-8 shrink-0 rounded object-cover" />
                  ) : (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded bg-muted text-muted-foreground">
                      <Icon name="file-text" size={14} />
                    </span>
                  )}
                  <span className="min-w-0 flex-1 truncate text-sm">{it.title || '(no title)'}</span>
                  <span className="text-[10px] tabular-nums text-muted-foreground">#{it.id}</span>
                </li>
              ))}
              {r.count > r.items.length && <li className="px-1.5 text-xs text-muted-foreground">+ {r.count - r.items.length} more</li>}
            </ul>
          )}
          <div className="border-t border-border pt-3">
            <Button size="xs" variant="ghost" icon="code" onClick={() => setCode(!code)}>
              {code ? 'Hide code' : 'Show code'}
            </Button>
            {code && (
              <div className="relative mt-2">
                <pre className="max-h-64 overflow-auto rounded-md bg-foreground/95 p-3 font-mono text-[11px] leading-relaxed text-background [tab-size:2]">{r.code}</pre>
                <IconButton
                  icon={copied ? 'check' : 'copy'}
                  label="Copy code"
                  size="icon-sm"
                  variant="secondary"
                  className="absolute right-1.5 top-1.5"
                  onClick={() => {
                    navigator.clipboard && navigator.clipboard.writeText(r.code);
                    setCopied(true);
                    setTimeout(() => setCopied(false), 1500);
                  }}
                />
              </div>
            )}
          </div>
        </>
      ) : (
        <span className="text-xs text-muted-foreground">Loading…</span>
      )}
    </aside>
  );
}
