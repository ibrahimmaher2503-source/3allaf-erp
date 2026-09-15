<?php

declare(strict_types=1);

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AllocateDocumentNumber
{
    public function executeForBranch(string $documentType, int $branchId): string
    {
        $branchRuleExists = DocumentSequence::query()
            ->where('document_type', trim($documentType))
            ->where('scope_key', DocumentSequence::scopeKeyFor('branch', $branchId))
            ->exists();

        return $branchRuleExists
            ? $this->execute($documentType, 'branch', $branchId)
            : $this->execute($documentType);
    }

    public function execute(string $documentType, string $scopeType = 'company', ?int $scopeId = null): string
    {
        $documentType = trim($documentType);
        if ($documentType === '') {
            throw ValidationException::withMessages(['document_type' => __('A document type is required for number allocation.')]);
        }
        $scopeType = strtolower(trim($scopeType));
        if (! in_array($scopeType, ['company', 'branch'], true) || ($scopeType === 'company' && $scopeId !== null) || ($scopeType === 'branch' && $scopeId === null)) {
            throw ValidationException::withMessages(['scope' => __('Only company-wide and branch document-numbering scopes are supported.')]);
        }
        if ($scopeType === 'branch' && ! Branch::query()->whereKey($scopeId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['scope' => __('The selected branch is not active or does not exist.')]);
        }
        $scopeKey = DocumentSequence::scopeKeyFor($scopeType, $scopeId);

        return DB::transaction(function () use ($documentType, $scopeType, $scopeId, $scopeKey): string {
            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('scope_key', $scopeKey)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering is not configured for :type in the selected scope.', ['type' => $documentType])]);
            }
            if ($sequence->scope_type !== $scopeType || (int) ($sequence->scope_id ?? 0) !== (int) ($scopeId ?? 0)) {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document-numbering scope does not match the allocation context.')]);
            }
            if ($sequence->status !== 'active') {
                throw ValidationException::withMessages(['document_sequence' => __('Document numbering is not active for :type.', ['type' => $documentType])]);
            }
            if ($sequence->next_value < 1 || $sequence->padding_length < 1 || $sequence->padding_length > 12) {
                throw ValidationException::withMessages(['document_sequence' => __('The configured document sequence is invalid.')]);
            }

            $period = $sequence->currentPeriodKey();
            $reset = $period !== null && $sequence->last_reset_period !== null && $sequence->last_reset_period !== $period;
            $allocatedValue = $reset ? 1 : (int) $sequence->next_value;
            $nextValue = $allocatedValue + 1;
            $before = $sequence->only(['next_value', 'lock_version', 'last_reset_period']);
            $number = $sequence->formatValue($allocatedValue);
            $sequence->advanceCounter($nextValue, $period);

            app(RecordAuditEvent::class)->execute(
                category: 'numbering',
                event: 'document_number_allocated',
                source: $sequence,
                before: $before,
                after: $sequence->only(['next_value', 'lock_version', 'last_reset_period']),
                metadata: [
                    'document_type' => $documentType,
                    'scope_type' => $scopeType,
                    'scope_id' => $scopeId,
                    'allocated_number' => $number,
                    'reset_applied' => $reset,
                ],
            );

            return $number;
        });
    }
}
