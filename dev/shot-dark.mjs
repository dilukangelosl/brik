// Screenshot a URL with the system in dark mode: node dev/shot-dark.mjs <url> <out.png> [width]
import { chromium } from 'playwright';
const [url, out, width = '1280'] = process.argv.slice(2);
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: Number(width), height: 900 }, colorScheme: 'dark' });
await page.goto(url, { waitUntil: 'networkidle' });
const height = await page.evaluate(() => document.body.scrollHeight);
for (let y = 0; y < height; y += 300) {
  await page.mouse.wheel(0, 300);
  await page.waitForTimeout(80);
}
await page.waitForTimeout(1500);
await page.evaluate(() => window.scrollTo(0, 0));
await page.screenshot({ path: out, fullPage: true });
await browser.close();
