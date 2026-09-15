<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierAccountAdjustment extends Model
{
    protected $fillable = [
        'supplier_id', 'purchase_invoice_id', 'adjustment_date', 'currency_code', 'direction',
        'amount', 'reason', 'reference', 'status', 'idempotency_key', 'payload_hash',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'amount' => 'decimal:4',
        'approved_at' => 'datetime',
    ];

    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }

    public function purchaseInvoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class); }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
