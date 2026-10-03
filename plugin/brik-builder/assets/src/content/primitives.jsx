// Primitives for the content app. The builder's Dialog and Popover portal into #brik-app,
// which doesn't exist on this admin page, so this file has local versions that portal into
// our own root (keeping the scoped styles) and sit above the admin bar.
import { useState, useEffect, useRef, createPortal, createRoot, __ } from '../builder/wp.js';
const { forwardRef } = window.wp.element;
import { Icon } from '../builder/icons.js';
import { cn, Button, IconButton, inputClass } from '../builder/ui.jsx';
import { useStore } from './lib.js';

const root = () => document.getElementById('brik-content-app') || document.body;

export function Dialog({ title, description, onClose, children, footer, size = 'md', icon }) {
  const box = useRef(null);
  useEffect(() => {
    const esc = (e) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    document.addEventListener('keydown', esc);
    const prev = document.activeElement;
    // Focus the first input, or the dialog itself so Escape works straight away.
    requestAnimationFrame(() => {
      if (!box.current) return;
      const first = box.current.querySelector('[data-autofocus], input:not([type=hidden]), textarea, select');
      (first || box.current).focus();
    });
    return () => {
      document.removeEventListener('keydown', esc);
      if (prev && prev.focus) prev.focus();
    };
  }, []);
  const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl' };
  return createPortal(
    <div className="fixed inset-0 z-[100050] flex items-start justify-center overflow-y-auto bg-black/40 p-4 pt-[9vh] backdrop-blur-[2px] bk-fade" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div ref={box} tabIndex={-1} role="dialog" aria-modal="true" aria-label={typeof title === 'string' ? title : undefined} className={cn('relative w-full rounded-xl border border-border bg-background shadow-2xl outline-none bk-zoom', widths[size])}>
        <div className="flex items-start justify-between gap-4 p-5 pb-3">
          <div className="flex items-start gap-3">
            {icon && (
              <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-foreground">
                <Icon name={icon} size={16} />
              </span>
            )}
            <div>
              <h2 className="text-base font-semibold leading-snug">{title}</h2>
              {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
            </div>
          </div>
          <IconButton icon="x" label={__('Close', 'brik-builder')} size="icon-sm" onClick={onClose} />
        </div>
        <div className="max-h-[68vh] overflow-y-auto px-5 pb-5">{children}</div>
        {footer && <div className="flex items-center justify-end gap-2 rounded-b-xl border-t border-border bg-muted/40 px-5 py-3">{footer}</div>}
      </div>
    </div>,
    root()
  );
}

/** Popover anchored to a trigger; closes on outside click and Escape. */
export function Popover({ trigger, children, align = 'start', width = 260, open: controlled, onOpenChange, className }) {
  const [own, setOwn] = useState(false);
  const open = controlled ?? own;
  const setOpen = onOpenChange ?? setOwn;
  const ref = useRef(null);
  const panel = useRef(null);
  const [pos, setPos] = useState(null);

  useEffect(() => {
    if (!open) return;
    const place = () => {
      const r = ref.current.getBoundingClientRect();
      const w = Math.min(width, window.innerWidth - 16);
      const left = align === 'end' ? r.right - w : r.left;
      let top = r.bottom + 6;
      const h = panel.current ? panel.current.offsetHeight : 0;
      // Flip above only when it doesn't fit below and there is more room above.
      if (h && top + h > window.innerHeight - 8 && r.top > window.innerHeight - r.bottom) top = Math.max(8, r.top - h - 6);
      setPos({ left: Math.max(8, Math.min(left, window.innerWidth - w - 8)), top, w });
    };
    place();
    requestAnimationFrame(place);
    const ro = new ResizeObserver(place);
    requestAnimationFrame(() => panel.current && ro.observe(panel.current));
    const close = (e) => {
      if (!ref.current.contains(e.target) && !(panel.current && panel.current.contains(e.target))) setOpen(false);
    };
    const esc = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', esc);
    window.addEventListener('scroll', place, true);
    window.addEventListener('resize', place);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', esc);
      window.removeEventListener('scroll', place, true);
      window.removeEventListener('resize', place);
      ro.disconnect();
    };
  }, [open]);

  return (
    <span ref={ref} className={cn('inline-flex', className)}>
      <span className="inline-flex w-full" onClick={() => setOpen(!open)}>
        {trigger}
      </span>
      {open &&
        createPortal(
          <div ref={panel} className="bk-pop fixed z-[100060] rounded-lg border border-border bg-popover p-2 text-popover-foreground shadow-lg" style={{ left: pos ? pos.left : -9999, top: pos ? pos.top : 0, width: pos ? pos.w : width }}>
            {typeof children === 'function' ? children(() => setOpen(false)) : children}
          </div>,
          root()
        )}
    </span>
  );
}

