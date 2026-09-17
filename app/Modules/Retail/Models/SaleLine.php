<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Concerns\GuardsApprovedParent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaleLine extends Model
{
    use GuardsApprovedParent;

    protected $fillable = ['sale_id', 'product_id', 'product_unit_id', 'entered_quantity', 'conversion_factor_snapshot', 'entered_unit_price', 'price_list_id', 'customer_product_price_id', 'price_source', 'minimum_price_snapshot', 'line_number', 'item_code', 'name_ar', 'name_en', 'variant_snapshot', 'quantity', 'unit_price', 'reference_price', 'is_open_price', 'open_price_authorized_by', 'open_price_approval_record_id', 'open_price_minimum_snapshot', 'open_price_maximum_snapshot', 'open_price_reason', 'gross_amount', 'discount_amount', 'discount_type', 'discount_rate', 'discount_reason', 'discount_applied_by', 'discount_replaced_by', 'discount_replaced_at', 'discount_approval_record_id', 'allocated_invoice_discount', 'net_amount', 'consumed_cost', 'stock_movement_id'];

    protected $casts = ['variant_snapshot' => 'array', 'entered_quantity' => 'decimal:6', 'conversion_factor_snapshot' => 'decimal:6', 'entered_unit_price' => 'decimal:4', 'minimum_price_snapshot' => 'decimal:4', 'quantity' => 'decimal:6', 'unit_price' => 'decimal:4', 'reference_price' => 'decimal:4', 'open_price_minimum_snapshot' => 'decimal:4', 'open_price_maximum_snapshot' => 'decimal:4', 'gross_amount' => 'decimal:2', 'discount_amount' => 'decimal:2', 'allocated_invoice_discount' => 'decimal:2', 'discount_rate' => 'decimal:2', 'discount_replaced_at' => 'datetime', 'is_open_price' => 'boolean', 'net_amount' => 'decimal:2', 'consumed_cost' => 'decimal:4'];

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    /** @return BelongsTo<ApprovalRecord, $this> */
    public function openPriceApproval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class, 'open_price_approval_record_id');
    }

    /** @return BelongsTo<ApprovalRecord, $this> */
    public function discountApproval(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class, 'discount_approval_record_id');
    }

    protected function approvedParent(): ?Model
    {
        return $this->sale()->first();
    }
}
