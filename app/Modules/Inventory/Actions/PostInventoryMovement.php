<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryBatch;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\ProductQuantity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PostInventoryMovement
{
    public function execute(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType = null, ?int $sourceId = null, ?int $sourceLineId = null, bool $allowNegative = false, ?int $reversalOfId = null, bool $requireFirstMovement = false, ?int $batchId = null): StockMovement
    {
        try {
            return $this->attempt($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $allowNegative, $reversalOfId, $requireFirstMovement, $batchId);
        } catch (UniqueConstraintViolationException $e) {
            if (! str_contains($e->getMessage(), 'idempotency_key')) {
                throw $e;
            }

            // Two concurrent callers can both pass the pre-insert idempotency
            // check before either commits (the check-then-insert is not itself
            // lockable). The unique index is the real guard against a
            // duplicate row; this recovers the loser's request as a normal
            // idempotent replay instead of surfacing a raw DB error.
            return $this->replayExisting($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $reversalOfId, $batchId);
        }
    }

    private function attempt(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, bool $allowNegative, ?int $reversalOfId, bool $requireFirstMovement, ?int $batchId): StockMovement
    {
        return DB::transaction(function () use ($productId, $storeId, $quantity, $movementType, $unitCost, $idempotencyKey, $sourceType, $sourceId, $sourceLineId, $allowNegative, $reversalOfId, $requireFirstMovement, $batchId): StockMovement {
            $product = Product::query()->select(['id', 'has_variations', 'parent_product_id', 'fractional_quantity', 'track_batches', 'track_expiry'])->with('baseProductUnit.unit')->findOrFail($productId);
            $quantity = (string) ProductQuantity::normalizeSigned($quantity, decimalPlaces: $this->precision($product));

            $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                return $this->assertReplaySafe($existing, $productId, $storeId, $quantity, $movementType, $unitCost, $sourceType, $sourceId, $sourceLineId, $reversalOfId, $batchId);
            }

            if (bccomp($quantity, '0', 6) === 0) {
                throw new InvalidArgumentException(__('Inventory movement quantity cannot be zero.'));
            }

            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('A variation family cannot receive an inventory movement. Select a child SKU.'));
            }
            $batch = $batchId === null ? null : InventoryBatch::query()->lockForUpdate()->findOrFail($batchId);
            if (($product->track_batches || $product->track_expiry) && $batch === null) {
                throw new InvalidArgumentException(__('A batch is required for this tracked product.'));
            }
            if ($batch !== null && ((int) $batch->product_id !== $productId || $batch->status !== 'active')) {
                throw new InvalidArgumentException(__('The selected batch is not active for this product.'));
            }
            if ($product->track_expiry && ($batch?->expiry_date === null || $batch->expiry_date->isBefore(today()))) {
                throw new InvalidArgumentException(__('An active, unexpired batch with an expiry date is required for this product.'));
            }
            $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->first();
            if ($balance === null) {
                $balance = StockBalance::query()->create(['product_id' => $productId, 'store_id' => $storeId, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
                $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
            }
            if ($requireFirstMovement && StockMovement::query()->where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->exists()) {
                throw new InvalidArgumentException(__('Opening inventory is blocked because this product and location already have inventory movements.'));
            }

            $newOnHand = bcadd($this->decimal($balance->on_hand), $quantity, 6);
            if (bccomp($newOnHand, '0', 6) < 0 && ! $allowNegative) {
                throw new InvalidArgumentException(__('Negative stock is blocked by default. An authorized override with a reason is required.'));
            }

            $cost = $unitCost !== null ? $this->decimal($unitCost) : $this->decimal($balance->average_cost);
            $oldValue = $this->decimal($balance->total_value);
            $consumedCost = '0.0000';
            if (bccomp($quantity, '0', 6) > 0) {
                $totalCost = bcmul($quantity, $cost, 4);
                $newValue = bcadd($oldValue, $totalCost, 4);
            } else {
                $consumedCost = bcmul(bcsub('0', $quantity, 6), $this->decimal($balance->average_cost), 4);
                $totalCost = bcsub('0', $consumedCost, 4);
                $newValue = bcsub($oldValue, $consumedCost, 4);
            }
            $newAverage = bccomp($newOnHand, '0', 6) === 0 ? '0.0000' : bcdiv($newValue, $newOnHand, 4);

            $movement = StockMovement::query()->create([
                'product_id' => $productId,
                'store_id' => $storeId,
                'batch_id' => $batch?->id,
                'movement_type' => $movementType,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'total_cost' => $totalCost,
                'consumed_cost' => $consumedCost,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_line_id' => $sourceLineId,
                'idempotency_key' => $idempotencyKey,
                'posted_at' => now(),
                'reversal_of_id' => $reversalOfId,
                'created_by' => Auth::id(),
            ]);

            $balance->update(['on_hand' => $newOnHand, 'average_cost' => $newAverage, 'total_value' => $newValue, 'version' => $balance->version + 1]);

            return $movement;
        });
    }

    private function assertReplaySafe(StockMovement $existing, int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, ?int $reversalOfId, ?int $batchId): StockMovement
    {
        $normalizedCost = $unitCost !== null ? $this->decimal($unitCost) : null;
        $replaySafe = $existing->product_id === $productId
            && $existing->store_id === $storeId
            && ($existing->batch_id === null ? null : (int) $existing->batch_id) === $batchId
            && $existing->movement_type === $movementType
            && bccomp((string) $existing->quantity, $quantity, 6) === 0
            && ($normalizedCost === null || bccomp((string) $existing->unit_cost, $normalizedCost, 4) === 0)
            && $existing->source_type === $sourceType
            && $existing->source_id === $sourceId
            && $existing->source_line_id === $sourceLineId
            && ($existing->reversal_of_id === null ? null : (int) $existing->reversal_of_id) === $reversalOfId;

        if (! $replaySafe) {
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $existing;
    }

    private function replayExisting(int $productId, int $storeId, string $quantity, string $movementType, ?string $unitCost, string $idempotencyKey, ?string $sourceType, ?int $sourceId, ?int $sourceLineId, ?int $reversalOfId, ?int $batchId): StockMovement
    {
        $existing = StockMovement::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing === null) {
            // The row that caused the unique-key violation is not (yet) visible
            // to this connection's read. This should not happen outside of
            // extreme replication lag; surface it rather than mask it.
            throw new InvalidArgumentException(__('This idempotency key was already used with a different request payload.'));
        }

        return $this->assertReplaySafe($existing, $productId, $storeId, (string) ProductQuantity::normalizeSigned($quantity, decimalPlaces: 6), $movementType, $unitCost, $sourceType, $sourceId, $sourceLineId, $reversalOfId, $batchId);
    }

    public function adjustInTransit(int $productId, int $storeId, string $quantity): void
    {
        $product = Product::query()->select(['id', 'fractional_quantity'])->with('baseProductUnit.unit')->findOrFail($productId);
        $precision = $this->precision($product);
        $balance = StockBalance::query()->where('product_id', $productId)->where('store_id', $storeId)->lockForUpdate()->first();
        if ($balance === null) {
            $balance = StockBalance::query()->create(['product_id' => $productId, 'store_id' => $storeId, 'on_hand' => 0, 'reserved' => 0, 'in_transit' => 0, 'average_cost' => 0, 'total_value' => 0, 'version' => 0]);
            $balance = StockBalance::query()->whereKey($balance->id)->lockForUpdate()->firstOrFail();
        }
        $newTransit = bcadd((string) ProductQuantity::normalize($balance->in_transit, allowZero: true, decimalPlaces: $precision), (string) ProductQuantity::normalizeSigned($quantity, decimalPlaces: $precision), 6);
        if (bccomp($newTransit, '0', 6) < 0) {
            throw new InvalidArgumentException(__('In-transit stock cannot become negative.'));
        }
        $balance->update(['in_transit' => $newTransit, 'version' => $balance->version + 1]);
    }

    private function precision(Product $product): int
    {
        return $product->fractional_quantity ? min(6, (int) ($product->baseProductUnit?->unit?->decimal_places ?? 6)) : 0;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException(__('Invalid decimal inventory quantity or cost.'));
        }

        // @phpstan-ignore argument.type
        return bcadd($value, '0', 6);
    }
}
