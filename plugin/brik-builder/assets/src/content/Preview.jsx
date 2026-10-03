// Interactive mock of the meta box, so conditions and widths can be tried before saving.
import { useState, __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn } from '../builder/ui.jsx';
import { SwitchBtn, Checkbox } from './primitives.jsx';
import { subFields, NO_VALUE } from './lib.js';

function isEmpty(v) {
  return v === undefined || v === null || v === '' || v === false || (Array.isArray(v) && !v.length);
}

export function conditionsMet(field, values) {
  const groups = field.conditions || [];
  if (!groups.length) return true;
  return groups.some((g) =>
    g.every((r) => {
      const v = values[r.field];
      const str = Array.isArray(v) ? v.map(String) : v === true ? '1' : v === false ? '0' : String(v ?? '');
      switch (r.operator) {
        case 'empty':
          return isEmpty(v);
        case '!empty':
          return !isEmpty(v);
        case 'contains':
          return Array.isArray(str) ? str.includes(String(r.value)) : str.includes(String(r.value));
        case '!=':
          return Array.isArray(str) ? !str.includes(String(r.value)) : str !== String(r.value);
        default:
          return Array.isArray(str) ? str.includes(String(r.value)) : str === String(r.value);
      }
    })
  );
}

function initialValues(fields) {
  const out = {};
  for (const f of fields) if (f.default !== undefined && f.default !== '') out[f.key] = f.type === 'toggle' ? !!Number(f.default) : f.default;
  return out;
}

export function Preview({ group, postTypeLabel }) {
  const fields = group.fields || [];
  const tabs = fields.filter((f) => f.type === 'tab');
  const [tab, setTab] = useState(0);
  const seamless = group.style === 'seamless';
  const side = group.position === 'side';

  // Fields before the first tab show above the tab bar.
  const sections = [];
  let cur = { tab: null, fields: [] };
  for (const f of fields) {
    if (f.type === 'tab') {
      sections.push(cur);
      cur = { tab: f, fields: [] };
    } else cur.fields.push(f);
  }
  sections.push(cur);
  const head = sections[0];
  const tabbed = sections.slice(1);

  return (
    <div className="rounded-xl border border-border bg-[#f0f0f1] p-4 sm:p-6" data-preview>
      <div className="mb-3 flex items-center justify-between text-xs text-[#50575e]">
        <span className="flex items-center gap-1.5">
          <Icon name="monitor" size={13} />
          {sprintf(__('Editing a %s', 'brik-builder'), postTypeLabel || __('post', 'brik-builder'))}
        </span>
        <span>{{ side: __('Sidebar', 'brik-builder'), after_title: __('After title', 'brik-builder') }[group.position] || __('Below content', 'brik-builder')}</span>
      </div>
      <div className={cn('mx-auto', side ? 'max-w-[280px]' : 'max-w-3xl')}>
        {group.position === 'after_title' && <div className="mb-3 rounded-sm border border-[#c3c4c7] bg-white px-3 py-2 text-xl text-[#1d2327]/40">{__('Add title', 'brik-builder')}</div>}
        <MetaBox values={{}} title={group.title} seamless={seamless}>
          {(values, setValue) => (
            <>
              <FieldGrid fields={head.fields} values={values} setValue={setValue} side={side} />
              {tabbed.length > 0 && (
                <div className={cn((tabs[0].options || {}).placement === 'left' && !side ? 'flex gap-4' : '')}>
                  <div className={cn('flex border-[#c3c4c7]', (tabs[0].options || {}).placement === 'left' && !side ? 'w-36 shrink-0 flex-col border-r' : 'mb-3 gap-1 border-b')}>
                    {tabbed.map((sct, i) => (
                      <button key={sct.tab.key} type="button" onClick={() => setTab(i)} className={cn('px-3 py-2 text-left text-[13px] cursor-pointer', i === tab ? 'border-b-2 border-[#2271b1] font-semibold text-[#1d2327]' : 'text-[#50575e] hover:text-[#2271b1]')}>
                        {sct.tab.label || __('Tab', 'brik-builder')}
                      </button>
                    ))}
                  </div>
                  <div className="min-w-0 flex-1">{tabbed[tab] && <FieldGrid fields={tabbed[tab].fields} values={values} setValue={setValue} side={side} />}</div>
                </div>
              )}
              {!fields.length && <p className="py-6 text-center text-[13px] text-[#646970]">{__('Add fields to see them here.', 'brik-builder')}</p>}
            </>
          )}
        </MetaBox>
      </div>
      <p className="mt-3 flex items-center justify-center gap-1.5 text-xs text-[#646970]">
        <Icon name="mouse-pointer-click" size={13} />
        {__('This preview is interactive — change values to test conditions.', 'brik-builder')}
      </p>
    </div>
  );
}

