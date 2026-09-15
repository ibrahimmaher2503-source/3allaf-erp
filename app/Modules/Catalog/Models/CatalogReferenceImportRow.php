<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogReferenceImportRow extends Model
{
    protected $fillable = ['catalog_reference_import_batch_id', 'row_number', 'raw_data', 'errors', 'status'];
    protected $casts = ['raw_data' => 'array', 'errors' => 'array'];

    public function batch(): BelongsTo { return $this->belongsTo(CatalogReferenceImportBatch::class, 'catalog_reference_import_batch_id'); }
}
