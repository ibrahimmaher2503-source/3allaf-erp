<?php

declare(strict_types=1);

namespace App\Modules\Party\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class PartyConsumableIssueLine extends Model
{
    private bool $movementMutation = false;
    protected $fillable = ['party_consumable_issue_id', 'party_operating_order_line_id', 'product_id', 'quantity', 'stock_movement_id'];
    protected $casts = ['quantity' => 'decimal:6'];
    protected static function booted(): void
    {
        self::updating(function (self $line): void { if (! $line->movementMutation) throw new LogicException('Party issue lines are immutable.'); });
        self::deleting(fn (): never => throw new LogicException('Party issue lines are immutable.'));
    }
    /** @return BelongsTo<PartyConsumableIssue, $this> */
    public function issue(): BelongsTo { return $this->belongsTo(PartyConsumableIssue::class, 'party_consumable_issue_id'); }
    /** @return BelongsTo<PartyOperatingOrderLine, $this> */
    public function orderLine(): BelongsTo { return $this->belongsTo(PartyOperatingOrderLine::class, 'party_operating_order_line_id'); }
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo { return $this->belongsTo(StockMovement::class); }

    public function attachMovement(int $movementId): void
    {
        $this->movementMutation = true;
        try { $this->update(['stock_movement_id' => $movementId]); } finally { $this->movementMutation = false; }
    }
}
