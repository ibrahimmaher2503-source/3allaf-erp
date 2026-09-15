<?php

declare(strict_types=1);

namespace App\Modules\Retail\Actions;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Support\DecimalMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Open a shift (`docs/32` §6).
 *
 * Exclusivity is the point of this action: at most one active shift per drawer
 * and per cashier. Both are checked under a row lock on the drawer so two
 * concurrent opens cannot both pass (§16).
 */
final class OpenShiftAction
{
    private const INVALID_DRAWER_CONFIGURATION = 'The selected cash drawer has an invalid company, branch, or store configuration. Ask an administrator to correct the drawer before opening a shift.';

    public function execute(User $cashier, CashDrawer $drawer, string $openingFloat, string $idempotencyKey): PosShift
    {
        abort_unless($cashier->can('shifts_cash_movements.create'), 403);

        try {
            return $this->attempt($cashier, $drawer, $openingFloat, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
            }

            if (PosShift::query()->active()->where('cashier_id', $cashier->id)->exists()) {
                throw new InvalidArgumentException(__('This cashier already has an active shift.'), previous: $e);
            }
            if (PosShift::query()->active()->where('cash_drawer_id', $drawer->getKey())->exists()) {
                throw new InvalidArgumentException(__('This drawer already has an active shift.'), previous: $e);
            }

            throw $e;
        }
    }

    private function attempt(User $cashier, CashDrawer $drawer, string $openingFloat, string $idempotencyKey): PosShift
    {
        return DB::transaction(function () use ($cashier, $drawer, $openingFloat, $idempotencyKey): PosShift {
            $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
            }

            // Lock the drawer row first: it is the shared resource both the
            // drawer-collision and cashier-collision checks depend on.
            $drawer = CashDrawer::query()->lockForUpdate()->find((int) $drawer->getKey());
            if ($drawer === null) {
                throw new InvalidArgumentException(__(self::INVALID_DRAWER_CONFIGURATION));
            }

            abort_unless($drawer->visibleTo($cashier)->whereKey($drawer->getKey())->exists(), 403);

            if ($drawer->getAttribute('status') !== 'active') {
                throw new InvalidArgumentException(__('This cash drawer is not active.'));
            }

            $float = $this->money($openingFloat);
            if (bccomp($float, '0', 2) < 0) {
                throw new InvalidArgumentException(__('The opening float cannot be negative.'));
            }

            $companyId = (int) $drawer->getAttribute('company_id');
            $branchId = (int) $drawer->getAttribute('branch_id');
            $storeId = (int) $drawer->getAttribute('store_id');
            $company = $companyId > 0 ? Company::query()->find($companyId) : null;
            $branch = $branchId > 0 ? Branch::query()->find($branchId) : null;
            $store = $storeId > 0 ? Store::query()->find($storeId) : null;
            if ($company === null
                || $branch === null
                || $store === null
                || (int) $branch->company_id !== $companyId
                || (int) $store->company_id !== $companyId
                || (int) $store->branch_id !== $branchId) {
                throw new InvalidArgumentException(__(self::INVALID_DRAWER_CONFIGURATION));
            }
            $currency = strtoupper(trim((string) $company->getAttribute('currency_code')));
            if (! preg_match('/^[A-Z]{3}$/', $currency) || $currency === 'TBD') {
                throw new InvalidArgumentException(__('The drawer company must have an approved three-letter currency before a shift can open.'));
            }

            $activeStates = $this->activeStates();

            if (PosShift::query()->where('cash_drawer_id', $drawer->getKey())->whereIn('status', $activeStates)->exists()) {
                throw new InvalidArgumentException(__('This drawer already has an active shift.'));
            }

            if (PosShift::query()->where('cashier_id', $cashier->id)->whereIn('status', $activeStates)->exists()) {
                throw new InvalidArgumentException(__('This cashier already has an active shift.'));
            }

            $shift = PosShift::query()->create([
                'branch_id' => $drawer->getAttribute('branch_id'),
                'store_id' => $drawer->getAttribute('store_id'),
                'cash_drawer_id' => $drawer->getKey(),
                'cashier_id' => $cashier->id,
                'opened_by' => $cashier->id,
                'status' => ShiftState::Open->value,
                'opening_cash' => $float,
                'currency_code' => $currency,
                'company_name_ar_snapshot' => $company->name_ar,
                'company_name_en_snapshot' => $company->name_en,
                'branch_code_snapshot' => $branch->code,
                'branch_name_ar_snapshot' => $branch->name_ar,
                'branch_name_en_snapshot' => $branch->name_en,
                'store_code_snapshot' => $store->code,
                'store_name_ar_snapshot' => $store->name_ar,
                'store_name_en_snapshot' => $store->name_en,
                'cash_drawer_code_snapshot' => $drawer->code,
                'cash_drawer_name_ar_snapshot' => $drawer->name_ar,
                'cash_drawer_name_en_snapshot' => $drawer->name_en,
                'idempotency_key' => $idempotencyKey,
                'opened_at' => now(),
                'lock_version' => 1,
            ]);

            DB::table('active_pos_shift_assignments')->insert([
                'shift_id' => $shift->getKey(),
                'cashier_id' => $cashier->id,
                'cash_drawer_id' => $drawer->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'retail',
                event: 'open_shift',
                source: $shift,
                before: [],
                after: $shift->only(['status', 'opening_cash', 'cash_drawer_id', 'cashier_id']),
                branchId: $shift->getAttribute('branch_id'),
                storeId: $shift->getAttribute('store_id'),
                metadata: ['opening_float' => $float],
            );

            return $shift;
        });
    }

    /** @return list<string> */
    private function activeStates(): array
    {
        return array_values(array_map(
            static fn (ShiftState $state): string => $state->value,
            array_filter(ShiftState::cases(), static fn (ShiftState $state): bool => $state->isActive()),
        ));
    }

    private function replay(string $idempotencyKey, User $cashier, CashDrawer $drawer, string $openingFloat): PosShift
    {
        $existing = PosShift::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $this->assertReplaySafe($existing, $cashier, $drawer, $openingFloat);
    }

    private function assertReplaySafe(PosShift $existing, User $cashier, CashDrawer $drawer, string $openingFloat): PosShift
    {
        $float = $this->money($openingFloat);
        $replaySafe = (int) $existing->getAttribute('cashier_id') === $cashier->id
            && (int) $existing->getAttribute('cash_drawer_id') === (int) $drawer->getKey()
            && (int) $existing->getAttribute('branch_id') === (int) $drawer->getAttribute('branch_id')
            && (int) $existing->getAttribute('store_id') === (int) $drawer->getAttribute('store_id')
            && bccomp((string) $existing->getAttribute('opening_cash'), $float, 2) === 0;

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
            throw new InvalidArgumentException(__('The opening float must be a valid number.'));
        }

        return DecimalMoney::round($value, 2, __('The opening float must be a valid number.'));
    }
}
