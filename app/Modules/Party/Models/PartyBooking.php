<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerChild;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

final class PartyBooking extends Model
{
    protected $fillable = [
        'public_id', 'booking_number', 'customer_id', 'child_id', 'branch_id', 'store_id', 'party_date',
        'starts_at', 'ends_at', 'timezone', 'location', 'primary_contact', 'secondary_contact', 'notes',
        'responsibilities', 'resource_keys', 'status', 'change_reason', 'created_by', 'updated_by',
        'confirmed_by', 'confirmed_at', 'closed_at', 'closed_by', 'idempotency_key', 'payload_hash', 'lock_version',
    ];

    protected $casts = [
        'party_date' => 'date', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime',
        'responsibilities' => 'array', 'resource_keys' => 'array', 'confirmed_at' => 'immutable_datetime',
        'closed_at' => 'immutable_datetime', 'lock_version' => 'integer',
    ];

    protected static function booted(): void
    {
        self::creating(fn (self $booking): string => $booking->public_id ??= (string) Str::uuid());
        self::deleting(fn (): never => throw new LogicException('Party bookings preserve history and cannot be deleted.'));
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    /** @return BelongsTo<CustomerChild, $this> */
    public function child(): BelongsTo { return $this->belongsTo(CustomerChild::class, 'child_id'); }
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    /** @return HasMany<PartyInvoice, $this> */
    public function invoices(): HasMany { return $this->hasMany(PartyInvoice::class); }
    /** @return HasOne<PartyInvoice, $this> */
    public function invoice(): HasOne { return $this->hasOne(PartyInvoice::class, 'party_booking_id'); }
    /** @return HasMany<PartyOperatingOrder, $this> */
    public function operatingOrders(): HasMany { return $this->hasMany(PartyOperatingOrder::class); }

    /** @param Builder<PartyBooking> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }

    public function isClosed(): bool { return $this->status === 'closed'; }
}
