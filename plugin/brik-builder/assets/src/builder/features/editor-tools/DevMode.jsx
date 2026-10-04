// Designer / Developer modes and the developer views (HTML, CSS, JSON, page info, hooks).
import { useState, useEffect, useMemo } from '../../wp.js';
import * as store from '../../store.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { cn, Button, Dialog, Tabs, Input, Textarea } from '../../ui.jsx';
import { nodeEl, frameDoc, copyText, useCanvasVersion } from './util.js';
import { HtmlCode, CssCode, JsonCode, prettyHtml } from './Code.jsx';
import { generatedRules, rulesToCss } from './Inspector.jsx';
import { HOOKS } from './hooks.js';

const KEY = 'brik-editor-mode';

export function readMode() {
  try {
    return localStorage.getItem(KEY) === 'designer' ? 'designer' : 'developer';
  } catch (e) {
    return 'developer';
  }
}

export function setMode(mode) {
  try {
    localStorage.setItem(KEY, mode);
  } catch (e) {}
  applyMode(mode);
  const s = store.getState();
  store.setState({ bkMode: mode, ...(mode === 'designer' && s.left === 'inspect' ? { left: null } : {}) });
}

export function applyMode(mode) {
  document.body.classList.toggle('bk-designer', mode === 'designer');
  document.body.classList.toggle('bk-developer', mode !== 'designer');
}

/*
 * Core panels can't be removed, so designer mode hides developer-only parts of them with CSS.
 * Those parts carry no hooks of their own; they are tagged here by their visible labels.
 */
export function startTagging() {
  const tag = () => {
    const schema = store.getState().schema;
    const labels = new Set(schema ? [schema.groups.custom_css, schema.groups.attributes].filter(Boolean) : ['Custom CSS', 'Attributes']);
    document.querySelectorAll('aside .border-b > button[aria-expanded]').forEach((b) => {
      const text = (b.querySelector('span') || b).textContent.trim();
      const dev = labels.has(text);
      const wrap = b.parentElement;
      if (dev && !wrap.hasAttribute('data-brik-dev')) wrap.setAttribute('data-brik-dev', 'section');
    });
    document.querySelectorAll('aside [role="tab"]').forEach((t) => {
      if ((t.getAttribute('aria-label') || t.getAttribute('title') || t.textContent).trim() === 'Inspect' && !t.hasAttribute('data-brik-dev')) t.setAttribute('data-brik-dev', 'tab');
    });
    document.querySelectorAll('.bk-field[data-type="code"]').forEach((f) => {
      if (!f.hasAttribute('data-brik-dev')) f.setAttribute('data-brik-dev', 'field');
    });
  };
  let queued = false;
  new MutationObserver(() => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
      queued = false;
      tag();
    });
  }).observe(document.getElementById('brik-app'), { childList: true, subtree: true });
  tag();
}

/* ------------------------------------------------------------------------
 * Top bar toggle.
 * ---------------------------------------------------------------------- */

