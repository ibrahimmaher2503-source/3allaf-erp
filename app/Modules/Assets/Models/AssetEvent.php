<?php

declare(strict_types=1);

namespace App\Modules\Assets\Models;

use App\Models\User;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AssetEvent extends Model
{
    private bool $transitioning = false;
    protected $fillable = ['asset_id', 'branch_id', 'store_id', 'event_type', 'source_type', 'source_id', 'party_reference', 'assessment', 'responsible_user_id', 'cost_value', 'cost_currency', 'resulting_status', 'status', 'evidence_attachment_id', 'approval_record_id', 'correction_of_id', 'idempotency_key', 'lock_version', 'metadata'];
    protected $casts = ['cost_value' => 'decimal:2', 'metadata' => 'array', 'lock_version' => 'integer'];
    protected static function booted(): void
    {
        static::updating(function (self $event): void { if (! $event->transitioning) throw new LogicException('Asset events are immutable; use a referenced correction.'); });
        static::deleting(fn (): never => throw new LogicException('Asset event history is immutable.'));
    }
    public function asset(): BelongsTo { return $this->belongsTo(RentalAsset::class, 'asset_id'); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function responsibleUser(): BelongsTo { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function approvalRecord(): BelongsTo { return $this->belongsTo(ApprovalRecord::class); }
    /** @param array<string, mixed> $attributes */
    public function transition(array $attributes): void
    {
        $this->transitioning = true;
        try { $this->fill($attributes)->save(); } finally { $this->transitioning = false; }
    }
}
