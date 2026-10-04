// Design system tree: colors, typography, spacing, radius & shadows, variables, components,
// classes and breakpoints. Edits save right away and restyle the canvas.
import { useState, useEffect, useRef, useMemo, api, config } from '../../wp.js';
import { useStore, setState, toast } from '../../store.js';
import { saveSettings } from '../../api.js';
import { reload } from '../../canvas.js';
import { Icon } from '../../icons.js';
import { ColorControl } from '../../fields.jsx';
import { insertLibraryItem } from '../../LeftPanel.jsx';
import { cn, Input, IconButton, Button, Select, Switch, Segmented } from '../../ui.jsx';
import { useDesign, refreshCss, saveVariables, deleteClass, duplicateClass, saveClass, useClasses, useUsage, loadUsage, useComponents, loadComponents, builderUrl, rerender, classCount } from './data.js';
import { VarPreview } from './VariablePicker.jsx';
import { editClass, slugify } from './Classes.jsx';

const TOKENS = ['background', 'foreground', 'card', 'card-foreground', 'popover', 'popover-foreground', 'primary', 'primary-foreground', 'secondary', 'secondary-foreground', 'muted', 'muted-foreground', 'accent', 'accent-foreground', 'destructive', 'border', 'input', 'ring'];

function useDebounced(fn, ms = 400) {
  const t = useRef(null);
  const latest = useRef(fn);
  latest.current = fn;
  useEffect(() => () => clearTimeout(t.current), []);
  return (...args) => {
    clearTimeout(t.current);
    t.current = setTimeout(() => latest.current(...args), ms);
  };
}

function useOpen() {
  const [open, setOpen] = useState(() => {
    try {
      return JSON.parse(localStorage.getItem('brik-ds-open')) || { colors: true, tokens: true };
    } catch (e) {
      return { colors: true, tokens: true };
    }
  });
  const toggle = (id, value) => {
    const next = { ...open, [id]: value ?? !open[id] };
    setOpen(next);
    try {
      localStorage.setItem('brik-ds-open', JSON.stringify(next));
    } catch (e) {}
  };
  return [open, toggle];
}

/* ------------------------------------------------------------------------
 * Tree rows.
 * ---------------------------------------------------------------------- */

function Branch({ id, icon, label, count, depth = 0, open, onToggle, actions, children }) {
  const isOpen = !!open[id];
  return (
    <div>
      <div className={cn('group flex h-8 items-center gap-1.5 pr-2 hover:bg-accent/60', depth === 0 && 'font-medium')} style={{ paddingLeft: 8 + depth * 14 }}>
        <button type="button" className="flex min-w-0 flex-1 cursor-pointer items-center gap-1.5 text-left" onClick={() => onToggle(id)} aria-expanded={isOpen}>
          <Icon name="chevron-right" size={13} className={cn('shrink-0 text-muted-foreground transition-transform', isOpen && 'rotate-90')} />
          <Icon name={icon} size={14} className={cn('shrink-0', depth === 0 ? 'text-foreground/70' : 'text-muted-foreground')} />
          <span className="truncate text-[13px]">{label}</span>
          {count !== undefined && <span className="rounded-full bg-muted px-1.5 text-[10px] font-normal tabular-nums text-muted-foreground">{count}</span>}
        </button>
        {actions && <span className="flex shrink-0 items-center opacity-0 transition-opacity group-hover:opacity-100 focus-within:opacity-100">{actions}</span>}
      </div>
      {isOpen && <div className={cn(depth === 0 && 'pb-1')}>{children}</div>}
    </div>
  );
}

