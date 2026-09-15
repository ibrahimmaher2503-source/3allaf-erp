<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class StockTransferReceipt extends Model
{
    protected $fillable = ['stock_transfer_id', 'receipt_number', 'status', 'received_by', 'received_at', 'difference_type', 'difference_reason', 'idempotency_key', 'payload_hash'];

    protected $casts = ['received_at' => 'datetime'];

    protected static function booted(): void
    {
        self::updating(fn () => throw new LogicException('Stock transfer receipt events are immutable.'));
        self::deleting(fn () => throw new LogicException('Stock transfer receipt events are immutable.'));
    }

    /** @return BelongsTo<StockTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<StockTransferReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferReceiptLine::class);
    }
}
