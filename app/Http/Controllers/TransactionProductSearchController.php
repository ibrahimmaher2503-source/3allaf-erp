<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use App\Modules\Purchasing\Queries\SupplierProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransactionProductSearchController extends Controller
{
    private const ABILITIES = [
        'purchase_orders.create', 'purchase_orders.edit',
        'purchase_invoices.draft', 'purchase_invoices_supplier_returns.create', 'purchase_invoices_supplier_returns.edit',
        'purchase_returns.create', 'purchase_returns.edit',
        'transfers.create', 'transfers.edit', 'inventory_stock_card.create', 'inventory_stock_card.edit', 'stock_counts.participate',
        'pos_sales.create', 'returns.create',
        'quotations.create', 'quotations.edit',
        'party_bookings_invoices.create', 'party_bookings_invoices.edit',
        'party_operating_orders_consumables.create', 'party_operating_orders_consumables.edit',
    ];

    public function __invoke(Request $request, SupplierProductPrice $prices): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user();
        abort_unless($actor instanceof User && ($actor->is_super_admin || collect(self::ABILITIES)->contains(fn (string $ability): bool => $actor->can($ability))), 403);

        $term = trim((string) $request->query('q', ''));
        if ($term === '' || mb_strlen($term) > 190) {
            return response()->json(['data' => []]);
        }

        $purchasing = $request->query('context') === 'purchasing';
        $supplierId = $request->integer('supplier_id');
        $supplierOnly = $purchasing && $request->boolean('supplier_only', true);
        $currencyCode = trim((string) $request->query('currency_code', '')) ?: null;
        $sourceInvoiceId = $request->integer('purchase_invoice_id');

        if ($purchasing && $supplierId < 1) {
            return response()->json(['data' => [], 'meta' => ['supplier_required' => true]]);
        }
        if ($supplierId > 0) {
            Supplier::query()->active()->findOrFail($supplierId);
        }

        $sourceInvoice = null;
        if ($sourceInvoiceId > 0) {
            abort_unless($actor->can('purchase_returns.create') || $actor->can('purchase_returns.edit'), 403);
            $sourceInvoice = PurchaseInvoice::query()
                ->whereIn('store_id', Store::query()->visibleTo($actor)->select('id'))
                ->where('status', 'approved')
                ->where('supplier_id', $supplierId)
                ->findOrFail($sourceInvoiceId);
        }

        $escaped = addcslashes($term, '\\%_');
        $prefix = $escaped.'%';
        $contained = '%'.$escaped.'%';
        $productsQuery = Product::query()->sellable()
            ->with([
                'barcodes' => fn ($query) => $query->active()->orderByDesc('is_primary'),
                'productSuppliers' => fn ($query) => $query->where('supplier_id', $supplierId),
            ])
            ->when($supplierOnly, fn ($query) => $query->whereHas('productSuppliers', fn ($relations) => $relations->where('supplier_id', $supplierId)))
            ->when($sourceInvoice !== null, fn ($query) => $query->whereIn('products.id', PurchaseInvoiceLine::query()->where('purchase_invoice_id', $sourceInvoice->id)->select('product_id')))
            ->where(function ($query) use ($term, $prefix, $contained, $supplierId): void {
                $query->where('item_code', $term)->orWhere('model_number', $term)
                    ->orWhere('item_code', 'like', $prefix)->orWhere('model_number', 'like', $prefix)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->active()->where(fn ($barcode) => $barcode->where('barcode', 'like', $prefix)->orWhere('barcode', 'like', $contained)))
                    ->orWhere('name_ar', 'like', $contained)->orWhere('name_en', 'like', $contained)
                    ->orWhere('item_code', 'like', $contained)->orWhere('model_number', 'like', $contained)
                    ->when($supplierId > 0, fn ($query) => $query->orWhereHas('productSuppliers', fn ($relations) => $relations->where('supplier_id', $supplierId)->where('supplier_item_code', 'like', $contained)));
            });

        $exactProductIds = (clone $productsQuery)
            ->where(function ($query) use ($term, $supplierId): void {
                $query->where('item_code', $term)
                    ->orWhere('model_number', $term)
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->active()->where('barcode', $term))
                    ->when($supplierId > 0, fn ($query) => $query->orWhereHas('productSuppliers', fn ($relations) => $relations->where('supplier_id', $supplierId)->where('supplier_item_code', $term)));
            })
            ->limit(2)
            ->pluck('products.id');
        $uniqueExactProductId = $exactProductIds->count() === 1 ? (int) $exactProductIds->first() : null;

        $products = $productsQuery
            ->orderByRaw('CASE WHEN item_code = ? OR model_number = ? OR EXISTS (SELECT 1 FROM barcodes WHERE barcodes.product_id = products.id AND barcodes.status = ? AND barcodes.barcode = ?) THEN 0 WHEN item_code LIKE ? OR model_number LIKE ? THEN 1 ELSE 2 END', [$term, $term, 'active', $term, $prefix, $prefix])
            ->orderBy('item_code')
            ->limit(20)
            ->get();

        $mayViewCost = $actor->can('products_categories_brands.cost_view')
            || collect(['purchase_orders.create', 'purchase_orders.edit', 'purchase_invoices.draft', 'purchase_returns.create', 'purchase_returns.edit', 'inventory_stock_card.create', 'inventory_stock_card.edit', 'transfers.create', 'transfers.edit'])
                ->contains(fn (string $ability): bool => $actor->can($ability));

        $supplierProductsEmpty = $supplierOnly && ! ProductSupplier::query()->where('supplier_id', $supplierId)->exists();

        return response()->json(['data' => $products->map(function (Product $product) use ($term, $mayViewCost, $supplierId, $currencyCode, $prices, $uniqueExactProductId): array {
            $barcodes = $product->barcodes->pluck('barcode')->filter()->values();
            $supplierProduct = $product->productSuppliers->first();
            $identifiers = collect([$product->item_code, $product->model_number, $supplierProduct?->supplier_item_code])->merge($barcodes)->filter()->map(fn ($value) => mb_strtolower(trim((string) $value)))->unique()->values();
            $price = $supplierId > 0 && $mayViewCost
                ? $prices->resolve($product, $supplierId, $currencyCode)
                : ['unit_cost' => $mayViewCost ? $product->average_cost : null, 'price_source' => $mayViewCost ? 'fallback_cost' : 'none', 'price_date' => null, 'price_currency' => $currencyCode];

            return [
                'id' => $product->id,
                'item_code' => $product->item_code,
                'model_number' => $product->model_number,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'barcodes' => $barcodes,
                'unit_of_measure' => $product->unit_of_measure,
                'supplier_item_code' => $supplierProduct?->supplier_item_code,
                'unit_cost' => $price['unit_cost'],
                'price_source' => $price['price_source'],
                'price_date' => $price['price_date'],
                'price_currency' => $price['price_currency'],
                'unit_price' => $product->sale_price,
                'exact' => $identifiers->contains(mb_strtolower($term)),
                'exact_unique' => $uniqueExactProductId === $product->id,
            ];
        })->values(), 'meta' => ['supplier_products_empty' => $supplierProductsEmpty]]);
    }
}
