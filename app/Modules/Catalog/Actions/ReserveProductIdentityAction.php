<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Barcode;
use App\Modules\Catalog\Models\GlobalProductCodeSequence;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReserveProductIdentityAction
{
    private const SEQUENCE_NAME = 'product_identity';

    private const MAX_SEQUENCE = 9_999_999;

    /** @return array{item_code: string, barcode_source: string, supplier: Supplier|null} */
    public function reserve(?string $suppliedCode, ?int $preferredSupplierId): array
    {
        $code = strtoupper(trim((string) $suppliedCode));
        $supplier = $preferredSupplierId
            ? Supplier::query()->whereKey($preferredSupplierId)->where('status', 'active')->first()
            : null;

        if ($preferredSupplierId && $supplier === null) {
            throw new InvalidArgumentException(__('The selected supplier must exist and be active.'));
        }

        if ($supplier?->barcode_prefix !== null && ! preg_match('/^[A-Z0-9]{1,12}$/', $supplier->barcode_prefix)) {
            throw new InvalidArgumentException(__('The preferred supplier barcode prefix is invalid.'));
        }

        if ($code !== '') {
            $this->lockSequence();
            $this->assertCodeFormatAndAvailability($code);

            return ['item_code' => $code, 'barcode_source' => 'supplier', 'supplier' => $supplier];
        }

        if ($supplier === null) {
            throw new InvalidArgumentException(__('An active preferred supplier is required when no global product code is supplied.'));
        }

        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $supplier->name_en) ?? '');
        if (strlen($letters) < 3) {
            throw new InvalidArgumentException(__('The preferred supplier English name must contain at least three English letters.'));
        }

        $sequence = $this->lockSequence();
        while ($sequence->next_number <= self::MAX_SEQUENCE) {
            $number = $sequence->next_number;
            $candidate = substr($letters, 0, 3).str_pad((string) $number, 7, '0', STR_PAD_LEFT);
            $sequence->update(['next_number' => $number + 1]);

            if (! Product::query()->where('item_code', $candidate)->exists()
                && ! Barcode::query()->where('barcode', $candidate)->exists()) {
                return ['item_code' => $candidate, 'barcode_source' => 'local', 'supplier' => $supplier];
            }
        }

        throw new InvalidArgumentException(__('The seven-digit global product-code sequence is exhausted.'));
    }

    public function createPrimaryBarcode(Product $product, string $code, string $source): Barcode
    {
        return Barcode::query()->create([
            'product_id' => $product->id,
            'barcode' => $code,
            'source' => $source,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    private function assertCodeFormatAndAvailability(string $code): void
    {
        if (! preg_match('/^[A-Z0-9][A-Z0-9._\/-]{0,49}$/', $code)) {
            throw new InvalidArgumentException(__('The supplied global product code is invalid.'));
        }
        if (Product::query()->where('item_code', $code)->exists()
            || Barcode::query()->where('barcode', $code)->exists()) {
            throw new InvalidArgumentException(__('The supplied global product code is already assigned.'));
        }
    }

    private function lockSequence(): GlobalProductCodeSequence
    {
        DB::table('global_product_code_sequences')->insertOrIgnore([
            'sequence_name' => self::SEQUENCE_NAME,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return GlobalProductCodeSequence::query()
            ->where('sequence_name', self::SEQUENCE_NAME)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
