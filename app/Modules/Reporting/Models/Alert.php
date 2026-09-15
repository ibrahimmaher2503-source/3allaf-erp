<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Models;

use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Alert extends Model
{
    protected $fillable = ['alert_key', 'alert_type', 'severity', 'title', 'description', 'source_type', 'source_id', 'branch_id', 'store_id', 'status', 'acknowledged_by', 'acknowledged_at', 'resolved_at', 'metadata'];
    protected $casts = ['metadata' => 'array', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime'];
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
