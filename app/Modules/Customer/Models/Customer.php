<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\City;
use App\Modules\Platform\Models\Governorate;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\CustomerProductPrice;
use App\Modules\Pricing\Models\PriceList;
use App\Modules\Retail\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class Customer extends Model
{
    private bool $namedMutation = false;

    protected $fillable = [
        'public_id', 'phone_normalized', 'phone_display', 'first_name_ar', 'last_name_ar', 'first_name_en', 'last_name_en', 'name_ar', 'name_en', 'email',
        'secondary_phone', 'secondary_phone_normalized', 'address_ar', 'address_en', 'status', 'merged_into_id',
        'created_by', 'updated_by', 'created_branch_id', 'created_store_id', 'customer_group_id', 'price_list_id', 'customer_type', 'credit_limit', 'notes', 'governorate_id', 'city_id', 'idempotency_key', 'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer',
        'credit_limit' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $customer): void {
            $customer->public_id ??= (string) Str::uuid();
        });
        self::updating(function (self $customer): void {
            if ($customer->getOriginal('status') === 'merged' && ! $customer->namedMutation) {
                throw new LogicException('Merged customer profiles are immutable.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Customer history is preserved; use a named logical state action.'));
    }

    public function getCustomerCodeAttribute(): string
    {
        return 'CUS-'.str_pad((string) $this->getKey(), 7, '0', STR_PAD_LEFT);
    }

    /** @return BelongsTo<Customer, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function specialPrices(): HasMany
    {
        return $this->hasMany(CustomerProductPrice::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Branch, $this> */
    public function createdBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'created_branch_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function createdStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'created_store_id');
    }

    /** @return HasMany<CustomerScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(CustomerScope::class);
    }

    /** @return HasMany<CustomerConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(CustomerConsent::class);
    }

    /** @return HasMany<CustomerChild, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(CustomerChild::class);
    }

    /** @return HasMany<LoyaltyLedger, $this> */
    public function loyaltyLedger(): HasMany
    {
        return $this->hasMany(LoyaltyLedger::class);
    }

    /** @return HasMany<LoyaltyAdjustment, $this> */
    public function loyaltyAdjustments(): HasMany
    {
        return $this->hasMany(LoyaltyAdjustment::class);
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(CustomerReceipt::class);
    }

    public function accountAdjustments(): HasMany
    {
        return $this->hasMany(CustomerAccountAdjustment::class);
    }

    /** @return HasMany<ProductWalletLedger, $this> */
    public function productWalletLedger(): HasMany
    {
        return $this->hasMany(ProductWalletLedger::class);
    }

    /** @return HasMany<PartyWalletLedger, $this> */
    public function partyWalletLedger(): HasMany
    {
        return $this->hasMany(PartyWalletLedger::class);
    }

    /** @return HasMany<ProductWalletAdjustment, $this> */
    public function productWalletAdjustments(): HasMany
    {
        return $this->hasMany(ProductWalletAdjustment::class);
    }

    /** @return HasMany<PartyWalletAdjustment, $this> */
    public function partyWalletAdjustments(): HasMany
    {
        return $this->hasMany(PartyWalletAdjustment::class);
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $companyIds = Store::query()->visibleTo($user)->select('company_id');

        return $query->whereHas('createdStore', fn (Builder $store): Builder => $store->whereIn('company_id', $companyIds));
    }

    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public function scopeVisibleFrom(Builder $query, User $user, int $branchId, ?int $storeId = null): Builder
    {
        abort_unless($user->is_super_admin || $user->canAccessBranch($branchId) || ($storeId !== null && $user->canAccessStore($storeId)), 403);
        $companyId = $storeId !== null
            ? Store::query()->whereKey($storeId)->value('company_id')
            : Branch::query()->whereKey($branchId)->value('company_id');

        return $query->whereHas('createdStore', fn (Builder $store): Builder => $store->where('company_id', $companyId));
    }

    /** @param array<string, mixed> $attributes */
    public function mutateMaster(array $attributes): void
    {
        $this->namedMutation = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedMutation = false;
        }
    }
}
