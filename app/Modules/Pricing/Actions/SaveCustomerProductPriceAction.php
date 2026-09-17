<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Pricing\Models\CustomerProductPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SaveCustomerProductPriceAction
{
    public function execute(User $actor, Customer $customer, Product $product, ProductUnit $unit, ?string $price, string $reason, ?string $effectiveFrom = null, ?string $effectiveTo = null): ?CustomerProductPrice
    {
        Gate::forUser($actor)->authorize('pricing_lists.overrides');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reason is required for a customer special price.'));
        }
        if ($price !== null && (! preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,4})?$/', trim($price)) || bccomp($price, '0', 4) <= 0)) {
            throw new InvalidArgumentException(__('The customer special price must be a positive decimal.'));
        }
        $from = Carbon::parse($effectiveFrom ?: today()->toDateString())->startOfDay();
        $to = filled($effectiveTo) ? Carbon::parse($effectiveTo)->startOfDay() : null;
        if ($from->isFuture()) {
            throw new InvalidArgumentException(__('Future customer special prices are not supported in this focused phase.'));
        }
        if ($to !== null && $to->lt($from)) {
            throw new InvalidArgumentException(__('The special-price end date must be on or after its start date.'));
        }

        return DB::transaction(function () use ($actor, $customer, $product, $unit, $price, $reason, $from, $to): ?CustomerProductPrice {
            $locked = Customer::query()->with('createdStore')->lockForUpdate()->findOrFail($customer->id);
            $companyId = (int) $locked->createdStore?->company_id;
            if ($companyId <= 0 || (int) $unit->product_id !== (int) $product->id || ! $unit->is_sale_unit) {
                throw new InvalidArgumentException(__('The selected product, selling unit, and customer company do not match.'));
            }
            $activeKey = implode(':', [$companyId, $locked->id, $product->id, $unit->id]);
            $current = CustomerProductPrice::query()->where('active_key', $activeKey)->lockForUpdate()->first();
            if ($current !== null) {
                $current->update(['status' => 'expired', 'active_key' => null, 'expired_by' => $actor->id, 'expired_at' => now(), 'effective_to' => $current->effective_to?->isPast() ? $current->effective_to : today()]);
                app(RecordAuditEvent::class)->execute('pricing', 'expire_customer_special_price', $current, after: $current->fresh()->toArray(), reasonText: $reason);
            }
            if ($price === null || trim($price) === '') {
                return null;
            }
            $special = CustomerProductPrice::query()->create([
                'company_id' => $companyId, 'customer_id' => $locked->id, 'product_id' => $product->id, 'product_unit_id' => $unit->id,
                'price' => bcadd($price, '0', 4), 'effective_from' => $from, 'effective_to' => $to, 'status' => 'active', 'active_key' => $activeKey,
                'reason' => $reason, 'created_by' => $actor->id, 'approved_by' => $actor->id, 'approved_at' => now(),
            ]);
            app(RecordAuditEvent::class)->execute('pricing', 'create_customer_special_price', $special, after: $special->toArray(), reasonText: $reason);

            return $special;
        });
    }
}
