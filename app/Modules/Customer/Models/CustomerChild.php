<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class CustomerChild extends Model
{
    private bool $namedMutation = false;

    protected $fillable = [
        'public_id', 'customer_id', 'name_ar', 'name_en', 'birth_date', 'purpose',
        'consent_status', 'consent_wording_version', 'consent_wording_text', 'status',
        'created_by', 'updated_by', 'created_branch_id', 'created_store_id', 'lock_version',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $child): void {
            $child->public_id ??= (string) Str::uuid();
        });
        self::updating(function (self $child): void {
            if (! $child->namedMutation) {
                throw new LogicException('Child profile history requires a named mutation.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Child history is preserved; archive the profile instead.'));
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

    /** @return BelongsTo<Branch, $this> */
    public function createdBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'created_branch_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function createdStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'created_store_id');
    }

    /** @param Builder<CustomerChild> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('created_branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('created_store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }

    /** @param array<string, mixed> $attributes */
    public function mutateProfile(array $attributes): void
    {
        $this->namedMutation = true;
        try {
            $this->fill($attributes)->save();
        } finally {
            $this->namedMutation = false;
        }
    }
}
