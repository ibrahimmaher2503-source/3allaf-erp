<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CancelPurchaseInvoiceAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseInvoice
    {
        Gate::authorize('purchase_invoices_supplier_returns.cancel');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A cancellation reason is required.'));
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
            if (! in_array($invoice->status, ['draft', 'submitted', 'awaiting_distribution'], true)) {
                throw new InvalidArgumentException(__('Only draft or awaiting-distribution invoices can be cancelled.'));
            }
            $before = $invoice->only(['status', 'lock_version']);
            $invoice->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancel_reason' => $reason, 'lock_version' => $invoice->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'cancel_purchase_invoice', source: $invoice, before: $before, after: $invoice->only(['status', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'lock_version']), storeId: $invoice->store_id);

            return $invoice->fresh(['lines']);
        });
    }
}
