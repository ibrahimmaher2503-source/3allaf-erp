<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'dimension',
        'decimal_places',
        'status',
    ];

    protected $casts = [
        'decimal_places' => 'integer',
    ];

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_units')
            ->withPivot(['conversion_factor', 'is_base_unit', 'is_purchase_unit', 'is_sale_unit'])
            ->withTimestamps();
    }
}
