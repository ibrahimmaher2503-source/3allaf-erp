<?php

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ApprovePurchaseOrderAction
{
    /**
     * Approve a submitted PO without posting stock, cost, invoice, or receipt effects.
     */
    public function execute(int $id, ?int $expectedVersion = null): PurchaseOrder
    {
        Gate::authorize('purchase_orders.approve');

        return DB::transaction(function () use ($id, $expectedVersion): PurchaseOrder {
            $approverId = Auth::id();
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($id);
            $this->assertStoreScope($order->store_id);

            if ($expectedVersion !== null && $order->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This purchase order was modified in another session. Please reload before approving.'));
            }

            if ($order->status === 'approved') {
                return $order->fresh(['supplier', 'store', 'creator', 'submitter', 'approver', 'lines.product']);
            }
            if ($order->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted purchase orders can be approved.'));
            }

            if ($order->submitted_by !== null && $order->submitted_by === $approverId && ! Auth::user()?->canBypassApproval()) {
                throw ValidationException::withMessages([
                    'approver' => __('A requester cannot approve their own purchase order.'),
                ]);
            }

            $before = $order->only(['status', 'submitted_by', 'approved_at', 'approved_by', 'lock_version']);

            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_orders')
                ->where('source_id', (string) $order->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            app(ApproveRequest::class)->execute($approval, (string) $order->lock_version, decisionNote: __('Purchase order approved.'));

            $order->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approverId,
                'updated_by' => $approverId,
                'lock_version' => $order->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'approve_purchase_order',
                source: $order,
                before: $before,
                after: $order->fresh()->only(['status', 'submitted_by', 'approved_at', 'approved_by', 'lock_version']),
                branchId: $order->branch_id,
                storeId: $order->store_id,
                metadata: ['stock_posting' => false, 'invoice_posting' => false, 'approval_record_id' => $approval->id],
            );

            return $order->fresh(['supplier', 'store', 'creator', 'submitter', 'approver', 'lines.product']);
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
