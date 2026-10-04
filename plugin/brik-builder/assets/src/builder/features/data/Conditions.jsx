// Display conditions: a rule tree in attrs.visibility_rules, edited in a dialog and summarised
// in the settings panel. The canvas never hides elements; it marks them with a badge instead.
import { useState, useEffect } from '../../wp.js';
import { closeModal, openModal, setAttr, useStore } from '../../store.js';
import { Icon, iconSvg } from '../../icons.js';
import { cn, Dialog, Button, IconButton, Input, Collapsible } from '../../ui.jsx';
import { loadConditionTypes, errorText } from './api.js';
import { GroupSelect, MultiPick, Connector, Field, asList } from './controls.jsx';
import { openPicker } from './DataPicker.jsx';

function useTypes() {
  const [types, setTypes] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => {
    loadConditionTypes()
      .then((d) => setTypes(d.types))
      .catch((e) => setError(errorText(e)));
  }, []);
  return [types, error];
}

export function hasRules(v) {
  return !!(v && Array.isArray(v.rules) && v.rules.length);
}

function countRules(g) {
  return (g.rules || []).reduce((n, r) => n + (r.rules ? countRules(r) : 1), 0);
}

function blankRule(type = 'user_status', types) {
  const t = types && types[type];
  const rule = { type };
  if (t && t.ops) rule.op = Object.keys(t.ops)[0];
  if (t && t.value === 'select' && t.options && t.options[0]) rule.value = t.options[0].value;
  if (t && t.kind) rule.kind = t.kind[0].value;
  if (t && t.taxonomy && t.taxonomy[0]) rule.taxonomy = t.taxonomy.find((x) => x.value === 'category') ? 'category' : t.taxonomy[0].value;
  return rule;
}

/* ------------------------------------------------------------------------
 * Sentences.
 * ---------------------------------------------------------------------- */

function optLabel(options, v) {
  const o = (options || []).find((x) => String(x.value) === String(v));
  return o ? o.label : v;
}

function ruleText(r, types) {
  if (r.rules) {
    const inner = r.rules.map((x) => ruleText(x, types)).filter(Boolean);
    return inner.length > 1 ? `(${inner.join(r.relation === 'OR' ? ' or ' : ' and ')})` : inner[0] || '';
  }
  const t = types && types[r.type];
  if (!t) return r.type;
  const op = t.ops && r.op ? t.ops[r.op] : '';
  const list = asList(r.value).map((v) => optLabel(t.options, v));
  switch (t.value) {
    case 'select':
      return `${t.label} ${optLabel(t.options, r.value)}`;
    case 'date_range':
      return `${t.label} ${r.from ? `from ${r.from}` : ''} ${r.to ? `until ${r.to}` : ''}`.trim();
    case 'time_range':
      return `${t.label} ${r.from || '00:00'}–${r.to || '23:59'}`;
    case 'px_range':
      return `${t.label} ${r.min ? `≥ ${r.min}px` : ''} ${r.max ? `≤ ${r.max}px` : ''}`.trim();
  }
  const subject = r.type === 'data' ? `{${r.field || '…'}}` : r.type === 'url_param' || r.type === 'cookie' ? `${t.label} “${r.key || '…'}”` : r.type === 'term' ? `${t.label} (${r.taxonomy || '…'})` : t.label;
  const noValue = ['exists', 'not_exists', 'empty', 'not_empty'].includes(r.op);
  return `${subject} ${op}${noValue ? '' : ` ${list.join(', ') || '…'}`}`.trim();
}

/* ------------------------------------------------------------------------
 * Panel.
 * ---------------------------------------------------------------------- */

export function ConditionsControl({ value, onChange }) {
  const [types] = useTypes();
  const rules = hasRules(value) ? value : null;
  const edit = () => openModal('brik-conditions', { initial: rules || { relation: 'AND', rules: [blankRule('user_status', types)] }, onApply: onChange });

  if (!rules) {
    return (
      <div className="space-y-2">
        <p className="text-xs leading-snug text-muted-foreground">Show this element only when rules match: user, role, post fields, URL, date and time, device, cart and more.</p>
        <Button size="xs" variant="outline" icon="plus" onClick={edit}>
          Add condition
        </Button>
      </div>
    );
  }
  return (
    <div className="space-y-2">
      <div className="space-y-1 rounded-lg border border-border bg-muted/30 p-2.5">
        <p className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Show when {rules.relation === 'OR' ? 'any' : 'all'} match</p>
        <ul className="space-y-1">
          {rules.rules.map((r, i) => (
            <li key={i} className="flex gap-1.5 text-xs leading-snug">
              <Icon name="corner-down-right" size={12} className="mt-0.5 shrink-0 text-muted-foreground" />
              <span>{ruleText(r, types)}</span>
            </li>
          ))}
        </ul>
      </div>
      <div className="flex gap-1.5">
        <Button size="xs" variant="outline" icon="pencil" className="flex-1" onClick={edit}>
          Edit conditions
        </Button>
        <IconButton icon="trash-2" label="Remove all conditions" size="icon-sm" variant="outline" onClick={() => onChange(undefined)} />
      </div>
    </div>
  );
}

