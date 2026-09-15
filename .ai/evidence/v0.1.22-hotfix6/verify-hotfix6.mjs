import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8016';
const out = path.resolve('.ai/evidence/v0.1.22-hotfix6');
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: '/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome', args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' });
const page = await context.newPage();
const errors = [];
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('pageerror', error => errors.push(error.message));

await page.goto(base + '/login');
await page.locator('[name=username]').fill('admin');
await page.locator('[name=password]').fill('Hotfix4!Verify#2026');
await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test="login-button"]').click()]);
const setLocale = async locale => {
  const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
  await context.request.post(base + '/locale', { form: { _token: token, locale } });
};
await setLocale('ar');

await page.goto(base + '/admin/settings?tab=company&setup=true&setup_step=company', { waitUntil: 'networkidle' });
const active = await page.evaluate(() => ({
  parentOpen: Boolean(document.querySelector('.app-navigation__nested[open]')),
  essentialsOpen: Boolean(document.querySelector('[data-setup-subcategory="basics"][open]')),
  companyActive: Boolean(document.querySelector('[data-setup-step="company"][aria-current="page"]')),
}));
if (!Object.values(active).every(Boolean)) throw new Error('active setup expansion failed: ' + JSON.stringify(active));
await page.locator('.app-navigation__nested').evaluate(element => element.open = true);
for (const subgroup of await page.locator('[data-setup-subcategory]').all()) await subgroup.evaluate(element => element.open = true);
const desktop = await page.evaluate(() => {
  const leaves = [...document.querySelector('.app-navigation').querySelectorAll('[data-setup-step]')];
  const counts = Object.fromEntries([...document.querySelectorAll('[data-setup-subcategory]')].map(group => [group.dataset.setupSubcategory, group.querySelectorAll('[data-setup-step]').length]));
  const sidebar = document.querySelector('.app-sidebar');
  const navigation = document.querySelector('.app-navigation');
  return {
    innerWidth,
    count: leaves.length,
    unique: new Set(leaves.map(leaf => leaf.dataset.setupStep)).size,
    counts,
    linksValid: leaves.every(leaf => (leaf.getAttribute('href') ?? '').includes('setup=true') && (leaf.getAttribute('href') ?? '').includes('setup_step=')),
    pageOverflow: document.documentElement.scrollWidth > innerWidth + 1,
    navigationOverflowY: getComputedStyle(navigation).overflowY,
    sidebarPosition: getComputedStyle(sidebar).position,
    sidebarComputed: { height: getComputedStyle(sidebar).height, minHeight: getComputedStyle(sidebar).minHeight, maxHeight: getComputedStyle(sidebar).maxHeight, top: getComputedStyle(sidebar).top, bottom: getComputedStyle(sidebar).bottom, display: getComputedStyle(sidebar).display },
    inlineRulePresent: [...document.querySelectorAll('style')].some(style => style.textContent.includes('inset-block-end: auto')),
    sidebarTag: sidebar.tagName + ' ' + sidebar.getAttributeNames().map(name => name + '=' + sidebar.getAttribute(name)).join(' '),
    sidebarHeight: sidebar.getBoundingClientRect().height,
    navigationHeight: navigation.getBoundingClientRect().height,
    navigationScrollHeight: navigation.scrollHeight,
    footerBottom: document.querySelector('.app-sidebar__footer').getBoundingClientRect().bottom,
    documentHeight: document.documentElement.scrollHeight,
    documentScrollable: document.documentElement.scrollHeight > innerHeight,
  };
});
if (desktop.count !== 21 || desktop.unique !== 21 || desktop.counts.basics !== 5 || desktop.counts.settings !== 5 || desktop.counts.operations !== 11 || !desktop.linksValid || desktop.pageOverflow || ['auto','scroll'].includes(desktop.navigationOverflowY) || desktop.sidebarHeight < desktop.navigationHeight || desktop.documentHeight < desktop.footerBottom) throw new Error('desktop setup menu failed: ' + JSON.stringify(desktop));
await page.screenshot({ path: path.join(out, 'arabic-basic-data-desktop.png'), fullPage: true });

await page.goto(base + '/admin/stores', { waitUntil: 'networkidle' });
if (!await page.getByText('إضافة مخزن / منفذ بيع', { exact: true }).first().isVisible()) throw new Error('store action wording missing');
await page.getByText('إضافة مخزن / منفذ بيع', { exact: true }).first().click();
await page.getByText('حفظ المخزن / منفذ البيع', { exact: true }).waitFor({ state: 'visible', timeout: 10000 });
const storeText = await page.locator('body').innerText();
if (!storeText.includes('حفظ المخزن / منفذ البيع')) throw new Error('store create workflow wording missing');

await page.goto(base + '/admin/audit', { waitUntil: 'networkidle' });
const auditArabic = await page.locator('body').innerText();
for (const raw of ['create_store','map_branch_selling_store','create_payment_method','update_local_settings','BranchSellingStore','PaymentMethod','AUDIT_EXPORT_MAX_ROWS']) if (auditArabic.includes(raw)) throw new Error('raw audit value in Arabic: ' + raw);
if (!auditArabic.includes('سجل التدقيق')) throw new Error('Arabic audit title missing');

await setLocale('en');
await page.goto(base + '/admin/audit', { waitUntil: 'networkidle' });
const auditEnglish = await page.locator('body').innerText();
for (const raw of ['create_store','map_branch_selling_store','create_payment_method','update_local_settings','BranchSellingStore','PaymentMethod','AUDIT_EXPORT_MAX_ROWS']) if (auditEnglish.includes(raw)) throw new Error('raw audit value in English: ' + raw);
if (!auditEnglish.includes('Audit logs')) throw new Error('English audit title missing');

await setLocale('ar');
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(base + '/admin/settings?tab=company&setup=true&setup_step=company', { waitUntil: 'networkidle' });
await page.getByLabel('فتح قائمة التنقل').click({ force: true });
await page.waitForTimeout(300);
const mobile = await page.evaluate(() => ({
  sidebarVisible: document.querySelector('.app-sidebar').getBoundingClientRect().width > 0,
  pageOverflow: document.documentElement.scrollWidth > innerWidth + 1,
  count: document.querySelector('.app-navigation').querySelectorAll('[data-setup-step]').length,
  activeVisible: Boolean(document.querySelector('[data-setup-step="company"][aria-current="page"]')),
}));
if (!mobile.sidebarVisible || mobile.pageOverflow || mobile.count !== 21 || !mobile.activeVisible) throw new Error('mobile drawer failed: ' + JSON.stringify(mobile));
await page.screenshot({ path: path.join(out, 'mobile-basic-data-drawer.png'), fullPage: false });

if (errors.length) throw new Error('browser errors: ' + JSON.stringify(errors));
const result = { database: 'rajeh_hotfix6_verify', active, desktop, mobile, audit: { arabic: 'localized', english: 'localized', rawValuesExposed: false }, storeCreate: 'verified', errors };
writeFileSync(path.join(out, 'runtime-verification.json'), JSON.stringify(result, null, 2) + '\n');
await browser.close();
console.log(JSON.stringify(result));
