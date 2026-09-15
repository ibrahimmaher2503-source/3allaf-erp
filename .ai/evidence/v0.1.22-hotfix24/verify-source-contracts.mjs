import assert from 'node:assert/strict';
import fs from 'node:fs';

const purchase = fs.readFileSync('resources/views/purchasing/invoices.blade.php', 'utf8');
const productForm = fs.readFileSync('resources/views/catalog/product-form.blade.php', 'utf8');
const productDetail = fs.readFileSync('resources/views/catalog/product-detail.blade.php', 'utf8');
const reports = fs.readFileSync('resources/views/pages/reports/index.blade.php', 'utf8');
const page = fs.readFileSync('resources/views/components/app/page.blade.php', 'utf8');
const pageHeader = fs.readFileSync('resources/views/components/page-header.blade.php', 'utf8');
const css = fs.readFileSync('resources/css/app.css', 'utf8');
const ar = JSON.parse(fs.readFileSync('lang/ar.json', 'utf8'));
const en = JSON.parse(fs.readFileSync('lang/en.json', 'utf8'));

const quickMethod = purchase.slice(purchase.indexOf('public function createQuickProduct'), purchase.indexOf('public function removeLine'));
const quickModal = purchase.slice(purchase.indexOf('@if($showQuickProduct)'), purchase.indexOf('@if ($showTransitionModal)'));
for (const field of [
  'barcode_registration_type', 'barcode', 'model_number', 'name_ar', 'name_en', 'category_id',
  'brand_id', 'preferred_supplier_id', 'product_type', 'status', 'average_cost', 'sale_price', 'open_price',
]) assert.ok(quickModal.includes(`quickProduct.${field}`), `quick product field missing: ${field}`);
assert.ok(quickMethod.includes("Gate::authorize('products_categories_brands.create')"));
assert.ok(quickMethod.includes("Rule::unique('products', 'item_code')"));
assert.ok(quickMethod.includes("Rule::unique('barcodes', 'barcode')"));
assert.ok(quickMethod.includes('DB::transaction(function () use'));
assert.ok(quickMethod.includes('$save->execute(['));
assert.ok(quickMethod.includes('$barcodes->addSupplierBarcode'));
assert.ok(quickMethod.includes('$barcodes->allocateLocalBarcode'));
assert.ok(quickMethod.includes('$this->insertProduct($product)'));
assert.equal(quickMethod.includes('$this->invoiceForm ='), false);
assert.equal(quickMethod.includes('$this->lineItems ='), false);
assert.equal(quickMethod.includes('ApprovePurchaseInvoiceAction'), false);
assert.equal(quickModal.includes('description_ar'), false);
assert.equal(quickModal.includes('description_en'), false);
assert.ok(purchase.includes("'preferred_supplier_id' => (string) ($this->invoiceForm['supplier_id'] ?: '')"));
console.log('PURCHASE_QUICK_PRODUCT_CONTRACT=PASS fields=13 atomic=yes draft_preserved=yes');

const identity = productForm.slice(productForm.indexOf('id="product-identity"'), productForm.indexOf('id="product-classification"'));
const additional = productForm.slice(productForm.indexOf("Stage B — Optional additional data"));
assert.equal(identity.includes('productForm.description_ar'), false);
assert.equal(identity.includes('productForm.description_en'), false);
assert.ok(additional.includes('productForm.description_ar'));
assert.ok(additional.includes('productForm.description_en'));
assert.ok(productForm.includes(":label=\"__('Cost price')\""));
assert.ok(productDetail.includes("<flux:heading size=\"lg\">{{ __('Cost price') }}"));
assert.equal(productForm.includes('متوسط تكلفة المخزون'), false);
assert.equal(ar['Average inventory cost'], 'سعر التكلفة');
assert.equal(ar['Cost price'], 'سعر التكلفة');
console.log('PRODUCT_DESCRIPTION_AND_COST_LABEL_CONTRACT=PASS');

assert.equal(reports.includes('report-catalog-heading'), false);
assert.equal(reports.includes('min-h-28'), false);
assert.equal((reports.match(/aria-label="\{\{ __\('Report categories'\) \}\}"/g) || []).length, 1);
for (const route of ['reports.sales', 'reports.customers', 'reports.cash', 'reports.purchasing', 'reports.inventory', 'reports.parties', 'reports.assets']) assert.ok(reports.includes(route));
for (const format of ['xlsx', 'pdf']) assert.ok(reports.includes(`'${format}'`));
for (const key of ['Date presets', 'Last 7 days', 'Last 30 days', 'Active filters', 'Search report details']) {
  assert.ok(ar[key] && ar[key] !== key, `Arabic report translation missing: ${key}`);
  assert.equal(en[key], key);
}
assert.ok(reports.includes("app()->getLocale() === 'ar' ? ($branch->name_ar"));
assert.ok(reports.includes("app()->getLocale() === 'ar' ? ($supplier->name_ar"));
console.log('REPORTS_COMPACT_LOCALIZATION_CONTRACT=PASS selector_rows=1');

assert.ok(page.includes('space-y-4'));
assert.ok(pageHeader.includes('mb-3') && pageHeader.includes('pb-3'));
assert.ok(css.includes('px-4 py-3 sm:px-7 sm:py-4 lg:px-10 lg:py-5'));
const changedSharedCss = css.slice(css.indexOf('.page-frame'), css.indexOf('.dashboard-shell'));
for (const forbidden of ['html {', 'body {', '.app-layout', '.app-sidebar', 'overflow-y', 'position:']) assert.equal(changedSharedCss.includes(forbidden), false);
console.log('AUTHENTICATED_COMPACT_LAYOUT_CONTRACT=PASS');

assert.equal(fs.readFileSync('app/Support/ApplicationVersion.php', 'utf8').includes("0.1.22-hotfix24"), true);
console.log('HOTFIX24_SOURCE_CONTRACTS=PASS');