function MetaBox({ title, seamless, children }) {
  const [values, setValues] = useState({});
  const [open, setOpen] = useState(true);
  const setValue = (k, v) => setValues((s) => ({ ...s, [k]: v }));
  if (seamless) return <div className="space-y-0">{children(values, setValue)}</div>;
  return (
    <div className="border border-[#c3c4c7] bg-white shadow-[0_1px_1px_rgba(0,0,0,.04)]">
      <button type="button" onClick={() => setOpen(!open)} className="flex w-full items-center justify-between border-b border-[#c3c4c7] px-3 py-2 text-left text-[14px] font-semibold text-[#1d2327] cursor-pointer">
        {title || __('(untitled)', 'brik-builder')}
        <Icon name={open ? 'chevron-up' : 'chevron-down'} size={16} className="text-[#787c82]" />
      </button>
      {open && <div className="p-3">{children(values, setValue)}</div>}
    </div>
  );
}

function FieldGrid({ fields, values, setValue, side }) {
  const visible = fields.filter((f) => conditionsMet(f, values));
  return (
    <div className="-mx-2 flex flex-wrap">
      {visible.map((f) => (
        <div key={f.key} className="bk-rise px-2 py-2" style={{ width: side ? '100%' : `${f.width || 100}%` }} data-preview-field={f.name || f.key}>
          {f.type !== 'message' && (
            <label className="mb-1 block text-[13px] font-semibold text-[#1d2327]">
              {f.label || <span className="text-[#a7aaad]">{__('(no label)', 'brik-builder')}</span>}
              {f.required && <span className="ml-0.5 text-[#d63638]">*</span>}
            </label>
          )}
          {f.instructions && f.type !== 'message' && <p className="mb-1.5 text-[12px] text-[#646970]">{f.instructions}</p>}
          <MockControl f={f} value={values[f.key] ?? (f.default !== '' ? f.default : undefined)} onChange={(v) => setValue(f.key, v)} />
        </div>
      ))}
    </div>
  );
}

const wpInput = 'h-[30px] w-full rounded-[4px] border border-[#8c8f94] bg-white px-2 text-[13px] text-[#2c3338] outline-none focus:border-[#2271b1] focus:shadow-[0_0_0_1px_#2271b1]';

function Affix({ o, children }) {
  if (!o.prepend && !o.append) return children;
  return (
    <div className="flex">
      {o.prepend && <span className="flex items-center rounded-l-[4px] border border-r-0 border-[#8c8f94] bg-[#f6f7f7] px-2 text-[13px] text-[#50575e]">{o.prepend}</span>}
      <div className="min-w-0 flex-1 [&_input]:rounded-none">{children}</div>
      {o.append && <span className="flex items-center rounded-r-[4px] border border-l-0 border-[#8c8f94] bg-[#f6f7f7] px-2 text-[13px] text-[#50575e]">{o.append}</span>}
    </div>
  );
}

function MediaBox({ icon, text }) {
  return (
    <div className="flex items-center gap-3 rounded-[4px] border border-dashed border-[#c3c4c7] bg-[#f6f7f7] p-3 text-[13px] text-[#50575e]">
      <span className="flex size-10 items-center justify-center rounded bg-white text-[#787c82] shadow-xs">
        <Icon name={icon} size={18} />
      </span>
      <span>
        {text} <span className="ml-1 rounded-[3px] border border-[#2271b1] px-2 py-0.5 text-[12px] text-[#2271b1]">{__('Select', 'brik-builder')}</span>
      </span>
    </div>
  );
}

