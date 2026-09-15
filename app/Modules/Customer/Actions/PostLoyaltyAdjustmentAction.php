<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Models\LoyaltyLedger;
use App\Modules\Customer\Models\LoyaltyPointAllocation;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostLoyaltyAdjustmentAction
{
    public function __construct(private readonly ExpireLoyaltyAction $expiry) {}

    public function execute(User $actor, LoyaltyAdjustment $adjustment, Store $store): LoyaltyLedger
    {
        $expiry = CustomerPolicy::loyaltyExpiry();
        $roundingVersion = CustomerPolicy::assertRounding();
        $integrityVersion = CustomerPolicy::assertLedgerIntegrity();

        // Remove due points before a sensitive negative adjustment so the
        // adjustment cannot consume an already-expired balance.
        $this->expiry->expireDue($actor, $adjustment->customer, $store);

        return DB::transaction(function () use ($actor, $adjustment, $store, $expiry, $roundingVersion, $integrityVersion): LoyaltyLedger {
            $adjustment = LoyaltyAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->status !== 'pending') {
                $existing = LoyaltyLedger::query()->where('idempotency_key', 'LOYALTY:ADJUST:'.$adjustment->id)->first();
                if ($existing !== null) {
                    return $existing;
                }
                throw new InvalidArgumentException(__('This loyalty adjustment is no longer pending.'));
            }
            $customer = $adjustment->customer()->lockForUpdate()->firstOrFail();
            $before = (int) LoyaltyLedger::query()->where('customer_id', $customer->id)->sum('points');
            if ($adjustment->points < 0 && abs((int) $adjustment->points) > $before) {
                throw new InvalidArgumentException(__('This adjustment would make the loyalty balance negative.'));
            }

            $idempotencyKey = 'LOYALTY:ADJUST:'.$adjustment->id;
            $existing = LoyaltyLedger::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }

            $entry = LoyaltyLedger::query()->create([
                'customer_id' => $customer->id,
                'branch_id' => $adjustment->branch_id ?? $store->branch_id,
                'store_id' => $adjustment->store_id ?? $store->id,
                'activity' => $adjustment->activity,
                'event_type' => 'adjustment',
                'points' => $adjustment->points,
                'balance_before' => $before,
                'balance_after' => $before + (int) $adjustment->points,
                'effective_at' => now(),
                'expires_at' => $adjustment->points > 0 ? now()->addDays($expiry['days']) : null,
                'source_type' => LoyaltyAdjustment::class,
                'source_id' => (string) $adjustment->id,
                'source_reference' => $adjustment->source_reference,
                'rule_key' => 'loyalty.approval_policy',
                'rule_version' => 'expiry:'.$expiry['version'].'|rounding:'.$roundingVersion.'|integrity:'.$integrityVersion,
                'reason' => $adjustment->reason,
                'created_by' => $actor->id,
                'approval_record_id' => $adjustment->approval_record_id,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['adjustment_id' => $adjustment->id, 'approved_by' => $actor->id],
                'created_at' => now(),
            ]);

            if ($adjustment->points < 0) {
                $remainingToAllocate = abs((int) $adjustment->points);
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

                foreach ($earnings as $earn) {
                    $allocated = (int) LoyaltyPointAllocation::query()->where('earn_ledger_id', $earn->id)->sum('points');
                    $available = max(0, (int) $earn->points - $allocated);
                    if ($available === 0) {
                        continue;
                    }
                    $portion = min($remainingToAllocate, $available);
                    LoyaltyPointAllocation::query()->create([
                        'debit_ledger_id' => $entry->id,
                        'earn_ledger_id' => $earn->id,
                        'points' => $portion,
                        'created_at' => now(),
                    ]);
                    $remainingToAllocate -= $portion;
                    if ($remainingToAllocate === 0) {
                        break;
                    }
                }

                if ($remainingToAllocate !== 0) {
                    throw new InvalidArgumentException(__('This negative adjustment cannot be reconciled against available loyalty points.'));
                }
            }

            $adjustment->transition([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'lock_version' => ((int) $adjustment->lock_version) + 1,
            ]);
            CustomerScope::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'branch_id' => $entry->branch_id,
                'store_id' => $entry->store_id,
            ], ['created_by' => $actor->id]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'loyalty_adjustment_posted',
                source: $entry,
                before: ['balance' => $before, 'adjustment_status' => 'pending'],
                after: $entry->only(['customer_id', 'event_type', 'points', 'balance_after', 'source_type', 'source_id', 'approval_record_id']),
                branchId: (int) $entry->branch_id,
                storeId: (int) $entry->store_id,
                reasonText: $adjustment->reason,
                metadata: ['actor_id' => $actor->id, 'approval_record_id' => $adjustment->approval_record_id, 'idempotency_key' => $idempotencyKey],
            );

            return $entry;
        }, 5);
    }
}
