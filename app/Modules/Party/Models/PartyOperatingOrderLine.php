<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Modules\Assets\Models\AssetCheckout;
use App\Modules\Assets\Models\AssetEvent;
use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\AssetReturn;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PartyOperatingOrderLine extends Model
{
    protected $fillable = ['party_operating_order_id', 'party_invoice_line_id', 'line_type', 'product_id', 'rental_asset_id', 'resource_key', 'asset_reservation_id', 'asset_checkout_id', 'asset_return_id', 'asset_inspection_event_id', 'description', 'planned_quantity', 'issued_quantity', 'consumed_quantity', 'returned_quantity', 'unit', 'responsible_user_id', 'change_reason'];
    protected $casts = ['planned_quantity' => 'decimal:6', 'issued_quantity' => 'decimal:6', 'consumed_quantity' => 'decimal:6', 'returned_quantity' => 'decimal:6'];
    protected static function booted(): void
    {
        self::updating(function (self $line): void {
            if ($line->order()->where('status', 'completed')->exists()) throw new LogicException('Completed Party order lines are immutable.');
        });
        self::deleting(fn (): never => throw new LogicException('Party operating-order lines cannot be deleted.'));
    }
    /** @return BelongsTo<PartyOperatingOrder, $this> */
    public function order(): BelongsTo { return $this->belongsTo(PartyOperatingOrder::class, 'party_operating_order_id'); }
    /** @return BelongsTo<PartyInvoiceLine, $this> */
    public function invoiceLine(): BelongsTo { return $this->belongsTo(PartyInvoiceLine::class, 'party_invoice_line_id'); }
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** @return BelongsTo<RentalAsset, $this> */
    public function rentalAsset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'rental_asset_id'); }
    /** @return BelongsTo<AssetReservation, $this> */
    public function assetReservation(): BelongsTo { return $this->belongsTo(AssetReservation::class, 'asset_reservation_id'); }
    /** @return BelongsTo<AssetCheckout, $this> */
    public function assetCheckout(): BelongsTo { return $this->belongsTo(AssetCheckout::class, 'asset_checkout_id'); }
    /** @return BelongsTo<AssetReturn, $this> */
    public function assetReturn(): BelongsTo { return $this->belongsTo(AssetReturn::class, 'asset_return_id'); }
    /** @return BelongsTo<AssetEvent, $this> */
    public function assetInspectionEvent(): BelongsTo { return $this->belongsTo(AssetEvent::class, 'asset_inspection_event_id'); }
}
