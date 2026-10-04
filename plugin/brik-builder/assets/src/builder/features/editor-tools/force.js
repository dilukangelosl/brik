// Forcing :hover / :focus / :active on a canvas element, like browser devtools.
//
// Generated element CSS already has `.bk-force-hover` twins of its hover rules (Style.php,
// canvas mode). For every other stylesheet (utilities, module CSS) twins are generated here
// into a <style> in the canvas, for the three states.
import { frameDoc, nodeEl, onCanvasChange } from './util.js';

export const STATES = ['hover', 'focus', 'active'];

let forced = { id: null, states: [] };
let builtFor = null;
const subs = new Set();

export function getForced() {
  return forced;
}

export function subscribeForced(fn) {
  subs.add(fn);
  return () => subs.delete(fn);
}

function pseudoRe(state) {
  return state === 'focus' ? /:focus(-visible|-within)?(?![\w-])/g : new RegExp(`:${state}(?![\\w-])`, 'g');
}

/** Copy every rule using :hover/:focus/:active with the pseudo-class swapped for a class. */
function buildTwins(doc) {
  if (builtFor === doc && doc.getElementById('bk-force-css')) return;
  builtFor = doc;
  const out = [];
  const visit = (rules, wrap) => {
    for (const rule of rules) {
      if (rule.selectorText !== undefined && rule.style) {
        const sel = rule.selectorText;
        if (!/:(hover|focus|active)/.test(sel) || sel.includes('bk-force-')) continue;
        let twin = sel;
        for (const st of STATES) twin = twin.replace(pseudoRe(st), `.bk-force-${st}`);
        if (twin !== sel) out.push(wrap(`${twin}{${rule.style.cssText}}`));
      } else if (rule.cssRules && rule.conditionText !== undefined) {
        const kind = rule.constructor.name === 'CSSSupportsRule' ? '@supports' : rule.constructor.name === 'CSSContainerRule' ? '@container' : '@media';
        // Hover-capability queries would hide twins on touch screens; the class is explicit.
        const cond = rule.conditionText;
        visit(rule.cssRules, cond.includes('hover') && kind === '@media' ? wrap : (css) => wrap(`${kind} ${cond}{${css}}`));
      } else if (rule.cssRules) {
        visit(rule.cssRules, wrap);
      }
    }
  };
  for (const sheet of doc.styleSheets) {
    // Generated element CSS already has its own twins; the frontend.css <link> shares its id.
    if (sheet.ownerNode && sheet.ownerNode.tagName === 'STYLE' && (sheet.ownerNode.id === 'brik-css' || sheet.ownerNode.id === 'bk-force-css')) continue;
    let rules;
    try {
      rules = sheet.cssRules;
    } catch (e) {
      continue; // Cross-origin stylesheet.
    }
    visit(rules, (css) => css);
  }
  let style = doc.getElementById('bk-force-css');
  if (!style) {
    style = doc.createElement('style');
    style.id = 'bk-force-css';
    doc.head.appendChild(style);
  }
  style.textContent = out.join('\n');
}

function clearClasses(doc) {
  if (!doc) return;
  for (const st of STATES) doc.querySelectorAll(`.bk-force-${st}`).forEach((n) => n.classList.remove(`bk-force-${st}`));
}

function apply() {
  const doc = frameDoc();
  if (!doc) return;
  clearClasses(doc);
  if (!forced.id || !forced.states.length) return;
  buildTwins(doc);
  const el = nodeEl(forced.id);
  if (!el) return;
  // Inner elements carry their own :hover utilities, so they get the class as well.
  const targets = [el, ...el.querySelectorAll('*')];
  for (const st of forced.states) targets.forEach((n) => n.classList.add(`bk-force-${st}`));
}

export function setForced(id, states) {
  forced = { id, states: [...states] };
  apply();
  subs.forEach((f) => f());
}

export function toggleForced(id, state) {
  const states = forced.id === id ? forced.states : [];
  setForced(id, states.includes(state) ? states.filter((s) => s !== state) : [...states, state]);
}

export function clearForced() {
  if (!forced.id) return;
  setForced(null, []);
}

// Re-rendered markup loses the classes; put them back.
onCanvasChange(() => {
  if (forced.id && forced.states.length) {
    const el = nodeEl(forced.id);
    if (el && !el.classList.contains(`bk-force-${forced.states[0]}`)) apply();
  }
});
