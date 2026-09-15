<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Enums\ShiftState;
use App\Modules\Platform\Contracts\ImmutableSourceContract;
use App\Modules\Platform\Models\Concerns\GuardsApprovedDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PosShift extends Model implements ImmutableSourceContract
{
    use GuardsApprovedDocument;

    protected $table = 'pos_shifts';

    protected $fillable = [
        'branch_id', 'store_id', 'cash_drawer_id', 'cashier_id', 'opened_by', 'closed_by', 'variance_approved_by', 'variance_approval_record_id',
        'status', 'opening_cash', 'closing_cash', 'currency_code', 'idempotency_key',
        'company_name_ar_snapshot', 'company_name_en_snapshot',
        'branch_code_snapshot', 'branch_name_ar_snapshot', 'branch_name_en_snapshot',
        'store_code_snapshot', 'store_name_ar_snapshot', 'store_name_en_snapshot',
        'cash_drawer_code_snapshot', 'cash_drawer_name_ar_snapshot', 'cash_drawer_name_en_snapshot',
        'opening_document_number', 'closing_document_number',
        'opened_at', 'closed_at', 'submitted_at', 'variance_approved_at', 'variance_approval_note',
        'recount_count', 'lock_version', 'policy_notes',
    ];

    protected $casts = [
        'opening_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'variance_approved_at' => 'datetime',
        'recount_count' => 'integer',
        'lock_version' => 'integer',
        'status' => ShiftState::class,
    ];

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

    /** @return BelongsTo<CashDrawer, $this> */
    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /** The current/settling canonical approval request; history stays in Platform. */
    public function varianceApprovalRecord(): BelongsTo
    {
        return $this->belongsTo(ApprovalRecord::class, 'variance_approval_record_id');
    }

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'shift_id');
    }

    /** @return HasMany<CashMovement, $this> */
    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class, 'shift_id');
    }

    /** @return HasMany<ShiftClosingSubmission, $this> */
    public function closingSubmissions(): HasMany
    {
        return $this->hasMany(ShiftClosingSubmission::class, 'shift_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ShiftState::Open->value);
    }

    /**
     * Shifts still occupying a drawer or cashier.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (ShiftState $state): string => $state->value,
            array_filter(ShiftState::cases(), static fn (ShiftState $state): bool => $state->isActive()),
        ));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
