<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Modules\Purchasing\Models\PurchaseOrder;

class Branch extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return BranchFactory::new();
    }

    protected $fillable = [
        'company_id',
        'default_price_list_id',
        'code',
        'name_ar',
        'name_en',
        'phone',
        'email',
        'address',
        'timezone',
        'status',
        'policy_notes',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultPriceList(): BelongsTo { return $this->belongsTo(\App\Modules\Pricing\Models\PriceList::class, 'default_price_list_id'); }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function purchaseOrders(): HasMany { return $this->hasMany(PurchaseOrder::class); }

    /**
     * Active warehouse locations directly belonging to this branch.
     *
     * Store remains the technical model; this relationship gives branch
     * directories an authoritative Warehouse-facing count.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Store::class)
            ->where('stores.type', 'warehouse')
            ->where('stores.status', 'active');
    }

    public function sellingStoreMappings(): HasMany
    {
        return $this->hasMany(BranchSellingStore::class);
    }

    public function activeSellingStoreMapping(): HasOne
    {
        return $this->hasOne(BranchSellingStore::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query): Builder => $query->where('status', 'active'),
        );
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->is_super_admin
            ? $query
            : $query->whereIn('id', $user->branchScopes()->where('status', 'active')->select('branch_id'));
    }
}
