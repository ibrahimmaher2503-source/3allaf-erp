<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\ApprovalRecordTransition;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Pricing\Enums\PriceVersionState;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ApprovePriceProposalAction
{
    public function execute(PriceVersion $version): PriceVersion
    {
        /** @var User $approver */
        $approver = Auth::user() ?? throw new \LogicException('An authenticated pricing approver is required.');
        Gate::forUser($approver)->authorize('pricing_labels.approve');

        return DB::transaction(function () use ($version, $approver): PriceVersion {
            $version = PriceVersion::query()->lockForUpdate()->with(['lines', 'approvalRecord'])->findOrFail($version->id);
            if ($version->state !== PriceVersionState::Submitted || $version->approvalRecord === null) {
                throw ValidationException::withMessages(['state' => __('Only a submitted proposal with a pending approval can be approved.')]);
            }
            /** @var ApprovalRecord $approvalRecord */
            $approvalRecord = $version->approvalRecord;
            if ($approvalRecord->requester_id === $approver->id && ! $approver->canBypassApproval()) {
                throw ValidationException::withMessages(['approval' => __('The proposer cannot approve the same price version.')]);
            }
            /** @var PriceLine $line */
            $line = $version->lines->firstOrFail();
            $this->assertNoOverlappingApprovedVersion($version, $line);

            app(ApprovalRecordTransition::class)->execute(
                record: $approvalRecord,
                state: ApprovalState::Approved,
                event: 'price_approval_approved',
                attributes: ['approver_id' => $approver->id, 'decided_at' => now(), 'decision_note' => __('Approved for the configured Local/Dev pricing workflow.')],
                expectedSourceVersion: (string) $version->lock_version,
                expectedSourceHash: $version->source_hash,
                authorize: fn ($record): mixed => Gate::forUser($approver)->authorize('pricing_labels.approve'),
            );

            $activeNow = $version->effective_from === null || $version->effective_from->lte(now());
            if ($activeNow) {
                $activeKey = $this->activeKey($line->product_id, $line->store_id);
                $old = PriceLine::query()->lockForUpdate()->where('active_key', $activeKey)->first();
                if ($old !== null && $old->price_version_id !== $version->id) {
                    $old->mutateApprovedParentLine(['active_key' => null]);
                    $oldVersion = $old->version()->lockForUpdate()->firstOrFail();
                    $oldVersion->mutateApprovedDocument(['state' => PriceVersionState::Superseded, 'superseded_at' => now()]);
                }
                $line->update(['active_key' => $activeKey]);
            }

            $version->update(['state' => PriceVersionState::Approved, 'approved_by' => $approver->id, 'approved_at' => now(), 'lock_version' => $version->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(
                category: 'pricing',
                event: 'price_version_approved',
                source: $version,
                after: ['state' => PriceVersionState::Approved->value, 'effective_now' => $activeNow, 'product_id' => $line->product_id, 'store_id' => $line->store_id],
                branchId: $line->branch_id,
                storeId: $line->store_id,
            );

            return $version->fresh(['lines.product', 'priceList', 'approvalRecord']);
        });
    }

    private function assertNoOverlappingApprovedVersion(PriceVersion $version, PriceLine $line): void
    {
        if ($version->effective_from === null || $version->effective_from->lte(now())) {
            return;
        }

        $overlaps = PriceLine::query()
            ->where('product_id', $line->product_id)
            ->where('store_id', $line->store_id)
            ->where('price_version_id', '!=', $version->id)
            ->whereHas('version', function ($versions) use ($version): void {
                $versions->where('state', PriceVersionState::Approved)
                    ->where(function ($q) use ($version): void {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>', $version->effective_from);
                    });
            })
            ->lockForUpdate()
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['effective_from' => __('The future effective period overlaps an approved price version for this product and store.')]);
        }
    }

    private function activeKey(int $productId, int $storeId): string
    {
        return $productId.':'.$storeId;
    }
}
