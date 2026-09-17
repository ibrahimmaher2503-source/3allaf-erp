<?php

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveProductPriceOverrideAction
{
    public function execute(Product $product, PriceList $list, ?string $amount, string $reason, ?string $effectiveFrom = null, ?string $effectiveTo = null, ?int $productUnitId = null, ?string $minimumPrice = null): ?ProductPriceOverride
    {
        Gate::authorize('pricing_lists.overrides');
        if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', (string) ($minimumPrice ?? '0'))) {
            throw ValidationException::withMessages(['minimum_price' => __('Enter a valid non-negative minimum selling price.')]);
        }

        return DB::transaction(function () use ($product, $list, $amount, $reason, $effectiveFrom, $effectiveTo, $productUnitId, $minimumPrice) {
            $companyId = (int) Company::query()->where('status', 'active')->lockForUpdate()->value('id');
            $lockedList = PriceList::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($list->id);
            $unit = ProductUnit::query()->where('product_id', $product->id)->where('is_sale_unit', true)->lockForUpdate()->findOrFail($productUnitId ?? $product->baseProductUnit()->value('id'));
            $unitBefore = $unit->only(['minimum_selling_price']);
            $unit->update(['minimum_selling_price' => $minimumPrice === null || trim($minimumPrice) === '' ? null : bcadd($minimumPrice, '0', 4)]);
            if ($unitBefore !== $unit->only(['minimum_selling_price'])) {
                app(RecordAuditEvent::class)->execute('pricing', 'update_minimum_selling_price', $unit, $unitBefore, $unit->only(['minimum_selling_price']), reasonText: $reason);
            }
            $override = ProductPriceOverride::query()->where('company_id', $companyId)->where('price_list_id', $lockedList->id)->where('product_id', $product->id)->where('product_unit_id', $unit->id)->lockForUpdate()->first();
            $before = $override?->toArray();
            if ($lockedList->isBase() && $unit->is_base_unit) {
                if (filled($amount) && bccomp((string) $amount, (string) $product->sale_price, 4) !== 0) {
                    throw ValidationException::withMessages(['override' => __('Change the base-unit retail price from the Product Card.')]);
                }
                $amount = null;
            }
            if ($amount === null || trim($amount) === '') {
                $override?->delete();
                app(RecordAuditEvent::class)->execute('pricing', 'remove_product_price_override', ProductPriceOverride::class, $before, null, reasonText: $reason, explicitSourceId: (string) ($before['id'] ?? ''));

                return null;
            }
            if (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', trim($amount)) || bccomp($amount, '0', 4) <= 0) {
                throw ValidationException::withMessages(['override' => __('Enter a valid positive selling price.')]);
            }
            $override ??= new ProductPriceOverride(['company_id' => $companyId, 'price_list_id' => $lockedList->id, 'product_id' => $product->id, 'product_unit_id' => $unit->id, 'created_by' => auth()->id()]);
            $override->fill(['amount' => $amount, 'reason' => trim($reason), 'effective_from' => $effectiveFrom ?: null, 'effective_to' => $effectiveTo ?: null, 'updated_by' => auth()->id()])->save();
            app(RecordAuditEvent::class)->execute('pricing', $before ? 'update_product_price_override' : 'create_product_price_override', $override, $before, $override->fresh()->toArray(), reasonText: $reason);

            return $override;
        });
    }
}
