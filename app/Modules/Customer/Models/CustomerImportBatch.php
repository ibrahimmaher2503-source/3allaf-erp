<?php

namespace App\Modules\Customer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerImportBatch extends Model
{
    protected $fillable = ['company_id', 'created_by', 'original_filename', 'mode', 'status', 'headers', 'total_rows', 'valid_rows', 'invalid_rows', 'added_rows', 'duplicate_existing_rows', 'duplicate_file_rows', 'approved_at'];

    protected $casts = ['headers' => 'array', 'approved_at' => 'datetime'];

    public function rows(): HasMany
    {
        return $this->hasMany(CustomerImportRow::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
