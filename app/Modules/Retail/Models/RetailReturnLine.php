<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetailReturnLine extends Model
{
    protected $fillable = ["retail_return_id", "sale_line_id", "product_id", "return_store_id", "line_number", "quantity", "unit_value", "gross_value", "discount_value", "eligible_value", "tax_value", "condition", "disposition", "inspection_notes"];
    protected $casts = ["quantity" => "decimal:6", "unit_value" => "decimal:4", "gross_value" => "decimal:2", "discount_value" => "decimal:2", "eligible_value" => "decimal:2", "tax_value" => "decimal:2"];
    public function retailReturn(): BelongsTo { return $this->belongsTo(RetailReturn::class); }
    public function saleLine(): BelongsTo { return $this->belongsTo(SaleLine::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function returnStore(): BelongsTo { return $this->belongsTo(Store::class, "return_store_id"); }
}
