<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitPurchaseInvoiceAction
{
    public function execute(int $id, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.edit');

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        return DB::transaction(function () use ($actor, $actorId, $id, $expectedVersion): PurchaseInvoice {
            $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session. Please reload before submitting.'));
            }
            if ($invoice->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft purchase invoices can be submitted.'));
            }
            if ($invoice->lines()->doesntExist()) {
                throw new InvalidArgumentException(__('A purchase invoice must contain at least one line item.'));
            }

            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update([
                'status' => 'awaiting_distribution',
                'submitted_at' => now(),
                'submitted_by' => $actorId,
                'lock_version' => $invoice->lock_version + 1,
            ]);
            $branchId = Store::query()->whereKey($invoice->store_id)->value('branch_id');
            app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'purchase_invoices',
                sourceId: (string) $invoice->id,
                sourceVersion: (string) $invoice->lock_version,
                requestedAction: 'approve',
                requestPermission: 'purchase_invoices_supplier_returns.edit',
                decisionPermission: 'purchase_invoices_supplier_returns.approve',
                branchId: $branchId === null ? null : (int) $branchId,
                storeId: $invoice->store_id,
                idempotencyKey: 'purchase-invoice-approval:'.$invoice->id.':'.$invoice->lock_version,
            ));
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'submit_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'submitted_at', 'lock_version']), storeId: $invoice->store_id);

            return $invoice->fresh(['supplier', 'store', 'lines.product']);
        });
    }
}
