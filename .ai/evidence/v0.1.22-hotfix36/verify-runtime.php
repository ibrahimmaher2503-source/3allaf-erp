<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Http\Controllers\LocaleController;
use App\Models\User;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuditLogPresentation;
use App\Modules\Platform\Support\WorkContext;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store as SessionStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? '--fixture';
$node = getenv('HOTFIX36_NODE') ?: '/opt/cpanel/ea-nodejs20/bin/node';
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

try {
    if ($mode === '--force-failure') {
        throw new RuntimeException('Forced verifier failure for isolated fail-closed testing.');
    }
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();
    if ($mode === '--fixture') {
        $loader = new FileLoader($app['files'], [$root.'/vendor/laravel/framework/src/Illuminate/Translation/lang', $root.'/lang']);
        $translator = new Translator($loader, 'en');
        $translator->determineLocalesUsing(static function (array $locales): array {
            $locale = (string) ($locales[0] ?? config('app.locale', 'en'));
            return config('app.locale_fallbacks.'.$locale, array_values(array_unique($locales)));
        });
        $app->instance('translation.loader', $loader);
        $app->instance('translator', $translator);
        Illuminate\Support\Facades\Facade::clearResolvedInstance('translator');
    }

    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix36', 'Hotfix36 version contract is not active.');
    $assert($app->routesAreCached(), 'Laravel routes are not cached for the packaged-candidate runtime proof.');
    $assert(config('app.supported_locales') === ['ar', 'en', 'ar-EG'], 'Supported locale order is incorrect.');
    $assert(config('app.locale_names.ar-EG') === 'العربية المصرية', 'Egyptian Arabic display name is incorrect.');
    $assert(config('app.locale_fallbacks.ar-EG') === ['ar-EG', 'ar', 'en'], 'Egyptian Arabic fallback order is incorrect.');
    $assert(in_array('ar-EG', config('app.rtl_locales', []), true), 'Egyptian Arabic is not RTL.');

    $translator = app('translator');
    $translator->addLines(['hotfix36.ar_probe' => 'AR_FALLBACK'], 'ar');
    $translator->addLines(['hotfix36.en_probe' => 'EN_FALLBACK'], 'en');
    $app->setLocale('ar-EG');
    $assert(__('hotfix36.ar_probe') === 'AR_FALLBACK', 'ar-EG did not fall back to ar.');
    $assert(__('hotfix36.en_probe') === 'EN_FALLBACK', 'ar-EG did not fall back through ar to en.');

    $localeRoute = Route::getRoutes()->getByName('locale.switch');
    $assert($localeRoute?->uri() === 'locale' && in_array('POST', $localeRoute->methods(), true), 'Locale switch route is missing.');
    $assert($localeRoute->getActionName() === LocaleController::class, 'Locale switch is not dispatched by the invokable controller.');
    $assert(is_string($localeRoute->getAction('uses')) && ! str_contains($localeRoute->getAction('uses'), 'SerializableClosure'), 'Locale switch retained a serialized closure action.');
    $platformRoutes = (string) file_get_contents($root.'/routes/platform.php');
    $retailRoutes = (string) file_get_contents($root.'/routes/retail.php');
    $assert(! preg_match('/post\([\'\"]locale[\'\"]\s*,\s*function/', $platformRoutes), 'platform.php retained a locale-switching closure.');
    $assert(! str_contains($retailRoutes, 'locale.switch') && ! str_contains($retailRoutes, "post('locale'") && ! str_contains($retailRoutes, 'post("locale"'), 'retail.php contains a locale-switch route.');
    foreach (['dashboard', 'catalog.products', 'purchasing.orders', 'purchasing.invoices', 'pricing.lists', 'pos', 'reports.index'] as $routeName) {
        $assert(Route::getRoutes()->getByName($routeName) !== null, "Required route {$routeName} is missing.");
    }

    config(['session.driver' => 'array', 'session.lottery' => [0, 100], 'cache.default' => 'array']);
    app('cache')->forgetDriver();
    app('session')->forgetDrivers();
    $sessionManager = app('session');
    $encrypter = app('encrypter');
    $kernel = $app->make(HttpKernel::class);
    $sessionCookie = static function (SessionStore $session) use ($encrypter): string {
        return $encrypter->encrypt(CookieValuePrefix::create($session->getName(), $encrypter->getKey()).$session->getId(), false);
    };
    $handle = static function (string $uri, string $method, array $parameters, SessionStore $session, array $cookies = [], array $server = []) use ($app, $kernel, $sessionCookie): array {
        $cookies = [$session->getName() => $sessionCookie($session), ...$cookies];
        $request = Request::create($uri, $method, $parameters, $cookies, [], $server);
        $app->instance('request', $request);
        $response = $kernel->handle($request);
        try {
            return [$response, $request->session()];
        } finally {
            $kernel->terminate($request, $response);
        }
    };
    $newSession = static function (string $locale = 'en') use ($sessionManager): SessionStore {
        $session = $sessionManager->driver();
        $session->setId(bin2hex(random_bytes(20)));
        $session->start();
        $session->put('locale', $locale);
        $session->put('_token', bin2hex(random_bytes(20)));
        $session->save();

        return $session;
    };
    foreach (['ar', 'en', 'ar-EG'] as $preference) {
        $session = $newSession();
        $referer = 'http://localhost/login?from=locale';
        [$response, $handledSession] = $handle('/locale', 'POST', ['_token' => $session->token(), 'locale' => $preference], $session, ['locale' => 'en'], ['HTTP_ACCEPT' => 'text/html', 'HTTP_REFERER' => $referer]);
        $assert($response->getStatusCode() === 302 && $response->headers->get('Location') === $referer, "Locale {$preference} did not redirect back.");
        $assert($handledSession->get('locale') === $preference, "Locale {$preference} was not persisted in the session.");
        $localeCookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'locale');
        $assert($localeCookie?->getValue() === $preference, "Locale {$preference} was not persisted in the cookie.");
        $assert($localeCookie->getExpiresTime() >= time() + (364 * 24 * 60 * 60), "Locale {$preference} cookie is not valid for one year.");
        [$page] = $handle('/login', 'GET', [], $handledSession, ['locale' => $preference], ['HTTP_ACCEPT' => 'text/html']);
        $assert($page->getStatusCode() === 200, "Locale {$preference} login render did not return HTTP 200.");
        $direction = str_starts_with($preference, 'ar') ? 'rtl' : 'ltr';
        $html = (string) $page->getContent();
        $assert((bool) preg_match('/<html\s+lang="'.preg_quote($preference, '/').'"\s+dir="'.$direction.'"/s', $html), "Locale {$preference} login render has incorrect lang/dir.");
        $assert((bool) preg_match('/<option value="'.preg_quote($preference, '/').'" selected>/', $html), "Locale {$preference} was not retained by the switcher.");
    }
    foreach (['fr', '', ['ar-EG']] as $invalidLocale) {
        $session = $newSession('en');
        [$response, $handledSession] = $handle('/locale', 'POST', ['_token' => $session->token(), 'locale' => $invalidLocale], $session, ['locale' => 'en'], ['HTTP_ACCEPT' => 'text/html', 'HTTP_REFERER' => 'http://localhost/login']);
        $assert($response->getStatusCode() === 302, 'Malformed or unsupported locale was not rejected safely.');
        $assert($handledSession->get('locale') === 'en', 'Rejected locale changed the persisted session preference.');
        $assert(collect($response->headers->getCookies())->doesntContain(fn ($cookie) => $cookie->getName() === 'locale'), 'Rejected locale emitted a locale cookie.');
    }
    $middleware = new SetLocale();
    foreach (['ar', 'en', 'ar-EG'] as $preference) {
        $probeSession = new SessionStore('hotfix36-'.$preference, new ArraySessionHandler(120));
        $probeSession->start();
        $probeSession->put('locale', $preference);
        $probe = Request::create('/probe', 'GET', [], ['locale' => 'en']);
        $probe->setLaravelSession($probeSession);
        $middleware->handle($probe, static fn () => response('ok'));
        $assert($app->getLocale() === $preference, "Session locale {$preference} did not take precedence.");
    }

    $app->setLocale('ar-EG');
    $shell = view('layouts.auth.simple', ['slot' => new Illuminate\Support\HtmlString('<div data-hotfix36>تمام</div>')])->render();
    $assert((bool) preg_match('/<html\s+lang="ar-EG"\s+dir="rtl"/s', $shell), 'Egyptian Arabic shell omitted exact lang/RTL attributes.');
    $assert(str_contains($shell, 'العربية المصرية'), 'Egyptian Arabic shell omitted its display name.');
    $sidebarSource = (string) file_get_contents($root.'/resources/views/layouts/app/sidebar.blade.php')
        .(string) file_get_contents($root.'/resources/views/components/app-navigation.blade.php');
    foreach (['data-sidebar-scroll', 'بحث سريع في النظام', 'اكتب اسم الشاشة أو الوحدة'] as $needle) {
        $assert(str_contains($sidebarSource, $needle), "Application sidebar/command palette omitted {$needle}.");
    }
    $lookup = Blade::render('<x-product-line-lookup wire:model="lineItems.0.product_id" :purchasing="true" supplier-id="7" :supplier-only="true" currency-code="EGP" />');
    foreach (['dir="rtl"', 'text-right', 'data-product-search', 'data-camera-trigger'] as $needle) {
        $assert(str_contains($lookup, $needle), "Egyptian Arabic invoice lookup omitted {$needle}.");
    }

    $manifest = json_decode((string) file_get_contents($root.'/public/build/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $jsAsset = $manifest['resources/js/app.js']['file'] ?? null;
    $cssAsset = $manifest['resources/css/app.css']['file'] ?? null;
    $assert(is_string($jsAsset) && is_file($root.'/public/build/'.$jsAsset), 'Compiled application JavaScript is missing.');
    $assert(is_string($cssAsset) && is_file($root.'/public/build/'.$cssAsset), 'Compiled application CSS is missing.');
    $compiledJs = (string) file_get_contents($root.'/public/build/'.$jsAsset);
    $compiledCss = (string) file_get_contents($root.'/public/build/'.$cssAsset);
    foreach (['supplier_only', 'exact_unique', 'productResultsOverlay', 'requestSequence', 'data-editor-total', 'morph.updated'] as $needle) {
        $assert(str_contains($compiledJs, $needle), "Compiled browser runtime omitted {$needle}.");
    }
    $assert(str_contains($compiledJs, "startsWith('ar')") || str_contains($compiledJs, 'startsWith("ar")') || str_contains($compiledJs, 'startsWith(`ar`)'), 'Compiled JavaScript omitted Arabic-family handling.');
    $assert(str_contains($compiledCss, 'lang|=ar'), 'Compiled CSS omitted Arabic-family Cairo selector.');
    $assert(str_contains($compiledCss, 'Cairo Variable'), 'Compiled CSS omitted Cairo typography.');

    $runBrowser = static function (string $renderedLookup, string $supplierId) use ($assert, $jsAsset, $node, $root): void {
        $assert(is_executable($node), 'Node 20 is unavailable.');
        $htmlFile = tempnam(sys_get_temp_dir(), 'hotfix36-browser-');
        $assert(is_string($htmlFile), 'Could not allocate browser fixture.');
        try {
            $html = '<main data-purchase-invoice-editor><form data-product-line-editor><div data-invoice-lines-scroll><div data-product-line>'.$renderedLookup.'</div></div></form></main>';
            $assert(file_put_contents($htmlFile, $html) !== false, 'Could not write browser fixture.');
            $output = [];
            $status = 1;
            exec(escapeshellarg($node).' '.escapeshellarg($root.'/.ai/evidence/v0.1.22-hotfix30/browser-search-contract.mjs').' '.escapeshellarg($root.'/public/build/'.$jsAsset).' '.escapeshellarg($htmlFile).' '.escapeshellarg($supplierId).' 2>&1', $output, $status);
            $result = implode("\n", $output);
            $assert($status === 0, "Compiled browser contract failed: {$result}");
            foreach (['HOTFIX30_COMPILED_BROWSER_ARABIC_AND_IDENTIFIER_INPUT=PASS', 'HOTFIX30_MULTI_RESULT_KEYBOARD_ROW_STATE=PASS', 'HOTFIX31_INVOICE_LINE_COMMIT_NEXT_FOCUS=PASS', 'HOTFIX31_REPEATED_ENTER_PARENT_SUBMIT_PREVENTION=PASS'] as $proof) {
                $assert(str_contains($result, $proof), "Browser contract omitted {$proof}.");
            }
            echo $result."\n";
        } finally {
            if (is_file($htmlFile)) {
                unlink($htmlFile);
            }
        }
    };

    echo "HOTFIX36_ROUTE_CACHE_BOOT=PASS controller=invokable\n";
    echo "HOTFIX36_STATIC_LOCALE_SHELL_ASSET=PASS\n";
    if ($mode === '--fixture') {
        $runBrowser($lookup, '7');
        echo "HOTFIX36_LOCALE_SWITCH_SESSION_COOKIE_FALLBACK=PASS locales=ar,en,ar-EG invalid=rejected\n";
        echo "HOTFIX36_RUNTIME_MODE=FIXTURE_ONLY\n";
        echo "HOTFIX36_RUNTIME_VERIFICATION=PASS mode=fixture\n";
        exit(0);
    }

    $assert($mode === '--activation', 'Expected --fixture, --activation, or --force-failure.');
    config(['cache.default' => 'array', 'session.driver' => 'array', 'session.lottery' => [0, 100]]);
    app('cache')->forgetDriver();
    app('session')->forgetDrivers();
    $connection = DB::connection();
    $level = $connection->transactionLevel();
    $connection->beginTransaction();
    try {
        $runtimeUser = null;
        $runtimeStore = null;
        foreach (User::query()->where('status', 'active')->whereNotNull('email_verified_at')->orderByDesc('is_super_admin')->orderBy('id')->limit(100)->get() as $candidate) {
            Auth::setUser($candidate);
            $required = ['dashboard_reports.view', 'products_categories_brands.view', 'purchase_orders.view', 'purchase_invoices_supplier_returns.view', 'inventory_stock_card.view', 'pos_sales.view'];
            if (collect($required)->contains(fn (string $permission): bool => ! $candidate->can($permission))) {
                continue;
            }
            $store = Store::query()->visibleTo($candidate)->where('status', 'active')->orderBy('id')->first();
            if ($store) {
                $runtimeUser = $candidate;
                $runtimeStore = $store;
                break;
            }
        }
        $assert($runtimeUser instanceof User && $runtimeStore instanceof Store, 'No authorized runtime user/store exists for all representative screens.');
        $sessionManager = app('session');
        $encrypter = app('encrypter');
        $kernel = $app->make(HttpKernel::class);
        $requestRoute = static function (string $uri, string $locale, string $accept = 'text/html,application/xhtml+xml') use ($app, $encrypter, $kernel, $runtimeStore, $runtimeUser, $sessionManager): array {
            $session = $sessionManager->driver();
            $session->setId(bin2hex(random_bytes(20)));
            $session->start();
            $session->put('locale', $locale);
            $session->put(WorkContext::SESSION_KEY, $runtimeStore->id);
            $session->save();
            $name = $session->getName();
            $value = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(), false);
            $request = Request::create($uri, 'GET', [], [$name => $value, 'locale' => $locale], [], ['HTTP_ACCEPT' => $accept, 'HTTP_X_REQUESTED_WITH' => $accept === 'application/json' ? 'XMLHttpRequest' : '']);
            $request->setUserResolver(static fn (): User => $runtimeUser);
            Auth::setUser($runtimeUser);
            $app->instance('request', $request);
            $response = $kernel->handle($request);
            try {
                return [$response->getStatusCode(), (string) $response->getContent()];
            } finally {
                $kernel->terminate($request, $response);
            }
        };

        $screens = ['/dashboard', '/catalog/products', '/alerts', '/approvals', '/sales', '/purchasing/orders', '/purchasing/invoices', '/inventory'];
        $egyptianWording = [
            '/catalog/products' => 'المنتجات', '/alerts' => 'التنبيهات',
            '/approvals' => 'الاعتمادات', '/sales' => 'نظرة عامة على المبيعات',
            '/purchasing/orders' => 'أوامر الشراء', '/purchasing/invoices' => 'فواتير المشتريات',
            '/inventory' => 'المخزون',
        ];
        foreach (['ar', 'en', 'ar-EG'] as $locale) {
            foreach ($screens as $uri) {
                [$status, $html] = $requestRoute($uri, $locale);
                $assert($status === 200, "{$locale} {$uri} returned HTTP {$status}.");
                $direction = str_starts_with($locale, 'ar') ? 'rtl' : 'ltr';
                $assert((bool) preg_match('/<html\s+lang="'.preg_quote($locale, '/').'"\s+dir="'.$direction.'"/s', $html), "{$locale} {$uri} has incorrect lang/dir.");
                $assert(str_contains($html, 'value="ar-EG"'), "{$locale} {$uri} omitted Egyptian Arabic switch option.");
                if ($locale === 'ar-EG') {
                    $assert(str_contains($html, 'data-sidebar-scroll'), "ar-EG {$uri} omitted sidebar navigation.");
                    $assert(str_contains($html, 'اكتب اسم الشاشة أو الوحدة'), "ar-EG {$uri} omitted Arabic command palette.");
                    if (isset($egyptianWording[$uri])) {
                        $assert(str_contains($html, $egyptianWording[$uri]), "ar-EG {$uri} omitted representative Egyptian wording.");
                    }
                }
                $assert(! preg_match('/>(?:offline|auth|validation|company)\.[A-Za-z0-9_.-]+</', $html), "{$locale} {$uri} exposed a raw translation key.");
            }
        }
        [$dashboardStatus, $dashboardHtml] = $requestRoute('/dashboard', 'ar-EG');
        $assert($dashboardStatus === 200 && str_contains($dashboardHtml, 'عامل إيه النهارده؟'), 'Egyptian dashboard greeting is not visible.');
        [$alertsStatus, $alertsHtml] = $requestRoute('/alerts', 'ar-EG');
        $assert($alertsStatus === 200 && str_contains($alertsHtml, 'التنبيهات') && ! str_contains($alertsHtml, 'بالمصري:'), 'Repaired Egyptian alert label is not visible.');
        $app->setLocale('ar-EG');
        $sourceLabel = app(AuditLogPresentation::class)->source(\App\Modules\Catalog\Models\Product::class);
        $assert($sourceLabel !== 'Product' && ! str_contains($sourceLabel, '\\'), 'Technical PHP class name was not converted at the presentation boundary.');
        echo "HOTFIX36_AUTHENTICATED_AR_EN_AR_EG_SCREENS=PASS count=24 status=200 representative=dashboard,products,alerts,approvals,sales,purchasing,inventory\n";
        echo "HOTFIX36_EGYPTIAN_GREETING_ALERT_SOURCE_LABELS=PASS source={$sourceLabel}\n";

        $association = ProductSupplier::query()->whereHas('supplier', fn ($query) => $query->active())->whereHas('product', fn ($query) => $query->sellable())->with(['product.barcodes'])->orderBy('id')->first();
        $assert($association instanceof ProductSupplier, 'No supplier-product association exists for invoice-search verification.');
        foreach (['منتج', '001'] as $query) {
            [$status, $json] = $requestRoute('/transaction-products/search?'.http_build_query(['q' => $query, 'context' => 'purchasing', 'supplier_id' => $association->supplier_id, 'supplier_only' => '1']), 'ar-EG', 'application/json');
            $assert($status === 200 && is_array(json_decode($json, true, 512, JSON_THROW_ON_ERROR)['data'] ?? null), "Runtime invoice search failed for {$query}.");
        }
        $runBrowser($lookup, (string) $association->supplier_id);
        echo "HOTFIX36_INVOICE_SEARCH_KEYBOARD_RUNTIME=PASS\n";
    } finally {
        while ($connection->transactionLevel() > $level) {
            $connection->rollBack();
        }
    }
    echo "HOTFIX36_DATABASE_MUTATION=NONE transaction=rolled_back\n";
    echo "HOTFIX36_RUNTIME_VERIFICATION=PASS mode=activation\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX36_RUNTIME_VERIFICATION=FAIL '.str_replace(["\r", "\n"], ' ', $exception->getMessage())."\n");
    exit(1);
}
