// Components: save as component, instance overrides panel, detach, and the master's override policy.
import { useState, useMemo, api, config } from '../../wp.js';
import { useStore, getState, setState, replaceNode, setAttr, commit, closeModal, toast } from '../../store.js';
import * as T from '../../tree.js';
import { Icon } from '../../icons.js';
import { Control } from '../../fields.jsx';
import { cn, Dialog, Button, IconButton, Input, Label } from '../../ui.jsx';
import { useComponents, loadComponents, useMaster, loadMaster, builderUrl, editingComponent, loadUsage } from './data.js';

const STRUCTURE = ['section', 'row', 'column', 'global'];

/** Default override rule, mirrored from Components::allowed() in PHP. */
export function allowed(policy, node, key, def) {
  const base = key.split('@')[0];
  const p = policy && policy[node.id];
  if (p && Object.prototype.hasOwnProperty.call(p, base)) return !!p[base];
  if (!def || !def.fields[base] || STRUCTURE.includes(node.type)) return false;
  const f = def.fields[base];
  return (f.tab || 'content') === 'content' && !['columns', 'library'].includes(f.type);
}

export function isInstance(node, components) {
  return node && node.type === 'global' && node.attrs && node.attrs.ref && components && components.some((c) => c.id === Number(node.attrs.ref));
}

/* ------------------------------------------------------------------------
 * Save as component.
 * ---------------------------------------------------------------------- */

export function SaveComponentModal({ id }) {
  const node = T.find(getState().tree, id);
  const schema = getState().schema;
  const [title, setTitle] = useState(node ? T.label(node, schema) : 'Component');
  const [busy, setBusy] = useState(false);
  if (!node) return null;

  const submit = async () => {
    setBusy(true);
    try {
      const res = await api({ path: '/brik/v1/design/components', method: 'POST', data: { title, tree: [node] } });
      const s = getState();
      if (T.find(s.tree, id)) {
        // New id, so re-render from the parent (or the whole page at the root).
        const instance = { id: T.uid(), type: 'global', attrs: { ref: res.item.id } };
        const parent = T.parentOf(s.tree, id);
        commit('Save as component', T.update(s.tree, id, () => instance), parent ? { ids: [parent.id] } : { full: true }, { selected: instance.id });
      }
      await loadComponents(true);
      loadUsage().catch(() => {});
      toast(`Component "${res.item.title}" created`, 'success');
      closeModal();
    } catch (e) {
      toast(e.message || 'Could not create the component', 'error');
      setBusy(false);
    }
  };

  return (
    <Dialog
      title="Save as component"
      description="The element becomes a reusable component. Instances share its design; text, links, images and icons can be changed per instance."
      onClose={closeModal}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={closeModal}>
            Cancel
          </Button>
          <Button variant="brand" icon="component" onClick={submit} disabled={busy || !title.trim()}>
            Create component
          </Button>
        </>
      }
    >
      <div className="space-y-1.5">
        <Label>Name</Label>
        <Input autoFocus value={title} onChange={(e) => setTitle(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && submit()} />
      </div>
    </Dialog>
  );
}

/* ------------------------------------------------------------------------
 * Instance panel (nodeHeader of a component instance).
 * ---------------------------------------------------------------------- */

