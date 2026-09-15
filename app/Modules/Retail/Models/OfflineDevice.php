<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OfflineDevice extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'store_id', 'shift_id', 'name', 'token_hash', 'policy_version', 'schema_version', 'expires_at', 'revoked_at'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];

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
