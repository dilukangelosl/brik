// Visual CSS inspector: box model, computed styles, generated rules, classes and variables.
import { useState, useEffect, useRef } from '../../wp.js';
import * as store from '../../store.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { cn, Input, Empty } from '../../ui.jsx';
import { nodeEl, useCanvasVersion, rgba, nodeLabel, copyText } from './util.js';
import { STATES, getForced, toggleForced, clearForced, subscribeForced } from './force.js';
import { CssCode } from './Code.jsx';

const GROUPS = {
  Layout: ['display', 'position', 'top', 'right', 'bottom', 'left', 'z-index', 'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height', 'box-sizing', 'flex-direction', 'flex-wrap', 'flex', 'justify-content', 'align-items', 'align-self', 'gap', 'grid-template-columns', 'overflow-x', 'overflow-y', 'float'],
  Typography: ['font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-align', 'text-transform', 'text-decoration-line', 'white-space'],
  Colors: ['color', 'background-color', 'background-image', 'border-top-color', 'outline-color', 'fill', 'stroke'],
  Effects: ['opacity', 'visibility', 'border-radius', 'box-shadow', 'filter', 'backdrop-filter', 'transform', 'transition', 'animation-name', 'mix-blend-mode', 'cursor'],
};

const SKIP = new Set(['none', 'normal', 'auto', '0px', 'rgba(0, 0, 0, 0)', 'visible', 'static', 'start', 'stretch', 'nowrap', 'row', 'content-box', 'all', '0s', 'ease', 'none 0s ease 0s 1 normal none running']);

const INHERITED = ['color', 'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-align'];

const TOKENS = ['--background', '--foreground', '--primary', '--primary-foreground', '--muted', '--muted-foreground', '--border', '--radius', '--brik-container'];

/* ------------------------------------------------------------------------
 * Data.
 * ---------------------------------------------------------------------- */

/** Rules in the generated stylesheet that target this element (or its inner parts). */
export function generatedRules(el, id) {
  const doc = el.ownerDocument;
  // The frontend.css <link> uses the same id, so match the <style> element.
  const style = doc.querySelector('style#brik-css');
  if (!style || !style.sheet) return [];
  const re = new RegExp(`brik-n-${id}(?![\\w-])`);
  const out = [];
  const visit = (rules, media) => {
    for (const rule of rules) {
      if (rule.selectorText !== undefined) {
        if (!re.test(rule.selectorText)) continue;
        const selectors = rule.selectorText.split(/,(?![^(]*\))/).map((x) => x.trim()).filter((x) => re.test(x));
        let target = 'self';
        try {
          if (!selectors.some((sel) => el.matches(sel))) target = selectors.some((sel) => el.querySelector(sel.replace(/\.bk-force-\w+/g, ''))) ? 'inner' : 'state';
        } catch (e) {
          target = 'state';
        }
        const active = !media || doc.defaultView.matchMedia(media).matches;
        out.push({ selector: selectors.join(',\n'), decls: rule.style.cssText.split(/;\s*/).filter(Boolean), media, active, target });
      } else if (rule.cssRules) {
        visit(rule.cssRules, rule.conditionText || rule.media?.mediaText || media);
      }
    }
  };
  visit(style.sheet.cssRules, null);
  return out;
}

export function rulesToCss(rules) {
  return rules
    .map((r) => {
      const body = `${r.selector} {\n${r.decls.map((d) => `  ${d};`).join('\n')}\n}`;
      return r.media ? `@media ${r.media} {\n${body.replace(/^/gm, '  ')}\n}` : body;
    })
    .join('\n\n');
}

function elementPath(el) {
  const out = [];
  for (let n = el; n && !n.hasAttribute('data-brik-root'); n = n.parentElement) {
    if (n.dataset && n.dataset.brikId) out.unshift(n);
    if (n === n.ownerDocument.body) break;
  }
  return out;
}

function sides(cs, prop) {
  const suffix = prop === 'border' ? '-width' : '';
  return ['top', 'right', 'bottom', 'left'].map((s) => parseFloat(cs.getPropertyValue(`${prop}-${s}${suffix}`)) || 0);
}

function parseShorthand(v) {
  const p = String(v || '').trim().split(/\s+(?![^(]*\))/).filter(Boolean);
  if (!p.length) return null;
  return [p[0], p[1] ?? p[0], p[2] ?? p[0], p[3] ?? p[1] ?? p[0]];
}

