<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use App\Modules\Purchasing\Support\SupplierBalance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseInvoice extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = [
        'invoice_number', 'supplier_id', 'purchase_order_id', 'store_id', 'supplier_reference',
        'invoice_date', 'currency_code', 'status', 'subtotal', 'tax_amount', 'discount_amount',
        'total_amount', 'idempotency_key', 'notes', 'lock_version', 'created_by', 'updated_by',
        'source_type', 'source_id', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by',
        'rejected_at', 'rejected_by', 'rejection_reason', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'lock_version' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<PurchaseInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class);
    }

    /** @return HasMany<PurchaseInvoiceCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceCharge::class);
    }

    /** @return HasMany<PurchaseInvoiceDistribution, $this> */
    public function distributions(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceDistribution::class);
    }

    /** @return HasMany<PurchaseReturn, $this> */
    public function supplierReturns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_invoice_id');
    }

    /** @return HasMany<SupplierPaymentAllocation, $this> */
    public function supplierPaymentAllocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }

    /** @return HasMany<SupplierAccountAdjustment, $this> */
    public function supplierAccountAdjustments(): HasMany
    {
        return $this->hasMany(SupplierAccountAdjustment::class);
    }

    /** @return numeric-string */
    public function paidAmount(): string
    {
        return app(SupplierBalance::class)->paidForInvoice($this);
    }

    /** @return numeric-string */
    public function outstandingAmount(): string
    {
        return app(SupplierBalance::class)->outstandingForInvoice($this);
    }

    public function paymentStatus(): string
    {
        return app(SupplierBalance::class)->paymentStatusForInvoice($this);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
