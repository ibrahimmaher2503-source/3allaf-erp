<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryAdjustment;
use App\Modules\Platform\Actions\AllocateDocumentNumber;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SaveInventoryAdjustmentAction
{
    /** @param array<string, mixed> $data @param array<int, array<string, mixed>> $lines */
    public function execute(array $data, array $lines, ?int $id = null, ?int $expectedVersion = null): InventoryAdjustment
    {
        Gate::authorize($id === null ? 'inventory_stock_card.create' : 'inventory_stock_card.edit');
        if ($lines === []) {
            throw new InvalidArgumentException(__('An inventory document must contain at least one line.'));
        }

        return DB::transaction(function () use ($data, $lines, $id, $expectedVersion): InventoryAdjustment {
            $adjustment = $id === null ? null : InventoryAdjustment::query()->with('lines')->lockForUpdate()->findOrFail($id);
            if ($adjustment !== null) {
                if ($adjustment->status !== 'draft') {
                    throw new InvalidArgumentException(__('Only draft inventory documents can be edited.'));
                }
                if ($expectedVersion !== null && $adjustment->lock_version !== $expectedVersion) {
                    throw new InvalidArgumentException(__('This inventory document changed in another session. Please reload before saving.'));
                }
            }

            $storeId = (int) ($data['store_id'] ?? 0);
            $store = Store::query()->whereKey($storeId)->where('status', 'active')->firstOrFail();
            app(AssertInventoryStoreScope::class)->execute($store->id);
            $type = trim((string) ($data['adjustment_type'] ?? ''));
            if (! in_array($type, ['entry', 'exit', 'exchange', 'adjustment'], true)) {
                throw new InvalidArgumentException(__('Select a supported inventory document type.'));
            }
            $reason = trim((string) ($data['reason_code'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException(__('An inventory document reason is required.'));
            }
            $allowNegative = filter_var($data['allow_negative'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($allowNegative) {
                Gate::authorize('inventory_stock_card.override');
                if (trim((string) ($data['reason_notes'] ?? '')) === '') {
                    throw new InvalidArgumentException(__('A negative-stock override reason is required.'));
                }
            }

            $normalizedLines = [];
            foreach (array_values($lines) as $line) {
                $product = Product::query()->sellable()->whereKey((int) ($line['product_id'] ?? 0))->firstOrFail();
                if (! $product->isSellable()) {
                    throw new InvalidArgumentException(__('Inactive products cannot be used in inventory documents.'));
                }
                $quantity = $this->decimal($line['quantity_delta'] ?? null);
                if (bccomp($quantity, '0', 6) === 0) {
                    throw new InvalidArgumentException(__('Inventory quantity cannot be zero.'));
                }
                if (bccomp(bcmod($quantity, '1', 6), '0', 6) !== 0) {
                    throw new InvalidArgumentException(__('This product does not allow fractional quantities.'));
                }
                if ($type === 'entry') {
                    $quantity = ltrim($quantity, '-');
                } elseif ($type === 'exit' && bccomp($quantity, '0', 6) > 0) {
                    $quantity = '-'.$quantity;
                }
                $normalizedLines[] = [
                    'product_id' => $product->id,
                    'quantity_delta' => $quantity,
                    'unit_cost' => $line['unit_cost'] === null || trim((string) $line['unit_cost']) === '' ? null : $this->cost($line['unit_cost']),
                ];
            }

            $attributes = [
                'store_id' => $store->id,
                'adjustment_type' => $type,
                'status' => 'draft',
                'reason_code' => $reason,
                'reason_notes' => trim((string) ($data['reason_notes'] ?? '')) ?: null,
                'allow_negative' => $allowNegative,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            ];
            if ($adjustment === null) {
                $adjustment = InventoryAdjustment::query()->create([
                    ...$attributes,
                    'adjustment_number' => app(AllocateDocumentNumber::class)->executeForBranch('inventory_adjustment', (int) $store->branch_id),
                    'created_by' => Auth::id(),
                    'idempotency_key' => (string) ($data['idempotency_key'] ?? 'inventory-adjustment:'.Str::uuid()),
                    'lock_version' => 1,
                ]);
                $before = null;
                $event = 'create_inventory_adjustment';
            } else {
                $before = $adjustment->only(['store_id', 'adjustment_type', 'reason_code', 'allow_negative', 'lock_version']);
                $adjustment->update([...$attributes, 'lock_version' => $adjustment->lock_version + 1]);
                $adjustment->lines()->delete();
                $event = 'update_inventory_adjustment';
            }
            foreach ($normalizedLines as $line) {
                $adjustment->lines()->create($line);
            }
            app(RecordAuditEvent::class)->execute('inventory', $event, $adjustment, $before, $adjustment->fresh(['lines'])->only(['adjustment_number', 'store_id', 'adjustment_type', 'reason_code', 'allow_negative', 'status', 'lock_version']), storeId: $store->id, reasonCode: $reason, reasonText: $adjustment->reason_notes, metadata: ['line_count' => count($normalizedLines)]);

            return $adjustment->fresh(['store', 'lines.product']);
        });
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d{1,6})?$/', $value)) {
            throw new InvalidArgumentException(__('Quantity must be a valid decimal with up to 6 places.'));
        }
        return bcadd($value, '0', 6);
    }

    private function cost(mixed $value): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException(__('Unit cost must be a valid decimal with up to 4 places.'));
        }
        return bcadd($value, '0', 4);
    }
}
