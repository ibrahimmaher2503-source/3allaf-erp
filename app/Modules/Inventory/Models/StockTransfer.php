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

final class StockTransfer extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['purchase_invoice_id', 'transfer_number', 'source_store_id', 'destination_store_id', 'status', 'difference_status', 'reason_code', 'reason_notes', 'requested_by', 'approved_by', 'dispatched_by', 'received_by', 'approved_at', 'dispatched_at', 'received_at', 'idempotency_key', 'lock_version', 'notes'];

    protected $casts = ['approved_at' => 'datetime', 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'lock_version' => 'integer'];

    /** @return BelongsTo<Store, $this> */
    public function sourceStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }

    /** @return BelongsTo<Store, $this> */
    public function destinationStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    /** @return HasMany<StockTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function sourceStoreId(): ?int
    {
        return $this->source_store_id === null ? null : (int) $this->source_store_id;
    }
}