/** "Display conditions" section at the bottom of every element's settings. */
export function ConditionsSection({ node, tab }) {
  if (!node || !node.type || (tab && tab !== 'advanced')) return null;
  const value = (node.attrs || {}).visibility_rules;
  const n = hasRules(value) ? countRules(value) : 0;
  return (
    <Collapsible key={`${node.id}-dc`} title={<span className="flex items-center gap-2"><Icon name="eye-off" size={14} className="text-muted-foreground" />Display conditions{n ? <span className="rounded-full bg-brand/10 px-1.5 text-[10px] font-semibold text-brand">{n}</span> : null}</span>} defaultOpen={n > 0}>
      <ConditionsControl value={value} onChange={(v) => setAttr(node.id, 'visibility_rules', v, null, true)} />
    </Collapsible>
  );
}

/* ------------------------------------------------------------------------
 * Dialog.
 * ---------------------------------------------------------------------- */

export function ConditionsDialog({ initial, onApply }) {
  const [tree, setTree] = useState(() => JSON.parse(JSON.stringify(initial)));
  const [types, error] = useTypes();
  const options = types ? Object.entries(types).map(([value, t]) => ({ value, label: t.label, group: t.group })) : [];
  const perVisitor = types && hasPerVisitor(tree, types);

  return (
    <Dialog
      size="lg"
      title="Display conditions"
      description="The element is rendered only when these rules match. In the builder it always stays visible."
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
              const cleaned = clean(tree);
              onApply(cleaned.rules.length ? cleaned : undefined);
              closeModal();
            }}
          >
            Apply
          </Button>
        </>
      }
    >
      {error && <p className="mb-3 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">{error}</p>}
      {!types ? (
        <span className="bk-spinner" />
      ) : (
        <div className="space-y-4">
          <p className="text-sm">
            Show this element when{' '}
            <button type="button" className="font-semibold underline decoration-dotted underline-offset-2 cursor-pointer" onClick={() => setTree({ ...tree, relation: tree.relation === 'OR' ? 'AND' : 'OR' })}>
              {tree.relation === 'OR' ? 'any' : 'all'}
            </button>{' '}
            of these match:
          </p>
          <RuleGroup group={tree} onChange={setTree} types={types} options={options} depth={0} />
          <div className="space-y-2">
            {perVisitor && (
              <p className="flex gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs leading-snug text-amber-800">
                <Icon name="triangle-alert" size={14} className="mt-px shrink-0" />
                These rules depend on the visitor (login, role, cart, cookies or URL). Pages using them ask caching plugins not to store them, but a CDN or server cache may still serve one visitor’s version to another — exclude this page from such caches.
              </p>
            )}
            <p className="flex gap-2 text-xs leading-snug text-muted-foreground">
              <Icon name="monitor-smartphone" size={14} className="mt-px shrink-0" />
              Device and viewport rules can’t be checked on the server; they’re applied in the browser with CSS, so the element is still in the page source.
            </p>
          </div>
        </div>
      )}
    </Dialog>
  );
}

function hasPerVisitor(g, types) {
  return (g.rules || []).some((r) => (r.rules ? hasPerVisitor(r, types) : types[r.type] && types[r.type].per_visitor));
}

function clean(g) {
  return {
    relation: g.relation === 'OR' ? 'OR' : 'AND',
    rules: (g.rules || []).map((r) => (r.rules ? clean(r) : r)).filter((r) => (r.rules ? r.rules.length : r.type)),
  };
}

