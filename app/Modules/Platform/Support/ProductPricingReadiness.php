<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

final class ProductPricingReadiness
{
    /** @return array<string,mixed> */
    public function snapshot(?int $companyId = null, int $displayLimit = 8): array
    {
        $companyId ??= (int) Company::query()->where('status', 'active')->value('id');
        $requestKey = 'product_pricing_readiness.'.$companyId.'.'.$displayLimit;
        if (app()->bound('request') && request()->attributes->has($requestKey)) {
            return request()->attributes->get($requestKey);
        }

        $version = (int) Cache::get('setup-readiness-version', 1);
        $cacheKey = sprintf('product-pricing-readiness:v%d:c%d:l%s:n%d', $version, $companyId, app()->getLocale(), $displayLimit);
        $snapshot = Cache::remember($cacheKey, now()->addMinutes(10), fn (): array => $this->buildSnapshot($companyId, $displayLimit));

        if (app()->bound('request')) {
            request()->attributes->set($requestKey, $snapshot);
        }

        return $snapshot;
    }

    /** @return array<string,mixed> */
    private function buildSnapshot(int $companyId, int $displayLimit): array
    {
        $products = Product::query()->sellable();
        $invalid = static fn (Builder $query): Builder => $query->where(function (Builder $issues): void {
            $issues->whereNull('sale_price')->orWhere('sale_price', '<=', 0)
                ->orWhereNull('item_code')->orWhere('item_code', '')
                ->orWhereNull('name_ar')->orWhere('name_ar', '')
                ->orWhereNull('model_number')->orWhere('model_number', '')
                ->orWhereNull('category_id')
                ->orWhereDoesntHave('category', fn (Builder $category): Builder => $category->where('status', 'active'))
                ->orWhereNull('barcode_registration_type')
                ->orWhereNotIn('barcode_registration_type', ['international', 'local']);
        });
        $counts = (clone $products)
            ->leftJoin('categories as readiness_categories', 'readiness_categories.id', '=', 'products.category_id')
            ->selectRaw("COUNT(*) AS applicable_count")
            ->selectRaw("SUM(CASE WHEN products.sale_price IS NULL OR products.sale_price <= 0 THEN 1 ELSE 0 END) AS invalid_price_count")
            ->selectRaw("SUM(CASE WHEN products.sale_price IS NULL OR products.sale_price <= 0 OR products.item_code IS NULL OR products.item_code = '' OR products.name_ar IS NULL OR products.name_ar = '' OR products.model_number IS NULL OR products.model_number = '' OR products.category_id IS NULL OR readiness_categories.status <> 'active' OR products.barcode_registration_type IS NULL OR products.barcode_registration_type NOT IN ('international', 'local') THEN 1 ELSE 0 END) AS affected_count")
            ->first();
        $applicableCount = (int) $counts->applicable_count;
        $affectedCount = (int) $counts->affected_count;
        $invalidPriceCount = (int) $counts->invalid_price_count;
        $affectedProducts = (clone $products)->tap($invalid)->orderBy('item_code')->limit($displayLimit)->get(['id', 'item_code', 'name_ar', 'name_en', 'sale_price'])
            ->map(fn (Product $product): array => [
                'id' => $product->id, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar,
                'name_en' => $product->name_en, 'sale_price' => $product->sale_price, 'issues' => ['incomplete_product_card'],
                'issue_message' => $product->sale_price === null || (float) $product->sale_price <= 0 ? __('Base consumer price is required and must be greater than zero.') : null,
            ])->all();
        $assessment = [
            'applicable_count' => $applicableCount,
            'affected_count' => $affectedCount,
            'invalid_price_count' => $invalidPriceCount,
            'affected_products' => $affectedProducts,
            'has_more_affected' => $affectedCount > $displayLimit,
            'all_affected_products' => $affectedProducts,
            'valid_price_count' => $applicableCount - $invalidPriceCount,
            'product_cards_complete' => $applicableCount > 0 && $affectedCount === 0,
            'pricing_products_complete' => $applicableCount > 0 && $affectedCount === 0,
        ];
        $base = $companyId > 0 ? PriceList::query()->where('company_id', $companyId)->where('list_number', 0)->first() : null;
        $overrideCount = $companyId > 0 ? ProductPriceOverride::query()->where('company_id', $companyId)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', today()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', today()))->count() : 0;

        return [
            ...$assessment,
            'company_id' => $companyId,
            'base_list_complete' => (bool) $base?->isEffective(),
            'pricing_prerequisite_complete' => $assessment['product_cards_complete'],
            'inherited_count' => $assessment['valid_price_count'],
            'effective_count' => $assessment['valid_price_count'],
            'manual_override_count' => $overrideCount,
        ];

    }

    /** @param iterable<int,Product|array<string,mixed>> $products @return array<string,mixed> */
    public function assessProducts(iterable $products, int $displayLimit = 8): array
    {
        $rows = collect($products)->map(function (Product|array $product): array {
            $value = static fn (string $key) => is_array($product) ? ($product[$key] ?? null) : $product->{$key};
            $categoryActive = is_array($product) ? (bool) ($product['category_active'] ?? true) : ($product->category?->status === 'active');
            $price = $value('sale_price');
            $issues = [];
            if ($price === null || ! is_numeric($price) || (float) $price <= 0) $issues[] = 'invalid_base_price';
            if (trim((string) $value('item_code')) === '') $issues[] = 'missing_item_code';
            if (trim((string) $value('name_ar')) === '') $issues[] = 'missing_arabic_name';
            if (trim((string) $value('model_number')) === '') $issues[] = 'missing_model_number';
            if (! $value('category_id') || ! $categoryActive) $issues[] = 'missing_active_category';
            if (! in_array((string) $value('barcode_registration_type'), ['international','local'], true)) $issues[] = 'missing_barcode_mode';

            return [
                'id' => (int) $value('id'),
                'item_code' => (string) $value('item_code'),
                'name_ar' => (string) $value('name_ar'),
                'name_en' => (string) $value('name_en'),
                'sale_price' => $price,
                'issues' => $issues,
                'issue_message' => in_array('invalid_base_price', $issues, true) ? __('Base consumer price is required and must be greater than zero.') : null,
            ];
        })->values();
        $affected = $rows->filter(fn (array $row): bool => $row['issues'] !== [])->values();
        $invalidPrices = $rows->filter(fn (array $row): bool => in_array('invalid_base_price', $row['issues'], true))->values();
        $complete = $rows->isNotEmpty() && $affected->isEmpty();

        return [
            'applicable_count' => $rows->count(),
            'affected_count' => $affected->count(),
            'invalid_price_count' => $invalidPrices->count(),
            'affected_products' => $affected->take($displayLimit)->all(),
            'has_more_affected' => $affected->count() > $displayLimit,
            'all_affected_products' => $affected->all(),
            'valid_price_count' => $rows->count() - $invalidPrices->count(),
            'product_cards_complete' => $complete,
            'pricing_products_complete' => $complete,
        ];
    }
}
