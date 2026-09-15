<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseInvoiceLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = [
        'purchase_invoice_id', 'purchase_order_line_id', 'product_id', 'product_unit_id', 'entered_quantity', 'conversion_factor_snapshot', 'entered_unit_price', 'quantity', 'quantity_received',
        'unit_cost', 'base_consumer_price', 'previous_product_cost', 'previous_base_consumer_price', 'discount_type', 'discount_value', 'discount_amount', 'tax_rate', 'tax_code',
        'tax_amount', 'subtotal', 'line_total', 'allocated_charge_amount', 'inventory_unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'entered_quantity' => 'decimal:6',
        'conversion_factor_snapshot' => 'decimal:6',
        'entered_unit_price' => 'decimal:4',
        'quantity_received' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'base_consumer_price' => 'decimal:3',
        'previous_product_cost' => 'decimal:4',
        'previous_base_consumer_price' => 'decimal:3',
        'discount_value' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'line_total' => 'decimal:4',
        'allocated_charge_amount' => 'decimal:4',
        'inventory_unit_cost' => 'decimal:6',
    ];

    /** @return BelongsTo<PurchaseInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductUnit, $this> */
    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function supplierReturnLines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class, 'purchase_invoice_line_id');
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceDistribution::class);
    }

    protected function approvedParent(): ?Model
    {
        return $this->invoice()->first();
    }
}
