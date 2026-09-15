<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductOptionGroup;
use App\Modules\Catalog\Models\ProductOptionValue;
use App\Modules\Catalog\Models\ProductVariantValue;
use App\Modules\Platform\Actions\RecordAuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class GenerateProductVariationsAction
{
    private const MAX_GROUPS = 3;

    private const MAX_VARIANTS = 100;

    /**
     * @param  array<int|string, array<int, int|string>>  $selectedValues  Group id => value ids.
     * @param  array<string, array{sku?: string, barcode?: string|null, status?: string, reorder_threshold?: numeric-string|int|float|null}>  $variantInput  Canonical signature => independently-owned fields.
     */
    public function execute(Product $candidate, array $selectedValues, array $variantInput): Product
    {
        Gate::authorize('products_categories_brands.edit');

        try {
            return DB::transaction(function () use ($candidate, $selectedValues, $variantInput): Product {
                $family = Product::query()->lockForUpdate()->findOrFail($candidate->id);
                $this->assertConvertible($family);
                $groups = $this->normalizeSelection($selectedValues);
                $groupIds = array_keys($groups);
                $existingGroupIds = DB::table('product_family_option_groups')->where('product_id', $family->id)->orderBy('sort_order')->pluck('product_option_group_id')->map(fn ($id): int => (int) $id)->all();
                if ($family->variants()->exists() && $existingGroupIds !== $groupIds) {
                    throw new InvalidArgumentException(__('Variation option groups are immutable after the first SKU is generated.'));
                }

                $combinations = $this->combinations($groups);
                if (count($combinations) > self::MAX_VARIANTS) {
                    throw new InvalidArgumentException(__('A product family cannot generate more than 100 SKUs.'));
                }

                $preferredSupplierId = $family->preferredProductSupplier()
                    ->whereHas('supplier', fn ($query) => $query->where('status', 'active'))
                    ->value('supplier_id');
                $plans = [];
                $reservedCodes = [];
                foreach ($combinations as $sortOrder => $selection) {
                    $signature = $this->signature($selection);
                    if ($family->variants()->where('variant_signature', $signature)->exists()) {
                        continue;
                    }

                    $input = $variantInput[$signature] ?? [];
                    $sku = strtoupper(trim((string) ($input['sku'] ?? '')));
                    $barcode = strtoupper(trim((string) ($input['barcode'] ?? '')));
                    if ($sku !== '' && $barcode !== '' && $sku !== $barcode) {
                        throw new InvalidArgumentException(__('A new variation global code and barcode must match.'));
                    }
                    $identity = app(ReserveProductIdentityAction::class)->reserve($sku !== '' ? $sku : $barcode, $preferredSupplierId ? (int) $preferredSupplierId : null);
                    if (isset($reservedCodes[$identity['item_code']])) {
                        throw new InvalidArgumentException(__('Every new variation must have a unique identity.'));
                    }
                    $reservedCodes[$identity['item_code']] = true;
                    $status = (string) ($input['status'] ?? 'inactive');
                    if (! in_array($status, ['active', 'inactive'], true)) {
                        throw new InvalidArgumentException(__('The selected variation status is not supported.'));
                    }
                    $plans[] = [
                        'selection' => $selection,
                        'signature' => $signature,
                        'sort_order' => $sortOrder + 1,
                        'identity' => $identity,
                        'status' => $status,
                        'reorder_threshold' => $this->nullableNonNegative($input['reorder_threshold'] ?? null),
                    ];
                }

                if (! $family->has_variations) {
                    $family->update(['has_variations' => true, 'lock_version' => $family->lock_version + 1]);
                }
                foreach ($groupIds as $order => $groupId) {
                    DB::table('product_family_option_groups')->insertOrIgnore([
                        'product_id' => $family->id,
                        'product_option_group_id' => $groupId,
                        'sort_order' => $order + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    foreach ($groups[$groupId] as $valueId) {
                        DB::table('product_family_option_values')->insertOrIgnore([
                            'product_id' => $family->id,
                            'product_option_value_id' => $valueId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                $created = [];
                foreach ($plans as $plan) {
                    $identity = $plan['identity'];
                    $variant = Product::query()->create([
                        ...$this->descriptiveFields($family),
                        'item_code' => $identity['item_code'],
                        'product_type' => 'standard',
                        'has_variations' => false,
                        'parent_product_id' => $family->id,
                        'variant_signature' => $plan['signature'],
                        'variant_sort_order' => $plan['sort_order'],
                        'status' => $plan['status'],
                        'barcode_mode' => $identity['barcode_source'],
                        'average_cost' => null,
                        'reorder_threshold' => $plan['reorder_threshold'],
                        'lock_version' => 0,
                    ]);
                    app(ReserveProductIdentityAction::class)->createPrimaryBarcode($variant, $identity['item_code'], $identity['barcode_source']);
                    if ($identity['supplier'] !== null) {
                        $variant->productSuppliers()->create(['supplier_id' => $identity['supplier']->id, 'is_preferred' => true]);
                    }
                    foreach ($plan['selection'] as $position => $valueId) {
                        $value = ProductOptionValue::query()->findOrFail($valueId);
                        ProductVariantValue::query()->create([
                            'product_id' => $variant->id,
                            'product_option_group_id' => $value->product_option_group_id,
                            'product_option_value_id' => $value->id,
                            'sort_order' => $position + 1,
                        ]);
                    }
                    $created[] = $variant->id;
                }

                app(RecordAuditEvent::class)->execute(
                    category: 'master_data',
                    event: 'generate_product_variations',
                    source: $family,
                    after: ['group_ids' => $groupIds, 'combination_count' => count($combinations), 'created_variant_ids' => $created],
                );

                return $family->fresh(['familyOptionGroups.values', 'variants.variantValues.group', 'variants.variantValues.value']);
            });
        } catch (\Throwable $exception) {
            app(RecordAuditEvent::class)->execute(category: 'master_data', event: 'product_variation_change_denied', source: $candidate, metadata: ['outcome' => 'denied', 'reason' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function inactivate(Product $variant): Product
    {
        Gate::authorize('products_categories_brands.edit');
        if (! $variant->isVariant()) {
            throw new InvalidArgumentException(__('Only a variation SKU can be inactivated here.'));
        }

        return DB::transaction(function () use ($variant): Product {
            $locked = Product::query()->whereNotNull('parent_product_id')->lockForUpdate()->findOrFail($variant->id);
            $before = ['status' => $locked->status];
            $locked->update(['status' => 'inactive', 'lock_version' => $locked->lock_version + 1]);
            app(RecordAuditEvent::class)->execute(category: 'master_data', event: 'inactivate_product_variation', source: $locked, before: $before, after: ['status' => 'inactive']);

            return $locked->fresh();
        });
    }

    private function assertConvertible(Product $product): void
    {
        if ($product->parent_product_id !== null || $product->product_type !== 'standard') {
            throw new InvalidArgumentException(__('Only a standard parent product can be configured as a variation family.'));
        }
        if ($product->has_variations) {
            return;
        }
        if ($product->barcodes()->exists() || $product->average_cost !== null) {
            throw new InvalidArgumentException(__('A used, barcoded, priced, or stocked product cannot be converted. Create a new family instead.'));
        }
        foreach (['price_lines', 'stock_balances', 'stock_movements', 'purchase_order_lines', 'purchase_invoice_lines', 'sale_lines', 'quotation_lines', 'product_suppliers', 'label_queues'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id') && DB::table($table)->where('product_id', $product->id)->exists()) {
                throw new InvalidArgumentException(__('A used, barcoded, priced, or stocked product cannot be converted. Create a new family instead.'));
            }
        }
    }

    /** @param array<int|string, array<int, int|string>> $selectedValues @return array<int, array<int, int>> */
    private function normalizeSelection(array $selectedValues): array
    {
        $groups = [];
        foreach ($selectedValues as $groupId => $valueIds) {
            $groupId = (int) $groupId;
            $ids = collect($valueIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
            if ($groupId > 0 && $ids !== []) {
                $groups[$groupId] = $ids;
            }
        }
        if (count($groups) < 1 || count($groups) > self::MAX_GROUPS) {
            throw new InvalidArgumentException(__('Select between one and three option groups.'));
        }
        $foundGroups = ProductOptionGroup::query()->active()->whereIn('id', array_keys($groups))->count();
        if ($foundGroups !== count($groups)) {
            throw new InvalidArgumentException(__('Every selected option group must be active.'));
        }
        foreach ($groups as $groupId => $ids) {
            if (ProductOptionValue::query()->active()->where('product_option_group_id', $groupId)->whereIn('id', $ids)->count() !== count($ids)) {
                throw new InvalidArgumentException(__('Every selected option value must be active and belong to its group.'));
            }
        }

        return $groups;
    }

    /** @param array<int, array<int, int>> $groups @return array<int, array<int, int>> */
    private function combinations(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $values) {
            $next = [];
            foreach ($result as $combination) {
                foreach ($values as $value) {
                    $next[] = [...$combination, $value];
                    if (count($next) > self::MAX_VARIANTS) {
                        throw new InvalidArgumentException(__('A product family cannot generate more than 100 SKUs.'));
                    }
                }
            }
            $result = $next;
        }

        return $result;
    }

    /** @param array<int, int> $valueIds */
    public function signature(array $valueIds): string
    {
        $values = ProductOptionValue::query()->whereIn('id', $valueIds)->get()->keyBy('id');

        return collect($valueIds)->map(fn (int $id): string => $values[$id]->product_option_group_id.':'.$id)->implode('|');
    }

    /** @return array<string, mixed> */
    private function descriptiveFields(Product $family): array
    {
        return $family->only([
            'name_ar', 'name_en', 'description_ar', 'description_en', 'model_number', 'unit_of_measure',
            'category_id', 'brand_id', 'dimension_length', 'dimension_width', 'dimension_height', 'dimension_unit',
            'weight', 'target_age', 'suitable_gender', 'character', 'key_points_ar', 'key_points_en', 'keywords_ar',
            'keywords_en', 'fractional_quantity',
        ]) + ['colour' => null, 'size' => null];
    }

    private function nullableNonNegative(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException(__('Variation reorder thresholds must be zero or greater.'));
        }

        return (float) $value;
    }
}
