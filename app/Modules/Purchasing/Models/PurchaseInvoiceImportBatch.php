<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseInvoiceImportBatch extends Model
{
    protected $fillable = [
        'created_by', 'original_filename', 'storage_path', 'mime_type', 'size_bytes', 'sha256', 'mode', 'status',
        'headers', 'total_rows', 'valid_rows', 'invalid_rows', 'retry_of_id', 'approved_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'approved_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<PurchaseInvoiceImportRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceImportRow::class);
    }

    /** @return BelongsTo<self, $this> */
    public function retryOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }
}
