<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Support\UserManagementScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SaveRoleAction
{
    private const CANONICAL_CODES = [
        'system-administrator', 'company-owner', 'branch-manager', 'cashier', 'purchasing-officer',
        'warehouse-manager', 'pricing-officer', 'party-manager', 'stock-counter', 'accountant-reviewer',
    ];

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?Role $role = null): Role
    {
        Gate::authorize($role === null ? 'users_roles_permissions.create' : 'users_roles_permissions.edit');
        $actor = Auth::user();
        if (! ($actor instanceof User && app(UserManagementScope::class)->canManageGlobalRoles($actor))) {
            throw new AuthorizationException(__('Global role definitions may be managed only by an authorized system administrator.'));
        }

        if ($role !== null && self::isCanonical($role)) {
            throw new AuthorizationException(__('Canonical roles are managed by the approved authorization baseline and cannot be edited here.'));
        }

        return DB::transaction(function () use ($data, $role): Role {
            $attributes = [
                'code' => strtolower(trim((string) $data['code'])),
                'name_ar' => trim((string) $data['name_ar']),
                'name_en' => trim((string) $data['name_en']),
                'description_ar' => filled($data['description_ar'] ?? null) ? trim((string) $data['description_ar']) : null,
                'description_en' => filled($data['description_en'] ?? null) ? trim((string) $data['description_en']) : null,
                'status' => (string) $data['status'],
            ];

            if (in_array($attributes['code'], self::CANONICAL_CODES, true)) {
                throw ValidationException::withMessages(['roleForm.code' => __('A canonical role code cannot be created manually.')]);
            }

            if ($role === null) {
                $saved = Role::query()->create($attributes);
                $event = 'create_role';
                $before = null;
            } else {
                $saved = Role::query()->lockForUpdate()->findOrFail($role->id);
                $before = $saved->only(['code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'status']);
                $saved->update($attributes);
                $event = 'update_role';
            }

            app(RecordAuditEvent::class)->execute(
                category: 'authorization',
                event: $event,
                source: $saved,
                before: $before,
                after: $saved->only(['code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'status']),
            );

            return $saved;
        });
    }

    /** @param array<int, int> $permissionIds */
    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $actor = Auth::user();
        $canManageAll = $actor instanceof User && self::canManagePermissions($actor, $role);

        if (! $canManageAll) {
            throw new AuthorizationException(__('Global role permissions may be managed only by an authorized system administrator.'));
        }

        $permissionIds = array_values(array_unique(array_map('intval', $permissionIds)));

        DB::transaction(function () use ($role, $permissionIds, $actor): void {
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $canManageAll = $actor instanceof User && self::canManagePermissions($actor, $role);

            if (self::isCanonical($role) && ! $canManageAll) {
                throw new AuthorizationException(__('Canonical role permissions are read-only in this screen.'));
            }

            $permissions = Permission::query()->whereIn('id', $permissionIds)->get(['id', 'code', 'status', 'sensitivity']);

            if ($permissions->count() !== count($permissionIds) || $permissions->contains(fn (Permission $permission): bool => $permission->status !== 'active')) {
                throw ValidationException::withMessages(['permissionIds' => __('Each selected permission must be active and available.')]);
            }
            if (! $canManageAll && $permissions->contains(fn (Permission $permission): bool => $permission->sensitivity === 'sensitive')) {
                throw ValidationException::withMessages(['permissionIds' => __('Sensitive permissions require an owner-approved canonical role grant.')]);
            }

            $before = $role->permissions()->orderBy('permissions.id')->pluck('permissions.id')->map(fn ($id): int => (int) $id)->all();
            $role->permissions()->sync($permissionIds);
            app(RecordAuditEvent::class)->execute(
                category: 'authorization',
                event: 'update_role_permissions',
                source: $role,
                before: ['permission_ids' => $before],
                after: ['permission_ids' => $permissionIds],
            );
        });
    }

    public static function canManagePermissions(User $actor, Role $target): bool
    {
        return $actor->status === 'active'
            && ! in_array($target->code, ['system-administrator', 'company-owner'], true)
            && ($actor->is_super_admin || $actor->roles()->where('code', 'system-administrator')->where('status', 'active')->exists());
    }

    public static function isCanonical(Role $role): bool
    {
        return in_array($role->code, self::CANONICAL_CODES, true);
    }
}
