<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryAdjustmentLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['inventory_adjustment_id', 'product_id', 'quantity_delta', 'unit_cost', 'before_on_hand', 'after_on_hand'];

    protected $casts = ['quantity_delta' => 'decimal:6', 'unit_cost' => 'decimal:4', 'before_on_hand' => 'decimal:6', 'after_on_hand' => 'decimal:6'];

    /** @return BelongsTo<InventoryAdjustment, $this> */
    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->adjustment()->first();
    }
}
