<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Governorate extends Model
{
    protected $fillable = ['code', 'name_ar', 'name_en', 'sort_order', 'status'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
