<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\SupplierGroup;
use App\Modules\Catalog\Support\SupplierSettlementMethod;
use App\Modules\Customer\Models\CustomerGroup;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Models\OpeningInventoryDocument;
use App\Modules\Inventory\Models\OpeningInventoryZeroDecision;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\SetupDecision;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Services\PriceListResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

final class InitialSetupStatus
{
    private ?array $requestSnapshot = null;

    /** @return array{steps: list<array<string, mixed>>, owner_decisions: list<array<string, mixed>>, completed_count: int, required_count: int, progress_percent: int, needs_attention: bool, complete: bool} */
    public function snapshot(): array
    {
        if (app()->bound('request') && request()->attributes->has('initial_setup_snapshot')) {
            return request()->attributes->get('initial_setup_snapshot');
        }
        if ($this->requestSnapshot !== null) {
            return $this->requestSnapshot;
        }

        $actor = auth()->user();
        $companyId = (int) Company::query()->where('status', 'active')->value('id');
        $permissions = $actor instanceof User ? array_keys(app(RequestPermissionLookup::class)->for($actor)) : [];
        sort($permissions);
        $version = (int) Cache::get('setup-readiness-version', 1);
        $key = sprintf('setup-ready:v%d:c%d:u%d:p%s:l%s', $version, $companyId, (int) ($actor?->id ?? 0), hash('xxh3', implode('|', $permissions)), app()->getLocale());
        $this->requestSnapshot = Cache::remember($key, now()->addMinutes(10), fn (): array => $this->buildSnapshot());
        if (app()->bound('request')) {
            request()->attributes->set('initial_setup_snapshot', $this->requestSnapshot);
        }

        return $this->requestSnapshot;
    }

    private function buildSnapshot(): array
    {
        $companyReady = $this->companyReady();
        $branchesReady = $this->branchesReady();
        $branchStoreSetupReady = $this->branchesAndStoresReady();
        $activeCompanyId = (int) Company::query()->where('status', 'active')->value('id');
        $productPricing = app(ProductPricingReadiness::class)->snapshot($activeCompanyId);
        $catalogReady = $this->catalogReady($productPricing);
        $activeCustomerGroups = CustomerGroup::query()->forCompany($activeCompanyId)->active();
        $activeSupplierGroups = SupplierGroup::query()->forCompany($activeCompanyId)->active();
        $activeProductOptionGroups = ProductOptionGroup::query()
            ->active()
            ->whereHas('values', fn (Builder $query): Builder => $query->active());
        $customerConsentPolicyRecords = $this->customerConsentPolicyRecords();
        $latestDecisions = $activeCompanyId > 0 && Schema::hasTable('setup_decisions')
            ? SetupDecision::query()->where('company_id', $activeCompanyId)->latest('id')->get()->unique('step_key')->keyBy('step_key')
            : collect();

        $steps = [
            $this->step('company', $companyReady, Company::query()->count()),
            $this->step('branches-stores', $branchesReady, Branch::query()->count()),
            $this->step('warehouses', $this->warehousesReady() && Store::query()->where('type','selling')->where('status','active')->whereNotNull('branch_id')->exists(), Store::query()->whereIn('type',['warehouse','selling'])->count()),
            $this->step('cash-drawers', $this->cashDrawersReady(), CashDrawer::query()->count()),
            $this->step('users-scopes', $this->usersAndScopesReady(), User::query()->where('status', 'active')->count()),
            $this->step('payment-methods', $this->paymentMethodsReady(), PaymentMethod::query()->count()),
            $this->step('taxes', $this->taxesReady(), TaxSetting::query()->count()),
            $this->step('document-sequences', $this->documentSequencesReady(), DocumentSequence::query()->count()),
            $this->step('printers', $this->printersReady(), PrinterConfiguration::query()->count()),
            $this->step('print-templates', $this->printTemplatesReady(), PrinterConfiguration::query()->where('status', 'active')->whereNotNull('template_name')->where('template_name', '!=', '')->count()),
            $this->step('categories', Category::query()->where('status', 'active')->exists(), Category::query()->count()),
            $this->step('brands', Brand::query()->where('status', 'active')->exists(), Brand::query()->count()),
            $this->step('customer-groups', $activeCustomerGroups->exists(), $activeCustomerGroups->count()),
            $this->step('customers', $customerConsentPolicyRecords === 3, $customerConsentPolicyRecords),
            $this->step('supplier-groups', $activeSupplierGroups->exists(), $activeSupplierGroups->count()),
            $this->step('suppliers', $this->suppliersReady(), Supplier::query()->count(), $this->suppliersReady() ? null : __('Add an active supplier and choose Cash, Cheques, Installments, Trust Deposits, or Other. Other also requires a description.')),
            $this->step('product-options', $activeProductOptionGroups->exists(), $activeProductOptionGroups->count()),
            $this->step('product-masters', $catalogReady, Product::query()->where('status', 'active')->count(), $this->productReadinessReason($productPricing), $productPricing),
            $this->step('prices', $this->pricesReady($productPricing), PriceList::query()->count(), $this->pricingReadinessReason($productPricing), $productPricing),
            $this->step('opening-configuration', $this->openingInventoryReady(), OpeningInventoryDocument::query()->count(), $branchStoreSetupReady && $catalogReady ? null : __('Complete active branches, stores, categories, and product masters before opening inventory.')),
        ];

        $steps = array_map(function (array $step) use ($latestDecisions): array {
            $decision = $latestDecisions->get($step['key']);
            if ($step['complete'] || $decision === null) {
                return $step + ['decision' => null, 'decided_at' => null, 'decided_by' => null];
            }

            $value = (string) $decision->decision;
            if (($step['required'] && $value !== 'deferred') || (! $step['required'] && $value !== 'skipped')) {
                return $step + ['decision' => null, 'decided_at' => null, 'decided_by' => null];
            }

            $step['decision'] = $value;
            $step['decided_at'] = $decision->decided_at;
            $step['decided_by'] = $decision->decided_by;
            $step['status'] = $value;
            $step['status_label'] = __($value === 'skipped' ? 'Skipped' : 'Complete later');

            return $step;
        }, $steps);

        $required = array_values(array_filter($steps, static fn (array $step): bool => $step['required']));
        $completed = count(array_filter($required, static fn (array $step): bool => $step['complete']));
        $count = count($required);

        return ['steps' => $steps, 'owner_decisions' => $this->ownerDecisions(), 'completed_count' => $completed, 'required_count' => $count, 'progress_percent' => $count === 0 ? 100 : (int) round(($completed / $count) * 100), 'needs_attention' => $completed < $count, 'complete' => $completed === $count];
    }

