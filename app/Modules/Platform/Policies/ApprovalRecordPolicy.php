<?php

namespace App\Modules\Platform\Policies;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;

class ApprovalRecordPolicy
{
    public function view(User $user, ApprovalRecord $record): bool
    {
        if (! $this->hasScope($user, $record)) {
            return false;
        }

        return $user->is_super_admin
            || $record->requester_id === $user->id
            || $user->hasPermission('audit_logs.view')
            || ($record->request_permission !== null && $user->hasPermission($record->request_permission))
            || $user->hasPermission($record->source_type.'.view')
            || $user->hasPermission($record->decisionPermission());
    }

    public function decide(User $user, ApprovalRecord $record): bool
    {
        return ! $record->approval_state->isTerminal()
            && ($record->requester_id !== $user->id || $user->canBypassApproval())
            && $this->hasScope($user, $record)
            && ($user->is_super_admin || $user->hasPermission($record->decisionPermission()));
    }

    public function withdraw(User $user, ApprovalRecord $record): bool
    {
        return ! $record->approval_state->isTerminal()
            && $record->requester_id === $user->id
            && $this->hasScope($user, $record);
    }

    public function cancel(User $user, ApprovalRecord $record): bool
    {
        return ! $record->approval_state->isTerminal()
            && $this->hasScope($user, $record)
            && ($user->is_super_admin || $user->hasPermission($record->source_type.'.cancel'));
    }

    private function hasScope(User $user, ApprovalRecord $record): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if ($record->branch_id === null && $record->store_id === null) {
            return $user->hasPermission($record->decisionPermission());
        }

        // A branch-scoped reviewer may decide records for stores inside that
        // branch; a store-scoped reviewer may decide only that store. This
        // mirrors ApprovalRecord::visibleTo() and prevents the inbox from
        // showing an item that its canonical policy can never decide.
        return ($record->branch_id !== null && $user->canAccessBranch((int) $record->branch_id))
            || ($record->store_id !== null && $user->canAccessStore((int) $record->store_id));
    }
}
