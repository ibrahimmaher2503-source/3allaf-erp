<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CheckoutAssetAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, RentalAsset $asset, AssetReservation $reservation, array $data): AssetCheckout
    {
        Gate::forUser($user)->authorize('rental_assets.checkout');
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = AssetCheckout::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_unless((int) $existing->asset_id === (int) $asset->id, 409);
                return $existing;
            }
        }
        return DB::transaction(function () use ($user, $asset, $reservation, $data, $idempotencyKey): AssetCheckout {
            $locked = RentalAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $lockedReservation = AssetReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->is_super_admin || $user->canAccessBranch($locked->branch_id) || $user->canAccessStore($locked->store_id), 403);
            if ($lockedReservation->asset_id !== $locked->id || $lockedReservation->status !== 'reserved' || $locked->status !== 'reserved') throw ValidationException::withMessages(['asset' => __('The reservation or asset is no longer eligible for checkout.')]);
            $checkout = AssetCheckout::create([
                'asset_id' => $locked->id, 'reservation_id' => $lockedReservation->id, 'branch_id' => $locked->branch_id, 'store_id' => $locked->store_id,
                'source_reference' => trim((string) ($data['source_reference'] ?? '')), 'checked_out_at' => now(), 'location_before' => $locked->location,
                'location_after' => $data['location_after'] ?? null, 'condition_before' => $locked->condition, 'notes' => $data['notes'] ?? null,
                'responsible_user_id' => $user->id, 'idempotency_key' => $idempotencyKey,
            ]);
            $locked->mutate(['status' => 'checked_out', 'location' => $data['location_after'] ?? $locked->location, 'updated_by' => $user->id, 'lock_version' => $locked->lock_version + 1]);
            $lockedReservation->update(['status' => 'fulfilled', 'lock_version' => $lockedReservation->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_checked_out', $checkout, before: ['status' => 'reserved', 'location' => $checkout->location_before, 'condition' => $checkout->condition_before], after: ['status' => 'checked_out', 'location' => $locked->location, 'condition' => $locked->condition], branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['reservation_id' => $reservation->id, 'source_reference' => $checkout->source_reference]);
            return $checkout;
        }, 5);
    }
}
