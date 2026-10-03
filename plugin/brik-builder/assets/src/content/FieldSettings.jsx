// Inline settings for one field: general, type options, conditional logic; plus the type picker.
import { useState, useEffect, useRef, useMemo, __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, IconButton, Input, Textarea, Select, Tabs } from '../builder/ui.jsx';
import { Dialog, RefInput, Row, SwitchRow, SwitchBtn, Segment, MultiSelect, Badge, Kbd } from './primitives.jsx';
import { useStore, fieldType, slugify, CATEGORIES, CHOICE_TYPES, NO_VALUE, PARENT_TYPES } from './lib.js';
import { postTypeOptions, taxonomyOptions } from './LocationRules.jsx';

const WIDTHS = [25, 33, 50, 66, 75, 100];
const PLACEHOLDER_TYPES = ['text', 'textarea', 'email', 'url', 'password', 'number'];
const NO_DEFAULT = ['image', 'file', 'gallery', 'link', 'post_object', 'relationship', 'user', 'map', 'repeater', 'group', 'tab', 'message', 'oembed', 'taxonomy'];

export function FieldSettings({ field, onChange, siblings, error, autoFocus, onEnter, lockName }) {
  const [tab, setTab] = useState('general');
  const [picking, setPicking] = useState(false);
  const type = fieldType(field.type);
  const opts = (type.options || []).filter((o) => o.key !== 'sub_fields');
  const set = (patch) => onChange({ ...field, ...patch });
  const setOpt = (patch) => onChange({ ...field, options: { ...(field.options || {}), ...patch } });
  const condCount = (field.conditions || []).reduce((n, g) => n + g.length, 0);
  const labelRef = useRef(null);

  useEffect(() => {
    if (autoFocus && labelRef.current) {
      labelRef.current.focus();
      labelRef.current.select();
    }
  }, [autoFocus]);

  const autoName = !field.name || (!lockName && field.name === slugify(field.label));
  const tabs = [
    { value: 'general', label: __('General', 'brik-builder'), icon: 'settings-2' },
    opts.length ? { value: 'options', label: __('Options', 'brik-builder'), icon: 'sliders-horizontal' } : null,
    !NO_VALUE.includes(field.type) || field.type === 'message' ? { value: 'logic', label: condCount ? sprintf(__('Conditions (%d)', 'brik-builder'), condCount) : __('Conditions', 'brik-builder'), icon: 'git-branch' } : null,
  ].filter(Boolean);

  return (
    <div className="space-y-4" data-field-settings={field.key}>
      <Tabs tabs={tabs} value={tabs.some((t) => t.value === tab) ? tab : 'general'} onChange={setTab} className="w-full sm:w-auto" />
      {(tab === 'general' || !tabs.some((t) => t.value === tab)) && (
        <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Row label={__('Label', 'brik-builder')} error={error && error.label}>
              <RefInput
                ref={labelRef}
                data-field-label
                value={field.label}
                placeholder={__('e.g. Price', 'brik-builder')}
                onChange={(e) => {
                  const label = e.target.value;
                  set(autoName && !NO_VALUE.includes(field.type) ? { label, name: slugify(label) } : { label });
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey && !e.metaKey && !e.ctrlKey) {
                    e.preventDefault();
                    onEnter && onEnter();
                  }
                }}
              />
            </Row>
            {!NO_VALUE.includes(field.type) && (
              <Row label={__('Name', 'brik-builder')} error={error && error.name} help={field.name ? <span className="font-mono">{`{field:${field.name}}`}</span> : __('Meta key and dynamic tag name.', 'brik-builder')}>
                <Input data-field-name className={cn('font-mono', error && error.name && 'border-destructive')} value={field.name} placeholder="price" onChange={(e) => set({ name: e.target.value.toLowerCase().replace(/[\s-]+/g, '_') })} />
              </Row>
            )}
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <Row label={__('Type', 'brik-builder')}>
              <button type="button" onClick={() => setPicking(true)} className="flex h-8 w-full items-center gap-2 rounded-md border border-input bg-background px-2.5 text-left text-sm shadow-xs hover:bg-accent/50 cursor-pointer" data-type-trigger>
                <Icon name={type.icon} size={15} />
                <span className="flex-1 truncate">{type.label}</span>
                <span className="text-xs text-muted-foreground">{__('Change', 'brik-builder')}</span>
              </button>
            </Row>
            <Row label={__('Width', 'brik-builder')}>
              <Segment size="sm" className="w-full" value={field.width || 100} onChange={(width) => set({ width: Number(width) })} options={WIDTHS.map((w) => ({ value: w, label: `${w}%` }))} />
            </Row>
          </div>
          {field.type !== 'message' && (
            <Row label={__('Instructions', 'brik-builder')} help={__('Shown to editors under the field label.', 'brik-builder')}>
              <Textarea rows={2} className="min-h-14" value={field.instructions || ''} onChange={(e) => set({ instructions: e.target.value })} />
            </Row>
          )}
          {!NO_VALUE.includes(field.type) && (
            <div className="grid gap-4 sm:grid-cols-2">
              {!NO_DEFAULT.includes(field.type) && (
                <Row label={__('Default value', 'brik-builder')}>
                  <DefaultInput field={field} onChange={(v) => set({ default: v })} />
                </Row>
              )}
              {PLACEHOLDER_TYPES.includes(field.type) && (
                <Row label={__('Placeholder', 'brik-builder')}>
                  <Input value={field.placeholder || ''} onChange={(e) => set({ placeholder: e.target.value })} />
                </Row>
              )}
            </div>
          )}
          {!NO_VALUE.includes(field.type) && field.type !== 'group' && (
            <div className="rounded-lg border border-border px-3 py-2.5">
              <SwitchRow label={__('Required', 'brik-builder')} help={__('Editors can’t publish without a value.', 'brik-builder')} checked={!!field.required} onChange={(required) => set({ required })} />
            </div>
          )}
        </div>
      )}
      {tab === 'options' && <TypeOptions field={field} options={opts} setOpt={setOpt} />}
      {tab === 'logic' && <Conditions field={field} siblings={siblings} onChange={(conditions) => set({ conditions })} />}
      {picking && (
        <TypePicker
          current={field.type}
          onClose={() => setPicking(false)}
          onPick={(t) => {
            setPicking(false);
            const options = { ...(field.options || {}) };
            if (PARENT_TYPES.includes(t) && !options.sub_fields) options.sub_fields = [];
            if (CHOICE_TYPES.includes(t) && !options.choices) options.choices = [];
            set({ type: t, options });
          }}
        />
      )}
    </div>
  );
}

