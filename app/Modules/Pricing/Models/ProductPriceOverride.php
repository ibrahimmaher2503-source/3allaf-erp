<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductPriceOverride extends Model
{
    protected $fillable = ['company_id', 'price_list_id', 'product_id', 'amount', 'reason', 'effective_from', 'effective_to', 'created_by', 'updated_by'];
    protected $casts = ['amount' => 'decimal:3', 'effective_from' => 'date', 'effective_to' => 'date'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function isEffective(?\DateTimeInterface $at = null): bool
    {
        $at = \Illuminate\Support\Carbon::instance($at ?? now())->startOfDay();
        return ($this->effective_from === null || $this->effective_from->lte($at)) && ($this->effective_to === null || $this->effective_to->gte($at));
    }
}
