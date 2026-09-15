<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseReturn extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = [
        'return_number', 'supplier_id', 'purchase_invoice_id', 'store_id', 'reason_id', 'return_date',
        'status', 'subtotal', 'total_amount', 'idempotency_key', 'lock_version', 'notes',
        'created_by', 'updated_by', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by',
        'rejected_at', 'rejected_by', 'rejection_reason', 'reversed_at', 'reversed_by', 'reversal_reason', 'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected $casts = [
        'return_date' => 'date',
        'subtotal' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'lock_version' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'reversed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<SupplierReturnReason, $this> */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(SupplierReturnReason::class, 'reason_id');
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
