<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\ApproveRequest;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RejectRequest;
use App\Modules\Platform\Enums\ApprovalState;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Manager review, recount, and final closure (`docs/32` §13-§14).
 *
 * Separation of duties is enforced here rather than in the UI: a cashier may
 * not approve their own variance, regardless of permission, unless an explicit
 * exceptional grant exists.
 */
final class ReviewShiftVarianceAction
{
    /**
     * Return a submitted shift to the cashier for a controlled recount.
     */
    public function requestRecount(User $reviewer, PosShift $shift, ApprovalRecord $approval, string $reason, int $expectedLockVersion): PosShift
    {
        abort_unless(Auth::id() === $reviewer->id, 403, 'Reviewer identity does not match the authenticated session.');

        return DB::transaction(function () use ($reviewer, $shift, $approval, $reason, $expectedLockVersion): PosShift {
            $shift = $this->lockForReview($shift, $expectedLockVersion);
            /** @var ShiftState $state */
            $state = $shift->getAttribute('status');

            if ($state !== ShiftState::VarianceReview && $state !== ShiftState::ClosingSubmitted) {
                throw new InvalidArgumentException(__('Only a submitted shift can be returned for recount.'));
            }

            if (trim($reason) === '') {
                throw new InvalidArgumentException(__('A recount request requires a reason.'));
            }

            $this->assertNotOwnShift($reviewer, $shift);

            $approval = $this->pendingApproval($shift, $approval);
            app(RejectRequest::class)->execute(
                $approval,
                (string) $shift->getAttribute('lock_version'),
                $reason,
                $this->approvalHash($shift),
            );

            $before = $shift->only(['status', 'lock_version']);
            try {
                $shift->update([
                    'status' => ShiftState::Open->value,
                    'submitted_at' => null,
                    'closing_cash' => null,
                    'recount_count' => (int) $shift->getAttribute('recount_count') + 1,
                    'lock_version' => (int) $shift->getAttribute('lock_version') + 1,
                ]);

                $this->audit('request_shift_recount', $shift, $before, $reviewer, ['reason' => $reason]);
            } catch (\Throwable $exception) {
                throw new InvalidArgumentException('Recount post-decision failure: '.$exception::class.' '.$exception->getMessage(), previous: $exception);
            }

            return $shift;
        });
    }

    /**
     * Approve the variance and close the shift immutably.
     */
    public function approveAndClose(User $reviewer, PosShift $shift, ApprovalRecord $approval, int $expectedLockVersion, ?string $note = null): PosShift
    {
        abort_unless(Auth::id() === $reviewer->id, 403);

        return DB::transaction(function () use ($reviewer, $shift, $approval, $expectedLockVersion, $note): PosShift {
            $shift = $this->lockForReview($shift, $expectedLockVersion);
            /** @var ShiftState $state */
            $state = $shift->getAttribute('status');

            if ($state->isTerminal()) {
                throw new InvalidArgumentException(__('This shift is already closed.'));
            }

            if ($state === ShiftState::Open) {
                throw new InvalidArgumentException(__('This shift has not been submitted for closing.'));
            }

            $this->assertNotOwnShift($reviewer, $shift);

            $approval = $this->pendingApproval($shift, $approval);
            app(ApproveRequest::class)->execute(
                $approval,
                (string) $shift->getAttribute('lock_version'),
                $this->approvalHash($shift),
                $note,
            );

            $before = $shift->only(['status', 'closing_document_number', 'lock_version']);
            $shift->update([
                'status' => ShiftState::Closed->value,
                'closed_at' => now(),
                'closed_by' => $reviewer->id,
                // These fields are a closure snapshot only. ApprovalRecord is
                // the decision authority and is linked below.
                'variance_approved_by' => $reviewer->id,
                'variance_approved_at' => now(),
                'variance_approval_note' => $note,
                'closing_document_number' => $this->allocateClosingNumber((int) $shift->getAttribute('branch_id')),
                'lock_version' => (int) $shift->getAttribute('lock_version') + 1,
            ]);

            DB::table('active_pos_shift_assignments')->where('shift_id', $shift->getKey())->delete();

            $this->audit('close_shift', $shift, $before, $reviewer, [
                'closing_document_number' => $shift->getAttribute('closing_document_number'),
                'note' => $note,
            ]);

            return $shift;
        });
    }

    /** Maker/checker remains mandatory while the zero-variance rule is owner-blocked. */
    private function assertNotOwnShift(User $reviewer, PosShift $shift): void
    {
        if ((int) $shift->getAttribute('cashier_id') !== $reviewer->id || $reviewer->canBypassApproval()) {
            return;
        }

        // docs/32 §13 — the cashier cannot review their own variance unless an
        // explicit exceptional permission exists.
        throw new AuthorizationException(__('A cashier cannot approve their own shift variance.'));
    }

    private function lockForReview(PosShift $shift, int $expectedLockVersion): PosShift
    {
        $locked = PosShift::query()->lockForUpdate()->findOrFail((int) $shift->getKey());

        // Stale-version protection (docs/32 §16): a reviewer acting on a stale
        // page must not overwrite a decision made in between.
        if ((int) $locked->getAttribute('lock_version') !== $expectedLockVersion) {
            throw ValidationException::withMessages([
                'lock_version' => __('This shift changed since it was loaded. Reload and review the current figures.'),
            ]);
        }

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $event, PosShift $shift, array $before, User $actor, array $metadata): void
    {
        app(RecordAuditEvent::class)->execute(
            category: 'retail',
            event: $event,
            source: $shift,
            before: $before,
            after: $shift->only(['status', 'closing_document_number', 'lock_version']),
            branchId: $shift->getAttribute('branch_id'),
            storeId: $shift->getAttribute('store_id'),
            metadata: $metadata + ['actor_id' => $actor->id],
        );
    }

    private function allocateClosingNumber(int $branchId): string
    {
        return app(AllocateDocumentNumber::class)->executeForBranch('shift_close', $branchId);
    }

    private function pendingApproval(PosShift $shift, ApprovalRecord $approval): ApprovalRecord
    {
        if ((int) $shift->getAttribute('variance_approval_record_id') !== (int) $approval->getKey()) {
            throw new InvalidArgumentException(__('This approval request does not belong to the shift being reviewed.'));
        }

        return ApprovalRecord::query()->whereKey($approval->getKey())
            ->where('source_type', 'pos_shifts')->where('source_id', (string) $shift->getKey())
            ->where('requested_action', 'approve_close')->where('approval_state', ApprovalState::Pending->value)
            ->lockForUpdate()->firstOrFail();
    }

    private function approvalHash(PosShift $shift): string
    {
        return hash('sha256', implode('|', [
            $shift->getKey(),
            $shift->getAttribute('status')->value,
            $shift->getAttribute('lock_version'),
            $shift->getAttribute('closing_cash'),
            $shift->getAttribute('submitted_at')?->format('c'),
        ]));
    }
}
