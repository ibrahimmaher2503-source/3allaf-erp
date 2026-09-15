<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EarnLoyaltyAction
{
    public function executeForSale(User $actor, Sale $sale): ?LoyaltyLedger
    {
        if ($sale->customer_id === null) {
            return null;
        }

        Gate::forUser($actor)->authorize('loyalty.earn');
        $sale->loadMissing('store', 'customer');
        $store = $sale->store;
        if (! $store instanceof Store || $sale->status !== 'approved' || $sale->customer === null) {
            return null;
        }
        abort_unless($actor->canAccessStore((int) $sale->store_id), 403);

        $existing = LoyaltyLedger::query()
            ->where('idempotency_key', 'SALE:'.$sale->id.':LOYALTY:EARN')
            ->first();
        if ($existing !== null) {
            abort_unless((int) $existing->customer_id === (int) $sale->customer_id && (string) $existing->source_id === (string) $sale->id, 409);

            return $existing;
        }

        $rule = CustomerPolicy::loyaltyRule('retail');
        $expiry = CustomerPolicy::loyaltyExpiry();
        $roundingVersion = CustomerPolicy::assertRounding();
        $integrityVersion = CustomerPolicy::assertLedgerIntegrity();
        $base = bcsub((string) $sale->subtotal, (string) $sale->discount_total, 8);
        $pointsString = bcdiv(bcmul($base, (string) $rule['value']['earn_points_per_currency'], 8), '1', 0);
        $points = max(0, (int) $pointsString);
        if ($points === 0) {
            return null;
        }

        return DB::transaction(function () use ($actor, $sale, $store, $rule, $expiry, $roundingVersion, $integrityVersion, $base, $points): LoyaltyLedger {
            $customer = $sale->customer()->lockForUpdate()->firstOrFail();
            CustomerScope::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
            ], ['created_by' => $actor->id]);

            $existing = LoyaltyLedger::query()->where('idempotency_key', 'SALE:'.$sale->id.':LOYALTY:EARN')->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $before = (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points');
            $entry = LoyaltyLedger::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'activity' => 'retail',
                'event_type' => 'earn',
                'points' => $points,
                'balance_before' => $before,
                'balance_after' => $before + $points,
                'effective_at' => now(),
                'expires_at' => now()->addDays($expiry['days']),
                'source_type' => Sale::class,
                'source_id' => (string) $sale->id,
                'source_reference' => $sale->document_number,
                'rule_key' => 'loyalty.retail_rule',
                'rule_version' => 'rule:'.$rule['version'].'|expiry:'.$expiry['version'].'|rounding:'.$roundingVersion.'|integrity:'.$integrityVersion,
                'created_by' => $actor->id,
                'idempotency_key' => 'SALE:'.$sale->id.':LOYALTY:EARN',
                'metadata' => ['basis_net_before_tax' => $base, 'earn_points_per_currency' => $rule['value']['earn_points_per_currency']],
                'created_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'loyalty_earned',
                source: $entry,
                before: ['balance' => $before],
                after: $entry->only(['customer_id', 'activity', 'event_type', 'points', 'balance_after', 'expires_at', 'source_type', 'source_id', 'rule_key', 'rule_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'idempotency_key' => $entry->idempotency_key, 'source_sale_id' => $sale->id],
            );

            return $entry;
        }, 5);
    }
}
