<?php

namespace App\Providers;

use App\Http\Responses\PosAwareLoginResponse;
use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Policies\ApprovalRecordPolicy;
use App\Modules\Platform\Policies\AuditLogPolicy;
use App\Modules\Platform\Support\ApprovalInboxAccess;
use App\Modules\Platform\Support\InitialSetupStatus;
use App\Modules\Platform\Support\ProductPricingReadiness;
use App\Modules\Platform\Support\RequestPermissionLookup;
use App\Modules\Platform\Support\TranslationOverrideLoader;
use App\Modules\Platform\Support\UserManagementScope;
use App\Modules\Platform\Support\WorkContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RequestPermissionLookup::class);
        $this->app->scoped(InitialSetupStatus::class);
        $this->app->scoped(ProductPricingReadiness::class);
        $this->app->scoped(WorkContext::class);
        $this->app->singleton(LoginResponseContract::class, PosAwareLoginResponse::class);

        // The framework registers this provider lazily; register it before replacing
        // its loader binding so resolving `translator` cannot restore FileLoader later.
        $this->app->register(TranslationServiceProvider::class);
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        $this->app->singleton('translation.loader', fn ($app) => new TranslationOverrideLoader($app['files'], [base_path('vendor/laravel/framework/src/Illuminate/Translation/lang'), $app['path.lang']]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app('translator')->determineLocalesUsing(static function (array $locales): array {
            $locale = (string) ($locales[0] ?? config('app.locale', 'en'));

            return config('app.locale_fallbacks.'.$locale, array_values(array_unique($locales)));
        });

        $this->configureDefaults();
        $this->configureQueryObservability();
        Livewire::addNamespace('platform', viewPath: resource_path('views/platform'));
        Livewire::addNamespace('catalog', viewPath: resource_path('views/catalog'));
        Livewire::addNamespace('purchasing', viewPath: resource_path('views/purchasing'));
        Livewire::addNamespace('pricing', viewPath: resource_path('views/pricing'));
        Livewire::addNamespace('inventory', viewPath: resource_path('views/inventory'));
        foreach (['saved', 'deleted', 'restored'] as $event) {
            Event::listen("eloquent.{$event}: *", static function (): void {
                if (! Cache::add('setup-readiness-version', 1)) {
                    Cache::increment('setup-readiness-version');
                }
            });
        }
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(ApprovalRecord::class, ApprovalRecordPolicy::class);

        Gate::before(function (?User $user, string $ability): ?bool {
            if ($user?->status !== 'active') {
                return false;
            }

            if (in_array($ability, ['access-administration-center', 'access-activity-log'], true)) {
                return $user->is_super_admin;
            }

            if ($user->is_super_admin) {
                return true;
            }

            return $user?->hasPermission($ability) ? true : null;
        });

        foreach ([
            'company_settings.view', 'company_settings.create', 'company_settings.edit', 'company_settings.logical_delete', 'company_settings.approve',
            'branches_stores.view', 'branches_stores.create', 'branches_stores.edit', 'branches_stores.logical_delete',
            'drawers_payments_tax_numbering_printers.view', 'drawers_payments_tax_numbering_printers.create',
            'drawers_payments_tax_numbering_printers.edit', 'drawers_payments_tax_numbering_printers.logical_delete',
            'drawers_payments_tax_numbering_printers.override', 'pos_sales.open_price_approve',
            'users_roles_permissions.view', 'users_roles_permissions.create', 'users_roles_permissions.edit', 'pos_sales.discount_approve',
            'dashboard_reports.view', 'audit_logs.view', 'product_wallet.view', 'product_wallet.export', 'product_wallet.settle', 'product_wallet.adjust', 'product_wallet.approve', 'party_wallet.view', 'party_wallet.export', 'party_wallet.settle', 'party_wallet.adjust', 'party_wallet.approve', 'party_bookings_invoices.view', 'party_bookings_invoices.create', 'party_bookings_invoices.edit', 'party_bookings_invoices.print', 'party_bookings_invoices.approve', 'party_bookings_invoices.reject', 'party_bookings_invoices.export', 'party_bookings_invoices.reverse', 'party_bookings_invoices.cancel', 'party_bookings_invoices.override', 'party_operating_orders_consumables.view', 'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit', 'party_operating_orders_consumables.print', 'party_operating_orders_consumables.approve', 'party_operating_orders_consumables.reject', 'party_operating_orders_consumables.export', 'party_operating_orders_consumables.reverse', 'party_operating_orders_consumables.cancel', 'party_operating_orders_consumables.override', 'returns_exchanges_gift_instruments.view', 'pos_sales.view', 'pos_sales.create', 'pos_sales.print',
            'dashboard_reports.export', 'dashboard_reports.export_xlsx', 'dashboard_reports.export_pdf', 'dashboard_reports.edit',
            'rental_assets.view', 'rental_assets.create', 'rental_assets.edit', 'rental_assets.print', 'rental_assets.approve', 'rental_assets.reject', 'rental_assets.export', 'rental_assets.cancel', 'rental_assets.override', 'rental_assets.reserve', 'rental_assets.checkout', 'rental_assets.return', 'rental_assets.inspect', 'rental_assets.status', 'rental_assets.cost_view', 'rental_assets.cost_edit',
            'quotations.view', 'quotations.create', 'quotations.edit', 'quotations.print', 'quotations.approve', 'quotations.export', 'quotations.cancel', 'quotations.issue', 'quotations.share',
            'customers.view', 'customers.create', 'customers.edit', 'customers.sensitive', 'customers.merge', 'customers.export', 'customers.import.approve',
            'loyalty.view', 'loyalty.earn', 'loyalty.redeem', 'loyalty.adjust', 'loyalty.approve', 'loyalty.export', 'loyalty.expire',
            'products_categories_brands.view', 'products_categories_brands.create', 'products_categories_brands.edit',
            'products_categories_brands.logical_delete', 'products_categories_brands.print', 'products_categories_brands.approve',
            'products_categories_brands.export', 'products_categories_brands.reverse', 'products_categories_brands.cancel',
            'products_categories_brands.override',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.logical_delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.edit', 'purchase_orders.print', 'purchase_orders.cancel', 'purchase_orders.approve',
            'pricing_labels.view', 'pricing_labels.create', 'pricing_labels.edit', 'pricing_labels.submit', 'pricing_labels.approve', 'pricing_labels.reject', 'pricing_labels.export', 'pricing_labels.override',
        ] as $ability) {
            Gate::define($ability, fn (?User $user): bool => $user?->hasPermission($ability) ?? false);
        }

        Gate::define('manage-settings', fn (?User $user): bool => $user?->hasPermission('company_settings.edit') ?? false);
        Gate::define('manage-branches-stores', fn (?User $user): bool => $user?->hasPermission('branches_stores.edit') ?? false);
        Gate::define('view-authorization-baseline', fn (?User $user): bool => $user?->hasPermission('users_roles_permissions.view') ?? false);
        Gate::define('manage-authorization', fn (?User $user): bool => $user?->hasPermission('users_roles_permissions.edit') ?? false);
        Gate::define('view-platform-status', fn (?User $user): bool => $user?->hasPermission('audit_logs.view') ?? false);
        Gate::define('view-ui-showcase', fn (?User $user): bool => $user?->hasPermission('dashboard_reports.view') ?? false);
        Gate::define('access-administration-center', fn (?User $user): bool => $user?->is_super_admin ?? false);
        Gate::define('access-activity-log', fn (?User $user): bool => $user?->is_super_admin ?? false);
        Gate::define('view-approval-inbox', fn (?User $user): bool => $user instanceof User && app(ApprovalInboxAccess::class)->allows($user));
        Gate::define('access-feed-store-operations', fn (?User $user): bool => $user instanceof User && collect([
            'customers.edit',
            'purchase_invoices.approve',
            'shifts_cash_movements.approve',
            'inventory_stock_card.edit',
        ])->contains(fn (string $permission): bool => $user->hasPermission($permission)));
        Gate::define('manage-global-role-catalog', fn (?User $user): bool => $user instanceof User && app(UserManagementScope::class)->canManageGlobalRoles($user));
    }

    protected function configureQueryObservability(): void
    {
        if (! config('performance.query_budget_enabled') && ! config('performance.slow_query_logging_enabled')) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $slowQueryMs = (float) config('performance.slow_query_ms', 100);

            if (config('performance.slow_query_logging_enabled') && $query->time >= $slowQueryMs) {
                Log::channel('slow_queries')->warning('Slow database query detected.', [
                    'connection' => $query->connectionName,
                    'duration_ms' => $query->time,
                    'sql' => $query->sql,
                    'bindings_count' => count($query->bindings),
                    'route' => app()->bound('request') ? request()->route()?->getName() : null,
                ]);
            }

            if (! config('performance.query_budget_enabled') || app()->runningInConsole() || ! app()->bound('request')) {
                return;
            }

            $request = request();
            $queryCount = ((int) $request->attributes->get('query_budget_count', 0)) + 1;
            $request->attributes->set('query_budget_count', $queryCount);

            $queryBudget = (int) config('performance.query_budget', 100);
            if ($queryCount > $queryBudget && ! $request->attributes->get('query_budget_exceeded')) {
                $request->attributes->set('query_budget_exceeded', true);
                Log::warning('Database query budget exceeded; request aborted.', [
                    'budget' => $queryBudget,
                    'count' => $queryCount,
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                ]);

                throw new \RuntimeException(sprintf(
                    'Query budget exceeded: %d queries (budget: %d).',
                    $queryCount,
                    $queryBudget,
                ));
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
