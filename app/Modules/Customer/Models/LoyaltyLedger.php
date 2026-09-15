<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class LoyaltyLedger extends Model
{
    public $timestamps = false;

    protected $table = 'loyalty_ledger';

    protected $fillable = [
        'public_id', 'customer_id', 'branch_id', 'store_id', 'activity', 'event_type',
        'points', 'balance_before', 'balance_after', 'effective_at', 'expires_at',
        'source_type', 'source_id', 'source_reference', 'rule_key', 'rule_version',
        'reason', 'created_by', 'approval_record_id', 'idempotency_key', 'metadata', 'created_at',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'effective_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $entry): void {
            $entry->public_id ??= (string) Str::uuid();
        });
        self::updating(fn (): never => throw new LogicException('Loyalty ledger entries are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Loyalty ledger entries cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<ApprovalRecord, $this> */
    public function approvalRecord(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<LoyaltyPointAllocation, $this> */
    public function debitAllocations(): HasMany
    {
        return $this->hasMany(LoyaltyPointAllocation::class, 'debit_ledger_id');
    }

    /**
     * @param  Builder<LoyaltyLedger>  $query
     * @return Builder<LoyaltyLedger>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('effective_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<LoyaltyLedger>  $query
     * @return Builder<LoyaltyLedger>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
