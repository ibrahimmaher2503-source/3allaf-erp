<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftReceiptLine extends Model
{
    protected $fillable = ['gift_receipt_id', 'sale_line_id', 'product_id', 'line_number', 'item_code', 'name_ar', 'name_en', 'quantity'];
    protected $casts = ['quantity' => 'decimal:6'];
    public function giftReceipt(): BelongsTo { return $this->belongsTo(GiftReceipt::class); }
    public function saleLine(): BelongsTo { return $this->belongsTo(SaleLine::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
