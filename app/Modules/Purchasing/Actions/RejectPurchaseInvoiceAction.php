<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RejectRequest;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RejectPurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A rejection reason is required.'));
        }

        return DB::transaction(function () use ($actor, $actorId, $id, $reason, $expectedVersion): PurchaseInvoice {
            $visibleStoreIds = Store::query()->visibleTo($actor)->select('id');
            $invoice = PurchaseInvoice::query()
                ->whereIn('store_id', $visibleStoreIds)
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $invoice->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This invoice was modified in another session.'));
            }
            if (! in_array($invoice->status, ['submitted', 'awaiting_distribution'], true)) {
                throw new InvalidArgumentException(__('Only invoices awaiting distribution can be rejected.'));
            }
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_invoices')
                ->where('source_id', (string) $invoice->id)
                ->where('requested_action', 'approve')
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();
            app(RejectRequest::class)->execute($approval, (string) $invoice->lock_version, $reason);
            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => $actorId, 'rejection_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'reject_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'rejected_at', 'rejected_by', 'rejection_reason', 'lock_version']), storeId: $invoice->store_id, reasonText: $reason, metadata: ['approval_record_id' => $approval->id]);

            return $invoice->fresh(['lines']);
        });
    }
}