export function InstancePanel({ node }) {
  const components = useComponents();
  const schema = useStore((s) => s.schema);
  const tree = useStore((s) => s.tree);
  const [openNodes, setOpenNodes] = useState({});
  const ref = Number(node.attrs.ref);
  const master = useMaster(isInstance(node, components) ? ref : null);
  const [busy, setBusy] = useState(false);
  const inner = useMemo(() => {
    if (!master) return [];
    const out = [];
    T.walk(master.tree || [], (n) => {
      const def = schema.byType[n.type];
      if (!def || STRUCTURE.includes(n.type)) return;
      // Text-like fields first: they are what instances change most.
      const rank = (f) => (['text', 'textarea', 'richtext'].includes(f.type) ? 0 : ['link', 'icon', 'image'].includes(f.type) ? 1 : 2);
      const fields = Object.entries(def.fields)
        .filter(([k]) => allowed(master.policy, n, k, def))
        .sort((a, b) => rank(a[1]) - rank(b[1]));
      if (fields.length) out.push({ node: n, def, fields });
    });
    return out;
  }, [master, schema]);
  if (!isInstance(node, components)) return null;
  const item = components.find((c) => c.id === ref);
  const instances = item ? liveInstances(item, tree) : 0;
  const overrides = node.attrs.overrides || {};
  const count = Object.values(overrides).reduce((n, o) => n + Object.keys(o || {}).length, 0);

  const setOverride = (nid, key, value) => {
    const next = { ...overrides, [nid]: { ...(overrides[nid] || {}) } };
    if (value === undefined) delete next[nid][key];
    else next[nid][key] = value;
    if (!Object.keys(next[nid]).length) delete next[nid];
    setAttr(node.id, 'overrides', Object.keys(next).length ? next : undefined, null, true);
  };

  const detach = async () => {
    setBusy(true);
    try {
      const s = getState();
      const { parent, index } = T.locate(s.tree, node.id);
      const res = await api({
        path: `/brik/v1/design/components/${ref}/resolve`,
        method: 'POST',
        data: { overrides, context: parent && parent.type === 'column' ? 'column' : 'root', fresh: true },
      });
      const nodes = res.nodes || [];
      if (!nodes.length) throw new Error('The component is empty.');
      let tree = T.remove(s.tree, node.id);
      tree = T.insert(tree, parent ? parent.id : null, index, nodes);
      commit('Detach component', tree, parent ? { ids: [parent.id] } : { full: true }, { selected: nodes[0].id });
      toast('Detached. This copy no longer follows the component.', 'success');
    } catch (e) {
      toast(e.message || 'Could not detach', 'error');
    }
    setBusy(false);
  };

  return (
    <div className="overflow-hidden rounded-lg border border-violet-500/30 bg-violet-500/[0.04]">
      <div className="flex items-center gap-2 border-b border-violet-500/20 px-2.5 py-2">
        <span className="flex size-6 shrink-0 items-center justify-center rounded-md bg-violet-500 text-white">
          <Icon name="component" size={14} />
        </span>
        <span className="min-w-0 flex-1">
          <span className="block text-[10px] font-medium uppercase tracking-wide text-violet-600">Component</span>
          <span className="block truncate text-sm font-semibold">{item ? item.title : `#${ref}`}</span>
        </span>
        <IconButton icon="pencil-ruler" size="icon-sm" label="Edit master (opens in a new tab)" onClick={() => window.open(builderUrl(ref), '_blank')} />
        <IconButton icon="library" size="icon-sm" label="Go to component in the library" onClick={() => setState({ left: 'design-system', dsFocus: { section: 'components', id: ref } })} />
        <IconButton icon="unlink" size="icon-sm" label="Detach: turn into an independent copy" disabled={busy} onClick={detach} />
      </div>
      <div className="space-y-3 p-2.5">
        <div className="flex items-center justify-between text-[11px] text-muted-foreground">
          <span>{item ? `${instances} instance${instances === 1 ? '' : 's'} on the site` : ''}</span>
          {count > 0 && (
            <button type="button" className="cursor-pointer hover:text-foreground" onClick={() => setAttr(node.id, 'overrides', undefined, null, true)}>
              Reset all ({count})
            </button>
          )}
        </div>
        {!master && <p className="text-xs text-muted-foreground">Loading component…</p>}
        {master && !inner.length && <p className="text-xs text-muted-foreground">This component has no overridable fields. Allow some in the master to change them per instance.</p>}
        <div className="-mx-2.5 max-h-[52vh] space-y-1 overflow-y-auto px-2.5">
        {inner.map(({ node: n, def, fields }, i) => {
          const isOpen = openNodes[n.id] ?? i === 0;
          const changed = overrides[n.id] ? Object.keys(overrides[n.id]).length : 0;
          return (
          <div key={n.id} className="space-y-2">
            <button type="button" onClick={() => setOpenNodes({ ...openNodes, [n.id]: !isOpen })} className="flex w-full cursor-pointer items-center gap-1.5 rounded py-1 text-left text-[11px] font-medium text-foreground/70 hover:text-foreground">
              <Icon name="chevron-right" size={12} className={cn('shrink-0 text-muted-foreground transition-transform', isOpen && 'rotate-90')} />
              <span className="shrink-0 text-muted-foreground [&_svg]:size-3.5" dangerouslySetInnerHTML={{ __html: def.icon }} />
              <span className="shrink-0">{T.label(n, schema)}</span>
              {T.preview(n) && <span className="min-w-0 truncate font-normal text-muted-foreground">· {T.preview(n)}</span>}
              {changed > 0 && <span className="ml-auto shrink-0 rounded-full bg-violet-500 px-1.5 text-[9px] leading-4 text-white">{changed}</span>}
            </button>
            {isOpen && fields.map(([key, field]) => {
              const own = overrides[n.id] && Object.prototype.hasOwnProperty.call(overrides[n.id], key);
              const masterValue = n.attrs && n.attrs[key] !== undefined ? n.attrs[key] : field.default;
              return (
                <div key={key} className={cn('space-y-1.5 rounded-md border-l-2 pl-2', own ? 'border-violet-500' : 'border-transparent')}>
                  {field.type !== 'toggle' && (
                    <div className="flex min-h-5 items-center justify-between gap-2">
                      <Label className="flex items-center gap-1.5">
                        {field.label}
                        {own && <span className="rounded bg-violet-500/15 px-1 text-[9px] font-medium uppercase text-violet-600">override</span>}
                      </Label>
                      {own && (
                        <button type="button" className="flex cursor-pointer items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground" title="Reset to master" onClick={() => setOverride(n.id, key, undefined)}>
                          <Icon name="rotate-ccw" size={11} />
                          Master
                        </button>
                      )}
                    </div>
                  )}
                  <Control field={field} value={own ? overrides[n.id][key] : masterValue} onChange={(v) => setOverride(n.id, key, v === '' ? '' : v)} node={n} fkey={key} def={def} />
                </div>
              );
            })}
          </div>
          );
        })}
        </div>
      </div>
    </div>
  );
}

