<?php

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CancelPurchaseOrderAction
{
    /**
     * Cancel a draft or submitted purchase order with a required reason.
     */
    public function execute(int $id, string $reasonText, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize('purchase_orders.cancel');

        $reasonText = trim($reasonText);
        if ($reasonText === '') {
            throw new InvalidArgumentException(__('A cancellation reason is required.'));
        }

        return DB::transaction(function () use ($id, $reasonText, $expectedVersion): PurchaseOrder {
            $userId = Auth::id();
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);
            $this->assertStoreScope($po->store_id);

            if ($expectedVersion !== null && $po->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before cancelling.'));
            }

            if (! in_array($po->status, ['draft', 'submitted'], true)) {
                throw new InvalidArgumentException(__('Only draft or submitted purchase orders can be cancelled.'));
            }

            $before = $po->only(['status', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'lock_version']);

            $po->update([
                'status' => 'cancelled',
                'cancel_reason' => $reasonText,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'updated_by' => $userId,
                'lock_version' => $po->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'cancel_purchase_order',
                source: $po,
                before: $before,
                after: $po->fresh()->only(['status', 'cancel_reason', 'cancelled_at', 'cancelled_by', 'lock_version']),
                branchId: $po->branch_id,
                storeId: $po->store_id,
                reasonText: $reasonText,
            );

            return $po->fresh(['supplier', 'store', 'lines.product']);
        });
    }

    private function assertStoreScope(?int $storeId): void
    {
        $user = Auth::user();
        if ($storeId === null || ! $user instanceof User || $user->is_super_admin) {
            return;
        }
        if (! Store::query()->visibleTo($user)->whereKey($storeId)->exists()) {
            throw new InvalidArgumentException(__('You are not authorized for the selected purchase-order store.'));
        }
    }
}
