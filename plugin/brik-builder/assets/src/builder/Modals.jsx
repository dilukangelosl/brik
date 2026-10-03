import { useState, useEffect, config } from './wp.js';
import { useStore, setState, getState, closeModal, insertNodes, replaceNode, toast, jumpTo } from './store.js';
import * as T from './tree.js';
import { saveToLibrary, saveSettings, search } from './api.js';
import { reload } from './canvas.js';
import { Icon } from './icons.js';
import { ModuleGrid, useLibrary, insertLibraryItem, insertBundled } from './LeftPanel.jsx';
import { StructurePreview, ColorControl, Control } from './fields.jsx';
import { cn, Dialog, Button, Input, Textarea, Select, Switch, Tabs, Label, IconButton, Empty } from './ui.jsx';

export function Modals() {
  const modal = useStore((s) => s.modal);
  if (!modal) return null;
  const C = MODALS[modal.type];
  return C ? <C {...modal.props} /> : null;
}

const STRUCTURES = ['1', '1/2,1/2', '1/3,1/3,1/3', '1/4,1/4,1/4,1/4', '1/3,2/3', '2/3,1/3', '1/4,3/4', '3/4,1/4', '1/4,1/2,1/4', '1/2,1/4,1/4', '1/4,1/4,1/2', '2/5,3/5', '3/5,2/5', '1/5,1/5,1/5,1/5,1/5', '1/6,1/6,1/6,1/6,1/6,1/6'];

function StructureGrid({ onPick }) {
  return (
    <div className="grid grid-cols-3 gap-2 sm:grid-cols-5">
      {STRUCTURES.map((s) => (
        <StructurePreview key={s} structure={s} onClick={() => onPick(s)} />
      ))}
    </div>
  );
}

function ModulePicker({ parent, index }) {
  const [tab, setTab] = useState('elements');
  const [lib] = useLibrary();
  return (
    <Dialog title="Add element" onClose={closeModal} size="lg">
      <Tabs
        className="mb-4"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'elements', label: 'Elements' },
          { value: 'row', label: 'Row (nested)' },
          { value: 'library', label: 'Library' },
        ]}
      />
      {tab === 'elements' && (
        <ModuleGrid
          draggable={false}
          onPick={(type) => {
            closeModal();
            if (type === 'section') insertNodes(null, getState().tree.length, [T.create('section')], 'Add section');
            else if (type === 'row') insertNodes(parent, index, [T.create('row')], 'Add row');
            else insertNodes(parent, index, [T.create(type)], 'Add element');
          }}
        />
      )}
      {tab === 'row' && (
        <StructureGrid
          onPick={(s) => {
            closeModal();
            insertNodes(parent, index, [T.create('row', { columns: s })], 'Add row');
          }}
        />
      )}
      {tab === 'library' && (
        <LibraryList
          items={lib ? lib.items.filter((i) => i.kind === 'module' || i.kind === 'row') : null}
          onPick={(item) => {
            closeModal();
            insertLibraryItem(item, { parent, index });
          }}
        />
      )}
    </Dialog>
  );
}

function LibraryList({ items, onPick }) {
  if (!items) return <p className="text-sm text-muted-foreground">Loading…</p>;
  if (!items.length) return <Empty icon="bookmark" title="Nothing saved yet" />;
  return (
    <div className="grid grid-cols-2 gap-2">
      {items.map((item) => (
        <button key={item.id} type="button" onClick={() => onPick(item)} className="flex items-center gap-2 rounded-md border border-border px-3 py-2.5 text-left text-sm hover:border-brand hover:bg-brand/5 cursor-pointer">
          <Icon name={item.global ? 'globe' : 'bookmark'} size={14} className={item.global ? 'text-brand' : 'text-muted-foreground'} />
          <span className="truncate">{item.title}</span>
          <span className="ml-auto rounded bg-muted px-1 text-[10px] text-muted-foreground">{item.kind}</span>
        </button>
      ))}
    </div>
  );
}

function StructureModal({ parent, index }) {
  return (
    <Dialog title="Add row" description="Choose a column structure." onClose={closeModal}>
      <StructureGrid
        onPick={(s) => {
          closeModal();
          const row = T.create('row', { columns: s });
          if (parent) insertNodes(parent, index, [row], 'Add row');
          else insertNodes(null, index, [{ ...T.create('section'), children: [row] }], 'Add section');
        }}
      />
    </Dialog>
  );
}

