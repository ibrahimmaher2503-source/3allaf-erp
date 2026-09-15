<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReturnAssetAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, RentalAsset $asset, AssetCheckout $checkout, array $data): AssetReturn
    {
        Gate::forUser($user)->authorize('rental_assets.return');
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = AssetReturn::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_unless((int) $existing->asset_id === (int) $asset->id, 409);
                return $existing;
            }
        }
        return DB::transaction(function () use ($user, $asset, $checkout, $data, $idempotencyKey): AssetReturn {
            $locked = RentalAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if ($checkout->asset_id !== $locked->id || $locked->status !== 'checked_out') throw ValidationException::withMessages(['asset' => __('Only a checked-out asset can be returned.')]);
            $condition = trim((string) ($data['condition_after'] ?? ''));
            if ($condition === '') throw ValidationException::withMessages(['condition_after' => __('A post-return condition is required.')]);
            $return = AssetReturn::create([
                'asset_id' => $locked->id, 'checkout_id' => $checkout->id, 'branch_id' => $locked->branch_id, 'store_id' => $locked->store_id,
                'returned_at' => now(), 'location_after' => $data['location_after'] ?? $locked->location, 'condition_after' => $condition,
                'outcome' => 'under_inspection', 'notes' => $data['notes'] ?? null, 'idempotency_key' => $idempotencyKey,
            ]);
            $locked->mutate(['status' => 'under_inspection', 'location' => $return->location_after, 'condition' => $condition, 'updated_by' => $user->id, 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_returned', $return, before: ['status' => 'checked_out'], after: ['status' => 'under_inspection', 'condition' => $condition, 'location' => $return->location_after], branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['checkout_id' => $checkout->id]);
            return $return;
        }, 5);
    }
}
