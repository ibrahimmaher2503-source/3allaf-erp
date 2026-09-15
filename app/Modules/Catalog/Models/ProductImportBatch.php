<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class ProductImportBatch extends Model
{
    protected $fillable = [
        'created_by', 'original_filename', 'storage_path', 'mime_type', 'size_bytes',
        'sha256', 'mode', 'status', 'headers', 'column_mapping', 'total_rows', 'valid_rows', 'invalid_rows', 'approved_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'column_mapping' => 'array',
        'approved_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ProductImportRow::class);
    }
}
