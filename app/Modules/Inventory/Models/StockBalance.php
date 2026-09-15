<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $fillable = [
        'product_id',
        'store_id',
        'on_hand',
        'reserved',
        'in_transit',
        'average_cost',
        'total_value',
        'version',
    ];

    protected $casts = [
        'on_hand' => 'decimal:6',
        'reserved' => 'decimal:6',
        'in_transit' => 'decimal:6',
        'average_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'version' => 'integer',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
