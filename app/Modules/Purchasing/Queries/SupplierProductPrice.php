<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Queries;

use App\Modules\Catalog\Models\Product;
use App\Modules\Purchasing\Models\PurchaseInvoiceLine;
use Illuminate\Support\Carbon;

final class SupplierProductPrice
{
    /** @return array{unit_cost: string|null, price_source: string, price_date: string|null, price_currency: string|null} */
    public function resolve(Product $product, int $supplierId, ?string $currencyCode = null): array
    {
        $currencyCode = filled($currencyCode) ? strtoupper(trim((string) $currencyCode)) : null;
        $lastPurchase = PurchaseInvoiceLine::query()
            ->select([
                'purchase_invoice_lines.unit_cost',
                'purchase_invoices.currency_code',
                'purchase_invoices.invoice_date',
                'purchase_invoices.approved_at',
            ])
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
            ->where('purchase_invoice_lines.product_id', $product->id)
            ->where('purchase_invoices.supplier_id', $supplierId)
            ->where('purchase_invoices.status', 'approved')
            ->when($currencyCode !== null, fn ($query) => $query->where('purchase_invoices.currency_code', $currencyCode))
            ->orderByDesc('purchase_invoices.approved_at')
            ->orderByDesc('purchase_invoices.id')
            ->first();

        if ($lastPurchase !== null) {
            $priceDate = $lastPurchase->approved_at ?? $lastPurchase->invoice_date;

            return [
                'unit_cost' => (string) $lastPurchase->unit_cost,
                'price_source' => 'last_supplier_price',
                'price_date' => $priceDate === null ? null : Carbon::parse($priceDate)->toDateString(),
                'price_currency' => $lastPurchase->currency_code,
            ];
        }

        $fallback = $product->average_cost;

        return [
            'unit_cost' => $fallback === null ? null : (string) $fallback,
            'price_source' => $fallback === null ? 'none' : 'fallback_cost',
            'price_date' => null,
            'price_currency' => $currencyCode,
        ];
    }
}