function Leaf({ depth = 1, lead, label, meta, selected, onSelect, children, mono = true }) {
  return (
    <div className={cn(selected && 'bg-accent/40')}>
      <button
        type="button"
        onClick={onSelect}
        className={cn('flex h-8 w-full cursor-pointer items-center gap-2 pr-2.5 text-left hover:bg-accent/60', selected && 'bg-accent hover:bg-accent')}
        style={{ paddingLeft: 8 + depth * 14 + 19 }}
      >
        {lead}
        <span className={cn('min-w-0 flex-1 truncate text-xs', mono && 'font-mono text-[11.5px]')}>{label}</span>
        {meta !== undefined && <span className="max-w-32 truncate font-mono text-[10.5px] text-muted-foreground">{meta}</span>}
      </button>
      {selected && children && (
        <div className="space-y-2.5 border-y border-border/60 bg-muted/30 py-3 pr-3" style={{ paddingLeft: 8 + depth * 14 + 19 }}>
          {children}
        </div>
      )}
    </div>
  );
}

function Row({ label, children }) {
  return (
    <div className="grid grid-cols-[64px_1fr] items-center gap-2">
      <span className="text-[11px] text-muted-foreground">{label}</span>
      <div className="min-w-0">{children}</div>
    </div>
  );
}

function ValueInput({ value, placeholder, onChange, disabled }) {
  const [draft, setDraft] = useState(value ?? '');
  useEffect(() => setDraft(value ?? ''), [value]);
  return (
    <Input
      value={draft}
      disabled={disabled}
      placeholder={placeholder}
      className="h-7 font-mono text-xs"
      onChange={(e) => {
        setDraft(e.target.value);
        onChange(e.target.value);
      }}
    />
  );
}

function Swatch({ color }) {
  return (
    <span className="bk-checker relative size-4 shrink-0 overflow-hidden rounded-[4px] ring-1 ring-black/10">
      <span className="absolute inset-0" style={{ background: color }} />
    </span>
  );
}

/* ------------------------------------------------------------------------
 * Panel.
 * ---------------------------------------------------------------------- */

export function DesignPanel() {
  const design = useDesign();
  const settings = useStore((s) => s.settings);
  const focus = useStore((s) => s.dsFocus);
  const [open, toggle] = useOpen();
  const [sel, setSel] = useState(null);
  const [q, setQ] = useState('');

  useEffect(() => {
    if (!focus) return;
    toggle(focus.section, true);
    setSel(`${focus.section}:${focus.id}`);
    setState({ dsFocus: null });
  }, [focus]);

  if (!design || !settings) {
    return (
      <div className="flex justify-center p-8">
        <span className="bk-spinner" />
      </div>
    );
  }

  const term = q.trim().toLowerCase();
  const openState = term ? new Proxy({}, { get: () => true }) : open;
  const pick = (key) => setSel(sel === key ? null : key);
  const props = { open: openState, onToggle: toggle, sel, pick, term, settings, design };

  return (
    <div className="flex h-full flex-col">
      <div className="space-y-2 border-b border-border p-3">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-semibold">Design system</p>
            <p className="text-[11px] text-muted-foreground">Edits apply site-wide instantly.</p>
          </div>
        </div>
        <div className="relative">
          <Icon name="search" size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Filter tokens, variables, classes…" className="h-8 pl-8 text-xs" />
        </div>
        {!config.canManage && <p className="rounded-md bg-muted px-2 py-1.5 text-[11px] text-muted-foreground">Read only: changing the design system needs the "edit theme options" capability.</p>}
      </div>
      <div className="flex-1 overflow-y-auto py-1.5 text-sm">
        <ColorsBranch {...props} />
        <TypographyBranch {...props} />
        <ScaleBranch {...props} id="space" icon="ruler" label="Spacing" />
        <Branch id="shape" icon="square-round-corner" label="Radius & shadows" open={openState} onToggle={toggle}>
          <RadiusBase {...props} />
          <ScaleBranch {...props} id="radius" icon="square-round-corner" label="Radius" depth={1} />
          <ScaleBranch {...props} id="shadow" icon="layers-2" label="Shadows" depth={1} />
        </Branch>
        <CustomBranch {...props} />
        <ComponentsBranch {...props} />
        <ClassesBranch {...props} />
        <BreakpointsBranch {...props} />
      </div>
    </div>
  );
}

