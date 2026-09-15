<?php

namespace App\Modules\Pricing\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
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
        return $this->resolveWithBasePrice($product, $list, (string) $product->sale_price, $at);
    }

    public function resolveWithBasePrice(Product $product, PriceList $list, string $base, ?\DateTimeInterface $at = null): ResolvedListPrice
    {
        $companyId = (int) Company::query()->where('status', 'active')->value('id');
        if (! $list->isEffective($at) || $list->company_id !== $companyId) throw ValidationException::withMessages(['price_list' => __('The selected price list is not active for this company and date.')]);
        if (bccomp($this->normalized($base, 3), '0', 3) <= 0) throw ValidationException::withMessages(['base_price' => __('Base consumer price is required and must be greater than zero.')]);
        $calculation = $list->isBase() ? ['pre_round' => $base, 'rounded' => $base] : $this->calculate($base, (string) $list->percentage_increase);
        $override = ProductPriceOverride::query()->where('company_id', $companyId)->where('price_list_id', $list->id)->where('product_id', $product->id)->first();
        $active = $override?->isEffective($at) ? $override : null;
        return new ResolvedListPrice($list->id, $list->code, $base, $calculation['pre_round'], $active ? (string) $active->amount : $calculation['rounded'], $active !== null);
    }

    public function listForOutlet(Store $outlet, ?\DateTimeInterface $at = null): PriceList
    {
        if ($outlet->type !== 'selling') throw ValidationException::withMessages(['store' => __('Only a Sales Outlet resolves a selling price list.')]);
        $list = $outlet->priceList ?: $outlet->branch?->defaultPriceList;
        if (! $list?->isEffective($at)) $list = PriceList::query()->where('company_id', $outlet->company_id)->where('list_number', 0)->first();
        if (! $list?->isEffective($at)) throw ValidationException::withMessages(['price_list' => __('Create and activate Base Price List 0.')]);
        return $list;
    }

    public function assignmentIsValid(Store $outlet, ?\DateTimeInterface $at = null): bool
    {
        if ($outlet->type !== 'selling' || $outlet->status !== 'active') return true;
        if ($outlet->price_list_id !== null && ! $outlet->priceList?->isEffective($at)) return false;
        if ($outlet->price_list_id === null && $outlet->branch?->default_price_list_id !== null && ! $outlet->branch->defaultPriceList?->isEffective($at)) return false;
        try { $this->listForOutlet($outlet, $at); return true; } catch (\Throwable) { return false; }
    }

    private function normalized(string $value, int $scale): string
    {
        if (! preg_match('/^\d+(?:\.(\d+))?$/', trim($value))) throw ValidationException::withMessages(['price' => __('Enter a valid non-negative decimal value.')]);
        $parts = explode('.', trim($value), 2);
        $whole = ltrim($parts[0], '0');
        return ($whole === '' ? '0' : $whole).'.'.str_pad(substr($parts[1] ?? '', 0, $scale), $scale, '0');
    }
}
