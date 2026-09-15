<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Support\AuthorizedCompanyContext;
use App\Support\DataExchange\ImportWorkbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use InvalidArgumentException;

final class ImportProductCardsWorkbookAction
{
    public const HEADERS = ['barcode_registration_type', 'barcode', 'supplier_code', 'model_number', 'name_ar', 'name_en', 'unit_cost', 'base_consumer_price', 'category_code', 'brand_code', 'open_price', 'product_type', 'unit_of_measure', 'filter_codes', 'length', 'width', 'height', 'dimension_unit', 'weight', 'weight_unit', 'sell_online', 'short_description_ar', 'short_description_en', 'detailed_description_ar', 'detailed_description_en', 'uses_battery', 'status'];

    /** @return array{total:int,added:int,rejected:int,duplicates:int,rejections:list<array<int|string,mixed>>} */
    public function execute(string $path): array
    {
        Gate::authorize('products_categories_brands.create');
        $actor = Auth::user(); abort_unless($actor instanceof User, 403);
        $company = app(AuthorizedCompanyContext::class)->resolve($actor, request()->input('company_id'));
        $reader = ReaderFactory::createFromFile($path); $reader->open($path);
        $staged = []; $rejections = []; $seenModels = []; $seenBarcodes = []; $number = 0;
        try {
            ImportWorkbook::assertMetadata($reader, 'toyjoy.product-card.v2', $company->code);
            $sheet = ImportWorkbook::dataSheet($reader);
            foreach ($sheet->getRowIterator() as $row) {
                    $number++; $cells = $row->getCells(); $values = array_map(fn ($cell) => trim((string) $cell->getValue()), $cells);
                    if ($number === 1) { ImportWorkbook::assertHeaders($values, self::HEADERS, __('The spreadsheet headers do not match the Product Card template.')); continue; }
                    if ($number > 5001) throw new InvalidArgumentException(__('The import is limited to 5,000 data rows.'));
                    if (collect($values)->every(fn ($value) => $value === '')) continue;
                    $source = array_combine(self::HEADERS, array_pad(array_slice($values, 0, count(self::HEADERS)), count(self::HEADERS), ''));
                    $errors = [];
                    if (collect($cells)->contains(fn ($cell) => $cell instanceof FormulaCell || str_starts_with(trim((string) $cell->getValue()), '='))) $errors[] = __('Spreadsheet formulas are not allowed.');
                    $mode = strtolower($source['barcode_registration_type']); $model = strtoupper($source['model_number']); $barcode = $source['barcode'];
                    if (! in_array($mode, ['international', 'local'], true)) $errors[] = __('Barcode registration type must be international or local.');
                    if ($mode === 'international' && $barcode === '') $errors[] = __('International rows require a barcode.');
                    if ($mode === 'local' && $source['supplier_code'] === '') $errors[] = __('Local rows require a supplier code.');
                    if ($model === '' || $source['name_ar'] === '') $errors[] = __('Model number and Arabic product name are required.');
                    if (isset($seenModels[$model]) || ($barcode !== '' && isset($seenBarcodes[$barcode]))) $errors[] = __('The model or barcode is duplicated inside this workbook.');
                    $seenModels[$model] = true; if ($barcode !== '') $seenBarcodes[$barcode] = true;
                    if (Product::query()->where('item_code', $model)->orWhereHas('barcodes', fn ($q) => $q->where('barcode', $barcode))->exists()) $errors[] = __('The product model or barcode already exists and will not be overwritten.');
                    $category = Category::query()->where('code', strtoupper($source['category_code']))->where('status', 'active')->first(); if (! $category) $errors[] = __('Unknown or inactive category code.');
                    $brand = $source['brand_code'] === '' ? null : Brand::query()->where('code', strtoupper($source['brand_code']))->where('status', 'active')->first(); if ($source['brand_code'] !== '' && ! $brand) $errors[] = __('Unknown or inactive brand code.');
                    $supplier = $source['supplier_code'] === '' ? null : Supplier::query()->where('code', strtoupper($source['supplier_code']))->where('status', 'active')->where(fn ($query) => $query->whereNull('supplier_group_id')->orWhereHas('supplierGroup', fn ($groups) => $groups->where('company_id', $company->id)))->first(); if ($mode === 'local' && ! $supplier) $errors[] = __('Unknown, inactive, or out-of-scope supplier code.');
                    $filterValues = $this->filterValues($source['filter_codes'], $errors);
                    if (! preg_match('/^\d+(?:\.\d{1,4})?$/', $source['unit_cost']) || ! preg_match('/^\d+(?:\.\d{1,4})?$/', $source['base_consumer_price'])) $errors[] = __('Unit cost and base consumer price must be valid non-negative amounts.');
                    if (! in_array($source['product_type'], ['standard', 'composite', 'service'], true)) $errors[] = __('Product type must be standard, composite, or service.');
                    if ($errors) { $rejections[] = [$number, ...array_values($source), implode(' | ', array_unique($errors))]; continue; }
                    $staged[] = compact('source', 'category', 'brand', 'supplier', 'filterValues', 'mode', 'barcode', 'model');
            }
        } finally { $reader->close(); }
        $added = 0;
        foreach ($staged as $item) {
            try {
                DB::transaction(function () use ($item, &$added): void {
                    $s = $item['source'];
                    $product = app(SaveProductAction::class)->execute(['item_code' => $item['model'], 'model_number' => $item['model'], 'barcode_registration_type' => $item['mode'], 'name_ar' => $s['name_ar'], 'name_en' => $s['name_en'], 'average_cost' => $s['unit_cost'], 'sale_price' => $s['base_consumer_price'], 'category_id' => $item['category']->id, 'brand_id' => $item['brand']?->id, 'preferred_supplier_id' => $item['supplier']?->id, 'open_price' => $this->yes($s['open_price']), 'sell_online' => $this->yes($s['sell_online']), 'product_type' => $s['product_type'], 'unit_of_measure' => $s['unit_of_measure'] ?: null, 'dimension_length' => $s['length'] ?: null, 'dimension_width' => $s['width'] ?: null, 'dimension_height' => $s['height'] ?: null, 'dimension_unit' => $s['dimension_unit'] ?: null, 'weight' => $s['weight'] ?: null, 'weight_unit' => $s['weight_unit'] ?: null, 'short_description_ar' => $s['short_description_ar'], 'short_description_en' => $s['short_description_en'], 'full_description_ar' => $s['detailed_description_ar'], 'full_description_en' => $s['detailed_description_en'], 'battery_required' => $this->yes($s['uses_battery']), 'status' => in_array($s['status'], ['active', 'inactive'], true) ? $s['status'] : 'active']);
                    if ($item['mode'] === 'local') app(AddBarcodeAction::class)->allocateLocalBarcode($product->id, $item['supplier']->code, 'product-import:'.$product->id); else app(AddBarcodeAction::class)->addSupplierBarcode($product->id, $item['barcode']);
                    if ($item['filterValues'] !== []) {
                        $groupIds = collect($item['filterValues'])->pluck('product_option_group_id')->unique()->values();
                        $product->familyOptionGroups()->sync($groupIds->mapWithKeys(fn ($id, $order) => [$id => ['sort_order' => $order]])->all());
                        $product->familyOptionValues()->sync(collect($item['filterValues'])->pluck('id')->all());
                    }
                    $added++;
                });
            } catch (\Throwable $e) { $rejections[] = ['', ...array_values($item['source']), \App\Support\UserSafeError::message($e)]; }
        }
        return ['total' => count($staged) + count($rejections), 'added' => $added, 'rejected' => count($rejections), 'duplicates' => collect($rejections)->filter(fn ($r) => str_contains((string) $r[count($r) - 1], 'duplicat'))->count(), 'rejections' => $rejections];
    }

    private function filterValues(string $codes, array &$errors): array
    {
        if (trim($codes) === "") return [];
        $resolved = [];
        foreach (preg_split("/[|,]+/", $codes) ?: [] as $pair) {
            [$groupCode, $valueCode] = array_pad(explode(":", strtoupper(trim($pair)), 2), 2, "");
            $group = ProductOptionGroup::query()->active()->where("code", $groupCode)->first();
            $value = $group ? ProductOptionValue::query()->active()->where("product_option_group_id", $group->id)->where("code", $valueCode)->first() : null;
            if (! $group || ! $value) { $errors[] = __("Unknown or inactive Product Filter code :code. Use GROUP:VALUE stable codes.", ["code" => trim($pair)]); continue; }
            $resolved[] = $value;
        }
        return $resolved;
    }

    private function yes(string $value): bool { return in_array(strtolower($value), ['1', 'yes', 'true', 'نعم'], true); }
}
