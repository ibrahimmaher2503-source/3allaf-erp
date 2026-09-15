<?php

declare(strict_types=1);

namespace App\Modules\Platform\Queries;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\PrinterConfiguration;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Platform\Support\InitialSetupStatus;
use Illuminate\Support\Facades\Gate;

final class AdministrationDashboard
{
    public function __construct(private readonly InitialSetupStatus $setupStatus) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        $canUsers = Gate::forUser($user)->allows('users_roles_permissions.view');
        $canLocations = Gate::forUser($user)->allows('branches_stores.view');
        $canSettings = Gate::forUser($user)->allows('company_settings.view');
        $canAudit = Gate::forUser($user)->allows('audit_logs.view');
        $users = $canUsers ? User::query() : null;
        $roles = $canUsers ? Role::query() : null;
        $branches = $canLocations ? Branch::query()->visibleTo($user) : null;
        $stores = $canLocations ? Store::query()->visibleTo($user) : null;

        return [
            'setup' => $this->setupStatus->snapshot(),
            'users' => $canUsers ? ['active' => (clone $users)->where('status', 'active')->count(), 'inactive' => (clone $users)->where('status', 'inactive')->count(), 'unverified' => (clone $users)->whereNull('email_verified_at')->count()] : null,
            'roles' => $canUsers ? ['active' => (clone $roles)->where('status', 'active')->count(), 'inactive' => (clone $roles)->where('status', 'inactive')->count(), 'permissions' => Permission::query()->where('status', 'active')->count(), 'unassigned' => Permission::query()->where('status', 'active')->whereDoesntHave('roles', fn ($query) => $query->where('roles.status', 'active'))->count()] : null,
            'locations' => $canLocations ? ['active_branches' => (clone $branches)->where('status', 'active')->count(), 'active_stores' => (clone $stores)->where('status', 'active')->count()] : null,
            'configuration' => $canSettings ? ['payment_methods' => PaymentMethod::query()->where('status', 'active')->count(), 'tax_rules' => TaxSetting::query()->where('status', 'active')->count(), 'numbering_rules' => DocumentSequence::query()->where('status', 'active')->count(), 'printer_profiles' => PrinterConfiguration::query()->where('status', 'active')->count()] : null,
            'pending_approvals' => ApprovalRecord::query()->visibleTo($user)->where('approval_state', ApprovalState::Pending->value)->count(),
            'recent_audit' => $canAudit ? AuditLog::query()->visibleTo($user)->select(['id', 'category', 'event', 'actor_name', 'created_at'])->latest('id')->limit(8)->get() : collect(),
        ];
    }
}