function AddSection({ index }) {
  const [tab, setTab] = useState('new');
  const [lib] = useLibrary();
  const layouts = lib ? lib.bundled : null;
  const [cat, setCat] = useState('all');
  const cats = layouts ? ['all', ...new Set(layouts.map((l) => l.category))] : [];
  return (
    <Dialog title="Add section" onClose={closeModal} size="lg">
      <Tabs
        className="mb-4"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'new', label: 'New section' },
          { value: 'layouts', label: 'Layouts' },
          { value: 'library', label: 'Library' },
        ]}
      />
      {tab === 'new' && (
        <StructureGrid
          onPick={(s) => {
            closeModal();
            const section = T.create('section');
            section.children = [T.create('row', { columns: s })];
            insertNodes(null, index, [section], 'Add section');
          }}
        />
      )}
      {tab === 'layouts' && (
        <div className="space-y-3">
          <div className="flex flex-wrap gap-1">
            {cats.map((c) => (
              <Button key={c} size="xs" variant="outline" active={cat === c} onClick={() => setCat(c)}>
                {c}
              </Button>
            ))}
          </div>
          {!layouts && <p className="text-sm text-muted-foreground">Loading…</p>}
          <div className="grid grid-cols-2 gap-2">
            {(layouts || [])
              .filter((l) => cat === 'all' || l.category === cat)
              .map((l) => (
                <button
                  key={l.slug}
                  type="button"
                  className="flex items-center gap-2 rounded-md border border-border px-3 py-3 text-left text-sm hover:border-brand hover:bg-brand/5 cursor-pointer"
                  onClick={() => {
                    closeModal();
                    insertBundled(l, { parent: null, index });
                  }}
                >
                  <Icon name="layout-template" size={16} className="text-muted-foreground" />
                  <span>
                    <span className="block font-medium">{l.title}</span>
                    <span className="text-xs text-muted-foreground">{l.category}</span>
                  </span>
                </button>
              ))}
          </div>
        </div>
      )}
      {tab === 'library' && (
        <LibraryList
          items={lib ? lib.items.filter((i) => i.kind === 'section' || i.kind === 'layout') : null}
          onPick={(item) => {
            closeModal();
            insertLibraryItem(item, { parent: null, index });
          }}
        />
      )}
    </Dialog>
  );
}

function SaveLibrary({ id }) {
  const node = T.find(getState().tree, id);
  const schema = getState().schema;
  const kind = node.type === 'section' ? 'section' : node.type === 'row' ? 'row' : node.type === 'column' ? 'row' : 'module';
  const [title, setTitle] = useState(T.label(node, schema));
  const [global, setGlobal] = useState(false);
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      const tree = node.type === 'column' ? [{ ...T.create('row'), children: [node] }] : [node];
      const item = await saveToLibrary(title, kind, tree, global);
      if (global) replaceNode(id, { id: T.uid(), type: 'global', attrs: { ref: item.id } }, 'Make global');
      toast(global ? 'Saved as global element' : 'Saved to library', 'success');
      closeModal();
    } catch (e) {
      toast(e.message, 'error');
      setBusy(false);
    }
  };

  return (
    <Dialog
      title="Save to library"
      onClose={closeModal}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button onClick={submit} disabled={busy || !title.trim()}>
            Save
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <div className="space-y-1.5">
          <Label>Name</Label>
          <Input autoFocus value={title} onChange={(e) => setTitle(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && submit()} />
        </div>
        <label className="flex items-start justify-between gap-4">
          <span>
            <span className="block text-sm font-medium">Global element</span>
            <span className="text-xs text-muted-foreground">Edits to a global element update every page that uses it.</span>
          </span>
          <Switch checked={global} onChange={setGlobal} label="Global" />
        </label>
      </div>
    </Dialog>
  );
}

/* ------------------------------------------------------------------------
 * Page / template settings.
 * ---------------------------------------------------------------------- */

