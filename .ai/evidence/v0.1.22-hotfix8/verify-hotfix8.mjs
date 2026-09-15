import { chromium } from 'playwright';

const base = 'http://127.0.0.1:8898';
const browser = await chromium.launch({ headless: true, executablePath: '/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome', args: ['--no-sandbox'] });
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG' });
const page = await context.newPage();
const errors = [];
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
page.on('pageerror', error => errors.push(error.message));
await page.goto(base + '/login');
await page.locator('[name=username]').fill('admin');
await page.locator('[name=password]').fill('Disposable-Only-Hotfix8-2026!');
await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('[data-test="login-button"]').click()]);

const setLocale = async locale => {
  const token = await page.locator('meta[name=csrf-token]').getAttribute('content');
  await context.request.post(base + '/locale', { form: { _token: token, locale } });
};
const inspect = async (locale, width) => {
  await setLocale(locale);
  await page.setViewportSize({ width, height: width < 600 ? 844 : 1000 });
  await page.goto(base + '/inventory/balances', { waitUntil: 'networkidle' });
  return page.evaluate(() => ({
    dir: document.documentElement.dir,
    overflow: document.documentElement.scrollWidth > innerWidth + 1,
    filterFields: document.querySelectorAll('[data-inventory-filter] input, [data-inventory-filter] select').length,
    actions: document.querySelectorAll('[data-inventory-filter] button, [data-inventory-filter] a').length,
  }));
};
const results = {
  arDesktop: await inspect('ar', 1440),
  enDesktop: await inspect('en', 1440),
  arMobile: await inspect('ar', 390),
  errors,
};
if (Object.values(results).some(value => value?.overflow) || errors.length) throw new Error(JSON.stringify(results));
console.log(JSON.stringify(results));
await browser.close();