    /** @return array<string, mixed> */
    private function step(string $key, bool $complete, int $records, ?string $incompleteReason = null, array $details = []): array
    {
        $definition = InitialSetupStepRegistry::steps()[$key];
        $label = __($definition['label']);
        $description = __($definition['description']);
        $routeName = $definition['route'];
        $permission = $definition['permission'];
        $required = $definition['required'];
        $status = $complete ? 'completed' : ($records === 0 ? 'not_started' : ($required ? 'requires_completion' : 'incomplete'));
        $route = route($routeName, $definition['parameters']);
        $canAccess = auth()->check() && auth()->user()->can($permission);

        $reason = $incompleteReason ?? ($complete ? __('Persisted data meets the current readiness rule.') : $description);

        return ['key' => $key, 'destination_key' => $definition['destination_key'] ?: $key, 'label' => $label, 'description' => $description, 'reason' => $reason, 'passed_conditions' => $complete ? [$reason] : [], 'failed_conditions' => $complete ? [] : [$reason], 'route' => $route, 'route_name' => $routeName, 'permission' => $permission, 'can_access' => $canAccess, 'complete' => $complete, 'required' => $required, 'records' => $records, 'status' => $status, 'status_label' => __(match ($status) {
            'not_started' => 'Not started', 'incomplete' => 'Incomplete', 'requires_completion' => 'Requires completion', 'ready' => 'Ready', default => 'Completed',
        }), 'cta_label' => __($complete ? 'Review' : 'Configure'), 'details' => $details];
    }

    private function companyReady(): bool
    {
        return Company::query()->where('status', 'active')->whereNotIn('code', ['', 'TBD'])->whereNotNull('name_ar')->where('name_ar', '!=', '')->whereNotNull('name_en')->where('name_en', '!=', '')->whereNotIn('currency_code', ['', 'TBD'])->whereNotIn('currency_symbol', ['', 'TBD'])->whereNotNull('timezone')->where('timezone', '!=', '')->exists();
    }