export function ModeToggle() {
  const mode = store.useStore((s) => s.bkMode || readMode());
  return (
    <div className="flex items-center gap-1">
      <div className="inline-flex rounded-md bg-muted p-0.5" role="radiogroup" aria-label="Editor mode" data-bk-mode={mode}>
        {[
          ['designer', 'palette', 'Designer mode — the essentials'],
          ['developer', 'code-xml', 'Developer mode — inspector, code, custom CSS'],
        ].map(([m, icon, label]) => (
          <button key={m} type="button" role="radio" aria-checked={mode === m} title={label} data-mode={m} onClick={() => setMode(m)} className={cn('inline-flex h-6 w-7 items-center justify-center rounded cursor-pointer', mode === m ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground')}>
            <Icon name={icon} size={14} />
          </button>
        ))}
      </div>
      <span data-brik-dev="devtools">
        <Button size="icon-sm" variant="ghost" icon="braces" title="Developer tools: page info, hooks, dynamic tags" onClick={() => store.openModal('bk-devtools')} data-bk-devtools-btn />
      </span>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Code views for the selected element.
 * ---------------------------------------------------------------------- */

export function CodeButton({ node }) {
  return (
    <span data-brik-dev="code">
      <Button size="xs" variant="outline" icon="code-xml" title="HTML, CSS and JSON of this element" onClick={() => store.openModal('bk-code', { id: node.id })} data-bk-code-btn>
        Code
      </Button>
    </span>
  );
}

export function CodeModal({ id, tab: initial = 'html' }) {
  const [tab, setTab] = useState(initial);
  const tree = store.useStore((s) => s.tree);
  const v = useCanvasVersion();
  const node = T.find(tree, id);
  const el = nodeEl(id);
  if (!node) return null;
  const html = el ? prettyHtml(el.outerHTML.replace(/\s+class="([^"]*)"/g, (m, c) => ` class="${c.replace(/\s*bk-force-\w+/g, '').trim()}"`)) : '';
  const rules = el ? generatedRules(el, id) : [];
  const css = rulesToCss(rules);

  return (
    <Dialog title={`${store.getState().schema.byType[node.type]?.title || node.type} · ${id}`} description="Read the markup and styles Brik produces, or edit the element as JSON." onClose={store.closeModal} size="lg">
      <div data-bk-code-modal>
        <div className="mb-3 flex items-center justify-between gap-2">
          <Tabs
            value={tab}
            onChange={setTab}
            tabs={[
              { value: 'html', label: 'HTML', icon: 'code-xml' },
              { value: 'css', label: 'CSS', icon: 'paintbrush' },
              { value: 'json', label: 'JSON', icon: 'braces' },
              { value: 'meta', label: 'Classes & vars', icon: 'hash' },
            ]}
          />
          {tab !== 'json' && tab !== 'meta' && (
            <Button size="xs" variant="outline" icon="clipboard-copy" onClick={() => copyText(tab === 'html' ? (el ? el.outerHTML : '') : css)}>
              Copy
            </Button>
          )}
        </div>
        {tab === 'html' && (el ? <HtmlCode code={html} data-bk-html /> : <p className="text-sm text-muted-foreground">Not rendered in the canvas.</p>)}
        {tab === 'css' && (css ? <CssCode code={css} data-bk-css /> : <p className="text-sm text-muted-foreground">No generated CSS for this element.</p>)}
        {tab === 'json' && <JsonEditor node={node} />}
        {tab === 'meta' && el && <ClassesVars el={el} rules={rules} />}
      </div>
    </Dialog>
  );
}

function JsonEditor({ node }) {
  const initial = useMemo(() => JSON.stringify(node.attrs || {}, null, 2), [node]);
  const [text, setText] = useState(initial);
  const [editing, setEditing] = useState(false);
  useEffect(() => setText(initial), [initial]);
  let error = null;
  let parsed = null;
  try {
    parsed = JSON.parse(text);
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) error = 'Attributes must be a JSON object.';
  } catch (e) {
    error = e.message;
  }
  const changed = text !== initial;
  const apply = () => {
    store.replaceNode(node.id, { ...node, attrs: parsed }, 'Edit JSON');
    store.toast('Attributes applied', 'success');
    setEditing(false);
  };
  return (
    <div className="space-y-2" data-bk-json>
      <div className="flex items-center justify-between text-xs text-muted-foreground">
        <span>
          <code className="font-mono">{node.type}</code> attributes · {Object.keys(node.attrs || {}).length} keys{node.children ? ` · ${node.children.length} children (not editable here)` : ''}
        </span>
        <span className="flex gap-1">
          <Button size="xs" variant="ghost" icon="clipboard-copy" onClick={() => copyText(JSON.stringify({ type: node.type, attrs: node.attrs || {}, children: node.children }, null, 2))}>
            Copy node
          </Button>
          {!editing && (
            <Button size="xs" variant="outline" icon="pencil" onClick={() => setEditing(true)} data-bk-json-edit>
              Edit
            </Button>
          )}
        </span>
      </div>
      {editing ? (
        <>
          <Textarea value={text} onChange={(e) => setText(e.target.value)} spellCheck={false} className="font-mono text-[11px] leading-[1.55]" style={{ height: "46vh" }} data-bk-json-input onKeyDown={(e) => e.stopPropagation()} />
          <div className="flex items-center justify-between gap-2">
            <span className={cn('flex items-center gap-1.5 text-xs', error ? 'text-destructive' : 'text-emerald-600')} data-bk-json-status>
              <Icon name={error ? 'circle-alert' : 'circle-check'} size={12} />
              {error || (changed ? 'Valid JSON' : 'No changes')}
            </span>
            <span className="flex gap-2">
              <Button size="sm" variant="ghost" onClick={() => (setText(initial), setEditing(false))}>
                Cancel
              </Button>
              <Button size="sm" disabled={!!error || !changed} onClick={apply} data-bk-json-apply>
                Apply
              </Button>
            </span>
          </div>
        </>
      ) : (
        <JsonCode code={initial} />
      )}
    </div>
  );
}

function ClassesVars({ el, rules }) {
  const cs = el.ownerDocument.defaultView.getComputedStyle(el);
  const vars = new Set();
  rules.forEach((r) => r.decls.forEach((d) => [...d.matchAll(/var\(\s*(--[\w-]+)/g)].forEach((m) => vars.add(m[1]))));
  return (
    <div className="space-y-4 text-xs">
      <div>
        <p className="mb-1.5 font-medium">Classes</p>
        <div className="flex flex-wrap gap-1">
          {[...el.classList]
            .filter((c) => !c.startsWith('bk-force'))
            .map((c) => (
              <button key={c} type="button" onClick={() => copyText(`.${c}`)} className="rounded border border-border px-1.5 py-0.5 font-mono text-[11px] hover:bg-accent cursor-pointer">
                .{c}
              </button>
            ))}
        </div>
      </div>
      <div>
        <p className="mb-1.5 font-medium">CSS variables in its rules</p>
        {vars.size ? (
          <table className="w-full">
            <tbody>
              {[...vars].map((n) => (
                <tr key={n}>
                  <td className="py-0.5 pr-3 font-mono text-[11px] text-violet-700">{n}</td>
                  <td className="py-0.5 font-mono text-[11px]">{cs.getPropertyValue(n).trim() || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : (
          <p className="text-muted-foreground">None.</p>
        )}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Page-level developer tools.
 * ---------------------------------------------------------------------- */

export function DevToolsModal({ tab: initial = 'page' }) {
  const [tab, setTab] = useState(initial);
  return (
    <Dialog title="Developer tools" description="Page statistics, PHP hooks and dynamic data tags." onClose={store.closeModal} size="lg">
      <div data-bk-devtools>
        <Tabs
          className="mb-4"
          value={tab}
          onChange={setTab}
          tabs={[
            { value: 'page', label: 'Page', icon: 'gauge' },
            { value: 'hooks', label: 'Hooks', icon: 'webhook' },
            { value: 'tags', label: 'Dynamic tags', icon: 'tags' },
          ]}
        />
        {tab === 'page' && <PageInfo />}
        {tab === 'hooks' && <Hooks />}
        {tab === 'tags' && <Tags />}
      </div>
    </Dialog>
  );
}

function PageInfo() {
  useCanvasVersion();
  const tree = store.useStore((s) => s.tree);
  const schema = store.useStore((s) => s.schema);
  const doc = frameDoc();
  const root = doc && doc.querySelector('[data-brik-root]');
  const counts = {};
  let nodes = 0;
  T.walk(tree, (n) => {
    nodes++;
    counts[n.type] = (counts[n.type] || 0) + 1;
  });
  const gen = doc && doc.querySelector('style#brik-css');
  const css = gen ? gen.textContent.length : 0;
  const pageDom = doc ? doc.body.querySelectorAll('*').length - (doc.getElementById('brik-ui') ? doc.getElementById('brik-ui').querySelectorAll('*').length + 1 : 0) : 0;
  const brikDom = root ? root.querySelectorAll('*').length : 0;
  const stats = [
    ['DOM elements (page)', pageDom, pageDom > 1500 ? 'Large: over 1,500 elements slows rendering' : ''],
    ['DOM elements (Brik content)', brikDom],
    ['Brik elements', nodes],
    ['Generated CSS', `${(css / 1024).toFixed(1)} KB`],
    ['Max nesting depth', depth(tree)],
  ];
  return (
    <div className="space-y-4" data-bk-pageinfo>
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
        {stats.map(([label, value, warn]) => (
          <div key={label} className="rounded-lg border border-border p-3">
            <p className="text-[11px] text-muted-foreground">{label}</p>
            <p className={cn('mt-0.5 font-mono text-lg font-semibold tabular-nums', warn && 'text-amber-600')} data-stat={label}>
              {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
            {warn && <p className="mt-0.5 text-[10.5px] text-amber-600">{warn}</p>}
          </div>
        ))}
      </div>
      <div>
        <p className="mb-1.5 text-xs font-medium">Elements by type</p>
        <div className="flex flex-wrap gap-1">
          {Object.entries(counts)
            .sort((a, b) => b[1] - a[1])
            .map(([type, n]) => (
              <span key={type} className="rounded-md border border-border px-1.5 py-0.5 text-[11px]">
                {(schema && schema.byType[type] && schema.byType[type].title) || type} <span className="font-mono text-muted-foreground">{n}</span>
              </span>
            ))}
        </div>
      </div>
    </div>
  );
}

function depth(nodes, d = 0) {
  return nodes.reduce((m, n) => Math.max(m, depth(n.children || [], d + 1)), d);
}

function Hooks() {
  const [q, setQ] = useState('');
  const term = q.trim().toLowerCase();
  const list = HOOKS.filter((hk) => !term || hk.name.includes(term) || hk.desc.toLowerCase().includes(term));
  return (
    <div className="space-y-2" data-bk-hooks>
      <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Filter hooks" className="h-8" />
      <div className="max-h-[50vh] divide-y divide-border overflow-y-auto rounded-lg border border-border">
        {list.map((hk) => (
          <div key={hk.name} className="flex items-start gap-3 px-3 py-2">
            <span className={cn('mt-0.5 w-12 shrink-0 rounded px-1 py-px text-center text-[10px] font-medium', hk.type === 'action' ? 'bg-sky-500/10 text-sky-700' : 'bg-violet-500/10 text-violet-700')}>{hk.type}</span>
            <div className="min-w-0 flex-1">
              <button type="button" className="font-mono text-xs font-medium hover:underline cursor-pointer" title="Copy example" onClick={() => copyText(hk.type === 'action' ? `add_action( '${hk.name}', function (${hk.args ? ` ${hk.args} ` : ''}) {\n\t// …\n}${hk.args.includes(',') ? `, 10, ${hk.args.split(',').length}` : ''} );` : `add_filter( '${hk.name}', function (${hk.args ? ` ${hk.args} ` : ''}) {\n\treturn ${hk.args.split(',')[0] || '$value'};\n}${hk.args.includes(',') ? `, 10, ${hk.args.split(',').length}` : ''} );`)}>
                {hk.name}
              </button>
              {hk.args && <span className="ml-1.5 font-mono text-[11px] text-muted-foreground">({hk.args})</span>}
              <p className="text-xs text-muted-foreground">{hk.desc}</p>
            </div>
          </div>
        ))}
      </div>
      <p className="text-[11px] text-muted-foreground">Click a hook name to copy a snippet.</p>
    </div>
  );
}

function Tags() {
  const schema = store.useStore((s) => s.schema);
  const tags = (schema && schema.tags) || [];
  const list = Array.isArray(tags) ? tags : Object.entries(tags).map(([tag, label]) => ({ tag, label }));
  return (
    <div className="max-h-[56vh] divide-y divide-border overflow-y-auto rounded-lg border border-border" data-bk-tags>
      {list.map((t, i) => {
        const tag = typeof t === 'string' ? t : t.tag || t.name || t.value;
        const label = typeof t === 'string' ? '' : t.label || t.title || t.description || '';
        const code = String(tag).startsWith('{') ? tag : `{${tag}}`;
        return (
          <div key={i} className="flex items-center justify-between gap-3 px-3 py-1.5">
            <button type="button" className="font-mono text-xs hover:underline cursor-pointer" onClick={() => copyText(code)}>
              {code}
            </button>
            <span className="truncate text-xs text-muted-foreground">{label}</span>
          </div>
        );
      })}
    </div>
  );
}
