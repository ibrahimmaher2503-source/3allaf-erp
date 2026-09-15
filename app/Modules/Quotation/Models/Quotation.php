<?php

declare(strict_types=1);

namespace App\Modules\Quotation\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class Quotation extends Model
{
    private bool $mutating = false;
    protected $fillable = ['public_id', 'quotation_number', 'activity_type', 'customer_id', 'branch_id', 'store_id', 'valid_until', 'status', 'currency_code', 'terms', 'notes', 'subtotal', 'total', 'source_type', 'source_id', 'source_reference', 'created_by', 'updated_by', 'idempotency_key', 'lock_version'];
    protected $casts = ['valid_until' => 'date', 'subtotal' => 'decimal:2', 'total' => 'decimal:2', 'lock_version' => 'integer'];
    protected static function booted(): void
    {
        static::creating(fn (self $quotation): ?string => $quotation->public_id ??= (string) Str::uuid());
        static::updating(function (self $quotation): void { if (! $quotation->mutating && in_array($quotation->getOriginal('status'), ['accepted', 'rejected', 'expired', 'cancelled'], true)) throw new LogicException('Terminal quotations are immutable.'); });
    }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function lines(): HasMany { return $this->hasMany(QuotationLine::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void { $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id')); });
    }
    public function mutate(array $attributes): void
    {
        $this->mutating = true;
        try { $this->fill($attributes)->save(); } finally { $this->mutating = false; }
    }
}
