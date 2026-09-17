<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class ProductPriceOverride extends Model
{
    protected $fillable = ['company_id', 'price_list_id', 'product_id', 'product_unit_id', 'amount', 'reason', 'effective_from', 'effective_to', 'created_by', 'updated_by'];

    protected $casts = ['amount' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function isEffective(?\DateTimeInterface $at = null): bool
    {
        $at = Carbon::instance($at ?? now())->startOfDay();

        return ($this->effective_from === null || $this->effective_from->lte($at)) && ($this->effective_to === null || $this->effective_to->gte($at));
    }
}