function MockControl({ f, value, onChange }) {
  const o = f.options || {};
  const choices = o.choices || [];
  switch (f.type) {
    case 'textarea':
      return <textarea className={cn(wpInput, 'h-auto py-1.5')} rows={o.rows || 3} placeholder={f.placeholder} value={value || ''} onChange={(e) => onChange(e.target.value)} />;
    case 'wysiwyg':
      return (
        <div className="rounded-[4px] border border-[#dcdcde]">
          <div className="flex gap-2 border-b border-[#dcdcde] bg-[#f6f7f7] px-2 py-1.5 text-[#50575e]">
            {['bold', 'italic', 'list', 'link', 'quote'].map((i) => (
              <Icon key={i} name={i} size={14} />
            ))}
          </div>
          <div className="h-20 p-2 text-[13px] text-[#a7aaad]">{__('Start writing…', 'brik-builder')}</div>
        </div>
      );
    case 'number':
    case 'email':
    case 'url':
    case 'password':
    case 'text':
      return (
        <Affix o={o}>
          <input className={wpInput} type={{ number: 'number', email: 'email', url: 'url', password: 'password' }[f.type] || 'text'} placeholder={f.placeholder} min={o.min} max={o.max} step={o.step} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
        </Affix>
      );
    case 'range':
      return (
        <div className="flex items-center gap-3">
          <input type="range" className="flex-1 accent-[#2271b1]" min={o.min ?? 0} max={o.max ?? 100} step={o.step ?? 1} value={value ?? o.min ?? 0} onChange={(e) => onChange(e.target.value)} />
          <span className="w-12 rounded-[4px] border border-[#8c8f94] px-1.5 py-0.5 text-center text-[13px]">{value ?? o.min ?? 0}</span>
        </div>
      );
    case 'select':
      return (
        <select className={cn(wpInput, 'bk-select-chevron')} value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
          <option value="">{__('— Select —', 'brik-builder')}</option>
          {choices.map((c) => (
            <option key={c.value} value={c.value}>
              {c.label || c.value}
            </option>
          ))}
        </select>
      );
    case 'radio':
      return (
        <div className="space-y-1">
          {choices.map((c) => (
            <label key={c.value} className="flex cursor-pointer items-center gap-2 text-[13px]">
              <span className={cn('flex size-4 items-center justify-center rounded-full border border-[#8c8f94]', value === c.value && 'border-[#2271b1]')}>{value === c.value && <span className="size-2 rounded-full bg-[#2271b1]" />}</span>
              <span onClick={() => onChange(c.value)}>{c.label || c.value}</span>
            </label>
          ))}
          {!choices.length && <NoChoices />}
        </div>
      );
    case 'checkbox': {
      const list = Array.isArray(value) ? value : value ? [value] : [];
      return (
        <div className="flex flex-wrap gap-x-4 gap-y-1">
          {choices.map((c) => (
            <label key={c.value} className="flex cursor-pointer items-center gap-2 text-[13px]">
              <Checkbox checked={list.includes(c.value)} onChange={(on) => onChange(on ? [...list, c.value] : list.filter((x) => x !== c.value))} />
              {c.label || c.value}
            </label>
          ))}
          {!choices.length && <NoChoices />}
        </div>
      );
    }
    case 'button_group':
      return (
        <div className="inline-flex overflow-hidden rounded-[4px] border border-[#2271b1]">
          {choices.map((c) => (
            <button key={c.value} type="button" onClick={() => onChange(c.value)} className={cn('border-r border-[#2271b1] px-3 py-1 text-[13px] last:border-r-0 cursor-pointer', value === c.value ? 'bg-[#2271b1] text-white' : 'bg-white text-[#2271b1]')}>
              {c.label || c.value}
            </button>
          ))}
          {!choices.length && <span className="px-3 py-1 text-[13px] text-[#646970]">{__('No choices', 'brik-builder')}</span>}
        </div>
      );
    case 'toggle':
      return (
        <div className="flex items-center gap-2 text-[13px]">
          <SwitchBtn checked={!!value && value !== '0'} onChange={onChange} label={f.label} />
          <span className="text-[#50575e]">{value && value !== '0' ? o.on_text || __('Yes', 'brik-builder') : o.off_text || __('No', 'brik-builder')}</span>
        </div>
      );
    case 'date':
    case 'datetime':
    case 'time':
      return <input className={cn(wpInput, 'max-w-60')} type={{ date: 'date', datetime: 'datetime-local', time: 'time' }[f.type]} value={value || ''} onChange={(e) => onChange(e.target.value)} />;
    case 'color':
      return (
        <div className="flex items-center gap-2">
          <span className="size-[30px] rounded-[4px] border border-[#8c8f94]" style={{ background: value || '#ffffff' }} />
          <span className="rounded-[3px] border border-[#2271b1] px-2 py-1 text-[12px] text-[#2271b1]">{__('Select color', 'brik-builder')}</span>
        </div>
      );
    case 'image':
      return <MediaBox icon="image" text={__('No image selected', 'brik-builder')} />;
    case 'file':
      return <MediaBox icon="paperclip" text={__('No file selected', 'brik-builder')} />;
    case 'gallery':
      return (
        <div className="flex flex-wrap gap-1.5 rounded-[4px] border border-[#dcdcde] bg-[#f6f7f7] p-2">
          {[0, 1, 2].map((i) => (
            <span key={i} className="flex size-16 items-center justify-center rounded bg-[#dcdcde] text-[#a7aaad]">
              <Icon name="image" size={16} />
            </span>
          ))}
          <span className="flex size-16 items-center justify-center rounded border border-dashed border-[#8c8f94] text-[#50575e]">
            <Icon name="plus" size={16} />
          </span>
        </div>
      );
    case 'oembed':
      return (
        <div className="relative">
          <Icon name="clapperboard" size={14} className="absolute top-1/2 left-2 -translate-y-1/2 text-[#787c82]" />
          <input className={cn(wpInput, 'pl-7')} placeholder="https://youtube.com/watch?v=…" />
        </div>
      );
    case 'link':
      return (
        <div className="flex items-center gap-2">
          <span className="rounded-[3px] border border-[#2271b1] px-2.5 py-1 text-[12px] text-[#2271b1]">{__('Select link', 'brik-builder')}</span>
        </div>
      );
    case 'post_object':
    case 'relationship':
    case 'taxonomy':
    case 'user':
      return (
        <div className={cn(wpInput, 'flex items-center gap-2 text-[#646970]')}>
          <Icon name={{ taxonomy: 'tags', user: 'user' }[f.type] || 'search'} size={14} />
          {f.type === 'relationship' ? __('Search posts…', 'brik-builder') : f.type === 'taxonomy' ? sprintf(__('Choose %s', 'brik-builder'), o.taxonomy || __('terms', 'brik-builder')) : f.type === 'user' ? __('Choose a user', 'brik-builder') : __('Choose a post', 'brik-builder')}
        </div>
      );
    case 'map':
      return (
        <div className="space-y-1.5">
          <input className={wpInput} placeholder={__('Search for an address…', 'brik-builder')} />
          <div className="bk-grid-dots relative flex h-32 items-center justify-center rounded-[4px] border border-[#dcdcde] bg-[#eef3f7]">
            <Icon name="map-pin" size={24} className="text-[#d63638]" />
          </div>
        </div>
      );
    case 'message':
      return <div className="rounded-[4px] border-l-4 border-[#72aee6] bg-[#f0f6fc] px-3 py-2 text-[13px] text-[#1d2327]">{o.message || f.label}</div>;
    case 'group':
      return (
        <div className="rounded-[4px] border border-[#dcdcde] p-1">
          <SubGrid fields={subFields(f)} />
        </div>
      );
    case 'repeater':
      return <RepeaterMock f={f} />;
    default:
      return <input className={wpInput} />;
  }
}

