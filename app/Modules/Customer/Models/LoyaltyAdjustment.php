<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class LoyaltyAdjustment extends Model
{
    private bool $namedTransition = false;

    protected $fillable = [
        'public_id', 'customer_id', 'activity', 'points', 'reason', 'source_reference',
        'status', 'requested_by', 'approved_by', 'approved_at', 'approval_record_id',
        'branch_id', 'store_id', 'idempotency_key', 'lock_version',
    ];

    protected $casts = [
        'points' => 'integer',
        'approved_at' => 'immutable_datetime',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $adjustment): void {
            $adjustment->public_id ??= (string) Str::uuid();
        });
        self::updating(function (self $adjustment): void {
            if (in_array($adjustment->getOriginal('status'), ['approved', 'rejected', 'cancelled'], true) && ! $adjustment->namedTransition) {
                throw new LogicException('A decided loyalty adjustment is immutable.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Loyalty adjustment history cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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

    /** @param Builder<LoyaltyAdjustment> $query */
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

    /** @param array<string, mixed> $attributes */
    public function transition(array $attributes): void
    {
        $this->namedTransition = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedTransition = false;
        }
    }
}
