import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8092';
const artifactsDirectory = path.resolve('artifacts/visual-verify');
const chromePath = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const routes = [
    ['dashboard', '/dashboard'],
    ['pos', '/pos'],
    ['system-app', '/system/app'],
    ['system-health', '/admin/system/health'],
    ['ui-showcase', '/admin/system/ui-showcase'],
    ['settings', '/admin/settings'],
    ['branches', '/admin/branches'],
    ['stores', '/admin/stores'],
    ['cash-drawers', '/admin/cash-drawers'],
    ['authorization-baseline', '/admin/authorization-baseline'],
];

await mkdir(artifactsDirectory, { recursive: true });

const browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
});

const results = { screenshots: [], checks: [], consoleErrors: [] };
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
page.on('console', (message) => {
    if (message.type() === 'error') results.consoleErrors.push(message.text());
});
page.on('pageerror', (error) => results.consoleErrors.push(error.message));

await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(artifactsDirectory, 'login-desktop.png'), fullPage: true });
await page.getByLabel('Username').fill('demo-admin');
await page.getByRole('textbox', { name: 'Password' }).fill('LocalDemoOnly!2026');
await page.getByRole('button', { name: 'Log in' }).click();
await page.waitForURL(`${baseUrl}/dashboard`);

async function setLocale(locale) {
    await page.evaluate(async (nextLocale) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/locale', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': token ?? '',
            },
            body: `locale=${nextLocale}`,
        });
    }, locale);
}

async function capture(name, route, locale, viewport) {
    await page.setViewportSize(viewport);
    await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
    const state = await page.evaluate(() => ({
        lang: document.documentElement.lang,
        dir: document.documentElement.dir,
        hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth,
        bodyText: document.body.innerText,
    }));
    const expectedDirection = locale === 'ar' ? 'rtl' : 'ltr';
    if (state.dir !== expectedDirection) throw new Error(`${name} rendered ${state.dir}, expected ${expectedDirection}.`);
    if (state.hasHorizontalOverflow) throw new Error(`${name} has horizontal overflow at ${viewport.width}px.`);

    const filename = `${name}-${locale}-${viewport.width}.png`;
    await page.screenshot({ path: path.join(artifactsDirectory, filename), fullPage: true });
    results.screenshots.push(filename);
    results.checks.push({ name, route, locale, viewport, dir: state.dir, horizontalOverflow: state.hasHorizontalOverflow });
}

await setLocale('ar');
for (const [name, route] of routes) {
    await capture(name, route, 'ar', { width: 1440, height: 1000 });
}

for (const [name, route] of routes.filter(([name]) => ['pos', 'system-app', 'ui-showcase', 'settings', 'branches', 'stores', 'cash-drawers'].includes(name))) {
    await capture(name, route, 'ar', { width: 390, height: 844 });
}

await setLocale('en');
for (const [name, route] of routes.filter(([name]) => ['dashboard', 'pos', 'settings', 'branches', 'stores', 'cash-drawers'].includes(name))) {
    await capture(name, route, 'en', { width: 1440, height: 1000 });
}

await writeFile(path.join(artifactsDirectory, 'results.json'), JSON.stringify(results, null, 2));
await browser.close();

if (results.consoleErrors.length > 0) {
    throw new Error(`Browser console errors: ${results.consoleErrors.join(' | ')}`);
}

console.log(JSON.stringify(results, null, 2));
