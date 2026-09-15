<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class SupplierGroup extends Model
{
    protected $fillable = [
        'company_id', 'parent_id', 'code', 'name_ar', 'name_en', 'sort_order', 'status',
        'created_by', 'updated_by', 'lock_version',
    ];

    protected $casts = ['lock_version' => 'integer', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        self::updating(function (self $group): void {
            if ($group->isDirty('company_id')) {
                throw new LogicException('Supplier group company ownership is immutable.');
            }
        });

        self::deleting(fn (): never => throw new LogicException('Supplier groups are preserved; use an inactive state.'));
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<SupplierGroup, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<SupplierGroup, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Supplier, $this> */
    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<SupplierGroup> $query */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    /** @param Builder<SupplierGroup> $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
