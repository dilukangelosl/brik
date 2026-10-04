// Colour helpers for the contrast check. Any CSS colour (oklch, color-mix results, lab…) is
// converted to sRGB by letting a 1×1 canvas paint it, so we don't need a colour-space library.

const cache = new Map();
let ctx = null;

/** [r, g, b, a] in 0-255 / 0-1, or null when the string isn't a colour. */
export function toRgba(value) {
  if (!value) return null;
  const key = String(value).trim();
  if (cache.has(key)) return cache.get(key);
  let out = null;
  const m = key.match(/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/);
  if (m) {
    const a = m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : parseFloat(m[4]);
    out = [+m[1], +m[2], +m[3], a];
  } else if (key === 'transparent') {
    out = [0, 0, 0, 0];
  } else if (window.CSS && CSS.supports('color', key)) {
    if (!ctx) {
      const c = document.createElement('canvas');
      c.width = c.height = 1;
      ctx = c.getContext('2d', { willReadFrequently: true });
    }
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = '#000';
    ctx.fillStyle = key;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    out = [d[0], d[1], d[2], d[3] / 255];
  }
  cache.set(key, out);
  return out;
}

/** Colours mentioned in a background-image value (gradient stops). */
export function gradientColors(value) {
  const found = String(value).match(/(?:rgba?|hsla?|oklch|oklab|lab|lch|color|hwb)\([^()]*\)|#[0-9a-f]{3,8}\b/gi) || [];
  return found.map(toRgba).filter(Boolean);
}

/** Composite a translucent colour over an opaque one. */
export function over(top, bottom) {
  const a = top[3] ?? 1;
  return [top[0] * a + bottom[0] * (1 - a), top[1] * a + bottom[1] * (1 - a), top[2] * a + bottom[2] * (1 - a), 1];
}

function channel(v) {
  v /= 255;
  return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
}

export function luminance(c) {
  return 0.2126 * channel(c[0]) + 0.7152 * channel(c[1]) + 0.0722 * channel(c[2]);
}

/** WCAG contrast ratio of a (possibly translucent) foreground on an opaque background. */
export function ratio(fg, bg) {
  const f = over(fg, bg);
  const l1 = luminance(f);
  const l2 = luminance(bg);
  return Math.round(((Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)) * 100) / 100;
}

export function hex(c) {
  return '#' + c.slice(0, 3).map((v) => Math.round(v).toString(16).padStart(2, '0')).join('');
}

function splitTop(str) {
  const out = [];
  let depth = 0;
  let cur = '';
  for (const ch of str) {
    if (ch === '(') depth++;
    if (ch === ')') depth--;
    if (ch === ',' && depth === 0) {
      out.push(cur.trim());
      cur = '';
    } else cur += ch;
  }
  if (cur.trim()) out.push(cur.trim());
  return out;
}

const SIDES = { top: 0, right: 90, bottom: 180, left: 270 };

/**
 * Colour of the top background layer at a point, when it is a linear gradient. Gradients are
 * sampled where the text actually sits instead of assuming the worst stop. Returns null for
 * other kinds of layers.
 */
export function linearGradientAt(value, rect, x, y) {
  const m = String(value).match(/^(?:repeating-)?linear-gradient\((.*)\)/);
  if (!m) return null;
  // Only the first (top) layer: cut at the matching parenthesis.
  let depth = 1;
  let end = 0;
  const body = m[1];
  for (let i = 0; i < body.length; i++) {
    if (body[i] === '(') depth++;
    if (body[i] === ')' && --depth === 0) {
      end = i;
      break;
    }
  }
  const args = splitTop(end ? body.slice(0, end) : body);
  let angle = 180;
  if (/^-?[\d.]+(deg|turn|rad|grad)$/.test(args[0])) {
    const v = parseFloat(args[0]);
    angle = args[0].endsWith('turn') ? v * 360 : args[0].endsWith('rad') ? (v * 180) / Math.PI : args[0].endsWith('grad') ? v * 0.9 : v;
    args.shift();
  } else if (/^to\s/.test(args[0])) {
    const parts = args.shift().slice(3).trim().split(/\s+/);
    if (parts.length === 1) angle = SIDES[parts[0]];
    else {
      // Corner: the angle that points at that corner of this box.
      const vx = parts.includes('right') ? rect.width : -rect.width;
      const vy = parts.includes('bottom') ? rect.height : -rect.height;
      angle = (Math.atan2(vx, -vy) * 180) / Math.PI;
    }
  }
  const stops = [];
  for (const a of args) {
    const cm = a.match(/^((?:rgba?|hsla?|oklch|oklab|lab|lch|color|hwb)\([^()]*\)|#[0-9a-f]{3,8}|[a-z]+)\s*(.*)$/i);
    if (!cm) continue;
    const c = toRgba(cm[1]);
    if (!c) continue;
    const pos = cm[2] ? cm[2].trim().split(/\s+/)[0] : '';
    stops.push({ c, p: pos.endsWith('%') ? parseFloat(pos) / 100 : null });
  }
  if (stops.length < 2) return stops[0] ? stops[0].c : null;
  if (stops[0].p === null) stops[0].p = 0;
  if (stops[stops.length - 1].p === null) stops[stops.length - 1].p = 1;
  for (let i = 1; i < stops.length - 1; i++) {
    if (stops[i].p !== null) continue;
    let j = i;
    while (stops[j].p === null) j++;
    const a = stops[i - 1].p;
    const step = (stops[j].p - a) / (j - i + 1);
    for (let k = i; k < j; k++) stops[k].p = a + step * (k - i + 1);
  }
  const rad = (angle * Math.PI) / 180;
  const dx = Math.sin(rad);
  const dy = -Math.cos(rad);
  const len = Math.abs(rect.width * dx) + Math.abs(rect.height * dy) || 1;
  const t = Math.min(1, Math.max(0, ((x - (rect.left + rect.width / 2)) * dx + (y - (rect.top + rect.height / 2)) * dy) / len + 0.5));
  if (t <= stops[0].p) return stops[0].c;
  for (let i = 1; i < stops.length; i++) {
    if (t <= stops[i].p) {
      const a = stops[i - 1];
      const b = stops[i];
      const f = b.p === a.p ? 1 : (t - a.p) / (b.p - a.p);
      return [0, 1, 2, 3].map((k) => a.c[k] + (b.c[k] - a.c[k]) * f);
    }
  }
  return stops[stops.length - 1].c;
}
