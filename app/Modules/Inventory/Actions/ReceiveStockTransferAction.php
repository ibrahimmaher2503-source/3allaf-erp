<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferReceipt;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReceiveStockTransferAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int|string, string> $receivedQuantities */
    public function execute(int $id, array $receivedQuantities, ?string $differenceType, ?string $differenceReason, ?string $idempotencyKey = null): StockTransfer
    {
        Gate::authorize('transfers.receive');
        $key = filled($idempotencyKey) ? trim((string) $idempotencyKey) : (string) Str::uuid();

        return DB::transaction(function () use ($id, $receivedQuantities, $differenceType, $differenceReason, $key): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer, source: false, destination: true);
            $payloadHash = $this->payloadHash($transfer, $receivedQuantities, $differenceType, $differenceReason);
            $existing = StockTransferReceipt::query()->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ((int) $existing->stock_transfer_id !== $transfer->id || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new InvalidArgumentException(__('This idempotency key was already used for a different transfer receipt.'));
                }

                return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
            }

            app(AssertNoOpenStockCountTransfer::class)->execute($transfer->source_store_id, $transfer->destination_store_id);
            if ($transfer->status !== 'in_transit') {
                throw new InvalidArgumentException(__('Only in-transit transfers can be received.'));
            }
            $normalized = $this->normalize($transfer, $receivedQuantities, $differenceType, $differenceReason);

            $receipt = StockTransferReceipt::query()->create([
                'stock_transfer_id' => $transfer->id,
                'receipt_number' => 'TRR-'.$transfer->id.'-'.str_pad((string) (StockTransferReceipt::query()->where('stock_transfer_id', $transfer->id)->count() + 1), 4, '0', STR_PAD_LEFT),
                'status' => $normalized['closes_difference'] ? 'difference' : ($normalized['completes_transfer'] ? 'complete' : 'partial'),
                'received_by' => Auth::id(),
                'received_at' => now(),
                'difference_type' => $normalized['difference_type'],
                'difference_reason' => $normalized['difference_reason'],
                'idempotency_key' => $key,
                'payload_hash' => $payloadHash,
            ]);

            $poster = app(PostInventoryMovement::class);
            foreach ($transfer->lines as $line) {
                $event = $normalized['lines'][$line->id];
                if (bccomp($event['received'], '0', 6) > 0) {
                    $poster->execute($line->product_id, $transfer->destination_store_id, $event['received'], 'transfer_receipt', (string) $line->unit_cost, 'TRANSFER-RECEIPT:'.$receipt->id.':'.$line->id, StockTransfer::class, $transfer->id, $line->id);
                }
                $released = bcadd($event['received'], $event['difference'], 6);
                if (bccomp($released, '0', 6) > 0) {
                    $poster->adjustInTransit($line->product_id, $transfer->destination_store_id, '-'.$released);
                }
                $receipt->lines()->create(['stock_transfer_line_id' => $line->id, 'quantity_received' => $event['received'], 'difference_quantity' => $event['difference']]);
                $line->mutateApprovedParentLine([
                    'quantity_received' => bcadd((string) $line->quantity_received, $event['received'], 6),
                    'difference_quantity' => bcadd((string) $line->difference_quantity, $event['difference'], 6),
                    'difference_type' => $event['difference'] === '0.000000' ? $line->difference_type : $normalized['difference_type'],
                    'difference_reason' => $event['difference'] === '0.000000' ? $line->difference_reason : $normalized['difference_reason'],
                ]);
            }

            $status = $normalized['closes_difference'] ? 'difference_review' : ($normalized['completes_transfer'] ? 'received' : 'in_transit');
            $before = $transfer->only(['status', 'difference_status', 'lock_version']);
            $transfer->mutateApprovedDocument([
                'status' => $status,
                'difference_status' => $status === 'difference_review' ? 'under_review' : null,
                'received_by' => Auth::id(),
                'received_at' => now(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            app(RecordAuditEvent::class)->execute('inventory', 'receive_stock_transfer', $transfer, $before, $transfer->only(['status', 'difference_status', 'received_by', 'received_at', 'lock_version']), storeId: $transfer->destination_store_id, reasonCode: $normalized['difference_type'], reasonText: $normalized['difference_reason'], metadata: ['receipt_id' => $receipt->id, 'received_quantities' => $normalized['lines'], 'idempotency_key' => $key]);

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @param array<int|string, string> $receivedQuantities @return array<string, mixed> */
    private function normalize(StockTransfer $transfer, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): array
    {
        if ($transfer->lines->isEmpty()) {
            throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
        }
        $differenceType = trim((string) $differenceType) ?: null;
        $differenceReason = trim((string) $differenceReason) ?: null;
        if ($differenceType !== null && ! in_array($differenceType, ['shortage', 'damage', 'refusal'], true)) {
            throw new InvalidArgumentException(__('A valid transfer difference type is required.'));
        }
        if (($differenceType === null) !== ($differenceReason === null)) {
            throw new InvalidArgumentException(__('A shortage/damage/refusal type and reason must be provided together.'));
        }

        $lines = [];
        $hasReceived = false;
        $remainingAfter = '0.000000';
        foreach ($transfer->lines as $line) {
            $received = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
            $remaining = bcsub((string) $line->quantity_dispatched, bcadd((string) $line->quantity_received, (string) $line->difference_quantity, 6), 6);
            if (bccomp($received, $remaining, 6) > 0) {
                throw new InvalidArgumentException(__('Received quantity cannot exceed the remaining in-transit quantity.'));
            }
            $after = bcsub($remaining, $received, 6);
            $difference = $differenceType === null ? '0.000000' : $after;
            $lines[$line->id] = ['received' => $received, 'difference' => $difference];
            $hasReceived = $hasReceived || bccomp($received, '0', 6) > 0;
            $remainingAfter = bcadd($remainingAfter, $after, 6);
        }
        if ($differenceType !== null && bccomp($remainingAfter, '0', 6) === 0) {
            throw new InvalidArgumentException(__('A transfer difference can only be recorded when a quantity remains unreceived.'));
        }
        if (! $hasReceived && $differenceType === null) {
            throw new InvalidArgumentException(__('At least one received quantity must be greater than zero.'));
        }

        return ['lines' => $lines, 'difference_type' => $differenceType, 'difference_reason' => $differenceReason, 'closes_difference' => $differenceType !== null && bccomp($remainingAfter, '0', 6) > 0, 'completes_transfer' => bccomp($remainingAfter, '0', 6) === 0];
    }

    /** @param array<int|string, string> $receivedQuantities */
    private function payloadHash(StockTransfer $transfer, array $receivedQuantities, ?string $differenceType, ?string $differenceReason): string
    {
        $lineIds = $transfer->lines->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        if (array_diff(array_map('strval', array_keys($receivedQuantities)), $lineIds) !== []) {
            throw new InvalidArgumentException(__('Received quantities contain a line outside this transfer.'));
        }
        $lines = [];
        foreach ($transfer->lines->sortBy('id') as $line) {
            $lines[(string) $line->id] = $this->decimal($receivedQuantities[$line->id] ?? $receivedQuantities[(string) $line->id] ?? '0');
        }

        return hash('sha256', json_encode([
            'lines' => $lines,
            'difference_type' => trim((string) $differenceType) ?: null,
            'difference_reason' => trim((string) $differenceReason) ?: null,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid transfer quantity.'));
        }

        return bcadd($value, '0', 6);
    }
}
