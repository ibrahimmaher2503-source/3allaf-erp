<?php

declare(strict_types=1);

namespace App\Modules\Assets\Actions;

use App\Models\User;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ReserveAssetAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, RentalAsset $asset, array $data): AssetReservation
    {
        Gate::forUser($user)->authorize('rental_assets.reserve');
        abort_unless($user->is_super_admin || $user->canAccessBranch($asset->branch_id) || $user->canAccessStore($asset->store_id), 403);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey !== '') {
            $existing = AssetReservation::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                abort_unless((int) $existing->asset_id === (int) $asset->id, 409);
                return $existing;
            }
        }
        $starts = CarbonImmutable::parse((string) $data['starts_at']);
        $ends = CarbonImmutable::parse((string) $data['ends_at']);
        if ($ends->lessThanOrEqualTo($starts)) throw ValidationException::withMessages(['ends_at' => __('The reservation must end after it starts.')]);
        if ($starts->isPast()) throw ValidationException::withMessages(['starts_at' => __('Reservations cannot start in the past.')]);

        return DB::transaction(function () use ($user, $asset, $data, $starts, $ends, $idempotencyKey): AssetReservation {
            $locked = RentalAsset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['available', 'reserved'], true)) throw ValidationException::withMessages(['asset' => __('This asset is not reservable in its current state.')]);
            $before = $locked->only(['status', 'location', 'condition']);
            $bufferBefore = max(0, (int) ($data['buffer_before_minutes'] ?? 0));
            $bufferAfter = max(0, (int) ($data['buffer_after_minutes'] ?? 0));
            $windowStart = $starts->subMinutes($bufferBefore);
            $windowEnd = $ends->addMinutes($bufferAfter);
            $overlap = AssetReservation::query()->where('asset_id', $locked->id)->active()
                ->where('starts_at', '<', $windowEnd)->where('ends_at', '>', $windowStart)
                ->lockForUpdate()
                ->exists();
            if ($overlap) throw ValidationException::withMessages(['starts_at' => __('This asset already has an overlapping reservation.')]);
            $reservation = AssetReservation::create([
                'asset_id' => $locked->id, 'branch_id' => $locked->branch_id, 'store_id' => $locked->store_id,
                'source_type' => $data['source_type'] ?? null, 'source_id' => $data['source_id'] ?? null,
                'source_reference' => $data['source_reference'] ?? null, 'starts_at' => $starts, 'ends_at' => $ends,
                'timezone' => $data['timezone'] ?? 'UTC', 'buffer_before_minutes' => $bufferBefore,
                'buffer_after_minutes' => $bufferAfter, 'status' => 'reserved', 'reserved_by' => $user->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            $locked->mutate(['status' => 'reserved', 'updated_by' => $user->id, 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('assets', 'asset_reserved', $reservation, before: $before, after: $locked->only(['status', 'location', 'condition']), branchId: $locked->branch_id, storeId: $locked->store_id, metadata: ['asset_id' => $locked->id, 'starts_at' => $starts->toIso8601String(), 'ends_at' => $ends->toIso8601String()]);
            return $reservation;
        }, 5);
    }
}
