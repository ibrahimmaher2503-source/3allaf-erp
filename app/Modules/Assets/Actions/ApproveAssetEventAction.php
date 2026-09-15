<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApproveAssetEventAction
{
    public function execute(User $user, AssetEvent $event): void
    {
        Gate::forUser($user)->authorize('rental_assets.approve');
        DB::transaction(function () use ($user, $event): void {
            $lockedEvent = AssetEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $approval = $lockedEvent->approvalRecord()->lockForUpdate()->firstOrFail();
            if ($approval->requester_id === $user->id && ! $user->canBypassApproval()) throw ValidationException::withMessages(['approval' => __('The requester and approver must be different users.')]);
            if ($approval->approval_state !== ApprovalState::Pending || $lockedEvent->status !== 'submitted') throw ValidationException::withMessages(['approval' => __('This asset event is no longer pending.')]);
            $asset = RentalAsset::query()->whereKey($lockedEvent->asset_id)->lockForUpdate()->firstOrFail();
            $before = $asset->only(['status', 'condition']);
            $approval->transitionTo(ApprovalState::Approved, ['approver_id' => $user->id, 'decided_at' => now(), 'decision_note' => 'Approved asset event.']);
            $lockedEvent->transition(['status' => 'approved', 'lock_version' => $lockedEvent->lock_version + 1]);
            if ($lockedEvent->event_type !== 'depreciation' && $lockedEvent->resulting_status !== null) $asset->mutate(['status' => $lockedEvent->resulting_status, 'updated_by' => $user->id, 'lock_version' => $asset->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_event_approved', $lockedEvent, before: $before + ['event_status' => 'submitted'], after: $asset->only(['status', 'condition']) + ['event_status' => 'approved'], branchId: $asset->branch_id, storeId: $asset->store_id, metadata: ['approval_record_id' => $approval->id, 'event_type' => $lockedEvent->event_type]);
            app(EvaluateAssetAlertsAction::class)->execute();
        }, 5);
    }
}