function variablesUsed(rules, el) {
  const names = new Set();
  const scan = (text) => {
    for (const m of String(text).matchAll(/var\(\s*(--[\w-]+)/g)) names.add(m[1]);
  };
  rules.forEach((r) => r.decls.forEach(scan));
  scan(el.getAttribute('style') || '');
  const cs = el.ownerDocument.defaultView.getComputedStyle(el);
  return [...names].map((n) => ({ name: n, value: cs.getPropertyValue(n).trim() }));
}

function inheritedFrom(el, schema, tree) {
  const win = el.ownerDocument.defaultView;
  return INHERITED.map((prop) => {
    const value = win.getComputedStyle(el).getPropertyValue(prop);
    let origin = el;
    for (let p = el.parentElement; p && p.nodeType === 1; p = p.parentElement) {
      if (win.getComputedStyle(p).getPropertyValue(prop) !== value) break;
      origin = p;
    }
    let from = 'this element';
    let id = null;
    if (origin !== el) {
      const owner = origin.closest('[data-brik-id]');
      if (owner && owner.contains(el) && owner !== el && origin !== origin.ownerDocument.body && origin !== origin.ownerDocument.documentElement) {
        id = owner.dataset.brikId;
        from = nodeLabel(T.find(tree, id)) || owner.dataset.brikType;
      } else if (owner === el) {
        from = 'this element';
      } else {
        from = 'page / theme';
      }
    }
    return { prop, value, from, id };
  });
}

/* ------------------------------------------------------------------------
 * Panel.
 * ---------------------------------------------------------------------- */

export function InspectPanel() {
  const selected = store.useStore((s) => s.selected);
  const tree = store.useStore((s) => s.tree);
  const schema = store.useStore((s) => s.schema);
  const device = store.useStore((s) => store.effectiveDevice(s));
  const v = useCanvasVersion();
  const [forcedTick, setForcedTick] = useState(0);
  useEffect(() => subscribeForced(() => setForcedTick((x) => x + 1)), []);
  useEffect(() => () => clearForced(), []);
  useEffect(() => {
    if (getForced().id && getForced().id !== selected) clearForced();
  }, [selected]);

  const node = selected ? T.find(tree, selected) : null;
  const el = node ? nodeEl(node.id) : null;

  if (!node) {
    return (
      <div className="p-4">
        <Empty icon="scan-search" title="Inspect an element">
          Select something on the canvas to see its box model, computed styles and the CSS Brik generates for it.
        </Empty>
      </div>
    );
  }
  if (!el) {
    return (
      <div className="p-4">
        <Empty icon="eye-off" title="Not in the canvas">
          This element is not rendered right now.
        </Empty>
      </div>
    );
  }
  return <Inspection key={node.id} node={node} el={el} schema={schema} tree={tree} device={device} v={v} forcedTick={forcedTick} />;
}

function Inspection({ node, el, schema, tree, device }) {
  const doc = el.ownerDocument;
  const win = doc.defaultView;
  const cs = win.getComputedStyle(el);
  const rules = generatedRules(el, node.id);
  const forced = getForced();
  const path = elementPath(el);

  return (
    <div className="pb-6 text-xs" data-bk-inspector data-bk-inspect-id={node.id}>
      <div className="space-y-2 border-b border-border p-3">
        <div className="flex flex-wrap items-center gap-0.5 font-mono text-[10.5px]" data-bk-path>
          {path.map((n, i) => {
            const id = n.dataset.brikId;
            const tag = n.tagName.toLowerCase();
            const cls = `brik-${n.dataset.brikType}`;
            const current = id === node.id;
            return (
              <span key={id} className="flex items-center gap-0.5">
                {i > 0 && <Icon name="chevron-right" size={10} className="text-muted-foreground/60" />}
                <button type="button" onClick={() => store.select(id)} className={cn('rounded px-1 py-0.5 cursor-pointer', current ? 'bg-foreground text-background' : 'text-muted-foreground hover:bg-accent hover:text-foreground')} title={nodeLabel(T.find(tree, id))}>
                  <span className={current ? '' : 'text-fuchsia-600 dark:text-fuchsia-400'}>{tag}</span>
                  <span className={current ? 'opacity-70' : ''}>.{cls}</span>
                </button>
              </span>
            );
          })}
        </div>
        <div className="flex items-center justify-between">
          <span className="text-muted-foreground">Force state</span>
          <div className="flex gap-1" data-bk-force>
            {STATES.map((st) => {
              const on = forced.id === node.id && forced.states.includes(st);
              return (
                <button key={st} type="button" data-state={st} aria-pressed={on} onClick={() => toggleForced(node.id, st)} className={cn('rounded-md border px-2 py-0.5 font-mono text-[10.5px] cursor-pointer', on ? 'border-brand bg-brand text-white' : 'border-border text-muted-foreground hover:bg-accent hover:text-foreground')}>
                  :{st}
                </button>
              );
            })}
          </div>
        </div>
      </div>

      <Section title="Box model" icon="box" defaultOpen>
        <BoxModel node={node} cs={cs} device={device} />
      </Section>

      <Section title="Computed" icon="list-tree" defaultOpen>
        <Computed cs={cs} />
      </Section>

      <Section title={`Brik CSS · ${rules.length} rule${rules.length === 1 ? '' : 's'}`} icon="code-xml" defaultOpen>
        {rules.length ? (
          <div className="space-y-1.5" data-bk-rules>
            {rules.map((r, i) => (
              <div key={i} className={cn('overflow-hidden rounded-md border border-border', !r.active && 'opacity-45')}>
                {(r.media || r.target !== 'self') && (
                  <div className="flex items-center gap-1.5 border-b border-border bg-muted/60 px-2 py-1 font-mono text-[10px] text-muted-foreground">
                    {r.media && <span>@media {r.media}</span>}
                    {r.media && !r.active && <span className="rounded bg-background px-1">inactive</span>}
                    {r.target === 'inner' && <span className="rounded bg-background px-1">inner element</span>}
                    {r.target === 'state' && <span className="rounded bg-background px-1">state</span>}
                  </div>
                )}
                <CssCode code={`${r.selector} {\n${r.decls.map((d) => `  ${d};`).join('\n')}\n}`} className="max-h-none border-0 rounded-none" />
              </div>
            ))}
            <button type="button" onClick={() => copyText(rulesToCss(rules))} className="flex items-center gap-1 text-[11px] text-muted-foreground hover:text-foreground cursor-pointer">
              <Icon name="clipboard-copy" size={11} /> Copy all
            </button>
          </div>
        ) : (
          <p className="text-muted-foreground">No generated rules. This element uses only its module defaults.</p>
        )}
      </Section>

      <Section title="Classes" icon="hash">
        <div className="flex flex-wrap gap-1" data-bk-classes>
          {[...el.classList].map((c) => (
            <span key={c} className={cn('rounded border px-1.5 py-0.5 font-mono text-[10.5px]', c.startsWith('bk-force') ? 'border-brand/40 text-brand' : 'border-border')}>
              .{c}
            </span>
          ))}
        </div>
      </Section>

      <Section title="CSS variables" icon="variable">
        <Variables vars={variablesUsed(rules, el)} el={el} />
      </Section>

      <Section title="Inherited text styles" icon="type">
        <table className="w-full" data-bk-inherited>
          <tbody>
            {inheritedFrom(el, schema, tree).map((row) => (
              <tr key={row.prop} className="align-top">
                <td className="py-0.5 pr-2 font-mono text-[10.5px] text-sky-700 dark:text-sky-400">{row.prop}</td>
                <td className="py-0.5 pr-2 font-mono text-[10.5px] break-all">
                  <Swatch value={row.prop === 'color' ? row.value : null} />
                  {row.prop === 'font-family' ? row.value.split(',')[0] : row.value}
                </td>
                <td className="py-0.5 text-right text-[10.5px] text-muted-foreground whitespace-nowrap">
                  {row.id ? (
                    <button type="button" className="hover:text-foreground hover:underline cursor-pointer" onClick={() => store.select(row.id)}>
                      {row.from}
                    </button>
                  ) : (
                    row.from
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Section>
    </div>
  );
}

function Section({ title, icon, children, defaultOpen = false }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border-b border-border">
      <button type="button" onClick={() => setOpen(!open)} className="flex w-full items-center justify-between px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground hover:text-foreground cursor-pointer">
        <span className="flex items-center gap-1.5">
          <Icon name={icon} size={12} />
          {title}
        </span>
        <Icon name="chevron-down" size={12} className={cn('transition-transform', open && 'rotate-180')} />
      </button>
      {open && <div className="px-3 pb-3">{children}</div>}
    </div>
  );
}

function Swatch({ value }) {
  if (!value) return null;
  const c = rgba(value);
  if (!c || (c[3] === 0 && !/transparent|0\)$/.test(value))) return null;
  return <span className="mr-1 inline-block size-2.5 rounded-sm border border-border align-[-1px]" style={{ background: value }} />;
}

/* ------------------------------------------------------------------------
 * Box model.
 * ---------------------------------------------------------------------- */

function BoxModel({ node, cs, device }) {
  const margin = sides(cs, 'margin');
  const border = sides(cs, 'border');
  const padding = sides(cs, 'padding');
  const cw = parseFloat(cs.width) - (cs.boxSizing === 'border-box' ? padding[1] + padding[3] + border[1] + border[3] : 0);
  const ch = parseFloat(cs.height) - (cs.boxSizing === 'border-box' ? padding[0] + padding[2] + border[0] + border[2] : 0);

  const write = (prop, index, value) => {
    const s = store.getState();
    const def = s.schema.byType[node.type];
    const field = def && def.fields[prop];
    const key = device !== 'desktop' && field && field.responsive ? `${prop}@${device}` : prop;
    const attrs = node.attrs || {};
    const chain = device === 'mobile' ? [`${prop}@mobile`, `${prop}@tablet`, prop] : device === 'tablet' ? [`${prop}@tablet`, prop] : [prop];
    const current = chain.map((k) => attrs[k]).find((x) => x !== undefined && x !== '');
    const computed = (prop === 'margin' ? margin : padding).map((n) => (n ? `${Math.round(n * 100) / 100}px` : '0'));
    const parts = parseShorthand(current) || computed;
    const v = String(value).trim();
    parts[index] = v === '' ? '0' : /^-?[\d.]+$/.test(v) ? (+v === 0 ? '0' : `${v}px`) : v;
    const next = parts[0] === parts[2] && parts[1] === parts[3] ? (parts[0] === parts[1] ? parts[0] : `${parts[0]} ${parts[1]}`) : parts.join(' ');
    store.setAttrs(node.id, { [key]: next }, `Edit ${prop}`);
  };

  return (
    <div className="select-none font-mono text-[10px]" data-bk-boxmodel>
      <Layer label="margin" values={margin} edit={(i, v) => write('margin', i, v)} className="border-dashed border-orange-300 bg-orange-100/70 dark:bg-orange-500/15" kind="margin">
        <Layer label="border" values={border} className="border-amber-400 bg-amber-100/80 dark:bg-amber-500/15" kind="border">
          <Layer label="padding" values={padding} edit={(i, v) => write('padding', i, v)} className="border-emerald-300 bg-emerald-100/70 dark:bg-emerald-500/15" kind="padding">
            <div className="flex h-9 min-w-24 items-center justify-center rounded-sm border border-sky-300 bg-sky-100 px-2 text-sky-900 dark:bg-sky-500/20 dark:text-sky-200" data-bk-content-size>
              {Math.round(cw * 100) / 100} × {Math.round(ch * 100) / 100}
            </div>
          </Layer>
        </Layer>
      </Layer>
      <p className="mt-1.5 font-sans text-[10.5px] text-muted-foreground">Click a margin or padding value to edit it for {device}.</p>
    </div>
  );
}

function Layer({ label, values, edit, className, children, kind }) {
  const cell = (i) => <SideValue value={values[i]} edit={edit ? (v) => edit(i, v) : null} kind={kind} side={['top', 'right', 'bottom', 'left'][i]} />;
  return (
    <div className={cn('relative rounded-md border px-1 pb-1 pt-0', className)}>
      <span className="absolute left-1.5 top-0.5 text-[9px] text-foreground/50">{label}</span>
      <div className="flex justify-center">{cell(0)}</div>
      <div className="flex items-center gap-1">
        <div className="flex w-9 justify-center">{cell(3)}</div>
        <div className="flex flex-1 justify-center">{children}</div>
        <div className="flex w-9 justify-center">{cell(1)}</div>
      </div>
      <div className="flex justify-center">{cell(2)}</div>
    </div>
  );
}

function SideValue({ value, edit, kind, side }) {
  const [editing, setEditing] = useState(false);
  const ref = useRef(null);
  const shown = value ? Math.round(value * 100) / 100 : '–';
  useEffect(() => {
    if (editing && ref.current) ref.current.select();
  }, [editing]);
  if (editing) {
    const done = (commit) => {
      if (commit) edit(ref.current.value);
      setEditing(false);
    };
    return (
      <input
        ref={ref}
        defaultValue={value ? Math.round(value * 100) / 100 : 0}
        data-bk-box-input={`${kind}-${side}`}
        className="h-4 w-11 rounded border border-ring bg-background text-center font-mono text-[10px] outline-none"
        onBlur={() => done(true)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') done(true);
          if (e.key === 'Escape') done(false);
          e.stopPropagation();
        }}
      />
    );
  }
  return (
    <button
      type="button"
      disabled={!edit}
      data-bk-box={`${kind}-${side}`}
      data-value={value}
      onClick={() => setEditing(true)}
      title={edit ? `Edit ${kind}-${side}` : `${kind}-${side}`}
      className={cn('h-4 min-w-6 rounded px-0.5 leading-4 tabular-nums', edit ? 'hover:bg-background/80 hover:ring-1 hover:ring-ring cursor-pointer' : 'cursor-default')}
    >
      {shown}
    </button>
  );
}

/* ------------------------------------------------------------------------
 * Computed styles.
 * ---------------------------------------------------------------------- */

function Computed({ cs }) {
  const [q, setQ] = useState('');
  const [all, setAll] = useState(false);
  const term = q.trim().toLowerCase();
  const groups = (() => {
    if (term || all) {
      const props = [];
      for (let i = 0; i < cs.length; i++) props.push(cs[i]);
      const list = props.filter((p) => !p.startsWith('--') && (!term || p.includes(term) || cs.getPropertyValue(p).toLowerCase().includes(term))).sort();
      return [['Matching', list]];
    }
    return Object.entries(GROUPS).map(([g, props]) => [g, props.filter((p) => !SKIP.has(cs.getPropertyValue(p)) || p === 'display' || p === 'color' || p === 'font-size')]);
  })();

  return (
    <div className="space-y-2" data-bk-computed>
      <div className="flex items-center gap-2">
        <div className="relative flex-1">
          <Icon name="search" size={12} className="absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Filter properties" className="h-7 pl-7 text-xs" data-bk-computed-filter />
        </div>
        <label className="flex items-center gap-1 text-[11px] text-muted-foreground">
          <input type="checkbox" checked={all} onChange={(e) => setAll(e.target.checked)} /> All
        </label>
      </div>
      {groups.map(([g, props]) =>
        props.length ? (
          <div key={g}>
            <p className="mb-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground/80">{g}</p>
            <table className="w-full table-fixed">
              <tbody>
                {props.slice(0, 400).map((p) => {
                  const val = cs.getPropertyValue(p);
                  return (
                    <tr key={p} className="align-top hover:bg-accent/60" data-prop={p}>
                      <td className="w-[42%] truncate py-px pr-2 font-mono text-[10.5px] text-sky-700 dark:text-sky-400" title={p}>
                        {p}
                      </td>
                      <td className="py-px font-mono text-[10.5px] break-all" title={val}>
                        <Swatch value={/color|^fill|^stroke/.test(p) ? val : null} />
                        {val.length > 120 ? `${val.slice(0, 120)}…` : val}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        ) : null
      )}
    </div>
  );
}

function Variables({ vars, el }) {
  const [tokens, setTokens] = useState(false);
  const cs = el.ownerDocument.defaultView.getComputedStyle(el);
  const list = tokens ? TOKENS.map((n) => ({ name: n, value: cs.getPropertyValue(n).trim() })) : vars;
  return (
    <div className="space-y-1.5" data-bk-vars>
      {list.length ? (
        <table className="w-full table-fixed">
          <tbody>
            {list.map((x) => (
              <tr key={x.name} className="align-top">
                <td className="w-[46%] truncate py-px pr-2 font-mono text-[10.5px] text-violet-700 dark:text-violet-400">{x.name}</td>
                <td className="py-px font-mono text-[10.5px] break-all">
                  <Swatch value={x.value} />
                  {x.value || <span className="text-muted-foreground">unset</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      ) : (
        <p className="text-muted-foreground">No variables in this element’s generated CSS.</p>
      )}
      <button type="button" onClick={() => setTokens(!tokens)} className="text-[11px] text-muted-foreground hover:text-foreground cursor-pointer">
        {tokens ? 'Show variables used here' : 'Show theme tokens'}
      </button>
    </div>
  );
}
