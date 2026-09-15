<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RequestPermissionLookup
{
    /** @var array<int, array<string, true>> */
    private array $lookups = [];

    /** @return array<string, true> */
    public function for(User $user): array
    {
        $requestKey = 'permission_lookup.'.$user->id;
        if (app()->bound('request') && request()->attributes->has($requestKey)) {
            return request()->attributes->get($requestKey);
        }

        $lookup = $this->lookups[$user->id] ??= DB::table('permissions')
            ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->join('roles', 'roles.id', '=', 'role_permissions.role_id')
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.status', 'active')
            ->where('permissions.status', 'active')
            ->distinct()
            ->pluck('permissions.code')
            ->mapWithKeys(static fn (string $code): array => [$code => true])
            ->all();

        if (app()->bound('request')) {
            request()->attributes->set($requestKey, $lookup);
        }

        return $lookup;
    }
}
