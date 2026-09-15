<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class UserManagementScope
{
    public const ROOT_MANAGED_ROLE_CODES = [
        'company-owner',
        'system-administrator',
    ];

    public function visibleUsers(User $actor): Builder
    {
        $query = User::query();

        if ($actor->is_super_admin) {
            return $query;
        }

        $branchIds = $this->branchIds($actor);
        $storeIds = $this->storeIds($actor);

        return $query
            ->where('is_super_admin', false)
            ->whereDoesntHave('roles', fn (Builder $roles): Builder => $roles->whereIn('code', self::ROOT_MANAGED_ROLE_CODES))
            ->where(function (Builder $visible) use ($branchIds, $storeIds): void {
                $visible->whereHas('branchScopes', fn (Builder $branches): Builder => $branches
                    ->where('status', 'active')
                    ->whereIn('branch_id', $branchIds))
                    ->orWhereHas('storeScopes', fn (Builder $stores): Builder => $stores
                        ->where('status', 'active')
                        ->whereIn('store_id', $storeIds));
            })
            ->whereDoesntHave('branchScopes', fn (Builder $branches): Builder => $branches
                ->where('status', 'active')
                ->whereNotIn('branch_id', $branchIds))
            ->whereDoesntHave('storeScopes', fn (Builder $stores): Builder => $stores
                ->where('status', 'active')
                ->whereNotIn('store_id', $storeIds));
    }

    public function roles(User $actor): Builder
    {
        return Role::query()
            ->when(! $actor->is_super_admin, fn (Builder $roles): Builder => $roles->whereNotIn('code', self::ROOT_MANAGED_ROLE_CODES));
    }

    /** @return Collection<int, Role> */
    public function assignableRoles(User $actor): Collection
    {
        return $this->roles($actor)
            ->where('status', 'active')
            ->with('permissions:id,code,module')
            ->orderBy('name_en')
            ->get()
            ->filter(fn (Role $role): bool => $this->roleIsAssignable($actor, $role))
            ->values();
    }

    public function canManageGlobalRoles(User $actor): bool
    {
        return $actor->status === 'active'
            && ($actor->is_super_admin || $this->hasActiveSystemAdministratorRole($actor));
    }

    public function branches(User $actor): Builder
    {
        return $actor->is_super_admin ? Branch::query() : Branch::query()->visibleTo($actor);
    }

    public function stores(User $actor): Builder
    {
        return $actor->is_super_admin ? Store::query() : Store::query()->visibleTo($actor);
    }

    public function assertTargetIsManageable(User $actor, User $target): void
    {
        if ($actor->is_super_admin) {
            return;
        }

        if (! $this->visibleUsers($actor)->whereKey($target->id)->exists()) {
            throw ValidationException::withMessages([
                'editingUserId' => __('The selected user is outside your authorized company or location scope.'),
            ]);
        }
    }

    /** @param array<int, int> $roleIds @param array<int, int> $branchIds @param array<int, int> $storeIds */
    public function assertAssignmentsAreManageable(User $actor, array $roleIds, array $branchIds, array $storeIds): void
    {
        if ($actor->is_super_admin) {
            return;
        }

        if ($branchIds === [] && $storeIds === []) {
            throw ValidationException::withMessages([
                'branchIds' => __('At least one authorized branch or store scope is required.'),
            ]);
        }

        $roles = $this->roles($actor)
            ->whereIn('id', $roleIds)
            ->with('permissions:id,code,module')
            ->get();

        if ($roles->count() !== count($roleIds)
            || $roles->contains(fn (Role $role): bool => ! $this->roleIsAssignable($actor, $role))) {
            throw ValidationException::withMessages([
                'roleIds' => __('Only company-scoped roles may be assigned here. Super Administrator and Company Owner roles are provisioned separately.'),
            ]);
        }

        if ($this->branches($actor)->where('status', 'active')->whereIn('id', $branchIds)->count() !== count($branchIds)) {
            throw ValidationException::withMessages([
                'branchIds' => __('Every selected branch must be active and within your authorized company scope.'),
            ]);
        }

        if ($this->stores($actor)->where('status', 'active')->whereIn('id', $storeIds)->count() !== count($storeIds)) {
            throw ValidationException::withMessages([
                'storeIds' => __('Every selected store must be active and within your authorized company scope.'),
            ]);
        }
    }

    /** @return Collection<int, int> */
    private function branchIds(User $actor): Collection
    {
        return Branch::query()->visibleTo($actor)->pluck('id')->map(fn (mixed $id): int => (int) $id);
    }

    /** @return Collection<int, int> */
    private function storeIds(User $actor): Collection
    {
        return Store::query()->visibleTo($actor)->pluck('id')->map(fn (mixed $id): int => (int) $id);
    }

    private function roleIsAssignable(User $actor, Role $role): bool
    {
        if ($actor->is_super_admin || $this->hasActiveSystemAdministratorRole($actor)) {
            return true;
        }

        return $role->permissions->every(fn ($permission): bool =>
            CompanyOwnerPermissionProfile::allows($permission->code, $permission->module)
            && $actor->hasPermission($permission->code)
        );
    }

    private function hasActiveSystemAdministratorRole(User $actor): bool
    {
        return $actor->roles()
            ->where('roles.code', 'system-administrator')
            ->where('roles.status', 'active')
            ->exists();
    }
}