function RuleGroup({ group, onChange, types, options, depth, onRemove }) {
  const rules = group.rules || [];
  const update = (i, r) => onChange({ ...group, rules: rules.map((x, j) => (j === i ? r : x)) });
  const remove = (i) => onChange({ ...group, rules: rules.filter((_, j) => j !== i) });
  const flip = () => onChange({ ...group, relation: group.relation === 'OR' ? 'AND' : 'OR' });
  return (
    <div className={cn(depth > 0 && 'rounded-lg border border-border bg-muted/30 p-2')}>
      {depth > 0 && (
        <div className="mb-1.5 flex items-center justify-between px-1">
          <span className="text-xs text-muted-foreground">
            Match{' '}
            <button type="button" className="font-semibold text-foreground underline decoration-dotted underline-offset-2 cursor-pointer" onClick={flip}>
              {group.relation === 'OR' ? 'any' : 'all'}
            </button>{' '}
            of
          </span>
          <IconButton icon="x" label="Remove group" size="icon-sm" onClick={onRemove} />
        </div>
      )}
      {rules.map((r, i) => (
        <div key={i}>
          {i > 0 && <Connector relation={group.relation} onToggle={flip} />}
          {r.rules ? (
            <RuleGroup group={r} onChange={(g) => update(i, g)} types={types} options={options} depth={depth + 1} onRemove={() => remove(i)} />
          ) : (
            <Rule rule={r} onChange={(x) => update(i, x)} onRemove={() => remove(i)} types={types} options={options} />
          )}
        </div>
      ))}
      <div className="mt-2 flex gap-1">
        <Button size="xs" variant="ghost" icon="plus" onClick={() => onChange({ ...group, rules: [...rules, blankRule('user_status', types)] })}>
          Add rule
        </Button>
        {depth < 3 && (
          <Button size="xs" variant="ghost" icon="brackets" onClick={() => onChange({ ...group, rules: [...rules, { relation: group.relation === 'OR' ? 'AND' : 'OR', rules: [blankRule('device', types)] }] })}>
            Add group
          </Button>
        )}
      </div>
    </div>
  );
}

function Rule({ rule, onChange, onRemove, types, options }) {
  const t = types[rule.type];
  const set = (patch) => onChange({ ...rule, ...patch });
  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-lg border border-border bg-background p-1.5 shadow-xs">
      <GroupSelect value={rule.type} options={options} onChange={(type) => onChange(blankRule(type, types))} title="Rule" />
      {t && t.field && (
        <span className="flex items-center gap-0.5">
          <Field value={rule.field || ''} placeholder="meta:price" onChange={(e) => set({ field: e.target.value.replace(/[{}]/g, '') })} className="w-36 font-mono text-xs" title="Data source (a tag without braces)" />
          <IconButton icon="database" label="Choose data" size="icon-sm" onClick={() => openPicker({ mode: 'text', title: 'Compare a value', onPick: (tag) => set({ field: tag.replace(/[{}]/g, '') }) })} />
        </span>
      )}
      {t && t.taxonomy && <GroupSelect value={rule.taxonomy || ''} options={t.taxonomy} onChange={(taxonomy) => set({ taxonomy })} className="font-normal" title="Taxonomy" />}
      {t && t.key && <Field value={rule.key || ''} placeholder={t.key} onChange={(e) => set({ key: e.target.value })} className="w-32" title="Name" />}
      {t && t.kind && <GroupSelect value={rule.kind || t.kind[0].value} options={t.kind} onChange={(kind) => set({ kind })} className="font-normal" />}
      {t && t.ops && <GroupSelect value={rule.op || Object.keys(t.ops)[0]} options={Object.entries(t.ops).map(([value, label]) => ({ value, label }))} onChange={(op) => set({ op })} className="font-normal" title="Operator" />}
      {t && <RuleValue t={t} rule={rule} set={set} />}
      {t && t.client && (
        <span className="rounded-full bg-sky-500/10 px-2 py-0.5 text-[10px] font-semibold text-sky-700" title="Applied in the browser with CSS">
          CSS
        </span>
      )}
      <span className="ml-auto" />
      <IconButton icon="trash-2" label="Remove rule" size="icon-sm" onClick={onRemove} />
    </div>
  );
}

function RuleValue({ t, rule, set }) {
  if (['exists', 'not_exists', 'empty', 'not_empty'].includes(rule.op)) return null;
  switch (t.value) {
    case 'select':
      return <GroupSelect value={rule.value || ''} options={t.options || []} onChange={(value) => set({ value })} className="font-normal" />;
    case 'multi':
      return <MultiPick value={asList(rule.value)} options={t.options || []} onChange={(value) => set({ value })} />;
    case 'list':
      return <Field value={asList(rule.value).join(', ')} placeholder={t.hint || 'one, two'} title={t.hint || ''} onChange={(e) => set({ value: asList(e.target.value) })} className="w-44" />;
    case 'number':
      return <Field type="number" value={rule.value ?? ''} onChange={(e) => set({ value: e.target.value })} className="w-24" />;
    case 'date_range':
      return (
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          from <Field type="date" value={rule.from || ''} onChange={(e) => set({ from: e.target.value })} className="w-36" />
          until <Field type="date" value={rule.to || ''} onChange={(e) => set({ to: e.target.value })} className="w-36" />
        </span>
      );
    case 'time_range':
      return (
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          from <Field type="time" value={rule.from || ''} onChange={(e) => set({ from: e.target.value })} className="w-28" />
          to <Field type="time" value={rule.to || ''} onChange={(e) => set({ to: e.target.value })} className="w-28" />
        </span>
      );
    case 'px_range':
      return (
        <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
          min <Field type="number" value={rule.min ?? ''} onChange={(e) => set({ min: e.target.value })} className="w-20" placeholder="0" />
          max <Field type="number" value={rule.max ?? ''} onChange={(e) => set({ max: e.target.value })} className="w-20" placeholder="∞" />
          px
        </span>
      );
  }
  return <Field value={rule.value ?? ''} onChange={(e) => set({ value: e.target.value })} className="w-40" placeholder="Value" />;
}

