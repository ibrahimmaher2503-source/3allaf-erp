<?php

declare(strict_types=1);

namespace App\Modules\Retail\Support;

use App\Models\User;
use App\Modules\Retail\Data\PosContext;
use App\Modules\Retail\Models\PosShift;

final class PosContextResolver
{
    public function resolve(User $cashier): PosContext
    {
        $shift = PosShift::query()
            ->select('pos_shifts.*')
            ->join('active_pos_shift_assignments', function ($join): void {
                $join->on('active_pos_shift_assignments.shift_id', '=', 'pos_shifts.id')
                    ->on('active_pos_shift_assignments.cash_drawer_id', '=', 'pos_shifts.cash_drawer_id');
            })
            ->open()
            ->where('pos_shifts.cashier_id', $cashier->id)
            ->where('active_pos_shift_assignments.cashier_id', $cashier->id)
            ->with(['branch', 'store.company', 'cashDrawer'])
            ->first();

        if ($shift === null) {
            return new PosContext(null, null, null, null, __('POS is disabled until you open an assigned cashier shift.'));
        }

        $branch = $shift->branch;
        $store = $shift->store;
        $drawer = $shift->cashDrawer;
        if (
            $branch === null
            || $store === null
            || $drawer === null
            || $branch->status !== 'active'
            || $store->status !== 'active'
            || $store->type !== 'selling'
            || $drawer->status !== 'active'
            || (int) $store->branch_id !== (int) $branch->id
            || (int) $drawer->branch_id !== (int) $branch->id
            || (int) $drawer->store_id !== (int) $store->id
            || ! $cashier->canAccessStore((int) $store->id)
        ) {
            return new PosContext(null, null, null, null, __('POS is disabled because the active shift assignment is no longer available to this cashier.'));
        }

        // The open shift's explicit selling outlet is the only stock source.
        // Historical branch-wide primary mappings are deliberately ignored.
        return new PosContext($branch, $store, $drawer, $shift);
    }
}
