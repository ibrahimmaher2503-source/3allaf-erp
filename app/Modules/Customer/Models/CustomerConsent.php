<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class CustomerConsent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'purpose', 'status', 'captured_at', 'captured_by', 'source',
        'wording_version', 'wording_text', 'retention_until', 'branch_id', 'store_id',
        'idempotency_key', 'created_at',
    ];

    protected $casts = [
        'captured_at' => 'immutable_datetime',
        'retention_until' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Consent history is append-only.'));
        self::deleting(fn (): never => throw new LogicException('Consent history cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
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

    /** @param Builder<CustomerConsent> $query */
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
