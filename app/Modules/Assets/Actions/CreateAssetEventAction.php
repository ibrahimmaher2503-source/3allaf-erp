<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateAssetEventAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, RentalAsset $asset, array $data): AssetEvent
    {
        Gate::forUser($user)->authorize('rental_assets.create');
        abort_unless($user->is_super_admin || $user->canAccessBranch($asset->branch_id) || $user->canAccessStore($asset->store_id), 403);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = AssetEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_unless((int) $existing->asset_id === (int) $asset->id, 409);
                return $existing;
            }
        }
        if (! in_array($data['event_type'] ?? '', ['damage', 'loss', 'maintenance', 'depreciation'], true)) throw ValidationException::withMessages(['event_type' => __('The asset event type is invalid.')]);
        if (trim((string) ($data['assessment'] ?? '')) === '') throw ValidationException::withMessages(['assessment' => __('An assessment is required.')]);
        $resultingStatus = $data['resulting_status'] ?? null;
        if ($data['event_type'] !== 'depreciation' && ! in_array($resultingStatus, ['damaged', 'lost', 'under_maintenance', 'available', 'retired'], true)) throw ValidationException::withMessages(['resulting_status' => __('A valid resulting asset state is required.')]);
        if (filled($data['cost_value'] ?? null)) Gate::forUser($user)->authorize('rental_assets.cost_edit');

        return DB::transaction(function () use ($user, $asset, $data, $resultingStatus, $idempotencyKey): AssetEvent {
            $locked = RentalAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $event = AssetEvent::create([
                'asset_id' => $locked->id, 'branch_id' => $locked->branch_id, 'store_id' => $locked->store_id,
                'event_type' => $data['event_type'], 'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null,
                'party_reference' => $data['party_reference'] ?? null, 'assessment' => trim((string) $data['assessment']),
                'responsible_user_id' => (int) ($data['responsible_user_id'] ?? $user->id), 'cost_value' => $data['cost_value'] ?? null,
                'cost_currency' => $data['cost_currency'] ?? 'EGP', 'resulting_status' => $resultingStatus, 'status' => 'submitted',
                'evidence_attachment_id' => $data['evidence_attachment_id'] ?? null, 'idempotency_key' => $idempotencyKey,
                'metadata' => ['submitted_status' => $locked->status],
            ]);
            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'asset_events', sourceId: (string) $event->id, sourceVersion: (string) $event->lock_version,
                requestedAction: 'approve_asset_event', requestPermission: 'rental_assets.create', branchId: $locked->branch_id,
                storeId: $locked->store_id, reasonCode: $data['event_type'], reasonText: $data['assessment'],
                sourceHash: hash('sha256', json_encode($event->only(['asset_id', 'event_type', 'assessment', 'cost_value', 'resulting_status']), JSON_THROW_ON_ERROR)),
                idempotencyKey: 'asset-approval:'.$data['idempotency_key'], decisionPermission: 'rental_assets.approve',
            ));
            $event->transition(['approval_record_id' => $approval->id]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_event_submitted', $event, after: $event->only(['asset_id', 'event_type', 'status', 'resulting_status', 'cost_value', 'approval_record_id']), branchId: $locked->branch_id, storeId: $locked->store_id, reasonCode: $data['event_type']);
            return $event;
        }, 5);
    }
}