/** Dropdown menu of actions. */
export function Menu({ trigger, items, align = 'end', width = 200 }) {
  return (
    <Popover trigger={trigger} align={align} width={width}>
      {(close) => (
        <div className="flex flex-col" role="menu">
          {items.filter(Boolean).map((it, i) =>
            it === '-' ? (
              <div key={i} className="my-1 h-px bg-border" />
            ) : (
              <button
                key={i}
                type="button"
                role="menuitem"
                className={cn('flex h-8 items-center gap-2 rounded-md px-2 text-left text-sm cursor-pointer hover:bg-accent', it.danger && 'text-destructive hover:bg-destructive/10')}
                onClick={() => {
                  close();
                  it.onClick();
                }}
              >
                {it.icon && <Icon name={it.icon} size={15} className={it.danger ? '' : 'text-muted-foreground'} />}
                {it.label}
              </button>
            )
          )}
        </div>
      )}
    </Popover>
  );
}

/**
 * Promise based confirmation. Resolves with false, or true / the extra option values.
 * @param {Object} opts { title, message, confirm, danger, check: { label } }
 */
export function confirmDialog(opts) {
  return new Promise((resolve) => {
    const host = document.createElement('div');
    root().appendChild(host);
    const r = createRoot(host);
    const done = (v) => {
      r.unmount();
      host.remove();
      resolve(v);
    };
    r.render(<ConfirmBody {...opts} done={done} />);
  });
}

function ConfirmBody({ title, message, confirm, cancel, danger, check, done, icon }) {
  const [checked, setChecked] = useState(false);
  return (
    <Dialog
      size="sm"
      title={title}
      icon={icon || (danger ? 'triangle-alert' : 'circle-question-mark')}
      onClose={() => done(false)}
      footer={
        <>
          <Button variant="outline" size="sm" onClick={() => done(false)}>
            {cancel || __('Cancel', 'brik-builder')}
          </Button>
          <Button variant={danger ? 'destructive' : 'default'} size="sm" data-autofocus onClick={() => done(check ? { checked } : true)}>
            {confirm || __('Continue', 'brik-builder')}
          </Button>
        </>
      }
    >
      {message && <div className="text-sm text-muted-foreground">{message}</div>}
      {check && (
        <label className="mt-4 flex cursor-pointer items-start gap-2.5 rounded-lg border border-border p-3 text-sm hover:bg-muted/50">
          <Checkbox checked={checked} onChange={setChecked} />
          <span>
            <span className="font-medium">{check.label}</span>
            {check.help && <span className="mt-0.5 block text-xs text-muted-foreground">{check.help}</span>}
          </span>
        </label>
      )}
    </Dialog>
  );
}

export function Checkbox({ checked, onChange, label, className, disabled }) {
  return (
    <button
      type="button"
      role="checkbox"
      aria-checked={!!checked}
      aria-label={label}
      disabled={disabled}
      onClick={(e) => {
        e.preventDefault();
        onChange(!checked);
      }}
      className={cn(
        'flex size-4 shrink-0 items-center justify-center rounded-[4px] border shadow-xs transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-pointer disabled:opacity-50',
        checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input bg-background',
        className
      )}
    >
      {checked && <Icon name="check" size={12} />}
    </button>
  );
}

/** Segmented control that always keeps a value. */
export function Segment({ options, value, onChange, className, size = 'md' }) {
  return (
    <div className={cn('inline-flex rounded-lg bg-muted p-0.5', className)} role="radiogroup">
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          role="radio"
          aria-checked={String(value) === String(o.value)}
          title={o.title || (typeof o.label === 'string' ? o.label : undefined)}
          onClick={() => onChange(o.value)}
          className={cn(
            'inline-flex flex-1 items-center justify-center gap-1.5 rounded-md px-2.5 font-medium whitespace-nowrap transition-all cursor-pointer',
            size === 'sm' ? 'h-6 text-xs' : 'h-7 text-xs',
            String(value) === String(o.value) ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
          )}
        >
          {o.icon && <Icon name={o.icon} size={14} />}
          {o.label}
        </button>
      ))}
    </div>
  );
}

