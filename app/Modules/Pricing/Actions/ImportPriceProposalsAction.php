<?php

namespace App\Modules\Pricing\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceVersion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ImportPriceProposalsAction
{
    /** @return array<int, PriceVersion> */
    public function execute(
        string $csv,
        string $priceListCode,
        string $priceListNameAr,
        string $priceListNameEn,
    ): array {
        /** @var User $user */
        $user = Auth::user() ?? throw new \LogicException('An authenticated pricing user is required.');
        Gate::forUser($user)->authorize('pricing_labels.create');

        $lines = preg_split('/\R/', trim($csv)) ?: [];
        if (count($lines) === 0 || count($lines) > 201) {
            throw ValidationException::withMessages(['importCsv' => __('Import must contain between 1 and 200 data rows.')]);
        }

        $versions = [];

        return DB::transaction(function () use ($lines, $priceListCode, $priceListNameAr, $priceListNameEn, &$versions): array {
            $start = str_contains(strtolower((string) $lines[0]), 'item_code') ? 1 : 0;
            for ($index = $start; $index < count($lines); $index++) {
                $row = str_getcsv($lines[$index]);
                if (count($row) < 3 || trim((string) $row[0]) === '') {
                    throw ValidationException::withMessages(['importCsv' => __('Invalid import row :row. Expected item_code, store_code, amount, optional effective_from, source_reference.', ['row' => $index + 1])]);
                }

                $product = Product::query()->sellable()->where('item_code', trim((string) $row[0]))->first();
                $store = Store::query()->where('code', trim((string) $row[1]))->first();
                if ($product === null || $store === null) {
                    throw ValidationException::withMessages(['importCsv' => __('Import row :row references an unknown product or store.', ['row' => $index + 1])]);
                }

                $versions[] = app(CreatePriceProposalAction::class)->execute(
                    product: $product,
                    store: $store,
                    priceListCode: $priceListCode,
                    priceListNameAr: $priceListNameAr,
                    priceListNameEn: $priceListNameEn,
                    amount: trim((string) $row[2]),
                    sourceType: 'import',
                    sourceReference: trim((string) ($row[4] ?? '')) ?: 'CSV row '.($index + 1),
                    effectiveFrom: trim((string) ($row[3] ?? '')) ?: null,
                    effectiveTo: null,
                    reasonText: 'Local/Dev CSV import; pending approval and not Production master data.',
                );
            }

            return $versions;
        });
    }
}
