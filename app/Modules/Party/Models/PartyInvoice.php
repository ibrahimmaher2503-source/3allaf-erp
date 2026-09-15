<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

final class PartyInvoice extends Model
{
    private bool $finalMutation = false;

    protected $fillable = [
        'public_id', 'party_booking_id', 'invoice_number', 'final_invoice_number', 'final_receipt_number', 'state', 'subtotal',
        'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'wallet_applied_amount',
        'balance_due', 'credit_amount', 'currency_code', 'notes', 'created_by', 'updated_by',
        'finalized_by', 'finalized_at', 'idempotency_key', 'final_close_idempotency_key', 'lock_version',
    ];

    protected $casts = ['finalized_at' => 'immutable_datetime', 'lock_version' => 'integer'];

    protected static function booted(): void
    {
        self::creating(fn (self $invoice): string => $invoice->public_id ??= (string) Str::uuid());
        self::updating(function (self $invoice): void {
            if ($invoice->getOriginal('state') === 'final' && ! $invoice->finalMutation) {
                throw new LogicException('Final Party invoices are immutable; use a referenced correction.');
            }
        });
        self::deleting(fn (): never => throw new LogicException('Party invoice history cannot be deleted.'));
    }

    /** @return BelongsTo<PartyBooking, $this> */
    public function booking(): BelongsTo { return $this->belongsTo(PartyBooking::class, 'party_booking_id'); }
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo { return $this->booking->customer(); }
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo { return $this->booking->branch(); }
    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo { return $this->booking->store(); }
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    /** @return HasMany<PartyInvoiceLine, $this> */
    public function lines(): HasMany { return $this->hasMany(PartyInvoiceLine::class); }
    /** @return HasMany<PartyInvoiceLine, $this> */
    public function retailLines(): HasMany { return $this->lines()->where('line_type', 'retail'); }
    /** @return HasMany<PartyPayment, $this> */
    public function payments(): HasMany { return $this->hasMany(PartyPayment::class); }

    /** @param Builder<PartyInvoice> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('booking', fn (Builder $booking): Builder => $booking->visibleTo($user));
    }

    /** @param array<string, mixed> $attributes */
    public function mutateFinal(array $attributes): void
    {
        if ($this->state !== 'final') throw new InvalidArgumentException('Only a final Party invoice may use final mutation context.');
        $this->finalMutation = true;
        try { $this->fill($attributes)->save(); } finally { $this->finalMutation = false; }
    }
}
