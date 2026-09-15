<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class InventoryAdjustment extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['adjustment_number', 'store_id', 'adjustment_type', 'status', 'reason_code', 'reason_notes', 'allow_negative', 'created_by', 'submitted_by', 'approved_by', 'reversed_by', 'reversal_of_id', 'submitted_at', 'approved_at', 'reversed_at', 'reversal_reason', 'idempotency_key', 'lock_version', 'notes'];

    protected $casts = ['allow_negative' => 'boolean', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'reversed_at' => 'datetime', 'lock_version' => 'integer'];

    /** @return BelongsTo<Store, $this> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return HasMany<InventoryAdjustmentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentLine::class);
    }

    /** @return BelongsTo<self, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
