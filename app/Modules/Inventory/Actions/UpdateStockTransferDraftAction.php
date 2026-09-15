<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class UpdateStockTransferDraftAction
{
    public function __construct(private readonly AssertInventoryStoreScope $scope) {}

    /** @param array<int, array{product_id: int, quantity_requested: string}> $lines */
    public function execute(
        int $id,
        int $sourceStoreId,
        int $destinationStoreId,
        array $lines,
        ?string $reasonCode = null,
        ?string $notes = null,
        ?int $expectedVersion = null,
    ): StockTransfer {
        Gate::authorize('transfers.edit');

        return DB::transaction(function () use ($id, $sourceStoreId, $destinationStoreId, $lines, $reasonCode, $notes, $expectedVersion): StockTransfer {
            $transfer = StockTransfer::query()->with('lines')->lockForUpdate()->findOrFail($id);
            $this->scope->transfer($transfer);
            if ($transfer->status !== 'draft') {
                throw new InvalidArgumentException(__('Only draft transfers can be edited.'));
            }
            if ($expectedVersion !== null && (int) $transfer->lock_version !== $expectedVersion) {
                throw new InvalidArgumentException(__('This transfer changed in another session. Please reload before saving.'));
            }
            if ($sourceStoreId === $destinationStoreId) {
                throw new InvalidArgumentException(__('Source and destination stores must be different.'));
            }
            $this->scope->execute($sourceStoreId);
            $this->scope->execute($destinationStoreId);
            app(AssertNoOpenStockCountTransfer::class)->execute($sourceStoreId, $destinationStoreId);
            $normalized = $this->normalizeLines($lines);
            $before = $transfer->only(['source_store_id', 'destination_store_id', 'reason_code', 'notes', 'status', 'lock_version']);
            $transfer->mutateApprovedDocument([
                'source_store_id' => $sourceStoreId,
                'destination_store_id' => $destinationStoreId,
                'reason_code' => $this->nullableTrimmed($reasonCode),
                'notes' => $this->nullableTrimmed($notes),
                'requested_by' => Auth::id(),
                'lock_version' => $transfer->lock_version + 1,
            ]);
            $transfer->lines()->delete();
            foreach ($normalized as $line) {
                $transfer->lines()->create($line);
            }
            app(RecordAuditEvent::class)->execute(
                category: 'inventory',
                event: 'update_stock_transfer_draft',
                source: $transfer,
                before: $before,
                after: $transfer->fresh('lines')->only(['transfer_number', 'source_store_id', 'destination_store_id', 'status', 'reason_code', 'notes', 'lock_version']),
                storeId: $sourceStoreId,
                reasonCode: $transfer->reason_code,
                metadata: ['destination_store_id' => $destinationStoreId, 'line_count' => count($normalized), 'actor_id' => Auth::id()],
            );

            return $transfer->fresh(['sourceStore', 'destinationStore', 'lines.product']);
        });
    }

    /** @param array<int, array{product_id: int, quantity_requested: string}> $lines @return array<int, array<string, mixed>> */
    private function normalizeLines(array $lines): array
    {
        if ($lines === []) {
            throw new InvalidArgumentException(__('A transfer must contain at least one line.'));
        }
        $normalized = [];
        foreach ($lines as $line) {
            $product = Product::query()->sellable()->find((int) ($line['product_id'] ?? 0));
            if ($product === null) {
                throw new InvalidArgumentException(__('Every transfer line must reference an active product.'));
            }
            $quantity = trim((string) ($line['quantity_requested'] ?? ''));
            if (! preg_match('/^\d+(?:\.\d+)?$/', $quantity)) {
                throw new InvalidArgumentException(__('Invalid transfer quantity.'));
            }
            $quantity = bcadd($quantity, '0', 6);
            if (bccomp($quantity, '0', 6) <= 0 || (! $product->fractional_quantity && bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0)) {
                throw new InvalidArgumentException(__('Transfer quantity is invalid for this product.'));
            }
            if (isset($normalized[$product->id])) {
                throw new InvalidArgumentException(__('A transfer cannot contain the same product more than once.'));
            }
            $normalized[$product->id] = ['product_id' => $product->id, 'quantity_requested' => $quantity, 'unit_cost' => bcadd((string) ($product->average_cost ?? '0'), '0', 4)];
        }

        return array_values($normalized);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
