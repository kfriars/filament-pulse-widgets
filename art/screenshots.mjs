/*
 * The README's screenshots. `npm run screenshots` writes every one of them to art/:
 * the dashboard and a period selector close-up, each in light and dark mode. Rerun it
 * after a visible change to a Pulse widget, and commit what it writes.
 *
 * It serves the workbench on a database of its own, seeded right before it shoots (so
 * the Servers card's graphs reach their right edge) by ScreenshotSeeder, whose users
 * have plain names, under a believable brand. The workbench's own data and
 * `composer serve` stay as they are.
 *
 * Each shot is taken at 1440×900 at 2x, then framed in a macOS window with a soft
 * shadow on a transparent background, so it sits on GitHub's light and dark pages.
 */
import { execFileSync, spawn } from 'node:child_process';
import { mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { delimiter, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const BRAND = 'Northwind';
const SIGNED_IN_USER = 'sofia@example.com';
const SCHEMES = ['light', 'dark'];

const root = fileURLToPath(new URL('..', import.meta.url));
const art = fileURLToPath(new URL('.', import.meta.url));
const scratch = mkdtempSync(join(tmpdir(), 'filament-pulse-widgets-screenshots-'));
const database = join(scratch, 'database.sqlite');

// Testbench reads environment variables only from $_SERVER and $_ENV. Under PHP's built-in
// server, $_SERVER has none and $_ENV is empty unless `variables_order` includes E. So the
// server gets the brand and the database through an extra ini file. A scan path that starts
// with the separator keeps PHP's own scan directory.
writeFileSync(join(scratch, 'screenshots.ini'), 'variables_order = "EGPCS"\n');
writeFileSync(database, '');

const env = {
    ...process.env,
    APP_NAME: BRAND,
    DB_DATABASE: database,
    PHP_INI_SCAN_DIR: `${process.env.PHP_INI_SCAN_DIR ?? ''}${delimiter}${scratch}`,
};

const testbench = (...args) =>
    execFileSync('php', ['vendor/bin/testbench', ...args, '--no-interaction'], { cwd: root, env, stdio: ['ignore', 'ignore', 'inherit'] });

let server;
let browser;

try {
    // Publish the latest Pulse widget stylesheet, as `composer build` does.
    testbench('filament:assets');

    // Not `migrate:fresh`, which would also run the workbench's own seeder.
    testbench('migrate');
    testbench('db:seed', '--class=Workbench\\Database\\Seeders\\ScreenshotSeeder');

    const port = await freePort();
    const base = `http://127.0.0.1:${port}`;

    // `--no-reload`, or the server drops every environment variable it doesn't know.
    server = spawn('php', ['vendor/bin/testbench', 'serve', `--port=${port}`, '--no-reload'], {
        cwd: root,
        env,
        stdio: 'ignore',
        detached: true,
    });

    await waitForServer(`${base}/admin/login`);

    browser = await chromium.launch();

    for (const scheme of SCHEMES) {
        const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2, colorScheme: scheme });
        const page = await context.newPage();

        await page.goto(`${base}/_workbench/login/${SIGNED_IN_USER}`);
        await page.waitForURL('**/admin');
        await page.locator('[wire\\:name="pulse.servers"] canvas').first().waitFor();
        await page.locator('[wire\\:name="pulse.queues"] canvas').first().waitFor();
        await page.locator('[wire\\:name="pulse.usage"]').getByText('Sofia Martinez').waitFor();
        await settle(page);

        // The hero: Pulse widgets next to Filament's own widgets.
        await frame(await page.screenshot(), scheme, `dashboard-${scheme}.png`);

        // A close-up of one Pulse widget with its period selector open. It's clipped to the
        // Pulse card, not the widget, whose grid cell can be taller than the card. The margin
        // is narrower than the grid's gap, so no neighbouring widget's edge shows.
        const usage = page.locator('[wire\\:name="pulse.usage"]');
        await usage.locator('.fi-pulse-widget-period-selector .fi-dropdown-trigger button').click();
        await usage.locator('.fi-dropdown-panel').waitFor();
        await settle(page);

        await frame(await page.screenshot({ clip: around(await usage.boundingBox(), 16) }), scheme, `period-selector-${scheme}.png`);

        await context.close();
    }
} finally {
    await browser?.close();

    if (server?.pid) {
        // The server runs PHP's built-in server as a child process, so stop the whole group.
        process.kill(-server.pid, 'SIGTERM');
    }

    rmSync(scratch, { recursive: true, force: true });
}

/**
 * Frame a shot in a macOS window: a plain title bar with the traffic lights, rounded
 * corners, and a soft shadow on a transparent background. Sizes are in the shot's own
 * pixels, so at 2x a 56px title bar is macOS's 28pt one.
 */
async function frame(shot, scheme, filename) {
    const dark = scheme === 'dark';
    const page = await browser.newPage({ viewport: { width: 3200, height: 2400 }, deviceScaleFactor: 1 });

    await page.setContent(`
        <style>
            body { margin: 0; background: transparent; }
            .stage { display: inline-block; padding: 80px 100px 120px; }
            .window { border-radius: 20px; overflow: hidden; box-shadow: 0 40px 90px rgb(0 0 0 / 0.35), 0 0 0 1px ${dark ? 'rgb(255 255 255 / 0.14)' : 'rgb(0 0 0 / 0.12)'}; }
            .title-bar { height: 56px; display: flex; align-items: center; gap: 16px; padding: 0 24px; background: ${dark ? '#2b2b2e' : '#ececec'}; border-bottom: 1px solid ${dark ? '#000' : '#d4d4d4'}; }
            .title-bar i { display: block; width: 24px; height: 24px; border-radius: 50%; }
            img { display: block; }
        </style>
        <div class="stage">
            <div class="window">
                <div class="title-bar"><i style="background: #ff5f57"></i><i style="background: #febc2e"></i><i style="background: #28c840"></i></div>
                <img src="data:image/png;base64,${shot.toString('base64')}">
            </div>
        </div>
    `);

    await page.locator('.stage').screenshot({ path: join(art, filename), omitBackground: true });
    await page.close();

    console.log(`art/${filename}`);
}

/**
 * Let the page finish its requests and draw its charts. Pulse cards poll every 5
 * seconds, so the network is quiet in between.
 */
async function settle(page) {
    await page.waitForLoadState('networkidle');
    await page.mouse.move(0, 0);
    await page.waitForTimeout(1000);
}

function around(box, margin) {
    return { x: box.x - margin, y: box.y - margin, width: box.width + margin * 2, height: box.height + margin * 2 };
}

function freePort() {
    return new Promise((resolve, reject) => {
        const probe = createServer().once('error', reject);

        probe.listen(0, '127.0.0.1', () => {
            const { port } = probe.address();
            probe.close(() => resolve(port));
        });
    });
}

async function waitForServer(url) {
    for (let attempt = 0; attempt < 60; attempt++) {
        if (server.exitCode !== null) {
            throw new Error(`The workbench server exited with code ${server.exitCode}.`);
        }

        try {
            if ((await fetch(url)).ok) {
                return;
            }
        } catch {
            // Not listening yet.
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`The workbench server didn't answer ${url} within 30 seconds.`);
}
