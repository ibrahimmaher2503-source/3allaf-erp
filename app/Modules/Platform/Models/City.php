<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class City extends Model
{
    protected $fillable = ['governorate_id', 'company_id', 'code', 'name_ar', 'name_en', 'name_ar_normalized', 'name_en_normalized', 'sort_order', 'status', 'created_by', 'updated_by', 'lock_version'];

    protected $casts = ['sort_order' => 'integer', 'lock_version' => 'integer'];

    protected static function booted(): void
    {
        self::saving(function (self $city): void {
            $city->name_ar_normalized = self::normalizeName($city->name_ar);
            $city->name_en_normalized = self::normalizeName($city->name_en);
            if ($city->exists && $city->isDirty(['company_id', 'governorate_id'])) {
                throw new LogicException('City ownership is immutable.');
            }
        });
        self::deleting(function (self $city): void {
            if ($city->customers()->exists()) {
                throw new LogicException(__('A city referenced by customers cannot be deleted. Deactivate it instead.'));
            }
        });
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(fn (Builder $scope) => $scope->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public static function normalizeName(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
    }
}
