<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Enums\OfflineTransactionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OfflineTransaction extends Model
{
    protected $fillable = ['offline_device_id', 'user_id', 'branch_id', 'store_id', 'shift_id', 'offline_sync_batch_id', 'server_sale_id', 'local_uuid', 'state', 'policy_version', 'schema_version', 'payload_hash', 'canonical_payload', 'captured_at', 'price_cached_at', 'expires_at', 'synced_at'];

    protected $casts = ['state' => OfflineTransactionState::class, 'canonical_payload' => 'array', 'captured_at' => 'datetime', 'price_cached_at' => 'datetime', 'expires_at' => 'datetime', 'synced_at' => 'datetime'];

    /** @return BelongsTo<OfflineDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(OfflineDevice::class, 'offline_device_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<PosShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
    }
}
