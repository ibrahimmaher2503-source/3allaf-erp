import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8899';
const out = path.resolve('.ai/evidence/v0.1.22-hotfix9');
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: '/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome', args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' });
const page = await context.newPage();
const errors = [];
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('pageerror', error => errors.push(error.message));

await page.goto(base + '/login');
await page.locator('[name=username]').fill('admin');
await page.locator('[name=password]').fill('Hotfix9!Verify#2026');
await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test="login-button"]').click()]);
const setLocale = async locale => {
  const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
  await context.request.post(base + '/locale', { form: { _token: token, locale } });
};
const inspectNavigation = async (locale, width) => {
  await setLocale(locale);
  await page.setViewportSize({ width, height: width < 600 ? 844 : 1000 });
  await page.goto(base + '/admin/settings?tab=company&setup=true&setup_step=company', { waitUntil: 'networkidle' });
  if (width < 600) await page.getByLabel('فتح قائمة التنقل').click({ force: true });
  await page.locator('.app-navigation__nested').evaluate(element => element.open = true);
  for (const subgroup of await page.locator('[data-setup-subcategory]').all()) await subgroup.evaluate(element => element.open = true);
  return page.evaluate(() => {
    const navigation = document.querySelector('.app-navigation');
    const text = navigation.innerText;
    const leaves = [...navigation.querySelectorAll('[data-setup-step]')];
    return {
      dir: document.documentElement.dir,
      overflow: document.documentElement.scrollWidth > innerWidth + 1,
      setupLeaves: leaves.length,
      uniqueSetupLeaves: new Set(leaves.map(leaf => leaf.dataset.setupStep)).size,
      partyStepVisible: leaves.some(leaf => leaf.dataset.setupStep === 'party-readiness'),
      partySectionVisible: text.includes('الحفلات والحجوزات') || text.includes('Parties & bookings'),
      systemSettingsStandalone: text.includes('إعدادات النظام') || text.includes('System settings'),
      footerProfile: Boolean(document.querySelector('.app-sidebar__footer')),
      headerProfile: Boolean(document.querySelector('.app-topbar__profile')),
      essentials: ['company', 'branches-stores', 'warehouses', 'cash-drawers', 'users-scopes'].every(key => leaves.some(leaf => leaf.dataset.setupStep === key)),
      roles: text.includes('الأدوار والصلاحيات') || text.includes('Roles & permissions'),
    };
  });
};

const arDesktop = await inspectNavigation('ar', 1440);
await page.screenshot({ path: path.join(out, 'sidebar-ar-desktop.png'), fullPage: true });
const enDesktop = await inspectNavigation('en', 1440);
await page.screenshot({ path: path.join(out, 'sidebar-en-desktop.png'), fullPage: true });
const arMobile = await inspectNavigation('ar', 390);
await page.screenshot({ path: path.join(out, 'drawer-ar-mobile.png'), fullPage: false });

await page.goto(base + '/initial-setup', { waitUntil: 'networkidle' });
const setup = await page.evaluate(() => ({
  cards: document.querySelectorAll('[data-guide^="initial-setup-step-"]').length,
  partyCard: Boolean(document.querySelector('[data-guide="initial-setup-step-party-readiness"]')),
  lastSequence: [...document.querySelectorAll('[data-guide^="initial-setup-step-"]')].at(-1)?.innerText.match(/20/) !== null,
  overflow: document.documentElement.scrollWidth > innerWidth + 1,
}));

await page.goto(base + '/pricing/lists/manage?setup=1&setup_step=prices', { waitUntil: 'networkidle' });
const pricing = await page.evaluate(() => ({
  readinessSummaries: document.querySelectorAll('[data-pricing-readiness]').length,
  topSummaryText: document.querySelector('.setup-context')?.innerText ?? '',
  overflow: document.documentElement.scrollWidth > innerWidth + 1,
}));

const results = { database: 'toyjoy_hotfix9_verify', arDesktop, enDesktop, arMobile, setup, pricing, errors };
for (const navigation of [arDesktop, enDesktop, arMobile]) {
  if (navigation.overflow || navigation.setupLeaves !== 20 || navigation.uniqueSetupLeaves !== 20 || navigation.partyStepVisible || navigation.partySectionVisible || navigation.systemSettingsStandalone || navigation.footerProfile || !navigation.headerProfile || !navigation.essentials || !navigation.roles) throw new Error(JSON.stringify(results));
}
if (setup.cards !== 20 || setup.partyCard || !setup.lastSequence || setup.overflow || pricing.readinessSummaries !== 1 || !pricing.topSummaryText || pricing.overflow || errors.length) throw new Error(JSON.stringify(results));
writeFileSync(path.join(out, 'runtime-verification.json'), JSON.stringify(results, null, 2) + '\n');
console.log(JSON.stringify(results));
await browser.close();
