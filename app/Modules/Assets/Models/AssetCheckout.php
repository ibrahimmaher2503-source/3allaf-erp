<?php

declare(strict_types=1);

namespace App\Modules\Assets\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AssetCheckout extends Model
{
    protected $fillable = ['asset_id', 'reservation_id', 'branch_id', 'store_id', 'source_reference', 'checked_out_at', 'location_before', 'location_after', 'condition_before', 'notes', 'responsible_user_id', 'idempotency_key'];
    protected $casts = ['checked_out_at' => 'datetime'];
    public function asset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'asset_id'); }
    public function reservation(): BelongsTo { return $this->belongsTo(AssetReservation::class, 'reservation_id'); }
    public function responsibleUser(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
}
