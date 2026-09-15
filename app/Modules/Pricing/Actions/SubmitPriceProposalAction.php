<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class SubmitPriceProposalAction
{
    public function execute(PriceVersion $version): PriceVersion
    {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated pricing user is required.');
        Gate::forUser($user)->authorize('pricing_labels.submit');

        return DB::transaction(function () use ($version, $user): PriceVersion {
            $version = PriceVersion::query()->lockForUpdate()->with('lines')->findOrFail($version->id);
            if ($version->state !== PriceVersionState::Draft) {
                throw ValidationException::withMessages(['state' => __('Only a draft proposal can be submitted.')]);
            }
            /** @var PriceLine $line */
            $line = $version->lines->firstOrFail();

            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'pricing_labels',
                sourceId: (string) $version->id,
                sourceVersion: (string) $version->lock_version,
                requestedAction: 'approve_price_version',
                requestPermission: 'pricing_labels.submit',
                branchId: $line->branch_id,
                storeId: $line->store_id,
                reasonText: $version->reason_text,
                sourceHash: $version->source_hash,
                idempotencyKey: 'price-submit-'.$version->id.'-'.$version->lock_version,
            ));

            $version->update([
                'state' => PriceVersionState::Submitted,
                'approval_record_id' => $approval->id,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'pricing',
                event: 'price_proposal_submitted',
                source: $version,
                after: ['state' => PriceVersionState::Submitted->value, 'approval_record_id' => $approval->id],
                branchId: $line->branch_id,
                storeId: $line->store_id,
                reasonText: $version->reason_text,
            );

            return $version->fresh(['lines.product', 'priceList', 'approvalRecord']);
        });
    }
}
