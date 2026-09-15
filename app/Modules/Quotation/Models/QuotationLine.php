<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuotationLine extends Model
{
    protected $fillable = ['quotation_id', 'line_number', 'line_type', 'product_id', 'description_ar', 'description_en', 'quantity', 'unit_price', 'line_total', 'source_reference'];
    protected $casts = ['quantity' => 'decimal:6', 'unit_price' => 'decimal:4', 'line_total' => 'decimal:2'];
    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
