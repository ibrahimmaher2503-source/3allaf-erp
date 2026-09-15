<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\WorkContext;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '--fixture';
$node = '/opt/cpanel/ea-nodejs20/bin/node';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if ($mode === '--fixture') {
    $loader = new FileLoader($app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']]);
    $app->instance('translation.loader', $loader);
    $app->instance('translator', new Translator($loader, 'en'));
    \Illuminate\Support\Facades\Facade::clearResolvedInstance('translator');
}

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix31', 'Hotfix31 version contract is not active.');
foreach ([
    'purchasing.invoices' => 'purchasing/invoices',
    'pricing.lists' => 'pricing/lists/manage',
    'purchasing.orders' => 'purchasing/orders',
    'purchasing.orders.create' => 'purchasing/orders/create',
] as $name => $uri) {
    $assert(Route::getRoutes()->getByName($name)?->uri() === $uri, "Route {$name} is missing.");
}

$invoiceSource = (string) file_get_contents($root.'/resources/views/purchasing/invoices.blade.php');
$pricingSource = (string) file_get_contents($root.'/resources/views/pricing/lists.blade.php');
foreach (['data-purchase-invoice-editor', 'data-invoice-metadata', 'data-invoice-lines-scroll', 'sticky top-0', 'data-editor-total', 'selectInvoiceProduct', 'wire:submit.prevent="saveInvoice"'] as $needle) {
    $assert(str_contains($invoiceSource, $needle), "Invoice editor omitted {$needle}.");
}
$assert(strpos($invoiceSource, 'data-invoice-lines-scroll') < strpos($invoiceSource, 'wire:model="invoiceForm.notes"'), 'Notes are not after invoice lines.');
foreach (['data-compact-filter-row', 'sm:grid-cols-[minmax(16rem,1fr)_10rem]', 'data-price-lists-table', 'size="sm"'] as $needle) {
    $assert(str_contains($pricingSource, $needle), "Pricing filter geometry omitted {$needle}.");
}

$lookups = [];
foreach (['en' => ['ltr', 'text-left'], 'ar' => ['rtl', 'text-right']] as $locale => [$direction, $alignment]) {
    $app->setLocale($locale);
    $lookup = Blade::render('<x-product-line-lookup wire:model="lineItems.0.product_id" :purchasing="true" supplier-id="7" :supplier-only="true" currency-code="EGP" />');
    foreach (["dir=\"{$direction}\"", $alignment, 'data-product-search', 'data-camera-trigger', 'data-message-loading=', 'data-message-error=', 'flex items-stretch gap-1.5'] as $needle) {
        $assert(str_contains($lookup, $needle), "Rendered {$locale} lookup omitted {$needle}.");
    }
    $assert(! preg_match('~<div class="relative min-w-0 flex-1">\s*<input[^>]+data-product-search[^>]*>\s*<button[^>]+data-camera-trigger~s', $lookup), "{$locale} camera button is inside the input.");
    $lookups[$locale] = $lookup;
}
$assert(str_contains($lookups['ar'], 'استخدام منتجات المورد فقط') === false, 'Supplier-only label belongs to the editor control, not the lookup input.');
$assert((json_decode((string) file_get_contents($root.'/lang/ar.json'), true, 512, JSON_THROW_ON_ERROR)['Use supplier products only'] ?? '') === 'استخدام منتجات المورد فقط', 'Exact Arabic supplier-only label is missing.');

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['supplier_only', 'exact_unique', 'productResultsOverlay', 'requestSequence', 'data-editor-total', 'data-compact-filter-row', 'morph.updated'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled browser runtime omitted {$needle}.");
}

$runBrowser = static function (string $lookup, string $supplierId) use ($assert, $asset, $node, $root): void {
    $assert(is_executable($node), 'Node 20 is unavailable.');
    $htmlFile = tempnam(sys_get_temp_dir(), 'hotfix31-browser-');
    $assert(is_string($htmlFile), 'Could not allocate browser fixture.');
    try {
        $html = '<main data-purchase-invoice-editor><form data-product-line-editor><div data-invoice-lines-scroll><div data-product-line>'.$lookup.'</div></div></form></main>';
        $assert(file_put_contents($htmlFile, $html) !== false, 'Could not write browser fixture.');
        $output = []; $status = 1;
        exec(escapeshellarg($node).' '.escapeshellarg($root.'/.ai/evidence/v0.1.22-hotfix30/browser-search-contract.mjs').' '.escapeshellarg($root.'/public/build/'.$asset).' '.escapeshellarg($htmlFile).' '.escapeshellarg($supplierId).' 2>&1', $output, $status);
        $result = implode("\n", $output);
        $assert($status === 0, "Compiled browser contract failed: {$result}");
        foreach (['HOTFIX30_COMPILED_BROWSER_ARABIC_AND_IDENTIFIER_INPUT=PASS', 'HOTFIX30_LOADING_RESULTS_EMPTY_ERROR_STATES=PASS', 'HOTFIX30_MULTI_RESULT_KEYBOARD_ROW_STATE=PASS', 'HOTFIX31_INVOICE_LINE_COMMIT_NEXT_FOCUS=PASS', 'HOTFIX31_REPEATED_ENTER_PARENT_SUBMIT_PREVENTION=PASS'] as $proof) {
            $assert(str_contains($result, $proof), "Browser contract omitted {$proof}.");
        }
        echo $result."\n";
    } finally {
        if (is_file($htmlFile)) unlink($htmlFile);
    }
};

echo "HOTFIX31_STATIC_MARKUP_ASSET=PASS\n";
if ($mode === '--fixture') {
    $runBrowser($lookups['ar'], '7');
    echo "HOTFIX31_RUNTIME_MODE=FIXTURE_ONLY\n";
    exit(0);
}
$assert($mode === '--activation', 'Expected --fixture or --activation.');

config(['cache.default' => 'array', 'session.driver' => 'array', 'session.lottery' => [0, 100]]);
app('cache')->forgetDriver(); app('session')->forgetDrivers();
$connection = DB::connection(); $level = $connection->transactionLevel(); $connection->beginTransaction();
try {
    $runtimeUser = null; $runtimeStore = null;
    foreach (User::query()->where('status', 'active')->whereNotNull('email_verified_at')->orderByDesc('is_super_admin')->orderBy('id')->limit(50)->get() as $candidate) {
        Auth::setUser($candidate);
        if (! $candidate->can('purchase_invoices_supplier_returns.view') || ! $candidate->can('pricing_lists.view') || ! $candidate->can('purchase_orders.view') || ! $candidate->can('purchase_orders.create')) continue;
        $store = Store::query()->visibleTo($candidate)->where('status', 'active')->orderBy('id')->first();
        if ($store) { $runtimeUser = $candidate; $runtimeStore = $store; break; }
    }
    $assert($runtimeUser instanceof User && $runtimeStore instanceof Store, 'No authorized runtime user/store exists.');
    $sessionManager = app('session'); $encrypter = app('encrypter'); $kernel = $app->make(HttpKernel::class);
    $requestRoute = static function (string $uri, string $locale, string $accept = 'text/html,application/xhtml+xml') use ($app, $encrypter, $kernel, $runtimeStore, $runtimeUser, $sessionManager) {
        $session = $sessionManager->driver(); $session->setId(bin2hex(random_bytes(20))); $session->start();
        $session->put('locale', $locale); $session->put(WorkContext::SESSION_KEY, $runtimeStore->id); $session->save();
        $name = $session->getName(); $value = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(), false);
        $request = Request::create($uri, 'GET', [], [$name => $value], [], ['HTTP_ACCEPT' => $accept, 'HTTP_X_REQUESTED_WITH' => $accept === 'application/json' ? 'XMLHttpRequest' : '']);
        $request->setUserResolver(static fn (): User => $runtimeUser); Auth::setUser($runtimeUser); $app->instance('request', $request);
        $response = $kernel->handle($request);
        try { return [$response->getStatusCode(), (string) $response->getContent()]; } finally { $kernel->terminate($request, $response); }
    };
    foreach (['ar', 'en'] as $locale) foreach (['/purchasing/invoices', '/pricing/lists/manage', '/purchasing/orders', '/purchasing/orders/create'] as $uri) {
        [$status] = $requestRoute($uri, $locale); $assert($status === 200, "{$locale} {$uri} returned HTTP {$status}.");
        echo 'HOTFIX31_AUTHENTICATED_'.strtoupper($locale).'_'.strtoupper(str_replace(['/', '-'], ['_', '_'], trim($uri, '/')))."=PASS status=200\n";
    }
    $association = ProductSupplier::query()->whereHas('supplier', fn ($q) => $q->active())->whereHas('product', fn ($q) => $q->sellable())->with(['product.barcodes'])->orderBy('id')->first();
    $assert($association instanceof ProductSupplier, 'No supplier-product association exists for runtime verification.');
    foreach (['منتج', '001'] as $query) {
        [$status, $json] = $requestRoute('/transaction-products/search?'.http_build_query(['q' => $query, 'context' => 'purchasing', 'supplier_id' => $association->supplier_id, 'supplier_only' => '1']), 'ar', 'application/json');
        $assert($status === 200 && is_array(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['data'] ?? null), "Runtime search failed for {$query}.");
    }
    $identifier = collect([$association->supplier_item_code, $association->product->item_code, $association->product->model_number, $association->product->barcodes->first()?->barcode, $association->product->name_ar, $association->product->name_en])
        ->filter(fn ($value) => mb_strlen(trim((string) $value)) >= 2)->first();
    $assert(is_string($identifier), 'No searchable runtime product identifier exists.');
    $query = mb_substr(trim($identifier), 0, min(4, mb_strlen(trim($identifier))));
    [$status, $json] = $requestRoute('/transaction-products/search?'.http_build_query(['q' => $query, 'context' => 'purchasing', 'supplier_id' => $association->supplier_id, 'supplier_only' => '1']), 'ar', 'application/json');
    $matches = collect(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['data'] ?? []);
    $assert($status === 200 && $matches->contains(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $association->product_id), 'Runtime partial search omitted its supplier product.');
    echo "HOTFIX31_AUTHENTICATED_PRODUCT_SEARCH=PASS matches={$matches->count()}\n";
    $runBrowser($lookups['ar'], (string) $association->supplier_id);
} finally {
    while ($connection->transactionLevel() > $level) $connection->rollBack();
}
echo "HOTFIX31_DATABASE_MUTATION=NONE transaction=rolled_back\n";
