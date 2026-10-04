// Read-only code views with light syntax colouring, and a small HTML pretty-printer.
import { cn } from '../../ui.jsx';
import { escapeHtml } from './util.js';

const C = {
  sel: 'text-fuchsia-700 dark:text-fuchsia-400',
  at: 'text-violet-700 dark:text-violet-400',
  prop: 'text-sky-700 dark:text-sky-400',
  val: 'text-amber-800 dark:text-amber-300',
  tag: 'text-fuchsia-700 dark:text-fuchsia-400',
  attr: 'text-sky-700 dark:text-sky-400',
  str: 'text-emerald-700 dark:text-emerald-400',
  num: 'text-amber-700 dark:text-amber-300',
  key: 'text-sky-700 dark:text-sky-400',
  punc: 'text-muted-foreground',
};

const span = (cls, text) => `<span class="${cls}">${text}</span>`;

export function highlightCss(code) {
  return code
    .split('\n')
    .map((line) => {
      const e = escapeHtml(line);
      const decl = line.match(/^(\s+)([\w-]+)(\s*:\s*)(.*?)(;?)$/);
      if (decl && !line.trim().endsWith('{')) {
        return `${decl[1]}${span(C.prop, escapeHtml(decl[2]))}${span(C.punc, escapeHtml(decl[3]))}${span(C.val, escapeHtml(decl[4]))}${span(C.punc, decl[5])}`;
      }
      if (line.trim().startsWith('@')) return span(C.at, e);
      if (line.trim().endsWith('{') || line.trim().endsWith(',')) return span(C.sel, e);
      return span(C.punc, e);
    })
    .join('\n');
}

export function highlightHtml(code) {
  return escapeHtml(code).replace(/&lt;(\/?)([\w-]+)([^]*?)(\/?)&gt;/g, (m, slash, name, attrs, self) => {
    const a = attrs.replace(/([\w:@.-]+)(=)(&quot;[^]*?&quot;)/g, (x, n, eq, v) => `${span(C.attr, n)}${span(C.punc, eq)}${span(C.str, v)}`);
    return `${span(C.punc, `&lt;${slash}`)}${span(C.tag, name)}${a}${span(C.punc, `${self}&gt;`)}`;
  });
}

export function highlightJson(code) {
  return escapeHtml(code).replace(/(&quot;(?:\\.|[^&]|&(?!quot;))*?&quot;)(\s*:)?|\b(true|false|null)\b|(-?\b\d+(?:\.\d+)?\b)/g, (m, str, colon, kw, num) => {
    if (str) return colon ? `${span(C.key, str)}${span(C.punc, colon)}` : span(C.str, str);
    if (kw) return span(C.num, kw);
    if (num) return span(C.num, num);
    return m;
  });
}

const VOID = new Set(['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse', 'use', 'stop']);
const INLINE = new Set(['a', 'span', 'strong', 'em', 'b', 'i', 'code', 'small', 'mark', 'sub', 'sup', 'u', 's', 'br']);

/** Indent markup one tag per line; inline formatting stays on the line. */
export function prettyHtml(html, max = 80000) {
  const src = html.length > max ? `${html.slice(0, max)}<!-- … truncated -->` : html;
  const tokens = src.split(/(<[^>]+>)/).filter((t) => t.trim() !== '');
  let depth = 0;
  const lines = [];
  let buffer = '';
  const flush = () => {
    if (buffer.trim()) lines.push('  '.repeat(depth) + buffer.trim());
    buffer = '';
  };
  for (const t of tokens) {
    const m = t.match(/^<\s*(\/?)\s*([\w-]+)/);
    const name = m ? m[2].toLowerCase() : null;
    if (!m || INLINE.has(name) || t.startsWith('<!')) {
      buffer += t.startsWith('<') ? t : t.replace(/\s+/g, ' ');
      continue;
    }
    flush();
    if (m[1]) {
      depth = Math.max(0, depth - 1);
      lines.push('  '.repeat(depth) + t);
    } else {
      lines.push('  '.repeat(depth) + t);
      if (!VOID.has(name) && !t.endsWith('/>')) depth++;
    }
  }
  flush();
  return lines.join('\n');
}

function Block({ html, className, ...props }) {
  return <pre className={cn('max-h-[60vh] overflow-auto rounded-md border border-border bg-muted/40 p-2.5 font-mono text-[11px] leading-[1.55] whitespace-pre', className)} dangerouslySetInnerHTML={{ __html: html }} {...props} />;
}

export function CssCode({ code, className, ...props }) {
  return <Block html={highlightCss(code)} className={className} {...props} />;
}

export function HtmlCode({ code, className, ...props }) {
  return <Block html={highlightHtml(code)} className={className} {...props} />;
}

export function JsonCode({ code, className, ...props }) {
  return <Block html={highlightJson(code)} className={className} {...props} />;
}
