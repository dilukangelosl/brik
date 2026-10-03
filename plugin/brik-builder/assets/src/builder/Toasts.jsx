import { useStore } from './store.js';
import { Icon } from './icons.js';
import { cn } from './ui.jsx';

const ICONS = { success: 'circle-check', error: 'circle-alert', info: 'info' };

export function Toasts() {
  const toasts = useStore((s) => s.toasts);
  return (
    <div className="pointer-events-none fixed bottom-4 left-1/2 z-[1200] flex -translate-x-1/2 flex-col items-center gap-2">
      {toasts.map((t) => (
        <div key={t.id} className={cn('bk-zoom flex items-center gap-2 rounded-lg border border-border bg-popover px-3 py-2 text-sm shadow-lg', t.type === 'error' && 'border-destructive/40 text-destructive')}>
          <Icon name={ICONS[t.type] || 'info'} size={16} className={t.type === 'success' ? 'text-emerald-500' : ''} />
          {t.message}
        </div>
      ))}
    </div>
  );
}
