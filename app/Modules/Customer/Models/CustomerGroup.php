<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class CustomerGroup extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id', 'code',
        'name_ar',
        'name_en', 'sort_order',
        'status',
        'created_by',
        'updated_by',
        'lock_version',
    ];

    protected $casts = [
        'lock_version' => 'integer', 'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        self::updating(function (self $group): void {
            if ($group->isDirty('company_id')) {
                throw new LogicException('Customer group company ownership is immutable.');
            }
        });

        self::deleting(fn (): never => throw new LogicException('Customer groups are preserved; use an inactive state.'));
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CustomerGroup, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<CustomerGroup, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<CustomerGroup>  $query
     * @return Builder<CustomerGroup>
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * @param  Builder<CustomerGroup>  $query
     * @return Builder<CustomerGroup>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
