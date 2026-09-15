<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class InspectAssetAction
{
    public function execute(User $user, RentalAsset $asset, AssetReturn $return, string $resultingStatus, string $assessment): AssetEvent
    {
        Gate::forUser($user)->authorize('rental_assets.inspect');
        $idempotencyKey = 'inspection:'.$return->id;
        $existing = AssetEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            abort_unless((int) $existing->asset_id === (int) $asset->id, 409);
            return $existing;
        }
        if (! in_array($resultingStatus, ['available', 'damaged', 'under_maintenance', 'lost'], true)) throw ValidationException::withMessages(['resulting_status' => __('The inspection outcome is invalid.')]);
        if (trim($assessment) === '') throw ValidationException::withMessages(['assessment' => __('Inspection findings are required.')]);
        return DB::transaction(function () use ($user, $asset, $return, $resultingStatus, $assessment, $idempotencyKey): AssetEvent {
            $locked = RentalAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($return->asset_id !== $locked->id || $locked->status !== 'under_inspection') throw ValidationException::withMessages(['asset' => __('The asset is not awaiting inspection.')]);
            $event = AssetEvent::create(['asset_id' => $locked->id, 'branch_id' => $locked->branch_id, 'store_id' => $locked->store_id, 'event_type' => 'inspection', 'source_type' => AssetReturn::class, 'source_id' => (string) $return->id, 'assessment' => $assessment, 'responsible_user_id' => $user->id, 'resulting_status' => $resultingStatus, 'status' => 'approved', 'idempotency_key' => $idempotencyKey]);
            $before = $locked->only(['status', 'condition']);
            $locked->mutate(['status' => $resultingStatus, 'condition' => $return->condition_after, 'updated_by' => $user->id, 'lock_version' => $locked->lock_version + 1]);
            $return->update(['outcome' => $resultingStatus, 'inspected_by' => $user->id]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_inspected', $event, before: $before, after: $locked->only(['status', 'condition']), branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['return_id' => $return->id, 'assessment' => $assessment]);
            return $event;
        }, 5);
    }
}
