<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PriceList extends Model
{
    protected $fillable = ['company_id', 'list_number', 'code', 'name_ar', 'name_en', 'percentage_increase', 'status', 'effective_from', 'effective_to', 'notes', 'created_by', 'updated_by'];

    protected $casts = ['list_number' => 'integer', 'percentage_increase' => 'decimal:4', 'effective_from' => 'date', 'effective_to' => 'date'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<PriceVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PriceVersion::class);
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(ProductPriceOverride::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function isBase(): bool
    {
        return $this->list_number === 0;
    }

    public function isEffective(?\DateTimeInterface $at = null): bool
    {
        $at = Carbon::instance($at ?? now())->startOfDay();

        return $this->status === 'active' && ($this->effective_from === null || $this->effective_from->lte($at)) && ($this->effective_to === null || $this->effective_to->gte($at));
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
