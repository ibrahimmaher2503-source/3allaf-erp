<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GiftCard extends Model
{
    protected $fillable = ['identifier', 'status', 'issued_value', 'balance', 'currency_code', 'holder_customer_id', 'branch_id', 'store_id', 'issued_by', 'source_type', 'source_id', 'source_reference', 'valid_from', 'valid_until', 'void_reason', 'voided_by', 'voided_at', 'idempotency_key', 'lock_version'];
    protected $casts = ['issued_value' => 'decimal:2', 'balance' => 'decimal:2', 'valid_from' => 'datetime', 'valid_until' => 'datetime', 'voided_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function holder(): BelongsTo { return $this->belongsTo(Customer::class, 'holder_customer_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function ledger(): HasMany { return $this->hasMany(GiftCardLedger::class); }
    public function printEvents(): HasMany { return $this->hasMany(GiftCardPrintEvent::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
