// Field group editor (#/field-groups/{key}): sortable nested field list, group settings, preview.
import { useState, useMemo, useEffect, useRef, createPortal, __, sprintf, _n } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, IconButton, Input, Tabs } from '../builder/ui.jsx';
import { Card, Row, SwitchRow, Badge, Segment, Menu, Kbd, modKey, confirmDialog } from './primitives.jsx';
import {
  useStore,
  getState,
  navigate,
  saveItem,
  toast,
  errorMessage,
  uid,
  clone,
  fieldType,
  newField,
  subFields,
  withSub,
  findField,
  parentOf,
  siblingsOf,
  updateField,
  removeField,
  insertField,
  contains,
  countFields,
  cloneField,
  validateFields,
  PARENT_TYPES,
  NO_VALUE,
} from './lib.js';
import { useDraft, useSaveShortcut, EditorHeader, SaveBar } from './EditorShell.jsx';
import { FieldSettings, TypePicker } from './FieldSettings.jsx';
import { LocationRules, postTypeOptions } from './LocationRules.jsx';
import { Preview } from './Preview.jsx';
import { useFieldDrag } from './dnd.js';
import { removeGroup } from './Overview.jsx';

const QUICK = ['text', 'textarea', 'number', 'image', 'select', 'toggle', 'date', 'repeater'];

function initialDraft(id, query) {
  const s = getState();
  if (id && id !== 'new') {
    const found = s.groups.find((g) => g.key === id);
    return found ? { position: 'normal', style: 'card', order: 0, location: [], fields: [], ...clone(found) } : null;
  }
  const pt = query.post_type || (s.post_types[0] && s.post_types[0].key) || 'post';
  return { key: uid('group'), active: true, version: 1, title: '', location: [[{ param: 'post_type', operator: '==', value: pt }]], position: 'normal', style: 'card', order: 0, fields: [] };
}

export function FieldGroupEditor({ id, query }) {
  const start = useMemo(() => initialDraft(id, query), [id]);
  if (!start) {
    return (
      <div className="flex flex-col items-center gap-3 py-20 text-center">
        <Icon name="file-question-mark" size={28} className="text-muted-foreground" />
        <p className="text-sm font-medium">{__('This field group doesn’t exist.', 'brik-builder')}</p>
        <Button variant="outline" size="sm" onClick={() => navigate('#/field-groups')}>
          {__('Back to field groups', 'brik-builder')}
        </Button>
      </div>
    );
  }
  return <Editor start={start} isNew={!id || id === 'new'} />;
}