function PageSettings() {
  const s = useStore((st) => st);
  const page = s.page || {};
  const setPage = (patch) => setState({ page: { ...page, ...patch }, dirty: true, change: { full: true, seq: Math.random() } });
  const isTemplate = !!s.area;

  return (
    <Dialog title={isTemplate ? 'Template settings' : `${config.typeLabel} settings`} onClose={closeModal} size="lg" footer={<Button onClick={closeModal}>Done</Button>}>
      <div className="space-y-5">
        <div className="space-y-1.5">
          <Label>Title</Label>
          <Input value={s.title} onChange={(e) => setState({ title: e.target.value, dirty: true })} />
        </div>
        {isTemplate && (
          <>
            <div className="space-y-1.5">
              <Label>Template area</Label>
              <Select
                value={s.area}
                onChange={(area) => setState({ area, dirty: true })}
                options={[
                  { value: 'header', label: 'Header' },
                  { value: 'body', label: 'Body' },
                  { value: 'footer', label: 'Footer' },
                ]}
              />
            </div>
            <Conditions />
          </>
        )}
        {s.post.library && s.post.library.kind === 'loop' && <LoopPreview />}
        {!isTemplate && !s.post.library && (
          <div className="grid grid-cols-2 gap-4">
            <label className="flex items-center justify-between gap-3 rounded-md border border-border p-3">
              <span className="text-sm">Dark color scheme</span>
              <Switch checked={!!page.dark} onChange={(dark) => setPage({ dark })} label="Dark" />
            </label>
            <div className="space-y-1.5">
              <Label>Body classes</Label>
              <Input value={page.body_class || ''} onChange={(e) => setPage({ body_class: e.target.value })} placeholder="landing-page" />
            </div>
          </div>
        )}
        <div className="space-y-1.5">
          <Label>Custom CSS for this {isTemplate ? 'template' : 'page'}</Label>
          <Textarea rows={8} className="font-mono text-xs" value={page.custom_css || ''} onChange={(e) => setPage({ custom_css: e.target.value })} placeholder=".brik-heading { }" spellCheck={false} />
        </div>
        <ImportExport />
      </div>
    </Dialog>
  );
}

/** Which post a loop item is previewed with in the canvas. */
function LoopPreview() {
  const post = useStore((st) => st.post);
  const types = useStore((st) => (st.schema ? st.schema.post_types : {}));
  const lib = post.library || {};
  const [type, setType] = useState(lib.post_type || 'post');
  const [q, setQ] = useState('');
  const [results, setResults] = useState([]);
  const current = lib.preview_post;

  useEffect(() => {
    const t = setTimeout(() => search({ q, post_type: type }).then(setResults).catch(() => setResults([])), 200);
    return () => clearTimeout(t);
  }, [q, type]);

  const choose = async (postId) => {
    const res = await window.wp.apiFetch({ path: `/brik/v1/library/${post.id}/preview`, method: 'POST', data: { post_id: postId, post_type: type } });
    setState({ post: { ...post, library: { ...lib, post_type: res.post_type, preview_post: res.preview } } });
    toast('Preview updated');
    reload();
  };

  return (
    <div className="space-y-2 rounded-md border border-border p-3">
      <div>
        <p className="text-sm font-medium">Preview with</p>
        <p className="text-xs text-muted-foreground">Dynamic content in this loop item shows data from this post while you design it.</p>
      </div>
      <div className="grid grid-cols-[160px_1fr] gap-2">
        <Select value={type} onChange={setType} options={Object.entries(types).map(([v, l]) => ({ value: v, label: l }))} />
        <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search posts…" />
      </div>
      {current && (
        <p className="text-xs">
          Currently: <strong>{current.title || `#${current.id}`}</strong>
        </p>
      )}
      <div className="max-h-40 space-y-0.5 overflow-y-auto">
        {results.map((r) => (
          <button key={r.id} type="button" onClick={() => choose(r.id)} className={cn('flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer', current && current.id === r.id && 'bg-accent')}>
            <span className="truncate">{r.title || '(no title)'}</span>
            {current && current.id === r.id && <Icon name="check" size={14} />}
          </button>
        ))}
      </div>
    </div>
  );
}