/* ------------------------------------------------------------------------
 * Colors.
 * ---------------------------------------------------------------------- */

function ColorsBranch({ open, onToggle, sel, pick, term, settings }) {
  const [mode, setMode] = useState('light');
  const resolved = settings.resolved || { light: {}, dark: {} };
  const save = useDebounced(async (changes) => {
    await saveSettings(changes);
    await refreshCss();
  }, 350);

  const setToken = (t, v) => {
    const tokens = { light: { ...((settings.tokens && settings.tokens.light) || {}) }, dark: { ...((settings.tokens && settings.tokens.dark) || {}) } };
    if (v) tokens[mode][t] = v;
    else delete tokens[mode][t];
    setState((s) => ({ settings: { ...s.settings, tokens, resolved: { ...s.settings.resolved, [mode]: { ...s.settings.resolved[mode], ...(v ? { [t]: v } : {}) } } } }));
    save({ tokens });
  };

  const colors = settings.colors || [];
  const setColors = (next) => {
    setState((s) => ({ settings: { ...s.settings, colors: next } }));
    save({ colors: next });
  };

  const tokens = TOKENS.filter((t) => !term || t.includes(term));
  const globals = colors.filter((c) => !term || `${c.name} ${c.id}`.toLowerCase().includes(term));

  return (
    <Branch id="colors" icon="palette" label="Colors" open={open} onToggle={onToggle}>
      <Branch
        id="tokens"
        icon="swatch-book"
        label="Theme tokens"
        depth={1}
        count={tokens.length}
        open={open}
        onToggle={onToggle}
      >
        <div className="flex items-center gap-2 py-1 pr-3 text-[11px] text-muted-foreground" style={{ paddingLeft: 49 }}>
          <span className="flex-1">Showing {mode} mode</span>
          <Segmented value={mode} onChange={(m) => setMode(m || mode)} options={[{ value: 'light', label: 'Light', icon: 'sun' }, { value: 'dark', label: 'Dark', icon: 'moon' }]} />
        </div>
        {tokens.map((t) => {
          const custom = settings.tokens && settings.tokens[mode] && settings.tokens[mode][t];
          return (
            <Leaf
              key={t}
              depth={2}
              lead={<Swatch color={resolved[mode][t]} />}
              label={`--${t}`}
              meta={custom ? 'custom' : ''}
              selected={sel === `token:${t}`}
              onSelect={() => pick(`token:${t}`)}
            >
              <ColorControl value={custom || ''} placeholder={resolved[mode][t]} onChange={(v) => config.canManage && setToken(t, v)} />
              <p className="text-[10.5px] leading-snug text-muted-foreground">
                {mode === 'light' ? 'Light' : 'Dark'} value of <code>var(--{t})</code>. Empty uses the base palette{settings.accent ? ' and accent' : ''}.
              </p>
            </Leaf>
          );
        })}
      </Branch>
      <Branch
        id="globals"
        icon="droplets"
        label="Global colors"
        depth={1}
        count={colors.length}
        open={open}
        onToggle={onToggle}
        actions={
          config.canManage && (
            <IconButton
              icon="plus"
              size="icon-sm"
              label="Add color"
              onClick={(e) => {
                e.stopPropagation();
                const id = Math.random().toString(36).slice(2, 8);
                setColors([...colors, { id, name: 'New color', value: '#6366f1' }]);
                onToggle('globals', true);
                pick(`color:${id}`);
              }}
            />
          )
        }
      >
        {!colors.length && <p className="py-1.5 text-[11px] text-muted-foreground" style={{ paddingLeft: 49 }}>No global colors yet.</p>}
        {globals.map((c) => (
          <Leaf key={c.id} depth={2} lead={<Swatch color={c.value} />} label={c.name || c.id} mono={false} meta={c.value} selected={sel === `color:${c.id}`} onSelect={() => pick(`color:${c.id}`)}>
            <Row label="Name">
              <ValueInput value={c.name} disabled={!config.canManage} onChange={(name) => setColors(colors.map((x) => (x.id === c.id ? { ...x, name } : x)))} />
            </Row>
            <Row label="Value">
              <ColorControl value={c.value} onChange={(value) => config.canManage && value && setColors(colors.map((x) => (x.id === c.id ? { ...x, value } : x)))} />
            </Row>
            <div className="flex items-center justify-between">
              <code className="text-[10.5px] text-muted-foreground">var(--brik-color-{c.id})</code>
              {config.canManage && (
                <Button size="xs" variant="ghost" icon="trash-2" onClick={() => setColors(colors.filter((x) => x.id !== c.id))}>
                  Remove
                </Button>
              )}
            </div>
          </Leaf>
        ))}
      </Branch>
    </Branch>
  );
}

