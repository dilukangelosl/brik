// Snapshot the shadcn/ui registry (new-york-v4) into resources/shadcn.
// Usage: node tools/fetch-shadcn.mjs
import { mkdir, writeFile } from 'node:fs/promises';

const base = 'https://ui.shadcn.com/r';
const out = new URL('../plugin/brik-builder/resources/shadcn/', import.meta.url);

const index = await (await fetch(`${base}/index.json`)).json();
const items = index.filter((i) => i.type === 'registry:ui');
await mkdir(out, { recursive: true });

for (const item of items) {
  const res = await fetch(`${base}/styles/new-york-v4/${item.name}.json`);
  if (!res.ok) {
    console.warn(`skip ${item.name} (${res.status})`);
    continue;
  }
  const data = await res.json();
  const source = data.files.map((f) => f.content).join('\n');
  await writeFile(new URL(`${item.name}.tsx`, out), source);
}

await writeFile(new URL('index.json', out), JSON.stringify(items.map((i) => i.name), null, 2));
console.log(`fetched ${items.length} components`);
