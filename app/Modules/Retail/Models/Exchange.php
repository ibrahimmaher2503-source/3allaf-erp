<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Exchange extends Model
{
    protected $fillable = ['retail_return_id', 'exchange_number', 'status', 'replacement_value', 'difference_value', 'difference_direction'];
    protected $casts = ['replacement_value' => 'decimal:2', 'difference_value' => 'decimal:2'];
    public function retailReturn(): BelongsTo { return $this->belongsTo(RetailReturn::class); }
    public function lines(): HasMany { return $this->hasMany(ExchangeLine::class); }
}
