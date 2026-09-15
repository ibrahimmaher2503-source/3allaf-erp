<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use LogicException;

final class OfflinePosPolicy
{
    public function assertEnabled(): void
    {
        if (app()->isProduction()) {
            throw new LogicException('Offline POS is permanently disabled in Production.');
        }

        if (! config('offline.enabled')) {
            throw new LogicException('Offline POS is disabled by policy.');
        }
    }

    public function assertDeviceAccess(User $actor, OfflineDevice $device, string $token, bool $requiresActiveAssignment = false): OfflineDevice
    {
        $this->assertEnabled();
        $device = OfflineDevice::query()->lockForUpdate()->findOrFail($device->id);
        $shift = PosShift::query()->lockForUpdate()->findOrFail($device->shift_id);
        if ((int) $device->user_id !== (int) $actor->id
            || ! $actor->canAccessBranch((int) $device->branch_id)
            || ! $actor->canAccessStore((int) $device->store_id)
            || (int) $shift->cashier_id !== (int) $device->user_id
            || (int) $shift->branch_id !== (int) $device->branch_id
            || (int) $shift->store_id !== (int) $device->store_id
            || $device->revoked_at !== null
            || $device->expires_at->isPast()
            || ! Hash::check($token, (string) $device->token_hash)) {
            throw new InvalidArgumentException('The offline device token or bound scope is no longer valid.');
        }

        if ($requiresActiveAssignment && ($shift->status->value !== 'open'
            || ! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
                ->where('cashier_id', $actor->id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists())) {
            throw new InvalidArgumentException('The offline device no longer has an active cashier shift assignment.');
        }

        return $device;
    }

    public function assertEnrollmentScope(User $actor, PosShift $shift): void
    {
        $this->assertEnabled();
        if (! $actor->hasPermission('offline_queue_conflicts.create')
            || (int) $shift->cashier_id !== (int) $actor->id
            || ! $actor->canAccessBranch((int) $shift->branch_id)
            || ! $actor->canAccessStore((int) $shift->store_id)
            || $shift->status->value !== 'open'
            || ! DB::table('active_pos_shift_assignments')->where('shift_id', $shift->id)
                ->where('cashier_id', $actor->id)->where('cash_drawer_id', $shift->cash_drawer_id)->exists()) {
            throw new InvalidArgumentException('Offline device enrollment requires the assigned open cashier shift.');
        }
    }
}
