<?php

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'store_id',
        'branch_id',
        'status',
        'receiving_status',
        'order_date',
        'expected_delivery_date',
        'payment_terms',
        'notes',
        'cancel_reason',
        'subtotal',
        'tax_amount',
        'total_amount',
        'lock_version',
        'created_by',
        'updated_by',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'cancelled_at',
        'cancelled_by',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'lock_version' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id')->orderBy('line_number');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'purchase_order_id')->latest('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PurchaseOrderDocument::class, 'purchase_order_id')->latest('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partially_received', 'received'], true);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }

    public function isClosable(): bool
    {
        return in_array($this->status, ['approved', 'partially_received', 'received'], true);
    }

    public function receivingState(): string
    {
        return match ($this->status) {
            'partially_received' => 'partially_received',
            'received' => 'fully_received',
            default => $this->receiving_status ?: 'not_received',
        };
    }

    public function nextAction(): string
    {
        return match (true) {
            $this->status === 'draft' => 'Submit for Review',
            $this->status === 'submitted' => 'Procurement Manager Approve',
            $this->isApproved() && $this->receivingState() !== 'fully_received' => 'Ready to Convert to Purchase Invoice',
            $this->isApproved() => 'Fully Received',
            default => 'No available action',
        };
    }

    public function workflowStatusLabel(): string
    {
        return match (true) {
            $this->status === 'draft' => 'Draft',
            $this->status === 'submitted' => 'Submitted for Review',
            $this->isApproved() => 'Procurement Manager Approved',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }
}
