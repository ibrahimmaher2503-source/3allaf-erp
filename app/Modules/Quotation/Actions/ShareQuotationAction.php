<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Quotation\Models\Quotation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

final class ShareQuotationAction
{
    public function execute(User $user, Quotation $quotation): string
    {
        Gate::forUser($user)->authorize('quotations.share');
        abort_unless($user->is_super_admin || $user->canAccessBranch($quotation->branch_id) || $user->canAccessStore($quotation->store_id), 403);
        $url = URL::temporarySignedRoute('quotations.print', now()->addHours(24), ['quotation' => $quotation->id]);
        app(RecordAuditEvent::class)->execute('quotations', 'quotation_shared', $quotation, branchId: $quotation->branch_id, storeId: $quotation->store_id, metadata: ['expires_at' => now()->addHours(24)->toIso8601String(), 'non_posting' => true]);
        return $url;
    }
}
