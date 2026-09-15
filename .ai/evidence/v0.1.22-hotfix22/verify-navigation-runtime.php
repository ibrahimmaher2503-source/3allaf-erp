<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Support\ApplicationNavigation;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, 'NAVIGATION_RUNTIME_CONTRACT=FAILED '.get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});

final class Hotfix22NavigationContractUser extends User
{
    /** @var array<string, true> */
    private array $contractPermissions;

    /** @param list<string> $permissions */
    public function __construct(int $id = -2200, array $permissions = [], bool $superAdmin = false)
    {
        parent::__construct();
        $this->contractPermissions = array_fill_keys($permissions, true);
        $this->forceFill([
            'id' => $id,
            'name' => 'Navigation Contract',
            'email' => "navigation-contract-{$id}@invalid.test",
            'status' => 'active',
            'is_super_admin' => $superAdmin,
        ]);
        $this->exists = false;
        $this->setRelation('uiPreference', null);
    }

    public function can($abilities, $arguments = [])
    {
        if ($this->is_super_admin) {
            return true;
        }

        $abilities = is_iterable($abilities) ? $abilities : [$abilities];

        foreach ($abilities as $ability) {
            $code = $ability instanceof UnitEnum ? $ability->value : (string) $ability;
            if (! isset($this->contractPermissions[$code])) {
                return false;
            }
        }

        return true;
    }

    public function hasPermission(string $code): bool
    {
        return $this->is_super_admin || isset($this->contractPermissions[$code]);
    }

