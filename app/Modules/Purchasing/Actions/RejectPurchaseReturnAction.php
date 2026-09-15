<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class RejectPurchaseReturnAction
{
    public function execute(int $id, string $reason, ?int $expectedVersion = null): PurchaseReturn
    {
        Gate::authorize('purchase_returns.approve');
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);
        $actorId = (int) $actor->getAuthIdentifier();

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A rejection reason is required.'));
        }

        return DB::transaction(function () use ($actor, $actorId, $id, $reason, $expectedVersion): PurchaseReturn {
            $return = PurchaseReturn::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->lockForUpdate()
                ->findOrFail($id);
            if ($expectedVersion !== null && $return->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This supplier return was modified in another session.'));
            }
            if ($return->status !== 'submitted') {
                throw new InvalidArgumentException(__('Only submitted supplier returns can be rejected.'));
            }
            if ($return->created_by === $actorId && ! $actor->canBypassApproval()) {
                throw new InvalidArgumentException(__('The supplier return creator cannot reject the same return.'));
            }
            $approval = ApprovalRecord::query()
                ->where('source_type', 'purchase_returns')
                ->where('source_id', (string) $return->id)
                ->where('requested_action', 'approve')
                ->where('store_id', $return->store_id)
                ->where('approval_state', ApprovalState::Pending->value)
                ->lockForUpdate()
                ->firstOrFail();

            app(ApprovalRecordTransition::class)->execute(
                record: $approval,
                state: ApprovalState::Rejected,
                event: 'approval_rejected',
                attributes: ['approver_id' => $actorId, 'decision_note' => $reason, 'decided_at' => now()],
                expectedSourceVersion: (string) $return->lock_version,
                authorize: function (ApprovalRecord $locked) use ($actor): mixed {
                    if ($locked->requester_id === $actor->id && ! $actor->canBypassApproval()) {
                        throw ValidationException::withMessages(['approver' => __('A requester cannot reject their own request.')]);
                    }

                    return Gate::forUser($actor)->authorize('decide', $locked);
                },
            );
            $before = $return->only(['status', 'lock_version']);
            $return->update(['status' => 'rejected', 'rejected_at' => now(), 'rejected_by' => $actorId, 'rejection_reason' => $reason, 'updated_by' => $actorId, 'lock_version' => $return->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(
                category: 'procurement',
                event: 'reject_supplier_return',
                source: $return,
                before: $before,
                after: $return->only(['status', 'rejected_at', 'rejected_by', 'lock_version']),
                storeId: $return->store_id,
                reasonText: $reason,
                metadata: ['approval_record_id' => $approval->id],
            );

            return $return->fresh(['supplier', 'store', 'reason', 'purchaseInvoice', 'lines.product']);
        });
    }
}
