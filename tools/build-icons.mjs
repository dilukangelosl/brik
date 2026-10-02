// Bundles Lucide icons and a set of brand icons (Simple Icons) into resources/icons.json.
import { readFile, writeFile } from 'node:fs/promises';
import * as simple from 'simple-icons';

const out = new URL('../plugin/brik-builder/resources/', import.meta.url);
const nodes = JSON.parse(await readFile(new URL('../node_modules/lucide-static/icon-nodes.json', import.meta.url)));
const tags = JSON.parse(await readFile(new URL('../node_modules/lucide-static/tags.json', import.meta.url)));

const attr = (o) => Object.entries(o).map(([k, v]) => `${k}="${v}"`).join(' ');
const icons = {};
for (const [name, children] of Object.entries(nodes)) {
  icons[name] = children.map(([tag, a]) => `<${tag} ${attr(a)}/>`).join('');
}

const brands = [
  'facebook', 'x', 'instagram', 'youtube', 'tiktok', 'github', 'gitlab', 'dribbble', 'behance',
  'pinterest', 'whatsapp', 'discord', 'telegram', 'threads', 'reddit', 'twitch', 'spotify', 'medium',
  'mastodon', 'bluesky', 'snapchat', 'vimeo', 'soundcloud', 'wordpress', 'figma', 'apple',
  'googleplay', 'paypal', 'stripe', 'visa', 'mastercard', 'producthunt', 'devdotto', 'stackoverflow',
];
const brand = {};
for (const slug of brands) {
  const key = 'si' + slug.charAt(0).toUpperCase() + slug.slice(1);
  const icon = simple[key];
  if (icon) brand[slug] = { title: icon.title, hex: icon.hex, path: icon.path };
  else console.warn('missing brand', slug);
}

// Removed from Simple Icons, kept here so social links stay complete.
brand.linkedin = {
  title: 'LinkedIn',
  hex: '0A66C2',
  path: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
};

await writeFile(new URL('icons.json', out), JSON.stringify(icons));
await writeFile(new URL('icon-tags.json', out), JSON.stringify(tags));
await writeFile(new URL('brands.json', out), JSON.stringify(brand));
console.log(`${Object.keys(icons).length} icons, ${Object.keys(brand).length} brands`);