/* ------------------------------------------------------------------------
 * Typography.
 * ---------------------------------------------------------------------- */

function TypographyBranch(props) {
  const { open, onToggle, sel, pick, settings, design } = props;
  const fonts = useStore((s) => (s.schema ? s.schema.fonts : []));
  const saved = design.saved || {};
  const headings = saved.headings || {};
  const textVars = (design.variables && design.variables.text) || [];
  const saveFonts = async (changes) => {
    await saveSettings(changes);
    reload();
  };
  const setHeading = (tag, key, value) => {
    const next = { ...headings, [tag]: { ...(headings[tag] || {}), [key]: value } };
    if (!value) delete next[tag][key];
    saveVariables({ ...saved, headings: next });
  };

  return (
    <Branch id="type" icon="type" label="Typography" open={open} onToggle={onToggle}>
      <Branch id="fonts" icon="case-sensitive" label="Fonts" depth={1} open={open} onToggle={onToggle}>
        {[
          ['font_body', 'Body', 'System UI'],
          ['font_heading', 'Headings', 'Same as body'],
        ].map(([key, label, ph]) => (
          <Leaf key={key} depth={2} lead={<span className="w-4 text-center text-sm font-semibold" style={{ fontFamily: settings[key] || undefined }}>Aa</span>} label={label} mono={false} meta={settings[key] || ph} selected={sel === `font:${key}`} onSelect={() => pick(`font:${key}`)}>
            <Select value={settings[key] || ''} onChange={(v) => config.canManage && saveFonts({ [key]: v })} placeholder={ph} options={fonts.map((f) => ({ value: f, label: f }))} className="h-7 text-xs" />
          </Leaf>
        ))}
      </Branch>
      <ScaleBranch {...props} id="text" icon="a-large-small" label="Type scale" depth={1} extra={<FluidToggle design={design} />} />
      <Branch id="headings" icon="heading" label="Heading styles" depth={1} open={open} onToggle={onToggle}>
        {['h1', 'h2', 'h3', 'h4', 'h5', 'h6'].map((tag) => {
          const hs = headings[tag] || {};
          return (
            <Leaf key={tag} depth={2} lead={<span className="w-4 text-center text-[11px] font-bold uppercase text-muted-foreground">{tag}</span>} label={hs.size ? hs.size.replace(/^var\(--(.*)\)$/, '$1') : 'Module default'} mono={!!hs.size} meta={hs.weight || ''} selected={sel === `h:${tag}`} onSelect={() => pick(`h:${tag}`)}>
              <Row label="Size">
                <Select value={hs.size || ''} onChange={(v) => config.canManage && setHeading(tag, 'size', v)} placeholder="Module default" options={textVars.map((v) => ({ value: `var(--${v.name})`, label: `${v.name} · ${v.value}` }))} className="h-7 text-xs" />
              </Row>
              <Row label="Weight">
                <Select value={hs.weight || ''} onChange={(v) => config.canManage && setHeading(tag, 'weight', v)} placeholder="Default" options={['300', '400', '500', '600', '700', '800', '900'].map((w) => ({ value: w, label: w }))} className="h-7 text-xs" />
              </Row>
              <Row label="Leading">
                <ValueInput value={hs.line_height || ''} placeholder="1.1" disabled={!config.canManage} onChange={(v) => setHeading(tag, 'line_height', v)} />
              </Row>
              <p className="text-[10.5px] leading-snug text-muted-foreground">Applies to every {tag.toUpperCase()} in Brik content; element settings still win.</p>
            </Leaf>
          );
        })}
      </Branch>
    </Branch>
  );
}

