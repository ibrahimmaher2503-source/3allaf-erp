<?php

declare(strict_types=1);

namespace App\Modules\Assets\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetReservation extends Model
{
    protected $fillable = ['asset_id', 'branch_id', 'store_id', 'source_type', 'source_id', 'source_reference', 'starts_at', 'ends_at', 'timezone', 'buffer_before_minutes', 'buffer_after_minutes', 'status', 'reserved_by', 'idempotency_key', 'lock_version'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'buffer_before_minutes' => 'integer', 'buffer_after_minutes' => 'integer', 'lock_version' => 'integer'];
    public function asset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'asset_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function reserver(): BelongsTo { return $this->belongsTo(User::class, 'reserved_by'); }
    public function scopeActive(Builder $query): Builder { return $query->whereIn('status', ['reserved', 'fulfilled']); }
}
