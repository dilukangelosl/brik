// Settings field controls. Each control gets { value, onChange, field, placeholder }.
import { useState, useEffect, useRef, useMemo } from './wp.js';
import { useStore, getState, setAttr, attrKey, replaceNode, effectiveDevice } from './store.js';
import * as T from './tree.js';
import { search, library as loadLibrary } from './api.js';
import { Icon, iconNames, brandNames, iconSvg } from './icons.js';
import { loadIconTags } from './api.js';
import { MenuTreeControl } from './menutree.jsx';
import { Slot, getControl } from './registry.js';
import { cn, Input, Textarea, Select, Switch, Segmented, Popover, Button, IconButton, Label, Tabs, inputClass } from './ui.jsx';

/* ------------------------------------------------------------------------
 * Visibility rules.
 * ---------------------------------------------------------------------- */

export function visible(field, attrs, fields) {
  if (!field.show_if) return true;
  for (const [key, cond] of Object.entries(field.show_if)) {
    let v = attrs[key];
    if ((v === undefined || v === '') && fields[key]) v = fields[key].default;
    if (cond === '!') {
      if (!v || (Array.isArray(v) && !v.length) || (typeof v === 'object' && !Array.isArray(v) && !v.url)) return false;
    } else if (Array.isArray(v) || (typeof v === 'string' && v.includes(',') && !Array.isArray(cond))) {
      // Multi-value fields match when any selected value matches.
      const values = Array.isArray(v) ? v.map(String) : v.split(',').map((x) => x.trim());
      const wanted = Array.isArray(cond) ? cond.map(String) : [String(cond)];
      if (!values.some((x) => wanted.includes(x))) return false;
    } else if (Array.isArray(cond)) {
      if (!cond.map(String).includes(String(v ?? ''))) return false;
    } else if (String(v ?? '') !== String(cond)) {
      return false;
    }
  }
  return true;
}

/** Value inherited from a wider device (or the normal state) shown as a placeholder. */
function inherited(attrs, key, k) {
  if (k === key) return undefined;
  const state = k.split('@')[1];
  if (state === 'mobile') return attrs[`${key}@tablet`] ?? attrs[key];
  return attrs[key];
}

/* ------------------------------------------------------------------------
 * A field row bound to a node attribute.
 * ---------------------------------------------------------------------- */

export function NodeField({ node, fkey, field, def }) {
  const device = useStore((s) => effectiveDevice(s));
  const mode = useStore((s) => s.mode);
  const k = attrKey(fkey, field);
  const attrs = node.attrs || {};
  let value = attrs[k];
  const fallback = inherited(attrs, fkey, k);
  const hasValue = value !== undefined && value !== '';

  const onChange = (v) => {
    if (fkey === 'columns' && node.type === 'row' && k === 'columns') {
      replaceNode(node.id, T.restructure(node, v || '1'), 'Change columns');
      return;
    }
    setAttr(node.id, fkey, v, field);
  };

  const scoped = k !== fkey;
  return (
    <div className="bk-field space-y-1.5" data-type={field.type}>
      <FieldLabel field={field} scoped={scoped} device={device} mode={mode} hasValue={hasValue} onReset={() => onChange(undefined)} node={node} fkey={fkey} />
      <Control field={field} value={value ?? (scoped ? undefined : undefined)} placeholder={fallback} onChange={onChange} node={node} fkey={fkey} def={def} />
      {field.description && <p className="text-[11px] leading-snug text-muted-foreground">{field.description}</p>}
    </div>
  );
}

