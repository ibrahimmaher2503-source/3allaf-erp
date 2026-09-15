<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierPaymentAllocation extends Model
{
    protected $fillable = ['supplier_payment_id', 'purchase_invoice_id', 'amount'];

    protected $casts = ['amount' => 'decimal:4'];

    public function payment(): BelongsTo { return $this->belongsTo(SupplierPayment::class, 'supplier_payment_id'); }

    public function purchaseInvoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class); }
}