function Editor({ start, isNew }) {
  const [g, update, dirty, markSaved] = useDraft(start);
  const [open, setOpen] = useState(() => new Set());
  const [focusKey, setFocusKey] = useState(null);
  const [view, setView] = useState('fields');
  const [picker, setPicker] = useState(null); // { parent, index }
  const [saving, setSaving] = useState(false);
  const [showErrors, setShowErrors] = useState(false);
  const [serverError, setServerError] = useState('');
  const titleRef = useRef(null);
  const s = useStore();
  // Names of saved fields are meta keys with data behind them, so they stop following the label.
  const keysOf = (list) => list.reduce((acc, f) => [...acc, f.key, ...keysOf(subFields(f))], []);
  const [savedKeys, setSavedKeys] = useState(() => new Set(isNew ? [] : keysOf(start.fields || [])));

  const fields = g.fields || [];
  const errors = useMemo(() => validateFields(fields), [fields]);
  const errorCount = Object.keys(errors).length;
  const titleError = !g.title.trim() ? __('Give the group a title.', 'brik-builder') : '';

  const setFields = (next) => update((d) => ({ fields: typeof next === 'function' ? next(d.fields || []) : next }));
  const toggle = (key, force) =>
    setOpen((o) => {
      const n = new Set(o);
      if (force ?? !n.has(key)) n.add(key);
      else n.delete(key);
      return n;
    });

  const add = (type, parent = null, index = null, label = '') => {
    const f = newField(type, label);
    const list = parent ? subFields(findField(fields, parent)) : fields;
    setFields(insertField(fields, parent, index ?? list.length, f));
    // One field open at a time while building keeps the list readable.
    setOpen(new Set([f.key]));
    setFocusKey(f.key);
    setView('fields');
    return f;
  };

  const addAfter = (key) => {
    const parent = parentOf(fields, key);
    const sibs = siblingsOf(fields, key);
    const i = sibs.findIndex((f) => f.key === key);
    // Collapse the one we came from so the list stays scannable while typing.
    const cur = findField(fields, key);
    if (cur && cur.label) toggle(key, false);
    add('text', parent, i + 1);
  };

  const remove = async (f) => {
    const n = countFields(subFields(f));
    if (n || f.label) {
      const ok = await confirmDialog({
        title: sprintf(__('Remove “%s”?', 'brik-builder'), f.label || f.name || __('field', 'brik-builder')),
        message: n ? sprintf(_n('Its %d sub field is removed too.', 'Its %d sub fields are removed too.', n, 'brik-builder'), n) : __('Values already saved stay in the database.', 'brik-builder'),
        confirm: __('Remove field', 'brik-builder'),
        danger: true,
      });
      if (!ok) return;
    }
    setFields(removeField(fields, f.key)[0]);
  };

  const duplicate = (f) => {
    const copy = cloneField(f);
    copy.label = sprintf(__('%s (copy)', 'brik-builder'), f.label || '');
    copy.name = f.name ? `${f.name}_copy` : '';
    const parent = parentOf(fields, f.key);
    const i = siblingsOf(fields, f.key).findIndex((x) => x.key === f.key);
    setFields(insertField(fields, parent, i + 1, copy));
    setOpen((o) => new Set([...o, copy.key]));
  };

  const moveBy = (f, delta) => {
    const parent = parentOf(fields, f.key);
    const sibs = siblingsOf(fields, f.key);
    const i = sibs.findIndex((x) => x.key === f.key);
    const j = i + delta;
    if (j < 0 || j >= sibs.length) return;
    const [rest, removed] = removeField(fields, f.key);
    setFields(insertField(rest, parent, j, removed));
  };

  const { drag, start: startDrag } = useFieldDrag({
    canDrop: (key, parent) => {
      if (!parent) return true;
      const dragged = findField(fields, key);
      return dragged && !contains(dragged, parent);
    },
    onDrop: (key, parent, index) => {
      const fromParent = parentOf(fields, key);
      const fromIndex = siblingsOf(fields, key).findIndex((f) => f.key === key);
      let to = index;
      if ((fromParent || null) === (parent || null) && fromIndex < index) to -= 1;
      if ((fromParent || null) === (parent || null) && to === fromIndex) return;
      const [rest, removed] = removeField(fields, key);
      setFields(insertField(rest, parent || null, to, removed));
    },
  });

  const save = async () => {
    if (errorCount || titleError) {
      setShowErrors(true);
      // Open the broken fields so the messages are visible.
      setOpen((o) => new Set([...o, ...Object.keys(errors)]));
      setView('fields');
      if (titleError && titleRef.current) titleRef.current.focus();
      toast(titleError || sprintf(_n('%d field needs attention.', '%d fields need attention.', errorCount, 'brik-builder'), errorCount), 'error');
      return;
    }
    setSaving(true);
    setServerError('');
    try {
      const saved = await saveItem('groups', g);
      markSaved({ position: 'normal', style: 'card', order: 0, location: [], fields: [], ...saved });
      setSavedKeys(new Set(keysOf(saved.fields || [])));
      toast(isNew ? __('Field group created', 'brik-builder') : __('Field group saved', 'brik-builder'), 'success');
      if (isNew) setTimeout(() => navigate(`#/field-groups/${saved.key}`), 0);
    } catch (e) {
      const msg = errorMessage(e, __('Could not save the field group.', 'brik-builder'));
      setServerError(msg);
      toast(msg, 'error');
    }
    setSaving(false);
  };
  useSaveShortcut(save);

  const ptLabel = (() => {
    const rule = (g.location || []).flat().find((r) => r.param === 'post_type' && r.operator === '==');
    if (!rule) return '';
    const o = postTypeOptions(s).find((x) => x.value === rule.value);
    const own = s.post_types.find((p) => p.key === rule.value);
    return (own && own.singular) || (o && o.label) || rule.value;
  })();
  const editLink = (() => {
    const rule = (g.location || []).flat().find((r) => r.param === 'post_type' && r.operator === '==');
    return rule && s.wp.types[rule.value] ? `post-new.php?post_type=${rule.value}` : null;
  })();

  return (
    <div>
      <EditorHeader
        back="#/field-groups"
        backLabel={__('Field groups', 'brik-builder')}
        icon={<Icon name="text-cursor-input" size={20} />}
        title={g.title || (isNew ? __('New field group', 'brik-builder') : __('(untitled)', 'brik-builder'))}
        subtitle={sprintf(_n('%d field', '%d fields', countFields(fields), 'brik-builder'), countFields(fields))}
        badges={!g.active ? <Badge tone="outline">{__('Inactive', 'brik-builder')}</Badge> : null}
        actions={
          <>
            {!isNew && editLink && (
              <Button variant="outline" size="sm" icon="external-link" onClick={() => window.open(editLink, '_blank')}>
                {__('Try in editor', 'brik-builder')}
              </Button>
            )}
            {!isNew && (
              <Button variant="ghost" size="sm" icon="trash-2" className="text-destructive hover:bg-destructive/10 hover:text-destructive" onClick={async () => (await removeGroup(start)) && navigate('#/field-groups')}>
                {__('Delete', 'brik-builder')}
              </Button>
            )}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <Tabs
              value={view}
              onChange={setView}
              tabs={[
                { value: 'fields', label: __('Fields', 'brik-builder'), icon: 'list' },
                { value: 'preview', label: __('Preview', 'brik-builder'), icon: 'eye' },
              ]}
            />
            {view === 'fields' && fields.length > 0 && (
              <div className="flex items-center gap-1">
                <Button variant="ghost" size="xs" icon="chevrons-down-up" onClick={() => setOpen(new Set())}>
                  {__('Collapse all', 'brik-builder')}
                </Button>
              </div>
            )}
          </div>

          {view === 'preview' ? (
            <Preview group={g} postTypeLabel={ptLabel} />
          ) : (
            <>
              {fields.length > 0 ? (
                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-xs">
                  <div className="hidden grid-cols-[minmax(0,1fr)_150px_120px_88px] gap-3 border-b border-border bg-muted/40 py-2 pr-3 pl-[76px] text-[11px] font-medium text-muted-foreground md:grid">
                    <span>{__('Label', 'brik-builder')}</span>
                    <span>{__('Name', 'brik-builder')}</span>
                    <span>{__('Type', 'brik-builder')}</span>
                    <span />
                  </div>
                  <FieldList
                    list={fields}
                    parent={null}
                    all={fields}
                    depth={0}
                    ctx={{ savedKeys, open, toggle, focusKey, errors: showErrors ? errors : {}, setFields, fields, remove, duplicate, moveBy, addAfter, startDrag, drag, add, setPicker }}
                  />
                </div>
              ) : (
                <EmptyFields onAdd={(t) => add(t)} onMore={() => setPicker({ parent: null, index: 0 })} />
              )}

              {fields.length > 0 && <QuickAdd onAdd={(t) => add(t)} onMore={() => setPicker({ parent: null, index: fields.length })} />}
              <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                <span className="flex items-center gap-1">
                  <Kbd>↵</Kbd> {__('in a label adds the next field', 'brik-builder')}
                </span>
                <span className="flex items-center gap-1">
                  <Kbd>{modKey}</Kbd>
                  <Kbd>S</Kbd> {__('saves', 'brik-builder')}
                </span>
                <span className="flex items-center gap-1">
                  <Icon name="grip-vertical" size={12} /> {__('drag to reorder or nest in a repeater', 'brik-builder')}
                </span>
              </p>
            </>
          )}
        </div>

        <aside className="space-y-4 lg:sticky lg:top-[104px] lg:self-start">
          <Card title={__('Group settings', 'brik-builder')} icon="settings-2">
            <Row label={__('Title', 'brik-builder')} error={showErrors && titleError} htmlFor="fg-title">
              <input id="fg-title" ref={titleRef} autoFocus={isNew} className={cn('h-8 w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50', showErrors && titleError && 'border-destructive')} value={g.title} placeholder={__('e.g. Project details', 'brik-builder')} onChange={(e) => update({ title: e.target.value })} />
            </Row>
            <SwitchRow label={__('Active', 'brik-builder')} help={__('Inactive groups keep their fields but don’t show them.', 'brik-builder')} checked={!!g.active} onChange={(active) => update({ active })} />
          </Card>
          <Card title={__('Show this group when', 'brik-builder')} icon="map-pin">
            <LocationRules value={g.location || []} onChange={(location) => update({ location })} />
          </Card>
          <Card title={__('Presentation', 'brik-builder')} icon="panels-top-left">
            <Row label={__('Position', 'brik-builder')}>
              <Segment
                className="w-full"
                value={g.position || 'normal'}
                onChange={(position) => update({ position })}
                options={[
                  { value: 'after_title', label: __('After title', 'brik-builder') },
                  { value: 'normal', label: __('Below content', 'brik-builder') },
                  { value: 'side', label: __('Sidebar', 'brik-builder') },
                ]}
              />
            </Row>
            <Row label={__('Style', 'brik-builder')}>
              <Segment
                className="w-full"
                value={g.style || 'card'}
                onChange={(style) => update({ style })}
                options={[
                  { value: 'card', label: __('Card', 'brik-builder'), icon: 'square' },
                  { value: 'seamless', label: __('Seamless', 'brik-builder'), icon: 'square-dashed' },
                ]}
              />
            </Row>
            <Row label={__('Order', 'brik-builder')} help={__('Lower numbers show first when several groups share a screen.', 'brik-builder')}>
              <Input type="number" value={g.order ?? 0} onChange={(e) => update({ order: Number(e.target.value) || 0 })} />
            </Row>
          </Card>
        </aside>
      </div>

      {picker && (
        <TypePicker
          title={picker.parent ? __('Add a sub field', 'brik-builder') : __('Add a field', 'brik-builder')}
          onClose={() => setPicker(null)}
          onPick={(t) => {
            add(t, picker.parent, picker.index);
            setPicker(null);
          }}
        />
      )}
      {drag && <DragLayer drag={drag} />}
      <SaveBar
        dirty={dirty}
        saving={saving}
        isNew={isNew}
        onSave={save}
        onCancel={() => navigate('#/field-groups')}
        error={serverError || (showErrors && (errorCount || titleError) ? titleError || sprintf(_n('%d field needs attention', '%d fields need attention', errorCount, 'brik-builder'), errorCount) : '')}
      />
    </div>
  );
}

function DragLayer({ drag }) {
  const t = fieldType(drag.type);
  return createPortal(
    <>
      {drag.line && <div className="bk-drop-line" style={{ position: 'fixed', left: drag.line.left, width: drag.line.width, top: drag.line.top, zIndex: 100090 }} />}
      <div className="bk-drag-ghost flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium shadow-xl" style={{ left: drag.x + 12, top: drag.y - 16 }}>
        <Icon name={t.icon} size={15} />
        {drag.label || t.label}
      </div>
    </>,
    document.getElementById('brik-content-app')
  );
}

function FieldList({ list, parent, depth, ctx }) {
  if (!list.length) {
    return (
      <div data-drop-list={parent || ''} data-index={0} className={cn('m-2 flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-5 text-center', ctx.drag && 'border-brand/40 bg-brand/5')}>
        <p className="text-xs text-muted-foreground">{__('No sub fields yet. Drag fields here or add one.', 'brik-builder')}</p>
        <SubAdd parent={parent} index={0} ctx={ctx} />
      </div>
    );
  }
  return (
    <div className={cn(depth > 0 && 'divide-y divide-border')}>
      <div className="divide-y divide-border">
        {list.map((f, i) => (
          <FieldRow key={f.key} field={f} index={i} parent={parent} depth={depth} siblings={list} ctx={ctx} />
        ))}
      </div>
      {depth > 0 && (
        <div data-drop-list={parent} data-index={list.length} className="px-3 py-1.5">
          <SubAdd parent={parent} index={list.length} ctx={ctx} />
        </div>
      )}
    </div>
  );
}

function SubAdd({ parent, index, ctx }) {
  return (
    <div className="flex items-center gap-1">
      <Button variant="ghost" size="xs" icon="plus" className="text-muted-foreground" onClick={() => ctx.add('text', parent, index)} data-add-sub>
        {__('Add sub field', 'brik-builder')}
      </Button>
      <IconButton icon="shapes" size="icon-sm" label={__('Choose type…', 'brik-builder')} onClick={() => ctx.setPicker({ parent, index })} />
    </div>
  );
}

function FieldRow({ field, index, parent, depth, siblings, ctx }) {
  const t = fieldType(field.type);
  const isOpen = ctx.open.has(field.key);
  const err = ctx.errors[field.key];
  const isParent = PARENT_TYPES.includes(field.type);
  const dragging = ctx.drag && ctx.drag.key === field.key;
  const conds = (field.conditions || []).length > 0;
  const subs = subFields(field);

  return (
    <div data-drop-row={field.key} data-parent={parent || ''} data-index={index} data-accepts={isParent ? '1' : undefined} className={cn('relative bg-card', dragging && 'bk-dragging')} data-field-row={field.name || field.key}>
      <div
        data-row-head
        className={cn('group grid cursor-pointer grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-3 py-2.5 pr-3 transition-colors md:grid-cols-[auto_minmax(0,1fr)_150px_120px_88px] hover:bg-muted/40', isOpen && 'bg-muted/30', err && 'bg-destructive/[0.03]')}
        style={{ paddingLeft: 8 + depth * 20 }}
        onClick={(e) => {
          if (e.target.closest('button, input, [data-no-toggle]')) return;
          ctx.toggle(field.key);
        }}
      >
        <div className="flex items-center gap-1.5">
          <span
            className="flex h-8 w-5 cursor-grab touch-none items-center justify-center rounded text-muted-foreground/60 hover:bg-accent hover:text-foreground active:cursor-grabbing"
            onPointerDown={(e) => ctx.startDrag(e, field)}
            data-drag-handle
            data-no-toggle
            title={__('Drag to reorder', 'brik-builder')}
          >
            <Icon name="grip-vertical" size={15} />
          </span>
          <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-md border border-border bg-background shadow-xs', isOpen && 'border-foreground/20')}>
            <Icon name={t.icon} size={15} />
          </span>
        </div>
        <div className="min-w-0">
          <div className="flex min-w-0 items-center gap-1.5">
            <span className={cn('truncate text-[13px] font-medium', !field.label && 'text-muted-foreground italic')}>{field.label || __('(no label)', 'brik-builder')}</span>
            {field.required && <Badge tone="danger">{__('Required', 'brik-builder')}</Badge>}
            {conds && <Badge tone="brand" icon="git-branch">{__('Conditional', 'brik-builder')}</Badge>}
            {field.width && field.width !== 100 && <Badge tone="outline">{field.width}%</Badge>}
            {err && <Icon name="circle-alert" size={14} className="shrink-0 text-destructive" />}
          </div>
          <div className="truncate font-mono text-[11px] text-muted-foreground md:hidden">
            {field.name || '—'} · {t.label}
          </div>
        </div>
        <span className="hidden truncate font-mono text-[12px] text-muted-foreground md:block">{NO_VALUE.includes(field.type) ? '—' : field.name || '—'}</span>
        <span className="hidden items-center gap-1.5 truncate text-[12px] text-muted-foreground md:flex">
          {t.label}
          {isParent && subs.length > 0 && <span className="rounded bg-muted px-1 text-[10px]">{subs.length}</span>}
        </span>
        <div className="flex items-center justify-end gap-0.5">
          <span className="flex opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">
            <IconButton icon="copy" size="icon-sm" label={__('Duplicate', 'brik-builder')} onClick={() => ctx.duplicate(field)} />
            <IconButton icon="trash-2" size="icon-sm" label={__('Remove', 'brik-builder')} onClick={() => ctx.remove(field)} />
          </span>
          <Menu
            width={190}
            trigger={<IconButton icon="ellipsis-vertical" size="icon-sm" label={__('More', 'brik-builder')} />}
            items={[
              { icon: 'arrow-up', label: __('Move up', 'brik-builder'), onClick: () => ctx.moveBy(field, -1) },
              { icon: 'arrow-down', label: __('Move down', 'brik-builder'), onClick: () => ctx.moveBy(field, 1) },
              { icon: 'list-plus', label: __('Add field below', 'brik-builder'), onClick: () => ctx.addAfter(field.key) },
              { icon: 'copy', label: __('Duplicate', 'brik-builder'), onClick: () => ctx.duplicate(field) },
              '-',
              { icon: 'trash-2', label: __('Remove', 'brik-builder'), danger: true, onClick: () => ctx.remove(field) },
            ]}
          />
        </div>
      </div>

      {isOpen && (
        <div className="border-t border-border bg-muted/20 py-4 pr-4 bk-fade" style={{ paddingLeft: 16 + depth * 20 }}>
          <div className="rounded-lg border border-border bg-background p-4 shadow-xs">
            <FieldSettings
              field={field}
              siblings={siblings}
              error={err}
              autoFocus={ctx.focusKey === field.key}
              lockName={ctx.savedKeys.has(field.key)}
              onEnter={() => ctx.addAfter(field.key)}
              onChange={(next) => ctx.setFields((all) => updateField(all, field.key, () => next))}
            />
          </div>
        </div>
      )}

      {isParent && (
        <div className="border-t border-border bg-muted/10" style={{ paddingLeft: 28 + depth * 20 }}>
          <div className="border-l-2 border-brand/25">
            <div className="flex items-center gap-1.5 px-3 pt-2 text-[11px] font-medium text-muted-foreground">
              <Icon name="corner-down-right" size={12} />
              {sprintf(__('Sub fields of %s', 'brik-builder'), field.label || t.label)}
            </div>
            <FieldList list={subs} parent={field.key} depth={depth + 1} ctx={ctx} />
          </div>
        </div>
      )}
    </div>
  );
}

function QuickAdd({ onAdd, onMore }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5 rounded-xl border border-dashed border-border bg-card/60 p-2" data-quick-add>
      <span className="px-1.5 text-xs font-medium text-muted-foreground">{__('Add', 'brik-builder')}</span>
      {QUICK.map((type) => {
        const t = fieldType(type);
        return (
          <Button key={type} variant="outline" size="xs" onClick={() => onAdd(type)} data-quick={type}>
            <Icon name={t.icon} size={13} />
            {t.label}
          </Button>
        );
      })}
      <Button variant="ghost" size="xs" icon="shapes" onClick={onMore} data-more-types>
        {__('More types…', 'brik-builder')}
      </Button>
    </div>
  );
}

