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
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RedeemLoyaltyAction
{
    public function __construct(private readonly ExpireLoyaltyAction $expiry) {}

    public function execute(User $actor, Customer $customer, Store $store, Sale $sourceSale, int $points, string $idempotencyKey): LoyaltyLedger
    {
        Gate::forUser($actor)->authorize('loyalty.redeem');
        if (app()->bound('request') && request()->header('X-Offline-Queue') !== null) {
            throw new InvalidArgumentException(__('Loyalty redemption is unavailable while the POS is offline.'));
        }
        abort_unless($points > 0, 422);
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);

        $sourceSale = Sale::query()->whereKey($sourceSale->id)->where('status', 'approved')->lockForUpdate()->firstOrFail();
        abort_unless((int) $sourceSale->customer_id === (int) $customer->id, 422);
        abort_unless(Sale::query()->visibleTo($actor)->whereKey($sourceSale->id)->exists(), 404);

        $rule = CustomerPolicy::loyaltyRule('retail');
        $roundingVersion = CustomerPolicy::assertRounding();
        $integrityVersion = CustomerPolicy::assertLedgerIntegrity();
        CustomerScope::query()->firstOrCreate([
            'customer_id' => $customer->id,
            'branch_id' => $store->branch_id,
            'store_id' => $store->id,
        ], ['created_by' => $actor->id]);
        $this->expiry->expireDue($actor, $customer, $store);

        return DB::transaction(function () use ($actor, $customer, $store, $sourceSale, $points, $idempotencyKey, $rule, $roundingVersion, $integrityVersion): LoyaltyLedger {
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $existing = LoyaltyLedger::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->customer_id !== (int) $customer->id || (int) $existing->points !== -$points) {
                    throw new InvalidArgumentException(__('This redemption idempotency key was already used with a different payload.'));
                }

                return $existing;
            }
            $sourceDuplicate = LoyaltyLedger::query()
                ->where('source_type', Sale::class)
                ->where('source_id', (string) $sourceSale->id)
                ->where('event_type', 'redeem')
                ->first();
            if ($sourceDuplicate !== null) {
                throw new InvalidArgumentException(__('This approved sale already has a loyalty redemption.'));
            }

            $balance = (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points');
            if ($points > $balance) {
                throw new InvalidArgumentException(__('The customer does not have enough available loyalty points.'));
            }

            $earnings = LoyaltyLedger::query()
                ->where('customer_id', $customer->id)
                ->where('points', '>', 0)
                ->whereIn('event_type', ['earn', 'adjustment'])
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByRaw('expires_at IS NULL')
                ->orderBy('expires_at')
                ->orderBy('effective_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remainingToAllocate = $points;
            $allocations = [];
            foreach ($earnings as $earn) {
                $used = (int) LoyaltyPointAllocation::query()->where('earn_ledger_id', $earn->id)->sum('points');
                $available = max(0, (int) $earn->points - $used);
                if ($available === 0) {
                    continue;
                }
                $allocated = min($remainingToAllocate, $available);
                $allocations[] = [$earn->id, $allocated];
                $remainingToAllocate -= $allocated;
                if ($remainingToAllocate === 0) {
                    break;
                }
            }
            if ($remainingToAllocate !== 0) {
                throw new InvalidArgumentException(__('The customer does not have enough unexpired loyalty points.'));
            }

            $entry = LoyaltyLedger::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'activity' => 'retail',
                'event_type' => 'redeem',
                'points' => -$points,
                'balance_before' => $balance,
                'balance_after' => $balance - $points,
                'effective_at' => now(),
                'source_type' => Sale::class,
                'source_id' => (string) $sourceSale->id,
                'source_reference' => $sourceSale->document_number,
                'rule_key' => 'loyalty.retail_rule',
                'rule_version' => 'rule:'.$rule['version'].'|rounding:'.$roundingVersion.'|integrity:'.$integrityVersion,
                'created_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'metadata' => [
                    'redeemed_points' => $points,
                    'redeemed_currency_value' => bcmul((string) $points, (string) $rule['value']['redeem_currency_per_point'], 8),
                    'allocation_count' => count($allocations),
                ],
                'created_at' => now(),
            ]);
            foreach ($allocations as [$earnId, $allocated]) {
                LoyaltyPointAllocation::query()->create(['debit_ledger_id' => $entry->id, 'earn_ledger_id' => $earnId, 'points' => $allocated, 'created_at' => now()]);
            }
            CustomerScope::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
            ], ['created_by' => $actor->id]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'loyalty_redeemed',
                source: $entry,
                before: ['balance' => $balance],
                after: $entry->only(['customer_id', 'activity', 'event_type', 'points', 'balance_after', 'source_type', 'source_id', 'rule_key', 'rule_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'allocation_count' => count($allocations)],
            );

            return $entry;
        }, 5);
    }
}
