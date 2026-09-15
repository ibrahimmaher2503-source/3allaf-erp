<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockTransferLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['stock_transfer_id', 'product_id', 'quantity_requested', 'quantity_dispatched', 'quantity_received', 'unit_cost', 'difference_quantity', 'difference_type', 'difference_reason'];

    protected $casts = ['quantity_requested' => 'decimal:6', 'quantity_dispatched' => 'decimal:6', 'quantity_received' => 'decimal:6', 'unit_cost' => 'decimal:4', 'difference_quantity' => 'decimal:6'];

    /** @return BelongsTo<StockTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->transfer()->first();
    }
}
