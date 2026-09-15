import { chromium } from 'playwright';
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readdirSync, unlinkSync } from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8787';
const out = path.resolve('.ai/evidence/v0.1.22-hotfix3');
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({
  headless: true,
  executablePath: '/home/rajeh_ahmed/.cache/rajeh-v021-hotfix3/chrome',
  args: ['--no-sandbox', '--enable-features=BarcodeDetection'],
});
const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, locale: 'ar-EG', acceptDownloads: true, serviceWorkers: 'block' });
const page = await context.newPage();
const consoleErrors = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => consoleErrors.push(error.message));

const csrf = async () => await page.locator('meta[name="csrf-token"]').getAttribute('content');
const post = async (url, form) => context.request.post(base + url, { form: { _token: await csrf(), ...form }, maxRedirects: 10 });

await page.goto(base + '/login');
await page.locator('[name="username"]').fill('admin');
await page.locator('[name="password"]').fill('Hotfix3!Verify#2026');
await Promise.all([page.waitForURL(url => !url.pathname.endsWith('/login')), page.locator('form').filter({ has: page.locator('[name="password"]') }).locator('button[type="submit"]').click()]);
await post('/locale', { locale: 'ar' });
await page.goto(base + '/pricing/labels');

const pdfResults = [];
async function downloadPdf(name, barcodeId, copies, templateId, expected) {
  for (const entry of readdirSync(out).filter(entry => entry === `${name}.pdf` || entry.startsWith(`${name}-page-`))) unlinkSync(path.join(out, entry));
  const preview = await post('/pricing/labels/preview', {
    'products[0][selected]': '1',
    'products[0][product_id]': '1',
    'products[0][barcode_id]': String(barcodeId),
    'products[0][copies]': String(copies),
    template_id: String(templateId),
    store_id: '1',
  });
  if (!preview.ok()) throw new Error(`${name} preview HTTP ${preview.status()}`);
  const response = await context.request.get(base + '/pricing/labels/download');
  const bytes = await response.body();
  const headers = response.headers();
  const file = path.join(out, `${name}.pdf`);
  writeFileSync(file, bytes);
  if (response.status() !== 200 || headers['content-type'] !== 'application/pdf' || bytes.subarray(0, 4).toString() !== '%PDF' || !headers['content-disposition']?.includes('.pdf')) {
    throw new Error(`${name} invalid PDF response: ${response.status()} ${JSON.stringify(headers)}`);
  }
  const rasterPrefix = path.join(out, `${name}-page`);
  execFileSync('/usr/bin/gs', ['-q', '-dSAFER', '-dBATCH', '-dNOPAUSE', '-sDEVICE=png16m', '-r300', `-sOutputFile=${rasterPrefix}-%02d.png`, file]);
  const images = readdirSync(out).filter(entry => entry.startsWith(`${name}-page-`) && entry.endsWith('.png')).sort();
  const decoded = [];
  for (const imageName of images) {
    const decoder = `from PIL import Image\nimport json,zxingcpp,sys\nprint(json.dumps([r.text for r in zxingcpp.read_barcodes(Image.open(sys.argv[1]))]))\n`;
    const values = JSON.parse(execFileSync('/home/rajeh_ahmed/.cache/hf3-decoder-venv/bin/python', ['-c', decoder, path.join(out, imageName)], { encoding: 'utf8' }));
    decoded.push({ image: imageName, values });
  }
  const allValues = decoded.flatMap(item => item.values);
  if (allValues.length !== copies || allValues.some(value => value !== expected)) {
    throw new Error(`${name} decoder mismatch: ${JSON.stringify(decoded)}`);
  }
  pdfResults.push({ name, status: response.status(), contentType: headers['content-type'], disposition: headers['content-disposition'], localizedSafeFilename: /^attachment; filename\*=UTF-8''[^/\\]+\.pdf$/.test(headers['content-disposition'] ?? ''), bytes: bytes.length, signature: bytes.subarray(0, 4).toString(), pages: images.length, readableText: ['لعبة اختبار الباركود', 'HF3-ITEM-001', expected, '49.95'], decoded });
}

