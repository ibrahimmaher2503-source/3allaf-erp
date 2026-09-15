<?php

declare(strict_types=1);

namespace App\Modules\Pricing\Queries;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Database\Eloquent\Builder;

final class PricingDashboard
{
    /** @return array<string, mixed> */
    public function for(User $actor, ?int $storeId = null, ?int $unpricedProductCount = null): array
    {
        $visibleStores = Store::query()->visibleTo($actor)->select('id');
        $storeIds = Store::query()->visibleTo($actor)
            ->when($storeId !== null, fn (Builder $query) => $query->whereKey($storeId))
            ->select('id');
        $visibleVersions = $this->visibleVersions($actor, $storeId);
        $effectiveLines = PriceLine::query()
            ->whereIn('price_lines.store_id', $storeIds)
            ->whereHas('version', fn (Builder $query) => $this->effectiveApproved($query));
        $futureVersions = (clone $visibleVersions)
            ->where('price_versions.state', PriceVersionState::Approved->value)
            ->where('price_versions.effective_from', '>', now());
        $expiringVersions = (clone $visibleVersions)
            ->where('price_versions.state', PriceVersionState::Approved->value)
            ->whereBetween('price_versions.effective_to', [now(), now()->addDays(7)]);

        return [
            'active_lists' => PriceList::query()
                ->where('price_lists.status', 'active')
                ->whereHas('versions.lines', fn (Builder $query) => $query->whereIn('price_lines.store_id', $storeIds))
                ->count(),
            'visible_store_count' => (clone $storeIds)->count(),
            'effective_prices' => (clone $effectiveLines)->count(),
            'unpriced_products' => $unpricedProductCount ?? Product::query()->sellable()
                ->where(fn (Builder $query) => $query->whereNull('sale_price')->orWhere('sale_price', '<=', 0))
                ->count(),
            'pending_approvals' => (clone $visibleVersions)->where('price_versions.state', PriceVersionState::Submitted->value)->count(),
            'scheduled_prices' => (clone $futureVersions)->count(),
            'expiring_soon' => (clone $expiringVersions)->count(),
            'open_price_lines' => (clone $effectiveLines)->where('price_lines.open_price_allowed', true)->count(),
            'pricing_exceptions' => [
                'expired_approved' => (clone $visibleVersions)
                    ->where('price_versions.state', PriceVersionState::Approved->value)
                    ->where('price_versions.effective_to', '<=', now())->count(),
                'invalid_open_bounds' => (clone $effectiveLines)
                    ->where('price_lines.open_price_allowed', true)
                    ->where(function (Builder $query): void {
                        $query->whereNull('price_lines.open_price_minimum')
                            ->orWhereNull('price_lines.open_price_maximum')
                            ->orWhereColumn('price_lines.open_price_minimum', '>', 'price_lines.open_price_maximum');
                    })->count(),
            ],
            'recent_versions' => (clone $visibleVersions)
                ->with(['priceList:id,code,name_ar,name_en', 'lines' => fn ($query) => $query
                    ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                    ->with(['product:id,item_code,name_ar,name_en', 'store:id,code,name_ar,name_en'])])
                ->latest('price_versions.updated_at')->latest('price_versions.id')->limit(6)->get(),
            'scheduled' => (clone $futureVersions)
                ->with(['priceList:id,code,name_ar,name_en', 'lines.product:id,item_code,name_ar,name_en', 'lines.store:id,code,name_ar,name_en'])
                ->orderBy('price_versions.effective_from')->limit(5)->get(),
            'scope_is_authorized' => $storeId === null || (clone $visibleStores)->whereKey($storeId)->exists(),
        ];
    }

    private function visibleVersions(User $actor, ?int $storeId): Builder
    {
        return PriceVersion::query()->whereHas('lines', function (Builder $query) use ($actor, $storeId): void {
            $query->whereIn('price_lines.store_id', Store::query()->visibleTo($actor)->select('id'))
                ->when($storeId !== null, fn (Builder $scope) => $scope->where('price_lines.store_id', $storeId));
        });
    }

    private function effectiveApproved(Builder $query): Builder
    {
        return $query->where('price_versions.state', PriceVersionState::Approved->value)
            ->where(fn (Builder $scope) => $scope->whereNull('price_versions.effective_from')->orWhere('price_versions.effective_from', '<=', now()))
            ->where(fn (Builder $scope) => $scope->whereNull('price_versions.effective_to')->orWhere('price_versions.effective_to', '>', now()));
    }
}
