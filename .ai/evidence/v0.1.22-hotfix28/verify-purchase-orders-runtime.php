<?php

declare(strict_types=1);

use App\Http\Controllers\TransactionProductSearchController;
use App\Models\User;
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

$failOnWarning = static function (int $severity, string $message, string $file, int $line): bool {
    if (! in_array($severity, [E_WARNING, E_USER_WARNING, E_NOTICE, E_USER_NOTICE, E_STRICT], true)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
};
if ($mode === '--activation') {
    set_error_handler($failOnWarning);
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if ($mode === '--fixture') {
    set_error_handler($failOnWarning);
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assert(ApplicationVersion::RELEASE === '0.1.22-hotfix28', 'Hotfix28 version contract is not active.');
$assert(is_dir($root.'/bootstrap/cache') && is_writable($root.'/bootstrap/cache'), 'Candidate bootstrap/cache is absent or not writable.');

$overviewRoute = Route::getRoutes()->getByName('purchasing.orders');
$createRoute = Route::getRoutes()->getByName('purchasing.orders.create');
$searchRoute = Route::getRoutes()->getByName('transaction-products.search');
$assert($overviewRoute?->uri() === 'purchasing/orders', 'Purchase-order overview route is missing.');
$assert($createRoute?->uri() === 'purchasing/orders/create', 'Purchase-order create route is missing.');
$assert($createRoute?->getActionName() === $overviewRoute?->getActionName(), 'Purchase-order routes do not reuse the same Livewire component.');
$assert(in_array('can:purchase_orders.view', $overviewRoute?->gatherMiddleware() ?? [], true), 'Overview route authorization is missing.');
$assert(in_array('can:purchase_orders.create', $createRoute?->gatherMiddleware() ?? [], true), 'Create route authorization is missing.');
$assert($searchRoute?->getActionName() === TransactionProductSearchController::class, 'Authorized product-search route is missing.');

$navigation = collect(config('navigation'))->firstWhere('key', 'purchasing')['items'] ?? [];
$overviewIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders');
$createIndex = collect($navigation)->search(fn (array $item): bool => ($item['route'] ?? null) === 'purchasing.orders.create');
$createItem = $createIndex === false ? null : $navigation[$createIndex];
$assert($overviewIndex !== false && $createIndex === $overviewIndex + 1, 'Create navigation item is not directly below overview.');
$assert(($createItem['permission'] ?? null) === 'purchase_orders.create', 'Create navigation permission is incorrect.');
$assert(($createItem['label']['ar'] ?? null) === 'إضافة أمر شراء' && ($createItem['label']['en'] ?? null) === 'Add Purchase Order', 'Create navigation localization is incorrect.');

$ordersSource = (string) file_get_contents($root.'/resources/views/purchasing/orders.blade.php');
$assert(! str_contains($ordersSource, 'wire:click="openCreateModal"'), 'Legacy create-modal trigger remains.');
$assert(str_contains($ordersSource, "route('purchasing.orders.create')"), 'Toolbar does not target the create route.');

$manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$asset = $manifest['resources/js/app.js']['file'] ?? null;
$assert(is_string($asset) && is_file($root.'/public/build/'.$asset), 'Compiled application JavaScript is missing.');
$compiled = (string) file_get_contents($root.'/public/build/'.$asset);
foreach (['supplier_only', 'BarcodeDetector', 'product-selected', 'aria-activedescendant', 'exact_unique'] as $needle) {
    $assert(str_contains($compiled, $needle), "Compiled product lookup omitted {$needle}.");
}

foreach (['en', 'ar'] as $locale) {
    $app->setLocale($locale);
    $lookup = Blade::render('<x-product-line-lookup name="lines[0][product_id]" :purchasing="true" supplier-id="7" :supplier-only="true" />');
    foreach (['role="combobox"', 'role="listbox"', 'dir="auto"', 'data-camera-trigger', 'ps-3', 'pe-12', 'w-11', 'border-s'] as $needle) {
        $assert(str_contains($lookup, $needle), "Rendered {$locale} lookup omitted {$needle}.");
    }
}

echo "HOTFIX28_STATIC_ROUTE_NAVIGATION_LOOKUP=PASS\n";

if ($mode === '--fixture') {
    echo "HOTFIX28_RUNTIME_MODE=FIXTURE_ONLY\n";
    exit(0);
}
$assert($mode === '--activation', 'Expected --fixture or --activation.');

config([
    'cache.default' => 'array',
    'session.driver' => 'array',
    'session.lottery' => [0, 100],
]);
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
    $assert(app(WorkContext::class)->stores($runtimeUser)->contains('id', $runtimeStore->id), 'Selected runtime store is outside the user scope.');

    $sessionManager = app('session');
    $encrypter = app('encrypter');
    $httpKernel = $app->make(HttpKernel::class);
    $renderRoute = static function (string $uri, string $locale) use ($app, $assert, $encrypter, $httpKernel, $runtimeStore, $runtimeUser, $sessionManager): string {
        $session = $sessionManager->driver();
        $session->setId(bin2hex(random_bytes(20)));
        $session->start();
        $session->put('locale', $locale);
        $session->put(WorkContext::SESSION_KEY, $runtimeStore->id);
        $session->save();

        $cookieName = $session->getName();
        $cookieValue = $encrypter->encrypt(
            CookieValuePrefix::create($cookieName, $encrypter->getKey()).$session->getId(),
            false,
        );
        $request = Request::create($uri, 'GET', [], [$cookieName => $cookieValue], [], [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'HTTP_X_HOTFIX_RUNTIME_VERIFY' => '1',
        ]);
        $request->setUserResolver(static fn (): User => $runtimeUser);
        Auth::setUser($runtimeUser);
        $app->instance('request', $request);

        $response = $httpKernel->handle($request);
        try {
            $assert($response->getStatusCode() === 200, "{$locale} {$uri} returned HTTP {$response->getStatusCode()}.");
            $content = $response->getContent();
            $assert(is_string($content) && $content !== '', "{$locale} {$uri} returned empty HTML.");
            $assert(substr_count($content, 'data-guide="po-header"') === 1, "{$locale} {$uri} does not have one permanent purchase-order root.");
            $assert(! str_contains($content, 'Undefined variable'), "{$locale} {$uri} contains a missing-variable failure.");

            return $content;
        } finally {
            $httpKernel->terminate($request, $response);
        }
    };

    foreach (['en', 'ar'] as $locale) {
        $overview = $renderRoute('/purchasing/orders', $locale);
        $assert(str_contains($overview, 'data-guide="po-create-action"'), "{$locale} overview omitted the create toolbar action.");
        $assert(preg_match('~href="[^"]*/purchasing/orders/create"~', $overview) === 1, "{$locale} overview toolbar/sidebar omitted the create URL.");
        $assert(str_contains($overview, $locale === 'ar' ? 'إضافة أمر شراء' : 'Add Purchase Order'), "{$locale} overview omitted the authorized sidebar create label.");
        $assert(! str_contains($overview, 'openCreateModal'), "{$locale} overview retained the legacy modal trigger.");

        $create = $renderRoute('/purchasing/orders/create', $locale);
        foreach (['data-guide="po-create-page"', 'data-product-line-editor', 'data-product-search', 'data-camera-trigger'] as $needle) {
            $assert(str_contains($create, $needle), "{$locale} create page omitted {$needle}.");
        }
        $assert(str_contains($create, $locale === 'ar' ? 'استخدام منتجات المورد فقط' : 'Use supplier products only'), "{$locale} create page omitted supplier filtering.");
        $assert(! str_contains($create, 'openCreateModal'), "{$locale} create page retained the legacy modal trigger.");

        echo 'HOTFIX28_AUTHENTICATED_PURCHASE_ORDERS_'.strtoupper($locale)."=PASS status=200 store={$runtimeStore->id}\n";
        echo 'HOTFIX28_AUTHENTICATED_PURCHASE_ORDER_CREATE_'.strtoupper($locale)."=PASS status=200 store={$runtimeStore->id}\n";
    }
} finally {
    while ($connection->transactionLevel() > $startingTransactionLevel) {
        $connection->rollBack();
    }
}

echo "HOTFIX28_DATABASE_MUTATION=NONE transaction=rolled_back\n";
