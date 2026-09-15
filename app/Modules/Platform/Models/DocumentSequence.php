<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class DocumentSequence extends Model
{
    private bool $advancingCounter = false;

    protected $fillable = [
        'document_type',
        'scope_type',
        'scope_id',
        'scope_key',
        'prefix',
        'suffix',
        'padding_length',
        'next_value',
        'reset_rule',
        'last_reset_period',
        'status',
        'lock_version',
        'policy_notes',
    ];

    protected $casts = [
        'scope_id' => 'integer',
        'padding_length' => 'integer',
        'next_value' => 'integer',
        'lock_version' => 'integer',
    ];

    public function scopeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'scope_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->where('scope_type', 'company')
                ->orWhereIn('scope_id', $user->branchScopes()->where('status', 'active')->select('branch_id'));
        });
    }

    protected static function booted(): void
    {
        static::creating(function (self $sequence): void {
            $sequence->normalizeScopeIdentity();
        });

        static::updating(function (self $sequence): void {
            $sequence->normalizeScopeIdentity();
            if (($sequence->isDirty('next_value') || $sequence->isDirty('lock_version')) && ! $sequence->advancingCounter) {
                throw new LogicException('Document-sequence counters may only change through allocation or audited override actions.');
            }
        });
    }

    public static function scopeKeyFor(string $scopeType, ?int $scopeId = null): string
    {
        $scopeType = strtolower(trim($scopeType));
        if ($scopeType === 'company' && $scopeId === null) {
            return 'company';
        }
        if ($scopeType === 'branch' && $scopeId !== null && $scopeId > 0) {
            return 'branch:'.$scopeId;
        }

        throw new LogicException('Only company and branch document-sequence scopes are supported.');
    }

    public function normalizeScopeIdentity(): void
    {
        $scopeType = strtolower(trim((string) ($this->scope_type ?: 'company')));
        $scopeId = $this->scope_id !== null ? (int) $this->scope_id : null;
        $this->scope_type = $scopeType;
        $this->scope_id = $scopeType === 'company' ? null : $scopeId;
        $this->scope_key = self::scopeKeyFor($scopeType, $this->scope_id);
    }

    public function formatValue(int $value): string
    {
        return (string) ($this->prefix ?? '')
            .str_pad((string) $value, (int) $this->padding_length, '0', STR_PAD_LEFT)
            .(string) ($this->suffix ?? '');
    }

    public function currentPeriodKey(): ?string
    {
        return match ($this->reset_rule) {
            'daily' => now()->toDateString(),
            'monthly' => now()->format('Y-m'),
            'yearly' => now()->format('Y'),
            default => null,
        };
    }

    public function advanceCounter(int $nextValue, ?string $period = null): void
    {
        $this->advancingCounter = true;
        try {
            $attributes = [
                'next_value' => $nextValue,
                'lock_version' => $this->lock_version + 1,
            ];
            if ($period !== null) {
                $attributes['last_reset_period'] = $period;
            }
            $this->forceFill($attributes)->save();
        } finally {
            $this->advancingCounter = false;
        }
    }
}