    private function branchesAndStoresReady(): bool
    {
        return Branch::query()->where('status', 'active')->exists()
            && Store::query()->where('status', 'active')->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))->exists()
            && Store::query()->where('type','selling')->where('status','active')->whereNotNull('branch_id')->exists();
    }

    private function branchesReady(): bool
    {
        return Branch::query()->where('status', 'active')->exists();
    }

    private function warehousesReady(): bool
    {
        return Store::query()->where('type', 'warehouse')->where('status', 'active')->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))->exists();
    }

    private function cashDrawersReady(): bool
    {
        return CashDrawer::query()
            ->where('status', 'active')
            ->whereHas('branch', fn (Builder $query): Builder => $query->where('status', 'active'))
            ->whereHas('store', fn (Builder $query): Builder => $query
                ->where('status', 'active')
                ->where('type', 'selling')
                ->whereColumn('stores.branch_id', 'cash_drawers.branch_id'))
            ->exists();
    }

    private function paymentMethodsReady(): bool
    {
        return PaymentMethod::query()->where('status', 'active')->exists();
    }

    private function taxesReady(): bool
    {
        return TaxSetting::query()->where('status', 'active')->exists();
    }

    private function documentSequencesReady(): bool
    {
        return DocumentSequence::query()->where('status', 'active')->exists();
    }

    private function printersReady(): bool
    {
        return PrinterConfiguration::query()->where('status', 'active')->exists();
    }

    private function printTemplatesReady(): bool
    {
        return PrinterConfiguration::query()->where('status', 'active')->whereNotNull('template_name')->where('template_name', '!=', '')->exists();
    }

    /** @return list<array<string, mixed>> */
    private function ownerDecisions(): array
    {
        return [
            $this->ownerDecision('warehouse-taxonomy', __('Warehouse taxonomy'), __('Confirm which stores are physical warehouses and which are selling or service locations before inventory routing.'), __('Review stores'), 'admin.stores', 'branches_stores.view'),
            $this->ownerDecision('timezone-provenance', __('Timezone provenance and branch override'), __('Confirm the company timezone source and whether each branch may override it.'), __('Review branches'), 'admin.branches', 'branches_stores.view'),
            $this->ownerDecision('payment-offline-policy', __('Payment and offline policy'), __('Confirm allowed tender types, evidence requirements, and which methods may be used by the local offline flow.'), __('Review payment settings'), 'admin.settings', 'company_settings.view', ['tab' => 'payments']),
            $this->ownerDecision('tax-treatment-policy', __('Tax treatment and zero-tax distinctions'), __('Confirm standard, zero-rated, exempt, and out-of-scope treatment plus the effective default before operational use.'), __('Review tax settings'), 'admin.settings', 'company_settings.view', ['tab' => 'tax']),
            $this->ownerDecision('document-numbering-policy', __('Document numbering policy'), __('Confirm sequence scope, daily reset behavior, prefix, suffix, padding, and any authorized correction process.'), __('Review numbering'), 'admin.settings', 'company_settings.view', ['tab' => 'sequences']),
            $this->ownerDecision('printer-output-policy', __('Printer and template policy'), __('Confirm the intended branch or location printer profile and template; physical hardware acceptance remains separate.'), __('Review printers'), 'admin.settings', 'company_settings.view', ['tab' => 'printers']),
            $this->ownerDecision('customer-policy', __('Customer consent, loyalty, and wallet policy'), __('Confirm consent purposes, child-profile handling, loyalty rules, and Product/Party Wallet boundaries before publishing policy versions.'), __('Review customer policy settings'), 'admin.settings.customer-loyalty', 'company_settings.view'),
            $this->ownerDecision('customer-data-entry', __('Customer and child data entry'), __('Use the customer screens to review the owner-approved consent purpose and child-profile capture before collecting genuine data.'), __('Review customer screens'), 'customers.index', 'customers.view'),
            $this->ownerDecision('supplier-payment-recipient', __('Supplier payment terms and recipient policy'), __('Confirm supplier payment terms and the intended order/invoice recipient before purchasing communication is used.'), __('Review suppliers'), 'suppliers.index', 'suppliers.view'),
        ];
    }

    /** @param array<string, mixed> $parameters @return array<string, mixed> */
    private function ownerDecision(string $key, string $title, string $description, string $ctaLabel, string $routeName, string $permission, array $parameters = []): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'status' => 'requires_owner_decision',
            'status_label' => __('Requires owner decision'),
            'cta_label' => $ctaLabel,
            'route_name' => $routeName,
            'route' => route($routeName, $parameters),
            'permission' => $permission,
            'can_access' => auth()->check() && auth()->user()->can($permission),
        ];
    }

    private function usersAndScopesReady(): bool
    {
        $administrator = User::query()->where('status', 'active')->where('is_super_admin', true)->whereHas('roles', fn (Builder $query): Builder => $query->where('roles.code', 'system-administrator')->where('roles.status', 'active'))->exists();
        $unscoped = User::query()->where('status', 'active')->where('is_super_admin', false)->where(fn (Builder $query): Builder => $query->whereDoesntHave('roles', fn (Builder $role): Builder => $role->where('roles.status', 'active'))->orWhere(fn (Builder $scope): Builder => $scope->whereDoesntHave('branchScopes', fn (Builder $branch): Builder => $branch->where('status', 'active'))->whereDoesntHave('storeScopes', fn (Builder $store): Builder => $store->where('status', 'active'))))->exists();

        return $administrator && ! $unscoped;
    }

    /** @param array<string,mixed> $productPricing */
    private function catalogReady(array $productPricing): bool
    {
        $broken = Product::query()->whereNull('parent_product_id')->where('has_variations', true)->where('status', 'active')->whereDoesntHave('variants', fn (Builder $query): Builder => $query->where('status', 'active'))->exists();

        return Category::query()->where('status', 'active')->exists() && $productPricing['product_cards_complete'] && ! $broken;
    }

    private function suppliersReady(): bool
    {
        return Supplier::query()
            ->where('status', 'active')
            ->whereIn('settlement_method', SupplierSettlementMethod::VALUES)
            ->where(fn (Builder $query): Builder => $query->where('settlement_method', '!=', 'other')->orWhereNotNull('settlement_other_description'))
            ->exists();
    }

    /** @param array<string,mixed> $productPricing */
    private function pricesReady(array $productPricing): bool
    {
        $companyId = (int) Company::query()->where('status', 'active')->value('id');
        $base = PriceList::query()->where('company_id', $companyId)->where('list_number', 0)->first();
        if (! $productPricing['pricing_prerequisite_complete'] || ! $base?->isEffective()) return false;
        return ! Store::query()->where('company_id', $companyId)->where('type', 'selling')->where('status', 'active')->with(['priceList','branch.defaultPriceList'])->get()->contains(fn (Store $outlet): bool => ! app(PriceListResolver::class)->assignmentIsValid($outlet));
    }

    /** @param array<string,mixed> $productPricing */
    private function pricingReadinessReason(array $productPricing): ?string
    {
        if ($this->pricesReady($productPricing)) return null;
        $companyId = (int) Company::query()->where('status', 'active')->value('id');
        $reasons = [];
        $base = PriceList::query()->where('company_id', $companyId)->where('list_number', 0)->first();
        if (! $base?->isEffective()) $reasons[] = __('Create and activate Base Price List 0.');
        $missing = (int) $productPricing['invalid_price_count'];
        if ($missing) $reasons[] = __('Sellable Product Cards needing a positive base consumer price: :count', ['count' => $missing]);
        $invalid = Store::query()->where('company_id', $companyId)->where('type', 'selling')->where('status', 'active')->with(['priceList','branch.defaultPriceList'])->get()->filter(fn (Store $outlet): bool => ! app(PriceListResolver::class)->assignmentIsValid($outlet))->count();
        if ($invalid) $reasons[] = __('Active Sales Outlets needing a valid active price list: :count', ['count' => $invalid]);
        return implode(' ', $reasons);
    }

    /** @param array<string,mixed> $productPricing */
    private function productReadinessReason(array $productPricing): ?string
    {
        if ($productPricing['product_cards_complete']) return null;
        $count = (int) $productPricing['affected_count'];
        return __('Sellable Product Cards requiring completion: :count', ['count' => $count]);
    }

    private function openingInventoryReady(): bool
    {
        return OpeningInventoryDocument::query()->where('status', 'approved')->whereNull('reversal_of_id')->exists()
            || OpeningInventoryZeroDecision::query()->exists();
    }

    private function customerConsentPolicyRecords(): int
    {
        return collect(['customer.consent.purpose', 'customer.consent.wording', 'customer.consent.retention'])
            ->filter(fn (string $key): bool => trim((string) CustomerPolicySettingVersion::query()->where('key', $key)->latest('version')->value('value')) !== '')
            ->count();
    }

}