function FluidToggle({ design }) {
  const saved = design.saved || {};
  return (
    <label className="flex items-center justify-between gap-3 py-1.5 pr-3 text-[11px]" style={{ paddingLeft: 49 }}>
      <span>
        <span className="block font-medium text-foreground">Fluid type</span>
        <span className="text-muted-foreground">Large sizes scale between 360px and 1280px wide screens.</span>
      </span>
      <Switch checked={!!saved.fluid} onChange={(v) => config.canManage && saveVariables({ ...saved, fluid: v })} label="Fluid type" />
    </label>
  );
}

/* ------------------------------------------------------------------------
 * Scales (spacing, type, radius, shadow).
 * ---------------------------------------------------------------------- */

function ScaleBranch({ id, icon, label, depth = 0, extra, open, onToggle, sel, pick, term, settings, design }) {
  const list = ((design.variables && design.variables[id]) || []).filter((v) => !term || v.name.includes(term));
  const saved = design.saved || {};
  const radius = settings.radius || '0.625rem';
  const save = useDebounced((step, value) => {
    const group = { ...(saved[id] || {}) };
    if (value) group[step] = value;
    else delete group[step];
    saveVariables({ ...saved, [id]: group });
  }, 450);
  if (term && !list.length) return null;
  return (
    <Branch id={id} icon={icon} label={label} depth={depth} count={list.length} open={open} onToggle={onToggle}>
      {extra}
      {list.map((v) => {
        const preview = String(v.value).replace(/var\(--radius\)/g, radius);
        const changed = v.value !== v.default;
        return (
          <Leaf
            key={v.name}
            depth={depth + 1}
            lead={<VarPreview v={{ group: id, preview: id === 'text' ? v.value : preview }} />}
            label={`--${v.name}`}
            meta={id === 'shadow' ? (changed ? 'custom' : '') : v.value}
            selected={sel === `var:${v.name}`}
            onSelect={() => pick(`var:${v.name}`)}
          >
            <ValueInput value={changed ? v.value : ''} placeholder={v.default} disabled={!config.canManage} onChange={(value) => save(v.step, value)} />
            {id === 'text' && v.css !== v.value && <p className="break-all font-mono text-[10px] text-muted-foreground">{v.css}</p>}
            {id === 'space' && <div className="h-2 rounded-sm bg-brand/60" style={{ width: `min(${preview}, 100%)` }} />}
            {id === 'radius' && <div className="h-12 w-full border-2 border-brand/60 bg-brand/5" style={{ borderRadius: preview }} />}
            {id === 'shadow' && <div className="h-12 w-full rounded-md bg-background" style={{ boxShadow: preview }} />}
            {id === 'text' && (
              <p className="truncate font-semibold leading-tight" style={{ fontSize: v.value }}>
                The quick brown fox
              </p>
            )}
            <div className="flex items-center justify-between">
              <code className="text-[10.5px] text-muted-foreground">var(--{v.name})</code>
              {changed && config.canManage && (
                <button type="button" className="cursor-pointer text-[10.5px] text-muted-foreground hover:text-foreground" onClick={() => save(v.step, '')}>
                  Reset to {v.default.length > 14 ? 'default' : v.default}
                </button>
              )}
            </div>
          </Leaf>
        );
      })}
    </Branch>
  );
}

