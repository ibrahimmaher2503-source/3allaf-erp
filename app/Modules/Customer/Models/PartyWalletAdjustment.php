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
use Illuminate\Support\Str;
use LogicException;

final class PartyWalletAdjustment extends Model
{
    private bool $namedTransition = false;

    protected $fillable = [
        'public_id', 'customer_id', 'branch_id', 'store_id', 'operation', 'amount', 'target_ledger_id',
        'source_type', 'source_id', 'source_line_id', 'source_reference', 'reason', 'status',
        'requested_by', 'approved_by', 'approved_at', 'decision_note', 'approval_record_id',
        'idempotency_key', 'payload_hash', 'lock_version', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'approved_at' => 'immutable_datetime',
        'lock_version' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            $adjustment->public_id ??= (string) Str::uuid();
        });
        static::updating(function (self $adjustment): void {
            if (in_array($adjustment->getOriginal('status'), ['approved', 'rejected', 'cancelled'], true) && ! $adjustment->namedTransition) {
                throw new LogicException('A decided Party Wallet adjustment is immutable.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Party Wallet adjustment history cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<PartyWalletLedger, $this> */
    public function targetLedger(): BelongsTo
    {
        return $this->belongsTo(PartyWalletLedger::class, 'target_ledger_id');
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

    /** @param Builder<PartyWalletAdjustment> $query */
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
