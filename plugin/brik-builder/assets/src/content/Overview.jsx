// Overview lists: post types, taxonomies, field groups, plus starter templates.
import { useState, __, sprintf, _n } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, IconButton, Input } from '../builder/ui.jsx';
import { Badge, Menu, MenuIcon, SwitchBtn, confirmDialog } from './primitives.jsx';
import { useStore, getState, navigate, saveItem, deleteItem, importContent, toast, errorMessage, loadCounts, clone, uid, cloneField, subFields, countFields } from './lib.js';
import { TEMPLATES } from './templates.js';
import { locationSummary } from './LocationRules.jsx';
import { ExportDialog } from './ImportExport.jsx';

export function Overview({ section }) {
  const s = useStore();
  const [q, setQ] = useState('');
  const [exporting, setExporting] = useState(null);
  const empty = !s.post_types.length && !s.taxonomies.length && !s.groups.length;

  return (
    <div className="space-y-8">
      {empty && <Welcome />}
      <div>
        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold tracking-tight">{TITLES[section]}</h2>
            <p className="text-sm text-muted-foreground">{SUBTITLES[section]}</p>
          </div>
          <div className="flex items-center gap-2">
            {s[KIND[section]].length > 5 && (
              <div className="relative">
                <Icon name="search" size={14} className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
                <Input className="h-8 w-48 pl-8" placeholder={__('Filter…', 'brik-builder')} value={q} onChange={(e) => setQ(e.target.value)} />
              </div>
            )}
            <Button size="sm" icon="plus" onClick={() => navigate(`#/${section}/new`)}>
              {CREATE[section]}
            </Button>
          </div>
        </div>
        {empty ? null : section === 'post-types' && <PostTypeList q={q} onExport={setExporting} />}
        {!empty && section === 'taxonomies' && <TaxonomyList q={q} onExport={setExporting} />}
        {!empty && section === 'field-groups' && <GroupList q={q} onExport={setExporting} />}
      </div>
      <Templates compact={!empty} />
      {exporting && <ExportDialog only={exporting} onClose={() => setExporting(null)} />}
    </div>
  );
}

const KIND = { 'post-types': 'post_types', taxonomies: 'taxonomies', 'field-groups': 'groups' };
const TITLES = { 'post-types': __('Post types', 'brik-builder'), taxonomies: __('Taxonomies', 'brik-builder'), 'field-groups': __('Field groups', 'brik-builder') };
const SUBTITLES = {
  'post-types': __('Custom content like projects, events or products, each with its own admin menu.', 'brik-builder'),
  taxonomies: __('Ways to group content, like categories and tags.', 'brik-builder'),
  'field-groups': __('Custom fields shown in the editor and available as dynamic data in Brik.', 'brik-builder'),
};
const CREATE = { 'post-types': __('New post type', 'brik-builder'), taxonomies: __('New taxonomy', 'brik-builder'), 'field-groups': __('New field group', 'brik-builder') };

