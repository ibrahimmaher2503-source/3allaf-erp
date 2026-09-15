<?php

namespace App\Modules\Pricing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['price_version_id', 'product_id', 'store_id', 'branch_id', 'amount', 'reference_amount', 'open_price_allowed', 'open_price_minimum', 'open_price_maximum', 'active_key', 'notes'];

    protected $casts = ['amount' => 'decimal:3', 'reference_amount' => 'decimal:3', 'open_price_allowed' => 'boolean', 'open_price_minimum' => 'decimal:4', 'open_price_maximum' => 'decimal:4'];

    /** @return BelongsTo<PriceVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(PriceVersion::class, 'price_version_id');
    }

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

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->version()->first();
    }
}
