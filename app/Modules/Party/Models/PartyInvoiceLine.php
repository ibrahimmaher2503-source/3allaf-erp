<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Modules\Assets\Models\AssetReservation;
use App\Modules\Assets\Models\RentalAsset;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PartyInvoiceLine extends Model
{
    private bool $draftMutation = false;
    protected $fillable = ['party_invoice_id', 'line_number', 'line_type', 'product_id', 'rental_asset_id', 'asset_reservation_id', 'description_ar', 'description_en', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total', 'resource_key', 'source_reference'];
    protected $casts = ['quantity' => 'decimal:6', 'unit_price' => 'decimal:4', 'discount_amount' => 'decimal:4', 'tax_amount' => 'decimal:4', 'line_total' => 'decimal:4'];
    protected static function booted(): void
    {
        self::updating(function (self $line): void {
            if ($line->invoice()->where('state', 'final')->exists() && ! $line->draftMutation) throw new LogicException('Final Party invoice lines are immutable.');
        });
        self::deleting(function (self $line): void {
            if ($line->invoice()->where('state', 'final')->exists() || ! $line->draftMutation) throw new LogicException('Party invoice lines cannot be deleted outside a named draft mutation.');
        });
    }
    /** @return BelongsTo<PartyInvoice, $this> */
    public function invoice(): BelongsTo { return $this->belongsTo(PartyInvoice::class, 'party_invoice_id'); }
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** @return BelongsTo<RentalAsset, $this> */
    public function rentalAsset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'rental_asset_id'); }
    /** @return BelongsTo<AssetReservation, $this> */
    public function assetReservation(): BelongsTo { return $this->belongsTo(AssetReservation::class, 'asset_reservation_id'); }

    /** @param array<string, mixed> $attributes */
    public function mutateDraft(array $attributes): void
    {
        $this->draftMutation = true;
        try { $this->fill($attributes)->save(); } finally { $this->draftMutation = false; }
    }

    public function deleteDraft(): void
    {
        $this->draftMutation = true;
        try { $this->delete(); } finally { $this->draftMutation = false; }
    }
}
