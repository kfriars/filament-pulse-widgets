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

// Frame each shot in a macOS window: title bar with traffic lights, rounded corners and a
// soft shadow on a transparent background, so it sits on GitHub's light and dark pages alike.
import { readFileSync } from 'node:fs';

const framer = await chromium.launch();
const framePage = await framer.newPage({ deviceScaleFactor: 1 });

for (const name of ['dashboard-light', 'dashboard-dark', 'period-selector-light', 'period-selector-dark']) {
  const dark = name.endsWith('dark');
  const png = readFileSync(`${out}${name}.png`).toString('base64');
  await framePage.setContent(`
    <style>
      body { margin: 0; background: transparent; }
      .stage { display: inline-block; padding: 80px 100px 120px; }
      .window { border-radius: 20px; overflow: hidden; box-shadow: 0 40px 90px rgba(0,0,0,.35), 0 0 0 1px ${dark ? 'rgba(255,255,255,.14)' : 'rgba(0,0,0,.12)'}; }
      .bar { height: 56px; display: flex; align-items: center; gap: 16px; padding: 0 24px; background: ${dark ? '#2b2b2e' : '#ececec'}; border-bottom: 1px solid ${dark ? '#000' : '#d4d4d4'}; }
      .bar i { width: 24px; height: 24px; border-radius: 50%; display: block; }
      img { display: block; }
    </style>
    <div class="stage"><div class="window">
      <div class="bar"><i style="background:#ff5f57"></i><i style="background:#febc2e"></i><i style="background:#28c840"></i></div>
      <img src="data:image/png;base64,${png}">
    </div></div>`);
  await framePage.locator('.stage').screenshot({ path: `${out}${name}-framed.png`, omitBackground: true });
}

await framer.close();
