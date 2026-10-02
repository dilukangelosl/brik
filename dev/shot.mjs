// Screenshot a URL: node dev/shot.mjs <url> <out.png> [width]
import { chromium } from 'playwright';

const [url, out, width = '1280'] = process.argv.slice(2);
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 } });
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
await page.goto(url, { waitUntil: 'networkidle' });
await page.screenshot({ path: out, fullPage: true });
if (errors.length) console.log('Errors:\n' + errors.join('\n'));
await browser.close();
