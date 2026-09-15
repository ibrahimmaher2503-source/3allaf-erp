<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Models\PriceList;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PurchaseInvoiceDistribution extends Model
{
    protected $fillable = ['purchase_invoice_id', 'purchase_invoice_line_id', 'destination_store_id', 'quantity', 'price_list_id', 'effective_selling_price', 'price_is_override'];

    protected $casts = ['quantity' => 'decimal:6', 'effective_selling_price' => 'decimal:3', 'price_is_override' => 'boolean'];

    public function invoice(): BelongsTo { return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id'); }
    public function line(): BelongsTo { return $this->belongsTo(PurchaseInvoiceLine::class, 'purchase_invoice_line_id'); }
    public function destination(): BelongsTo { return $this->belongsTo(Store::class, 'destination_store_id'); }
    public function priceList(): BelongsTo { return $this->belongsTo(PriceList::class); }
}
