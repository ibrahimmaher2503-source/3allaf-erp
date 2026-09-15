<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class StockTransferReceiptLine extends Model
{
    protected $fillable = ['stock_transfer_receipt_id', 'stock_transfer_line_id', 'quantity_received', 'difference_quantity'];

    protected $casts = ['quantity_received' => 'decimal:6', 'difference_quantity' => 'decimal:6'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Stock transfer receipt lines are immutable.'));
        self::deleting(fn () => throw new LogicException('Stock transfer receipt lines are immutable.'));
    }

    /** @return BelongsTo<StockTransferReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockTransferReceipt::class, 'stock_transfer_receipt_id');
    }

    /** @return BelongsTo<StockTransferLine, $this> */
    public function transferLine(): BelongsTo
    {
        return $this->belongsTo(StockTransferLine::class, 'stock_transfer_line_id');
    }
}