export function Badge({ children, tone = 'default', className, icon }) {
  const tones = {
    default: 'bg-muted text-muted-foreground',
    brand: 'bg-brand/10 text-brand',
    success: 'bg-emerald-500/10 text-emerald-700',
    warn: 'bg-amber-500/15 text-amber-700',
    danger: 'bg-destructive/10 text-destructive',
    outline: 'border border-border text-muted-foreground',
  };
  return (
    <span className={cn('inline-flex h-5 items-center gap-1 rounded-full px-2 text-[11px] font-medium whitespace-nowrap', tones[tone], className)}>
      {icon && <Icon name={icon} size={11} />}
      {children}
    </span>
  );
}

/** Label + control + help/error, the standard form row. */
export function Row({ label, help, error, children, htmlFor, className, aside }) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {(label || aside) && (
        <div className="flex items-center justify-between gap-2">
          {label && (
            <label htmlFor={htmlFor} className="text-[13px] font-medium text-foreground">
              {label}
            </label>
          )}
          {aside}
        </div>
      )}
      {children}
      {error ? (
        <p className="flex items-center gap-1 text-xs text-destructive">
          <Icon name="circle-alert" size={12} />
          {error}
        </p>
      ) : help ? (
        <p className="text-xs text-muted-foreground">{help}</p>
      ) : null}
    </div>
  );
}

/** Setting with a switch on the right, for boolean options. */
export function SwitchRow({ label, help, checked, onChange, icon }) {
  return (
    <div className="flex items-start justify-between gap-4 py-0.5">
      <div className="flex min-w-0 items-start gap-2.5">
        {icon && <Icon name={icon} size={16} className="mt-0.5 text-muted-foreground" />}
        <div className="min-w-0">
          <div className="cursor-pointer text-[13px] font-medium" onClick={() => onChange(!checked)}>
            {label}
          </div>
          {help && <p className="mt-0.5 text-xs text-muted-foreground">{help}</p>}
        </div>
      </div>
      <SwitchBtn checked={checked} onChange={onChange} label={typeof label === 'string' ? label : undefined} />
    </div>
  );
}

export function SwitchBtn({ checked, onChange, label, size = 'md' }) {
  const sm = size === 'sm';
  return (
    <button
      type="button"
      role="switch"
      aria-checked={!!checked}
      aria-label={label}
      onClick={(e) => {
        e.stopPropagation();
        onChange(!checked);
      }}
      className={cn(
        'relative inline-flex shrink-0 items-center rounded-full transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-pointer',
        sm ? 'h-4 w-7' : 'h-5 w-9',
        checked ? 'bg-primary' : 'bg-input'
      )}
    >
      <span className={cn('pointer-events-none block rounded-full bg-background shadow-sm transition-transform', sm ? 'size-3' : 'size-4', checked ? (sm ? 'translate-x-3.5' : 'translate-x-[18px]') : 'translate-x-0.5')} />
    </button>
  );
}

export function Card({ title, description, children, className, actions, icon, id }) {
  return (
    <section id={id} className={cn('rounded-xl border border-border bg-card shadow-xs', className)}>
      {(title || actions) && (
        <header className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
          <div className="flex items-start gap-3">
            {icon && (
              <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/50">
                <Icon name={icon} size={16} />
              </span>
            )}
            <div>
              <h3 className="text-sm font-semibold">{title}</h3>
              {description && <p className="mt-0.5 text-xs text-muted-foreground">{description}</p>}
            </div>
          </div>
          {actions}
        </header>
      )}
      <div className="space-y-5 p-5">{children}</div>
    </section>
  );
}

