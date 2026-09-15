<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Queries;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;

final class SearchAssignableProducts
{
    /** @param array<string,mixed> $filters */
    public function query(array $filters): Builder
    {
        $query = Product::query()->with(['barcodes' => fn ($q) => $q->where('status', 'active')->orderByDesc('is_primary'), 'productSuppliers:id,product_id,supplier_item_code', 'category:id,code,name_ar,name_en', 'brand:id,code,name_ar,name_en'])
            ->where(function (Builder $scope): void {
                $scope->where(function (Builder $simple): void {
                    $simple->whereNull('products.parent_product_id')->where('products.has_variations', false);
                })->orWhere(function (Builder $variant): void {
                    $variant->whereNotNull('products.parent_product_id')
                        ->whereHas('parent', fn (Builder $family): Builder => $family->where('status', 'active')->where('has_variations', true));
                });
            })->where('product_type', '!=', 'service')->completeCard();
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested->where('item_code', 'like', $like)->orWhere('model_number', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)->orWhere('name_en', 'like', $like)
                    ->orWhereHas('barcodes', fn (Builder $barcodes) => $barcodes->where('barcode', 'like', $like)->where('status', 'active'))
                    ->orWhereHas('productSuppliers', fn (Builder $suppliers) => $suppliers->where('supplier_item_code', 'like', $like));
            });
        }
        foreach (['category_id', 'brand_id'] as $field) {
            if (($id = filter_var($filters[$field] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) !== false) $query->where($field, $id);
        }
        if (($supplierId = filter_var($filters['supplier_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) !== false) {
            $query->whereHas('productSuppliers', fn (Builder $supplier) => $supplier->where('supplier_id', $supplierId));
        }
        if (in_array($filters['product_type'] ?? null, ['standard', 'composite', 'digital'], true)) $query->where('product_type', $filters['product_type']);
        $query->where('products.status', in_array($filters['status'] ?? null, ['active', 'inactive'], true) ? $filters['status'] : 'active');
        if (($filters['not_assigned'] ?? false) && ($storeId = filter_var($filters['store_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) !== false) {
            $query->whereDoesntHave('storeAssignments', fn (Builder $assignment) => $assignment->where('store_id', $storeId)->where('status', 'active'));
        }

        return $query->orderBy('item_code')->orderBy('id');
    }
}
