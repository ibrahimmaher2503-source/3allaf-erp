<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public const ALWAYS_REDACTED_FIELDS = [
        'password',
        'password_confirmation',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'reset_token',
        'token',
        'secret',
        'private_key',
    ];

    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'legacy_source_key',
        'category',
        'event',
        'actor_id',
        'actor_name',
        'source_type',
        'source_id',
        'branch_id',
        'store_id',
        'reason_code',
        'reason_text',
        'before_values',
        'after_values',
        'changed_fields',
        'metadata',
        'request_id',
        'created_at',
    ];

    protected $casts = [
        'before_values' => 'array',
        'after_values' => 'array',
        'changed_fields' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit records are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Audit records are append-only.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        $branchIds = $user->branchScopes()->where('status', 'active')->select('branch_id');
        $storeIds = $user->storeScopes()->where('status', 'active')->select('store_id');

        return $query->where(function (Builder $scope) use ($branchIds, $storeIds): void {
            $scope->whereIn('branch_id', $branchIds)
                ->orWhereIn('store_id', $storeIds);
        });
    }
}
