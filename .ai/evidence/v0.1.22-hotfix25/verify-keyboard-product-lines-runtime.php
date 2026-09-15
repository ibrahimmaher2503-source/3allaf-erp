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

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix25', 'Hotfix25 version contract is not active.');
$route = Route::getRoutes()->getByName('transaction-products.search');
$assert($route !== null && $route->getActionName() === TransactionProductSearchController::class, 'Authorized transaction product-search route is missing.');

foreach (['en' => ['Product', 'Scan barcode or search product'], 'ar' => ['منتج', 'امسح الباركود أو ابحث عن المنتج']] as $locale => $needles) {
    $app->setLocale($locale);
    $html = Blade::render('<x-product-line-lookup name="lines[0][product_id]" :required="true" />');
    foreach (['role="combobox"', 'role="listbox"', 'data-product-search', 'data-product-id', ...$needles] as $needle) {
        $assert(str_contains($html, $needle), "Rendered {$locale} product lookup omitted {$needle}.");
    }
}

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['product-selected', 'data-product-line-editor', 'aria-activedescendant', 'pos-product-search-focus'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled keyboard workflow omitted {$needle}.");
}

echo "HOTFIX25_PRODUCT_LOOKUP_RENDER_EN_AR=PASS\n";
echo "HOTFIX25_TRANSACTION_SEARCH_ROUTE=PASS\n";
echo 'HOTFIX25_COMPILED_KEYBOARD_ASSET=PASS sha256='.hash_file('sha256', $root.'/public/build/'.$asset).PHP_EOL;
