// Log in and screenshot the builder: node dev/builder-shot.mjs <post_id> <out.png> [script.js]
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';

const [id, out, script] = process.argv.slice(2);
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => m.type() === 'error' && errors.push('console: ' + m.text()));
await page.goto('http://localhost:8888/wp-login.php');
await page.fill('#user_login', 'admin');
await page.fill('#user_pass', 'admin');
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');
await page.goto(`http://localhost:8888/wp-admin/post.php?post=${id}&action=brik`);
await page.waitForTimeout(3000);
if (script) {
  const fn = new Function('page', 'frame', `return (async () => { ${readFileSync(script, 'utf8')} })()`);
  const frame = page.frameLocator('iframe');
  await fn(page, frame);
}
await page.screenshot({ path: out });
if (errors.length) console.log(errors.join('\n'));
await browser.close();
