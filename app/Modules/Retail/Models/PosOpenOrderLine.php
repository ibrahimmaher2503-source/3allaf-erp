<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PosOpenOrderLine extends Model
{
    protected $fillable = ['pos_open_order_id', 'product_id', 'line_position', 'quantity', 'draft_payload'];
    protected $casts = ['quantity' => 'decimal:6', 'draft_payload' => 'array'];

    public function order(): BelongsTo { return $this->belongsTo(PosOpenOrder::class, 'pos_open_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
