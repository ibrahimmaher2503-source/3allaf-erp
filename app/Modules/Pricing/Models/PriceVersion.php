<?php

namespace App\Modules\Pricing\Models;

use App\Models\User;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use App\Modules\Pricing\Enums\PriceVersionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceVersion extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $fillable = ['price_list_id', 'version', 'state', 'source_type', 'source_reference', 'source_hash', 'approval_record_id', 'requested_by', 'submitted_by', 'approved_by', 'effective_from', 'effective_to', 'submitted_at', 'approved_at', 'superseded_at', 'reason_text', 'lock_version'];

    protected $casts = [
        'state' => PriceVersionState::class,
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'superseded_at' => 'datetime',
        'lock_version' => 'integer',
        'version' => 'integer',
    ];

    /** @return BelongsTo<PriceList, $this> */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /** @return HasMany<PriceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PriceLine::class);
    }

    /** @return BelongsTo<ApprovalRecord, $this> */
    public function approvalRecord(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function documentStateColumn(): string
    {
        return 'state';
    }

    public function sourceBranchId(): ?int
    {
        $branchId = $this->lines()->value('branch_id');

        return $branchId === null ? null : (int) $branchId;
    }

    public function sourceStoreId(): ?int
    {
        $storeId = $this->lines()->value('store_id');

        return $storeId === null ? null : (int) $storeId;
    }
}
