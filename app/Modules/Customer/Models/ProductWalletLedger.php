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

/**
 * @property string $public_id
 * @property string $entry_type
 */
final class ProductWalletLedger extends Model
{
    private bool $appendMutation = false;

    protected $table = 'product_wallet_ledger';

    public $timestamps = false;

    protected $fillable = [
        'public_id', 'customer_id', 'branch_id', 'store_id', 'entry_type', 'amount', 'currency_code',
        'balance_before', 'balance_after', 'source_type', 'source_id', 'source_line_id',
        'idempotency_key', 'payload_hash', 'reference', 'reason', 'reversal_of_id', 'correction_of_id',
        'created_by', 'metadata', 'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'balance_before' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $entry): void {
            if (! $entry->appendMutation) {
                throw new LogicException('Product Wallet ledger entries may only be appended by a named wallet action.');
            }
            $entry->public_id ??= (string) Str::uuid();
        });
        self::updating(fn (): never => throw new LogicException('Product Wallet ledger entries are append-only.'));
        self::deleting(fn (): never => throw new LogicException('Product Wallet ledger entries are append-only.'));
    }

    /** @param array<string, mixed> $attributes */
    public static function appendEntry(array $attributes): self
    {
        $entry = new self();
        $entry->appendMutation = true;
        try {
            $entry->fill($attributes)->save();
        } finally {
            $entry->appendMutation = false;
        }

        return $entry;
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<self, $this> */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    /** @param Builder<ProductWalletLedger> $query */
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

    /** @param Builder<ProductWalletLedger> $query */
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<ProductWalletLedger>  $query
     * @return Builder<ProductWalletLedger>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
