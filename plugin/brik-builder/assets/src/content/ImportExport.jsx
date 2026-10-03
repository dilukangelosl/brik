// Export (JSON download / copy, PHP code) and import (paste or upload) dialogs.
import { useState, useEffect, useRef, __, sprintf, _n } from './wp.js';
import { Icon } from '../builder/icons.js';
import { cn, Button, Textarea } from '../builder/ui.jsx';
import { Dialog, Segment, Checkbox, Badge, Spinner } from './primitives.jsx';
import { useStore, getState, exportContent, importContent, toast, errorMessage, download, copyText } from './lib.js';

const KINDS = [
  { key: 'post_types', label: __('Post types', 'brik-builder'), icon: 'file-stack', name: (x) => x.plural || x.singular || x.key },
  { key: 'taxonomies', label: __('Taxonomies', 'brik-builder'), icon: 'tags', name: (x) => x.plural || x.singular || x.key },
  { key: 'groups', label: __('Field groups', 'brik-builder'), icon: 'text-cursor-input', name: (x) => x.title || x.key },
];

function asText(res) {
  if (typeof res === 'string') return res;
  if (res && typeof res.code === 'string') return res.code;
  if (res && typeof res.php === 'string') return res.php;
  return JSON.stringify(res, null, 2);
}

/** @param {Object} only optional { post_types: [keys], … } to export a subset */
export function ExportDialog({ onClose, only }) {
  const s = useStore();
  const [format, setFormat] = useState('json');
  const [pick, setPick] = useState(() => {
    const out = {};
    for (const k of KINDS) out[k.key] = s[k.key].map((x) => x.key).filter((key) => !only || (only[k.key] || []).includes(key));
    return out;
  });
  const [full, setFull] = useState(null);
  const [php, setPhp] = useState(null);
  const [phpFor, setPhpFor] = useState('');
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    exportContent('json')
      .then(setFull)
      .catch(() => setFull({ post_types: s.post_types, taxonomies: s.taxonomies, groups: s.groups }));
  }, []);
  const pickKey = JSON.stringify(pick);
  useEffect(() => {
    if (format !== 'php' || phpFor === pickKey) return;
    setPhp(null);
    setPhpFor(pickKey);
    exportContent('php', pick)
      .then((r) => setPhp(asText(r)))
      .catch((e) => setPhp(`// ${errorMessage(e, __('PHP export is not available.', 'brik-builder'))}`));
  }, [format, pickKey]);

  const data = full
    ? Object.fromEntries(
        Object.entries(full).map(([k, v]) => {
          if (!Array.isArray(v) || !pick[k]) return [k, v];
          return [k, v.filter((x) => pick[k].includes(x.key))];
        })
      )
    : null;
  const total = KINDS.reduce((n, k) => n + pick[k.key].length, 0);
  const json = data ? JSON.stringify(data, null, 2) : '';
  const text = format === 'json' ? json : php || '';

  const toggle = (kind, key) => setPick((p) => ({ ...p, [kind]: p[kind].includes(key) ? p[kind].filter((x) => x !== key) : [...p[kind], key] }));

  return (
    <Dialog
      size="lg"
      icon="download"
      title={__('Export content model', 'brik-builder')}
      description={__('Move post types, taxonomies and field groups to another site, or register them from your theme with PHP.', 'brik-builder')}
      onClose={onClose}
      footer={
        <>
          <Button
            variant="outline"
            size="sm"
            icon={copied ? 'check' : 'copy'}
            disabled={!text}
            onClick={async () => {
              if (await copyText(text)) {
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
                toast(__('Copied to the clipboard', 'brik-builder'), 'success');
              }
            }}
          >
            {copied ? __('Copied', 'brik-builder') : format === 'php' ? __('Copy PHP code', 'brik-builder') : __('Copy JSON', 'brik-builder')}
          </Button>
          <Button size="sm" icon="download" disabled={!text || !total} onClick={() => download(format === 'json' ? 'brik-content.json' : 'brik-content.php', text, format === 'json' ? 'application/json' : 'text/x-php')}>
            {__('Download', 'brik-builder')}
          </Button>
        </>
      }
    >
      <div className="grid gap-5 md:grid-cols-[220px_1fr]">
        <div className="space-y-4">
          <Segment
            className="w-full"
            value={format}
            onChange={setFormat}
            options={[
              { value: 'json', label: 'JSON', icon: 'braces' },
              { value: 'php', label: 'PHP', icon: 'code' },
            ]}
          />
          {KINDS.map((k) => (
            <div key={k.key}>
              <div className="mb-1.5 flex items-center justify-between">
                <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                  <Icon name={k.icon} size={13} />
                  {k.label}
                </span>
                {s[k.key].length > 1 && (
                  <button type="button" className="text-[11px] text-muted-foreground hover:text-foreground cursor-pointer" onClick={() => setPick((p) => ({ ...p, [k.key]: p[k.key].length === s[k.key].length ? [] : s[k.key].map((x) => x.key) }))}>
                    {pick[k.key].length === s[k.key].length ? __('None', 'brik-builder') : __('All', 'brik-builder')}
                  </button>
                )}
              </div>
              {s[k.key].length ? (
                <div className="space-y-0.5">
                  {s[k.key].map((x) => (
                    <label key={x.key} className="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-[13px] hover:bg-muted/60">
                      <Checkbox checked={pick[k.key].includes(x.key)} onChange={() => toggle(k.key, x.key)} />
                      <span className="truncate">{k.name(x)}</span>
                    </label>
                  ))}
                </div>
              ) : (
                <p className="px-1.5 text-xs text-muted-foreground">{__('None yet', 'brik-builder')}</p>
              )}
            </div>
          ))}
        </div>
        <div className="min-w-0">
          {format === 'php' && (
            <p className="mb-2 flex items-start gap-1.5 text-xs text-muted-foreground">
              <Icon name="info" size={13} className="mt-px shrink-0" />
              {__('Paste into your theme’s functions.php or a small plugin, then deactivate or delete the items here so they aren’t registered twice.', 'brik-builder')}
            </p>
          )}
          <div className="relative">
            {!text && (
              <div className="absolute inset-0 flex items-center justify-center">
                <Spinner />
              </div>
            )}
            <pre className="h-[420px] overflow-auto rounded-lg border border-border bg-zinc-950 p-3 font-mono text-[12px] leading-relaxed text-zinc-100" data-export-format={format}>
              {text}
            </pre>
          </div>
        </div>
      </div>
    </Dialog>
  );
}

