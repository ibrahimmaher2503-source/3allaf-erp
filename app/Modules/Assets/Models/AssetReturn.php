<?php

declare(strict_types=1);

namespace App\Modules\Assets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetReturn extends Model
{
    protected $fillable = ['asset_id', 'checkout_id', 'branch_id', 'store_id', 'returned_at', 'location_after', 'condition_after', 'outcome', 'notes', 'inspected_by', 'idempotency_key'];
    protected $casts = ['returned_at' => 'datetime'];
    public function asset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'asset_id'); }
    public function checkout(): BelongsTo { return $this->belongsTo(AssetCheckout::class, 'checkout_id'); }
    public function inspector(): BelongsTo { return $this->belongsTo(User::class, 'inspected_by'); }
}