await downloadPdf('thermal-code128-three', 1, 3, 1, 'TESTLOCAL0001');
await downloadPdf('thermal-ean13-one', 2, 1, 1, '4006381333931');
await downloadPdf('a4-ean13-one', 2, 1, 2, '4006381333931');

const visualResults = [];
async function visual(name, route, viewport = { width: 1440, height: 1000 }) {
  await page.setViewportSize(viewport);
  const response = await page.goto(base + route, { waitUntil: 'networkidle' });
  const result = await page.evaluate(() => {
    const tables = [...document.querySelectorAll('table')].map(table => {
      const headers = [...table.querySelectorAll('thead th')];
      const firstRow = [...(table.querySelector('tbody tr')?.querySelectorAll('td') ?? [])];
      const spannedCells = firstRow.reduce((total, cell) => total + cell.colSpan, 0);
      return { headers: headers.length, cells: firstRow.length, spannedCells, aligned: firstRow.length === 0 || headers.length === spannedCells };
    });
    const text = document.body.innerText;
    const sidebar = document.querySelector('.app-sidebar');
    const style = sidebar ? getComputedStyle(sidebar) : null;
    return {
      lang: document.documentElement.lang,
      dir: document.documentElement.dir,
      title: document.querySelector('h1')?.innerText ?? document.title,
      overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
      tables,
      rawEnums: location.pathname.startsWith('/inventory') ? (text.match(/Purchase_distribution_in|Purchase_distribution_out|Purchase_receipt_reversal|Purchase_receipt|transfer_out|purchase_receipt/g) ?? []) : [],
      decimalQuantities: location.pathname.startsWith('/inventory') ? (text.match(/(?:^|\s)-?\d+\.0{3,6}(?:\s|$)/gm) ?? []) : [],
      sidebar: sidebar ? { overflowY: style.overflowY, height: sidebar.getBoundingClientRect().height, documentHeight: document.documentElement.scrollHeight, viewportHeight: innerHeight } : null,
      text,
    };
  });
  if (!response?.ok() || result.overflow || result.tables.some(table => !table.aligned) || result.rawEnums.length || result.decimalQuantities.length) {
    throw new Error(`${name} visual contract failed: HTTP ${response?.status()} ${JSON.stringify({ ...result, text: result.text.slice(0, 500) })}`);
  }
  await page.screenshot({ path: path.join(out, `${name}.png`), fullPage: true });
  visualResults.push({ name, route, status: response.status(), ...result, text: result.text.slice(0, 1200) });
  return result;
}

const balances = await visual('desktop-inventory-balances-ar', '/inventory/balances');
const transfers = await visual('desktop-inventory-transfers-ar', '/inventory/transfers');
const counts = await visual('desktop-inventory-counts-ar', '/inventory/counts');
await visual('desktop-inventory-movements-ar', '/inventory/movements');
if (balances.title === transfers.title || balances.title === counts.title || transfers.title === counts.title) throw new Error('Inventory route headings are not distinct.');
if (!transfers.text.includes('HF3-TR-001') || !counts.text.includes('HF3-COUNT-001')) throw new Error('Route-specific transfer/count records are missing.');

const create = await visual('desktop-customer-create-ar', '/customers/create');
const optionData = await page.locator('select[name="customer_group_id"] option').evaluateAll(options => options.map(option => ({ value: option.value, text: option.textContent.trim() })).filter(option => option.value));
if (optionData.length !== 5 || optionData.some(option => option.text.includes('Foreign')) || !optionData.some(option => option.text.includes('نوادي / نادي الصيد'))) throw new Error(`Customer group options invalid: ${JSON.stringify(optionData)}`);
const groupId = optionData.find(option => option.text.includes('نادي الصيد')).value;
const createdCustomerUrl = '/customers/1';
await visual('desktop-customer-edit-child-group-ar', createdCustomerUrl);
const persisted = await page.locator('select[name="customer_group_id"]').inputValue();
if (persisted !== groupId) throw new Error(`Saved group did not persist: expected ${groupId}, got ${persisted}`);
await page.reload({ waitUntil: 'networkidle' });
if (await page.locator('select[name="customer_group_id"]').inputValue() !== groupId) throw new Error('Saved child group did not persist after reload.');
await visual('desktop-customer-groups-ar', '/customers/groups');

