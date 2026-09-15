<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Models\User;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

final class PartyPayment extends Model
{
    protected $fillable = ['public_id', 'party_invoice_id', 'party_booking_id', 'branch_id', 'store_id', 'payment_method_id', 'method_code', 'method_type', 'amount', 'reference', 'evidence_reference', 'receipt_number', 'receipt_label', 'status', 'created_by', 'approved_by', 'approved_at', 'idempotency_key', 'payload_hash'];
    protected $casts = ['amount' => 'decimal:4', 'approved_at' => 'immutable_datetime'];
    protected static function booted(): void
    {
        self::creating(fn (self $payment): string => $payment->public_id ??= (string) Str::uuid());
        self::updating(fn (): never => throw new LogicException('Party payments are immutable; correct them by reference.'));
        self::deleting(fn (): never => throw new LogicException('Party payments are immutable; correct them by reference.'));
    }
    /** @return BelongsTo<PartyInvoice, $this> */
    public function invoice(): BelongsTo { return $this->belongsTo(PartyInvoice::class, 'party_invoice_id'); }
    /** @return BelongsTo<PartyBooking, $this> */
    public function booking(): BelongsTo { return $this->belongsTo(PartyBooking::class, 'party_booking_id'); }
    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }
    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