function EmptyFields({ onAdd, onMore }) {
  return (
    <div className="rounded-xl border border-dashed border-border bg-card px-6 py-10 text-center">
      <span className="mx-auto flex size-11 items-center justify-center rounded-full bg-muted">
        <Icon name="text-cursor-input" size={20} className="text-muted-foreground" />
      </span>
      <p className="mt-3 text-sm font-medium">{__('Add your first field', 'brik-builder')}</p>
      <p className="mx-auto mt-1 max-w-sm text-xs text-muted-foreground">{__('Pick a common type below or browse all of them. You can change the type at any time.', 'brik-builder')}</p>
      <div className="mt-5 flex flex-wrap justify-center gap-2">
        {QUICK.map((type) => {
          const t = fieldType(type);
          return (
            <button key={type} type="button" onClick={() => onAdd(type)} data-quick={type} className="flex w-[84px] flex-col items-center gap-1.5 rounded-lg border border-border bg-background p-3 text-xs font-medium shadow-xs transition-all hover:-translate-y-px hover:border-foreground/20 hover:shadow-sm cursor-pointer">
              <Icon name={t.icon} size={18} />
              {t.label}
            </button>
          );
        })}
      </div>
      <Button variant="ghost" size="sm" icon="shapes" className="mt-3" onClick={onMore} data-more-types>
        {__('Browse all field types', 'brik-builder')}
      </Button>
    </div>
  );
}