/* ------------------------------------------------------------------------
 * Canvas badges.
 * ---------------------------------------------------------------------- */

function conditionalIds(nodes, out = []) {
  for (const n of nodes || []) {
    if (n.attrs && hasRules(n.attrs.visibility_rules)) out.push(n.id);
    if (n.children) conditionalIds(n.children, out);
  }
  return out;
}

/**
 * Marks elements that have display conditions with a dashed outline and a small badge drawn in
 * a fixed layer inside the canvas (nothing is added to the elements themselves).
 */
export function CanvasBadges() {
  const tree = useStore((s) => s.tree);
  const ready = useStore((s) => !!s.canvasReady);
  const ids = conditionalIds(tree).join(',');

  useEffect(() => {
    const frame = document.querySelector('.bk-canvas-wrap iframe');
    if (!frame) return undefined;
    let doc = null;
    let layer = null;
    let raf = 0;
    let observer = null;
    const list = ids ? ids.split(',') : [];

    const draw = () => {
      raf = 0;
      if (!doc || !layer) return;
      layer.innerHTML = '';
      for (const id of list) {
        const el = doc.querySelector(`[data-brik-id="${id}"]`);
        if (!el) continue;
        const r = el.getBoundingClientRect();
        if (r.bottom < 0 || r.top > doc.defaultView.innerHeight || !r.width) continue;
        const b = doc.createElement('span');
        b.className = 'brik-dc-badge';
        b.innerHTML = `${iconSvg('eye-off', 11)}<span>Conditional</span>`;
        b.style.cssText = `position:fixed;top:${Math.max(0, r.top) + 4}px;left:${r.right - 4}px;transform:translateX(-100%);display:inline-flex;align-items:center;gap:4px;padding:2px 7px;border-radius:999px;background:#7c3aed;color:#fff;font:600 10px/16px Inter,system-ui,sans-serif;letter-spacing:.02em;box-shadow:0 1px 3px rgb(0 0 0/.25);white-space:nowrap`;
        layer.appendChild(b);
      }
    };
    const schedule = () => {
      if (!raf && doc) raf = doc.defaultView.requestAnimationFrame(draw);
    };

    const setup = () => {
      doc = frame.contentDocument;
      if (!doc || !doc.body) return;
      layer = doc.getElementById('brik-dc-ui');
      if (!layer) {
        layer = doc.createElement('div');
        layer.id = 'brik-dc-ui';
        layer.style.cssText = 'position:fixed;inset:0;pointer-events:none;z-index:2147483000';
        doc.body.appendChild(layer);
      }
      let style = doc.getElementById('brik-dc-style');
      if (!style) {
        style = doc.createElement('style');
        style.id = 'brik-dc-style';
        doc.head.appendChild(style);
      }
      style.textContent = list.map((id) => `[data-brik-id="${id}"]{outline:1px dashed #7c3aed;outline-offset:-1px}`).join('');
      doc.defaultView.addEventListener('scroll', schedule, { passive: true });
      doc.defaultView.addEventListener('resize', schedule);
      // Re-draw when the page re-renders, but not for our own layer (that would loop).
      observer = new doc.defaultView.MutationObserver((records) => {
        if (records.some((m) => !layer.contains(m.target))) schedule();
      });
      observer.observe(doc.body, { childList: true, subtree: true });
      schedule();
    };

    setup();
    frame.addEventListener('load', setup);
    return () => {
      frame.removeEventListener('load', setup);
      if (observer) observer.disconnect();
      if (doc && doc.defaultView) {
        doc.defaultView.removeEventListener('scroll', schedule);
        doc.defaultView.removeEventListener('resize', schedule);
        if (raf) doc.defaultView.cancelAnimationFrame(raf);
      }
    };
  }, [ids, ready]);

  return null;
}
