<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class SubmitStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    public function execute(int $id): StockTransfer
    {
        Gate::authorize('transfers.submit');

        return DB::transaction(function () use ($id): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);
            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft transfers can be submitted.'));
            }
            if ($transfer->source_store_id === $transfer->destination_store_id) {
                throw new InvalidArgumentException(__('Source and destination stores must be different.'));
            }
            if ($transfer->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
            }
            foreach ($transfer->lines as $line) {
                if (bccomp((string) $line->quantity_requested, '0', 6) <= 0) {
                    throw new InvalidArgumentException(__('Transfer quantities must be greater than zero.'));
                }
                $balance = StockBalance::query()->where('product_id', $line->product_id)->where('store_id', $transfer->source_store_id)->lockForUpdate()->first();
                if ($balance === null || bccomp((string) $balance->on_hand, (string) $line->quantity_requested, 6) < 0) {
                    throw new InvalidArgumentException(__('The source store does not have enough available stock for this transfer.'));
                }
            }
            $before = $transfer->only(['status', 'requested_by', 'lock_version']);
            $transfer->mutateApprovedDocument(['status' => 'submitted', 'requested_by' => Auth::id(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'submit_stock_transfer', $transfer, $before, $transfer->only(['status', 'requested_by', 'lock_version']), storeId: $transfer->source_store_id, metadata: ['destination_store_id' => $transfer->destination_store_id, 'line_count' => $transfer->lines->count()]);
            app(RequestStockTransferApprovalAction::class)->execute($transfer->id);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }
}
