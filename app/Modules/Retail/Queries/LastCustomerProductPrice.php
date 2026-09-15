<?php

declare(strict_types=1);

namespace App\Modules\Retail\Queries;

use App\Modules\Retail\Models\SaleLine;

final class LastCustomerProductPrice
{
    /** @return array{price: string, unit_code: ?string, date: string, sale_id: int}|null */
    public function for(int $customerId, int $productId): ?array
    {
        $line = SaleLine::query()
            ->select('sale_lines.*', 'sales.approved_at as sale_approved_at')
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', 'approved')
            ->where('sale_lines.product_id', $productId)
            ->with('productUnit.unit')
            ->orderByDesc('sales.approved_at')
            ->orderByDesc('sales.id')
            ->orderByDesc('sale_lines.id')
            ->first();

        if ($line === null) {
            return null;
        }

        return [
            'price' => (string) ($line->entered_unit_price ?? $line->unit_price),
            'unit_code' => $line->productUnit?->unit?->code,
            'date' => (string) $line->getAttribute('sale_approved_at'),
            'sale_id' => (int) $line->sale_id,
        ];
    }
}
