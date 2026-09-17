<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'unit_id',
        'conversion_factor',
        'minimum_selling_price',
        'is_base_unit',
        'is_purchase_unit',
        'is_sale_unit',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
        'minimum_selling_price' => 'decimal:4',
        'is_base_unit' => 'boolean',
        'is_purchase_unit' => 'boolean',
        'is_sale_unit' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