function Welcome() {
  const steps = [
    { icon: 'file-stack', title: __('Create a post type', 'brik-builder'), text: __('Projects, events, team members… anything that deserves its own menu.', 'brik-builder'), to: '#/post-types/new' },
    { icon: 'tags', title: __('Add a taxonomy', 'brik-builder'), text: __('Organise entries into categories or tags.', 'brik-builder'), to: '#/taxonomies/new' },
    { icon: 'text-cursor-input', title: __('Design custom fields', 'brik-builder'), text: __('Prices, dates, galleries — then show them with Brik dynamic data.', 'brik-builder'), to: '#/field-groups/new' },
  ];
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
      <div className="bk-grid-dots border-b border-border px-6 py-6">
        <Badge tone="brand" icon="sparkles">
          {__('Getting started', 'brik-builder')}
        </Badge>
        <h2 className="mt-3 text-xl font-semibold tracking-tight">{__('Model your content, then design it in Brik', 'brik-builder')}</h2>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">{__('Everything here is registered with WordPress, works in the block editor and the REST API, and shows up as dynamic data, listings and filters in the builder. Start from scratch or install a template below.', 'brik-builder')}</p>
      </div>
      <div className="grid gap-px bg-border sm:grid-cols-3">
        {steps.map((st, i) => (
          <button key={st.to} type="button" onClick={() => navigate(st.to)} className="group flex items-start gap-3 bg-card p-5 text-left transition-colors hover:bg-muted/40 cursor-pointer">
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-foreground shadow-xs">
              <Icon name={st.icon} size={17} />
            </span>
            <span className="min-w-0">
              <span className="flex items-center gap-1.5 text-sm font-medium">
                <span className="text-muted-foreground">{i + 1}.</span> {st.title}
                <Icon name="arrow-right" size={14} className="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />
              </span>
              <span className="mt-0.5 block text-xs text-muted-foreground">{st.text}</span>
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

function ListShell({ children, empty }) {
  if (empty) return empty;
  return <div className="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card shadow-xs">{children}</div>;
}

function EmptyList({ icon, title, text, action, onAction }) {
  return (
    <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border bg-card/60 px-6 py-10 text-center">
      <span className="flex size-10 items-center justify-center rounded-full bg-muted">
        <Icon name={icon} size={18} className="text-muted-foreground" />
      </span>
      <p className="text-sm font-medium">{title}</p>
      <p className="max-w-sm text-xs text-muted-foreground">{text}</p>
      {action && (
        <Button size="sm" variant="outline" icon="plus" className="mt-2" onClick={onAction}>
          {action}
        </Button>
      )}
    </div>
  );
}

function matches(q, ...parts) {
  if (!q) return true;
  return parts.join(' ').toLowerCase().includes(q.toLowerCase());
}

async function toggleActive(kind, item) {
  try {
    await saveItem(kind, { ...item, active: !item.active });
    toast(item.active ? __('Deactivated', 'brik-builder') : __('Activated', 'brik-builder'), 'success');
  } catch (e) {
    toast(errorMessage(e, __('Could not update.', 'brik-builder')), 'error');
  }
}

function Row({ icon, iconNode, title, sub, meta, item, kind, onEdit, menu }) {
  return (
    <div className={cn('group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/30', !item.active && 'bg-muted/20')}>
      <button type="button" onClick={onEdit} className={cn('flex size-10 shrink-0 items-center justify-center rounded-lg border border-border bg-background text-foreground shadow-xs cursor-pointer', !item.active && 'opacity-50')}>
        {iconNode || <Icon name={icon} size={18} />}
      </button>
      <button type="button" onClick={onEdit} className="min-w-0 flex-1 text-left cursor-pointer">
        <span className="flex flex-wrap items-center gap-2">
          <span className={cn('truncate text-sm font-medium', !item.active && 'text-muted-foreground')}>{title}</span>
          <code className="rounded bg-muted px-1.5 py-px font-mono text-[11px] text-muted-foreground">{sub}</code>
          {!item.active && <Badge tone="outline">{__('Inactive', 'brik-builder')}</Badge>}
          {item.local && <Badge tone="warn" icon="code">{__('Registered in code', 'brik-builder')}</Badge>}
        </span>
        <span className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">{meta}</span>
      </button>
      <div className="flex shrink-0 items-center gap-1.5">
        <span className="mr-2 hidden items-center gap-2 text-xs text-muted-foreground sm:flex" title={item.active ? __('Active', 'brik-builder') : __('Inactive', 'brik-builder')}>
          {!item.local && <SwitchBtn size="sm" checked={!!item.active} onChange={() => toggleActive(kind, item)} label={__('Active', 'brik-builder')} />}
        </span>
        <Button size="sm" variant="outline" onClick={onEdit}>
          {__('Edit', 'brik-builder')}
        </Button>
        <Menu trigger={<IconButton icon="ellipsis" label={__('More actions', 'brik-builder')} />} items={menu} />
      </div>
    </div>
  );
}

function Meta({ icon, children }) {
  return (
    <span className="inline-flex items-center gap-1">
      <Icon name={icon} size={12} />
      {children}
    </span>
  );
}

/* ------------------------------------------------------------------------ */

function PostTypeList({ q, onExport }) {
  const { post_types, counts, groups } = useStore();
  const list = post_types.filter((p) => matches(q, p.singular, p.plural, p.key));
  return (
    <ListShell
      empty={
        !post_types.length && (
          <EmptyList icon="file-stack" title={__('No post types yet', 'brik-builder')} text={__('Create one from scratch or install a starter template below.', 'brik-builder')} action={__('Create post type', 'brik-builder')} onAction={() => navigate('#/post-types/new')} />
        )
      }
    >
      {list.map((pt) => {
        const n = counts[`pt:${pt.key}`];
        const fieldCount = groups.filter((g) => JSON.stringify(g.location || []).includes(`"${pt.key}"`)).length;
        return (
          <Row
            key={pt.key}
            item={pt}
            kind="post_types"
            iconNode={<MenuIcon icon={pt.icon} size={18} />}
            title={pt.plural || pt.singular || pt.key}
            sub={pt.key}
            onEdit={() => navigate(`#/post-types/${pt.key}`)}
            meta={
              <>
                <Meta icon="file-text">{n == null ? '—' : sprintf(_n('%d entry', '%d entries', n, 'brik-builder'), n)}</Meta>
                {pt.taxonomies && pt.taxonomies.length > 0 && <Meta icon="tags">{pt.taxonomies.join(', ')}</Meta>}
                {fieldCount > 0 && <Meta icon="text-cursor-input">{sprintf(_n('%d field group', '%d field groups', fieldCount, 'brik-builder'), fieldCount)}</Meta>}
                {pt.brik && <Meta icon="layout-template">{__('Brik builder', 'brik-builder')}</Meta>}
                {!pt.public && <Meta icon="eye-off">{__('Private', 'brik-builder')}</Meta>}
              </>
            }
            menu={[
              { icon: 'pencil', label: __('Edit', 'brik-builder'), onClick: () => navigate(`#/post-types/${pt.key}`) },
              pt.active && getState().wp.types[pt.key] && { icon: 'list', label: __('View entries', 'brik-builder'), onClick: () => (window.location.href = `edit.php?post_type=${pt.key}`) },
              pt.active && getState().wp.types[pt.key] && { icon: 'file-plus', label: __('Add new entry', 'brik-builder'), onClick: () => (window.location.href = `post-new.php?post_type=${pt.key}`) },
              { icon: 'copy', label: __('Duplicate', 'brik-builder'), onClick: () => navigate(`#/post-types/new?from=${pt.key}`) },
              { icon: 'download', label: __('Export', 'brik-builder'), onClick: () => onExport({ post_types: [pt.key] }) },
              '-',
              { icon: 'trash-2', label: __('Delete', 'brik-builder'), danger: true, onClick: () => removePostType(pt) },
            ]}
          />
        );
      })}
      {!list.length && post_types.length > 0 && <p className="px-4 py-6 text-center text-sm text-muted-foreground">{__('Nothing matches that filter.', 'brik-builder')}</p>}
    </ListShell>
  );
}

export async function removePostType(pt) {
  const n = getState().counts[`pt:${pt.key}`];
  const res = await confirmDialog({
    title: sprintf(__('Delete “%s”?', 'brik-builder'), pt.plural || pt.key),
    message: __('The post type is unregistered. Existing entries stay in the database unless you choose to delete them, and come back if you recreate the same key.', 'brik-builder'),
    confirm: __('Delete post type', 'brik-builder'),
    danger: true,
    check: { label: n ? sprintf(_n('Also delete its %d entry', 'Also delete all %d entries', n, 'brik-builder'), n) : __('Also delete its entries', 'brik-builder'), help: __('This permanently removes the posts and cannot be undone.', 'brik-builder') },
  });
  if (!res) return false;
  try {
    await deleteItem('post_types', pt.key, res.checked ? { delete_posts: 1 } : {});
    toast(__('Post type deleted', 'brik-builder'), 'success');
    return true;
  } catch (e) {
    toast(errorMessage(e, __('Could not delete.', 'brik-builder')), 'error');
    return false;
  }
}

function TaxonomyList({ q, onExport }) {
  const { taxonomies, counts, post_types } = useStore();
  const list = taxonomies.filter((t) => matches(q, t.singular, t.plural, t.key));
  const label = (k) => (post_types.find((p) => p.key === k) || {}).plural || k;
  return (
    <ListShell
      empty={
        !taxonomies.length && (
          <EmptyList icon="tags" title={__('No taxonomies yet', 'brik-builder')} text={__('Taxonomies group entries — think “Project type” or “Location”.', 'brik-builder')} action={__('Create taxonomy', 'brik-builder')} onAction={() => navigate('#/taxonomies/new')} />
        )
      }
    >
      {list.map((tx) => {
        const n = counts[`tx:${tx.key}`];
        return (
          <Row
            key={tx.key}
            item={tx}
            kind="taxonomies"
            icon={tx.hierarchical ? 'folder-tree' : 'tag'}
            title={tx.plural || tx.singular || tx.key}
            sub={tx.key}
            onEdit={() => navigate(`#/taxonomies/${tx.key}`)}
            meta={
              <>
                <Meta icon="hash">{n == null ? '—' : sprintf(_n('%d term', '%d terms', n, 'brik-builder'), n)}</Meta>
                <Meta icon={tx.hierarchical ? 'folder-tree' : 'tag'}>{tx.hierarchical ? __('Hierarchical', 'brik-builder') : __('Flat (tags)', 'brik-builder')}</Meta>
                {(tx.post_types || []).length > 0 ? <Meta icon="file-stack">{tx.post_types.map(label).join(', ')}</Meta> : <Meta icon="unlink">{__('Not attached', 'brik-builder')}</Meta>}
              </>
            }
            menu={[
              { icon: 'pencil', label: __('Edit', 'brik-builder'), onClick: () => navigate(`#/taxonomies/${tx.key}`) },
              tx.active && getState().wp.taxonomies[tx.key] && { icon: 'list', label: __('Manage terms', 'brik-builder'), onClick: () => (window.location.href = `edit-tags.php?taxonomy=${tx.key}${tx.post_types && tx.post_types[0] ? `&post_type=${tx.post_types[0]}` : ''}`) },
              { icon: 'copy', label: __('Duplicate', 'brik-builder'), onClick: () => navigate(`#/taxonomies/new?from=${tx.key}`) },
              { icon: 'download', label: __('Export', 'brik-builder'), onClick: () => onExport({ taxonomies: [tx.key] }) },
              '-',
              { icon: 'trash-2', label: __('Delete', 'brik-builder'), danger: true, onClick: () => removeTaxonomy(tx) },
            ]}
          />
        );
      })}
    </ListShell>
  );
}

export async function removeTaxonomy(tx) {
  const ok = await confirmDialog({
    title: sprintf(__('Delete “%s”?', 'brik-builder'), tx.plural || tx.key),
    message: __('The taxonomy is unregistered. Its terms stay in the database unless you choose to delete them, and come back if you recreate the same key.', 'brik-builder'),
    confirm: __('Delete taxonomy', 'brik-builder'),
    danger: true,
    check: { label: __('Also delete its terms', 'brik-builder'), help: __('Entries keep existing but lose these terms. This cannot be undone.', 'brik-builder') },
  });
  if (!ok) return false;
  try {
    await deleteItem('taxonomies', tx.key, ok.checked ? { delete_terms: 1 } : {});
    toast(__('Taxonomy deleted', 'brik-builder'), 'success');
    return true;
  } catch (e) {
    toast(errorMessage(e, __('Could not delete.', 'brik-builder')), 'error');
    return false;
  }
}

function GroupList({ q, onExport }) {
  const { groups } = useStore();
  const list = groups.filter((g) => matches(q, g.title, g.key));
  return (
    <ListShell
      empty={
        !groups.length && (
          <EmptyList icon="text-cursor-input" title={__('No field groups yet', 'brik-builder')} text={__('A field group is a set of custom fields shown on the screens you choose.', 'brik-builder')} action={__('Create field group', 'brik-builder')} onAction={() => navigate('#/field-groups/new')} />
        )
      }
    >
      {list.map((g) => {
        const n = countFields(g.fields || []);
        return (
          <Row
            key={g.key}
            item={g}
            kind="groups"
            icon="text-cursor-input"
            title={g.title || __('(untitled)', 'brik-builder')}
            sub={g.key}
            onEdit={() => navigate(`#/field-groups/${g.key}`)}
            meta={
              <>
                <Meta icon="list">{sprintf(_n('%d field', '%d fields', n, 'brik-builder'), n)}</Meta>
                <Meta icon="map-pin">{locationSummary(g.location)}</Meta>
                <Meta icon="panel-right">{{ side: __('Sidebar', 'brik-builder'), after_title: __('After title', 'brik-builder') }[g.position] || __('Below content', 'brik-builder')}</Meta>
              </>
            }
            menu={[
              { icon: 'pencil', label: __('Edit', 'brik-builder'), onClick: () => navigate(`#/field-groups/${g.key}`) },
              { icon: 'copy', label: __('Duplicate', 'brik-builder'), onClick: () => duplicateGroup(g) },
              { icon: 'download', label: __('Export', 'brik-builder'), onClick: () => onExport({ groups: [g.key] }) },
              '-',
              { icon: 'trash-2', label: __('Delete', 'brik-builder'), danger: true, onClick: () => removeGroup(g) },
            ]}
          />
        );
      })}
    </ListShell>
  );
}

async function duplicateGroup(g) {
  const copy = clone(g);
  copy.key = uid('group');
  copy.title = sprintf(__('%s (copy)', 'brik-builder'), g.title || '');
  copy.active = false;
  copy.fields = (copy.fields || []).map(cloneField);
  try {
    await saveItem('groups', copy);
    toast(__('Duplicated — the copy is inactive until you turn it on.', 'brik-builder'), 'success', 4500);
  } catch (e) {
    toast(errorMessage(e, __('Could not duplicate.', 'brik-builder')), 'error');
  }
}

export async function removeGroup(g) {
  const ok = await confirmDialog({
    title: sprintf(__('Delete “%s”?', 'brik-builder'), g.title || g.key),
    message: __('The fields disappear from the editor. Values already saved stay in post meta.', 'brik-builder'),
    confirm: __('Delete field group', 'brik-builder'),
    danger: true,
  });
  if (!ok) return false;
  try {
    await deleteItem('groups', g.key);
    toast(__('Field group deleted', 'brik-builder'), 'success');
    return true;
  } catch (e) {
    toast(errorMessage(e, __('Could not delete.', 'brik-builder')), 'error');
    return false;
  }
}

/* ------------------------------------------------------------------------
 * Starter templates.
 * ---------------------------------------------------------------------- */

function Templates({ compact }) {
  const [busy, setBusy] = useState(null);
  const installed = useStore((s) => s.post_types).map((p) => p.key);

  const apply = async (tpl) => {
    const data = tpl.build();
    const s = getState();
    const clash = [...data.post_types.filter((p) => s.post_types.some((x) => x.key === p.key)).map((p) => p.key), ...data.taxonomies.filter((t) => s.taxonomies.some((x) => x.key === t.key)).map((t) => t.key)];
    const fields = data.groups.reduce((n, g) => n + countFields(g.fields), 0);
    const ok = await confirmDialog({
      icon: tpl.icon,
      title: sprintf(__('Install the %s template?', 'brik-builder'), tpl.name),
      message: (
        <div className="space-y-3">
          <p>{tpl.description}</p>
          <ul className="space-y-1.5 rounded-lg border border-border bg-muted/30 p-3 text-[13px] text-foreground">
            {data.post_types.map((p) => (
              <li key={p.key} className="flex items-center gap-2">
                <MenuIcon icon={p.icon} size={16} /> {sprintf(__('Post type %s', 'brik-builder'), p.plural)} <code className="font-mono text-[11px] text-muted-foreground">{p.key}</code>
              </li>
            ))}
            {data.taxonomies.map((t) => (
              <li key={t.key} className="flex items-center gap-2">
                <Icon name="tags" size={16} /> {sprintf(__('Taxonomy %s', 'brik-builder'), t.plural)} <code className="font-mono text-[11px] text-muted-foreground">{t.key}</code>
              </li>
            ))}
            {data.groups.map((g) => (
              <li key={g.key} className="flex items-center gap-2">
                <Icon name="text-cursor-input" size={16} /> {sprintf(_n('Field group “%1$s” with %2$d field', 'Field group “%1$s” with %2$d fields', fields, 'brik-builder'), g.title, fields)}
              </li>
            ))}
          </ul>
          {clash.length > 0 && (
            <p className="flex items-start gap-2 rounded-lg bg-amber-500/10 p-2.5 text-xs text-amber-800">
              <Icon name="triangle-alert" size={14} className="mt-px" />
              {sprintf(__('Already exists and will be updated: %s', 'brik-builder'), clash.join(', '))}
            </p>
          )}
        </div>
      ),
      confirm: __('Install', 'brik-builder'),
    });
    if (!ok) return;
    setBusy(tpl.id);
    try {
      const res = await importContent(data);
      if (res && res.errors && res.errors.length) toast(res.errors.map((e) => e.message).join(' '), 'error', 7000);
      else toast(sprintf(__('%s installed', 'brik-builder'), tpl.name), 'success');
      loadCounts();
    } catch (e) {
      toast(errorMessage(e, __('Could not install the template.', 'brik-builder')), 'error');
    }
    setBusy(null);
  };

  return (
    <div>
      <div className="mb-3">
        <h2 className="flex items-center gap-2 text-sm font-semibold">
          <Icon name="layout-grid" size={15} />
          {__('Starter templates', 'brik-builder')}
        </h2>
        <p className="text-xs text-muted-foreground">{__('One click sets up the post type, taxonomies and fields. Change anything afterwards.', 'brik-builder')}</p>
      </div>
      <div className={cn('grid gap-3', compact ? 'grid-cols-2 lg:grid-cols-4' : 'grid-cols-2 lg:grid-cols-4')}>
        {TEMPLATES.map((tpl) => {
          const done = tpl.build && installed.includes(TEMPLATE_KEYS[tpl.id]);
          return (
            <button
              key={tpl.id}
              type="button"
              disabled={!!busy}
              onClick={() => apply(tpl)}
              className="group relative flex flex-col items-start gap-2 rounded-xl border border-border bg-card p-4 text-left shadow-xs transition-all hover:-translate-y-px hover:border-foreground/20 hover:shadow-md disabled:opacity-60 cursor-pointer"
            >
              <span className="flex w-full items-center justify-between">
                <span className={cn('flex size-9 items-center justify-center rounded-lg', tpl.color)}>
                  <Icon name={tpl.icon} size={17} />
                </span>
                {busy === tpl.id ? <span className="bk-spinner size-4" /> : done ? <Badge tone="success" icon="check">{__('Installed', 'brik-builder')}</Badge> : <Icon name="plus" size={16} className="text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100" />}
              </span>
              <span className="text-sm font-medium">{tpl.name}</span>
              <span className="text-xs leading-relaxed text-muted-foreground">{tpl.description}</span>
            </button>
          );
        })}
      </div>
    </div>
  );
}

const TEMPLATE_KEYS = { portfolio: 'project', team: 'member', events: 'event', products: 'product_item', testimonials: 'testimonial', faq: 'faq', 'real-estate': 'property', jobs: 'job' };
