import { h } from './jsx.js';

let lucide = {};
let brands = {};
let aliases = {};

export function setIcons(icons, brandIcons, aliasMap) {
  lucide = icons || {};
  brands = brandIcons || {};
  aliases = aliasMap || {};
}

export function iconNames() {
  return Object.keys(lucide);
}

export function brandNames() {
  return Object.keys(brands);
}

export function iconSvg(name, size = 16) {
  if (!name) return '';
  if (!lucide[name] && aliases[name]) name = aliases[name];
  if (name.startsWith('brand:')) {
    const b = brands[name.slice(6)];
    return b ? `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}" fill="currentColor" aria-hidden="true"><path d="${b.path}"/></svg>` : '';
  }
  const body = lucide[name];
  if (!body) return '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
}

export function Icon({ name, size = 16, className = '' }) {
  return h('span', {
    className: `bk-icon inline-flex shrink-0 ${className}`,
    dangerouslySetInnerHTML: { __html: iconSvg(name, size) },
  });
}
