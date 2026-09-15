<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Exceptions\ImmutableItemCodeChangeException;
use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\AgeLabel;
use App\Modules\Catalog\Models\Character;
use App\Modules\Catalog\Models\Colour;
use App\Modules\Catalog\Models\Gender;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Support\ProductPricingContract;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\TaxSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class SaveProductAction
{
    /** @var array<int, string> */
    private const PRODUCT_TYPES = ['standard', 'composite', 'service', 'digital'];

    /** @var array<int, string> */
    private const FEED_KINDS = ['feed', 'raw_material', 'additive'];
    private const ANIMAL_TYPES = ['cattle', 'poultry', 'rabbit', 'other'];

    /** @param array<string, mixed> $data */
    public function execute(array $data, ?int $id = null, ?int $expectedVersion = null): Product
    {
        Gate::authorize($id ? 'products_categories_brands.edit' : 'products_categories_brands.create');

        try {
            return DB::transaction(function () use ($data, $id, $expectedVersion): Product {
                $itemCode = strtoupper(trim((string) ($data['item_code'] ?? $data['model_number'] ?? '')));
                $nameAr = trim((string) ($data['name_ar'] ?? ''));
                $nameEn = trim((string) ($data['name_en'] ?? ''));
                $productType = trim((string) ($data['product_type'] ?? 'standard'));
                $status = trim((string) ($data['status'] ?? 'active'));
                $feedKind = $this->nullableString($data['feed_kind'] ?? null);
                $animalType = $this->nullableString($data['animal_type'] ?? null);

                if ($nameAr === '') {
                    throw new InvalidArgumentException(__('The Arabic product name is required for the product card.'));
                }

                if (! in_array($productType, self::PRODUCT_TYPES, true)) {
                    throw new InvalidArgumentException(__('The selected product type is not supported.'));
                }

                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new InvalidArgumentException(__('The selected product status is not supported.'));
                }

                if ($feedKind !== null && ! in_array($feedKind, self::FEED_KINDS, true)) {
                    throw new InvalidArgumentException(__('The selected feed kind is not supported.'));
                }
                if ($animalType !== null && ! in_array($animalType, self::ANIMAL_TYPES, true)) {
                    throw new InvalidArgumentException(__('The selected animal type is not supported.'));
                }

                $category = Category::query()->whereKey((int) ($data['category_id'] ?? 0))->first();
                $brand = ! empty($data['brand_id']) ? Brand::query()->find((int) $data['brand_id']) : null;

                if ($category === null || $category->status !== 'active') {
                    throw new InvalidArgumentException(__('The selected category must exist and be active.'));
                }

                if (! empty($data['brand_id']) && ($brand === null || $brand->status !== 'active')) {
                    throw new InvalidArgumentException(__('The selected brand must exist and be active.'));
                }

                $taxSettingId = ! empty($data['tax_setting_id']) ? (int) $data['tax_setting_id'] : null;
                if ($taxSettingId !== null) {
                    if (Company::query()->count() !== 1 || ! Company::query()->where('status', 'active')->exists()) {
                        throw new InvalidArgumentException(__('A single active company is required before assigning product tax.'));
                    }

                    $taxSetting = TaxSetting::query()
                        ->whereKey($taxSettingId)
                        ->where('status', 'active')
                        ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                        ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
                        ->first();

                    if ($taxSetting === null) {
                        throw new InvalidArgumentException(__('The selected product tax must be active and valid for the current company.'));
                    }
                }

                $pricing = app(ProductPricingContract::class)->forProductCard($data['average_cost'] ?? null, $data['sale_price'] ?? null);
                $supplierId = (int) ($data['preferred_supplier_id'] ?? 0);
                $supplier = $supplierId > 0
                    ? Supplier::query()->whereKey($supplierId)->where('status', 'active')->first()
                    : null;
                if ($supplierId > 0 && $supplier === null) {
                    throw new InvalidArgumentException(__('The selected supplier must exist and be active.'));
                }

                $product = $id === null ? null : Product::query()->lockForUpdate()->findOrFail($id);

                if ($product?->isVariant()) {
                    throw new InvalidArgumentException(__('Variation descriptive fields are owned by the family. Open the family variation matrix to manage this SKU.'));
                }

                if ($product !== null && $product->item_code !== $itemCode) {
                    throw new ImmutableItemCodeChangeException(__('The internal item code is immutable after product creation.'));
                }

                if ($product !== null && $expectedVersion !== null && $product->lock_version !== $expectedVersion) {
                    throw new StaleCatalogRecordException(__('This product changed in another session. Reload it before saving.'));
                }

                if ($product !== null && ! array_key_exists('product_type', $data)) {
                    $productType = $product->product_type;
                }

                $identity = null;
                if ($product === null) {
                    $identity = app(ReserveProductIdentityAction::class)->reserve($itemCode, $supplierId ?: null);
                    $itemCode = $identity['item_code'];
                }

                $attributes = [
                    'item_code' => $itemCode,
                    'name_ar' => $nameAr,
                    'name_en' => $nameEn,
                    'description_ar' => $this->nullableString($data['description_ar'] ?? null),
                    'description_en' => $this->nullableString($data['description_en'] ?? null),
                    'short_description_ar' => $this->nullableString($data['short_description_ar'] ?? null), 'short_description_en' => $this->nullableString($data['short_description_en'] ?? null),
                    'full_description_ar' => $this->nullableString($data['full_description_ar'] ?? null), 'full_description_en' => $this->nullableString($data['full_description_en'] ?? null),
                    'meta_title_ar' => $this->nullableString($data['meta_title_ar'] ?? null), 'meta_title_en' => $this->nullableString($data['meta_title_en'] ?? null), 'meta_description_ar' => $this->nullableString($data['meta_description_ar'] ?? null), 'meta_description_en' => $this->nullableString($data['meta_description_en'] ?? null),
                    'seo_slug' => $this->nullableSlug($data['seo_slug'] ?? null, $id), 'publish_visibility' => $this->nullableString($data['publish_visibility'] ?? null), 'sort_order' => ($data['sort_order'] ?? null) === null || ($data['sort_order'] ?? '') === '' ? null : max(0, (int) $data['sort_order']),
                    'model_number' => $this->nullableString($data['model_number'] ?? null),
                    'product_type' => $productType,
                    'feed_kind' => $feedKind,
                    'animal_type' => $animalType,
                    'protein_percentage' => $this->nullablePercentage($data['protein_percentage'] ?? null),
                    'track_batches' => (bool) ($data['track_batches'] ?? false),
                    'track_expiry' => (bool) ($data['track_expiry'] ?? false),
                    'unit_of_measure' => $this->nullableString($data['unit_of_measure'] ?? null),
                    'category_id' => (int) $data['category_id'],
                    'brand_id' => ! empty($data['brand_id']) ? (int) $data['brand_id'] : null,
                    'tax_setting_id' => $taxSettingId,
                    'originating_supplier_id' => ! empty($data['preferred_supplier_id'] ?? null) ? (int) $data['preferred_supplier_id'] : null,
                    'barcode_registration_type' => $this->nullableString($data['barcode_registration_type'] ?? null),
                    'status' => $status,
                    'reorder_threshold' => in_array($productType, ['service', 'digital'], true) ? null : $this->nullableNumeric($data['reorder_threshold'] ?? null),
                    'dimension_length' => $this->nullableNumeric($data['dimension_length'] ?? null),
                    'dimension_width' => $this->nullableNumeric($data['dimension_width'] ?? null),
                    'dimension_height' => $this->nullableNumeric($data['dimension_height'] ?? null),
                    'dimension_unit' => $this->nullableString($data['dimension_unit'] ?? null),
                    'weight' => $this->nullableNumeric($data['weight'] ?? null),
                    'weight_unit' => $this->nullableString($data['weight_unit'] ?? null),
                    'target_age' => $this->nullableString($data['target_age'] ?? null),
                    'age_label_id' => $this->nullableLookupId($data['age_label_id'] ?? null, AgeLabel::class),
                    'suitable_gender' => $this->nullableString($data['suitable_gender'] ?? null),
                    'gender_id' => $this->nullableLookupId($data['gender_id'] ?? null, Gender::class),
                    'colour' => $this->nullableString($data['colour'] ?? null),
                    'colour_id' => $this->nullableLookupId($data['colour_id'] ?? null, Colour::class),
                    'size' => $this->nullableString($data['size'] ?? null),
                    'character' => $this->nullableString($data['character'] ?? null),
                    'character_id' => $this->nullableLookupId($data['character_id'] ?? null, Character::class),
                    'key_points_ar' => $this->nullableString($data['key_points_ar'] ?? null),
                    'key_points_en' => $this->nullableString($data['key_points_en'] ?? null),
                    'keywords_ar' => $this->nullableString($data['keywords_ar'] ?? null),
                    'keywords_en' => $this->nullableString($data['keywords_en'] ?? null),
                    'fractional_quantity' => false,
                    'sale_price' => $pricing['sale_price'],
                    'open_price' => (bool) ($data['open_price'] ?? false),
                    'sell_online' => (bool) ($data['sell_online'] ?? false),
                    'battery_required' => (bool) ($data['battery_required'] ?? false),
                    'battery_details' => $this->nullableString($data['battery_details'] ?? null),
                ];

                // DEC-038 has no catalog cost-field grant. Keep the field available in the
                // contract for later approved work, but never accept a normal forged mutation.
                if ($product === null) {
                    $attributes['average_cost'] = auth()->user()?->hasPermission('products_categories_brands.cost_view')
                        ? $pricing['average_cost']
                        : null;
                } elseif (auth()->user()?->hasPermission('products_categories_brands.cost_view')) {
                    $attributes['average_cost'] = $pricing['average_cost'];
                }

                if ($product !== null) {
                    foreach ([
                        'description_ar', 'description_en', 'short_description_ar','short_description_en','full_description_ar','full_description_en','meta_title_ar','meta_title_en','meta_description_ar','meta_description_en','seo_slug','publish_visibility','sort_order','model_number', 'feed_kind', 'animal_type', 'protein_percentage', 'track_batches', 'track_expiry', 'unit_of_measure', 'average_cost', 'sale_price', 'battery_required', 'battery_details', 'tax_setting_id',
                        'reorder_threshold', 'dimension_length', 'dimension_width', 'dimension_height',
                        'dimension_unit', 'weight', 'weight_unit', 'open_price', 'sell_online', 'target_age', 'age_label_id', 'suitable_gender', 'gender_id', 'colour', 'colour_id', 'size', 'character', 'character_id',
                        'key_points_ar', 'key_points_en', 'keywords_ar', 'keywords_en', 'fractional_quantity',
                    ] as $optionalField) {
                        if (! array_key_exists($optionalField, $data)) {
                            unset($attributes[$optionalField]);
                        }
                    }
                }

                if ($product === null) {
                    $attributes['barcode_mode'] = 'none';
                    $attributes['lock_version'] = 0;
                    $product = Product::query()->create($attributes);
                    $event = 'create_product_card';
                    $before = null;
                } else {
                    $before = $this->auditValues($product);
                    $previousType = $product->product_type;
                    $product->update([
                        ...$attributes,
                        'lock_version' => $product->lock_version + 1,
                    ]);
                    $event = $previousType !== $productType ? 'change_product_type' : 'update_product_card';

                    if ($product->has_variations) {
                        $this->syncFamilyDescriptions($product);
                    }
                }

                foreach ([['age_label_ids', 'ages'], ['character_ids', 'characters'], ['colour_ids', 'colours'], ['gender_ids', 'genders']] as [$input, $relation]) {
                    if (array_key_exists($input, $data)) {
                        $product->{$relation}()->sync(array_values(array_filter(array_map('intval', (array) $data[$input]))));
                    }
                }
                if (array_key_exists('preferred_supplier_id', $data)) {
                    $product->productSuppliers()->update(['is_preferred' => false]);

                    if ($supplier !== null) {
                        $product->productSuppliers()->updateOrCreate(['supplier_id' => $supplier->id], [
                            'is_preferred' => true,
                            'supplier_item_code' => $this->nullableString($data['supplier_item_code'] ?? null),
                        ]);
                    }
                }

                if (array_key_exists('product_units', $data)) {
                    $this->syncProductUnits($product, (array) $data['product_units']);
                }

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: $event,
                    source: $product,
                    before: $before,
                    after: $this->auditValues($product->fresh()),
                );

                return $product->fresh();
            });
        } catch (ImmutableItemCodeChangeException $exception) {
            $product = $id === null ? null : Product::query()->find($id);

            if ($product !== null) {
                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: 'attempted_immutable_item_code_change',
                    source: $product,
                    before: ['item_code' => $product->item_code],
                    after: ['item_code' => strtoupper(trim((string) ($data['item_code'] ?? '')))],
                    metadata: ['outcome' => 'denied'],
                );
            }

            throw $exception;
        }
    }

    public function toggleStatus(int $id): Product
    {
        Gate::authorize('products_categories_brands.edit');

        return DB::transaction(function () use ($id): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($id);
            $before = ['status' => $product->status, 'lock_version' => $product->lock_version];
            $product->update([
                'status' => $product->status === 'active' ? 'inactive' : 'active',
                'lock_version' => $product->lock_version + 1,
            ]);

            app(RecordAuditEvent::class)->execute(
                category: 'master_data',
                event: 'toggle_product_status',
                source: $product,
                before: $before,
                after: ['status' => $product->status, 'lock_version' => $product->lock_version],
            );

            return $product->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function auditValues(Product $product): array
    {
        return $product->only([
            'item_code', 'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number',
            'product_type', 'feed_kind', 'animal_type', 'protein_percentage', 'track_batches', 'track_expiry', 'unit_of_measure', 'category_id', 'brand_id', 'tax_setting_id', 'status', 'barcode_mode',
            'average_cost', 'reorder_threshold', 'dimension_length', 'dimension_width', 'dimension_height',
            'dimension_unit', 'weight', 'target_age', 'suitable_gender', 'colour', 'size', 'character',
            'key_points_ar', 'key_points_en', 'keywords_ar', 'keywords_en', 'fractional_quantity',
            'lock_version',
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableSlug(mixed $value, ?int $id): ?string { $slug = trim((string) ($value ?? '')); if ($slug === '') return null; $query = Product::query()->where('seo_slug', $slug); if ($id !== null) $query->where('id', '<>', $id); if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || $query->exists()) throw new InvalidArgumentException(__('The SEO slug must be lowercase, URL-safe, and unique.')); return $slug; }

    private function nullableNumeric(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(__('Numeric product values must be zero or greater.'));
        }

        return (float) $value;
    }

    private function nullablePercentage(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
            throw new InvalidArgumentException(__('Protein percentage must be between zero and one hundred.'));
        }

        return bcadd((string) $value, '0', 2);
    }

    /** @param array<int, mixed> $rows */
    private function syncProductUnits(Product $product, array $rows): void
    {
        if ($rows === []) {
            throw new InvalidArgumentException(__('Add at least one product unit.'));
        }

        $unitIds = [];
        $baseCount = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException(__('Product unit data is invalid.'));
            }

            $unitId = filter_var($row['unit_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $factor = trim((string) ($row['conversion_factor'] ?? ''));
            $isBase = filter_var($row['is_base_unit'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($unitId === false || in_array($unitId, $unitIds, true) || ! preg_match('/^(?:0*[1-9]\d*)(?:\.\d{1,6})?$|^0\.0*[1-9]\d{0,5}$/', $factor)) {
                throw new InvalidArgumentException(__('Each product unit must be unique and have a positive conversion factor.'));
            }

            if (! Unit::query()->whereKey($unitId)->where('status', 'active')->lockForUpdate()->exists()) {
                throw new InvalidArgumentException(__('The selected product unit is not active.'));
            }

            if ($isBase && bccomp($factor, '1', 6) !== 0) {
                throw new InvalidArgumentException(__('The base product unit conversion factor must equal one.'));
            }

            $unitIds[] = $unitId;
            $baseCount += $isBase ? 1 : 0;
            ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->id, 'unit_id' => $unitId],
                [
                    'conversion_factor' => bcadd($factor, '0', 6),
                    'is_base_unit' => $isBase,
                    'is_purchase_unit' => filter_var($row['is_purchase_unit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'is_sale_unit' => filter_var($row['is_sale_unit'] ?? false, FILTER_VALIDATE_BOOLEAN),
                ],
            );
        }

        if ($baseCount !== 1) {
            throw new InvalidArgumentException(__('Select exactly one base product unit.'));
        }

        ProductUnit::query()->where('product_id', $product->id)->whereNotIn('unit_id', $unitIds)->delete();
        $baseUnit = Unit::query()->findOrFail($unitIds[array_search(true, array_map(
            fn (mixed $row): bool => filter_var(is_array($row) ? ($row['is_base_unit'] ?? false) : false, FILTER_VALIDATE_BOOLEAN),
            $rows,
        ), true)]);
        $product->update(['unit_of_measure' => $baseUnit->code, 'fractional_quantity' => $baseUnit->decimal_places > 0]);
    }

    private function nullableLookupId(mixed $value, string $model): ?int
    {
        if ($value === null || $value === '') return null;
        $lookup = $model::query()->whereKey((int) $value)->where('status', 'active')->first();
        if ($lookup === null) throw new InvalidArgumentException(__('The selected catalog lookup value must exist and be active.'));
        return (int) $lookup->id;
    }

    private function syncFamilyDescriptions(Product $family): void
    {
        $fields = $family->only([
            'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number', 'unit_of_measure',
            'category_id', 'brand_id', 'dimension_length', 'dimension_width', 'dimension_height', 'dimension_unit',
            'weight', 'target_age', 'suitable_gender', 'character', 'key_points_ar', 'key_points_en', 'keywords_ar',
            'keywords_en', 'fractional_quantity',
        ]);
        $family->variants()->update($fields);
    }
}
