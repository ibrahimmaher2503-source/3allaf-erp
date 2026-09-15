<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\CashMovement;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Record a cash movement against an open shift (`docs/32` §8).
 *
 * Amounts are supplied unsigned and signed here by type, so a caller cannot
 * turn a disbursement into a deposit by passing a negative number.
 */
final class RecordCashMovementAction
{
    /** Types that remove cash from the drawer. */
    private const OUTFLOWS = [
        CashMovement::TYPE_CASH_OUT,
        CashMovement::TYPE_PETTY_DISBURSEMENT,
        CashMovement::TYPE_SAFE_DEPOSIT,
    ];

    public function execute(
        User $actor,
        PosShift $shift,
        string $movementType,
        string $amount,
        string $reason,
        string $idempotencyKey,
        ?string $reference = null,
    ): CashMovement {
        abort_unless($actor->can('shifts_cash_movements.create'), 403);

        $normalizedAmount = $this->money($amount);
        $normalizedReason = trim($reason);
        $normalizedReference = filled($reference) ? trim((string) $reference) : null;

        try {
            return $this->attempt($actor, $shift, $movementType, $normalizedAmount, $normalizedReason, $idempotencyKey, $normalizedReference);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_key')) {
                throw $e;
            }

            $existing = CashMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing === null) {
                throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
            }

            return $this->assertReplaySafe($existing, $actor, $shift, $movementType, $normalizedAmount, $normalizedReason, $normalizedReference);
        }
    }

    private function attempt(
        User $actor,
        PosShift $shift,
        string $movementType,
        string $amount,
        string $reason,
        string $idempotencyKey,
        ?string $reference,
    ): CashMovement {
        return DB::transaction(function () use ($actor, $shift, $movementType, $amount, $reason, $idempotencyKey, $reference): CashMovement {
            $existing = CashMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $actor, $shift, $movementType, $amount, $reason, $reference);
            }

            $shift = PosShift::query()->lockForUpdate()->findOrFail((int) $shift->getKey());

            if (! in_array($movementType, CashMovement::TYPES, true)) {
                throw new InvalidArgumentException(__('Unknown cash movement type.'));
            }

            /** @var ShiftState $state */
            $state = $shift->getAttribute('status');
            if (! $state->acceptsActivity()) {
                // docs/32 §14 — a submitted or closed shift accepts no further
                // drawer activity, otherwise the expected total it was closed
                // against becomes wrong after the fact.
                throw new InvalidArgumentException(__('This shift no longer accepts cash movements.'));
            }

            abort_unless(
                (int) $shift->getAttribute('cashier_id') === $actor->id
                || $actor->can('shifts_cash_movements.approve')
                || $actor->is_super_admin,
                403,
            );

            if (trim($reason) === '') {
                throw new InvalidArgumentException(__('A cash movement requires a reason.'));
            }

            $magnitude = $this->money($amount);
            if (bccomp($magnitude, '0', 2) <= 0) {
                throw new InvalidArgumentException(__('A cash movement amount must be greater than zero.'));
            }

            $signed = in_array($movementType, self::OUTFLOWS, true)
                ? bcsub('0', $magnitude, 2)
                : $magnitude;

            $movement = CashMovement::query()->create([
                'shift_id' => $shift->getKey(),
                'cash_drawer_id' => $shift->getAttribute('cash_drawer_id'),
                'branch_id' => $shift->getAttribute('branch_id'),
                'store_id' => $shift->getAttribute('store_id'),
                'movement_type' => $movementType,
                'amount' => $signed,
                'reason' => $reason,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor->id,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'cash_movement',
                source: $movement,
                before: [],
                after: $movement->only(['movement_type', 'amount', 'shift_id']),
                branchId: $shift->getAttribute('branch_id'),
                storeId: $shift->getAttribute('store_id'),
                metadata: ['reason' => $reason, 'actor_id' => $actor->id],
            );

            return $movement;
        });
    }

    private function assertReplaySafe(
        CashMovement $existing,
        User $actor,
        PosShift $shift,
        string $movementType,
        string $amount,
        string $reason,
        ?string $reference,
    ): CashMovement
    {
        $signed = in_array($movementType, self::OUTFLOWS, true) ? bcsub('0', $amount, 2) : $amount;
        $replaySafe = (int) $existing->getAttribute('shift_id') === (int) $shift->getKey()
            && (int) $existing->getAttribute('created_by') === $actor->id
            && (int) $existing->getAttribute('cash_drawer_id') === (int) $shift->getAttribute('cash_drawer_id')
            && (int) $existing->getAttribute('branch_id') === (int) $shift->getAttribute('branch_id')
            && (int) $existing->getAttribute('store_id') === (int) $shift->getAttribute('store_id')
            && $existing->getAttribute('movement_type') === $movementType
            && bccomp((string) $existing->getAttribute('amount'), $signed, 2) === 0
            && (string) $existing->getAttribute('reason') === $reason
            && (string) ($existing->getAttribute('reference') ?? '') === (string) ($reference ?? '');

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    /** @return numeric-string */
    private function money(string $value): string
    {
        $value = trim($value);
        if ($value === '' || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('A cash movement amount must be a valid number.'));
        }

        return DecimalMoney::round($value, 2, __('A cash movement amount must be a valid number.'));
    }
}
