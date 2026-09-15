<?php

declare(strict_types=1);

namespace App\Modules\Customer\Actions;

use App\Models\User;
use App\Modules\Customer\Models\ProductWalletAdjustment;
use App\Modules\Customer\Support\WalletPolicy;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectProductWalletAdjustmentAction
{
    public function execute(User $approver, ApprovalRecord $approval, string $note): void
    {
        Gate::forUser($approver)->authorize('decide', $approval);
        abort_unless($approval->source_type === 'product_wallet_adjustments' && $approval->decisionPermission() === 'product_wallet.approve', 403);
        $note = trim($note);
        if ($note === '') {
            throw ValidationException::withMessages(['decision_note' => __('A rejection reason is required.')]);
        }

        DB::transaction(function () use ($approver, $approval, $note): void {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            Gate::forUser($approver)->authorize('decide', $approval);
            $adjustment = ProductWalletAdjustment::query()->lockForUpdate()->findOrFail((int) $approval->source_id);
            if ((int) $adjustment->requested_by === (int) $approver->id && ! $approver->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The requester cannot reject the same Product Wallet adjustment.')]);
            }
            app(ApprovalRecordTransition::class)->execute(
                $approval, ApprovalState::Rejected, 'approval_rejected',
                ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => $note],
                expectedSourceVersion: (string) $adjustment->lock_version,
                expectedSourceHash: (string) $approval->source_hash,
                authorize: static function (ApprovalRecord $record) use ($approver): void {
                    Gate::forUser($approver)->authorize('decide', $record);
                },
            );
            $adjustment->transition(['status' => 'rejected', 'approved_by' => $approver->id, 'approved_at' => now(), 'decision_note' => $note]);
            app(RecordAuditEvent::class)->execute(
                category: 'customer_value', event: 'product_wallet_adjustment_rejected', source: $adjustment,
                before: ['status' => 'pending'], after: ['status' => 'rejected', 'decision_note' => $note],
                branchId: (int) $adjustment->branch_id, storeId: (int) $adjustment->store_id,
                reasonText: $note, metadata: ['wallet' => 'product', 'approval_record_id' => $approval->id, 'customer_id' => $adjustment->customer_id],
            );
        }, 5);
    }
}