function parseImport(text) {
  const data = JSON.parse(text);
  const out = { post_types: [], taxonomies: [], groups: [] };
  // Accept a full export, a single definition, or a list of definitions.
  const add = (item) => {
    if (!item || typeof item !== 'object') return;
    if (Array.isArray(item.fields) || Array.isArray(item.location)) out.groups.push(item);
    else if (Array.isArray(item.post_types) && !item.supports) out.taxonomies.push(item);
    else if (item.key) out.post_types.push(item);
  };
  if (Array.isArray(data)) data.forEach(add);
  else if (data.post_types || data.taxonomies || data.groups || data.field_groups) {
    for (const [k, src] of [['post_types', data.post_types], ['taxonomies', data.taxonomies], ['groups', data.groups || data.field_groups]]) {
      if (Array.isArray(src)) out[k] = src;
      else if (src && typeof src === 'object') out[k] = Object.values(src);
    }
  } else add(data);
  if (!out.post_types.length && !out.taxonomies.length && !out.groups.length) throw new Error(__('No post types, taxonomies or field groups found in this JSON.', 'brik-builder'));
  return out;
}

export function ImportDialog({ onClose }) {
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [drag, setDrag] = useState(false);
  const [errors, setErrors] = useState([]);
  const file = useRef(null);
  let parsed = null;
  let error = '';
  if (text.trim()) {
    try {
      parsed = parseImport(text);
    } catch (e) {
      error = e instanceof SyntaxError ? __('This isn’t valid JSON.', 'brik-builder') : e.message;
    }
  }
  const s = getState();

  const read = (f) => {
    if (!f) return;
    const r = new FileReader();
    r.onload = () => setText(String(r.result || ''));
    r.readAsText(f);
  };

  const run = async () => {
    setBusy(true);
    try {
      const res = await importContent(parsed);
      const done = res && res.imported ? Object.values(res.imported).reduce((a, b) => a + b, 0) : parsed.post_types.length + parsed.taxonomies.length + parsed.groups.length;
      const errs = (res && res.errors) || [];
      if (done) toast(sprintf(_n('Imported %d item', 'Imported %d items', done, 'brik-builder'), done), 'success');
      if (errs.length) {
        setErrors(errs.map((e) => e.message || String(e)));
        setBusy(false);
        return;
      }
      onClose();
    } catch (e) {
      toast(errorMessage(e, __('Import failed.', 'brik-builder')), 'error');
      setBusy(false);
    }
  };

  return (
    <Dialog
      size="lg"
      icon="upload"
      title={__('Import content model', 'brik-builder')}
      description={__('Paste JSON exported from Brik, or drop a .json file. Items with the same key are updated.', 'brik-builder')}
      onClose={onClose}
      footer={
        <>
          <Button variant="outline" size="sm" onClick={onClose}>
            {__('Cancel', 'brik-builder')}
          </Button>
          <Button size="sm" icon="upload" disabled={!parsed || busy} onClick={run}>
            {busy ? __('Importing…', 'brik-builder') : __('Import', 'brik-builder')}
          </Button>
        </>
      }
    >
      <div
        className={cn('relative rounded-lg transition-shadow', drag && 'ring-2 ring-brand')}
        onDragOver={(e) => {
          e.preventDefault();
          setDrag(true);
        }}
        onDragLeave={() => setDrag(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDrag(false);
          read(e.dataTransfer.files[0]);
        }}
      >
        <Textarea className="h-56 font-mono text-[12px]" placeholder={'{ "post_types": [ … ], "taxonomies": [ … ], "groups": [ … ] }'} value={text} onChange={(e) => setText(e.target.value)} spellCheck={false} />
        <div className="mt-2 flex items-center justify-between gap-2">
          <span className="text-xs text-muted-foreground">{__('or', 'brik-builder')}</span>
          <input ref={file} type="file" accept=".json,application/json" className="hidden" onChange={(e) => read(e.target.files[0])} />
          <Button variant="outline" size="xs" icon="file-up" onClick={() => file.current.click()} className="mr-auto">
            {__('Choose file', 'brik-builder')}
          </Button>
        </div>
      </div>
      {error && (
        <p className="mt-3 flex items-center gap-1.5 text-xs text-destructive">
          <Icon name="circle-alert" size={13} />
          {error}
        </p>
      )}
      {errors.length > 0 && (
        <div className="mt-4 space-y-1 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-xs text-destructive" data-import-errors>
          <p className="font-medium">{__('Some items could not be imported:', 'brik-builder')}</p>
          {errors.map((m, i) => (
            <p key={i}>{m}</p>
          ))}
        </div>
      )}
      {parsed && (
        <div className="mt-4 space-y-2 rounded-lg border border-border bg-muted/30 p-3">
          <p className="text-xs font-medium text-muted-foreground">{__('Ready to import', 'brik-builder')}</p>
          {KINDS.map((k) =>
            parsed[k.key].map((x) => {
              const exists = s[k.key].some((y) => y.key === x.key);
              return (
                <div key={k.key + x.key} className="flex items-center gap-2 text-[13px]">
                  <Icon name={k.icon} size={14} className="text-muted-foreground" />
                  <span>{k.name(x)}</span>
                  <code className="font-mono text-[11px] text-muted-foreground">{x.key}</code>
                  {exists ? <Badge tone="warn">{__('Updates existing', 'brik-builder')}</Badge> : <Badge tone="success">{__('New', 'brik-builder')}</Badge>}
                </div>
              );
            })
          )}
        </div>
      )}
    </Dialog>
  );
}
