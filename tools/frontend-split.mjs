// Per-module front-end scripts.
//
// frontend.js stays the complete bundle (the builder canvas needs every behaviour, and it is the
// fallback when optimized assets are switched off). Alongside it we emit build/frontend/core.js
// plus one file per frontend/modules/*.js, and a manifest the plugin reads to decide which
// scripts a page needs and which classes those scripts may add at runtime (so the per-page CSS
// keeps the rules for them).
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';

// Module scripts talk to the core bundle through window.brik instead of bundling their own copy.
const coreShim = {
  name: 'brik-core-shim',
  setup(build) {
    build.onResolve({ filter: /(^|\/)core\.js$/ }, (args) => (args.importer.includes('/frontend/') ? { path: 'brik-core', namespace: 'brik-core' } : undefined));
    build.onLoad({ filter: /.*/, namespace: 'brik-core' }, () => ({
      contents: 'export const on=(s,f)=>window.brik.on(s,f);export const mount=(r)=>window.brik.mount(r);export const ready=()=>{};',
      loader: 'js',
    }));
  },
};

export function splitBuilds(root, modules, common) {
  return [
    {
      ...common,
      entryPoints: undefined,
      outdir: undefined,
      outfile: `${root}build/frontend/core.js`,
      stdin: { contents: "import { ready } from './core.js';\nready();\n", resolveDir: `${root}src/frontend`, sourcefile: 'core-entry.js' },
    },
    {
      ...common,
      outdir: `${root}build/frontend`,
      entryPoints: Object.fromEntries(modules.map((f) => [f.replace(/\.js$/, ''), `${root}src/frontend/modules/${f}`])),
      plugins: [coreShim],
    },
  ];
}

/** Source of a file and the relative modules it imports (core excluded). */
async function sources(file, seen = new Set()) {
  if (seen.has(file)) return '';
  seen.add(file);
  let src = await readFile(file, 'utf8');
  let out = src;
  for (const m of src.matchAll(/import\s+[^'"]*['"](\.{1,2}\/[^'"]+)['"]/g)) {
    if (/core\.js$/.test(m[1]) || /_api\.js$/.test(m[1])) continue;
    out += '\n' + (await sources(resolve(dirname(file), m[1]), seen));
  }
  return out;
}

/** Class names, attribute names and class prefixes a script may put into the DOM. */
function tokens(src) {
  const found = new Set();
  const prefixes = new Set();
  const literals = [];
  // Strip comments first so prose doesn't end up in the safelist.
  const code = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:\\'"`])\/\/.*$/gm, '$1');
  for (const m of code.matchAll(/'((?:\\.|[^'\\\n])*)'|"((?:\\.|[^"\\\n])*)"|`((?:\\.|[^`\\])*)`/g)) {
    literals.push(m[1] ?? m[2] ?? m[3] ?? '');
  }
  for (let lit of literals) {
    lit = lit.replace(/\$\{[^}]*\}/g, (s) => '\u0000');
    for (const raw of lit.split(/[\s\u0000]+/)) {
      if (!raw) continue;
      found.add(raw);
      for (const part of raw.split(/[.#\[\]=,>()"'~+*^$|]+/)) {
        if (part && /^[a-zA-Z_-][\w:/@%!&-]*$/.test(part)) found.add(part);
      }
    }
    // Pieces glued to an interpolation (`brik-anim-${name}`) or a concatenation ('is-' + x).
    for (const m of lit.matchAll(/([a-zA-Z][\w-]*-)(?=\u0000|$)/g)) prefixes.add(m[1]);
  }
  for (const m of code.matchAll(/\.dataset\.([a-zA-Z]+)/g)) found.add('data-' + m[1].replace(/[A-Z]/g, (c) => '-' + c.toLowerCase()));
  for (const m of code.matchAll(/dataset\[['"]([a-zA-Z]+)['"]\]/g)) found.add('data-' + m[1].replace(/[A-Z]/g, (c) => '-' + c.toLowerCase()));
  const clean = [...found].filter((t) => t.length > 1 && t.length < 80 && /^[a-zA-Z_@!-]/.test(t) && !/\s/.test(t));
  return { tokens: clean.sort(), prefixes: [...prefixes].filter((p) => p.length > 2).sort() };
}

/** Selectors a module script mounts on (the first argument of on()). */
function selectors(src) {
  const out = new Set();
  for (const m of src.matchAll(/\bon\(\s*(['"`])((?:\\.|(?!\1).)*)\1/g)) {
    for (const s of m[2].split(',')) {
      const sel = s.trim();
      // jQuery-style event names ('found_variation') are not selectors.
      if (/^[a-z]*[.\[]/i.test(sel)) out.add(sel);
    }
  }
  return [...out];
}

export async function writeManifest(root, modules, fx) {
  const manifest = { core: tokens(await sources(`${root}src/frontend/core.js`)), modules: {}, fx: {} };
  for (const f of modules) {
    const src = await sources(`${root}src/frontend/modules/${f}`);
    manifest.modules[f.replace(/\.js$/, '')] = { selectors: selectors(src), ...tokens(src) };
  }
  const api = await readFile(`${root}src/fx/_api.js`, 'utf8').catch(() => '');
  for (const f of fx) {
    manifest.fx[f.replace(/\.js$/, '')] = tokens((await sources(`${root}src/fx/${f}`)) + '\n' + api);
  }
  await mkdir(`${root}build/frontend`, { recursive: true });
  await writeFile(`${root}build/frontend/manifest.json`, JSON.stringify(manifest));
}