function RadiusBase({ settings, sel, pick }) {
  const save = useDebounced(async (radius) => {
    await saveSettings({ radius });
    await refreshCss();
  }, 400);
  return (
    <Leaf depth={1} lead={<span className="size-4 rounded-[5px] border-2 border-foreground/50" />} label="--radius" meta={settings.radius} selected={sel === 'radius-base'} onSelect={() => pick('radius-base')}>
      <ValueInput value={settings.radius} disabled={!config.canManage} onChange={(v) => v && save(v)} />
      <p className="text-[10.5px] leading-snug text-muted-foreground">Base corner radius. The radius scale and every shadcn component follow it.</p>
    </Leaf>
  );
}

/* ------------------------------------------------------------------------
 * Custom variables.
 * ---------------------------------------------------------------------- */

function CustomBranch({ open, onToggle, sel, pick, term, design }) {
  const saved = design.saved || {};
  const custom = saved.custom || [];
  const groups = design.groups || {};
  const list = custom.filter((v) => !term || v.name.includes(term));
  const commitList = useDebounced((next) => saveVariables({ ...saved, custom: next }), 500);
  const [draft, setDraft] = useState(null);
  const update = (name, patch) => {
    const next = custom.map((v) => (v.name === name ? { ...v, ...patch } : v));
    setState((s) => ({ design: { ...s.design, saved: { ...saved, custom: next } } }));
    commitList(next);
  };

  return (
    <Branch
      id="custom"
      icon="variable"
      label="Custom variables"
      count={custom.length}
      open={open}
      onToggle={onToggle}
      actions={
        config.canManage && (
          <IconButton
            icon="plus"
            size="icon-sm"
            label="Add variable"
            onClick={(e) => {
              e.stopPropagation();
              onToggle('custom', true);
              setDraft({ name: '', value: '', group: 'space' });
            }}
          />
        )
      }
    >
      {draft && (
        <div className="space-y-2 border-y border-border/60 bg-muted/30 py-3 pl-9 pr-3">
          <Row label="Name">
            <Input autoFocus value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} placeholder="section-gap" className="h-7 font-mono text-xs" />
          </Row>
          <Row label="Value">
            <Input value={draft.value} onChange={(e) => setDraft({ ...draft, value: e.target.value })} placeholder="clamp(2rem, 5vw, 6rem)" className="h-7 font-mono text-xs" />
          </Row>
          <Row label="Group">
            <Select value={draft.group} onChange={(group) => setDraft({ ...draft, group })} options={Object.entries(groups).map(([value, label]) => ({ value, label }))} className="h-7 text-xs" />
          </Row>
          <div className="flex justify-end gap-1.5">
            <Button size="xs" variant="ghost" onClick={() => setDraft(null)}>
              Cancel
            </Button>
            <Button
              size="xs"
              disabled={!draft.name.trim() || !draft.value.trim()}
              onClick={async () => {
                await saveVariables({ ...saved, custom: [...custom, draft] });
                setDraft(null);
              }}
            >
              Add variable
            </Button>
          </div>
        </div>
      )}
      {!custom.length && !draft && <p className="py-1.5 pl-9 text-[11px] text-muted-foreground">Name any value once and reuse it everywhere.</p>}
      {list.map((v) => (
        <Leaf key={v.name} lead={<VarPreview v={{ group: v.group, preview: v.value }} />} label={`--${v.name}`} meta={v.value} selected={sel === `custom:${v.name}`} onSelect={() => pick(`custom:${v.name}`)}>
          <Row label="Value">
            <ValueInput value={v.value} disabled={!config.canManage} onChange={(value) => update(v.name, { value })} />
          </Row>
          <Row label="Group">
            <Select value={v.group} onChange={(group) => update(v.name, { group })} options={Object.entries(groups).map(([value, label]) => ({ value, label }))} className="h-7 text-xs" />
          </Row>
          <div className="flex items-center justify-between">
            <code className="text-[10.5px] text-muted-foreground">var(--{v.name})</code>
            {config.canManage && (
              <Button size="xs" variant="ghost" icon="trash-2" onClick={() => saveVariables({ ...saved, custom: custom.filter((x) => x.name !== v.name) })}>
                Delete
              </Button>
            )}
          </div>
        </Leaf>
      ))}
    </Branch>
  );
}

