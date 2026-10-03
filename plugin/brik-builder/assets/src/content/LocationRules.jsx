// Location rules: OR groups of AND rules deciding where a field group shows up.
import { __, sprintf } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, IconButton, Select, Input } from '../builder/ui.jsx';
import { useStore, getState } from './lib.js';

export const PARAMS = [
  { value: 'post_type', label: __('Post type', 'brik-builder') },
  { value: 'taxonomy', label: __('Taxonomy term', 'brik-builder') },
  { value: 'post_template', label: __('Page template', 'brik-builder') },
  { value: 'page_type', label: __('Page type', 'brik-builder') },
  { value: 'post_status', label: __('Post status', 'brik-builder') },
  { value: 'user_role', label: __('User role', 'brik-builder') },
  { value: 'options_page', label: __('Options page', 'brik-builder') },
];

const PAGE_TYPES = [
  { value: 'front_page', label: __('Front page', 'brik-builder') },
  { value: 'posts_page', label: __('Posts page', 'brik-builder') },
  { value: 'top_level', label: __('Top level (no parent)', 'brik-builder') },
  { value: 'child', label: __('Child (has parent)', 'brik-builder') },
];

const ROLES = [
  { value: 'all', label: __('Any role', 'brik-builder') },
  { value: 'administrator', label: __('Administrator', 'brik-builder') },
  { value: 'editor', label: __('Editor', 'brik-builder') },
  { value: 'author', label: __('Author', 'brik-builder') },
  { value: 'contributor', label: __('Contributor', 'brik-builder') },
  { value: 'subscriber', label: __('Subscriber', 'brik-builder') },
];

const HIDDEN_TYPES = ['attachment', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'brik_library', 'brik_template'];

export function postTypeOptions(s = getState()) {
  const own = s.post_types.map((p) => ({ value: p.key, label: p.plural || p.key }));
  const wp = Object.values(s.wp.types || {})
    .filter((t) => !HIDDEN_TYPES.includes(t.slug) && !own.some((o) => o.value === t.slug))
    .map((t) => ({ value: t.slug, label: t.name }));
  return [...wp, ...own];
}

export function taxonomyOptions(s = getState()) {
  const own = s.taxonomies.map((t) => ({ value: t.key, label: t.plural || t.key }));
  const wp = Object.values(s.wp.taxonomies || {})
    .filter((t) => !own.some((o) => o.value === t.slug) && t.slug !== 'nav_menu' && !String(t.slug).startsWith('wp_'))
    .map((t) => ({ value: t.slug, label: t.name }));
  return [...wp, ...own];
}

function serverParam(param, s) {
  return (s.location_params || []).find((p) => p.value === param);
}

function valueOptions(param, s) {
  // Brik's own types may not be registered yet (inactive or just created), so merge them in.
  const remote = serverParam(param, s);
  if (remote && Array.isArray(remote.choices)) {
    const own = param === 'post_type' ? postTypeOptions(s) : param === 'taxonomy' ? taxonomyOptions(s) : [];
    return [...remote.choices, ...own.filter((o) => !remote.choices.some((c) => c.value === o.value))];
  }
  switch (param) {
    case 'post_type':
      return postTypeOptions(s);
    case 'taxonomy':
      return taxonomyOptions(s);
    case 'page_type':
      return PAGE_TYPES;
    case 'post_status': {
      const st = Object.values(s.wp.statuses || {}).map((x) => ({ value: x.slug, label: x.name }));
      return st.length ? st : ['publish', 'draft', 'pending', 'private', 'future'].map((v) => ({ value: v, label: v }));
    }
    case 'user_role':
      return ROLES;
    case 'options_page':
      return [{ value: 'brik-options', label: __('Site options', 'brik-builder') }];
    default:
      return null;
  }
}

export function locationSummary(location) {
  const s = getState();
  if (!location || !location.length || !location[0].length) return __('Not shown anywhere yet', 'brik-builder');
  return location
    .map((g) =>
      g
        .map((r) => {
          const p = serverParam(r.param, s) || PARAMS.find((x) => x.value === r.param);
          const opts = valueOptions(r.param, s) || [];
          const v = (opts.find((o) => o.value === r.value) || {}).label || r.value || '…';
          return `${p ? p.label : r.param} ${r.operator === '!=' ? '≠' : '='} ${v}`;
        })
        .join(' & ')
    )
    .join(__(' or ', 'brik-builder'));
}

