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

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix26', 'Hotfix26 version contract is not active.');
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
    foreach (['role="combobox"', 'role="listbox"', 'data-context="purchasing"', 'data-supplier-id="7"', 'data-camera-trigger', 'data-product-search', 'data-product-id', ...$needles] as $needle) {
        $assert(str_contains($html, $needle), "Rendered {$locale} purchasing lookup omitted {$needle}.");
    }
    if ($locale === 'ar') {
        $assert(! str_contains($html, '>Use supplier products only<'), 'Arabic purchasing lookup leaked its English checkbox label.');
    }
}

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['supplier_only', 'BarcodeDetector', 'product-selected', 'product-lookup-empty', 'aria-activedescendant'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled purchasing lookup omitted {$needle}.");
}

echo "HOTFIX26_PURCHASING_LOOKUP_RENDER_EN_AR=PASS\n";
echo "HOTFIX26_TRANSACTION_SEARCH_ROUTE=PASS\n";
echo 'HOTFIX26_COMPILED_PURCHASING_ASSET=PASS sha256='.hash_file('sha256', $root.'/public/build/'.$asset).PHP_EOL;