/* ------------------------------------------------------------------------
 * Components.
 * ---------------------------------------------------------------------- */

const previews = {};

function ComponentPreview({ id }) {
  const [html, setHtml] = useState(previews[id] || null);
  useEffect(() => {
    if (previews[id]) return;
    api({ path: `/brik/v1/design/components/${id}/preview` }).then((r) => {
      previews[id] = r.html;
      setHtml(r.html);
    });
  }, [id]);
  // Rendered at a 760px virtual width and scaled to the panel.
  const scale = 0.42;
  return (
    <div className="relative h-40 overflow-hidden rounded-md border border-border bg-background">
      {html ? (
        <iframe title="Component preview" srcDoc={html} className="pointer-events-none absolute left-0 top-0 origin-top-left border-0" style={{ width: 760, height: `${100 / scale}%`, transform: `scale(${scale})` }} />
      ) : (
        <div className="flex h-full items-center justify-center">
          <span className="bk-spinner" />
        </div>
      )}
    </div>
  );
}

function ComponentsBranch({ open, onToggle, sel, pick, term }) {
  const items = useComponents();
  const list = (items || []).filter((c) => !term || c.title.toLowerCase().includes(term));
  if (term && !list.length) return null;
  return (
    <Branch
      id="components"
      icon="component"
      label="Components"
      count={items ? items.length : undefined}
      open={open}
      onToggle={onToggle}
      actions={<IconButton icon="refresh-cw" size="icon-sm" label="Refresh" onClick={(e) => (e.stopPropagation(), loadComponents(true))} />}
    >
      {items && !items.length && <p className="py-1.5 pl-9 pr-3 text-[11px] leading-snug text-muted-foreground">Right-click an element and choose “Save as component” to reuse it with per-instance content.</p>}
      {list.map((c) => (
        <Leaf
          key={c.id}
          mono={false}
          lead={<Icon name="component" size={14} className="shrink-0 text-violet-500" />}
          label={c.title}
          meta={`${c.instances}×`}
          selected={sel === `components:${c.id}`}
          onSelect={() => pick(`components:${c.id}`)}
        >
          <ComponentPreview id={c.id} />
          <div className="flex flex-wrap gap-1.5">
            <Button size="xs" variant="outline" icon="plus" onClick={() => insertLibraryItem({ ...c, global: true })}>
              Insert
            </Button>
            <Button size="xs" variant="outline" icon="pencil-ruler" onClick={() => window.open(builderUrl(c.id), '_blank')}>
              Edit master
            </Button>
          </div>
          {c.used_on && c.used_on.length > 0 && (
            <div className="space-y-0.5">
              <p className="text-[10.5px] font-medium text-muted-foreground">Used on</p>
              {c.used_on.slice(0, 6).map((p) => (
                <a key={p.id} href={builderUrl(p.id)} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 truncate text-[11px] text-foreground/80 hover:text-foreground">
                  <Icon name="file" size={11} className="text-muted-foreground" />
                  {p.title || `#${p.id}`}
                </a>
              ))}
            </div>
          )}
        </Leaf>
      ))}
    </Branch>
  );
}

/* ------------------------------------------------------------------------
 * Classes.
 * ---------------------------------------------------------------------- */

