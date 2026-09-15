<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CatalogReferenceImportBatch extends Model
{
    protected $fillable = ['type', 'mode', 'created_by', 'original_filename', 'storage_path', 'sha256', 'status', 'headers', 'total_rows', 'valid_rows', 'invalid_rows', 'approved_at', 'approved_by'];
    protected $casts = ['headers' => 'array', 'approved_at' => 'datetime'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function rows(): HasMany { return $this->hasMany(CatalogReferenceImportRow::class); }
}
