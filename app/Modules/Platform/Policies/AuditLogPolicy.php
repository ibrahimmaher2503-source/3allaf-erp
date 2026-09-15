<?php

namespace App\Modules\Platform\Policies;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit_logs.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $user->hasPermission('audit_logs.view')) {
            return false;
        }

        if ($user->is_super_admin) {
            return true;
        }

        return ($auditLog->branch_id !== null && $user->canAccessBranch((int) $auditLog->branch_id))
            || ($auditLog->store_id !== null && $user->canAccessStore((int) $auditLog->store_id));
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('audit_logs.export');
    }
}
