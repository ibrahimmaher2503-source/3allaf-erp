import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = 'http://127.0.0.1:8092';
const output = path.resolve('artifacts/authorization-verify');
const browser = await chromium.launch({
    executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    headless: true,
});

await mkdir(output, { recursive: true });
const results = [];

async function signIn(username) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await context.newPage();
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByLabel('Username').fill(username);
    await page.getByRole('textbox', { name: 'Password' }).fill('LocalDemoOnly!2026');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(`${baseUrl}/dashboard`).catch(() => {});
    return { context, page };
}

async function verify(username, allowedPath, deniedPath, screenshot, hiddenText = null) {
    const { context, page } = await signIn(username);
    const allowed = await page.goto(`${baseUrl}${allowedPath}`, { waitUntil: 'networkidle' });
    if (allowed.status() !== 200) throw new Error(`${username} expected 200 for ${allowedPath}, received ${allowed.status()}.`);
    if (hiddenText && await page.getByText(hiddenText, { exact: true }).count() !== 0) throw new Error(`${username} can see ${hiddenText}.`);
    await page.screenshot({ path: path.join(output, screenshot), fullPage: true });
    const denied = await page.goto(`${baseUrl}${deniedPath}`, { waitUntil: 'networkidle' });
    if (denied.status() !== 403) throw new Error(`${username} expected 403 for ${deniedPath}, received ${denied.status()}.`);
    results.push({ username, allowedPath, deniedPath, allowedStatus: allowed.status(), deniedStatus: denied.status(), screenshot, hiddenText });
    await context.close();
}

await verify('demo-admin', '/admin/authorization-baseline', '/forbidden', 'super-admin-authorization.png');
await verify('demo-branch-manager', '/admin/branches', '/admin/settings', 'branch-manager-branches.png', 'Add Branch');
await verify('demo-cashier', '/pos', '/admin/stores', 'cashier-pos.png');
await verify('demo-reviewer', '/dashboard', '/admin/branches', 'reviewer-dashboard.png');
await verify('demo-no-access', '/', '/dashboard', 'no-access-home.png');

await writeFile(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
await browser.close();
console.log(JSON.stringify(results, null, 2));
