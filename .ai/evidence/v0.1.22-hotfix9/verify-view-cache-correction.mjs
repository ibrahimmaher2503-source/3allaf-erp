import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const base = 'http://127.0.0.1:8899';
const browser = await chromium.launch({
    headless: true,
    executablePath: '/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome',
    args: ['--no-sandbox'],
});
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' });
const page = await context.newPage();
const errors = [];

page.on('console', (message) => {
    if (message.type() === 'error') errors.push(message.text());
});
page.on('pageerror', (error) => errors.push(error.message));

await page.goto(`${base}/login`);
await page.locator('[name=username]').fill('admin');
await page.locator('[name=password]').fill('Hotfix9!Verify#2026');
await Promise.all([
    page.waitForURL((url) => !url.pathname.endsWith('/login')),
    page.locator('[data-test="login-button"]').click(),
]);

const setLocale = async (locale) => {
    const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
    await context.request.post(`${base}/locale`, { form: { _token: token, locale } });
};

const inspect = async (locale) => {
    await setLocale(locale);
    await page.goto(`${base}/pricing/labels`, { waitUntil: 'networkidle' });
    const labels = await page.evaluate(() => ({
        dir: document.documentElement.dir,
        workspace: Boolean(document.querySelector('[data-label-workspace]')),
        search: Boolean(document.querySelector('#product-search')),
        alpineReady: typeof window.Alpine !== 'undefined',
        overflow: document.documentElement.scrollWidth > innerWidth + 1,
    }));

    await page.goto(`${base}/pricing/lists/manage?setup=1&setup_step=prices`, { waitUntil: 'networkidle' });
    const pricing = await page.evaluate(() => ({
        readinessSummaries: document.querySelectorAll('[data-pricing-readiness]').length,
        overflow: document.documentElement.scrollWidth > innerWidth + 1,
    }));

    await page.goto(`${base}/admin/settings?tab=company&setup=true&setup_step=company`, { waitUntil: 'networkidle' });
    await page.locator('.app-navigation__nested').evaluate((element) => { element.open = true; });
    for (const group of await page.locator('[data-setup-subcategory]').all()) {
        await group.evaluate((element) => { element.open = true; });
    }
    const navigation = await page.evaluate(() => {
        const leaves = [...document.querySelectorAll('.app-navigation [data-setup-step]')];
        return {
            leaves: leaves.length,
            uniqueLeaves: new Set(leaves.map((leaf) => leaf.dataset.setupStep)).size,
            partyDeferred: !leaves.some((leaf) => leaf.dataset.setupStep === 'party-readiness'),
            overflow: document.documentElement.scrollWidth > innerWidth + 1,
        };
    });

    return { labels, pricing, navigation };
};

const results = {
    database: 'toyjoy_hotfix9_viewcache_verify',
    ar: await inspect('ar'),
    en: await inspect('en'),
    errors,
};

for (const locale of [results.ar, results.en]) {
    if (!locale.labels.workspace || !locale.labels.search || !locale.labels.alpineReady || locale.labels.overflow) throw new Error(JSON.stringify(results));
    if (locale.pricing.readinessSummaries !== 1 || locale.pricing.overflow) throw new Error(JSON.stringify(results));
    if (locale.navigation.leaves !== 20 || locale.navigation.uniqueLeaves !== 20 || !locale.navigation.partyDeferred || locale.navigation.overflow) throw new Error(JSON.stringify(results));
}
if (results.ar.labels.dir !== 'rtl' || results.en.labels.dir !== 'ltr' || errors.length) throw new Error(JSON.stringify(results));

writeFileSync(
    '.ai/evidence/v0.1.22-hotfix9/view-cache-correction-browser.json',
    `${JSON.stringify(results, null, 2)}\n`,
);
console.log(JSON.stringify(results));
await browser.close();