function ImportExport() {
  const exportJson = () => {
    const s = getState();
    const blob = new Blob([JSON.stringify({ brik: config.version, title: s.title, tree: s.tree, page: s.page }, null, 2)], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${(s.title || 'brik-layout').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.json`;
    a.click();
  };
  const importJson = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    file.text().then((text) => {
      try {
        const data = JSON.parse(text);
        const tree = Array.isArray(data) ? data : data.tree;
        if (!Array.isArray(tree)) throw new Error('No layout found in file');
        const replace = window.confirm('Replace the current content? Choose Cancel to add the layout at the end.');
        const nodes = T.wrapForRoot(T.cloneAll(tree));
        const s = getState();
        if (replace) {
          setState({ tree: nodes, past: [...s.past, { tree: s.tree, label: 'Import' }], future: [], dirty: true, change: { full: true, seq: Math.random() } });
        } else {
          insertNodes(null, s.tree.length, nodes, 'Import');
        }
        toast('Layout imported', 'success');
        closeModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    });
  };
  return (
    <div className="flex items-center justify-between rounded-md border border-border p-3">
      <div>
        <p className="text-sm font-medium">Import / export</p>
        <p className="text-xs text-muted-foreground">Move layouts between sites as JSON.</p>
      </div>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" icon="download" onClick={exportJson}>
          Export
        </Button>
        <label className="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-input px-3 text-sm font-medium shadow-xs hover:bg-accent">
          <Icon name="upload" size={16} />
          Import
          <input type="file" accept="application/json,.json" className="hidden" onChange={importJson} />
        </label>
      </div>
    </div>
  );
}

const NEEDS = {
  singular: 'post_type',
  archive: 'post_type',
  post: 'ids',
  term: 'terms',
  in_term: 'terms',
  author: 'authors',
};

function Conditions() {
  const conditions = useStore((s) => s.conditions);
  const schema = useStore((s) => s.schema);
  const set = (list) => setState({ conditions: list, dirty: true });
  const update = (i, patch) => set(conditions.map((c, j) => (j === i ? { ...c, ...patch } : c)));

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <div>
          <Label>Display conditions</Label>
          <p className="text-xs text-muted-foreground">Where this template is used. Exclusions win; the most specific template is chosen.</p>
        </div>
        <Button size="sm" variant="outline" icon="plus" onClick={() => set([...conditions, { type: 'include', rule: 'entire_site' }])}>
          Add rule
        </Button>
      </div>
      {!conditions.length && <p className="rounded-md border border-dashed border-border p-3 text-center text-xs text-muted-foreground">No rules: this template is not used anywhere yet.</p>}
      {conditions.map((c, i) => (
        <div key={i} className="flex flex-wrap items-start gap-2 rounded-md border border-border p-2">
          <Select
            className="w-28"
            value={c.type}
            onChange={(type) => update(i, { type })}
            options={[
              { value: 'include', label: 'Include' },
              { value: 'exclude', label: 'Exclude' },
            ]}
          />
          <Select className="w-52" value={c.rule} onChange={(rule) => update(i, { rule, ids: [], post_type: '', taxonomy: '' })} options={Object.entries(schema.conditions).map(([v, l]) => ({ value: v, label: l }))} />
          {NEEDS[c.rule] === 'post_type' && <Select className="w-40" value={c.post_type || ''} onChange={(post_type) => update(i, { post_type })} placeholder="Any type" options={Object.entries(schema.post_types).map(([v, l]) => ({ value: v, label: l }))} />}
          {NEEDS[c.rule] === 'terms' && <Select className="w-40" value={c.taxonomy || ''} onChange={(taxonomy) => update(i, { taxonomy, ids: [] })} placeholder="Taxonomy" options={Object.entries(schema.taxonomies).map(([v, l]) => ({ value: v, label: l }))} />}
          {(NEEDS[c.rule] === 'ids' || (NEEDS[c.rule] === 'terms' && c.taxonomy)) && <IdPicker rule={c} onChange={(ids) => update(i, { ids })} />}
          <IconButton icon="trash-2" label="Remove rule" className="ml-auto" onClick={() => set(conditions.filter((_, j) => j !== i))} />
        </div>
      ))}
    </div>
  );
}

function IdPicker({ rule, onChange }) {
  const ids = rule.ids || [];
  const [q, setQ] = useState('');
  const [results, setResults] = useState([]);
  const [labels, setLabels] = useState({});
  const term = rule.rule === 'term' || rule.rule === 'in_term';

  useEffect(() => {
    if (!q) {
      setResults([]);
      return;
    }
    const t = setTimeout(() => {
      search(term ? { what: 'term', taxonomy: rule.taxonomy, q } : { q }).then(setResults);
    }, 250);
    return () => clearTimeout(t);
  }, [q]);

  return (
    <div className="relative min-w-52 flex-1 space-y-1.5">
      <div className="flex flex-wrap gap-1">
        {ids.map((id) => (
          <span key={id} className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-xs">
            {labels[id] || `#${id}`}
            <button type="button" className="cursor-pointer" onClick={() => onChange(ids.filter((x) => x !== id))}>
              <Icon name="x" size={10} />
            </button>
          </span>
        ))}
      </div>
      <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder={term ? 'Search terms…' : 'Search posts and pages…'} />
      {results.length > 0 && (
        <div className="absolute left-0 right-0 z-10 max-h-48 overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-lg">
          {results.map((r) => (
            <button
              key={r.id}
              type="button"
              className="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
              onClick={() => {
                if (!ids.includes(r.id)) onChange([...ids, r.id]);
                setLabels({ ...labels, [r.id]: r.title });
                setQ('');
              }}
            >
              {r.title} {r.type && <span className="text-xs text-muted-foreground">{r.type}</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Global design settings.
 * ---------------------------------------------------------------------- */

const TOKEN_LABELS = ['background', 'foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'card', 'card-foreground', 'border', 'input', 'ring', 'destructive'];

function DesignSettings() {
  const settings = useStore((s) => s.settings);
  const fonts = useStore((s) => (s.schema ? s.schema.fonts : []));
  const [draft, setDraft] = useState(() => structuredClone(settings));
  const [tab, setTab] = useState('theme');
  const [busy, setBusy] = useState(false);
  const set = (patch) => setDraft({ ...draft, ...patch });

  const save = async () => {
    setBusy(true);
    try {
      const { base, accent, radius, font_body, font_heading, container, colors, tokens, custom_css } = draft;
      await saveSettings({ base, accent, radius, font_body, font_heading, container, colors, tokens, custom_css });
      toast('Design settings saved', 'success');
      closeModal();
      reload();
    } catch (e) {
      toast(e.message, 'error');
      setBusy(false);
    }
  };

  const resolved = settings.resolved || { light: {}, dark: {} };
  const tokenValue = (mode, t) => (draft.tokens && draft.tokens[mode] && draft.tokens[mode][t]) || '';
  const setToken = (mode, t, v) => {
    const tokens = { light: { ...(draft.tokens.light || {}) }, dark: { ...(draft.tokens.dark || {}) } };
    if (v) tokens[mode][t] = v;
    else delete tokens[mode][t];
    set({ tokens });
  };

  return (
    <Dialog
      title="Design system"
      description="Site-wide colors, typography and shape. Every element uses these tokens."
      onClose={closeModal}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button onClick={save} disabled={busy || !config.canManage}>
            Save
          </Button>
        </>
      }
    >
      <Tabs
        className="mb-5"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'theme', label: 'Theme' },
          { value: 'colors', label: 'Global colors' },
          { value: 'tokens', label: 'Tokens' },
          { value: 'css', label: 'Custom CSS' },
        ]}
      />
      {tab === 'theme' && (
        <div className="space-y-5">
          <div className="space-y-2">
            <Label>Base color</Label>
            <div className="grid grid-cols-4 gap-2">
              {Object.entries(settings.bases).map(([k, label]) => (
                <Button key={k} variant="outline" active={draft.base === k} onClick={() => set({ base: k })}>
                  {label}
                </Button>
              ))}
            </div>
          </div>
          <div className="space-y-2">
            <Label>Accent</Label>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" active={!draft.accent} onClick={() => set({ accent: '' })}>
                None
              </Button>
              {Object.entries(settings.accents).map(([k, label]) => (
                <Button key={k} variant="outline" size="sm" active={draft.accent === k} onClick={() => set({ accent: k })}>
                  <span className={cn('size-3 rounded-full', `bk-accent-${k}`)} />
                  {label}
                </Button>
              ))}
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Body font</Label>
              <Select value={draft.font_body || ''} onChange={(font_body) => set({ font_body })} placeholder="System UI" options={fonts.map((f) => ({ value: f, label: f }))} />
            </div>
            <div className="space-y-1.5">
              <Label>Heading font</Label>
              <Select value={draft.font_heading || ''} onChange={(font_heading) => set({ font_heading })} placeholder="Same as body" options={fonts.map((f) => ({ value: f, label: f }))} />
            </div>
            <div className="space-y-1.5">
              <Label>Corner radius</Label>
              <div className="flex gap-1">
                {['0rem', '0.3rem', '0.5rem', '0.625rem', '0.75rem', '1rem'].map((r) => (
                  <Button key={r} size="sm" variant="outline" active={draft.radius === r} onClick={() => set({ radius: r })}>
                    {r.replace('rem', '')}
                  </Button>
                ))}
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Content width</Label>
              <Input value={draft.container || ''} onChange={(e) => set({ container: e.target.value })} placeholder="1200px" />
            </div>
          </div>
        </div>
      )}
      {tab === 'colors' && (
        <div className="space-y-2">
          {(draft.colors || []).map((c, i) => (
            <div key={c.id || i} className="grid grid-cols-[1fr_1.4fr_auto] items-center gap-2">
              <Input
                value={c.name}
                placeholder="Name"
                onChange={(e) => set({ colors: draft.colors.map((x, j) => (j === i ? { ...x, name: e.target.value } : x)) })}
              />
              <ColorControl value={c.value} onChange={(value) => set({ colors: draft.colors.map((x, j) => (j === i ? { ...x, value: value || '' } : x)) })} />
              <IconButton icon="trash-2" label="Remove color" onClick={() => set({ colors: draft.colors.filter((_, j) => j !== i) })} />
            </div>
          ))}
          <Button size="sm" variant="outline" icon="plus" onClick={() => set({ colors: [...(draft.colors || []), { id: T.uid(), name: 'Brand', value: '#6366f1' }] })}>
            Add color
          </Button>
          <p className="text-xs text-muted-foreground">Global colors appear in every color picker. Changing one updates it everywhere.</p>
        </div>
      )}
      {tab === 'tokens' && (
        <div className="space-y-2">
          <p className="text-xs text-muted-foreground">Override individual design tokens. Leave empty to use the base and accent.</p>
          <div className="grid grid-cols-[140px_1fr_1fr] gap-2 text-xs font-medium text-muted-foreground">
            <span>Token</span>
            <span>Light</span>
            <span>Dark</span>
          </div>
          {TOKEN_LABELS.map((t) => (
            <div key={t} className="grid grid-cols-[140px_1fr_1fr] items-center gap-2">
              <code className="text-xs">--{t}</code>
              <ColorControl value={tokenValue('light', t)} placeholder={resolved.light[t]} onChange={(v) => setToken('light', t, v)} />
              <ColorControl value={tokenValue('dark', t)} placeholder={resolved.dark[t]} onChange={(v) => setToken('dark', t, v)} />
            </div>
          ))}
        </div>
      )}
      {tab === 'css' && <Textarea rows={14} className="font-mono text-xs" value={draft.custom_css || ''} onChange={(e) => set({ custom_css: e.target.value })} spellCheck={false} placeholder="/* Applies to the whole site */" />}
    </Dialog>
  );
}

function History() {
  const past = useStore((s) => s.past);
  const future = useStore((s) => s.future);
  return (
    <Dialog title="History" description="Jump back to an earlier state. Undone steps can be redone." onClose={closeModal} size="sm">
      <div className="space-y-0.5">
        {[...future].reverse().map((step, i) => (
          <div key={`f${i}`} className="rounded px-2 py-1.5 text-sm text-muted-foreground line-through">
            {step.label}
          </div>
        ))}
        <div className="rounded bg-brand/10 px-2 py-1.5 text-sm font-medium">Current</div>
        {[...past].reverse().map((step, i) => (
          <button
            key={i}
            type="button"
            className="block w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
            onClick={() => {
              jumpTo(past.length - 1 - i);
              closeModal();
            }}
          >
            {step.label}
          </button>
        ))}
        {!past.length && !future.length && <p className="text-sm text-muted-foreground">No changes yet.</p>}
      </div>
    </Dialog>
  );
}

function Restore({ backup }) {
  return (
    <Dialog
      title="Restore unsaved changes?"
      description={`There are unsaved changes from ${new Date(backup.time).toLocaleString()}.`}
      onClose={closeModal}
      size="sm"
      footer={
        <>
          <Button
            variant="outline"
            onClick={() => {
              try {
                localStorage.removeItem(`brik-backup-${config.post.id}`);
              } catch (e) {}
              closeModal();
            }}
          >
            Discard
          </Button>
          <Button
            onClick={() => {
              setState({ tree: backup.tree, page: backup.page || getState().page, dirty: true, change: { full: true, seq: Math.random() } });
              closeModal();
            }}
          >
            Restore
          </Button>
        </>
      }
    />
  );
}

const MODALS = {
  modules: ModulePicker,
  structure: StructureModal,
  'add-section': AddSection,
  'save-library': SaveLibrary,
  page: PageSettings,
  design: DesignSettings,
  history: History,
  restore: Restore,
};
