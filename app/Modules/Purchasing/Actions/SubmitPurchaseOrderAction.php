<?php

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SubmitPurchaseOrderAction
{
    /**
     * Submit a draft purchase order.
     */
    public function execute(int $id, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize('purchase_orders.edit');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseOrder {
            $userId = Auth::id();
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);
            $this->assertStoreScope($po->store_id);

            if ($expectedVersion !== null && $po->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before submitting.'));
            }

            if ($po->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft purchase orders can be submitted.'));
            }

            if ($po->lines()->count() === 0) {
                throw new InvalidArgumentException(__('Cannot submit a purchase order with no line items.'));
            }

            if ($po->supplier && $po->supplier->status !== 'active') {
                throw new InvalidArgumentException(__('Cannot submit a purchase order for an inactive supplier.'));
            }

            $before = $po->only(['status', 'lock_version', 'submitted_at', 'submitted_by']);

            $po->update([
                'status' => 'submitted',
                'submitted_at' => now(),
                'submitted_by' => $userId,
                'updated_by' => $userId,
                'lock_version' => $po->lock_version + 1,
            ]);

            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'purchase_orders',
                sourceId: (string) $po->id,
                sourceVersion: (string) $po->lock_version,
                requestedAction: 'approve',
                requestPermission: 'purchase_orders.edit',
                decisionPermission: 'purchase_orders.approve',
                branchId: $po->branch_id,
                storeId: $po->store_id,
                idempotencyKey: 'purchase-order-approval:'.$po->id.':'.$po->lock_version,
            ));

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'submit_purchase_order',
                source: $po,
                before: $before,
                after: $po->fresh()->only(['status', 'lock_version', 'submitted_at', 'submitted_by']),
                branchId: $po->branch_id,
                storeId: $po->store_id,
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