function NoChoices() {
  return <span className="text-[12px] text-[#646970]">{__('Add choices to see them here.', 'brik-builder')}</span>;
}

function SubGrid({ fields }) {
  const [values, setValues] = useState({});
  if (!fields.length) return <p className="p-3 text-[12px] text-[#646970]">{__('No sub fields yet.', 'brik-builder')}</p>;
  return <FieldGrid fields={fields} values={values} setValue={(k, v) => setValues((s) => ({ ...s, [k]: v }))} />;
}

function RepeaterMock({ f }) {
  const o = f.options || {};
  const subs = subFields(f).filter((s) => !NO_VALUE.includes(s.type));
  const [rows, setRows] = useState(Math.max(1, Number(o.min) || 1));
  const table = (o.layout || 'table') === 'table';
  return (
    <div className="rounded-[4px] border border-[#dcdcde]">
      {table && subs.length > 0 && (
        <div className="flex border-b border-[#dcdcde] bg-[#f6f7f7] pl-7 text-[12px] font-semibold text-[#1d2327]">
          {subs.map((s) => (
            <div key={s.key} className="px-2 py-1.5" style={{ width: `${s.width || 100 / subs.length}%` }}>
              {s.label}
            </div>
          ))}
        </div>
      )}
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="flex border-b border-[#dcdcde] last:border-b-0">
          <span className="flex w-7 shrink-0 items-start justify-center border-r border-[#dcdcde] bg-[#f6f7f7] pt-2.5 text-[11px] text-[#787c82]">{i + 1}</span>
          <div className="min-w-0 flex-1">
            {table ? (
              <div className="flex">
                {subs.map((s) => (
                  <div key={s.key} className="px-2 py-1.5" style={{ width: `${s.width || 100 / subs.length}%` }}>
                    <MockControl f={s} onChange={() => {}} />
                  </div>
                ))}
              </div>
            ) : (
              <SubGrid fields={subFields(f)} />
            )}
          </div>
        </div>
      ))}
      {!subs.length && <p className="p-3 text-[12px] text-[#646970]">{__('Add sub fields to build the row.', 'brik-builder')}</p>}
      <div className="flex justify-end border-t border-[#dcdcde] bg-[#f6f7f7] p-1.5">
        <button type="button" onClick={() => setRows(rows + 1)} className="rounded-[3px] bg-[#2271b1] px-2.5 py-1 text-[12px] text-white cursor-pointer">
          {o.button_label || __('Add row', 'brik-builder')}
        </button>
      </div>
    </div>
  );
}
