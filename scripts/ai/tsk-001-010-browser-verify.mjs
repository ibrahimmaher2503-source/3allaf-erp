/**
 * TSK-001..TSK-009 browser verification (owner-authorized local automation).
 *
 * Captures locale/direction, responsive, console, and network evidence for the
 * currently implemented Platform screens. This supplements, and does not
 * replace, the required manual visual review.
 */
import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.TSK_BASE_URL ?? 'http://127.0.0.1:8093';
const artifactsDirectory = path.resolve('artifacts/tsk-001-010-browser');
const chromePath = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

const desktop = { width: 1440, height: 1000 };
const tablet = { width: 834, height: 1112 };
const mobile = { width: 390, height: 844 };

const authorizedRoutes = [
    ['dashboard', '/dashboard'],
    ['settings', '/admin/settings'],
    ['branches', '/admin/branches'],
    ['stores', '/admin/stores'],
    ['cash-drawers', '/admin/cash-drawers'],
    ['authorization-baseline', '/admin/authorization-baseline'],
    ['audit', '/admin/audit'],
    ['system-health', '/admin/system/health'],
    ['ui-showcase', '/admin/system/ui-showcase'],
    ['pos', '/pos'],
];

await mkdir(artifactsDirectory, { recursive: true });

const results = {
    baseUrl,
    startedAt: new Date().toISOString(),
    screenshots: [],
    checks: [],
    consoleErrors: [],
    failedRequests: [],
};

const browser = await chromium.launch({ executablePath: chromePath, headless: true });
const context = await browser.newContext({ viewport: desktop });
const page = await context.newPage();

page.on('console', (message) => {
    if (message.type() === 'error') {
        results.consoleErrors.push({ url: page.url(), text: message.text() });
    }
});
page.on('pageerror', (error) => results.consoleErrors.push({ url: page.url(), text: error.message }));
page.on('response', (response) => {
    if (response.status() >= 400) {
        results.failedRequests.push({ url: response.url(), status: response.status() });
    }
});

async function shot(name) {
    const file = path.join(artifactsDirectory, `${name}.png`);
    await page.screenshot({ path: file, fullPage: true });
    results.screenshots.push(`${name}.png`);
}

async function inspect(name, route, viewport) {
    await page.setViewportSize(viewport);
    const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
    const state = await page.evaluate(() => ({
        lang: document.documentElement.lang,
        dir: document.documentElement.dir,
        scrollWidth: document.documentElement.scrollWidth,
        clientWidth: document.documentElement.clientWidth,
        title: document.title,
    }));
    const check = {
        name,
        route,
        viewport: `${viewport.width}x${viewport.height}`,
        status: response?.status() ?? null,
        ...state,
        horizontalOverflow: state.scrollWidth > state.clientWidth + 1,
    };
    results.checks.push(check);
    await shot(name);

    return check;
}

async function setLocale(locale) {
    await page.evaluate(async (nextLocale) => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/locale', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token ?? '' },
            body: `locale=${nextLocale}`,
        });
    }, locale);
}

async function login(username, password = 'LocalDemoOnly!2026') {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Username').fill(username);
    await page.getByRole('textbox', { name: 'Password' }).fill(password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForLoadState('networkidle');
}

async function logout() {
    await page.evaluate(async () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/logout', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': token ?? '' },
        });
    });
    await context.clearCookies();
}

// --- TSK-002: guest surfaces and validation ------------------------------
await inspect('tsk002-login-desktop-ltr', '/login', desktop);
await inspect('tsk002-forgot-password-desktop-ltr', '/forgot-password', desktop);

await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
await page.getByRole('button', { name: 'Log in' }).click();
await page.waitForLoadState('networkidle');
results.checks.push({
    name: 'tsk002-login-empty-submit',
    route: '/login',
    validationVisible: await page.locator('text=/required|مطلوب/i').first().isVisible().catch(() => false),
});
await shot('tsk002-login-validation');

await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
await page.getByLabel('Username').fill('demo-admin');
await page.getByRole('textbox', { name: 'Password' }).fill('definitely-wrong-password');
await page.getByRole('button', { name: 'Log in' }).click();
await page.waitForLoadState('networkidle');
results.checks.push({
    name: 'tsk002-invalid-credentials',
    route: '/login',
    stillOnLogin: page.url().includes('/login'),
    genericError: await page.locator('text=/credentials|بيانات/i').first().isVisible().catch(() => false),
});
await shot('tsk002-login-invalid-credentials');

