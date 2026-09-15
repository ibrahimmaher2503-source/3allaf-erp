<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UatBatch extends Model
{
    protected $fillable = ['company_id', 'batch_key', 'status', 'coverage'];
    protected $casts = ['coverage' => 'array'];
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function records(): HasMany { return $this->hasMany(UatRecord::class); }
}
