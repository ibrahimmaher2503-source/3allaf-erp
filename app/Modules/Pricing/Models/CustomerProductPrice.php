<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class CustomerProductPrice extends Model
{
    protected $fillable = ['company_id', 'customer_id', 'product_id', 'product_unit_id', 'price', 'effective_from', 'effective_to', 'status', 'active_key', 'reason', 'created_by', 'approved_by', 'expired_by', 'approved_at', 'expired_at'];

    protected $casts = ['price' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime', 'expired_at' => 'datetime'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEffective(?\DateTimeInterface $at = null): bool
    {
        $date = Carbon::instance($at ?? now())->startOfDay();

        return $this->status === 'active'
            && $this->effective_from->lte($date)
            && ($this->effective_to === null || $this->effective_to->gte($date));
    }
}