// --- TSK-001: safe error pages ------------------------------------------
await inspect('tsk001-404-desktop', '/this-route-does-not-exist', desktop);
await inspect('tsk001-403-desktop', '/forbidden', desktop);
await inspect('tsk001-403-mobile-rtl', '/forbidden', mobile);

const requestIdHeader = await page.evaluate(async (url) => {
    const response = await fetch(url, { method: 'GET' });

    return response.headers.get('x-request-id');
}, `${baseUrl}/login`);
results.checks.push({ name: 'tsk001-request-id-header', value: requestIdHeader });

// --- TSK-003/004/005/006/007/009: authorized screens ---------------------
await login('demo-admin');

await setLocale('en');
for (const [name, route] of authorizedRoutes) {
    await inspect(`ltr-desktop-${name}`, route, desktop);
}

await setLocale('ar');
for (const [name, route] of authorizedRoutes) {
    await inspect(`rtl-desktop-${name}`, route, desktop);
}

for (const [name, route] of authorizedRoutes) {
    await inspect(`rtl-mobile-${name}`, route, mobile);
}

await inspect('rtl-tablet-branches', '/admin/branches', tablet);
await inspect('rtl-tablet-audit', '/admin/audit', tablet);

// PWA shell assets.
const manifestStatus = await page.evaluate(async (url) => (await fetch(url)).status, `${baseUrl}/manifest.json`);
const serviceWorkerStatus = await page.evaluate(async (url) => (await fetch(url)).status, `${baseUrl}/sw.js`);
const serviceWorkerRegistered = await page.evaluate(async () => {
    if (!('serviceWorker' in navigator)) return 'unsupported';
    const registration = await navigator.serviceWorker.getRegistration();

    return registration ? registration.scope : 'not-registered';
});
results.checks.push({
    name: 'tsk003-pwa-shell',
    manifestStatus,
    serviceWorkerStatus,
    serviceWorkerRegistered,
});

// Audit screen interaction: filter + detail modal.
await setLocale('en');
await page.goto(`${baseUrl}/admin/audit`, { waitUntil: 'networkidle' });
const firstViewButton = page.getByRole('button', { name: 'View' }).first();
if (await firstViewButton.isVisible().catch(() => false)) {
    await firstViewButton.click();
    await page.waitForTimeout(600);
    await shot('tsk009-audit-detail-modal');
    results.checks.push({
        name: 'tsk009-audit-detail-modal',
        opened: await page.locator('text=Audit event details').isVisible().catch(() => false),
        rendersRedactionMarkerWhenPresent: (await page.content()).includes('[redacted]'),
    });
}

// Shared UI states.
await inspect('tsk004-ui-showcase-mobile', '/admin/system/ui-showcase', mobile);

await logout();

// --- TSK-008: denied navigation and direct routes ------------------------
await login('demo-cashier');
await setLocale('en');
const cashierPos = await inspect('tsk008-cashier-pos', '/pos', desktop);
const cashierDeniedSettings = await inspect('tsk008-cashier-denied-settings', '/admin/settings', desktop);
const cashierDeniedAudit = await inspect('tsk008-cashier-denied-audit', '/admin/audit', desktop);
results.checks.push({
    name: 'tsk008-cashier-denials',
    posStatus: cashierPos.status,
    settingsStatus: cashierDeniedSettings.status,
    auditStatus: cashierDeniedAudit.status,
});
await logout();

await login('demo-branch-manager');
await setLocale('ar');
await inspect('tsk006-manager-branches-rtl', '/admin/branches', desktop);
await inspect('tsk006-manager-denied-drawers', '/admin/cash-drawers', desktop);
await logout();

await login('demo-reviewer');
await setLocale('en');
await inspect('tsk009-reviewer-audit', '/admin/audit', desktop);
await inspect('tsk009-reviewer-denied-branches', '/admin/branches', desktop);
await logout();

results.finishedAt = new Date().toISOString();
results.summary = {
    screens: results.checks.filter((check) => check.route).length,
    horizontalOverflow: results.checks.filter((check) => check.horizontalOverflow).map((check) => check.name),
    consoleErrorCount: results.consoleErrors.length,
    failedRequestCount: results.failedRequests.length,
};

await writeFile(path.join(artifactsDirectory, 'results.json'), JSON.stringify(results, null, 2), 'utf8');
await browser.close();

console.log(JSON.stringify(results.summary, null, 2));