function DefaultInput({ field, onChange }) {
  const v = field.default ?? '';
  if (field.type === 'toggle') return <div className="flex h-8 items-center"><SwitchBtn checked={!!v && v !== '0'} onChange={(b) => onChange(b ? 1 : 0)} label={__('Default value', 'brik-builder')} /></div>;
  if (CHOICE_TYPES.includes(field.type)) {
    const choices = (field.options && field.options.choices) || [];
    return <Select value={String(v)} onChange={onChange} options={choices.map((c) => ({ value: c.value, label: c.label || c.value }))} placeholder={__('None', 'brik-builder')} />;
  }
  if (field.type === 'color') {
    return (
      <div className="flex gap-1.5">
        <input type="color" value={/^#[0-9a-f]{6}$/i.test(v) ? v : '#000000'} onChange={(e) => onChange(e.target.value)} className="h-8 w-9 shrink-0 cursor-pointer rounded-md border border-input p-0.5" />
        <Input value={v} onChange={(e) => onChange(e.target.value)} placeholder="#000000" className="font-mono" />
      </div>
    );
  }
  const inputType = { number: 'number', range: 'number', date: 'date', time: 'time', datetime: 'datetime-local' }[field.type] || 'text';
  if (field.type === 'textarea' || field.type === 'wysiwyg') return <Textarea rows={2} className="min-h-14" value={v} onChange={(e) => onChange(e.target.value)} />;
  return <Input type={inputType} value={v} onChange={(e) => onChange(inputType === 'number' && e.target.value !== '' ? Number(e.target.value) : e.target.value)} />;
}

/* ------------------------------------------------------------------------
 * Type specific options from the field type's schema.
 * ---------------------------------------------------------------------- */

const ROLE_OPTIONS = [
  { value: 'administrator', label: __('Administrator', 'brik-builder') },
  { value: 'editor', label: __('Editor', 'brik-builder') },
  { value: 'author', label: __('Author', 'brik-builder') },
  { value: 'contributor', label: __('Contributor', 'brik-builder') },
  { value: 'subscriber', label: __('Subscriber', 'brik-builder') },
];

function TypeOptions({ field, options, setOpt }) {
  const s = useStore();
  const o = field.options || {};
  const toggles = options.filter((x) => x.type === 'toggle');
  const choices = options.find((x) => x.type === 'choices');
  const simple = options.filter((x) => x !== choices && x.type !== 'toggle');
  const wide = (opt) => ['textarea', 'post_types', 'terms', 'roles'].includes(opt.type);
  const list = (v) => (Array.isArray(v) ? v : v ? [v] : []);

  const control = (opt) => {
    const v = o[opt.key];
    const def = opt.default;
    switch (opt.type) {
      case 'number':
        return <Input type="number" min={opt.min} max={opt.max} value={v ?? ''} placeholder={def !== '' && def != null ? String(def) : opt.placeholder} onChange={(e) => setOpt({ [opt.key]: e.target.value === '' ? '' : Number(e.target.value) })} />;
      case 'select':
        return <Select value={v ?? def ?? ''} onChange={(val) => setOpt({ [opt.key]: val })} options={opt.choices || []} />;
      case 'textarea':
        return <Textarea rows={3} value={v ?? ''} onChange={(e) => setOpt({ [opt.key]: e.target.value })} />;
      case 'post_types':
        return <MultiSelect options={postTypeOptions(s)} value={list(v)} onChange={(val) => setOpt({ [opt.key]: val })} placeholder={__('Any post type', 'brik-builder')} />;
      case 'taxonomy':
        return <Select value={v ?? def ?? ''} onChange={(val) => setOpt({ [opt.key]: val })} options={taxonomyOptions(s)} placeholder={__('Choose a taxonomy', 'brik-builder')} />;
      case 'roles':
        return <MultiSelect options={ROLE_OPTIONS} value={list(v)} onChange={(val) => setOpt({ [opt.key]: val })} placeholder={__('Any role', 'brik-builder')} />;
      case 'role':
        return <Select value={v ?? ''} onChange={(val) => setOpt({ [opt.key]: val })} options={ROLE_OPTIONS} placeholder={__('Any role', 'brik-builder')} />;
      case 'terms':
        return <TermsInput value={list(v)} onChange={(val) => setOpt({ [opt.key]: val })} />;
      default:
        return <Input value={Array.isArray(v) ? v.join(', ') : v ?? ''} placeholder={def !== '' && def != null ? String(def) : opt.placeholder} onChange={(e) => setOpt({ [opt.key]: e.target.value })} />;
    }
  };

  return (
    <div className="space-y-4" data-type-options>
      {choices && (
        <Row label={choices.label} help={choices.help}>
          <ChoicesEditor value={o.choices || []} onChange={(c) => setOpt({ choices: c })} />
        </Row>
      )}
      {simple.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2">
          {simple.map((opt) => (
            <Row key={opt.key} label={opt.label} help={opt.help} className={wide(opt) ? 'sm:col-span-2' : ''}>
              {control(opt)}
            </Row>
          ))}
        </div>
      )}
      {toggles.length > 0 && (
        <div className="divide-y divide-border rounded-lg border border-border">
          {toggles.map((opt) => (
            <div key={opt.key} className="px-3 py-2.5">
              <SwitchRow label={opt.label} help={opt.help} checked={!!(o[opt.key] ?? opt.default)} onChange={(v) => setOpt({ [opt.key]: v })} />
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

/** "taxonomy:slug" chips, typed and confirmed with Enter or comma. */
function TermsInput({ value, onChange }) {
  const [text, setText] = useState('');
  const commit = () => {
    const parts = text.split(',').map((t) => t.trim()).filter(Boolean);
    if (parts.length) onChange(Array.from(new Set([...value, ...parts])));
    setText('');
  };
  return (
    <div className="flex min-h-8 flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-1.5 py-1 shadow-xs">
      {value.map((t) => (
        <span key={t} className="inline-flex h-6 items-center gap-1 rounded-md border border-border bg-muted/60 pr-0.5 pl-2 font-mono text-[11px]">
          {t}
          <button type="button" aria-label={__('Remove', 'brik-builder')} className="flex size-5 items-center justify-center rounded text-muted-foreground hover:text-foreground cursor-pointer" onClick={() => onChange(value.filter((x) => x !== t))}>
            <Icon name="x" size={11} />
          </button>
        </span>
      ))}
      <input
        className="h-6 min-w-32 flex-1 bg-transparent px-1 text-[13px] outline-none"
        value={text}
        placeholder={value.length ? '' : 'category:news'}
        onChange={(e) => setText(e.target.value)}
        onBlur={commit}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            commit();
          }
          if (e.key === 'Backspace' && !text && value.length) onChange(value.slice(0, -1));
        }}
      />
    </div>
  );
}

function ChoicesEditor({ value, onChange }) {
  const [bulk, setBulk] = useState(false);
  const [text, setText] = useState('');
  const [dragIdx, setDragIdx] = useState(null);
  const [overIdx, setOverIdx] = useState(null);
  const refs = useRef([]);
  const [focus, setFocus] = useState(null);

  useEffect(() => {
    if (focus !== null && refs.current[focus]) refs.current[focus].focus();
  }, [focus, value.length]);

  const setRow = (i, patch) => onChange(value.map((c, j) => (j === i ? { ...c, ...patch } : c)));
  const add = (at = value.length) => {
    const next = value.slice();
    next.splice(at, 0, { value: '', label: '' });
    onChange(next);
    setFocus(at);
  };
  const toText = () => value.map((c) => (c.value === slugify(c.label) || c.value === c.label ? c.label : `${c.value} : ${c.label}`)).join('\n');
  const fromText = (t) =>
    t
      .split('\n')
      .map((l) => l.trim())
      .filter(Boolean)
      .map((l) => {
        const m = l.split(/\s*:\s*/);
        if (m.length > 1) return { value: m[0], label: m.slice(1).join(' : ') };
        return { value: slugify(l), label: l };
      });

  if (bulk) {
    return (
      <div className="space-y-2" data-bulk>
        <Textarea autoFocus rows={7} className="font-mono text-[12px]" value={text} onChange={(e) => setText(e.target.value)} placeholder={'red : Red\ngreen : Green\nBlue'} />
        <div className="flex items-center justify-between gap-2">
          <p className="text-xs text-muted-foreground">{__('One choice per line. Use “value : label”, or just a label.', 'brik-builder')}</p>
          <div className="flex gap-2">
            <Button variant="ghost" size="xs" onClick={() => setBulk(false)}>
              {__('Cancel', 'brik-builder')}
            </Button>
            <Button
              size="xs"
              onClick={() => {
                onChange(fromText(text));
                setBulk(false);
              }}
            >
              {__('Apply', 'brik-builder')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-border" data-choices>
      {value.length > 0 && (
        <div className="grid grid-cols-[20px_1fr_1fr_28px] gap-2 border-b border-border px-2 py-1.5 text-[11px] font-medium text-muted-foreground">
          <span />
          <span>{__('Label', 'brik-builder')}</span>
          <span>{__('Value', 'brik-builder')}</span>
          <span />
        </div>
      )}
      <div className="divide-y divide-border">
        {value.map((c, i) => (
          <div
            key={i}
            className={cn('relative grid grid-cols-[20px_1fr_1fr_28px] items-center gap-2 px-2 py-1.5', dragIdx === i && 'opacity-40')}
            onDragOver={(e) => {
              if (dragIdx === null) return;
              e.preventDefault();
              setOverIdx(i);
            }}
            onDrop={(e) => {
              e.preventDefault();
              if (dragIdx === null || dragIdx === i) return;
              const next = value.slice();
              const [m] = next.splice(dragIdx, 1);
              next.splice(i, 0, m);
              onChange(next);
              setDragIdx(null);
              setOverIdx(null);
            }}
          >
            {overIdx === i && dragIdx !== null && dragIdx !== i && <span className={cn('bk-drop-line', dragIdx < i ? 'bottom-0' : 'top-0')} />}
            <span
              draggable
              onDragStart={(e) => {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(i));
                setDragIdx(i);
              }}
              onDragEnd={() => (setDragIdx(null), setOverIdx(null))}
              className="flex cursor-grab items-center justify-center text-muted-foreground hover:text-foreground"
              title={__('Drag to reorder', 'brik-builder')}
            >
              <Icon name="grip-vertical" size={14} />
            </span>
            <RefInput
              ref={(el) => (refs.current[i] = el)}
              className="h-7"
              value={c.label}
              placeholder={__('Label', 'brik-builder')}
              onChange={(e) => {
                const label = e.target.value;
                const auto = !c.value || c.value === slugify(c.label);
                setRow(i, auto ? { label, value: slugify(label) } : { label });
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  e.stopPropagation();
                  add(i + 1);
                }
                if (e.key === 'Backspace' && !c.label && !c.value) {
                  e.preventDefault();
                  onChange(value.filter((_, j) => j !== i));
                  setFocus(Math.max(0, i - 1));
                }
              }}
            />
            <Input className="h-7 font-mono text-[12px]" value={c.value} placeholder="value" onChange={(e) => setRow(i, { value: e.target.value })} />
            <IconButton icon="x" size="icon-sm" label={__('Remove choice', 'brik-builder')} onClick={() => onChange(value.filter((_, j) => j !== i))} />
          </div>
        ))}
      </div>
      {!value.length && <p className="px-3 py-3 text-xs text-muted-foreground">{__('No choices yet. Add them one by one or paste a list.', 'brik-builder')}</p>}
      <div className="flex items-center gap-1 border-t border-border bg-muted/30 px-2 py-1.5">
        <Button variant="ghost" size="xs" icon="plus" onClick={() => add()} data-add-choice>
          {__('Add choice', 'brik-builder')}
        </Button>
        <Button
          variant="ghost"
          size="xs"
          icon="clipboard-paste"
          onClick={() => {
            setText(toText());
            setBulk(true);
          }}
          data-bulk-choices
        >
          {__('Bulk edit', 'brik-builder')}
        </Button>
        <span className="ml-auto hidden items-center gap-1 text-[11px] text-muted-foreground sm:flex">
          <Kbd>↵</Kbd> {__('adds the next choice', 'brik-builder')}
        </span>
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Conditional logic.
 * ---------------------------------------------------------------------- */

const OPERATORS = [
  { value: '==', label: __('is equal to', 'brik-builder') },
  { value: '!=', label: __('is not equal to', 'brik-builder') },
  { value: 'contains', label: __('contains', 'brik-builder') },
  { value: 'empty', label: __('is empty', 'brik-builder') },
  { value: '!empty', label: __('has any value', 'brik-builder') },
];

function Conditions({ field, siblings, onChange }) {
  const groups = field.conditions || [];
  const candidates = siblings.filter((f) => f.key !== field.key && !NO_VALUE.includes(f.type) && !PARENT_TYPES.includes(f.type));
  const blank = () => ({ field: candidates[0] ? candidates[0].key : '', operator: candidates[0] && candidates[0].type === 'toggle' ? '!empty' : '==', value: '' });
  const setRule = (gi, ri, patch) => onChange(groups.map((g, i) => (i === gi ? g.map((r, j) => (j === ri ? { ...r, ...patch } : r)) : g)));
  const remove = (gi, ri) => onChange(groups.map((g, i) => (i === gi ? g.filter((_, j) => j !== ri) : g)).filter((g) => g.length));

  if (!candidates.length) {
    return (
      <p className="flex items-start gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
        <Icon name="info" size={14} className="mt-px shrink-0" />
        {__('Conditions compare against other fields at the same level. Add another field first.', 'brik-builder')}
      </p>
    );
  }

  return (
    <div className="space-y-3" data-conditions>
      <SwitchRow
        label={__('Show this field only when…', 'brik-builder')}
        help={groups.length ? __('All rules in a group must match; any group can match.', 'brik-builder') : __('Hide or show this field based on other fields’ values.', 'brik-builder')}
        checked={groups.length > 0}
        onChange={(on) => onChange(on ? [[blank()]] : [])}
      />
      {groups.map((g, gi) => (
        <div key={gi}>
          {gi > 0 && (
            <div className="my-2 flex items-center gap-2">
              <span className="h-px flex-1 bg-border" />
              <span className="text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">{__('or', 'brik-builder')}</span>
              <span className="h-px flex-1 bg-border" />
            </div>
          )}
          <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-2.5">
            {g.map((r, ri) => {
              const target = candidates.find((c) => c.key === r.field);
              const choices = target && target.options && target.options.choices;
              const needsValue = !['empty', '!empty'].includes(r.operator);
              return (
                <div key={ri} className="flex flex-wrap items-center gap-1.5" data-condition>
                  <span className="w-9 shrink-0 text-right text-[11px] font-medium text-muted-foreground">{ri === 0 ? __('If', 'brik-builder') : __('and', 'brik-builder')}</span>
                  <Select className="h-8 min-w-0 flex-1 basis-32 text-[13px]" value={r.field} onChange={(v) => setRule(gi, ri, { field: v, value: '' })} options={candidates.map((c) => ({ value: c.key, label: c.label || c.name || c.key }))} />
                  <Select className="h-8 min-w-0 flex-1 basis-28 text-[13px]" value={r.operator} onChange={(v) => setRule(gi, ri, { operator: v })} options={OPERATORS} />
                  {needsValue &&
                    (target && target.type === 'toggle' ? (
                      <Select className="h-8 min-w-0 flex-1 basis-24 text-[13px]" value={String(r.value)} onChange={(v) => setRule(gi, ri, { value: v })} options={[{ value: '1', label: __('On', 'brik-builder') }, { value: '0', label: __('Off', 'brik-builder') }]} placeholder={__('Choose…', 'brik-builder')} />
                    ) : choices && choices.length ? (
                      <Select className="h-8 min-w-0 flex-1 basis-28 text-[13px]" value={r.value} onChange={(v) => setRule(gi, ri, { value: v })} options={choices.map((c) => ({ value: c.value, label: c.label || c.value }))} placeholder={__('Choose…', 'brik-builder')} />
                    ) : (
                      <Input className="h-8 min-w-0 flex-1 basis-28 text-[13px]" value={r.value} placeholder={__('Value', 'brik-builder')} onChange={(e) => setRule(gi, ri, { value: e.target.value })} />
                    ))}
                  <IconButton icon="x" size="icon-sm" label={__('Remove rule', 'brik-builder')} onClick={() => remove(gi, ri)} />
                </div>
              );
            })}
            <Button variant="ghost" size="xs" icon="plus" className="ml-10 text-muted-foreground" onClick={() => onChange(groups.map((x, i) => (i === gi ? [...x, blank()] : x)))}>
              {__('And', 'brik-builder')}
            </Button>
          </div>
        </div>
      ))}
      {groups.length > 0 && (
        <Button variant="outline" size="xs" icon="plus" onClick={() => onChange([...groups, [blank()]])}>
          {__('Or another group', 'brik-builder')}
        </Button>
      )}
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Type picker.
 * ---------------------------------------------------------------------- */

export function TypePicker({ current, onPick, onClose, title }) {
  const types = useStore((s) => s.field_types);
  const [q, setQ] = useState('');
  const shown = useMemo(() => {
    const term = q.trim().toLowerCase();
    return types.filter((t) => !term || `${t.label} ${t.type} ${t.description}`.toLowerCase().includes(term));
  }, [q, types]);

  return (
    <Dialog size="lg" icon="shapes" title={title || __('Choose a field type', 'brik-builder')} description={__('Every type stores its value as post meta and can be shown with Brik dynamic data.', 'brik-builder')} onClose={onClose}>
      <div className="relative mb-4">
        <Icon name="search" size={14} className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
        <Input
          autoFocus
          className="h-9 pl-8"
          placeholder={__('Search types…', 'brik-builder')}
          value={q}
          onChange={(e) => setQ(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && shown[0]) {
              e.preventDefault();
              onPick(shown[0].type);
            }
          }}
        />
      </div>
      <div className="space-y-5" data-type-picker>
        {CATEGORIES.map((cat) => {
          const list = shown.filter((t) => (t.category || 'advanced') === cat.id);
          if (!list.length) return null;
          return (
            <div key={cat.id}>
              <h3 className="mb-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">{cat.label}</h3>
              <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {list.map((t) => (
                  <button
                    key={t.type}
                    type="button"
                    data-type={t.type}
                    onClick={() => onPick(t.type)}
                    className={cn('flex items-start gap-3 rounded-lg border p-3 text-left transition-colors cursor-pointer', current === t.type ? 'border-brand/50 bg-brand/5 ring-1 ring-brand/30' : 'border-border hover:border-foreground/20 hover:bg-muted/40')}
                  >
                    <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-md border', current === t.type ? 'border-brand/30 bg-background text-brand' : 'border-border bg-background')}>
                      <Icon name={t.icon} size={16} />
                    </span>
                    <span className="min-w-0">
                      <span className="flex items-center gap-1.5 text-[13px] font-medium">
                        {t.label}
                        {current === t.type && <Badge tone="brand">{__('Current', 'brik-builder')}</Badge>}
                      </span>
                      <span className="mt-0.5 block text-xs leading-snug text-muted-foreground">{t.description}</span>
                    </span>
                  </button>
                ))}
              </div>
            </div>
          );
        })}
        {!shown.length && <p className="py-8 text-center text-sm text-muted-foreground">{__('No field type matches.', 'brik-builder')}</p>}
      </div>
    </Dialog>
  );
}