function ClassesBranch({ open, onToggle, sel, pick, term }) {
  const classes = useClasses();
  const usage = useUsage();
  const schema = useStore((s) => s.schema);
  const [renaming, setRenaming] = useState(null);
  const entries = Object.entries(classes).filter(([, c]) => !term || `${c.name} ${c.label}`.toLowerCase().includes(term));
  if (term && !entries.length) return null;
  const tree = useStore((s) => s.tree);
  const count = (id) => classCount(usage, id, tree);

  const remove = async (id, c) => {
    const n = count(id);
    if (!window.confirm(n ? `Delete .${c.name}? It is used by ${n} element${n === 1 ? '' : 's'}; they lose its styles.` : `Delete .${c.name}?`)) return;
    await deleteClass(id);
    rerender();
    loadUsage();
    toast(`Deleted .${c.name}`);
  };

  const rename = async (id, c, name) => {
    setRenaming(null);
    const slug = slugify(name);
    if (!slug || slug === c.name) return;
    await saveClass(id, { name: slug });
    rerender();
    toast(`Renamed to .${slug}`, 'success');
  };

  return (
    <Branch id="classes" icon="hash" label="Classes" count={Object.keys(classes).length} open={open} onToggle={onToggle} actions={<IconButton icon="refresh-cw" size="icon-sm" label="Recount usages" onClick={(e) => (e.stopPropagation(), loadUsage())} />}>
      {!entries.length && <p className="py-1.5 pl-9 pr-3 text-[11px] leading-snug text-muted-foreground">Select an element and use “+ Class” under its name to create one, or turn its styles into a class.</p>}
      {entries.map(([id, c]) => (
        <Leaf key={id} lead={<Icon name="hash" size={13} className="shrink-0 text-brand" />} label={`.${c.name}`} meta={usage ? `${count(id)} use${count(id) === 1 ? '' : 's'}` : ''} selected={sel === `classes:${id}`} onSelect={() => pick(`classes:${id}`)}>
          {renaming === id ? (
            <Input autoFocus defaultValue={c.name} className="h-7 font-mono text-xs" onBlur={(e) => rename(id, c, e.target.value)} onKeyDown={(e) => e.key === 'Enter' && e.target.blur()} />
          ) : (
            <p className="text-[11px] text-muted-foreground">
              {Object.keys(c.attrs || {}).length} style{Object.keys(c.attrs || {}).length === 1 ? '' : 's'}
              {c.type && schema.byType[c.type] ? ` · made for ${schema.byType[c.type].title}` : ''}
            </p>
          )}
          <div className="flex flex-wrap gap-1.5">
            <Button size="xs" variant="outline" icon="paintbrush" onClick={() => editClass(id)}>
              Edit styles
            </Button>
            {config.canManage && (
              <>
                <IconButton icon="pencil" size="icon-sm" variant="outline" label="Rename" onClick={() => setRenaming(id)} />
                <IconButton icon="copy" size="icon-sm" variant="outline" label="Duplicate" onClick={async () => (await duplicateClass(id), toast('Class duplicated'))} />
                <IconButton icon="trash-2" size="icon-sm" variant="outline" label="Delete" onClick={() => remove(id, c)} />
              </>
            )}
          </div>
        </Leaf>
      ))}
    </Branch>
  );
}

/* ------------------------------------------------------------------------
 * Breakpoints (read only).
 * ---------------------------------------------------------------------- */

function BreakpointsBranch({ open, onToggle, design, term }) {
  if (term) return null;
  const bp = design.breakpoints || { desktop: 981, tablet: 980, mobile: 767 };
  const rows = [
    ['monitor', 'Desktop', `≥ ${bp.desktop}px`],
    ['tablet', 'Tablet', `≤ ${bp.tablet}px`],
    ['smartphone', 'Mobile', `≤ ${bp.mobile}px`],
  ];
  return (
    <Branch id="breakpoints" icon="monitor-smartphone" label="Breakpoints" open={open} onToggle={onToggle}>
      {rows.map(([icon, label, value]) => (
        <Leaf key={label} mono={false} lead={<Icon name={icon} size={13} className="shrink-0 text-muted-foreground" />} label={label} meta={value} />
      ))}
      <p className="py-1.5 pl-9 pr-3 text-[10.5px] leading-snug text-muted-foreground">Tablet values fall back to desktop, mobile to tablet. Hover styles apply on devices that can hover.</p>
    </Branch>
  );
}
