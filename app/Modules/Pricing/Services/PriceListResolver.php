<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Data\ResolvedListPrice;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Validation\ValidationException;

final class PriceListResolver
{
    private const DERIVED_PRICE_INCREMENT = '5';

    /** @return array{pre_round:string,rounded:string} */
    public function calculate(string $basePrice, string $percentage): array
    {
        $base = $this->normalized($basePrice, 3);
        $percent = $this->normalized($percentage, 4);
        $factor = bcadd('1', bcdiv($percent, '100', 8), 8);
        $calculated = bcmul($base, $factor, 8);
        $multiples = bcdiv($calculated, self::DERIVED_PRICE_INCREMENT, 0);

        if (bccomp($calculated, bcmul($multiples, self::DERIVED_PRICE_INCREMENT, 8), 8) > 0) {
            $multiples = bcadd($multiples, '1', 0);
        }

        return [
            'pre_round' => bcadd($calculated, '0', 3),
            'rounded' => bcadd(bcmul($multiples, self::DERIVED_PRICE_INCREMENT, 3), '0', 3),
        ];
    }

    public function resolve(Product $product, PriceList $list, ?\DateTimeInterface $at = null): ResolvedListPrice
    {
        $unit = $product->baseProductUnit()->first();
        if (! $unit instanceof ProductUnit) {
            throw ValidationException::withMessages(['product_unit' => __('The product has no sellable base unit.')]);
        }

        return $this->resolveForUnit($product, $unit, $list, $at);
    }

    public function resolveForUnit(Product $product, ProductUnit $unit, PriceList $list, ?\DateTimeInterface $at = null): ResolvedListPrice
    {
        if ((int) $unit->product_id !== (int) $product->id || ! $unit->is_sale_unit) {
            throw ValidationException::withMessages(['product_unit' => __('The selected selling unit does not belong to this product.')]);
        }
        if (! $list->isEffective($at)) {
            throw ValidationException::withMessages(['price_list' => __('The selected price list is not active for this company and date.')]);
        }

        $baseList = PriceList::query()->where('company_id', $list->company_id)->where('list_number', 0)->first();
        if (! $baseList?->isEffective($at)) {
            throw ValidationException::withMessages(['price_list' => __('Create and activate Base Price List 0.')]);
        }
        $baseOverride = ProductPriceOverride::query()->where('company_id', $list->company_id)->where('price_list_id', $baseList->id)->where('product_id', $product->id)->where('product_unit_id', $unit->id)->first();
        $baseOverride = $baseOverride?->isEffective($at) ? $baseOverride : null;
        $base = $baseOverride !== null ? (string) $baseOverride->amount : ($unit->is_base_unit ? (string) $product->sale_price : '0');
        if (bccomp($this->normalized($base, 4), '0', 4) <= 0) {
            throw ValidationException::withMessages(['base_price' => __('A retail price is required for this selling unit.')]);
        }

        $override = ProductPriceOverride::query()->where('company_id', $list->company_id)->where('price_list_id', $list->id)->where('product_id', $product->id)->where('product_unit_id', $unit->id)->first();
        $active = $override?->isEffective($at) ? $override : null;
        $calculation = $list->isBase() ? ['pre_round' => $base, 'rounded' => $base] : $this->calculate($base, (string) $list->percentage_increase);
        $final = $active ? (string) $active->amount : $calculation['rounded'];

        return new ResolvedListPrice($list->id, $list->code, $base, $calculation['pre_round'], $final, $active !== null, $unit->id, $active?->id, $baseOverride?->id);
    }

    public function resolveWithBasePrice(Product $product, PriceList $list, string $base, ?\DateTimeInterface $at = null): ResolvedListPrice
    {
        $companyId = (int) $list->company_id;
        if (! $list->isEffective($at)) {
            throw ValidationException::withMessages(['price_list' => __('The selected price list is not active for this company and date.')]);
        }
        if (bccomp($this->normalized($base, 3), '0', 3) <= 0) {
            throw ValidationException::withMessages(['base_price' => __('Base consumer price is required and must be greater than zero.')]);
        }
        $calculation = $list->isBase() ? ['pre_round' => $base, 'rounded' => $base] : $this->calculate($base, (string) $list->percentage_increase);
        $unitId = $product->baseProductUnit()->value('id');
        $override = ProductPriceOverride::query()->where('company_id', $companyId)->where('price_list_id', $list->id)->where('product_id', $product->id)->where('product_unit_id', $unitId)->first();
        $active = $override?->isEffective($at) ? $override : null;

        return new ResolvedListPrice($list->id, $list->code, $base, $calculation['pre_round'], $active ? (string) $active->amount : $calculation['rounded'], $active !== null, $unitId ? (int) $unitId : null, $active?->id);
    }

    public function listForOutlet(Store $outlet, ?\DateTimeInterface $at = null): PriceList
    {
        if ($outlet->type !== 'selling') {
            throw ValidationException::withMessages(['store' => __('Only a Sales Outlet resolves a selling price list.')]);
        }
        $list = $outlet->priceList ?: $outlet->branch?->defaultPriceList;
        if (! $list?->isEffective($at)) {
            $list = PriceList::query()->where('company_id', $outlet->company_id)->where('list_number', 0)->first();
        }
        if (! $list?->isEffective($at)) {
            throw ValidationException::withMessages(['price_list' => __('Create and activate Base Price List 0.')]);
        }

        return $list;
    }

    public function assignmentIsValid(Store $outlet, ?\DateTimeInterface $at = null): bool
    {
        if ($outlet->type !== 'selling' || $outlet->status !== 'active') {
            return true;
        }
        if ($outlet->price_list_id !== null && ! $outlet->priceList?->isEffective($at)) {
            return false;
        }
        if ($outlet->price_list_id === null && $outlet->branch?->default_price_list_id !== null && ! $outlet->branch->defaultPriceList?->isEffective($at)) {
            return false;
        }
        try {
            $this->listForOutlet($outlet, $at);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalized(string $value, int $scale): string
    {
        if (! preg_match('/^\d+(?:\.(\d+))?$/', trim($value))) {
            throw ValidationException::withMessages(['price' => __('Enter a valid non-negative decimal value.')]);
        }
        $parts = explode('.', trim($value), 2);
        $whole = ltrim($parts[0], '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($parts[1] ?? '', 0, $scale), $scale, '0');
    }
}
