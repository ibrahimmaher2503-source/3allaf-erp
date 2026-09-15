<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;

final class CompanyOwnerPermissionProfile
{
    public const ROLE_CODE = 'company-owner';

    private const EXCLUDED_MODULES = [
        'audit_logs',
        'infrastructure',
        'platform',
    ];

    private const EXCLUDED_CODE_FRAGMENTS = [
        'cross-company',
        'cross.company',
        'cross_company',
        'impersonat',
        'infrastructure',
        'platform-owner',
        'platform.owner',
        'platform_owner',
        'super-admin',
        'super.admin',
        'super_admin',
    ];

    public function synchronize(): Role
    {
        $role = Role::query()->updateOrCreate(
            ['code' => self::ROLE_CODE],
            [
                'name_ar' => 'مالك الشركة',
                'name_en' => 'Company Owner',
                'description_ar' => 'إدارة شاملة لأعمال الشركة ضمن الفروع والمواقع المصرح بها، دون صلاحيات المنصة أو مسؤول النظام الأعلى.',
                'description_en' => 'Full company business administration within assigned branches and locations, without platform or Super Admin privileges.',
                'status' => 'active',
            ],
        );

        $permissionIds = Permission::query()
            ->where('status', 'active')
            ->get(['id', 'code', 'module'])
            ->filter(fn (Permission $permission): bool => self::allows($permission->code, $permission->module))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $role->permissions()->sync($permissionIds);

        return $role->fresh('permissions');
    }

    public static function allows(string $code, string $module): bool
    {
        $normalizedCode = strtolower($code);
        $normalizedModule = strtolower($module);

        if (in_array($normalizedModule, self::EXCLUDED_MODULES, true)) {
            return false;
        }

        foreach (self::EXCLUDED_CODE_FRAGMENTS as $fragment) {
            if (str_contains($normalizedCode, $fragment)) {
                return false;
            }
        }

        return true;
    }
}
