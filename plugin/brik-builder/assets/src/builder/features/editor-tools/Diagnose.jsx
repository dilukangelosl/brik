// "Why is this broken?" popover in the settings panel header.
import { useState, useEffect, useMemo } from '../../wp.js';
import * as store from '../../store.js';
import { Icon } from '../../icons.js';
import { cn, Button, Popover } from '../../ui.jsx';
import { scrollTo } from '../../canvas.js';
import { diagnose } from './diagnose.js';
import { useCanvasVersion } from './util.js';

const LEVEL = {
  error: { icon: 'circle-alert', cls: 'text-destructive', ring: 'border-destructive/30 bg-destructive/[0.04]' },
  warn: { icon: 'triangle-alert', cls: 'text-amber-600', ring: 'border-amber-500/30 bg-amber-500/[0.05]' },
  info: { icon: 'info', cls: 'text-sky-600', ring: 'border-sky-500/30 bg-sky-500/[0.05]' },
};

export function DiagnoseButton({ node }) {
  const request = store.useStore((s) => s.bkDiagnose);
  const open = request === node.id;
  const setOpen = (v) => store.setState({ bkDiagnose: v ? node.id : null });
  return (
    <Popover
      align="end"
      width={360}
      open={open}
      onOpenChange={setOpen}
      trigger={
        <Button size="xs" variant="outline" icon="stethoscope" active={open} title="Why is this broken? Find what hides, clips or covers this element" data-bk-diagnose-btn>
          Diagnose
        </Button>
      }
    >
      {() => <DiagnosePanel id={node.id} />}
    </Popover>
  );
}

function DiagnosePanel({ id }) {
  const v = useCanvasVersion();
  const tree = store.useStore((s) => s.tree);
  const device = store.useStore((s) => store.effectiveDevice(s));
  const [showPassed, setShowPassed] = useState(false);
  const [first, setFirst] = useState(true);
  const result = useMemo(() => diagnose(id, { scroll: first }), [id, v, tree, device]);
  useEffect(() => setFirst(false), []);

  const order = { error: 0, warn: 1, info: 2 };
  const issues = [...result.issues].sort((a, b) => order[a.level] - order[b.level]);
  const errors = issues.filter((i) => i.level === 'error').length;

  return (
    <div className="-m-3" data-bk-diagnose>
      <div className="flex items-start gap-2.5 border-b border-border p-3">
        <span className={cn('mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full', issues.length ? (errors ? 'bg-destructive/10 text-destructive' : 'bg-amber-500/10 text-amber-600') : 'bg-emerald-500/10 text-emerald-600')}>
          <Icon name={issues.length ? 'stethoscope' : 'circle-check'} size={15} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold leading-tight">{issues.length ? `${issues.length} possible cause${issues.length > 1 ? 's' : ''} found` : 'Nothing looks broken'}</p>
          <p className="mt-0.5 text-xs text-muted-foreground">
            Checked on <span className="font-medium text-foreground">{device}</span>. {issues.length ? 'Fixes can be undone.' : 'It is displayed, sized, on screen and readable.'}
          </p>
        </div>
        <button type="button" title="Scroll to element" onClick={() => scrollTo(id)} className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground cursor-pointer">
          <Icon name="scan" size={14} />
        </button>
      </div>
      {issues.length > 0 && (
        <div className="max-h-[52vh] space-y-2 overflow-y-auto p-3">
          {issues.map((issue) => {
            const L = LEVEL[issue.level];
            return (
              <div key={issue.key} className={cn('rounded-lg border p-2.5', L.ring)} data-bk-issue={issue.key}>
                <div className="flex items-start gap-2">
                  <Icon name={L.icon} size={14} className={cn('mt-0.5', L.cls)} />
                  <div className="min-w-0 flex-1">
                    <p className="text-[13px] font-medium leading-snug">{issue.title}</p>
                    <p className="mt-0.5 text-xs leading-relaxed text-muted-foreground">{issue.detail}</p>
                    {issue.fix && (
                      <Button
                        size="xs"
                        variant="outline"
                        icon="wand-sparkles"
                        className="mt-2 h-6 bg-background"
                        data-bk-fix={issue.key}
                        onClick={() => {
                          issue.fix.run();
                          store.toast('Fixed — press ⌘Z to undo', 'success');
                        }}
                      >
                        {issue.fix.label}
                      </Button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
      {result.passed.length > 0 && (
        <div className="border-t border-border px-3 py-2">
          <button type="button" onClick={() => setShowPassed(!showPassed)} className="flex w-full items-center justify-between text-xs text-muted-foreground hover:text-foreground cursor-pointer">
            <span className="flex items-center gap-1.5">
              <Icon name="circle-check" size={12} className="text-emerald-600" />
              {result.passed.length} check{result.passed.length > 1 ? 's' : ''} passed
            </span>
            <Icon name="chevron-down" size={12} className={cn('transition-transform', showPassed && 'rotate-180')} />
          </button>
          {showPassed && (
            <ul className="mt-1.5 space-y-1">
              {result.passed.map((p) => (
                <li key={p} className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Icon name="check" size={11} className="text-emerald-600" />
                  {p}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}

/** Context menu entry. */
export const diagnoseMenuItem = {
  icon: 'stethoscope',
  label: 'Why is this broken?',
  action: (node) => {
    store.select(node.id);
    // Wait for the settings panel to mount the button that hosts the popover.
    setTimeout(() => store.setState({ bkDiagnose: node.id }), 30);
  },
};
