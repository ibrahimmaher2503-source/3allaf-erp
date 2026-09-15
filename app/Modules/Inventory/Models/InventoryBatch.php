<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class InventoryBatch extends Model
{
    protected $fillable = ['product_id', 'batch_number', 'production_date', 'expiry_date', 'status'];

    protected $casts = ['production_date' => 'immutable_date', 'expiry_date' => 'immutable_date'];

    protected static function booted(): void
    {
        self::saving(function (self $batch): void {
            $batch->batch_number = trim((string) $batch->batch_number);
            if ($batch->batch_number === '') {
                throw new InvalidArgumentException(__('A batch number is required.'));
            }
            if ($batch->production_date !== null && $batch->expiry_date !== null && $batch->production_date->isAfter($batch->expiry_date)) {
                throw new InvalidArgumentException(__('Batch production date cannot be after its expiry date.'));
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'batch_id');
    }
}
