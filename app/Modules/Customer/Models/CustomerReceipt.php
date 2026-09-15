<?php

declare(strict_types=1);

namespace App\Modules\Customer\Models;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class CustomerReceipt extends Model
{
    protected $fillable = ['public_id', 'customer_id', 'payment_method_id', 'cash_account_id', 'receipt_date', 'amount', 'reference', 'evidence_reference', 'notes', 'status', 'created_by', 'approved_by', 'approved_at', 'idempotency_key', 'payload_hash'];

    protected $casts = ['receipt_date' => 'date', 'amount' => 'decimal:4', 'approved_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        self::creating(fn (self $receipt): string => $receipt->public_id ??= (string) Str::uuid());
        self::updating(fn (): never => throw new LogicException('Customer receipts are immutable; correct them by reference.'));
        self::deleting(fn (): never => throw new LogicException('Customer receipts are immutable; correct them by reference.'));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerReceiptAllocation::class);
    }
}
