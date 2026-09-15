<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Models\OfflineDevice;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\OfflinePosPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class EnrollOfflineDeviceAction
{
    public function __construct(private readonly OfflinePosPolicy $policy) {}

    public function execute(User $actor, PosShift $shift, string $name, string $token): OfflineDevice
    {
        $name = trim($name);
        if ($name === '' || trim($token) === '') {
            throw new InvalidArgumentException('An offline device name and token are required.');
        }

        return DB::transaction(function () use ($actor, $shift, $name, $token): OfflineDevice {
            $shift = PosShift::query()->lockForUpdate()->findOrFail($shift->id);
            $this->policy->assertEnrollmentScope($actor, $shift);
            $device = OfflineDevice::query()->updateOrCreate(
                ['user_id' => $actor->id, 'name' => $name],
                [
                    'branch_id' => $shift->branch_id,
                    'store_id' => $shift->store_id,
                    'shift_id' => $shift->id,
                    'token_hash' => Hash::make($token),
                    'policy_version' => (string) config('offline.policy_version'),
                    'schema_version' => (string) config('offline.schema_version'),
                    'expires_at' => now()->addMinutes((int) config('offline.limits.max_duration_minutes')),
                    'revoked_at' => null,
                ],
            );
            app(RecordAuditEvent::class)->execute('retail', 'offline_device_enrolled', $device, null, [
                'user_id' => $actor->id, 'shift_id' => $shift->id, 'policy_version' => $device->policy_version,
            ], (int) $shift->branch_id, (int) $shift->store_id, metadata: ['actor_id' => $actor->id]);

            return $device;
        });
    }
}
