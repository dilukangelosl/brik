// Compiles the front-end, builder and canvas stylesheets with Tailwind.
import { readdir, writeFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../plugin/brik-builder/assets/', import.meta.url));
const bin = fileURLToPath(new URL('../node_modules/.bin/tailwindcss', import.meta.url));
const minify = !process.argv.includes('--dev');

// Module stylesheets are picked up automatically.
// effects*.css ships separately and only loads on pages that use an effect.
const parts = (await readdir(`${root}src/css/modules`)).filter((f) => f.endsWith('.css')).sort();
const isFx = (f) => f.startsWith('effects');
await writeFile(`${root}src/css/_modules.css`, parts.filter((f) => !isFx(f)).map((f) => `@import "./modules/${f}";`).join('\n') + '\n');
await writeFile(`${root}src/css/_effects.css`, parts.filter(isFx).map((f) => `@import "./modules/${f}";`).join('\n') + '\n');

for (const name of ['frontend', 'effects', 'builder', 'canvas', 'content']) {
  const args = ['-i', `${root}src/css/${name}.css`, '-o', `${root}build/${name}.css`];
  if (minify) args.push('--minify');
  execFileSync(bin, args, { stdio: 'inherit' });
}
