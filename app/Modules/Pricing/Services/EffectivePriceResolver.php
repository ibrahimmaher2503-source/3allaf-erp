<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\CustomerProductPrice;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EffectivePriceResolver
{
    public function __construct(private readonly PriceListResolver $lists) {}

    public function resolve(int $productId, int $storeId, ?Carbon $at = null, ?int $productUnitId = null, ?int $customerId = null): ?PriceLine
    {
        $at ??= now();
        $store = Store::query()->with(['priceList', 'branch.defaultPriceList'])->find($storeId);
        $product = Product::query()->sellable()->with(['productUnits.unit'])->find($productId);
        if ($store === null || $product === null) {
            return null;
        }

        $unit = $productUnitId === null
            ? $product->productUnits->firstWhere('is_base_unit', true)
            : $product->productUnits->firstWhere('id', $productUnitId);
        if (! $unit instanceof ProductUnit || ! $unit->is_sale_unit) {
            return null;
        }

        $customer = $customerId === null ? null : Customer::query()->with(['createdStore', 'priceList'])->find($customerId);
        if ($customer !== null && (int) $customer->createdStore?->company_id !== (int) $store->company_id) {
            $customer = null;
        }

        $special = $customer === null ? null : CustomerProductPrice::query()
            ->where('company_id', $store->company_id)
            ->where('customer_id', $customer->id)
            ->where('product_id', $product->id)
            ->where('product_unit_id', $unit->id)
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $at)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at))
            ->latest('id')->first();
        if ($special !== null) {
            return $this->line($product, $store, $unit, (string) $special->price, 'customer_special', __('سعر خاص للعميل'), $customer->price_list_id, $special->id, null, $special);
        }

        if ($customer?->priceList?->isEffective($at) && (int) $customer->priceList->company_id === (int) $store->company_id) {
            $resolved = $this->lists->resolveForUnit($product, $unit, $customer->priceList, $at);

            return $this->line($product, $store, $unit, $resolved->finalPrice, 'customer_price_list', str_starts_with(app()->getLocale(), 'ar') ? $customer->priceList->name_ar : $customer->priceList->name_en, $resolved->priceListId, null, $resolved->overrideId ?? $resolved->baseOverrideId, $customer->priceList);
        }

        if ($unit->is_base_unit) {
            $legacy = PriceLine::query()
                ->with(['version.priceList', 'product', 'store'])
                ->where('product_id', $productId)->where('store_id', $storeId)
                ->whereHas('version', fn ($query) => $query->where('state', PriceVersionState::Approved->value)
                    ->where(fn ($scope) => $scope->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
                    ->where(fn ($scope) => $scope->whereNull('effective_to')->orWhere('effective_to', '>', $at)))
                ->orderByDesc('price_version_id')->first();
            if ($legacy !== null) {
                return $this->decorate($legacy, $unit, 'approved_store_price', __('سعر منفذ معتمد'), $legacy->version?->price_list_id, null, null, $legacy);
            }
        }

        try {
            $list = $this->lists->listForOutlet($store, $at);
            $resolved = $this->lists->resolveForUnit($product, $unit, $list, $at);

            return $this->line($product, $store, $unit, $resolved->finalPrice, 'outlet_price_list', str_starts_with(app()->getLocale(), 'ar') ? $list->name_ar : $list->name_en, $resolved->priceListId, null, $resolved->overrideId ?? $resolved->baseOverrideId, $list);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isPriced(int $productId, int $storeId, ?Carbon $at = null, ?int $productUnitId = null, ?int $customerId = null): bool
    {
        return $this->resolve($productId, $storeId, $at, $productUnitId, $customerId) !== null;
    }

    /** @param array<int, int> $productIds @return Collection<int, PriceLine> */
    public function resolveForStore(array $productIds, int $storeId, ?Carbon $at = null, ?int $customerId = null): Collection
    {
        return collect(array_values(array_unique(array_map('intval', $productIds))))
            ->mapWithKeys(fn (int $id) => ($price = $this->resolve($id, $storeId, $at, null, $customerId)) === null ? [] : [$id => $price]);
    }

    public function identity(PriceLine $price): string
    {
        return implode(':', [$price->price_source, $price->source_key, $price->product_unit_id, $price->amount, $price->minimum_selling_price]);
    }

    public function lockSource(PriceLine $price): ?Model
    {
        return match ($price->price_source) {
            'customer_special' => CustomerProductPrice::query()->lockForUpdate()->find($price->customer_product_price_id),
            'approved_store_price' => PriceLine::query()->lockForUpdate()->find($price->getKey()),
            default => $price->product_price_override_id
                ? ProductPriceOverride::query()->lockForUpdate()->find($price->product_price_override_id)
                : Product::query()->lockForUpdate()->find($price->product_id),
        };
    }

    private function line(Product $product, Store $store, ProductUnit $unit, string $amount, string $source, string $label, ?int $priceListId, ?int $specialId, ?int $overrideId, Model $sourceModel): PriceLine
    {
        $line = new PriceLine(['product_id' => $product->id, 'store_id' => $store->id, 'amount' => $amount, 'reference_amount' => $amount, 'open_price_allowed' => (bool) $product->open_price]);
        $line->setRelation('product', $product);
        $line->setRelation('store', $store);

        return $this->decorate($line, $unit, $source, $label, $priceListId, $specialId, $overrideId, $sourceModel);
    }

    private function decorate(PriceLine $line, ProductUnit $unit, string $source, string $label, ?int $priceListId, ?int $specialId, ?int $overrideId, Model $sourceModel): PriceLine
    {
        $line->setAttribute('product_unit_id', $unit->id);
        $line->setAttribute('price_list_id', $priceListId);
        $line->setAttribute('customer_product_price_id', $specialId);
        $line->setAttribute('product_price_override_id', $overrideId);
        $line->setAttribute('minimum_selling_price', $unit->minimum_selling_price);
        $line->setAttribute('price_source', $source);
        $line->setAttribute('price_label', $label);
        $line->setAttribute('source_key', (string) $sourceModel->getKey());
        $line->setAttribute('source_updated_at', optional($sourceModel->updated_at)->toISOString());

        return $line;
    }
}
