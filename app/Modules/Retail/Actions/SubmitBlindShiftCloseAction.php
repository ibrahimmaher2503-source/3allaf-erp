<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Data\BlindShiftCloseResult;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\ShiftClosingSubmission;
use App\Modules\Retail\Services\ShiftExpectedTotalsService;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Blind close submission (`docs/32` §10-§11, CSH-02/CSH-03).
 *
 * The cashier supplies actuals and receives nothing back that reveals the
 * expectation. Expected values are computed here — after the actuals are
 * captured — and stored on the immutable submission row.
 *
 * The ordering matters: deriving expected totals before persisting the actuals
 * would make it possible to return them in an error path.
 */
final class SubmitBlindShiftCloseAction
{
    public function __construct(private readonly ShiftExpectedTotalsService $totals) {}

    /**
     * @param  array<string, string>  $actualByMethod
     */
    public function execute(
        User $cashier,
        PosShift $shift,
        string $actualCash,
        array $actualByMethod,
        string $idempotencyKey,
        ?string $notes = null,
    ): BlindShiftCloseResult {
        abort_unless($cashier->can('shifts_cash_movements.submit'), 403);

        $actual = $this->money($actualCash);
        $normalizedMethods = $this->normalizeActualMethods($actualByMethod);
        $normalizedNotes = filled($notes) ? trim((string) $notes) : null;

        try {
            $submission = $this->attempt($cashier, $shift, $actual, $normalizedMethods, $idempotencyKey, $normalizedNotes);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_key')) {
                throw $e;
            }

            // docs/32 §11 — a duplicate submit is idempotently returned, not
            // treated as a second count.
            $submission = $this->assertReplaySafe(
                ShiftClosingSubmission::query()->where('idempotency_key', $idempotencyKey)->first(),
                $cashier,
                $shift,
                $actual,
                $normalizedMethods,
                $normalizedNotes,
            );
        }

