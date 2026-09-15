<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return StoreFactory::new();
    }

    protected $fillable = [
        'company_id',
        'branch_id',
        'price_list_id',
        'code',
        'type',
        'name_ar',
        'name_en',
        'status',
        'allows_negative_stock',
        'policy_notes',
    ];

    protected $casts = [
        'allows_negative_stock' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function priceList(): BelongsTo { return $this->belongsTo(\App\Modules\Pricing\Models\PriceList::class); }

    public function sellingStoreMappings(): HasMany
    {
        return $this->hasMany(BranchSellingStore::class);
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('id', $user->storeScopes()->where('status', 'active')->select('store_id'))
                ->orWhereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'));
        });
    }
}
