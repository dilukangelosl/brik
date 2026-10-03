// Small shadcn-style primitives for the builder interface.
import { useState, useEffect, useRef, createPortal } from './wp.js';
import { Icon } from './icons.js';

export function cn(...parts) {
  return parts.filter(Boolean).join(' ');
}

const variants = {
  default: 'bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs',
  secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
  outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground shadow-xs',
  ghost: 'hover:bg-accent hover:text-accent-foreground',
  destructive: 'bg-destructive text-white hover:bg-destructive/90',
  brand: 'bg-brand text-white hover:bg-brand/90 shadow-xs',
};

const sizes = {
  xs: 'h-7 px-2 text-xs gap-1',
  sm: 'h-8 px-3 text-sm gap-1.5',
  md: 'h-9 px-4 text-sm gap-2',
  icon: 'size-8',
  'icon-sm': 'size-7',
};

export function Button({ variant = 'default', size = 'md', className, icon, children, active, ...props }) {
  return (
    <button
      type="button"
      className={cn(
        'inline-flex items-center justify-center rounded-md font-medium whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50 disabled:pointer-events-none cursor-pointer',
        variants[variant],
        sizes[size],
        active && 'bg-accent text-accent-foreground',
        className
      )}
      {...props}
    >
      {icon && <Icon name={icon} size={size === 'icon-sm' || size === 'xs' ? 14 : 16} />}
      {children}
    </button>
  );
}

export function IconButton({ icon, label, size = 'icon', variant = 'ghost', ...props }) {
  return <Button icon={icon} size={size} variant={variant} title={label} aria-label={label} {...props} />;
}

export const inputClass =
  'h-8 w-full min-w-0 rounded-md border border-input bg-background px-2.5 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export function Input({ className, ...props }) {
  return <input className={cn(inputClass, className)} {...props} />;
}

export function Textarea({ className, ...props }) {
  return <textarea className={cn(inputClass, 'h-auto min-h-20 py-2 leading-relaxed', className)} {...props} />;
}

export function Select({ options = [], value, onChange, className, placeholder, ...props }) {
  return (
    <select className={cn(inputClass, 'pr-7 appearance-none bk-select-chevron', className)} value={value ?? ''} onChange={(e) => onChange(e.target.value)} {...props}>
      {placeholder !== undefined && <option value="">{placeholder}</option>}
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

export function Switch({ checked, onChange, label }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={!!checked}
      aria-label={label}
      onClick={() => onChange(!checked)}
      className={cn(
        'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full border border-transparent transition-colors outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-pointer',
        checked ? 'bg-primary' : 'bg-input'
      )}
    >
      <span className={cn('pointer-events-none block size-4 rounded-full bg-background shadow-sm transition-transform', checked ? 'translate-x-4' : 'translate-x-0.5')} />
    </button>
  );
}

export function Tabs({ tabs, value, onChange, className }) {
  return (
    <div className={cn('inline-flex h-8 items-center rounded-lg bg-muted p-0.5 text-muted-foreground', className)} role="tablist">
      {tabs.map((t) => (
        <button
          key={t.value}
          type="button"
          role="tab"
          aria-selected={value === t.value}
          onClick={() => onChange(t.value)}
          className={cn(
            'inline-flex h-7 flex-1 items-center justify-center gap-1.5 rounded-md px-2.5 text-xs font-medium whitespace-nowrap transition-all cursor-pointer',
            value === t.value ? 'bg-background text-foreground shadow-sm' : 'hover:text-foreground'
          )}
        >
          {t.icon && <Icon name={t.icon} size={14} />}
          {t.label}
        </button>
      ))}
    </div>
  );
}

export function Segmented({ options, value, onChange }) {
  return (
    <div className="inline-flex rounded-md border border-input p-0.5 shadow-xs">
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          title={o.label}
          aria-pressed={value === o.value}
          onClick={() => onChange(value === o.value ? '' : o.value)}
          className={cn('inline-flex h-6 min-w-7 items-center justify-center rounded px-1.5 text-xs cursor-pointer', value === o.value ? 'bg-accent text-foreground' : 'text-muted-foreground hover:text-foreground')}
        >
          {o.icon ? <Icon name={o.icon} size={14} /> : o.label}
        </button>
      ))}
    </div>
  );
}

