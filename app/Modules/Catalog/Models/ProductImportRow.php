<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ProductImportRow extends Model
{
    protected $fillable = [
        'product_import_batch_id', 'row_number', 'raw_data', 'mapped_data', 'errors', 'status', 'product_id',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'mapped_data' => 'array',
        'errors' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductImportBatch::class, 'product_import_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
