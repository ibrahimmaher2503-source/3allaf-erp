<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseInvoiceImportRow extends Model
{
    protected $fillable = [
        'purchase_invoice_import_batch_id', 'row_number', 'raw_data', 'mapped_data', 'errors', 'status', 'purchase_invoice_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'mapped_data' => 'array',
        'errors' => 'array',
    ];

    /** @return BelongsTo<PurchaseInvoiceImportBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceImportBatch::class, 'purchase_invoice_import_batch_id');
    }

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
