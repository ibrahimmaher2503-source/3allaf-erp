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

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '--fixture';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$warningHandler = static function (int $severity, string $message, string $file, int $line): bool {
    if (! in_array($severity, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_STRICT], true)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
};
if ($mode === '--activation') {
    set_error_handler($warningHandler);
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if ($mode === '--fixture') {
    set_error_handler($warningHandler);
}

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix29', 'Hotfix29 version contract is not active.');
$assert(is_dir($root.'/bootstrap/cache') && is_writable($root.'/bootstrap/cache'), 'Candidate bootstrap/cache is absent or not writable.');

$overviewRoute = Route::getRoutes()->getByName('purchasing.orders');
$createRoute = Route::getRoutes()->getByName('purchasing.orders.create');
$searchRoute = Route::getRoutes()->getByName('transaction-products.search');
$assert($overviewRoute?->uri() === 'purchasing/orders', 'Purchase-order overview route is missing.');
$assert($createRoute?->uri() === 'purchasing/orders/create', 'Purchase-order create route is missing.');
$assert($createRoute?->getActionName() === $overviewRoute?->getActionName(), 'Purchase-order routes do not share the Livewire implementation.');
$assert(in_array('can:purchase_orders.view', $overviewRoute?->gatherMiddleware() ?? [], true), 'Overview authorization is missing.');
$assert(in_array('can:purchase_orders.create', $createRoute?->gatherMiddleware() ?? [], true), 'Create authorization is missing.');
$assert($searchRoute !== null, 'Product-search route is missing.');

$navigation = collect(config('navigation'))->firstWhere('key', 'purchasing')['items'] ?? [];
$overviewIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders');
$createIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders.create');
$assert($overviewIndex !== false && $createIndex === $overviewIndex + 1, 'Create navigation placement is incorrect.');
$assert(($navigation[$createIndex]['permission'] ?? null) === 'purchase_orders.create', 'Create navigation authorization is incorrect.');

foreach (['en' => ['ltr', 'text-left'], 'ar' => ['rtl', 'text-right']] as $locale => [$direction, $alignment]) {
    $app->setLocale($locale);
    $lookup = Blade::render('<x-product-line-lookup name="lines[0][product_id]" :purchasing="true" supplier-id="7" :supplier-only="true" />');
    foreach (["dir=\"{$direction}\"", $alignment, 'data-product-search', 'data-camera-trigger', 'role="listbox"', 'flex items-stretch gap-1.5'] as $needle) {
        $assert(str_contains($lookup, $needle), "Rendered {$locale} lookup omitted {$needle}.");
    }
    $assert(! preg_match('~<div class="relative min-w-0 flex-1">\s*<input[^>]+data-product-search[^>]*>\s*<button[^>]+data-camera-trigger~s', $lookup), "Rendered {$locale} camera control remains inside the input container.");
}

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['supplier_only', 'BarcodeDetector', 'exact_unique', 'productLookupReady', 'requestSequence'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled product lookup omitted {$needle}.");
}

echo "HOTFIX29_STATIC_ROUTE_LAYOUT_ASSET=PASS\n";
if ($mode === '--fixture') {
    echo "HOTFIX29_RUNTIME_MODE=FIXTURE_ONLY\n";
    exit(0);
}
$assert($mode === '--activation', 'Expected --fixture or --activation.');

config(['cache.default' => 'array', 'session.driver' => 'array', 'session.lottery' => [0, 100]]);
app('cache')->forgetDriver();
app('session')->forgetDrivers();

$connection = DB::connection();
$startingTransactionLevel = $connection->transactionLevel();
$connection->beginTransaction();

try {
    $runtimeUser = null;
    $runtimeStore = null;
    foreach (User::query()->where('status', 'active')->whereNotNull('email_verified_at')->orderByDesc('is_super_admin')->orderBy('id')->limit(50)->get() as $candidate) {
        Auth::setUser($candidate);
        if (! $candidate->can('purchase_orders.view') || ! $candidate->can('purchase_orders.create')) {
            continue;
        }
        $store = Store::query()->visibleTo($candidate)->where('status', 'active')->orderBy('id')->first();
        if ($store !== null) {
            $runtimeUser = $candidate;
            $runtimeStore = $store;
            break;
        }
    }
    $assert($runtimeUser instanceof User, 'No active verified user can view and create purchase orders.');
    $assert($runtimeStore instanceof Store, 'No authorized active store exists for the runtime user.');
    $assert(app(WorkContext::class)->stores($runtimeUser)->contains('id', $runtimeStore->id), 'Runtime store is outside the user scope.');

    $sessionManager = app('session');
    $encrypter = app('encrypter');
    $httpKernel = $app->make(HttpKernel::class);
    $requestRoute = static function (string $uri, string $locale, string $accept = 'text/html,application/xhtml+xml') use ($app, $encrypter, $httpKernel, $runtimeStore, $runtimeUser, $sessionManager) {
        $session = $sessionManager->driver();
        $session->setId(bin2hex(random_bytes(20)));
        $session->start();
        $session->put('locale', $locale);
        $session->put(WorkContext::SESSION_KEY, $runtimeStore->id);
        $session->save();
        $cookieName = $session->getName();
        $cookieValue = $encrypter->encrypt(CookieValuePrefix::create($cookieName, $encrypter->getKey()).$session->getId(), false);
        $request = Request::create($uri, 'GET', [], [$cookieName => $cookieValue], [], [
            'HTTP_ACCEPT' => $accept,
            'HTTP_X_REQUESTED_WITH' => $accept === 'application/json' ? 'XMLHttpRequest' : '',
            'HTTP_X_HOTFIX_RUNTIME_VERIFY' => '1',
        ]);
        $request->setUserResolver(static fn (): User => $runtimeUser);
        Auth::setUser($runtimeUser);
        $app->instance('request', $request);
        $response = $httpKernel->handle($request);
        try {
            return [$response->getStatusCode(), (string) $response->getContent()];
        } finally {
            $httpKernel->terminate($request, $response);
        }
    };

    foreach (['en', 'ar'] as $locale) {
        [$overviewStatus, $overview] = $requestRoute('/purchasing/orders', $locale);
        $assert($overviewStatus === 200, "{$locale} overview returned HTTP {$overviewStatus}.");
        $assert(substr_count($overview, 'data-guide="po-header"') === 1, "{$locale} overview has an invalid permanent root.");
        $assert(preg_match('~href="[^"]*/purchasing/orders/create"~', $overview) === 1, "{$locale} overview omitted the create-page target.");
        $assert(! str_contains($overview, 'openCreateModal'), "{$locale} overview retained the legacy modal trigger.");

        [$createStatus, $create] = $requestRoute('/purchasing/orders/create', $locale);
        $assert($createStatus === 200, "{$locale} create returned HTTP {$createStatus}.");
        $assert(substr_count($create, 'data-guide="po-header"') === 1, "{$locale} create has an invalid permanent root.");
        foreach (['data-guide="po-create-page"', 'data-product-line-editor', 'data-product-search', 'data-camera-trigger', 'data-order-lines-scroll'] as $needle) {
            $assert(str_contains($create, $needle), "{$locale} create omitted {$needle}.");
        }
        $assert(str_contains($create, $locale === 'ar' ? 'dir="rtl"' : 'dir="ltr"'), "{$locale} create lookup direction is incorrect.");
        echo 'HOTFIX29_AUTHENTICATED_PURCHASE_ORDERS_'.strtoupper($locale)."=PASS status=200 store={$runtimeStore->id}\n";
        echo 'HOTFIX29_AUTHENTICATED_PURCHASE_ORDER_CREATE_'.strtoupper($locale)."=PASS status=200 store={$runtimeStore->id}\n";
    }

    $association = ProductSupplier::query()
        ->whereHas('supplier', fn ($query) => $query->active())
        ->whereHas('product', fn ($query) => $query->sellable())
        ->with(['supplier', 'product.barcodes'])
        ->orderBy('id')
        ->first();
    $assert($association instanceof ProductSupplier, 'No existing supplier-product association is available for lookup verification.');
    $identifier = collect([
        $association->supplier_item_code,
        $association->product->item_code,
        $association->product->model_number,
        $association->product->barcodes->first()?->barcode,
        $association->product->name_ar,
        $association->product->name_en,
    ])->filter(fn ($value) => mb_strlen(trim((string) $value)) >= 2)->first();
    $assert(is_string($identifier), 'No searchable identifier exists for the runtime product.');
    $query = mb_substr(trim($identifier), 0, min(4, mb_strlen(trim($identifier))));
    $searchUri = '/transaction-products/search?'.http_build_query([
        'q' => $query,
        'context' => 'purchasing',
        'supplier_id' => $association->supplier_id,
        'supplier_only' => '1',
    ]);
    [$searchStatus, $searchJson] = $requestRoute($searchUri, 'ar', 'application/json');
    $assert($searchStatus === 200, "Runtime product search returned HTTP {$searchStatus}.");
    $payload = json_decode($searchJson, true, 512, JSON_THROW_ON_ERROR);
    $matches = collect($payload['data'] ?? []);
    $assert($matches->contains(fn (array $item): bool => (int) ($item['id'] ?? 0) === (int) $association->product_id), 'Runtime partial lookup omitted the authorized supplier product.');
    echo "HOTFIX29_AUTHENTICATED_PRODUCT_SEARCH=PASS status=200 query_length=".mb_strlen($query)." matches={$matches->count()}\n";
} finally {
    while ($connection->transactionLevel() > $startingTransactionLevel) {
        $connection->rollBack();
    }
}

echo "HOTFIX29_DATABASE_MUTATION=NONE transaction=rolled_back\n";
