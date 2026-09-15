<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExchangeLine extends Model
{
    protected $fillable = ['exchange_id', 'product_id', 'direction', 'quantity', 'unit_value', 'item_code', 'name_ar', 'name_en'];
    protected $casts = ['quantity' => 'decimal:6', 'unit_value' => 'decimal:4'];
    public function exchange(): BelongsTo { return $this->belongsTo(Exchange::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
