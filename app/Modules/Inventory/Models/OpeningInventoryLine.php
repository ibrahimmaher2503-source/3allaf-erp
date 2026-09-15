<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class OpeningInventoryLine extends Model
{
    protected $fillable = ['product_id', 'store_id', 'quantity', 'unit_cost', 'total_value'];
    protected $casts = ['quantity' => 'decimal:6', 'unit_cost' => 'decimal:4', 'total_value' => 'decimal:4'];

    protected static function booted(): void
    {
        $assertDraft = static function (self $line): void {
            $document = $line->document()->first();
            if ($document !== null && $document->status !== 'draft') {
                throw new LogicException('Opening Inventory lines are immutable after approval; use a referenced reversal or adjustment.');
            }
        };
        self::saving($assertDraft);
        self::deleting($assertDraft);
    }

    public function document(): BelongsTo { return $this->belongsTo(OpeningInventoryDocument::class, 'opening_inventory_document_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
}