export function Collapsible({ title, defaultOpen = false, children, count }) {
  const [open, setOpen] = useState(defaultOpen);
  return (
    <div className="border-b border-border">
      <button type="button" className="flex w-full items-center justify-between px-4 py-3 text-sm font-medium cursor-pointer hover:bg-accent/50" onClick={() => setOpen(!open)} aria-expanded={open}>
        <span className="flex items-center gap-2">
          {title}
          {count ? <span className="size-1.5 rounded-full bg-brand" title="Has values" /> : null}
        </span>
        <Icon name="chevron-down" size={16} className={cn('text-muted-foreground transition-transform', open && 'rotate-180')} />
      </button>
      {open && <div className="space-y-4 px-4 pb-4">{children}</div>}
    </div>
  );
}

/** Popover anchored to a trigger; closes on outside click and Escape. */
export function Popover({ trigger, children, align = 'start', width = 260, open: controlled, onOpenChange }) {
  const [own, setOwn] = useState(false);
  const open = controlled ?? own;
  const setOpen = onOpenChange ?? setOwn;
  const ref = useRef(null);
  const panel = useRef(null);
  const [pos, setPos] = useState(null);

  useEffect(() => {
    if (!open) return;
    const r = ref.current.getBoundingClientRect();
    const left = align === 'end' ? r.right - width : r.left;
    const top = r.bottom + 6;
    setPos({ left: Math.max(8, Math.min(left, window.innerWidth - width - 8)), top });
    const close = (e) => {
      if (!ref.current.contains(e.target) && !(panel.current && panel.current.contains(e.target))) setOpen(false);
    };
    const esc = (e) => e.key === 'Escape' && setOpen(false);
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', esc);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', esc);
    };
  }, [open]);

  useEffect(() => {
    // Flip above the trigger when there's no room below.
    if (open && pos && panel.current) {
      const h = panel.current.offsetHeight;
      if (pos.top + h > window.innerHeight - 8) {
        const r = ref.current.getBoundingClientRect();
        const top = Math.max(8, r.top - h - 6);
        if (top !== pos.top) setPos({ ...pos, top });
      }
    }
  });

  return (
    <span ref={ref} className="inline-flex">
      <span className="inline-flex w-full" onClick={() => setOpen(!open)}>
        {trigger}
      </span>
      {open &&
        pos &&
        createPortal(
          <div ref={panel} className="bk-pop fixed z-[1000] rounded-lg border border-border bg-popover p-3 text-popover-foreground shadow-lg" style={{ left: pos.left, top: pos.top, width }}>
            {typeof children === 'function' ? children(() => setOpen(false)) : children}
          </div>,
          document.getElementById('brik-app')
        )}
    </span>
  );
}

export function Dialog({ title, description, onClose, children, footer, size = 'md' }) {
  useEffect(() => {
    const esc = (e) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', esc);
    return () => document.removeEventListener('keydown', esc);
  }, []);
  const widths = { sm: 'max-w-md', md: 'max-w-xl', lg: 'max-w-3xl', xl: 'max-w-5xl' };
  return createPortal(
    <div className="fixed inset-0 z-[900] flex items-start justify-center overflow-y-auto bg-black/50 p-4 pt-[8vh] bk-fade" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div role="dialog" aria-modal="true" className={cn('relative w-full rounded-xl border border-border bg-background shadow-xl bk-zoom', widths[size])}>
        <div className="flex items-start justify-between gap-4 p-5 pb-3">
          <div>
            <h2 className="text-base font-semibold leading-none">{title}</h2>
            {description && <p className="mt-1.5 text-sm text-muted-foreground">{description}</p>}
          </div>
          <IconButton icon="x" label="Close" size="icon-sm" onClick={onClose} />
        </div>
        <div className="max-h-[70vh] overflow-y-auto px-5 pb-5">{children}</div>
        {footer && <div className="flex justify-end gap-2 border-t border-border px-5 py-3">{footer}</div>}
      </div>
    </div>,
    document.getElementById('brik-app')
  );
}

export function Empty({ icon = 'inbox', title, children }) {
  return (
    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-4 py-8 text-center">
      <Icon name={icon} size={22} className="text-muted-foreground" />
      <p className="text-sm font-medium">{title}</p>
      {children && <div className="text-xs text-muted-foreground">{children}</div>}
    </div>
  );
}

export function Kbd({ children }) {
  return <kbd className="pointer-events-none inline-flex h-5 min-w-5 items-center justify-center rounded border border-border bg-muted px-1 font-mono text-[10px] font-medium text-muted-foreground">{children}</kbd>;
}

export function Label({ children, className, ...props }) {
  return (
    <label className={cn('text-xs font-medium text-foreground/80', className)} {...props}>
      {children}
    </label>
  );
}
