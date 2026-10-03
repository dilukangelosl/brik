// Menu icon picker: Dashicons (native to wp-admin) or Lucide icons (saved as "lucide:name").
import { useState, useEffect, useMemo, __ } from './wp.js';
import { Icon, iconNames, iconSvg } from '../builder/icons.js';
import { cn, Input } from '../builder/ui.jsx';
import { Segment, MenuIcon } from './primitives.jsx';
import { cfg } from './lib.js';

function dashicons() {
  const raw = cfg.dashicons || [];
  const list = Array.isArray(raw) ? raw : Object.keys(raw);
  return list.map((n) => (String(n).startsWith('dashicons-') ? n : `dashicons-${n}`));
}

let tags = null;
function useIconTags() {
  const [t, setT] = useState(tags);
  useEffect(() => {
    if (tags || !cfg.iconsUrl) return;
    fetch(cfg.iconsUrl.replace(/icons\.json.*$/, 'icon-tags.json'))
      .then((r) => r.json())
      .then((json) => {
        tags = json;
        setT(json);
      })
      .catch(() => {});
  }, []);
  return t;
}

export function IconPicker({ value, onPick }) {
  const [set, setSet] = useState(value && value.startsWith('lucide:') ? 'lucide' : 'dashicons');
  const [q, setQ] = useState('');
  const t = useIconTags();
  const names = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (set === 'dashicons') return dashicons().filter((n) => !term || n.includes(term));
    const all = iconNames();
    if (!term) return all.slice(0, 280).map((n) => `lucide:${n}`);
    return all
      .filter((n) => n.includes(term) || (t && t[n] && t[n].some((x) => x.includes(term))))
      .slice(0, 280)
      .map((n) => `lucide:${n}`);
  }, [q, set, t]);

  return (
    <div className="space-y-2">
      <Segment
        className="w-full"
        value={set}
        onChange={setSet}
        options={[
          { value: 'dashicons', label: __('Dashicons', 'brik-builder') },
          { value: 'lucide', label: __('Lucide', 'brik-builder') },
        ]}
      />
      <div className="relative">
        <Icon name="search" size={14} className="pointer-events-none absolute top-1/2 left-2.5 -translate-y-1/2 text-muted-foreground" />
        <Input autoFocus className="pl-8" value={q} onChange={(e) => setQ(e.target.value)} placeholder={__('Search icons…', 'brik-builder')} />
      </div>
      <div className="grid max-h-64 grid-cols-8 gap-1 overflow-y-auto pr-0.5" data-icon-grid={set}>
        {names.map((n) => (
          <button
            key={n}
            type="button"
            title={n.replace(/^(lucide:|dashicons-)/, '')}
            onClick={() => onPick(n)}
            className={cn('flex aspect-square items-center justify-center rounded-md text-foreground/80 hover:bg-accent hover:text-foreground cursor-pointer', value === n && 'bg-accent text-foreground ring-1 ring-brand')}
          >
            {set === 'lucide' ? <span dangerouslySetInnerHTML={{ __html: iconSvg(n.slice(7), 18) }} /> : <MenuIcon icon={n} size={20} />}
          </button>
        ))}
        {!names.length && <p className="col-span-8 py-6 text-center text-xs text-muted-foreground">{__('No icons found.', 'brik-builder')}</p>}
      </div>
    </div>
  );
}