function blankRule(s) {
  const first = postTypeOptions(s).find((o) => !['post', 'page'].includes(o.value)) || postTypeOptions(s)[0];
  return { param: 'post_type', operator: '==', value: first ? first.value : 'post' };
}

export function LocationRules({ value = [], onChange }) {
  const s = useStore();
  const params = s.location_params && s.location_params.length ? s.location_params.map((p) => ({ value: p.value, label: p.label })) : PARAMS;
  const groups = value.length ? value : [];

  const setRule = (gi, ri, patch) => onChange(groups.map((g, i) => (i === gi ? g.map((r, j) => (j === ri ? { ...r, ...patch } : r)) : g)));
  const addRule = (gi) => onChange(groups.map((g, i) => (i === gi ? [...g, blankRule(s)] : g)));
  const removeRule = (gi, ri) => onChange(groups.map((g, i) => (i === gi ? g.filter((_, j) => j !== ri) : g)).filter((g) => g.length));

  return (
    <div className="space-y-2" data-location>
      {groups.map((g, gi) => (
        <div key={gi}>
          {gi > 0 && (
            <div className="my-2 flex items-center gap-2">
              <span className="h-px flex-1 bg-border" />
              <span className="rounded-full border border-border bg-background px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">{__('or', 'brik-builder')}</span>
              <span className="h-px flex-1 bg-border" />
            </div>
          )}
          <div className="space-y-1.5 rounded-lg border border-border bg-muted/20 p-2">
            {g.map((r, ri) => {
              const opts = valueOptions(r.param, s);
              return (
                <div key={ri} className="flex flex-col gap-1.5" data-rule>
                  {ri > 0 && <span className="pl-1 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase">{__('and', 'brik-builder')}</span>}
                  <div className="grid grid-cols-[minmax(0,1fr)_84px_auto] items-center gap-1.5">
                    <Select
                      className="h-8 text-[13px]"
                      value={r.param}
                      options={params}
                      onChange={(param) => {
                        const o = valueOptions(param, s);
                        setRule(gi, ri, { param, value: o && o[0] ? o[0].value : '' });
                      }}
                    />
                    <Select
                      className="h-8 text-[13px]"
                      value={r.operator}
                      options={[
                        { value: '==', label: __('is', 'brik-builder') },
                        { value: '!=', label: __('is not', 'brik-builder') },
                      ]}
                      onChange={(operator) => setRule(gi, ri, { operator })}
                    />
                    <IconButton icon="x" size="icon-sm" label={__('Remove rule', 'brik-builder')} onClick={() => removeRule(gi, ri)} />
                  </div>
                  {opts ? (
                    <Select className="h-8 text-[13px]" value={r.value} options={opts.some((o) => o.value === r.value) || !r.value ? opts : [...opts, { value: r.value, label: r.value }]} onChange={(v) => setRule(gi, ri, { value: v })} />
                  ) : (
                    <>
                      <Input className="h-8 font-mono text-[13px]" list="bk-templates" value={r.value} placeholder={__('Template file, e.g. templates/full-width.php', 'brik-builder')} onChange={(e) => setRule(gi, ri, { value: e.target.value })} />
                      <datalist id="bk-templates">
                        <option value="default" />
                      </datalist>
                    </>
                  )}
                </div>
              );
            })}
            <Button variant="ghost" size="xs" icon="plus" className="text-muted-foreground" onClick={() => addRule(gi)}>
              {__('And', 'brik-builder')}
            </Button>
          </div>
        </div>
      ))}
      {!groups.length && (
        <p className="flex items-start gap-2 rounded-lg border border-dashed border-amber-500/40 bg-amber-500/5 p-2.5 text-xs text-amber-800">
          <Icon name="triangle-alert" size={14} className="mt-px shrink-0" />
          {__('Add a rule so the fields show up somewhere.', 'brik-builder')}
        </p>
      )}
      <Button variant="outline" size="xs" icon="plus" onClick={() => onChange([...groups, [blankRule(s)]])}>
        {groups.length ? __('Add rule group (or)', 'brik-builder') : __('Add rule', 'brik-builder')}
      </Button>
    </div>
  );
}
