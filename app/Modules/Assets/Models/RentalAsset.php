<?php

declare(strict_types=1);

namespace App\Modules\Assets\Models;

use App\Models\User;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

final class RentalAsset extends Model
{
    private bool $mutating = false;

    protected $fillable = [
        'public_id', 'code', 'name_ar', 'name_en', 'category', 'branch_id', 'store_id',
        'location', 'condition', 'status', 'cost_value', 'cost_currency', 'created_by',
        'updated_by', 'lock_version',
    ];

    protected $casts = ['cost_value' => 'decimal:2', 'lock_version' => 'integer'];

    protected static function booted(): void
    {
        static::creating(fn (self $asset): ?string => $asset->public_id ??= (string) Str::uuid());
        static::updating(function (self $asset): void {
            if (! $asset->mutating) {
                throw new LogicException('Rental assets may only change through named actions.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Rental asset history is preserved.'));
    }

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function store(): BelongsTo { return $this->belongsTo(Store::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reservations(): HasMany { return $this->hasMany(AssetReservation::class, 'asset_id'); }
    public function checkouts(): HasMany { return $this->hasMany(AssetCheckout::class, 'asset_id'); }
    public function returns(): HasMany { return $this->hasMany(AssetReturn::class, 'asset_id'); }
    public function events(): HasMany { return $this->hasMany(AssetEvent::class, 'asset_id'); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) return $query;

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('branch_id', $user->branchScopes()->where('status', 'active')->select('branch_id'))
                ->orWhereIn('store_id', $user->storeScopes()->where('status', 'active')->select('store_id'));
        });
    }

    /** @param array<string, mixed> $attributes */
    public function mutate(array $attributes): void
    {
        $this->mutating = true;
        try { $this->fill($attributes)->save(); } finally { $this->mutating = false; }
    }
}
