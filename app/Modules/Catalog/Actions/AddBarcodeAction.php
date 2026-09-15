<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\BarcodeSequence;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\ProductBarcodePolicy;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AddBarcodeAction
{
    public function addManualCode128(int $productId, string $value): Barcode
    {
        $this->authorizeWorkspaceBarcode();
        $value = trim($value);
        if (! preg_match('/^[\x20-\x7E]{1,48}$/', $value)) throw new InvalidArgumentException(__('Code 128 must contain 1 to 48 supported letters, numbers, spaces, or symbols.'));
        return $this->createWorkspaceBarcode($productId, $value, 'manual');
    }

    public function generateWorkspaceCode128(int $productId): Barcode
    {
        $this->authorizeWorkspaceBarcode();
        $product = Product::query()->findOrFail($productId);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $value = 'TJ-'.preg_replace('/[^A-Z0-9]/', '', strtoupper($product->item_code)).'-'.strtoupper(bin2hex(random_bytes(3)));
            if (! Barcode::query()->where('barcode', $value)->exists()) return $this->createWorkspaceBarcode($productId, $value, 'local');
        }
        throw new InvalidArgumentException(__('A unique local barcode could not be generated. Please try again.'));
    }

    private function createWorkspaceBarcode(int $productId, string $value, string $source): Barcode
    {
        return DB::transaction(function () use ($productId, $value, $source): Barcode {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);
            if ($product->isFamily()) throw new InvalidArgumentException(__('A variation family is not sellable and cannot own a barcode. Add the barcode to a child SKU.'));
            if (Barcode::query()->where('barcode', $value)->exists() || Product::query()->where('item_code', $value)->exists()) throw new InvalidArgumentException(__('This barcode is already assigned and cannot be silently reassigned.'));
            $barcode = Barcode::query()->create(['product_id' => $product->id, 'barcode' => $value, 'source' => $source, 'status' => 'active', 'is_primary' => ! $product->barcodes()->active()->exists()]);
            $this->syncProductMode($product);
            app(RecordAuditEvent::class)->execute(category: 'master_data', event: 'create_workspace_barcode', source: $barcode, after: $barcode->only(['product_id','barcode','source','status','is_primary']));
            return $barcode;
        });
    }

    private function authorizeWorkspaceBarcode(): void
    {
        abort_unless(Gate::allows('products_categories_brands.edit') || Gate::allows('pricing_labels.create'), 403);
    }

    public function addSupplierBarcode(int $productId, string $barcode): Barcode
    {
        Gate::authorize('products_categories_brands.edit');
        $value = trim($barcode);

        if ($value === '') {
            throw new InvalidArgumentException(__('An international barcode is required.'));
        }
        $value = app(ProductBarcodePolicy::class)->international($value);

        return DB::transaction(function () use ($productId, $value): Barcode {
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('A variation family is not sellable and cannot own a barcode. Add the barcode to a child SKU.'));
            }

            if (Barcode::query()->where('barcode', $value)->exists()
                || Product::query()->where('item_code', $value)->exists()) {
                throw new InvalidArgumentException(__('This barcode is already assigned and cannot be silently reassigned.'));
            }

            $barcode = Barcode::query()->create([
                'product_id' => $product->id,
                'barcode' => $value,
                'source' => 'international',
                'status' => 'active',
                'is_primary' => ! $product->barcodes()->where('status', 'active')->exists(),
            ]);

            $this->syncProductMode($product);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'create_international_barcode',
                source: $barcode,
                after: $barcode->only(['product_id', 'barcode', 'source', 'status', 'is_primary']),
            );

            return $barcode;
        });
    }

    public function allocateLocalBarcode(int $productId, string $supplierCode, string $allocationKey): Barcode
    {
        Gate::authorize('products_categories_brands.edit');
        $supplierCode = strtoupper(trim($supplierCode));
        $allocationKey = trim($allocationKey);

        $supplierCode = app(ProductBarcodePolicy::class)->supplierDigits($supplierCode);
        if ($allocationKey === '') {
            throw new InvalidArgumentException(__('A barcode allocation request key is required.'));
        }

        try {
            return DB::transaction(function () use ($productId, $supplierCode, $allocationKey): Barcode {
            $existing = Barcode::query()->where('allocation_key', $allocationKey)->lockForUpdate()->first();

            if ($existing !== null) {
                return $this->assertMatchingLocalAllocation($existing, $productId, $supplierCode, $allocationKey);
            }

            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            if ($product->isFamily()) {
                throw new InvalidArgumentException(__('A variation family is not sellable and cannot own a barcode. Add the barcode to a child SKU.'));
            }
            BarcodeSequence::query()->insertOrIgnore([
                'supplier_code' => $supplierCode,
                'next_serial' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $sequence = BarcodeSequence::query()->where('supplier_code', $supplierCode)->lockForUpdate()->firstOrFail();
            $serial = $sequence->next_serial;
            $highestCommitted = (int) Barcode::query()->where('source', 'local')->where('supplier_code', $supplierCode)->max('serial_value');
            $serial = max((int) $serial, $highestCommitted + 1);

            while ($serial <= 999999) {
                $value = app(ProductBarcodePolicy::class)->local($supplierCode, $serial);

                if (! Barcode::query()->where('barcode', $value)->exists()) {
                    break;
                }

                $serial++;
            }

            if ($serial > 999999) {
                throw new InvalidArgumentException(__('The local barcode serial range is exhausted for this supplier code.'));
            }

            $sequence->update(['next_serial' => $serial + 1]);

            $barcode = Barcode::query()->create([
                'product_id' => $product->id,
                'barcode' => $value,
                'source' => 'local',
                'supplier_code' => $supplierCode,
                'serial_value' => $serial,
                'status' => 'active',
                'is_primary' => ! $product->barcodes()->where('status', 'active')->exists(),
                'allocation_key' => $allocationKey,
            ]);

            $this->syncProductMode($product);
            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'allocate_local_barcode',
                source: $barcode,
                after: $barcode->only(['product_id', 'barcode', 'source', 'supplier_code', 'serial_value', 'status', 'is_primary']),
                metadata: ['allocation_key_hash' => hash('sha256', $allocationKey)],
            );

                return $barcode;
            });
        } catch (QueryException $exception) {
            $existing = Barcode::query()->where('allocation_key', $allocationKey)->first();
            if ($existing !== null) {
                return $this->assertMatchingLocalAllocation($existing, $productId, $supplierCode, $allocationKey);
            }

            throw $exception;
        }
    }

    public function deactivate(int $barcodeId): Barcode
    {
        Gate::authorize('products_categories_brands.edit');

        return DB::transaction(function () use ($barcodeId): Barcode {
            $barcode = Barcode::query()->lockForUpdate()->findOrFail($barcodeId);
            $before = $barcode->only(['status', 'is_primary']);
            $barcode->update(['status' => 'inactive', 'is_primary' => false]);
            $product = $barcode->product()->lockForUpdate()->firstOrFail();
            $this->syncProductMode($product);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'deactivate_barcode',
                source: $barcode,
                before: $before,
                after: $barcode->fresh()->only(['status', 'is_primary']),
            );

            return $barcode->fresh();
        });
    }


    private function syncProductMode(Product $product): void
    {
        $sources = $product->barcodes()->where('status', 'active')->pluck('source')->unique()->values()->all();
        $mode = match (true) {
            count($sources) === 0 => 'none',
            count($sources) === 1 && in_array($sources[0], ['supplier', 'international'], true) => 'international',
            count($sources) === 1 && $sources[0] === 'local' => 'local',
            default => 'mixed',
        };

        $product->update(['barcode_mode' => $mode]);
    }

    private function assertMatchingLocalAllocation(
        Barcode $barcode,
        int $productId,
        string $supplierCode,
        string $allocationKey,
    ): Barcode {
        $serial = $barcode->serial_value;
        $expectedValue = $serial === null
            ? null
            : app(ProductBarcodePolicy::class)->local($supplierCode, $serial);

        if ((int) $barcode->product_id !== $productId
            || $barcode->source !== 'local'
            || $barcode->supplier_code !== $supplierCode
            || $barcode->allocation_key !== $allocationKey
            || $serial === null
            || $serial < 1
            || $serial > 999999
            || $barcode->barcode !== $expectedValue) {
            throw new InvalidArgumentException(__('This barcode allocation request key conflicts with another allocation.'));
        }

        return $barcode;
    }
}
