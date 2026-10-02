// Bundles the builder app and the front-end script.
import { readdir, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = fileURLToPath(new URL('../plugin/brik-builder/assets/', import.meta.url));
const watch = process.argv.includes('--watch');

const modules = (await readdir(`${root}src/frontend/modules`)).filter((f) => f.endsWith('.js')).sort();
await writeFile(`${root}src/frontend/_modules.js`, modules.map((f) => `import './modules/${f}';`).join('\n') + '\n');

const common = {
  outdir: `${root}build`,
  bundle: true,
  minify: !watch,
  sourcemap: watch ? 'inline' : false,
  target: ['es2020'],
  logLevel: 'info',
};

const builds = [
  {
    ...common,
    entryPoints: { builder: `${root}src/builder/index.jsx` },
    jsx: 'transform',
    jsxFactory: 'h',
    jsxFragment: 'Fragment',
    inject: [`${root}src/builder/jsx.js`],
    loader: { '.js': 'jsx' },
  },
  { ...common, entryPoints: { frontend: `${root}src/frontend/index.js` } },
];

if (watch) {
  for (const options of builds) await (await esbuild.context(options)).watch();
} else {
  await Promise.all(builds.map((options) => esbuild.build(options)));
}
