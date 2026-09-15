<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class PartyOperatingOrder extends Model
{
    protected $fillable = ['public_id', 'order_number', 'party_booking_id', 'party_invoice_id', 'branch_id', 'store_id', 'status', 'notes', 'created_by', 'released_by', 'released_at', 'completed_by', 'completed_at', 'idempotency_key', 'lock_version'];
    protected $casts = ['released_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'lock_version' => 'integer'];
    protected static function booted(): void
    {
        self::creating(fn (self $order): string => $order->public_id ??= (string) Str::uuid());
        self::updating(function (self $order): void {
            if ($order->getOriginal('status') === 'completed') throw new LogicException('Completed Party operating orders are immutable.');
        });
        self::deleting(fn (): never => throw new LogicException('Party operating-order history cannot be deleted.'));
    }
    /** @return BelongsTo<PartyBooking, $this> */
    public function booking(): BelongsTo { return $this->belongsTo(PartyBooking::class, 'party_booking_id'); }
    /** @return BelongsTo<PartyInvoice, $this> */
    public function invoice(): BelongsTo { return $this->belongsTo(PartyInvoice::class, 'party_invoice_id'); }
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** @return HasMany<PartyOperatingOrderLine, $this> */
    public function lines(): HasMany { return $this->hasMany(PartyOperatingOrderLine::class); }
    /** @return HasMany<PartyConsumableIssue, $this> */
    public function consumableIssues(): HasMany { return $this->hasMany(PartyConsumableIssue::class); }

    /** @param Builder<PartyOperatingOrder> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
