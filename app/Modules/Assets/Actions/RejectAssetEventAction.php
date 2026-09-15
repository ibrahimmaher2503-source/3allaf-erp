<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class RejectAssetEventAction
{
    public function execute(User $user, AssetEvent $event, string $reason): void
    {
        Gate::forUser($user)->authorize('rental_assets.approve');
        if (trim($reason) === '') throw ValidationException::withMessages(['reason' => __('A rejection reason is required.')]);
        DB::transaction(function () use ($user, $event, $reason): void {
            $locked = AssetEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $approval = $locked->approvalRecord()->lockForUpdate()->firstOrFail();
            if ($approval->requester_id === $user->id && ! $user->canBypassApproval()) throw ValidationException::withMessages(['approval' => __('The requester and approver must be different users.')]);
            $approval->transitionTo(ApprovalState::Rejected, ['approver_id' => $user->id, 'decided_at' => now(), 'decision_note' => $reason]);
            $locked->transition(['status' => 'rejected', 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_event_rejected', $locked, before: ['status' => 'submitted'], after: ['status' => 'rejected'], branchId: $locked->branch_id, storeId: $locked->store_id, reasonText: $reason, metadata: ['approval_record_id' => $approval->id]);
        }, 5);
    }
}
