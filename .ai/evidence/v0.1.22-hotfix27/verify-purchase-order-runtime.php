<?php

declare(strict_types=1);

use App\Http\Controllers\TransactionProductSearchController;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message.PHP_EOL);
        exit(1);
    }
};

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix27', 'Hotfix27 version contract is not active.');
$route = Route::getRoutes()->getByName('transaction-products.search');
$assert($route !== null && $route->getActionName() === TransactionProductSearchController::class, 'Authorized transaction product-search route is missing.');

$contracts = [
    'en' => ['Product', 'Use supplier products only', 'Scan with camera', 'Last supplier price'],
    'ar' => ['منتج', 'استخدام منتجات المورد فقط', 'المسح بالكاميرا', 'آخر سعر من المورد'],
];

foreach ($contracts as $locale => $needles) {
    $app->setLocale($locale);
    $lookup = Blade::render('<x-product-line-lookup name="lines[0][product_id]" :required="true" :purchasing="true" supplier-id="7" :supplier-only="true" currency-code="EGP" />');
    $checkbox = Blade::render('<flux:checkbox :label="__(\'Use supplier products only\')" />');
    $html = $lookup.$checkbox;
    foreach (['role="combobox"', 'role="listbox"', 'dir="auto"', 'data-context="purchasing"', 'data-supplier-id="7"', 'data-camera-trigger', 'data-product-search', 'data-product-id', 'ps-3', 'pe-12', 'text-start', '[unicode-bidi:plaintext]', 'w-11', 'end-px', 'border-s', ...$needles] as $needle) {
        $assert(str_contains($html, $needle), "Rendered {$locale} purchasing lookup omitted {$needle}.");
    }
    if ($locale === 'ar') {
        $assert(! str_contains($html, '>Use supplier products only<'), 'Arabic purchasing lookup leaked its English checkbox label.');
    }
}

$formVariables = [
    'fullPage' => true,
    'suppliers' => collect(),
    'stores' => collect(),
    'products' => collect(),
    'orderForm' => ['supplier_id' => '', 'store_id' => '', 'order_date' => '2026-09-10', 'expected_delivery_date' => '', 'payment_terms' => '', 'notes' => ''],
    'supplierProductsOnly' => true,
    'lineItems' => [['product_id' => '', 'quantity_ordered' => 1, 'unit_cost' => '', 'price_source' => 'none', 'price_date' => '', 'price_currency' => '']],
    'formSubtotal' => 0,
];
foreach (['en' => ['Save Draft', 'Use supplier products only'], 'ar' => ['حفظ المسودة', 'استخدام منتجات المورد فقط']] as $locale => $labels) {
    $app->setLocale($locale);
    $fullPageForm = Blade::render("@include('purchasing.partials.order-form')", $formVariables);
    foreach (['data-product-line-editor', 'wire:submit.prevent="saveOrder"', 'data-product-line', 'data-product-search', 'data-camera-trigger', ...$labels] as $needle) {
        $assert(str_contains($fullPageForm, $needle), "Rendered {$locale} full-page purchase-order form omitted {$needle}.");
    }
    $assert(! str_contains($fullPageForm, "\$set('showFormModal', false)"), "Rendered {$locale} full-page form retained a modal-only close action.");
}

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['supplier_only', 'BarcodeDetector', 'product-selected', 'product-lookup-empty', 'aria-activedescendant'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled purchasing lookup omitted {$needle}.");
}

foreach (['purchasing.orders', 'purchasing.orders.create'] as $routeName) {
    $assert(Route::has($routeName), "Purchase-order route {$routeName} is missing.");
}
$overviewRoute = Route::getRoutes()->getByName('purchasing.orders');
$createRoute = Route::getRoutes()->getByName('purchasing.orders.create');
$assert($createRoute?->uri() === 'purchasing/orders/create', 'Dedicated purchase-order create URI is incorrect.');
$assert($createRoute?->getActionName() === $overviewRoute?->getActionName(), 'Create and overview routes do not reuse the same Livewire implementation.');
$assert(in_array('can:purchase_orders.create', $createRoute?->gatherMiddleware() ?? [], true), 'Create route does not enforce purchase_orders.create.');
$navigation = collect(config('navigation'))->firstWhere('key', 'purchasing')['items'] ?? [];
$createItem = collect($navigation)->firstWhere('route', 'purchasing.orders.create');
$assert(($createItem['label']['ar'] ?? null) === 'إضافة أمر شراء' && ($createItem['label']['en'] ?? null) === 'Add Purchase Order', 'Purchase-order create navigation item is missing or incorrectly localized.');
$createIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders.create');
$overviewIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders');
$assert($createIndex === $overviewIndex + 1 && ($createItem['permission'] ?? null) === 'purchase_orders.create', 'Purchase-order create navigation placement or permission is incorrect.');
$ordersSource = (string) file_get_contents($root.'/resources/views/purchasing/orders.blade.php');
$assert(str_contains($ordersSource, "route('purchasing.orders.create')") && ! str_contains($ordersSource, 'wire:click="openCreateModal"'), 'Purchase-order create action does not navigate to the dedicated route.');
$assert(str_contains($ordersSource, "@if (\$createPage)") && str_contains($ordersSource, "purchasing.partials.order-form"), 'Dedicated full-page purchase-order form contract is missing.');

echo "HOTFIX27_PURCHASING_LOOKUP_RENDER_EN_AR=PASS\n";
echo "HOTFIX27_PURCHASE_ORDER_FULL_PAGE_ROUTE=PASS\n";
echo "HOTFIX27_PURCHASE_ORDER_NAVIGATION=PASS\n";
echo 'HOTFIX27_COMPILED_PURCHASING_ASSET=PASS sha256='.hash_file('sha256', $root.'/public/build/'.$asset).PHP_EOL;
