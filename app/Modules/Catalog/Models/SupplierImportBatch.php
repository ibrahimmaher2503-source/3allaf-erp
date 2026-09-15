<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierImportBatch extends Model
{
    protected $fillable = ['company_id', 'created_by', 'original_filename', 'storage_path', 'sha256', 'mode', 'status', 'headers', 'column_mapping', 'total_rows', 'valid_rows', 'invalid_rows', 'added_rows', 'approved_at'];

    protected $casts = ['headers' => 'array', 'column_mapping' => 'array', 'approved_at' => 'datetime'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(SupplierImportRow::class);
    }
}
