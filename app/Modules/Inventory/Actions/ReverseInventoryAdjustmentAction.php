<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Inventory\Models\InventoryAdjustmentLine;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReverseInventoryAdjustmentAction
{
    public function execute(int $id, string $reason): InventoryAdjustment
    {
        Gate::authorize('inventory_stock_card.reverse');
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException(__('A reversal reason is required.'));
        }

        return DB::transaction(function () use ($id, $reason): InventoryAdjustment {
            $original = InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($id);
            app(AssertInventoryStoreScope::class)->execute($original->store_id);
            if ($original->status !== 'approved' || $original->reversal_of_id !== null || $original->reversed_at !== null) {
                throw new InvalidArgumentException(__('Only an approved, unreversed inventory document can be reversed.'));
            }
            $store = Store::query()->findOrFail($original->store_id);
            $reversal = InventoryAdjustment::query()->create([
                'adjustment_number' => app(AllocateDocumentNumber::class)->executeForBranch('inventory_adjustment', (int) $store->branch_id),
                'store_id' => $original->store_id,
                'adjustment_type' => 'adjustment',
                'status' => 'approved',
                'reason_code' => 'reversal',
                'reason_notes' => $reason,
                'allow_negative' => false,
                'created_by' => Auth::id(),
                'approved_by' => Auth::id(),
                'submitted_at' => now(),
                'approved_at' => now(),
                'reversal_of_id' => $original->id,
                'idempotency_key' => 'inventory-adjustment-reversal:'.$original->id,
                'lock_version' => 2,
                'notes' => 'Referenced reversal of '.$original->adjustment_number,
            ]);
            foreach ($original->lines as $line) {
                $movement = StockMovement::query()->where('source_type', InventoryAdjustment::class)->where('source_id', $original->id)->where('source_line_id', $line->id)->lockForUpdate()->firstOrFail();
                $reversalLine = InventoryAdjustmentLine::query()->create([
                    'inventory_adjustment_id' => $reversal->id,
                    'product_id' => $line->product_id,
                    'quantity_delta' => '-'.ltrim((string) $line->quantity_delta, '-'),
                    'unit_cost' => $line->unit_cost,
                ]);
                app(PostInventoryMovement::class)->execute($line->product_id, $original->store_id, (string) $reversalLine->quantity_delta, 'inventory_reversal', (string) $line->unit_cost, 'inventory-adjustment-reversal-movement:'.$original->id.':'.$line->id, InventoryAdjustment::class, $reversal->id, $reversalLine->id, false, $movement->id);
            }
            $originalBefore = $original->only(['status', 'reversed_at', 'reversed_by', 'reversal_of_id']);
            $original->mutateApprovedDocument(['reversed_by' => Auth::id(), 'reversed_at' => now(), 'reversal_reason' => $reason, 'lock_version' => $original->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'reverse_inventory_adjustment', $reversal, $originalBefore, ['original_adjustment_id' => $original->id, 'reversal_adjustment_id' => $reversal->id], storeId: $store->id, reasonCode: 'reversal', reasonText: $reason, metadata: ['original_adjustment_id' => $original->id, 'movement_count' => $original->lines->count()]);

            return $reversal->fresh(['store', 'lines.product', 'reversalOf']);
        });
    }
}
