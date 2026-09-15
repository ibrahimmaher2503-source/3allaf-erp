<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupplierReturnReason extends Model
{
    protected $fillable = [
        'code', 'label_ar', 'label_en', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<SupplierReturnReason>  $query
     * @return Builder<SupplierReturnReason>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** @return HasMany<PurchaseReturn, $this> */
    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'reason_id');
    }
}
