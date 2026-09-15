<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Platform\Enums\ApprovalState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ApprovalRecord extends Model
{
    private bool $transitioning = false;

    protected $fillable = [
        'source_type',
        'uuid',
        'source_id',
        'source_version',
        'source_hash',
        'requested_action',
        'request_permission',
        'decision_permission',
        'approval_state',
        'requester_id',
        'approver_id',
        'branch_id',
        'store_id',
        'reason_code',
        'reason_text',
        'decision_note',
        'limit_context',
        'request_id',
        'idempotency_key',
        'pending_key',
        'requested_at',
        'decided_at',
        'withdrawn_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'approval_state' => ApprovalState::class,
        'limit_context' => 'array',
        'requester_id' => 'integer',
        'approver_id' => 'integer',
        'branch_id' => 'integer',
        'store_id' => 'integer',
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            $record->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $record): void {
            if (! $record->transitioning) {
                throw new LogicException('Approval records may only change through a named transition action.');
            }
        });

        static::deleting(fn (): never => throw new LogicException('Approval records are preserved as approval history.'));
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @param array<string, mixed> $attributes */
    public function transitionTo(ApprovalState $state, array $attributes = []): void
    {
        if (! $this->approval_state->canTransitionTo($state)) {
            throw new LogicException('Terminal approval records cannot be changed.');
        }

        $this->transitioning = true;

        try {
            $this->fill([
                ...$attributes,
                'approval_state' => $state,
                'pending_key' => null,
            ])->save();
        } finally {
            $this->transitioning = false;
        }
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $branchIds = $user->branchScopes()->where('status', 'active')->select('branch_id');
        $storeIds = $user->storeScopes()->where('status', 'active')->select('store_id');

        return $query->where(function (Builder $scope) use ($user, $branchIds, $storeIds): void {
            $scope->where('requester_id', $user->id)
                ->orWhere(function (Builder $scoped) use ($branchIds, $storeIds): void {
                $scoped->whereIn('branch_id', $branchIds)
                        ->orWhereIn('store_id', $storeIds);
                });

            if ($user->hasPermission('company_settings.approve')) {
                $scope->orWhere(function (Builder $global): void {
                    $global->whereNull('branch_id')->whereNull('store_id');
                });
            }
        });
    }

    public function decisionPermission(): string
    {
        return $this->decision_permission ?: $this->source_type.'.approve';
    }
}
