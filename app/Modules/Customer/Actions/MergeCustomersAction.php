<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerMergeEvent;
use App\Modules\Customer\Models\CustomerScope;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class MergeCustomersAction
{
    public function execute(User $actor, Customer $duplicate, Customer $survivor, Store $store, string $reason, string $idempotencyKey): Customer
    {
        Gate::forUser($actor)->authorize('customers.merge');
        abort_unless($store->status === 'active' && $actor->canAccessStore((int) $store->id), 403);
        abort_unless($duplicate->id !== $survivor->id, 422);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($duplicate->id)->exists(), 404);
        abort_unless(Customer::query()->visibleFrom($actor, (int) $store->branch_id, (int) $store->id)->whereKey($survivor->id)->exists(), 404);
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A merge reason is required.'));
        }

        try {
            return DB::transaction(function () use ($actor, $duplicate, $survivor, $store, $reason, $idempotencyKey): Customer {
            $event = CustomerMergeEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($event !== null) {
                return Customer::query()->findOrFail($event->survivor_customer_id);
            }

            $duplicate = Customer::query()->lockForUpdate()->findOrFail($duplicate->id);
            $survivor = Customer::query()->lockForUpdate()->findOrFail($survivor->id);
            if ($duplicate->status !== 'active' || $survivor->status !== 'active') {
                throw new InvalidArgumentException(__('Only active customer profiles can be merged.'));
            }

            if ($duplicate->sales()->exists()
                || $duplicate->loyaltyLedger()->exists()
                || $duplicate->children()->exists()
                || $duplicate->productWalletLedger()->exists()
                || $duplicate->partyWalletLedger()->exists()
                || $duplicate->productWalletAdjustments()->exists()
                || $duplicate->partyWalletAdjustments()->exists()) {
                throw new InvalidArgumentException(__('This merge is unsafe because the duplicate already has sales, loyalty, wallet, or child history. Use a reviewed correction workflow.'));
            }

            $duplicate->mutateMaster([
                'status' => 'merged',
                'merged_into_id' => $survivor->id,
                'updated_by' => $actor->id,
                'lock_version' => ((int) $duplicate->lock_version) + 1,
            ]);

            foreach ($duplicate->scopes()->get() as $scope) {
                CustomerScope::query()->firstOrCreate([
                    'customer_id' => $survivor->id,
                    'branch_id' => $scope->branch_id,
                    'store_id' => $scope->store_id,
                ], ['created_by' => $actor->id]);
            }

            $merge = CustomerMergeEvent::query()->create([
                'duplicate_customer_id' => $duplicate->id,
                'survivor_customer_id' => $survivor->id,
                'reason' => $reason,
                'merged_by' => $actor->id,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'idempotency_key' => $idempotencyKey,
                'created_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'customer_value',
                event: 'customer_merged',
                source: $merge,
                before: ['duplicate_customer_id' => $duplicate->id, 'duplicate_status' => 'active'],
                after: ['survivor_customer_id' => $survivor->id, 'duplicate_status' => 'merged'],
                branchId: (int) $store->branch_id,
                storeId: (int) $store->id,
                reasonText: $reason,
                metadata: ['actor_id' => $actor->id, 'idempotency_key' => $idempotencyKey, 'history_preserved' => true],
            );

            return $survivor->fresh(['scopes']);
            }, 5);
        } catch (InvalidArgumentException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'unsafe')) {
                app(RecordAuditEvent::class)->execute(
                    category: 'customer_value',
                    event: 'customer_merge_blocked',
                    source: $duplicate,
                    before: ['status' => $duplicate->status],
                    after: ['status' => $duplicate->status],
                    branchId: (int) $store->branch_id,
                    storeId: (int) $store->id,
                    reasonText: $reason,
                    metadata: ['survivor_customer_id' => $survivor->id, 'unsafe_activity_or_child_history' => true, 'actor_id' => $actor->id],
                );
            }

            throw $exception;
        }
    }
}
