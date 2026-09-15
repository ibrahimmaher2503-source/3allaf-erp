<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ReceiveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int|string, string> $receivedQuantities */
    public function execute(int $id, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): StockTransfer
    {
        Gate::authorize('transfers.receive');

        return DB::transaction(function () use ($id, $receivedQuantities, $differenceType, $differenceReason): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: false, destination: true);
            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            if ($transfer->lines->isEmpty()) {
                throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
            }
            if ($differenceType !== null && ! in_array($differenceType, ['shortage', 'damage', 'refusal'], true)) {
                throw new InvalidArgumentException(__('A valid transfer difference type is required.'));
            }
            $totalDifference = '0.000000';
            $receivedByLine = [];
            foreach ($transfer->lines as $line) {
                $received = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
                $dispatched = $this->decimal($line->quantity_dispatched);
                if (bccomp($received, '0', 6) < 0 || bccomp($received, $dispatched, 6) > 0) {
                    throw new InvalidArgumentException(__('Received quantity must be between zero and the dispatched quantity.'));
                }
                $difference = bcsub($dispatched, $received, 6);
                $receivedByLine[$line->id] = ['received' => $received, 'dispatched' => $dispatched, 'difference' => $difference];
                $totalDifference = bcadd($totalDifference, $difference, 6);
            }
            if (bccomp($totalDifference, '0', 6) > 0 && (trim((string) $differenceType) === '' || trim((string) $differenceReason) === '')) {
                throw new InvalidArgumentException(__('A shortage/damage/refusal reason is required for a transfer difference.'));
            }
            foreach ($transfer->lines as $line) {
                $quantities = $receivedByLine[$line->id];
                if (bccomp($quantities['received'], '0', 6) > 0) {
                    app(PostInventoryMovement::class)->execute($line->product_id, $transfer->destination_store_id, $quantities['received'], 'transfer_receipt', (string) $line->unit_cost, 'DEMO-TRANSFER-RECEIPT:'.$transfer->id.':'.$line->id, StockTransfer::class, $transfer->id, $line->id);
                }
                app(PostInventoryMovement::class)->adjustInTransit($line->product_id, $transfer->destination_store_id, '-'.$quantities['dispatched']);
                $line->mutateApprovedParentLine(['quantity_received' => $quantities['received'], 'difference_quantity' => $quantities['difference'], 'difference_type' => $differenceType, 'difference_reason' => $differenceReason]);
            }
            $status = bccomp($totalDifference, '0', 6) === 0 ? 'received' : 'difference_review';
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $transfer->mutateApprovedDocument(['status' => $status, 'difference_status' => $status === 'received' ? null : 'under_review', 'reason_code' => $differenceType, 'reason_notes' => $differenceReason, 'received_by' => Auth::id(), 'received_at' => now(), 'lock_version' => $transfer->lock_version + 1]);
            app(RecordAuditEvent::class)->execute('inventory', 'receive_stock_transfer', $transfer, $before, $transfer->only(['status', 'difference_status', 'received_by', 'received_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $differenceType, reasonText: $differenceReason, metadata: ['received_quantities' => $receivedByLine, 'difference_quantity' => $totalDifference]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid transfer quantity.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }
}
