<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeQuotationStatusAction
{
    public function execute(User $user, Quotation $quotation, string $status, ?string $reason = null): Quotation
    {
        if (! in_array($status, ['issued', 'accepted', 'rejected', 'expired', 'cancelled'], true)) throw ValidationException::withMessages(['status' => __('This quotation status is not supported.')]);
        Gate::forUser($user)->authorize($status === 'issued' ? 'quotations.issue' : 'quotations.edit');
        return DB::transaction(function () use ($user, $quotation, $status, $reason): Quotation {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            $allowed = ['draft' => ['issued', 'cancelled'], 'issued' => ['accepted', 'rejected', 'expired', 'cancelled'], 'accepted' => [], 'rejected' => [], 'expired' => [], 'cancelled' => []];
            if (! in_array($status, $allowed[$locked->status] ?? [], true)) throw ValidationException::withMessages(['status' => __('This quotation transition is not allowed.')]);
            $before = $locked->only(['status', 'valid_until']);
            $locked->mutate(['status' => $status, 'updated_by' => $user->id, 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('quotations', 'quotation_status_changed', $locked, before: $before, after: $locked->only(['status', 'valid_until']), branchId: $locked->branch_id, storeId: $locked->store_id, reasonText: $reason, metadata: ['non_posting' => true]);
            return $locked;
        }, 5);
    }
}
