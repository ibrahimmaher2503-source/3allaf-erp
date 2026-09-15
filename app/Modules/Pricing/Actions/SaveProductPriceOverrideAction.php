<?php

namespace App\Modules\Pricing\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Pricing\Models\ProductPriceOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveProductPriceOverrideAction
{
    public function execute(Product $product, PriceList $list, ?string $amount, string $reason, ?string $effectiveFrom = null, ?string $effectiveTo = null): ?ProductPriceOverride
    {
        Gate::authorize('pricing_lists.overrides');
        if ($list->isBase()) throw ValidationException::withMessages(['override' => __('Base Price List 0 uses the Product Card consumer price and cannot be overridden here.')]);
        return DB::transaction(function () use ($product, $list, $amount, $reason, $effectiveFrom, $effectiveTo) {
            $companyId = (int) Company::query()->where('status', 'active')->lockForUpdate()->value('id');
            $lockedList = PriceList::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($list->id);
            $override = ProductPriceOverride::query()->where('company_id', $companyId)->where('price_list_id', $lockedList->id)->where('product_id', $product->id)->lockForUpdate()->first();
            $before = $override?->toArray();
            if ($amount === null || trim($amount) === '') { $override?->delete(); app(RecordAuditEvent::class)->execute('pricing', 'remove_product_price_override', ProductPriceOverride::class, $before, null, reasonText: $reason, explicitSourceId: (string) ($before['id'] ?? '')); return null; }
            $override ??= new ProductPriceOverride(['company_id' => $companyId, 'price_list_id' => $lockedList->id, 'product_id' => $product->id, 'created_by' => auth()->id()]);
            $override->fill(['amount' => $amount, 'reason' => trim($reason), 'effective_from' => $effectiveFrom ?: null, 'effective_to' => $effectiveTo ?: null, 'updated_by' => auth()->id()])->save();
            app(RecordAuditEvent::class)->execute('pricing', $before ? 'update_product_price_override' : 'create_product_price_override', $override, $before, $override->fresh()->toArray(), reasonText: $reason);
            return $override;
        });
    }
}
