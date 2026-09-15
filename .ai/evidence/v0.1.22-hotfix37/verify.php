<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Platform\Support\ApplicationNavigation;
use App\Support\ApplicationVersion;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;

$root = dirname(__DIR__, 3);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) throw new RuntimeException($message);
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $loader = new FileLoader($app['files'], [$root.'/vendor/laravel/framework/src/Illuminate/Translation/lang', $root.'/lang']);
    $translator = new Translator($loader, 'en');
    $translator->determineLocalesUsing(static function (array $locales): array {
        $locale = (string) ($locales[0] ?? 'en');

        return config('app.locale_fallbacks.'.$locale, array_values(array_unique($locales)));
    });
    $app->instance('translation.loader', $loader);
    $app->instance('translator', $translator);
    Illuminate\Support\Facades\Facade::clearResolvedInstance('translator');
    $assert(ApplicationVersion::RELEASE === '0.1.22-hotfix37', 'Version mismatch.');

    $localeKeys = [
        'Open contextual help', 'Close help', 'Navigation help', 'Filter / Search',
        'Show search and filters by default', 'Branch help', 'User access help',
        'Roles and permissions help',
    ];
    $localeMaps = [];
    foreach (['ar', 'en', 'ar-EG'] as $locale) {
        $localeMaps[$locale] = json_decode((string) file_get_contents($root."/lang/{$locale}.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach ($localeKeys as $key) $assert(filled($localeMaps[$locale][$key] ?? null), "{$locale} is missing {$key}.");
        $app->setLocale($locale);
        $help = Blade::render('<x-context-help :title="__(\'Navigation help\')"><p>{{ __(\'Filter / Search\') }}</p></x-context-help>');
        $filter = Blade::render('<x-tables.filter-bar><input type="search"></x-tables.filter-bar>');
        foreach (['aria-haspopup="dialog"', 'x-trap.inert.noscroll', 'x-on:click.self="close()"', 'x-on:keydown.escape.window', 'context-help__close'] as $needle) {
            $assert(str_contains($help, $needle), "{$locale} help render omitted {$needle}.");
        }
        $assert(str_contains($filter, 'filtersOpen: false'), "{$locale} list filters are not closed by default.");
        $assert(str_contains($filter, 'table-filter-bar__toggle') && str_contains($filter, 'aria-controls'), "{$locale} filter control was not rendered.");
        echo "HOTFIX37_LOCALE_RENDER=PASS locale={$locale}\n";
    }
    $assert($localeMaps['ar-EG']['Navigation help'] !== $localeMaps['ar']['Navigation help'], 'ar-EG navigation help is not naturally localized.');

    $branch = (string) file_get_contents($root.'/resources/views/platform/admin/branches.blade.php');
    $settings = (string) file_get_contents($root.'/resources/views/platform/admin/settings.blade.php');
    $users = (string) file_get_contents($root.'/resources/views/platform/admin/authorization-baseline.blade.php');
    $assert(! str_contains($branch, 'branchForm.timezone') && ! str_contains($branch, "__('Timezone')"), 'Branch screen still presents timezone.');
    $assert(! str_contains($branch, "variant=\"primary\" href=\"{{ route('admin.stores') }}\""), 'Branch screen still contains the warehouses/POS shortcut.');
    $assert(str_contains($settings, 'companyForm.timezone') && str_contains($settings, 'showSearchFiltersByDefault'), 'General Settings controls are incomplete.');
    $assert(! str_contains($users, 'auth-users-card') && ! str_contains($users, 'auth-roles-card') && ! str_contains($users, 'roleCount'), 'User/role total cards remain.');

    $css = (string) file_get_contents($root.'/resources/css/app.css');
    $js = (string) file_get_contents($root.'/resources/js/sidebar-navigation-state.js');
    foreach ([':hover', ':focus-visible', '.app-navigation__item.is-active', 'overflow-y:auto!important', '.context-help__panel'] as $needle) $assert(str_contains($css, $needle), "CSS omitted {$needle}.");
    foreach (['rajeh_sidebar_navigation_v3', 'livewire:navigating', 'livewire:navigated', "'ArrowDown'", "'ArrowRight'", 'navigation.scrollTop'] as $needle) $assert(str_contains($js, $needle), "Sidebar runtime omitted {$needle}.");

    $configured = config('navigation');
    $groupKeys = array_column($configured, 'key');
    $assert(array_search('purchasing', $groupKeys, true) < array_search('sales', $groupKeys, true), 'Supplier group is not before Customer group.');
    $inventory = collect($configured)->firstWhere('key', 'inventory')['items'];
    $routes = array_column($inventory, 'route');
    $expected = ['catalog.products', 'catalog.categories', 'catalog.brands', 'catalog.product-options'];
    $assert(array_slice($routes, 0, 4) === $expected, 'Product navigation sequence is incorrect.');

    $request = Request::create('/catalog/categories', 'GET');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(static fn () => $route);
    $app->instance('request', $request);
    $user = new User();
    $user->forceFill(['id' => 1, 'status' => 'active', 'is_super_admin' => true, 'name' => 'Verifier', 'email' => 'verifier@example.test']);
    $navigation = app(ApplicationNavigation::class)->for($user, 'en');
    $active = collect($navigation)->flatMap(fn (array $group) => $group['items'])->filter(fn (array $item) => $item['active'] ?? false);
    $assert($active->count() === 1 && $active->first()['route'] === 'catalog.categories', 'Active route highlighting is ambiguous.');

    $identities = [];
    $collect = static function (array $entry) use (&$identities): void {
        if (($entry['url'] ?? '#') !== '#') {
            $parts = parse_url($entry['url']);
            parse_str($parts['query'] ?? '', $query);
            unset($query['setup'], $query['setup_step']);
            if (($query['section'] ?? null) === 'supplier-masters') unset($query['section']);
            ksort($query);
            $identities[] = ($parts['path'] ?? '').($query === [] ? '' : '?'.http_build_query($query));
        }
        foreach ($entry['subgroups'] ?? [] as $subcategory) foreach ($subcategory['items'] ?? [] as $child) $identities[] = parse_url($child['url'], PHP_URL_PATH).(parse_url($child['url'], PHP_URL_QUERY) ? '?'.preg_replace('/(?:^|&)setup(?:_step)?=[^&]*/', '', parse_url($child['url'], PHP_URL_QUERY)) : '');
    };
    foreach ($navigation as $group) foreach ($group['items'] as $item) $collect($item);
    $duplicates = array_filter(array_count_values($identities), static fn (int $count): bool => $count > 1);
    $assert($duplicates === [], 'Rendered navigation contains duplicate destinations: '.implode(', ', array_keys($duplicates)));

    echo "HOTFIX37_NAVIGATION_HELP_BRANCH_SETTINGS_AUTH=PASS\n";
    echo "HOTFIX37_FINAL_VERIFICATION=PASS database_mutation=none\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'HOTFIX37_FINAL_VERIFICATION=FAIL '.$exception->getMessage()."\n");
    exit(1);
}