        return $this->safeResult($submission);
    }

    /**
     * @param  array<string, string>  $actualByMethod
     */
    private function attempt(
        User $cashier,
        PosShift $shift,
        string $actual,
        array $actualByMethod,
        string $idempotencyKey,
        ?string $notes,
    ): ShiftClosingSubmission {
        return DB::transaction(function () use ($cashier, $shift, $actual, $actualByMethod, $idempotencyKey, $notes): ShiftClosingSubmission {
            $existing = ShiftClosingSubmission::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $cashier, $shift, $actual, $actualByMethod, $notes);
            }

            $shift = PosShift::query()->lockForUpdate()->findOrFail((int) $shift->getKey());
            /** @var ShiftState $state */
            $state = $shift->getAttribute('status');

            // Only an open shift may be submitted. A shift already in
            // variance_review is awaiting a manager, not another blind count,
            // unless a recount explicitly returned it to open.
            if ($state !== ShiftState::Open) {
                throw new InvalidArgumentException(__('Only an open shift can be submitted for closing.'));
            }

            abort_unless(
                (int) $shift->getAttribute('cashier_id') === $cashier->id || $cashier->is_super_admin,
                403,
            );

            if (bccomp($actual, '0', 2) < 0) {
                throw new InvalidArgumentException(__('The counted cash cannot be negative.'));
            }

            $this->assertActiveElectronicMethods(array_keys($actualByMethod));

            // Expected values are derived only now — after the actual count is
            // in hand (CSH-03).
            $expected = $this->totals->derive($shift);
            $variance = $this->totals->variance($actual, $actualByMethod, $expected);

            $attempt = (int) ShiftClosingSubmission::query()->where('shift_id', $shift->getKey())->max('attempt') + 1;

            $submission = ShiftClosingSubmission::query()->create([
                'shift_id' => $shift->getKey(),
                'attempt' => $attempt,
                'actual_cash' => $actual,
                'actual_by_method' => $actualByMethod,
                'expected_cash' => $expected['expected_cash'],
                'expected_by_method' => $expected['expected_by_method'],
                'cash_variance' => $variance['cash_variance'],
                'method_variance' => $variance['method_variance'],
                'total_variance' => $variance['total_variance'],
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
                'submitted_by' => $cashier->id,
                'submitted_at' => now(),
            ]);

            // The zero-variance auto-close rule is owner-blocked. Until it is
            // decided, every close remains subject to the same shared
            // maker/checker approval; the state only distinguishes whether a
            // non-zero variance needs explicit review attention.
            $nextState = bccomp($variance['total_variance'], '0', 2) === 0
                ? ShiftState::ClosingSubmitted
                : ShiftState::VarianceReview;

            $before = $shift->only(['status', 'lock_version']);
            $shift->update([
                'status' => $nextState->value,
                'submitted_at' => now(),
                'closing_cash' => $actual,
                'lock_version' => (int) $shift->getAttribute('lock_version') + 1,
            ]);

            // The variance decision is a shared Platform approval, not a
            // Retail-only status change.  Keep request creation in this same
            // transaction so a submitted shift can never be left without its
            // review record (or vice versa).
            $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
                sourceType: 'pos_shifts',
                sourceId: (string) $shift->getKey(),
                sourceVersion: (string) $shift->getAttribute('lock_version'),
                sourceHash: $this->approvalHash($shift),
                requestedAction: 'approve_close',
                requestPermission: 'shifts_cash_movements.submit',
                decisionPermission: 'shifts_cash_movements.approve',
                branchId: (int) $shift->getAttribute('branch_id'),
                storeId: (int) $shift->getAttribute('store_id'),
                reasonCode: 'shift_close',
                reasonText: $notes,
                idempotencyKey: 'SHIFT-APPROVAL:'.$shift->getKey().':'.$attempt,
            ));

            $shift->update(['variance_approval_record_id' => $approval->id]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'submit_shift_close',
                source: $shift,
                before: $before,
                after: $shift->only(['status', 'lock_version', 'variance_approval_record_id']),
                branchId: $shift->getAttribute('branch_id'),
                storeId: $shift->getAttribute('store_id'),
                metadata: [
                    'attempt' => $attempt,
                    'total_variance' => $variance['total_variance'],
                    'submitted_by' => $cashier->id,
                    'approval_record_id' => $approval->id,
                ],
            );

            return $submission;
        });
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('A counted amount must be a valid number.'));
        }

        return DecimalMoney::round($value, 2, __('A counted amount must be a valid number.'));
    }

    /** @param array<string, string> $values @return array<string, numeric-string> */
    private function normalizeActualMethods(array $values): array
    {
        $normalized = [];
        foreach ($values as $code => $value) {
            $code = trim((string) $code);
            if ($code === '') {
                throw new InvalidArgumentException(__('A payment method code is required for every electronic actual.'));
            }

            $amount = $this->money((string) $value);
            if (bccomp($amount, '0', 2) < 0) {
                throw new InvalidArgumentException(__('A counted electronic amount cannot be negative.'));
            }
            $normalized[$code] = $amount;
        }
        ksort($normalized);

        return $normalized;
    }

    /** @param list<string> $methodCodes */
    private function assertActiveElectronicMethods(array $methodCodes): void
    {
        if ($methodCodes === []) {
            return;
        }

        $methods = PaymentMethod::query()->whereIn('code', $methodCodes)->where('status', 'active')->get()->keyBy('code');
        foreach ($methodCodes as $code) {
            $method = $methods->get($code);
            if (! $method instanceof PaymentMethod || $method->isCash()) {
                throw new InvalidArgumentException(__('Actual electronic totals may use only active non-cash payment methods.'));
            }
        }
    }

    /**
     * @param array<string, numeric-string> $actualByMethod
     */
    private function assertReplaySafe(
        ?ShiftClosingSubmission $existing,
        User $cashier,
        PosShift $shift,
        string $actual,
        array $actualByMethod,
        ?string $notes,
    ): ShiftClosingSubmission {
        $storedMethods = (array) ($existing?->getAttribute('actual_by_method') ?? []);
        ksort($storedMethods);
        $replaySafe = $existing !== null
            && (int) $existing->getAttribute('shift_id') === (int) $shift->getKey()
            && (int) $existing->getAttribute('submitted_by') === $cashier->id
            && bccomp((string) $existing->getAttribute('actual_cash'), $actual, 2) === 0
            && $storedMethods === $actualByMethod
            && (string) ($existing->getAttribute('notes') ?? '') === (string) ($notes ?? '');

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    private function safeResult(ShiftClosingSubmission $submission): BlindShiftCloseResult
    {
        $shift = PosShift::query()->findOrFail((int) $submission->getAttribute('shift_id'));

        return new BlindShiftCloseResult(
            shiftId: (int) $shift->getKey(),
            submissionId: (int) $submission->getKey(),
            attempt: (int) $submission->getAttribute('attempt'),
            shiftState: $shift->getAttribute('status')->value,
        );
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