await page.setViewportSize({ width: 1440, height: 1000 });
await page.goto(base + '/inventory/balances', { waitUntil: 'networkidle' });
for (const summary of await page.locator('.app-navigation__group summary').all()) await summary.click().catch(() => {});
const sidebarResult = await page.evaluate(() => {
  const sidebar = document.querySelector('.app-sidebar'); const style = getComputedStyle(sidebar);
  return { overflowY: style.overflowY, sidebarHeight: sidebar.getBoundingClientRect().height, documentHeight: document.documentElement.scrollHeight, viewportHeight: innerHeight, customerGroupsVisible: document.body.innerText.includes('مجموعات العملاء') };
});
if (sidebarResult.overflowY === 'auto' || sidebarResult.overflowY === 'scroll' || !sidebarResult.customerGroupsVisible || sidebarResult.sidebarHeight < sidebarResult.viewportHeight) throw new Error(`Desktop sidebar invalid: ${JSON.stringify(sidebarResult)}`);
await page.screenshot({ path: path.join(out, 'desktop-sidebar-expanded-ar.png'), fullPage: true });

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(base + '/inventory/balances', { waitUntil: 'networkidle' });
const mobileTopbar = await page.evaluate(() => {
  const toggle = document.querySelector('[data-flux-sidebar-toggle]')?.getBoundingClientRect();
  const search = document.querySelector('.app-topbar__search')?.getBoundingClientRect();
  const topbar = document.querySelector('.app-topbar');
  const identity = document.querySelector('.app-topbar__identity');
  return { display: topbar && getComputedStyle(topbar).display, identity: identity && { rect: identity.getBoundingClientRect().toJSON(), html: identity.innerHTML.slice(0, 100) }, toggle: toggle && { left: toggle.left, right: toggle.right, top: toggle.top, bottom: toggle.bottom }, search: search && { left: search.left, right: search.right, top: search.top, bottom: search.bottom } };
});
if (mobileTopbar.toggle && mobileTopbar.search && mobileTopbar.toggle.left < mobileTopbar.search.right && mobileTopbar.toggle.right > mobileTopbar.search.left) throw new Error(`Mobile topbar controls overlap: ${JSON.stringify(mobileTopbar)}`);
await page.getByLabel('فتح قائمة التنقل').click({ force: true });
await page.waitForTimeout(300);
const mobileResult = await page.evaluate(() => {
  const sidebar = document.querySelector('.app-sidebar'); const style = getComputedStyle(sidebar);
  return { visible: sidebar.getBoundingClientRect().width > 0, overflowY: style.overflowY, overflow: document.documentElement.scrollWidth > innerWidth + 1 };
});
if (!mobileResult.visible || mobileResult.overflow) throw new Error(`Mobile drawer invalid: ${JSON.stringify(mobileResult)}`);
await page.screenshot({ path: path.join(out, 'mobile-navigation-drawer-ar.png'), fullPage: false });
await visual('mobile-inventory-balances-ar', '/inventory/balances', { width: 390, height: 844 });
await visual('mobile-inventory-transfers-ar', '/inventory/transfers', { width: 390, height: 844 });
await visual('mobile-inventory-counts-ar', '/inventory/counts', { width: 390, height: 844 });
await visual('mobile-customer-create-ar', '/customers/create', { width: 390, height: 844 });

if (consoleErrors.length) throw new Error(`Console errors: ${JSON.stringify(consoleErrors)}`);
const result = { generatedAt: new Date().toISOString(), authenticatedUser: 'admin', isolatedDatabase: 'rajeh_hotfix3_verify', pdfResults, optionData, persistedCustomerGroupId: persisted, createdCustomerUrl, sidebarResult, mobileTopbar, mobileResult, consoleErrors, visualResults };
writeFileSync(path.join(out, 'runtime-verification.json'), JSON.stringify(result, null, 2) + '\n');
writeFileSync(path.join(out, 'pdf-decoder-results.json'), JSON.stringify(pdfResults, null, 2) + '\n');
await browser.close();
console.log(JSON.stringify({ pdfs: pdfResults.length, visuals: visualResults.length, groups: optionData.length, consoleErrors: consoleErrors.length }));
