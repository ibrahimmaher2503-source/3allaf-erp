<?php

namespace App\Modules\Platform\Actions;

use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\DocumentSequence;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class OverrideDocumentSequenceCounter
{
    public function execute(DocumentSequence $sequence, int $nextValue, int $expectedLockVersion, string $reason): DocumentSequence
    {
        Gate::authorize('drawers_payments_tax_numbering_printers.override');

        return $this->persist($sequence->id, $nextValue, $expectedLockVersion, $reason, true);
    }

    public function applyApproved(ApprovalRecord $approval): DocumentSequence
    {
        return DB::transaction(function () use ($approval): DocumentSequence {
            $approval = ApprovalRecord::query()->lockForUpdate()->findOrFail($approval->id);
            $actor = Auth::user();
            $context = $approval->limit_context ?? [];
            $id = isset($context['id']) && $context['id'] !== null ? (int) $context['id'] : null;
            $data = $context['proposed'] ?? null;
            $before = $context['before'] ?? null;
            $expectedHash = hash('sha256', json_encode($context, JSON_THROW_ON_ERROR));
            $expectedSourceId = 'document_sequence_override:'.($id ?? '');
            $sequence = $id === null ? null : DocumentSequence::query()->lockForUpdate()->findOrFail($id);
            $canonicalBefore = $sequence?->getAttributes();
            if ($canonicalBefore !== null) {
                unset($canonicalBefore['created_at'], $canonicalBefore['updated_at']);
            }

            if ($actor === null
                || $id === null
                || ! is_array($data)
                || (int) ($data['sequence_id'] ?? 0) !== $id
                || $approval->approval_state !== ApprovalState::Approved
                || $approval->approver_id !== $actor->id
                || $approval->source_type !== 'platform_settings'
                || $approval->requested_action !== 'document_sequence_override'
                || $approval->source_id !== $expectedSourceId
                || $approval->source_version !== (string) $id
                || $approval->source_hash !== $expectedHash
                || $before !== $canonicalBefore
                || $approval->request_permission !== 'drawers_payments_tax_numbering_printers.override'
                || $approval->decision_permission !== 'company_settings.approve'
                || ! Gate::forUser($actor)->allows('company_settings.approve')
                || AuditLog::query()->where('event', 'document_sequence_counter_overridden')->whereJsonContains('metadata->approval_record_id', $approval->id)->exists()) {
                throw ValidationException::withMessages(['approval' => __('The approved platform setting effect is not available.')]);
            }

            return $this->persist(
                $id,
                (int) ($data['next_value'] ?? 0),
                (int) ($data['expected_lock_version'] ?? 0),
                (string) ($data['reason'] ?? ''),
                false,
                ['approval_record_id' => $approval->id, 'requester_id' => $approval->requester_id],
            );
        });
    }

    /** @param array<string, mixed> $metadata */
    private function persist(int $sequenceId, int $nextValue, int $expectedLockVersion, string $reason, bool $visibleToCurrentUser, array $metadata = []): DocumentSequence
    {
        if ($nextValue < 1) {
            throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The next value must be at least one.')]);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['sequenceOverride.reason' => __('A sequence-counter override reason is required.')]);
        }

        return DB::transaction(function () use ($sequenceId, $nextValue, $expectedLockVersion, $reason, $visibleToCurrentUser, $metadata): DocumentSequence {
            $sequence = $visibleToCurrentUser
                ? DocumentSequence::visibleTo(Auth::user())->lockForUpdate()->findOrFail($sequenceId)
                : DocumentSequence::query()->lockForUpdate()->findOrFail($sequenceId);
            if ($sequence->lock_version !== $expectedLockVersion) {
                throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The sequence changed in another session. Reload before overriding it.')]);
            }
            if ($sequence->next_value === $nextValue) {
                throw ValidationException::withMessages(['sequenceOverride.next_value' => __('The override must change the current counter.')]);
            }

            $before = $sequence->only(['document_type', 'next_value', 'lock_version']);
            $sequence->advanceCounter($nextValue);
            app(RecordAuditEvent::class)->execute(
                'numbering',
                'document_sequence_counter_overridden',
                $sequence,
                $before,
                $sequence->only(['document_type', 'next_value', 'lock_version']),
                reasonText: trim($reason),
                metadata: ['override' => true, ...$metadata],
            );

            return $sequence;
        });
    }
}
