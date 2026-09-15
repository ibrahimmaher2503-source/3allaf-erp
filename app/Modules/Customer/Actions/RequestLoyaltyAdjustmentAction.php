<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Customer\Models\LoyaltyAdjustment;
use App\Modules\Customer\Support\CustomerPolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RequestLoyaltyAdjustmentAction
{
    public function execute(User $actor, Customer $customer, Store $store, int $points, string $reason, string $idempotencyKey, ?string $sourceReference = null): LoyaltyAdjustment
    {
        Gate::forUser($actor)->authorize('loyalty.adjust');
        CustomerPolicy::assertApproval();
        CustomerPolicy::assertLedgerIntegrity();
        abort_unless($points !== 0, 422);
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($customer->id)->exists(), 404);
        abort_unless($customer->status === 'active', 404);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A loyalty adjustment reason is required.'));
        }

        $sourceReference = filled($sourceReference) ? trim((string) $sourceReference) : null;

        return DB::transaction(function () use ($actor, $customer, $store, $points, $reason, $idempotencyKey, $sourceReference): LoyaltyAdjustment {
            $existing = LoyaltyAdjustment::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->customer_id !== (int) $customer->id || (int) $existing->points !== $points || $existing->reason !== $reason) {
                    throw new InvalidArgumentException(__('This adjustment idempotency key was already used with a different payload.'));
                }

                return $existing;
            }

            CustomerScope::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
            ], ['created_by' => $actor->id]);

            $adjustment = LoyaltyAdjustment::query()->create([
                'customer_id' => $customer->id,
                'activity' => 'retail',
                'points' => $points,
                'reason' => $reason,
                'source_reference' => $sourceReference,
                'status' => 'pending',
                'requested_by' => $actor->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'idempotency_key' => $idempotencyKey,
                'lock_version' => 1,
            ]);

            $sourceHash = hash('sha256', json_encode([
                'customer_id' => $customer->id,
                'points' => $points,
                'reason' => $reason,
                'source_reference' => $sourceReference,
                'lock_version' => $adjustment->lock_version,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'loyalty_adjustments',
                sourceId: (string) $adjustment->id,
                sourceVersion: (string) $adjustment->lock_version,
                requestedAction: 'adjust',
                requestPermission: 'loyalty.adjust',
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                reasonCode: 'manual_loyalty_adjustment',
                reasonText: $reason,
                limitContext: ['points' => $points, 'activity' => 'retail'],
                sourceHash: $sourceHash,
                idempotencyKey: 'LOYALTY-ADJUST-APPROVAL:'.$idempotencyKey,
                decisionPermission: 'loyalty.approve',
            ));
            $adjustment->update(['approval_record_id' => $approval->id]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'loyalty_adjustment_requested',
                source: $adjustment,
                after: $adjustment->only(['customer_id', 'activity', 'points', 'reason', 'status', 'approval_record_id', 'lock_version']),
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                reasonText: $reason,
                metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'approval_record_id' => $approval->id],
            );

            return $adjustment->fresh(['approvalRecord']);
        }, 5);
    }
}
