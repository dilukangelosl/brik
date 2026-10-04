// Rule catalogue and scoring. Weights mirror Brik\Audit\Scanner::scores().

export const RULES = {
  // Accessibility.
  'heading-multiple-h1': ['a11y', 'One H1 per page'],
  'heading-skipped': ['a11y', 'Heading levels in order'],
  'heading-empty': ['a11y', 'Headings have text'],
  'img-alt-missing': ['a11y', 'Images have alt text'],
  'img-alt-filename': ['a11y', 'Alt text is descriptive'],
  'img-alt-long': ['a11y', 'Alt text is concise'],
  contrast: ['a11y', 'Text contrast (WCAG AA)'],
  'name-missing': ['a11y', 'Links and buttons have names'],
  'link-generic': ['a11y', 'Descriptive link text'],
  'div-button': ['a11y', 'Buttons are real buttons'],
  'input-label': ['a11y', 'Form fields are labelled'],
  'focus-removed': ['a11y', 'Keyboard focus is visible'],
  'tabindex-positive': ['a11y', 'Natural tab order'],
  'lang-missing': ['a11y', 'Page language is set'],
  'autoplay-sound': ['a11y', 'No autoplaying sound'],
  'aria-hidden-focusable': ['a11y', 'Hidden content is not focusable'],
  motion: ['a11y', 'Reduced motion respected'],
  // SEO.
  'h1-count': ['seo', 'Exactly one H1'],
  'title-length': ['seo', 'Title length'],
  'meta-description': ['seo', 'Meta description'],
  'img-heavy': ['seo', 'Images under 500 KB'],
  'img-dimensions': ['seo', 'Images have width and height'],
  'link-broken': ['seo', 'No broken links'],
  'link-blank-noopener': ['seo', 'New-tab links use noopener'],
  'link-sponsored': ['seo', 'Paid links are marked'],
  'faq-schema': ['seo', 'FAQ structured data'],
  'structured-data': ['seo', 'Structured data'],
  noindex: ['seo', 'Page can be indexed'],
  canonical: ['seo', 'Canonical URL'],
  // Responsive.
  overflow: ['responsive', 'No horizontal scrolling'],
  offscreen: ['responsive', 'Content stays on screen'],
  'fixed-width': ['responsive', 'No fixed widths wider than the screen'],
  'img-wide': ['responsive', 'Images fit the screen'],
  'text-clip': ['responsive', 'Text is not clipped'],
  'heading-size': ['responsive', 'Headings fit small screens'],
  'tap-target': ['responsive', 'Tap targets are large enough'],
  overlap: ['responsive', 'Controls don’t overlap'],
};

// Rules the canvas computes from the live DOM; the server's version of these is replaced
// (its fixes are kept and attached to the matching canvas finding).
export const CANVAS_RULES = new Set([
  'heading-multiple-h1',
  'heading-skipped',
  'heading-empty',
  'h1-count',
  'img-alt-missing',
  'img-alt-filename',
  'img-alt-long',
  'img-dimensions',
  'contrast',
  'name-missing',
  'link-generic',
  'link-blank-noopener',
  'link-sponsored',
  'div-button',
  'input-label',
  'tabindex-positive',
  'aria-hidden-focusable',
]);

export const WEIGHTS = { error: 12, warning: 5, info: 0 };

export function score(findings) {
  // Each rule+severity group costs its weight once; repeats add a quarter each (up to four),
  // so one pattern repeated across a page doesn't zero the score.
  const groups = {};
  for (const f of findings) {
    const k = `${f.rule}|${f.severity}`;
    groups[k] = groups[k] || { severity: f.severity, count: 0 };
    groups[k].count++;
  }
  let penalty = 0;
  for (const g of Object.values(groups)) {
    const w = WEIGHTS[g.severity];
    penalty += w + w * 0.25 * Math.min(g.count - 1, 4);
  }
  return Math.max(0, Math.round(100 - penalty));
}

export function title(rule) {
  return (RULES[rule] && RULES[rule][1]) || rule;
}

export function category(rule) {
  return (RULES[rule] && RULES[rule][0]) || 'a11y';
}

export function tone(score) {
  if (score === null || score === undefined) return 'muted';
  return score >= 90 ? 'good' : score >= 60 ? 'ok' : 'bad';
}
