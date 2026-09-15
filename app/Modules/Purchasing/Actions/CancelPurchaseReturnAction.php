<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class CancelPurchaseReturnAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.cancel');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A cancellation reason is required.'));
        }

        return DB::transaction(function () use ($actor, $actorId, $id, $reason, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if (! in_array($return->status, ['draft', 'submitted'], true)) {
                throw new InvalidArgumentException(__('Only draft or submitted returns can be cancelled.'));
            }
            $before = $return->only(['status', 'lock_version']);
            $return->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancellation_reason' => $reason, 'updated_by' => $actorId, 'lock_version' => $return->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'procurement', event: 'cancel_supplier_return', source: $return, before: $before, after: $return->only(['status', 'cancelled_at', 'cancelled_by', 'lock_version']), storeId: $return->store_id, reasonText: $reason);

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }
}
