<?php

namespace App\Modules\Platform\Actions;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Data\CorrectionReferenceData;
use App\Modules\Platform\Enums\CorrectionType;
use App\Modules\Platform\Guards\AssertCorrectionAllowed;
use App\Modules\Platform\Guards\AssertCorrectionScope;
use App\Modules\Platform\Guards\AssertNoDuplicateCorrection;
use App\Modules\Platform\Guards\AssertOriginalPreserved;
use App\Modules\Platform\Guards\AssertSourceVersionCurrent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Transaction boundary for a real module's correction reference and effects.
 * The source module owns persistence, permissions, and the compensating effect.
 */
final class ExecuteCorrection
{
    /**
     * @param  array<int, CorrectionType|string>  $allowedTypes
     * @param  callable(User, ImmutableSourceContract, CorrectionReferenceData): void  $authorize
     * @param  callable(CorrectionReferenceData, ImmutableSourceContract): mixed  $createAndApply
     * @param  callable(CorrectionReferenceData): bool|null  $duplicateLookup
     * @param  callable(CorrectionReferenceData): void|null  $assertApproval
     */
    public function execute(
        CorrectionReferenceData $reference,
        ImmutableSourceContract $source,
        User $actor,
        array $allowedTypes,
        callable $authorize,
        callable $createAndApply,
        ?callable $duplicateLookup = null,
        ?callable $assertApproval = null,
    ): mixed {
        if (Auth::id() !== $actor->id) {
            throw new AuthorizationException(__('The authenticated actor does not match the correction requester.'));
        }

        return DB::transaction(function () use ($reference, $source, $actor, $allowedTypes, $authorize, $createAndApply, $duplicateLookup, $assertApproval): mixed {
            if ($reference->requestedBy !== $actor->id) {
                throw new AuthorizationException(__('The correction requester must be the authenticated actor.'));
            }

            $authorize($actor, $source, $reference);
            app(AssertCorrectionAllowed::class)->execute($source, $reference, $allowedTypes);
            app(AssertSourceVersionCurrent::class)->execute($source, $reference->originalSourceVersion, $reference->originalSourceHash);
            app(AssertCorrectionScope::class)->execute($actor, $source, $reference);

            if ($duplicateLookup !== null) {
                app(AssertNoDuplicateCorrection::class)->execute($reference, $duplicateLookup);
            }

            if ($assertApproval !== null) {
                $assertApproval($reference);
            }

            $result = $createAndApply($reference, $source);
            app(AssertOriginalPreserved::class)->execute($source, $reference);

            app(RecordAuditEvent::class)->execute(
                category: 'workflow',
                event: 'correction.created',
                source: $reference->correctionSourceType,
                before: [
                    'source_type' => $reference->originalSourceType,
                    'source_id' => $reference->originalSourceId,
                    'source_version' => $reference->originalSourceVersion,
                    'source_hash' => $reference->originalSourceHash,
                ],
                after: [
                    'correction_type' => $reference->correctionType->value,
                    'correction_source_type' => $reference->correctionSourceType,
                    'correction_source_id' => $reference->correctionSourceId,
                ],
                branchId: $reference->branchId,
                storeId: $reference->storeId,
                reasonText: $reference->reason,
                metadata: [
                    'original_source_type' => $reference->originalSourceType,
                    'original_source_id' => $reference->originalSourceId,
                    'correction_type' => $reference->correctionType->value,
                    'correction_source_id' => $reference->correctionSourceId,
                    'requested_by' => $reference->requestedBy,
                    'approved_by' => $reference->approvedBy,
                    'request_id' => $reference->requestId,
                    'idempotency_key' => $reference->idempotencyKey,
                ],
                requestId: $reference->requestId,
                explicitSourceId: $reference->correctionSourceId,
            );

            return $result;
        });
    }
}
