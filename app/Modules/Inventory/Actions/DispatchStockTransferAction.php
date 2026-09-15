<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class DispatchStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.dispatch');

        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: true, destination: false);
            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'approved') {
                throw new InvalidArgumentException(__('Only approved transfers can be dispatched.'));
            }
            $poster = app(PostInventoryMovement::class);
            foreach ($transfer->lines as $line) {
                $quantity = (string) $line->quantity_requested;
                $poster->execute($line->product_id, $transfer->source_store_id, '-'.$quantity, 'transfer_dispatch', (string) $line->unit_cost, 'DEMO-TRANSFER-DISPATCH:'.$transfer->id.':'.$line->id, StockTransfer::class, $transfer->id, $line->id);
                $poster->adjustInTransit($line->product_id, $transfer->destination_store_id, $quantity);
                $line->mutateApprovedParentLine(['quantity_dispatched' => $quantity]);
            }
            $before = $transfer->only(['status', 'lock_version']);
            $transfer->mutateApprovedDocument(['status' => 'in_transit', 'dispatched_by' => Auth::id(), 'dispatched_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'dispatch_stock_transfer', $transfer, $before, $transfer->only(['status', 'dispatched_by', 'dispatched_at', 'lock_version']), storeId: $transfer->source_store_id);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
