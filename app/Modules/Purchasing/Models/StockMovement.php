<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'store_id', 'movement_type', 'quantity', 'unit_cost', 'total_cost', 'consumed_cost',
        'source_type', 'source_id', 'source_line_id', 'idempotency_key', 'posted_at', 'reversal_of_id', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'consumed_cost' => 'decimal:4',
        'posted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        self::updating(fn (): never => throw new LogicException('Posted stock movements are immutable; post a referenced reversal movement.'));
        self::deleting(fn (): never => throw new LogicException('Posted stock movements are append-only and cannot be deleted.'));
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
