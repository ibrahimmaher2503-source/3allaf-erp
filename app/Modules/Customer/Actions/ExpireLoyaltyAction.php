<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\LoyaltyPointAllocation;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ExpireLoyaltyAction
{
    public function execute(User $actor, Customer $customer, Store $store): int
    {
        Gate::forUser($actor)->authorize('loyalty.expire');

        return $this->expireDue($actor, $customer, $store, true);
    }

    public function expireDue(User $actor, Customer $customer, Store $store, bool $authorize = false): int
    {
        if ($authorize) {
            Gate::forUser($actor)->authorize('loyalty.expire');
        }
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);

        $expiry = CustomerPolicy::loyaltyExpiry();
        $roundingVersion = CustomerPolicy::assertRounding();
        $integrityVersion = CustomerPolicy::assertLedgerIntegrity();

        return DB::transaction(function () use ($actor, $customer, $store, $expiry, $roundingVersion, $integrityVersion): int {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            CustomerScope::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
            ], ['created_by' => $actor->id]);

            $entries = LoyaltyLedger::query()
                ->where('customer_id', $customer->id)
                ->where('points', '>', 0)
                ->whereIn('event_type', ['earn', 'adjustment'])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $created = 0;

            foreach ($entries as $earn) {
                $allocated = (int) LoyaltyPointAllocation::query()->where('earn_ledger_id', $earn->id)->sum('points');
                $remaining = max(0, (int) $earn->points - $allocated);
                if ($remaining === 0) {
                    continue;
                }

                $idempotencyKey = 'LOYALTY:EXPIRE:'.$earn->id;
                $existing = LoyaltyLedger::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    continue;
                }
                $before = (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points');
                $entry = LoyaltyLedger::query()->create([
                    'customer_id' => $customer->id,
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'activity' => $earn->activity,
                    'event_type' => 'expiry',
                    'points' => -$remaining,
                    'balance_before' => $before,
                    'balance_after' => $before - $remaining,
                    'effective_at' => now(),
                    'source_type' => LoyaltyLedger::class,
                    'source_id' => (string) $earn->id,
                    'source_reference' => $earn->source_reference,
                    'rule_key' => $earn->rule_key,
                    'rule_version' => $earn->rule_version.'|expiry-run:'.$expiry['version'].'|rounding:'.$roundingVersion.'|integrity:'.$integrityVersion,
                    'reason' => __('Loyalty points expired.'),
                    'created_by' => $actor->id,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => ['expired_earn_entry_id' => $earn->id, 'expired_points' => $remaining],
                    'created_at' => now(),
                ]);
                LoyaltyPointAllocation::query()->create(['debit_ledger_id' => $entry->id, 'earn_ledger_id' => $earn->id, 'points' => $remaining, 'created_at' => now()]);

                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'loyalty_expired',
                    source: $entry,
                    before: ['balance' => $before, 'earn_entry_id' => $earn->id, 'remaining_points' => $remaining],
                    after: $entry->only(['customer_id', 'event_type', 'points', 'balance_after', 'expires_at', 'source_type', 'source_id']),
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey],
                );
                $created++;
            }

            return $created;
        }, 5);
    }
}