/** Instances on the site: stored ones on other posts plus the live tree of this page. */
function liveInstances(item, tree) {
  const postId = config.post && config.post.id;
  const here = ((item.used_on || []).find((p) => p.id === postId) || {}).count || 0;
  let live = 0;
  T.walk(tree || [], (n) => {
    if (n.type === 'global' && n.attrs && Number(n.attrs.ref) === item.id) live++;
  });
  return item.instances - here + live;
}

/* ------------------------------------------------------------------------
 * Master editing: override policy per field.
 * ---------------------------------------------------------------------- */

export function MasterBanner() {
  const lib = editingComponent();
  const components = useComponents();
  if (!lib) return null;
  const item = components && components.find((c) => c.id === lib.id);
  return (
    <div className="flex items-start gap-2 rounded-lg border border-violet-500/30 bg-violet-500/[0.06] p-2.5 text-[11px] leading-snug">
      <Icon name="component" size={14} className="mt-px shrink-0 text-violet-600" />
      <span>
        <span className="font-medium text-foreground">Editing the component master.</span>{' '}
        <span className="text-muted-foreground">
          Saving updates {item ? `all ${item.instances} instance${item.instances === 1 ? '' : 's'}` : 'every instance'} on the site. Use the <Icon name="lock-open" size={10} className="inline-flex align-[-1px]" /> toggles to choose which fields instances may override.
        </span>
      </span>
    </div>
  );
}

export function PolicyToggle({ node, fkey, field }) {
  const lib = editingComponent();
  const schema = useStore((s) => s.schema);
  const master = useMaster(lib ? lib.id : null);
  if (!lib || !node || STRUCTURE.includes(node.type) || fkey === 'admin_label') return null;
  const def = schema.byType[node.type];
  const policy = (master && master.policy) || {};
  const on = allowed(policy, node, fkey, def);

  const toggle = async () => {
    const next = { ...policy, [node.id]: { ...(policy[node.id] || {}), [fkey]: !on } };
    setState((s) => ({ masters: { ...(s.masters || {}), [lib.id]: { ...(s.masters || {})[lib.id], policy: next } } }));
    try {
      await api({ path: `/brik/v1/design/components/${lib.id}/policy`, method: 'POST', data: { policy: next } });
      loadMaster(lib.id, true);
    } catch (e) {
      toast(e.message || 'Could not save', 'error');
    }
  };

  return (
    <button
      type="button"
      onClick={toggle}
      title={on ? 'Instances can override this field (click to lock)' : 'Locked to the master (click to allow overrides)'}
      className={cn('inline-flex cursor-pointer items-center rounded', on ? 'text-violet-600 hover:text-violet-700' : 'text-muted-foreground/60 hover:text-foreground')}
    >
      <Icon name={on ? 'lock-open' : 'lock'} size={12} />
    </button>
  );
}
