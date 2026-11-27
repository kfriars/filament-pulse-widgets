// PROTOTYPE, throwaway: rough README screenshots from the workbench, to judge which
// shots the README wants and whether scripting them is worth it.
// Run: composer build && php vendor/bin/testbench serve --port=8765, then node prototype-readme/screenshots.mjs
import { chromium } from 'playwright';

const base = 'http://127.0.0.1:8765';
const out = new URL('./screenshots/', import.meta.url).pathname;
const browser = await chromium.launch();

for (const scheme of ['light', 'dark']) {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: scheme });
  const page = await context.newPage();
  await page.goto(`${base}/_workbench`);
  await page.goto(`${base}/admin`);
  await page.waitForSelector('.fi-pulse-widget canvas', { timeout: 20000 });
  await page.waitForTimeout(1500);

  // 1. The hero: the dashboard as a user first sees it, Pulse widgets next to Filament's own.
  await page.screenshot({ path: `${out}dashboard-${scheme}.png` });

  // 2. The whole dashboard, every ready-made Pulse widget and the custom one.
  await page.screenshot({ path: `${out}dashboard-full-${scheme}.png`, fullPage: true });

  // 3. One Pulse widget with its period selector open.
  const usage = page.locator('.fi-pulse-widget').filter({ hasText: 'Application Usage' }).first();
  await usage.scrollIntoViewIfNeeded();
  const selector = usage.getByRole('button', { name: /1h/ }).first();
  await selector.click();
  await page.waitForTimeout(500);
  const box = await usage.boundingBox();
  await page.screenshot({ path: `${out}period-selector-${scheme}.png`, clip: { x: box.x - 16, y: box.y - 16, width: box.width + 32, height: Math.min(box.height, 420) + 32 } });

  await context.close();
}

await browser.close();
