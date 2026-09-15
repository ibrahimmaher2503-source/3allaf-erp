<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Models\User;
use App\Modules\Inventory\Models\ProductStoreAssignment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportWorkbook;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

final class ImportOpeningInventoryWorkbookAction
{
    public const HEADERS = ['product_barcode_or_code', 'product_code_reference', 'supplier_code_reference', 'model_reference', 'barcode_reference', 'product_name_ar_reference', 'product_name_en_reference', 'inventory_location_code', 'opening_quantity', 'unit_cost'];
    public const MAX_ROWS = 100000;

    /** @return array{document:\App\Modules\Inventory\Models\OpeningInventoryDocument|null,total:int,accepted:int,rejected:int,rejections:list<array<int|string,mixed>>} */
    public function execute(string $path, ?string $notes = null, mixed $documentDate = null): array
    {
        Gate::authorize('inventory_stock_card.create');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') throw new InvalidArgumentException(__('Upload the approved XLSX Opening Inventory template.'));
        $actor = auth()->user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $reader = ReaderFactory::createFromFile($path); $reader->open($path);
        $rawRows = []; $number = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.opening-inventory.v3', $company->code);
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                $number++; $cells = $row->getCells(); $values = array_map(fn ($cell) => trim((string) $cell->getValue()), $cells);
                if ($number === 1) { ImportWorkbook::assertHeaders($values, self::HEADERS, __('The spreadsheet headers do not match the Opening Inventory template.')); continue; }
                if ($number > self::MAX_ROWS + 1) throw new InvalidArgumentException(__('The import is limited to :count data rows.', ['count' => self::MAX_ROWS]));
                $source = array_combine(self::HEADERS, array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), ''));
                if ($source['inventory_location_code'] === '' && $source['opening_quantity'] === '' && $source['unit_cost'] === '') continue;
                $rawRows[] = ['number' => $number, 'source' => $source, 'formula' => collect($cells)->contains(fn ($cell) => $cell instanceof FormulaCell || str_starts_with(trim((string) $cell->getValue()), '='))];
            }
        } finally { $reader->close(); }

        $identifiers = collect($rawRows)->pluck('source.product_barcode_or_code')->filter()->unique()->values();
        $productsByIdentifier = collect();
        $ambiguousIdentifiers = collect();
        $register = static function (string $identifier, Product $product) use ($productsByIdentifier, $ambiguousIdentifiers): void {
            if ($identifier === '') return;
            $existing = $productsByIdentifier->get($identifier);
            if ($existing instanceof Product && (int) $existing->id !== (int) $product->id) {
                $ambiguousIdentifiers->put($identifier, true);
                return;
            }
            $productsByIdentifier->put($identifier, $product);
        };
        foreach ($identifiers->chunk(500) as $chunk) {
            $found = Product::query()->with([
                'barcodes' => fn ($query) => $query->where('status', 'active')->orderByDesc('is_primary'),
                'preferredProductSupplier' => fn ($query) => $query->whereHas('supplier', fn ($supplier) => $supplier
                    ->whereNull('supplier_group_id')
                    ->orWhereHas('supplierGroup', fn ($group) => $group->where('company_id', $company->id))),
                'productSuppliers:id,product_id,supplier_item_code',
            ])
                ->where(fn ($query) => $query->whereIn('item_code', $chunk)
                    ->orWhereIn('model_number', $chunk)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->whereIn('barcode', $chunk)->where('status', 'active'))
                    ->orWhereHas('productSuppliers', fn ($suppliers) => $suppliers->whereIn('supplier_item_code', $chunk)))->get();
            foreach ($found as $product) {
                $register((string) $product->item_code, $product);
                foreach ($product->barcodes as $barcode) $register((string) $barcode->barcode, $product);
                if ($product->model_number) $register((string) $product->model_number, $product);
                foreach ($product->productSuppliers as $supplierLink) if ($supplierLink->supplier_item_code) $register((string) $supplierLink->supplier_item_code, $product);
            }
        }
        $storeCodes = collect($rawRows)->pluck('source.inventory_location_code')->filter()->unique();
        $stores = Store::query()->visibleTo($actor)->where('company_id', $company->id)->whereIn('code', $storeCodes)->where('status', 'active')->whereIn('type', ['warehouse', 'selling'])->get()->keyBy('code');
        $productIds = $productsByIdentifier->pluck('id')->unique(); $storeIds = $stores->pluck('id');
        $movementPairs = $productIds->isEmpty() || $storeIds->isEmpty() ? collect() : StockMovement::query()->whereIn('product_id', $productIds)->whereIn('store_id', $storeIds)->select(['product_id', 'store_id'])->distinct()->get()->keyBy(fn ($movement): string => $movement->product_id.':'.$movement->store_id);
        $assignedProductIds = ProductStoreAssignment::query()->where('company_id', $company->id)->whereIn('product_id', $productIds)->distinct()->pluck('product_id')->mapWithKeys(fn ($productId): array => [(int) $productId => true]);
        $activeAssignmentPairs = ProductStoreAssignment::query()->where('company_id', $company->id)->where('status', 'active')->whereIn('product_id', $productIds)->whereIn('store_id', $storeIds)->get(['product_id', 'store_id'])->keyBy(fn (ProductStoreAssignment $assignment): string => $assignment->product_id.':'.$assignment->store_id);

        $rows = []; $rejections = []; $seen = [];
        foreach ($rawRows as $raw) {
            $source = $raw['source']; $errors = [];
            if ($raw['formula']) $errors[] = __('Spreadsheet formulas are not allowed.');
            $identifier = $source['product_barcode_or_code'];
            $product = $ambiguousIdentifiers->has($identifier) ? null : $productsByIdentifier->get($identifier); $store = $stores->get($source['inventory_location_code']);
            if ($ambiguousIdentifiers->has($identifier)) $errors[] = __('The product code matches more than one product; use a unique internal code or barcode.');
            elseif (! $product) $errors[] = __('Unknown product barcode or code.');
            if (! $store) $errors[] = __('Unknown, inactive, or non-stock inventory location code.');
            if (! preg_match('/^\d+$/', $source['opening_quantity']) || bccomp($source['opening_quantity'] ?: '0', '0', 0) <= 0) $errors[] = __('Product quantities must be whole numbers.');
            if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $source['unit_cost'])) $errors[] = __('Unit cost must be zero or greater with up to four decimal places.');
            if ($product && ($product->product_type === 'service' || $product->isFamily() || ! $product->isSellable())) $errors[] = __('Service products and variation families cannot receive opening inventory.');
            if ($product) {
                $expected = [$product->item_code, $product->preferredProductSupplier?->supplier_item_code ?? '', $product->model_number ?? '', $product->barcodes->first()?->barcode ?? '', $product->name_ar, $product->name_en ?? ''];
                $actual = [$source['product_code_reference'], $source['supplier_code_reference'], $source['model_reference'], $source['barcode_reference'], $source['product_name_ar_reference'], $source['product_name_en_reference']];
                if ($expected !== $actual) $errors[] = __('Locked product reference columns were changed. Download a fresh template and edit only location and quantity.');
            }
            if ($product && $store && $assignedProductIds->has($product->id) && ! $activeAssignmentPairs->has($product->id.':'.$store->id)) $errors[] = __('Assign this product to the selected inventory location before entering an opening quantity.');
            if ($product && $store && $movementPairs->has($product->id.':'.$store->id)) $errors[] = __('This product and location already have inventory movements.');
            $key = ($product?->id ?? 'x').':'.($store?->id ?? 'x'); if (isset($seen[$key])) $errors[] = __('Duplicate product and inventory location in this workbook.'); $seen[$key] = true;
            if ($errors) { $rejections[] = [$raw['number'], ...array_values($source), implode(' | ', array_unique($errors))]; continue; }
            $rows[] = ['product_id' => $product->id, 'store_id' => $store->id, 'quantity' => $source['opening_quantity'], 'unit_cost' => $source['unit_cost']];
        }
        if (collect($rows)->pluck('store_id')->unique()->count() > 1) throw new InvalidArgumentException(__('Each opening inventory workbook must use one warehouse or store.'));
        $document = $rows ? app(SaveOpeningInventoryDraftAction::class)->execute($rows, notes: $notes, companyId: $company->id, documentDate: $documentDate) : null;
        return ['document' => $document, 'total' => count($rows) + count($rejections), 'accepted' => count($rows), 'rejected' => count($rejections), 'rejections' => $rejections];
    }
}
