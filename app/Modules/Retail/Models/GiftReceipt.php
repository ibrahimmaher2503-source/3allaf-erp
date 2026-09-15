<?php

declare(strict_types=1);

namespace App\Modules\Retail\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GiftReceipt extends Model
{
    protected $fillable = ['sale_id', 'branch_id', 'store_id', 'issued_by', 'reference', 'status', 'used_return_id', 'used_by', 'used_at', 'idempotency_key', 'lock_version'];

    protected $casts = ['used_at' => 'datetime'];

    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function lines(): HasMany { return $this->hasMany(GiftReceiptLine::class); }
    public function printEvents(): HasMany { return $this->hasMany(GiftReceiptPrintEvent::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;
        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }
}