function FieldLabel({ field, scoped, device, mode, hasValue, onReset, node, fkey }) {
  if (field.type === 'toggle') return null;
  return (
    <div className="flex min-h-5 items-center justify-between gap-2">
      <Label className="flex items-center gap-1.5">
        {field.label}
        {scoped && mode === 'hover' && <Icon name="mouse-pointer-2" size={12} className="text-brand" />}
        {scoped && mode !== 'hover' && <Icon name={device === 'tablet' ? 'tablet' : 'smartphone'} size={12} className="text-brand" />}
      </Label>
      <span className="flex items-center gap-1.5">
        <Slot name="fieldLabel" node={node} fkey={fkey} field={field} />
        {hasValue && (
          <button type="button" className="text-muted-foreground hover:text-foreground cursor-pointer" title="Reset" onClick={onReset}>
            <Icon name="rotate-ccw" size={12} />
          </button>
        )}
      </span>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Control switch.
 * ---------------------------------------------------------------------- */

export function Control(props) {
  const { field } = props;
  const C = getControl(field.type) || CONTROLS[field.type] || TextControl;
  return <C {...props} />;
}

const CONTROLS = {
  text: TextControl,
  textarea: TextareaControl,
  richtext: RichTextControl,
  code: CodeControl,
  number: NumberControl,
  range: RangeControl,
  unit: UnitControl,
  select: SelectControl,
  toggle: ToggleControl,
  color: ColorControl,
  gradient: GradientControl,
  image: ImageControl,
  gallery: GalleryControl,
  video: VideoControl,
  link: LinkControl,
  icon: IconControl,
  align: AlignControl,
  spacing: SpacingControl,
  font: FontControl,
  shadow: ShadowControl,
  date: DateControl,
  devices: DevicesControl,
  columns: ColumnsControl,
  menu: MenuControl,
  post_type: PostTypeControl,
  taxonomy: TaxonomyControl,
  library: LibraryControl,
  repeater: RepeaterControl,
  menu_tree: MenuTreeControl,
  multiselect: MultiSelectControl,
};

function MultiSelectControl({ value, onChange, field }) {
  const list = Array.isArray(value) ? value.map(String) : typeof value === 'string' && value ? value.split(',').map((v) => v.trim()) : [];
  const toggle = (v) => {
    const next = list.includes(v) ? list.filter((x) => x !== v) : [...list, v];
    onChange(next.length ? next : undefined);
  };
  return (
    <div className="flex flex-wrap gap-1.5">
      {(field.options || []).map((o) => (
        <Button key={o.value} size="xs" variant="outline" active={list.includes(o.value)} icon={list.includes(o.value) ? 'check' : undefined} onClick={() => toggle(o.value)}>
          {o.label}
        </Button>
      ))}
    </div>
  );
}

/** Debounced local state for text inputs so typing stays smooth. */
function useDraft(value, onChange, delay = 250) {
  const [draft, setDraft] = useState(value ?? '');
  const timer = useRef(null);
  const last = useRef(value);
  useEffect(() => {
    if (value !== last.current) {
      setDraft(value ?? '');
      last.current = value;
    }
  }, [value]);
  const update = (v) => {
    setDraft(v);
    clearTimeout(timer.current);
    timer.current = setTimeout(() => {
      last.current = v;
      onChange(v);
    }, delay);
  };
  return [draft, update];
}

function DynamicTags({ onInsert }) {
  const tags = useStore((s) => (s.schema ? s.schema.tags : {}));
  const fields = useStore((s) => (s.schema ? s.schema.fields || [] : []));
  return (
    <Popover
      align="end"
      width={240}
      trigger={<IconButton icon="braces" label="Insert dynamic content" size="icon-sm" variant="outline" />}
    >
      {(close) => (
        <div className="max-h-64 space-y-0.5 overflow-y-auto">
          <p className="mb-2 text-xs text-muted-foreground">Dynamic content</p>
          {Object.entries(tags).map(([tag, label]) => (
            <button
              key={tag}
              type="button"
              className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
              onClick={() => {
                onInsert(`{${tag === 'meta:KEY' ? 'meta:' + (window.prompt('Custom field key') || 'key') : tag}}`);
                close();
              }}
            >
              <span>{label}</span>
              <code className="text-[10px] text-muted-foreground">{`{${tag}}`}</code>
            </button>
          ))}
          {fields.length > 0 && <p className="mb-1 mt-3 text-xs text-muted-foreground">Custom fields</p>}
          {fields.map((f) => (
            <button
              key={`${f.group}-${f.name}`}
              type="button"
              className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
              onClick={() => {
                onInsert(`{field:${f.name}${['image', 'file'].includes(f.type) ? '|url' : ''}}`);
                close();
              }}
            >
              <span className="truncate">{f.label}</span>
              <code className="text-[10px] text-muted-foreground">{`{field:${f.name}}`}</code>
            </button>
          ))}
        </div>
      )}
    </Popover>
  );
}

function TextControl({ value, onChange, field, placeholder, fkey }) {
  const [draft, update] = useDraft(value, onChange);
  const listId = field.suggestions && field.suggestions.length ? `brik-sugg-${fkey || 'f'}` : undefined;
  return (
    <div className="flex gap-1.5">
      <Input value={draft} list={listId} placeholder={placeholder ?? field.placeholder ?? ''} onChange={(e) => update(e.target.value)} />
      {listId && (
        <datalist id={listId}>
          {field.suggestions.map((sname) => (
            <option key={sname} value={sname} />
          ))}
        </datalist>
      )}
      <Slot name="textAddons" value={draft} onChange={update} field={field} />
      <DynamicTags onInsert={(t) => update(`${draft}${t}`)} />
    </div>
  );
}

function TextareaControl({ value, onChange, field, placeholder }) {
  const [draft, update] = useDraft(value, onChange);
  return <Textarea rows={4} value={draft} placeholder={placeholder ?? field.placeholder ?? ''} onChange={(e) => update(e.target.value)} />;
}

function CodeControl({ value, onChange, field }) {
  const [draft, update] = useDraft(value, onChange, 400);
  return (
    <Textarea
      rows={8}
      spellCheck={false}
      className="font-mono text-xs"
      value={draft}
      placeholder={field.language === 'css' ? 'selector { }' : '<div>…</div>'}
      onKeyDown={(e) => {
        if (e.key === 'Tab') {
          e.preventDefault();
          const t = e.target;
          const v = t.value.slice(0, t.selectionStart) + '\t' + t.value.slice(t.selectionEnd);
          const pos = t.selectionStart + 1;
          update(v);
          requestAnimationFrame(() => t.setSelectionRange(pos, pos));
        }
      }}
      onChange={(e) => update(e.target.value)}
    />
  );
}

function RichTextControl({ value, onChange }) {
  const ref = useRef(null);
  const [source, setSource] = useState(false);
  const [draft, update] = useDraft(value, onChange, 300);

  useEffect(() => {
    if (!source && ref.current && document.activeElement !== ref.current && ref.current.innerHTML !== (draft || '')) {
      ref.current.innerHTML = draft || '';
    }
  }, [draft, source]);

  const exec = (cmd, arg = null) => {
    ref.current.focus();
    if (cmd === 'createLink') {
      arg = window.prompt('Link URL', 'https://');
      if (!arg) return;
    }
    document.execCommand(cmd, false, arg);
    update(ref.current.innerHTML);
  };
  const tools = [
    ['bold', 'bold'],
    ['italic', 'italic'],
    ['underline', 'underline'],
    ['createLink', 'link'],
    ['h2', 'heading-2', 'formatBlock'],
    ['h3', 'heading-3', 'formatBlock'],
    ['p', 'pilcrow', 'formatBlock'],
    ['insertUnorderedList', 'list'],
    ['insertOrderedList', 'list-ordered'],
    ['blockquote', 'quote', 'formatBlock'],
    ['removeFormat', 'remove-formatting'],
  ];
  return (
    <div className="rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50">
      <div className="flex flex-wrap items-center gap-0.5 border-b border-border p-1">
        {!source &&
          tools.map(([cmd, icon, block]) => (
            <IconButton key={cmd} icon={icon} label={cmd} size="icon-sm" onMouseDown={(e) => e.preventDefault()} onClick={() => (block ? exec(block, cmd) : exec(cmd))} />
          ))}
        <span className="ml-auto" />
        <Button size="xs" variant="ghost" onClick={() => setSource(!source)}>
          {source ? 'Visual' : 'HTML'}
        </Button>
      </div>
      {source ? (
        <textarea className="block min-h-40 w-full resize-y bg-transparent p-2.5 font-mono text-xs outline-none" value={draft} onChange={(e) => update(e.target.value)} spellCheck={false} />
      ) : (
        <div ref={ref} contentEditable suppressContentEditableWarning className="bk-rich min-h-32 max-h-80 overflow-y-auto p-2.5 text-sm outline-none" onInput={(e) => update(e.currentTarget.innerHTML)} />
      )}
    </div>
  );
}

function NumberControl({ value, onChange, field, placeholder }) {
  return <Input type="number" value={value ?? ''} min={field.min} max={field.max} step={field.step} placeholder={placeholder ?? ''} onChange={(e) => onChange(e.target.value)} />;
}

function RangeControl({ value, onChange, field, placeholder }) {
  const min = field.min ?? 0;
  const max = field.max ?? 100;
  const step = field.step ?? 1;
  const current = value ?? placeholder ?? '';
  return (
    <div className="flex items-center gap-2">
      <input type="range" className="bk-range flex-1" min={min} max={max} step={step} value={current === '' ? min : current} onChange={(e) => onChange(e.target.value)} />
      <div className="relative w-20">
        <Input value={value ?? ''} placeholder={placeholder ?? ''} onChange={(e) => onChange(e.target.value)} className="pr-7" />
        {field.unit && <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-muted-foreground">{field.unit}</span>}
      </div>
    </div>
  );
}

/** Bump the number inside a CSS length with arrow keys. */
function nudge(e, value, update) {
  if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
  const m = String(value || '0').match(/^(-?\d*\.?\d+)(.*)$/);
  if (!m) return;
  e.preventDefault();
  const step = e.shiftKey ? 10 : e.altKey ? 0.1 : 1;
  const n = parseFloat(m[1]) + (e.key === 'ArrowUp' ? step : -step);
  update(`${Math.round(n * 100) / 100}${m[2] || 'px'}`);
}

function UnitControl({ value, onChange, field, placeholder }) {
  const [draft, update] = useDraft(value, onChange, 200);
  return <Input value={draft} placeholder={placeholder ?? field.placeholder ?? 'auto'} onKeyDown={(e) => nudge(e, draft || placeholder, update)} onChange={(e) => update(e.target.value)} />;
}

function SelectControl({ value, onChange, field, placeholder }) {
  const options = field.options || [];
  const hasEmpty = options.some((o) => o.value === '');
  const current = value ?? (placeholder !== undefined ? '' : field.default ?? '');
  return <Select options={options} value={current} onChange={onChange} placeholder={hasEmpty ? undefined : placeholder !== undefined ? `Inherit (${placeholder})` : undefined} />;
}

function ToggleControl({ value, onChange, field, placeholder }) {
  const on = value === undefined || value === '' ? !!(placeholder ?? field.default) : !!value && value !== '0' && value !== 'false';
  return (
    <label className="flex cursor-pointer items-center justify-between gap-3">
      <span className="text-xs font-medium text-foreground/80">{field.label}</span>
      <Switch checked={on} onChange={(v) => onChange(v ? true : false)} label={field.label} />
    </label>
  );
}

function AlignControl({ value, onChange }) {
  return (
    <Segmented
      value={value ?? ''}
      onChange={onChange}
      options={[
        { value: 'left', label: 'Left', icon: 'align-left' },
        { value: 'center', label: 'Center', icon: 'align-center' },
        { value: 'right', label: 'Right', icon: 'align-right' },
        { value: 'justify', label: 'Justify', icon: 'align-justify' },
      ]}
    />
  );
}

function DevicesControl({ value, onChange }) {
  const list = Array.isArray(value) ? value : [];
  const toggle = (d) => {
    const next = list.includes(d) ? list.filter((x) => x !== d) : [...list, d];
    onChange(next.length ? next : undefined);
  };
  return (
    <div className="flex gap-1.5">
      {[
        ['desktop', 'monitor'],
        ['tablet', 'tablet'],
        ['mobile', 'smartphone'],
      ].map(([d, icon]) => (
        <Button key={d} size="sm" variant="outline" active={list.includes(d)} icon={icon} onClick={() => toggle(d)}>
          {d}
        </Button>
      ))}
    </div>
  );
}

function DateControl({ value, onChange }) {
  return <Input type="datetime-local" value={value ?? ''} onChange={(e) => onChange(e.target.value)} />;
}

/* ------------------------------------------------------------------------
 * Colors.
 * ---------------------------------------------------------------------- */

const TOKENS = ['primary', 'primary-foreground', 'secondary', 'muted', 'muted-foreground', 'accent', 'foreground', 'background', 'card', 'border', 'destructive'];

function useSwatches() {
  const settings = useStore((s) => s.settings);
  return useMemo(() => {
    const resolved = (settings && settings.resolved && settings.resolved.light) || {};
    const tokens = TOKENS.map((t) => ({ value: `var(--${t})`, label: t, preview: resolved[t] || `var(--${t})` }));
    const globals = ((settings && settings.colors) || []).map((c) => ({ value: `var(--brik-color-${c.id})`, label: c.name || c.id, preview: c.value }));
    return { tokens, globals };
  }, [settings]);
}

function previewColor(value, swatches) {
  if (!value) return '';
  const all = [...swatches.tokens, ...swatches.globals];
  const hit = all.find((s) => s.value === value);
  return hit ? hit.preview : value;
}

function toHex(color) {
  if (/^#[0-9a-f]{6}$/i.test(color)) return color;
  if (/^#[0-9a-f]{3}$/i.test(color)) return '#' + color.slice(1).split('').map((c) => c + c).join('');
  // Resolve any CSS color through the browser.
  const ctx = document.createElement('canvas').getContext('2d');
  ctx.fillStyle = '#000';
  ctx.fillStyle = color;
  const v = ctx.fillStyle;
  return /^#/.test(v) ? v : '#000000';
}

export function ColorControl({ value, onChange, placeholder }) {
  const swatches = useSwatches();
  const [draft, update] = useDraft(value, onChange, 150);
  const shown = previewColor(draft || placeholder, swatches);
  return (
    <div className="flex gap-1.5">
      <Popover
        width={248}
        trigger={
          <button type="button" className="bk-checker relative size-8 shrink-0 cursor-pointer overflow-hidden rounded-md border border-input shadow-xs" title="Pick color">
            <span className="absolute inset-0" style={{ background: shown || 'transparent' }} />
          </button>
        }
      >
        <div className="space-y-3">
          <input type="color" className="h-24 w-full cursor-pointer rounded-md border border-input bg-transparent" value={toHex(shown || '#000000')} onChange={(e) => update(e.target.value)} />
          <div>
            <p className="mb-1.5 text-[11px] font-medium text-muted-foreground">Theme colors</p>
            <div className="grid grid-cols-8 gap-1.5">
              {swatches.tokens.map((s) => (
                <Swatch key={s.value} swatch={s} active={draft === s.value} onClick={() => update(s.value)} />
              ))}
            </div>
          </div>
          {swatches.globals.length > 0 && (
            <div>
              <p className="mb-1.5 text-[11px] font-medium text-muted-foreground">Global colors</p>
              <div className="grid grid-cols-8 gap-1.5">
                {swatches.globals.map((s) => (
                  <Swatch key={s.value} swatch={s} active={draft === s.value} onClick={() => update(s.value)} />
                ))}
              </div>
            </div>
          )}
          <div className="flex gap-1.5">
            <Button size="xs" variant="outline" onClick={() => update('transparent')}>
              Transparent
            </Button>
            <Button size="xs" variant="ghost" onClick={() => update('')}>
              Clear
            </Button>
          </div>
        </div>
      </Popover>
      <Input value={draft} placeholder={placeholder ?? 'Default'} onChange={(e) => update(e.target.value)} className="font-mono text-xs" />
    </div>
  );
}

function Swatch({ swatch, active, onClick }) {
  return (
    <button type="button" title={swatch.label} onClick={onClick} className={cn('bk-checker relative size-6 cursor-pointer overflow-hidden rounded border border-border', active && 'ring-2 ring-brand ring-offset-1')}>
      <span className="absolute inset-0" style={{ background: swatch.preview }} />
    </button>
  );
}

const GRADIENTS = [
  'linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%)',
  'linear-gradient(135deg, #0ea5e9 0%, #22d3ee 100%)',
  'linear-gradient(135deg, #f97316 0%, #facc15 100%)',
  'linear-gradient(135deg, #10b981 0%, #84cc16 100%)',
  'linear-gradient(180deg, #0f172a 0%, #1e293b 100%)',
  'linear-gradient(135deg, #f43f5e 0%, #fb7185 100%)',
  'radial-gradient(circle at top, #312e81 0%, #0f172a 70%)',
  'linear-gradient(180deg, transparent 0%, rgb(0 0 0 / 0.7) 100%)',
];

function parseGradient(v) {
  const m = String(v || '').match(/^(linear|radial)-gradient\((?:(\d+)deg,\s*|circle[^,]*,\s*)?(.+?)\s+(\d+)%,\s*(.+?)\s+(\d+)%\)$/);
  if (!m) return null;
  return { type: m[1], angle: m[2] || '180', c1: m[3], s1: m[4], c2: m[5], s2: m[6] };
}

function buildGradient(g) {
  const stops = `${g.c1} ${g.s1}%, ${g.c2} ${g.s2}%`;
  return g.type === 'radial' ? `radial-gradient(circle, ${stops})` : `linear-gradient(${g.angle}deg, ${stops})`;
}

function GradientControl({ value, onChange, placeholder }) {
  const parsed = parseGradient(value) || { type: 'linear', angle: '135', c1: '#6366f1', s1: '0', c2: '#ec4899', s2: '100' };
  const set = (patch) => onChange(buildGradient({ ...parsed, ...patch }));
  return (
    <div className="space-y-2">
      <div className="grid grid-cols-8 gap-1.5">
        {GRADIENTS.map((g) => (
          <button key={g} type="button" className={cn('h-6 cursor-pointer rounded border border-border', value === g && 'ring-2 ring-brand ring-offset-1')} style={{ background: g }} onClick={() => onChange(g)} />
        ))}
      </div>
      <Popover
        width={260}
        trigger={
          <Button size="xs" variant="outline" icon="sliders-horizontal">
            Customize
          </Button>
        }
      >
        <div className="space-y-3">
          <div className="h-10 rounded-md border border-border" style={{ background: value || buildGradient(parsed) }} />
          <Tabs tabs={[{ value: 'linear', label: 'Linear' }, { value: 'radial', label: 'Radial' }]} value={parsed.type} onChange={(type) => set({ type })} className="w-full" />
          {parsed.type === 'linear' && (
            <div className="space-y-1">
              <Label>Angle</Label>
              <RangeControl field={{ min: 0, max: 360, unit: 'deg' }} value={parsed.angle} onChange={(angle) => set({ angle })} />
            </div>
          )}
          {[1, 2].map((i) => (
            <div key={i} className="grid grid-cols-[1fr_70px] gap-2">
              <ColorControl value={parsed[`c${i}`]} onChange={(c) => set({ [`c${i}`]: c || 'transparent' })} />
              <Input value={parsed[`s${i}`]} onChange={(e) => set({ [`s${i}`]: e.target.value })} />
            </div>
          ))}
        </div>
      </Popover>
      <Input value={value ?? ''} placeholder={placeholder ?? 'linear-gradient(…)'} onChange={(e) => onChange(e.target.value)} className="font-mono text-xs" />
    </div>
  );
}

function ShadowControl({ value, onChange, placeholder }) {
  const presets = ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl', 'inner'];
  const custom = value && !presets.includes(value);
  return (
    <div className="space-y-1.5">
      <div className="flex flex-wrap gap-1">
        {presets.map((p) => (
          <Button key={p} size="xs" variant="outline" active={value === p} onClick={() => onChange(value === p ? '' : p)}>
            {p}
          </Button>
        ))}
      </div>
      <Input value={custom ? value : ''} placeholder={placeholder && !presets.includes(placeholder) ? placeholder : '0 10px 30px rgb(0 0 0 / .1)'} onChange={(e) => onChange(e.target.value)} className="font-mono text-xs" />
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Media.
 * ---------------------------------------------------------------------- */

function mediaFrame({ title, type = 'image', multiple = false, onSelect }) {
  const frame = window.wp.media({ title, multiple, library: { type }, button: { text: 'Use selected' } });
  frame.on('select', () => {
    const items = frame
      .state()
      .get('selection')
      .toJSON()
      .map((a) => ({ id: a.id, url: (a.sizes && a.sizes.full ? a.sizes.full.url : a.url) || a.url, alt: a.alt || '', mime: a.mime }));
    onSelect(multiple ? items : items[0]);
  });
  frame.open();
}

function imageUrl(v) {
  if (!v) return '';
  return typeof v === 'string' ? v : v.url || '';
}

function ImageControl({ value, onChange, field, placeholder }) {
  const url = imageUrl(value) || imageUrl(placeholder);
  const [draft, update] = useDraft(typeof value === 'string' ? value : value ? value.url : '', (v) => onChange(v ? { url: v } : undefined), 400);
  return (
    <div className="space-y-1.5">
      {url && !url.startsWith('{') ? (
        <div className="bk-checker group relative overflow-hidden rounded-md border border-border">
          <img src={url} alt="" className="h-28 w-full object-contain" />
          <div className="absolute right-1.5 top-1.5 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100">
            <IconButton icon="replace" label="Replace" size="icon-sm" variant="secondary" onClick={() => mediaFrame({ title: field.label, onSelect: onChange })} />
            <IconButton icon="trash-2" label="Remove" size="icon-sm" variant="secondary" onClick={() => onChange(undefined)} />
          </div>
        </div>
      ) : (
        <button type="button" onClick={() => mediaFrame({ title: field.label, onSelect: onChange })} className="flex h-20 w-full cursor-pointer flex-col items-center justify-center gap-1 rounded-md border border-dashed border-input text-xs text-muted-foreground hover:bg-accent/50">
          <Icon name="image-plus" size={18} />
          Choose image
        </button>
      )}
      <div className="flex gap-1.5">
        <Input value={draft} placeholder="https:// or {featured_image}" onChange={(e) => update(e.target.value)} className="text-xs" />
      </div>
    </div>
  );
}

function GalleryControl({ value, onChange, field }) {
  const list = Array.isArray(value) ? value : [];
  return (
    <div className="space-y-2">
      {list.length > 0 && (
        <div className="grid grid-cols-4 gap-1.5">
          {list.map((img, i) => (
            <div key={`${img.id || img.url}-${i}`} className="group relative aspect-square overflow-hidden rounded border border-border">
              <img src={imageUrl(img)} alt="" className="size-full object-cover" />
              <button type="button" className="absolute right-0.5 top-0.5 hidden rounded bg-background/90 p-0.5 group-hover:block cursor-pointer" onClick={() => onChange(list.filter((_, j) => j !== i))} title="Remove">
                <Icon name="x" size={12} />
              </button>
            </div>
          ))}
        </div>
      )}
      <Button size="sm" variant="outline" icon="images" onClick={() => mediaFrame({ title: field.label, multiple: true, onSelect: (items) => onChange([...list, ...items]) })}>
        Add images
      </Button>
    </div>
  );
}

function VideoControl({ value, onChange, field }) {
  const url = typeof value === 'string' ? value : value ? value.url : '';
  return (
    <div className="flex gap-1.5">
      <Input value={url || ''} placeholder={field.media_type === 'audio' ? 'https://…mp3' : 'https://…mp4, YouTube or Vimeo URL'} onChange={(e) => onChange(e.target.value)} className="text-xs" />
      <IconButton icon="folder-open" label="Media library" variant="outline" onClick={() => mediaFrame({ title: field.label, type: field.media_type || 'video', onSelect: (v) => onChange(v.url) })} />
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Links.
 * ---------------------------------------------------------------------- */

function LinkControl({ value, onChange, placeholder }) {
  const link = value && typeof value === 'object' ? value : typeof value === 'string' ? { url: value } : {};
  const [draft, update] = useDraft(link.url || '', (url) => onChange(url || link.new_tab ? { ...link, url } : undefined), 300);
  const [results, setResults] = useState([]);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open || !draft || /^(https?:|#|\/|mailto:|tel:|\{)/.test(draft)) {
      setResults([]);
      return;
    }
    const t = setTimeout(() => search({ q: draft }).then(setResults).catch(() => {}), 250);
    return () => clearTimeout(t);
  }, [draft, open]);

  return (
    <div className="space-y-1.5">
      <div className="relative">
        <Icon name="link" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
        <Input
          value={draft}
          className="pl-8"
          placeholder={(placeholder && placeholder.url) || 'Search or paste a URL'}
          onFocus={() => setOpen(true)}
          onBlur={() => setTimeout(() => setOpen(false), 150)}
          onChange={(e) => update(e.target.value)}
        />
        {open && results.length > 0 && (
          <div className="absolute left-0 right-0 top-9 z-50 max-h-56 overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-lg">
            {results.map((r) => (
              <button
                key={r.id}
                type="button"
                className="flex w-full items-center justify-between gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
                onMouseDown={(e) => {
                  e.preventDefault();
                  api_permalink(r.id).then((url) => {
                    update(url);
                    onChange({ ...link, url, post_id: r.id });
                  });
                  setOpen(false);
                }}
              >
                <span className="truncate">{r.title || '(no title)'}</span>
                <span className="text-[10px] uppercase text-muted-foreground">{r.type}</span>
              </button>
            ))}
          </div>
        )}
      </div>
      <div className="flex gap-4">
        <label className="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer">
          <input type="checkbox" checked={!!link.new_tab} onChange={(e) => onChange({ ...link, url: draft, new_tab: e.target.checked })} /> New tab
        </label>
        <label className="flex items-center gap-1.5 text-xs text-muted-foreground cursor-pointer">
          <input type="checkbox" checked={!!link.nofollow} onChange={(e) => onChange({ ...link, url: draft, nofollow: e.target.checked })} /> nofollow
        </label>
      </div>
    </div>
  );
}

async function api_permalink(id) {
  const posts = await window.wp.apiFetch({ path: `/wp/v2/search?include=${id}&per_page=1&type=post` }).catch(() => []);
  return posts && posts[0] ? posts[0].url : `/?p=${id}`;
}

/* ------------------------------------------------------------------------
 * Icons.
 * ---------------------------------------------------------------------- */

function IconControl({ value, onChange }) {
  return (
    <div className="flex gap-1.5">
      <Popover
        width={320}
        trigger={
          <button type="button" className={cn(inputClass, 'flex items-center gap-2 text-left cursor-pointer')}>
            {value ? <Icon name={value} size={16} /> : <Icon name="smile-plus" size={16} className="text-muted-foreground" />}
            <span className={cn('truncate', !value && 'text-muted-foreground')}>{value || 'Choose icon'}</span>
          </button>
        }
      >
        {(close) => (
          <IconPicker
            value={value}
            onPick={(name) => {
              onChange(name);
              close();
            }}
          />
        )}
      </Popover>
      {value && <IconButton icon="x" label="Remove icon" variant="outline" onClick={() => onChange(undefined)} />}
    </div>
  );
}

export function IconPicker({ value, onPick }) {
  const [q, setQ] = useState('');
  const [set, setSet] = useState(value && value.startsWith('brand:') ? 'brands' : 'icons');
  const [tags, setTags] = useState(null);
  useEffect(() => {
    loadIconTags().then(setTags);
  }, []);
  const names = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (set === 'brands') return brandNames().filter((n) => n.includes(term)).map((n) => `brand:${n}`);
    const all = iconNames();
    if (!term) return all.slice(0, 240);
    return all.filter((n) => n.includes(term) || (tags && tags[n] && tags[n].some((t) => t.includes(term)))).slice(0, 240);
  }, [q, set, tags]);

  return (
    <div className="space-y-2">
      <Tabs tabs={[{ value: 'icons', label: 'Icons' }, { value: 'brands', label: 'Brands' }]} value={set} onChange={setSet} className="w-full" />
      <Input autoFocus value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search icons…" />
      <div className="grid max-h-64 grid-cols-8 gap-1 overflow-y-auto">
        {names.map((n) => (
          <button key={n} type="button" title={n} onClick={() => onPick(n)} className={cn('flex aspect-square items-center justify-center rounded hover:bg-accent cursor-pointer', value === n && 'bg-accent ring-1 ring-brand')}>
            <span dangerouslySetInnerHTML={{ __html: iconSvg(n, 18) }} />
          </button>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Spacing (CSS shorthand with four sides).
 * ---------------------------------------------------------------------- */

function splitSides(v) {
  const p = String(v || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return ['', '', '', ''];
  if (p.length === 1) return [p[0], p[0], p[0], p[0]];
  if (p.length === 2) return [p[0], p[1], p[0], p[1]];
  if (p.length === 3) return [p[0], p[1], p[2], p[1]];
  return p.slice(0, 4);
}

function joinSides(s) {
  const v = s.map((x) => (x === '' ? '0' : /^-?\d*\.?\d+$/.test(x) ? `${x}px` : x));
  if (s.every((x) => x === '')) return '';
  if (v[0] === v[1] && v[1] === v[2] && v[2] === v[3]) return v[0];
  if (v[0] === v[2] && v[1] === v[3]) return `${v[0]} ${v[1]}`;
  if (v[1] === v[3]) return `${v[0]} ${v[1]} ${v[2]}`;
  return v.join(' ');
}

function SpacingControl({ value, onChange, placeholder }) {
  const sides = splitSides(value);
  const ph = splitSides(placeholder);
  const [linked, setLinked] = useState(sides.every((s) => s === sides[0]) && !!value);
  const set = (i, v) => {
    const next = linked ? [v, v, v, v] : sides.map((s, j) => (j === i ? v : s));
    onChange(joinSides(next));
  };
  const labels = ['Top', 'Right', 'Bottom', 'Left'];
  return (
    <div className="flex items-center gap-1.5">
      <div className="grid flex-1 grid-cols-4 gap-1">
        {sides.map((s, i) => (
          <div key={i} className="space-y-0.5">
            <input
              className={cn(inputClass, 'px-1.5 text-center text-xs')}
              value={s}
              placeholder={ph[i] || '–'}
              onKeyDown={(e) => nudge(e, s || ph[i], (v) => set(i, v))}
              onChange={(e) => set(i, e.target.value)}
              aria-label={labels[i]}
            />
            <span className="block text-center text-[10px] text-muted-foreground">{labels[i]}</span>
          </div>
        ))}
      </div>
      <IconButton icon={linked ? 'link' : 'unlink'} label="Link sides" size="icon-sm" variant={linked ? 'secondary' : 'ghost'} onClick={() => setLinked(!linked)} className="-mt-4" />
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Pickers fed by the schema.
 * ---------------------------------------------------------------------- */

function FontControl({ value, onChange, placeholder }) {
  const fonts = useStore((s) => (s.schema ? s.schema.fonts : []));
  const [draft, update] = useDraft(value, onChange, 300);
  return (
    <div>
      <Input list="brik-fonts" value={draft} placeholder={placeholder ?? 'Default'} onChange={(e) => update(e.target.value)} style={{ fontFamily: draft || undefined }} />
      <datalist id="brik-fonts">
        <option value="inherit" />
        <option value="ui-sans-serif, system-ui, sans-serif" />
        <option value="ui-serif, Georgia, serif" />
        <option value="ui-monospace, SFMono-Regular, monospace" />
        {fonts.map((f) => (
          <option key={f} value={f} />
        ))}
      </datalist>
    </div>
  );
}

function MenuControl({ value, onChange }) {
  const menus = useStore((s) => (s.schema ? s.schema.menus || [] : []));
  return (
    <div className="space-y-1">
      <Select value={value ?? ''} onChange={onChange} placeholder="Primary location / first menu" options={menus.map((m) => ({ value: String(m.id), label: m.title }))} />
      <a className="text-[11px] text-muted-foreground underline" href={`${window.brikBuilder.adminUrl}nav-menus.php`} target="_blank" rel="noreferrer">
        Manage menus
      </a>
    </div>
  );
}

function PostTypeControl({ value, onChange }) {
  const types = useStore((s) => (s.schema ? s.schema.post_types : {}));
  return <Select value={value ?? ''} onChange={onChange} placeholder="Default" options={Object.entries(types).map(([v, l]) => ({ value: v, label: l }))} />;
}

function TaxonomyControl({ value, onChange }) {
  const tax = useStore((s) => (s.schema ? s.schema.taxonomies : {}));
  return <Select value={value ?? ''} onChange={onChange} placeholder="Choose taxonomy" options={Object.entries(tax).map(([v, l]) => ({ value: v, label: l }))} />;
}

function LibraryControl({ value, onChange, field }) {
  const [items, setItems] = useState([]);
  useEffect(() => {
    loadLibrary(field.kind || '').then((r) => setItems(r.items || []));
  }, []);
  return <Select value={value ? String(value) : ''} onChange={(v) => onChange(v ? Number(v) : undefined)} placeholder="Choose item" options={items.map((i) => ({ value: String(i.id), label: `${i.title} (${i.kind})` }))} />;
}

const STRUCTURES = ['1', '1/2,1/2', '1/3,1/3,1/3', '1/4,1/4,1/4,1/4', '1/3,2/3', '2/3,1/3', '1/4,3/4', '3/4,1/4', '1/4,1/2,1/4', '1/2,1/4,1/4', '1/4,1/4,1/2', '1/5,1/5,1/5,1/5,1/5', '1/6,1/6,1/6,1/6,1/6,1/6', '2/5,3/5', '3/5,2/5'];

export function StructurePreview({ structure, active, onClick }) {
  const parts = String(structure).includes('/') ? structure.split(',') : Array.from({ length: Number(structure) || 1 }, () => '1/1');
  return (
    <button type="button" onClick={onClick} title={structure} className={cn('flex h-9 w-full cursor-pointer gap-0.5 rounded-md border p-1 hover:border-brand', active ? 'border-brand bg-brand/5' : 'border-border')}>
      {parts.map((p, i) => {
        const [a, b] = p.split('/').map(Number);
        return <span key={i} className={cn('h-full rounded-sm', active ? 'bg-brand/60' : 'bg-muted-foreground/25')} style={{ flex: b ? a / b : 1 }} />;
      })}
    </button>
  );
}

function ColumnsControl({ value, onChange, node, placeholder }) {
  const device = useStore((s) => s.device);
  const count = node ? (node.children || []).length : 1;
  const current = value ?? '';
  const options = device === 'desktop' ? STRUCTURES : STRUCTURES.filter((s) => T.columnCount(s) <= count);
  return (
    <div className="space-y-1.5">
      <div className="grid grid-cols-3 gap-1.5">
        {options.map((s) => (
          <StructurePreview key={s} structure={s} active={current === s || (!current && placeholder === s)} onClick={() => onChange(s)} />
        ))}
      </div>
      {device !== 'desktop' && <p className="text-[11px] text-muted-foreground">On {device} the same columns are re-arranged; leave empty to stack.</p>}
      <Input value={current} placeholder={placeholder || 'e.g. 1/3,2/3 or 4'} onChange={(e) => onChange(e.target.value)} className="font-mono text-xs" />
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Repeater.
 * ---------------------------------------------------------------------- */

function itemTitle(item, field, i) {
  const key = field.title_field || Object.keys(field.fields || {})[0];
  const v = item && key ? item[key] : '';
  const text = typeof v === 'string' ? v.replace(/<[^>]+>/g, '').trim() : '';
  return text || `Item ${i + 1}`;
}

function blankItem(field) {
  if (Array.isArray(field.default) && field.default[0]) return structuredClone(field.default[0]);
  const out = {};
  for (const [k, f] of Object.entries(field.fields || {})) if (f.default !== undefined) out[k] = f.default;
  return out;
}

function RepeaterControl({ value, onChange, field, placeholder }) {
  const items = Array.isArray(value) ? value : Array.isArray(placeholder) ? placeholder : Array.isArray(field.default) ? field.default : [];
  const [open, setOpen] = useState(null);
  const set = (list) => onChange(list);
  const update = (i, k, v) => set(items.map((it, j) => (j === i ? { ...it, [k]: v } : it)));
  const moveItem = (i, d) => {
    const list = items.slice();
    const [it] = list.splice(i, 1);
    list.splice(i + d, 0, it);
    set(list);
    setOpen(i + d);
  };
  return (
    <div className="space-y-1.5">
      {items.map((item, i) => (
        <div key={i} className="rounded-md border border-border bg-background">
          <div className="flex items-center gap-1 pl-2.5 pr-1">
            <button type="button" className="flex-1 truncate py-2 text-left text-sm cursor-pointer" onClick={() => setOpen(open === i ? null : i)}>
              {itemTitle(item, field, i)}
            </button>
            <IconButton icon="chevron-up" label="Move up" size="icon-sm" disabled={i === 0} onClick={() => moveItem(i, -1)} />
            <IconButton icon="chevron-down" label="Move down" size="icon-sm" disabled={i === items.length - 1} onClick={() => moveItem(i, 1)} />
            <IconButton icon="copy" label="Duplicate" size="icon-sm" onClick={() => set([...items.slice(0, i + 1), structuredClone(item), ...items.slice(i + 1)])} />
            <IconButton icon="trash-2" label="Remove" size="icon-sm" onClick={() => set(items.filter((_, j) => j !== i))} />
          </div>
          {open === i && (
            <div className="space-y-3 border-t border-border p-3">
              {Object.entries(field.fields || {})
                .filter(([, f]) => visible(f, item, field.fields))
                .map(([k, f]) => (
                  <div key={k} className="space-y-1.5">
                    {f.type !== 'toggle' && <Label>{f.label}</Label>}
                    <Control field={f} value={item[k]} onChange={(v) => update(i, k, v)} />
                  </div>
                ))}
            </div>
          )}
        </div>
      ))}
      <Button
        size="sm"
        variant="outline"
        icon="plus"
        className="w-full"
        onClick={() => {
          set([...items, blankItem(field)]);
          setOpen(items.length);
        }}
      >
        Add {field.item_label || 'item'}
      </Button>
    </div>
  );
}
