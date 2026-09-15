<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseReturnLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = [
        'purchase_return_id', 'purchase_invoice_line_id', 'product_id', 'quantity', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
    ];

    /** @return BelongsTo<PurchaseReturn, $this> */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /** @return BelongsTo<PurchaseInvoiceLine, $this> */
    public function purchaseInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->purchaseReturn()->first();
    }
}
