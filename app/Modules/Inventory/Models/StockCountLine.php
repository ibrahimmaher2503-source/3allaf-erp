<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockCountLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['stock_count_id', 'store_id', 'product_id', 'reference_on_hand', 'movement_quantity_after_reference', 'movement_after_completion', 'expected_quantity', 'counted_quantity', 'normalized_closing_quantity', 'variance_quantity', 'is_counted', 'explicit_zero', 'input_method', 'recount_number', 'counted_at', 'completed_at', 'completed_by', 'notes'];

    protected $casts = ['reference_on_hand' => 'decimal:6', 'movement_quantity_after_reference' => 'decimal:6', 'movement_after_completion' => 'decimal:6', 'expected_quantity' => 'decimal:6', 'counted_quantity' => 'decimal:6', 'normalized_closing_quantity' => 'decimal:6', 'variance_quantity' => 'decimal:6', 'is_counted' => 'boolean', 'explicit_zero' => 'boolean', 'recount_number' => 'integer', 'counted_at' => 'datetime', 'completed_at' => 'datetime'];

    /** @return BelongsTo<StockCount, $this> */
    public function count(): BelongsTo
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo { return $this->belongsTo(Store::class); }

    protected function approvedParent(): ?Model
    {
        return $this->count()->first();
    }
}
