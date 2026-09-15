<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseInvoiceCharge extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['purchase_invoice_id', 'charge_type', 'amount', 'notes'];

    protected $casts = ['amount' => 'decimal:4'];

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    protected function approvedParent(): ?Model
    {
        return $this->invoice()->first();
    }
}
