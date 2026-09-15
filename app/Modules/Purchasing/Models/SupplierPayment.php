<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\CashControl\Models\CashAccount;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_id', 'payment_method_id', 'cash_account_id', 'payment_date', 'currency_code',
        'amount', 'reference', 'evidence_reference', 'notes', 'status', 'idempotency_key',
        'payload_hash', 'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'cash_account_id' => 'integer',
        'payment_date' => 'date',
        'amount' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    public function paymentMethod(): BelongsTo { return $this->belongsTo(PaymentMethod::class); }

    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }

    public function allocations(): HasMany { return $this->hasMany(SupplierPaymentAllocation::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
