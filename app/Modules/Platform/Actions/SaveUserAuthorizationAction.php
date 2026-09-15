<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveUserAuthorizationAction
{
    /** @param array<int, int> $roleIds @param array<int, int> $branchIds @param array<int, int> $storeIds */
    public function execute(User $user, array $roleIds, array $branchIds, array $storeIds, ?string $status = null, bool $creating = false): void
    {
        Gate::authorize($creating ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');

        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $branchIds = array_values(array_unique(array_map('intval', $branchIds)));
        $storeIds = array_values(array_unique(array_map('intval', $storeIds)));
        if ($status !== null && ! in_array($status, ['active', 'inactive'], true)) {
            throw ValidationException::withMessages(['status' => __('The user status is invalid.')]);
        }

        DB::transaction(function () use ($user, $roleIds, $branchIds, $storeIds, $status, $creating, $actor): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $scope = app(UserManagementScope::class);
            if (! $creating) {
                $scope->assertTargetIsManageable($actor, $user);
            }
            $scope->assertAssignmentsAreManageable($actor, $roleIds, $branchIds, $storeIds);

            $systemAdministratorId = Role::query()->where('code', 'system-administrator')->value('id');

            if (Role::query()->whereIn('id', $roleIds)->where('status', 'active')->count() !== count($roleIds)) {
                throw ValidationException::withMessages(['roleIds' => __('Every selected role must be active and available.')]);
            }
            if (Branch::query()->whereIn('id', $branchIds)->where('status', 'active')->count() !== count($branchIds)) {
                throw ValidationException::withMessages(['branchIds' => __('Every selected branch scope must be active and available.')]);
            }
            $stores = Store::query()->whereIn('id', $storeIds)->where('status', 'active')->get(['id', 'branch_id']);
            if ($stores->count() !== count($storeIds)) {
                throw ValidationException::withMessages(['storeIds' => __('Every selected store scope must be active and available.')]);
            }
            if ($branchIds !== [] && $stores->contains(fn (Store $store): bool => $store->branch_id !== null && ! in_array((int) $store->branch_id, $branchIds, true))) {
                throw ValidationException::withMessages(['storeIds' => __('Each selected store must belong to one of the selected branch scopes.')]);
            }

            $nextStatus = $status ?? (string) ($user->status ?: 'active');
            $currentlyAdministrator = $systemAdministratorId !== null && $user->roles()->whereKey($systemAdministratorId)->exists();
            $willRemainAdministrator = $systemAdministratorId !== null
                && in_array((int) $systemAdministratorId, $roleIds, true)
                && $nextStatus === 'active';

            if ($systemAdministratorId !== null
                && $currentlyAdministrator
                && ! $willRemainAdministrator
                && ! User::query()
                    ->whereKeyNot($user->id)
                    ->where('status', 'active')
                    ->whereHas('roles', fn ($query) => $query->whereKey($systemAdministratorId))
                    ->lockForUpdate()
                    ->exists()) {
                throw ValidationException::withMessages(['roleIds' => __('At least one system administrator must remain assigned.')]);
            }

            $before = ['status' => $user->status, 'roles' => $user->roles()->pluck('roles.id')->all(), 'branches' => $user->branchScopes()->pluck('branch_id')->all(), 'stores' => $user->storeScopes()->pluck('store_id')->all()];
            $user->roles()->sync($roleIds);
            $user->branchScopes()->delete();
            $user->storeScopes()->delete();
            $user->branchScopes()->createMany(array_map(fn ($id) => ['branch_id' => $id, 'status' => 'active'], $branchIds));
            $user->storeScopes()->createMany(array_map(fn ($id) => ['store_id' => $id, 'status' => 'active'], $storeIds));
            if ($status !== null) {
                $user->update(['status' => $nextStatus]);
            }
            app(RecordAuditEvent::class)->execute(
                category: 'authorization',
                event: 'update_user_authorization',
                source: $user,
                before: $before,
                after: ['status' => $nextStatus, 'roles' => $roleIds, 'branches' => $branchIds, 'stores' => $storeIds],
            );
        });
    }
}