    public function unreadNotifications()
    {
        return new class
        {
            public function count(): int
            {
                return 0;
            }
        };
    }
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$installRequest = static function (User $user, string $locale) use ($app): void {
    $request = Illuminate\Http\Request::create('/dashboard', 'GET');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $dashboardRoute = Route::getRoutes()->getByName('dashboard');
    if ($dashboardRoute === null) {
        throw new RuntimeException('Dashboard route is unavailable.');
    }
    $boundRoute = (clone $dashboardRoute)->bind($request);
    $request->setRouteResolver(static fn () => $boundRoute);
    $request->setUserResolver(static fn () => $user);
    $app->instance('request', $request);
    Auth::setUser($user);
    $app->setLocale($locale);
};

$renderNavigation = static function (array $groups): string {
    return Blade::render('<x-app-navigation :groups="$groups" />', ['groups' => $groups]);
};

$allKeys = static function (array $groups) use (&$allKeys): array {
    $keys = [];
    foreach ($groups as $group) {
        $keys[] = $group['key'];
        foreach ($group['items'] as $item) {
            $keys[] = $item['key'];
            if ($item['subgroups'] !== []) {
                $keys = [...$keys, ...$allKeys($item['subgroups'])];
            }
        }
    }

    return $keys;
};

$routes = static function (array $groups): array {
    $routes = [];
    foreach ($groups as $group) {
        foreach ($group['items'] as $item) {
            if (is_string($item['route'] ?? null)) {
                $routes[] = $item['route'];
            }
        }
    }

    return $routes;
};

$navigation = $app->make(ApplicationNavigation::class);
$profiles = [
    'cashier' => new Hotfix22NavigationContractUser(-2201, [
        'dashboard_reports.view', 'pos_sales.view', 'pos_sales.create', 'shifts_cash_movements.view',
    ]),
    'manager' => new Hotfix22NavigationContractUser(-2202, [
        'dashboard_reports.view', 'pos_sales.view', 'customers.view', 'purchase_orders.view',
        'purchase_invoices_supplier_returns.view', 'inventory_stock_card.view', 'transfers.view',
    ]),
    'administrator' => new Hotfix22NavigationContractUser(-2203, [], true),
];

$profileGroups = [];
foreach ($profiles as $profile => $user) {
    $installRequest($user, $profile === 'cashier' ? 'ar' : 'en');
    $groups = $navigation->for($user, app()->getLocale());
    $html = $renderNavigation($groups);
    $keys = $allKeys($groups);
    $assert($groups !== [], "{$profile} navigation is empty");
    $assert(Str::contains($html, ['<nav', 'app-navigation']), "{$profile} navigation did not render");
    $assert(! str_contains($html, 'Undefined array key'), "{$profile} navigation emitted an undefined-key warning");
    $assert(count($keys) === count(array_unique($keys)), "{$profile} navigation keys are not unique");
    $assert(! in_array('', $keys, true), "{$profile} navigation contains an empty key");
    $profileGroups[$profile] = $groups;
}

$cashierRoutes = $routes($profileGroups['cashier']);
$managerRoutes = $routes($profileGroups['manager']);
$administratorRoutes = $routes($profileGroups['administrator']);
$assert(in_array('pos', $cashierRoutes, true), 'Cashier navigation omitted POS.');
$assert(! in_array('initial-setup', $cashierRoutes, true), 'Cashier navigation exposed Initial Setup.');
$assert(in_array('inventory.balances', $managerRoutes, true), 'Manager navigation omitted permitted inventory.');
$assert(! in_array('initial-setup', $managerRoutes, true), 'Manager navigation exposed ungranted Initial Setup.');
$assert(in_array('initial-setup', $administratorRoutes, true), 'Administrator navigation omitted Initial Setup.');

$configuredSetup = collect(config('navigation', []))
    ->flatMap(static fn (array $group): array => is_array($group['items'] ?? null) ? $group['items'] : [])
    ->firstWhere('route', 'initial-setup');
$assert(is_array($configuredSetup) && ! array_key_exists('key', $configuredSetup), 'Contract no longer covers a real keyless navigation item.');
$normalizedSetup = collect($profileGroups['administrator'])
    ->flatMap(static fn (array $group): array => $group['items'])
    ->firstWhere('route', 'initial-setup');
$assert(is_array($normalizedSetup) && str_starts_with($normalizedSetup['key'], 'auto-'), 'Keyless Initial Setup item did not receive a fallback key.');
$assert($normalizedSetup['subgroups'] !== [], 'Real nested Initial Setup groups were not rendered.');

$fixture = [
    [
        'label' => ['ar' => 'مجموعة العقد', 'en' => 'Contract group'],
        'items' => [
            ['type' => 'heading', 'label' => ['en' => 'Optional heading']],
            ['separator' => true],
            ['label' => 'Keyless active item', 'url' => '/contract/active', 'active' => true],
            [
                'type' => 'group',
                'label' => 'Keyless nested group',
                'url' => '/contract/nested',
                'subgroups' => [
                    [
                        'label' => 'Keyless subgroup',
                        'items' => [
                            ['label' => 'Keyless child', 'url' => '/contract/child'],
                            ['type' => 'heading', 'label' => []],
                            ['type' => 'separator'],
                            [],
                        ],
                    ],
                    ['type' => 'separator'],
                    ['heading' => true, 'label' => null],
                ],
            ],
            [],
        ],
    ],
    ['type' => 'separator'],
    ['heading' => true, 'label' => []],
    ['items' => []],
];
$normalizedFixture = $navigation->normalizeForRendering($fixture, 'en');
$assert($normalizedFixture === $navigation->normalizeForRendering($fixture, 'en'), 'Fallback keys are not deterministic.');
$fixtureKeys = $allKeys($normalizedFixture);
$assert(count($fixtureKeys) === count(array_unique($fixtureKeys)), 'Fallback keys are not unique across optional shapes.');
$fixtureHtml = $renderNavigation($fixture);
$assert(substr_count($fixtureHtml, 'role="separator"') >= 3, 'Separator shapes did not render.');
$assert(str_contains($fixtureHtml, 'Optional heading'), 'Heading shape did not render.');
$assert(str_contains($fixtureHtml, 'Keyless child'), 'Nested keyless item did not render.');
$assert(! str_contains($fixtureHtml, 'Undefined array key'), 'Optional fixture emitted an undefined-key warning.');

$installRequest($profiles['administrator'], 'en');
$originalWorkContext = $app->make(App\Modules\Platform\Support\WorkContext::class);
$app->instance(App\Modules\Platform\Support\WorkContext::class, new class
{
    public function stores(User $actor): Illuminate\Support\Collection
    {
        return collect();
    }

    public function selected(User $actor): mixed
    {
        return null;
    }
});
$trend = collect(range(6, 0))->map(static fn (int $days): array => [
    'day' => now()->subDays($days)->toDateString(),
    'sales' => '0',
    'purchases' => '0',
]);
$dashboard = [
    'sales' => null,
    'purchases' => null,
    'low_stock' => collect(),
    'low_stock_count' => null,
    'out_of_stock_count' => null,
    'recent_movements' => collect(),
    'trend' => $trend,
    'top_products' => collect(),
    'open_shifts' => null,
    'pending_orders' => null,
    'pending_returns' => null,
    'pending_approvals' => null,
];
$setup = ['completed_count' => 0, 'required_count' => 0];
$dashboardHtml = view('dashboard', compact('dashboard', 'setup'))->render();
$app->instance(App\Modules\Platform\Support\WorkContext::class, $originalWorkContext);
$assert(str_contains($dashboardHtml, 'dashboard-toolbar'), 'Authenticated database-free dashboard did not render.');
$assert(str_contains($dashboardHtml, 'id="application-navigation"'), 'Authenticated database-free dashboard omitted navigation.');
$assert(! str_contains($dashboardHtml, 'Undefined array key'), 'Authenticated database-free dashboard emitted an undefined-key warning.');

echo "NAVIGATION_ROLE_AND_OPTIONAL_SHAPES=PASS profiles=3\n";
echo "NAVIGATION_FALLBACK_KEYS=PASS real_keyless_initial_setup=1\n";
echo "NAVIGATION_COMPONENT_RUNTIME_RENDER=PASS\n";
echo "AUTHENTICATED_DASHBOARD_NAVIGATION_RUNTIME=PASS mode=database-free\n";

if (($argv[1] ?? '--fixture') === '--activation') {
    DB::connection()->beginTransaction();
    try {
        $users = User::query()->where('status', 'active')->orderByDesc('is_super_admin')->orderBy('id')->limit(20)->get();
        $assert($users->isNotEmpty(), 'No active user exists for the authenticated navigation preflight.');
        $dashboardUser = null;
        $realProfiles = [];

        foreach ($users as $user) {
            $installRequest($user, 'en');
            $groups = $navigation->for($user, 'en');
            $signature = hash('sha256', implode('|', $routes($groups)));
            if (! isset($realProfiles[$signature])) {
                $renderNavigation($groups);
                $realProfiles[$signature] = true;
            }
            if ($dashboardUser === null && $user->can('dashboard_reports.view')) {
                $dashboardUser = $user;
            }
            if (count($realProfiles) >= 3 && $dashboardUser !== null) {
                break;
            }
        }

        $assert($dashboardUser instanceof User, 'No active dashboard-authorized user exists for runtime rendering.');
        $installRequest($dashboardUser, 'en');
        $trend = collect(range(6, 0))->map(static fn (int $days): array => [
            'day' => now()->subDays($days)->toDateString(),
            'sales' => '0',
            'purchases' => '0',
        ]);
        $dashboard = [
            'sales' => null,
            'purchases' => null,
            'low_stock' => collect(),
            'low_stock_count' => null,
            'out_of_stock_count' => null,
            'recent_movements' => collect(),
            'trend' => $trend,
            'top_products' => collect(),
            'open_shifts' => null,
            'pending_orders' => null,
            'pending_returns' => null,
            'pending_approvals' => null,
        ];
        $setup = ['completed_count' => 0, 'required_count' => 0];
        $dashboardHtml = view('dashboard', compact('dashboard', 'setup'))->render();
        $assert(str_contains($dashboardHtml, 'dashboard-toolbar'), 'Authenticated dashboard did not render.');
        $assert(str_contains($dashboardHtml, 'id="application-navigation"'), 'Authenticated dashboard omitted application navigation.');
        $assert(! str_contains($dashboardHtml, 'Undefined array key'), 'Authenticated dashboard emitted an undefined-key warning.');

        echo 'AUTHENTICATED_DASHBOARD_NAVIGATION_RUNTIME=PASS real_profiles='.count($realProfiles)."\n";
    } finally {
        DB::connection()->rollBack();
    }
} elseif (($argv[1] ?? '--fixture') !== '--fixture') {
    throw new InvalidArgumentException('Expected --fixture or --activation.');
}