/** Multi select shown as removable chips with a searchable dropdown. */
export function MultiSelect({ options, value = [], onChange, placeholder, empty }) {
  const [q, setQ] = useState('');
  const selected = options.filter((o) => value.includes(o.value));
  const missing = value.filter((v) => !options.some((o) => o.value === v)).map((v) => ({ value: v, label: v }));
  const rest = options.filter((o) => !value.includes(o.value) && (!q || `${o.label} ${o.value}`.toLowerCase().includes(q.toLowerCase())));
  return (
    <div className={cn(inputClass, 'flex h-auto min-h-9 flex-wrap items-center gap-1.5 py-1.5')}>
      {[...selected, ...missing].map((o) => (
        <span key={o.value} className="inline-flex h-6 items-center gap-1 rounded-md border border-border bg-muted/60 pr-0.5 pl-2 text-xs font-medium">
          {o.icon && <Icon name={o.icon} size={12} />}
          {o.label}
          <button type="button" aria-label={__('Remove', 'brik-builder')} className="flex size-5 items-center justify-center rounded text-muted-foreground hover:bg-background hover:text-foreground cursor-pointer" onClick={() => onChange(value.filter((v) => v !== o.value))}>
            <Icon name="x" size={12} />
          </button>
        </span>
      ))}
      <Popover
        width={260}
        trigger={
          <button type="button" className="inline-flex h-6 items-center gap-1 rounded-md px-1.5 text-xs text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer">
            <Icon name="plus" size={12} />
            {selected.length ? __('Add', 'brik-builder') : placeholder || __('Select…', 'brik-builder')}
          </button>
        }
      >
        {(close) => (
          <div className="space-y-1.5">
            {options.length > 6 && <input className={inputClass} placeholder={__('Search…', 'brik-builder')} value={q} onChange={(e) => setQ(e.target.value)} autoFocus />}
            <div className="max-h-60 overflow-y-auto">
              {rest.length ? (
                rest.map((o) => (
                  <button
                    key={o.value}
                    type="button"
                    className="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-accent cursor-pointer"
                    onClick={() => {
                      onChange([...value, o.value]);
                      if (rest.length <= 1) close();
                    }}
                  >
                    {o.icon && <Icon name={o.icon} size={14} className="text-muted-foreground" />}
                    <span className="flex-1 truncate">{o.label}</span>
                    <span className="font-mono text-[11px] text-muted-foreground">{o.value}</span>
                  </button>
                ))
              ) : (
                <p className="px-2 py-3 text-center text-xs text-muted-foreground">{empty || __('Nothing left to add.', 'brik-builder')}</p>
              )}
            </div>
          </div>
        )}
      </Popover>
    </div>
  );
}

const TOAST_ICONS = { success: 'circle-check', error: 'circle-alert', info: 'info' };

export function Toasts() {
  const toasts = useStore((s) => s.toasts);
  return createPortal(
    <div className="pointer-events-none fixed top-[96px] right-5 z-[100100] flex flex-col items-end gap-2">
      {toasts.map((t) => (
        <div key={t.id} role="status" className={cn('bk-rise pointer-events-auto flex max-w-sm items-center gap-2.5 rounded-lg border border-border bg-popover px-3.5 py-2.5 text-sm shadow-lg', t.type === 'error' && 'border-destructive/30')}>
          <Icon name={TOAST_ICONS[t.type] || 'info'} size={16} className={t.type === 'success' ? 'text-emerald-600' : t.type === 'error' ? 'text-destructive' : 'text-muted-foreground'} />
          <span className={t.type === 'error' ? 'text-destructive' : ''}>{t.message}</span>
        </div>
      ))}
    </div>,
    root()
  );
}

/** Renders a post type menu icon: dashicon class, lucide:name, or an image URL. */
export function MenuIcon({ icon, size = 18, className }) {
  if (icon && icon.startsWith('lucide:')) return <Icon name={icon.slice(7)} size={size} className={className} />;
  if (icon && /^(https?:|data:)/.test(icon)) return <img src={icon} alt="" width={size} height={size} className={className} />;
  const cls = icon && icon.startsWith('dashicons-') ? icon : 'dashicons-admin-post';
  return <span className={cn('dashicons', cls, className)} style={{ fontSize: size, width: size, height: size }} aria-hidden="true" />;
}

export function Spinner({ className }) {
  return <span className={cn('bk-spinner', className)} />;
}

export function Kbd({ children }) {
  return <kbd className="inline-flex h-5 min-w-5 items-center justify-center rounded border border-border bg-muted px-1 font-mono text-[10px] font-medium text-muted-foreground">{children}</kbd>;
}

export const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
export const modKey = isMac ? '⌘' : 'Ctrl';

/** Text input that forwards its ref (the builder's Input doesn't). */
export const RefInput = forwardRef(function RefInput({ className, ...props }, ref) {
  return <input ref={ref} className={cn(inputClass, className)} {...props} />;
});
